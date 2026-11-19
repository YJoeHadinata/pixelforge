<?php

declare(strict_types=1);

namespace App\Actions\Generations;

use App\Data\PixelforgeGenerationData;
use App\Models\Generation;
use Illuminate\Support\Facades\DB;

final class ProcessPixelforgeWebhookAction
{
    public function __construct(
        private readonly SyncGenerationStateAction $syncState,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function execute(array $payload, int $deliveryAttempt = 1): void
    {
        $eventId = (string) ($payload['id'] ?? '');
        $eventType = (string) ($payload['type'] ?? '');

        if ($eventId === '' || $eventType === '') {
            return;
        }

        DB::transaction(function () use ($payload, $eventId, $eventType, $deliveryAttempt): void {
            /** @var array<string, mixed>|null $object */
            $object = is_array($payload['data']['object'] ?? null) ? $payload['data']['object'] : null;
            $pixelforgeGenerationId = $eventType !== 'ping' && isset($object['id'])
                ? (string) $object['id']
                : null;

            $now = now()->utc();
            $inserted = DB::table('pixelforge_webhook_events')->insertOrIgnore([
                'event_id' => $eventId,
                'event_type' => $eventType,
                'pixelforge_generation_id' => $pixelforgeGenerationId,
                'delivery_attempt' => max(1, $deliveryAttempt),
                'payload' => json_encode($payload, JSON_THROW_ON_ERROR),
                'processed_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            // Deduplicate identical event IDs delivered multiple times.
            if ($inserted === 0 || $eventType === 'ping' || $object === null) {
                return;
            }

            $remote = PixelforgeGenerationData::fromArray($object);
            $generation = $this->findMatchingGeneration($remote);

            if ($generation === null) {
                return;
            }

            $this->syncState->execute($generation, $remote);
        });
    }

    private function findMatchingGeneration(PixelforgeGenerationData $remote): ?Generation
    {
        if ($remote->id !== '') {
            $byProviderId = Generation::where('pixelforge_id', $remote->id)->first();
            if ($byProviderId !== null) {
                return $byProviderId;
            }
        }

        $localId = $remote->localGenerationId();
        if ($localId !== null && $localId !== '') {
            return Generation::find($localId);
        }

        return null;
    }
}
