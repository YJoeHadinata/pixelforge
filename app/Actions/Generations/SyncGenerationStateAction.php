<?php

declare(strict_types=1);

namespace App\Actions\Generations;

use App\Actions\Credits\ReleaseReservedCreditsAction;
use App\Actions\Credits\SettleGenerationCreditsAction;
use App\Data\PixelforgeGenerationData;
use App\Enums\GenerationStatus;
use App\Models\Generation;
use App\Services\Pixelforge\PixelforgeCostCalculator;
use Illuminate\Support\Facades\DB;

final class SyncGenerationStateAction
{
    public function __construct(
        private readonly PixelforgeCostCalculator $calculator,
        private readonly SettleGenerationCreditsAction $settleCredits,
        private readonly ReleaseReservedCreditsAction $releaseCredits,
    ) {}

    public function execute(Generation $generation, PixelforgeGenerationData $remote): Generation
    {
        return DB::transaction(function () use ($generation, $remote): Generation {
            /** @var Generation $locked */
            $locked = Generation::whereKey($generation->id)->lockForUpdate()->firstOrFail();

            if ($locked->pixelforge_id === null && $remote->id !== '') {
                $locked->pixelforge_id = $remote->id;
            }

            if ($remote->rates->inputTextTokens !== '0.00' || empty($locked->rates_snapshot)) {
                $locked->rates_snapshot = $remote->rates->toArray();
            }

            $locked->pixelforge_created_at ??= $remote->createdAt;
            $locked->pixelforge_started_at ??= $remote->startedAt;
            $locked->pixelforge_completed_at ??= $remote->completedAt;
            $locked->last_synced_at = now()->utc();

            if (! $locked->status->canTransitionTo($remote->status)) {
                $locked->save();

                return $locked;
            }

            $locked->status = $remote->status;

            if ($remote->status === GenerationStatus::Succeeded) {
                $this->applySuccess($locked, $remote);
                $locked->save();
                $this->settleCredits->execute($locked, (int) ($locked->actual_user_cost_picodollars ?? 0));

                return $locked->fresh() ?? $locked;
            }

            if ($remote->status === GenerationStatus::Failed) {
                $this->applyFailure($locked, $remote);
                $locked->save();
                $this->releaseCredits->execute($locked, 'Released reservation for failed generation');

                return $locked->fresh() ?? $locked;
            }

            $locked->error_code = null;
            $locked->error_message = null;
            $locked->save();

            return $locked;
        });
    }

    private function applySuccess(Generation $locked, PixelforgeGenerationData $remote): void
    {
        if ($remote->usage !== null) {
            $locked->usage = $remote->usage->toArray();
            $actual = $this->calculator->calculateActual($remote->usage, $remote->rates);
            $locked->actual_provider_cost_picodollars = $actual->providerCostPicodollars;
            $locked->actual_user_cost_picodollars = $actual->userCostPicodollars;
            $locked->profit_picodollars = $actual->profitPicodollars;
        } else {
            $locked->actual_provider_cost_picodollars = 0;
            $locked->actual_user_cost_picodollars = 0;
            $locked->profit_picodollars = 0;
        }

        $locked->image_url = $remote->imageUrl;
        $locked->image_url_expires_at = $remote->imageUrlExpiresAt;
        $locked->error_code = null;
        $locked->error_message = null;
    }

    private function applyFailure(Generation $locked, PixelforgeGenerationData $remote): void
    {
        $locked->actual_provider_cost_picodollars = 0;
        $locked->actual_user_cost_picodollars = 0;
        $locked->profit_picodollars = 0;
        $locked->error_code = $remote->errorCode ?? 'generation_failed';
        $locked->error_message = $remote->errorMessage ?? 'The generation failed on Pixelforge.';
    }
}
