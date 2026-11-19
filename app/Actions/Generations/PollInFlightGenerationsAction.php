<?php

declare(strict_types=1);

namespace App\Actions\Generations;

use App\Enums\GenerationStatus;
use App\Models\Generation;
use App\Models\User;
use App\Services\Pixelforge\PixelforgeClient;
use Throwable;

final class PollInFlightGenerationsAction
{
    public function __construct(
        private readonly PixelforgeClient $client,
        private readonly SyncGenerationStateAction $syncState,
    ) {}

    public function syncOne(Generation $generation): Generation
    {
        if ($generation->status->isTerminal() || $generation->pixelforge_id === null) {
            return $generation;
        }

        $remote = $this->client->getGeneration($generation->pixelforge_id);

        return $this->syncState->execute($generation, $remote);
    }

    public function syncForUser(User $user, int $limit = 10, bool $force = false): int
    {
        $cutoff = now()->utc()->subSeconds(5);

        $inFlight = $user->generations()
            ->whereIn('status', [GenerationStatus::Queued->value, GenerationStatus::Processing->value])
            ->whereNotNull('pixelforge_id')
            ->when(! $force, function ($query) use ($cutoff): void {
                $query->where(function ($sub) use ($cutoff): void {
                    $sub->whereNull('last_synced_at')
                        ->orWhere('last_synced_at', '<=', $cutoff);
                });
            })
            ->latest('created_at')
            ->limit($limit)
            ->get();

        $synced = 0;

        foreach ($inFlight as $generation) {
            try {
                $this->syncOne($generation);
                $synced++;
            } catch (Throwable) {
                // Keep polling other records if one remote lookup encounters a transient error.
            }
        }

        return $synced;
    }
}
