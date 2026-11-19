<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Generations\SyncGenerationStateAction;
use App\Data\PixelforgeGenerationData;
use App\Enums\CreditTransactionType;
use App\Enums\GenerationStatus;
use App\Models\Generation;
use App\Models\PixelforgeWebhookEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

final class PixelforgeWebhookTest extends TestCase
{
    use RefreshDatabase;

    private string $secret = 'whsec_test_secret_123';

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.pixelforge.webhook_secret' => $this->secret]);
        config(['services.pixelforge.webhook_tolerance_seconds' => 300]);
        config(['services.pixelforge.markup_percent' => 25]);
    }

    public function test_rejects_invalid_signature_and_stale_timestamp(): void
    {
        $raw = json_encode(['id' => 'evt_bad', 'type' => 'ping'], JSON_THROW_ON_ERROR);

        $this->call('POST', '/webhooks/pixelforge', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_PIXELFORGE_SIGNATURE' => 't='.time().',v1=deadbeef',
        ], $raw)->assertStatus(400);

        $staleTs = time() - 600;
        $staleSig = hash_hmac('sha256', "{$staleTs}.{$raw}", $this->secret);

        $this->call('POST', '/webhooks/pixelforge', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_PIXELFORGE_SIGNATURE' => "t={$staleTs},v1={$staleSig}",
        ], $raw)->assertStatus(400);
    }

    public function test_handles_ping_event_cleanly(): void
    {
        $response = $this->sendSignedWebhook([
            'id' => 'evt_ping_01',
            'object' => 'event',
            'type' => 'ping',
            'created_at' => '2026-11-19T00:00:00Z',
            'data' => ['object' => []],
        ]);

        $response->assertNoContent();
        $this->assertDatabaseHas('pixelforge_webhook_events', [
            'event_id' => 'evt_ping_01',
            'event_type' => 'ping',
            'pixelforge_generation_id' => null,
        ]);
    }

    public function test_deduplicates_duplicate_webhook_delivery_without_double_charging(): void
    {
        $user = User::factory()->create([
            'credit_balance_picodollars' => 1_000_000_000_000,
            'reserved_balance_picodollars' => 500_000_000_000,
        ]);

        $generation = Generation::factory()->for($user)->create([
            'pixelforge_id' => 'gen_dup_01',
            'status' => GenerationStatus::Processing,
            'reserved_user_cost_picodollars' => 500_000_000_000,
        ]);

        $payload = $this->succeededPayload('evt_dup_01', 'gen_dup_01', $generation->id);

        $this->sendSignedWebhook($payload, attempt: 1)->assertNoContent();
        $this->sendSignedWebhook($payload, attempt: 2)->assertNoContent();

        $user->refresh();
        $generation->refresh();

        $this->assertSame(GenerationStatus::Succeeded, $generation->status);
        $this->assertSame(305_510_000_000, $generation->actual_provider_cost_picodollars);
        $this->assertSame(381_887_500_000, $generation->actual_user_cost_picodollars);
        $this->assertSame(618_112_500_000, $user->credit_balance_picodollars);
        $this->assertSame(0, $user->reserved_balance_picodollars);
        $this->assertSame(1, PixelforgeWebhookEvent::where('event_id', 'evt_dup_01')->count());
        $this->assertSame(1, $user->creditTransactions()->where('type', CreditTransactionType::Charge)->count());
    }

    public function test_ignores_out_of_order_processing_event_after_succeeded(): void
    {
        $user = User::factory()->create([
            'credit_balance_picodollars' => 1_000_000_000_000,
            'reserved_balance_picodollars' => 500_000_000_000,
        ]);

        $generation = Generation::factory()->for($user)->create([
            'pixelforge_id' => 'gen_ooo_01',
            'status' => GenerationStatus::Queued,
            'reserved_user_cost_picodollars' => 500_000_000_000,
        ]);

        $this->sendSignedWebhook($this->succeededPayload('evt_ooo_2', 'gen_ooo_01', $generation->id))
            ->assertNoContent();

        $this->sendSignedWebhook([
            'id' => 'evt_ooo_1',
            'object' => 'event',
            'type' => 'generation.processing',
            'created_at' => '2026-11-19T00:00:05Z',
            'data' => [
                'object' => [
                    'id' => 'gen_ooo_01',
                    'object' => 'generation',
                    'status' => 'processing',
                    'prompt' => 'Test',
                    'size' => '1536x1024',
                    'quality' => 'high',
                    'reference_images' => [],
                    'metadata' => ['local_id' => $generation->id],
                    'rates' => [
                        'input_text_tokens' => '5.00',
                        'input_image_tokens' => '10.00',
                        'output_tokens' => '40.00',
                        'reasoning_tokens' => '12.50',
                    ],
                ],
            ],
        ])->assertNoContent();

        $generation->refresh();
        $this->assertSame(GenerationStatus::Succeeded, $generation->status);
        $this->assertSame(381_887_500_000, $generation->actual_user_cost_picodollars);
    }

    public function test_handles_early_webhook_arriving_before_post_response_returns(): void
    {
        $user = User::factory()->create([
            'credit_balance_picodollars' => 1_000_000_000_000,
            'reserved_balance_picodollars' => 500_000_000_000,
        ]);

        // Local row created right before HTTP POST returns (pixelforge_id still null).
        $generation = Generation::factory()->for($user)->create([
            'pixelforge_id' => null,
            'status' => GenerationStatus::Submitting,
            'reserved_user_cost_picodollars' => 500_000_000_000,
        ]);

        $this->sendSignedWebhook($this->succeededPayload('evt_early_1', 'gen_early_01', $generation->id))
            ->assertNoContent();

        $generation->refresh();
        $this->assertSame(GenerationStatus::Succeeded, $generation->status);
        $this->assertSame('gen_early_01', $generation->pixelforge_id);

        // Now simulate delayed POST /v1/generations 202 queued response arriving afterward.
        app(SyncGenerationStateAction::class)->execute(
            $generation,
            PixelforgeGenerationData::fromArray([
                'id' => 'gen_early_01',
                'status' => 'queued',
                'prompt' => 'Test',
                'size' => '1536x1024',
                'quality' => 'high',
                'rates' => [
                    'input_text_tokens' => '5.00',
                    'input_image_tokens' => '10.00',
                    'output_tokens' => '40.00',
                    'reasoning_tokens' => '12.50',
                ],
            ]),
        );

        $generation->refresh();
        $this->assertSame(GenerationStatus::Succeeded, $generation->status);
        $this->assertSame(381_887_500_000, $generation->actual_user_cost_picodollars);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function sendSignedWebhook(array $payload, int $attempt = 1): TestResponse
    {
        $raw = json_encode($payload, JSON_THROW_ON_ERROR);
        $ts = time();
        $sig = hash_hmac('sha256', "{$ts}.{$raw}", $this->secret);

        return $this->call('POST', '/webhooks/pixelforge', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_PIXELFORGE_SIGNATURE' => "t={$ts},v1={$sig}",
            'HTTP_PIXELFORGE_EVENT_ID' => (string) ($payload['id'] ?? ''),
            'HTTP_PIXELFORGE_DELIVERY_ATTEMPT' => (string) $attempt,
        ], $raw);
    }

    /**
     * @return array<string, mixed>
     */
    private function succeededPayload(string $eventId, string $pixelforgeId, string $localId): array
    {
        return [
            'id' => $eventId,
            'object' => 'event',
            'type' => 'generation.succeeded',
            'created_at' => '2026-11-19T00:00:29Z',
            'data' => [
                'object' => [
                    'id' => $pixelforgeId,
                    'object' => 'generation',
                    'status' => 'succeeded',
                    'prompt' => 'Lighthouse',
                    'size' => '1536x1024',
                    'quality' => 'high',
                    'reference_images' => ['https://example.com/style.png'],
                    'metadata' => ['local_id' => $localId],
                    'image_url' => 'https://pixelforge.promptedit.dev/images/test.png',
                    'image_url_expires_at' => '2026-11-20T00:00:29Z',
                    'usage' => [
                        'input_text_tokens' => 12,
                        'input_image_tokens' => 765,
                        'output_tokens' => 6450,
                        'reasoning_tokens' => 3184,
                        'total_tokens' => 10411,
                    ],
                    'rates' => [
                        'input_text_tokens' => '5.00',
                        'input_image_tokens' => '10.00',
                        'output_tokens' => '40.00',
                        'reasoning_tokens' => '12.50',
                    ],
                    'error' => null,
                    'created_at' => '2026-11-19T00:00:11Z',
                    'started_at' => '2026-11-19T00:00:13Z',
                    'completed_at' => '2026-11-19T00:00:29Z',
                ],
            ],
        ];
    }
}
