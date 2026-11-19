<?php

declare(strict_types=1);

namespace App\Actions\Generations;

use App\Actions\Credits\ReserveCreditsAction;
use App\Enums\GenerationStatus;
use App\Enums\ImageQuality;
use App\Enums\ImageSize;
use App\Jobs\DispatchQueuedGenerationJob;
use App\Models\CreditTransaction;
use App\Models\Generation;
use App\Models\User;
use App\Services\Pixelforge\Exceptions\PixelforgeApiException;
use App\Services\Pixelforge\PixelforgeClient;
use App\Services\Pixelforge\PixelforgeCostCalculator;
use App\Services\Pixelforge\PixelforgePricingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class SubmitGenerationAction
{
    public function __construct(
        private readonly PixelforgePricingService $pricingService,
        private readonly PixelforgeCostCalculator $calculator,
        private readonly ReserveCreditsAction $reserveCredits,
        private readonly PixelforgeClient $client,
        private readonly SyncGenerationStateAction $syncState,
    ) {}

    /**
     * @param  list<string>  $referenceImages
     */
    public function execute(
        User $user,
        string $prompt,
        ImageSize $size,
        ImageQuality $quality,
        array $referenceImages = [],
    ): Generation {
        $pricing = $this->pricingService->current();
        $cleanImages = array_values(array_filter(
            array_map(static fn (string $url): string => trim($url), $referenceImages),
            static fn (string $url): bool => $url !== '',
        ));

        $estimate = $this->calculator->estimate(
            prompt: $prompt,
            size: $size,
            quality: $quality,
            referenceImageCount: count($cleanImages),
            pricing: $pricing,
        );

        /** @var Generation $generation */
        $generation = DB::transaction(function () use ($user, $prompt, $size, $quality, $cleanImages, $pricing, $estimate): Generation {
            /** @var User $lockedUser */
            $lockedUser = User::whereKey($user->id)->lockForUpdate()->firstOrFail();

            $localId = (string) Str::uuid();
            $created = Generation::create([
                'id' => $localId,
                'user_id' => $lockedUser->id,
                'idempotency_key' => (string) Str::uuid(),
                'status' => GenerationStatus::Submitting,
                'prompt' => $prompt,
                'size' => $size,
                'quality' => $quality,
                'reference_images' => $cleanImages,
                'rates_snapshot' => $pricing->rates->toArray(),
                'estimated_min_provider_cost_picodollars' => $estimate->minProviderCostPicodollars,
                'estimated_expected_provider_cost_picodollars' => $estimate->expectedProviderCostPicodollars,
                'estimated_max_provider_cost_picodollars' => $estimate->maxProviderCostPicodollars,
                'estimated_min_user_cost_picodollars' => $estimate->minUserCostPicodollars,
                'estimated_expected_user_cost_picodollars' => $estimate->expectedUserCostPicodollars,
                'estimated_max_user_cost_picodollars' => $estimate->maxUserCostPicodollars,
                'reserved_user_cost_picodollars' => $estimate->maxUserCostPicodollars,
            ]);

            $this->reserveCredits->execute(
                user: $lockedUser,
                amountPicodollars: $estimate->maxUserCostPicodollars,
                generationId: $created->id,
            );

            return $created;
        });

        try {
            $remote = $this->client->createGeneration(
                prompt: $prompt,
                size: $size,
                quality: $quality,
                referenceImages: $cleanImages,
                metadata: [
                    'local_id' => $generation->id,
                    'user_id' => (string) $user->id,
                ],
                idempotencyKey: $generation->idempotency_key,
            );

            return $this->syncState->execute($generation, $remote);
        } catch (PixelforgeApiException $e) {
            if ($e->isConcurrencyOrRateLimit()) {
                return $this->enqueueLocally($generation, $e);
            }

            $this->rollbackRejectedSubmission($generation);

            throw $e;
        }
    }

    private function enqueueLocally(Generation $generation, PixelforgeApiException $exception): Generation
    {
        $updated = DB::transaction(function () use ($generation, $exception): Generation {
            /** @var Generation $locked */
            $locked = Generation::whereKey($generation->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === GenerationStatus::Submitting) {
                $locked->status = GenerationStatus::QueuedLocally;
                $locked->error_code = $exception->errorCode;
                $locked->error_message = $exception->errorMessage;
                $locked->last_synced_at = now()->utc();
                $locked->save();
            }

            return $locked;
        });

        if ($updated->status === GenerationStatus::QueuedLocally) {
            $delay = max(2, min(30, $exception->retryAfterSeconds ?? 5));
            DispatchQueuedGenerationJob::dispatch($updated->id)->delay(now()->addSeconds($delay));
        }

        return $updated;
    }

    private function rollbackRejectedSubmission(Generation $generation): void
    {
        DB::transaction(function () use ($generation): void {
            /** @var Generation|null $locked */
            $locked = Generation::whereKey($generation->id)->lockForUpdate()->first();

            // Protect if an early webhook already attached pixelforge_id or transitioned status.
            if ($locked === null || $locked->pixelforge_id !== null || $locked->status !== GenerationStatus::Submitting) {
                return;
            }

            /** @var User $lockedUser */
            $lockedUser = User::whereKey($locked->user_id)->lockForUpdate()->firstOrFail();
            $reserved = (int) $locked->reserved_user_cost_picodollars;

            if ($reserved > 0) {
                $lockedUser->reserved_balance_picodollars = max(
                    0,
                    (int) $lockedUser->reserved_balance_picodollars - $reserved,
                );
                $lockedUser->save();
            }

            CreditTransaction::where('generation_id', $locked->id)->delete();
            $locked->delete();
        });
    }
}
