<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\Credits\ReleaseReservedCreditsAction;
use App\Actions\Generations\SyncGenerationStateAction;
use App\Enums\GenerationStatus;
use App\Models\Generation;
use App\Services\Pixelforge\Exceptions\PixelforgeApiException;
use App\Services\Pixelforge\PixelforgeClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

final class DispatchQueuedGenerationJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    private const MAX_RETRIES = 12;

    public function __construct(
        public readonly string $generationId,
    ) {}

    public function handle(
        PixelforgeClient $client,
        SyncGenerationStateAction $syncState,
        ReleaseReservedCreditsAction $releaseCredits,
    ): void {
        /** @var Generation|null $generation */
        $generation = Generation::find($this->generationId);
        if ($generation === null || $generation->status !== GenerationStatus::QueuedLocally) {
            return;
        }

        try {
            $remote = $client->createGeneration(
                prompt: $generation->prompt,
                size: $generation->size,
                quality: $generation->quality,
                referenceImages: $generation->reference_images ?? [],
                metadata: [
                    'local_id' => $generation->id,
                    'user_id' => (string) $generation->user_id,
                ],
                idempotencyKey: $generation->idempotency_key,
                maxOverloadRetries: 1,
            );

            $syncState->execute($generation, $remote);
        } catch (PixelforgeApiException $e) {
            if ($e->isRetryableTransient() && $generation->retry_count < self::MAX_RETRIES) {
                $generation->retry_count++;
                $generation->last_synced_at = now()->utc();
                $generation->save();

                $delay = max(2, min(30, $e->retryAfterSeconds ?? 5));
                self::dispatch($generation->id)->delay(now()->addSeconds($delay));

                return;
            }

            $this->failQueuedGeneration($generation, $e, $releaseCredits);
        }
    }

    private function failQueuedGeneration(
        Generation $generation,
        PixelforgeApiException $exception,
        ReleaseReservedCreditsAction $releaseCredits,
    ): void {
        DB::transaction(function () use ($generation, $exception, $releaseCredits): void {
            /** @var Generation $locked */
            $locked = Generation::whereKey($generation->id)->lockForUpdate()->firstOrFail();

            if ($locked->status->isTerminal()) {
                return;
            }

            $locked->status = GenerationStatus::Failed;
            $locked->actual_provider_cost_picodollars = 0;
            $locked->actual_user_cost_picodollars = 0;
            $locked->profit_picodollars = 0;
            $locked->error_code = $exception->errorCode;
            $locked->error_message = $exception->errorMessage;
            $locked->last_synced_at = now()->utc();
            $locked->save();

            $releaseCredits->execute($locked, "Released reservation after queued submission error ({$exception->errorCode})");
        });
    }
}
