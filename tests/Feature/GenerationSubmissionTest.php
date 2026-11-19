<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Generations\ProcessPixelforgeWebhookAction;
use App\Enums\GenerationStatus;
use App\Jobs\DispatchQueuedGenerationJob;
use App\Models\CreditTransaction;
use App\Models\Generation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Sleep;
use Tests\TestCase;

final class GenerationSubmissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.pixelforge.api_key' => 'pf_test_key']);
        config(['services.pixelforge.base_url' => 'https://pixelforge.promptedit.dev/v1']);
        config(['services.pixelforge.markup_percent' => 25]);
    }

    public function test_submits_generation_reserves_max_cost_and_stores_queued_row(): void
    {
        Http::fake([
            'https://pixelforge.promptedit.dev/v1/pricing' => Http::response($this->pricingResponse(), 200),
            'https://pixelforge.promptedit.dev/v1/generations' => Http::response([
                'id' => 'gen_live_01',
                'object' => 'generation',
                'status' => 'queued',
                'prompt' => 'A lighthouse [succeed]',
                'size' => '1024x1024',
                'quality' => 'medium',
                'reference_images' => [],
                'metadata' => ['local_id' => 'ignored'],
                'rates' => $this->pricingResponse()['rates'],
                'created_at' => '2026-11-19T00:00:00Z',
            ], 202),
        ]);

        $user = User::factory()->create([
            'credit_balance_picodollars' => 2_000_000_000_000,
        ]);

        $response = $this->actingAs($user)->post('/generations', [
            'prompt' => 'A lighthouse [succeed]',
            'size' => '1024x1024',
            'quality' => 'medium',
            'reference_images' => [''],
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $user->refresh();
        $generation = Generation::where('user_id', $user->id)->firstOrFail();

        $this->assertSame('gen_live_01', $generation->pixelforge_id);
        $this->assertSame(GenerationStatus::Queued, $generation->status);
        $this->assertGreaterThan(0, $user->reserved_balance_picodollars);
        $this->assertSame($generation->reserved_user_cost_picodollars, $user->reserved_balance_picodollars);
    }

    public function test_blocked_submission_rolls_back_placeholder_and_reservation(): void
    {
        Http::fake([
            'https://pixelforge.promptedit.dev/v1/pricing' => Http::response($this->pricingResponse(), 200),
            'https://pixelforge.promptedit.dev/v1/generations' => Http::response([
                'error' => [
                    'type' => 'invalid_request_error',
                    'code' => 'content_policy_violation',
                    'message' => 'The prompt was rejected by content policy.',
                ],
            ], 400),
        ]);

        $user = User::factory()->create([
            'credit_balance_picodollars' => 2_000_000_000_000,
        ]);

        $response = $this->actingAs($user)->post('/generations', [
            'prompt' => 'Bad prompt [blocked]',
            'size' => '1024x1024',
            'quality' => 'low',
        ]);

        $response->assertSessionHasErrors('prompt');
        $user->refresh();

        $this->assertSame(0, Generation::count());
        $this->assertSame(0, CreditTransaction::count());
        $this->assertSame(0, $user->reserved_balance_picodollars);
        $this->assertSame(2_000_000_000_000, $user->availableBalancePicodollars());
    }

    public function test_overloaded_submission_rolls_back_after_retry_cap(): void
    {
        Sleep::fake();

        Http::fake([
            'https://pixelforge.promptedit.dev/v1/pricing' => Http::response($this->pricingResponse(), 200),
            'https://pixelforge.promptedit.dev/v1/generations' => Http::response([
                'error' => [
                    'type' => 'api_error',
                    'code' => 'overloaded',
                    'message' => 'Pixelforge is temporarily overloaded.',
                ],
            ], 503, ['Retry-After' => '2']),
        ]);

        $user = User::factory()->create([
            'credit_balance_picodollars' => 2_000_000_000_000,
        ]);

        $response = $this->actingAs($user)->post('/generations', [
            'prompt' => 'Heavy prompt [overloaded]',
            'size' => '1024x1024',
            'quality' => 'low',
        ]);

        $response->assertSessionHasErrors('prompt');
        $user->refresh();

        $this->assertSame(0, Generation::count());
        $this->assertSame(0, $user->reserved_balance_picodollars);
    }

    public function test_async_fail_keeps_failed_history_with_zero_cost_and_releases_reserve(): void
    {
        Http::fake([
            'https://pixelforge.promptedit.dev/v1/pricing' => Http::response($this->pricingResponse(), 200),
            'https://pixelforge.promptedit.dev/v1/generations' => Http::response([
                'id' => 'gen_fail_01',
                'object' => 'generation',
                'status' => 'queued',
                'prompt' => 'Test [fail]',
                'size' => '1024x1024',
                'quality' => 'low',
                'reference_images' => [],
                'metadata' => [],
                'rates' => $this->pricingResponse()['rates'],
            ], 202),
        ]);

        $user = User::factory()->create([
            'credit_balance_picodollars' => 2_000_000_000_000,
        ]);

        $this->actingAs($user)->post('/generations', [
            'prompt' => 'Test [fail]',
            'size' => '1024x1024',
            'quality' => 'low',
        ])->assertRedirect();

        $generation = Generation::firstOrFail();

        app(ProcessPixelforgeWebhookAction::class)->execute([
            'id' => 'evt_fail_01',
            'type' => 'generation.failed',
            'data' => [
                'object' => [
                    'id' => 'gen_fail_01',
                    'status' => 'failed',
                    'prompt' => 'Test [fail]',
                    'size' => '1024x1024',
                    'quality' => 'low',
                    'rates' => $this->pricingResponse()['rates'],
                    'error' => [
                        'code' => 'generation_failed',
                        'message' => 'Simulated failure.',
                    ],
                    'completed_at' => '2026-11-19T00:01:00Z',
                ],
            ],
        ]);

        $user->refresh();
        $generation->refresh();

        $this->assertSame(GenerationStatus::Failed, $generation->status);
        $this->assertSame(0, $generation->actual_provider_cost_picodollars);
        $this->assertSame(0, $generation->actual_user_cost_picodollars);
        $this->assertSame(0, $user->reserved_balance_picodollars);
        $this->assertSame(2_000_000_000_000, $user->credit_balance_picodollars);
    }

    public function test_concurrency_limit_429_queues_locally_and_dispatches_retry_job(): void
    {
        Queue::fake();

        Http::fake([
            'https://pixelforge.promptedit.dev/v1/pricing' => Http::response($this->pricingResponse(), 200),
            'https://pixelforge.promptedit.dev/v1/generations' => Http::response([
                'error' => [
                    'type' => 'rate_limit_error',
                    'code' => 'concurrency_limit_exceeded',
                    'message' => 'Too many generations queued or processing.',
                ],
            ], 429, ['Retry-After' => '5']),
        ]);

        $user = User::factory()->create([
            'credit_balance_picodollars' => 2_000_000_000_000,
        ]);

        $this->actingAs($user)->post('/generations', [
            'prompt' => 'Queued burst prompt',
            'size' => '1024x1024',
            'quality' => 'low',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $generation = Generation::firstOrFail();
        $this->assertSame(GenerationStatus::QueuedLocally, $generation->status);
        Queue::assertPushed(DispatchQueuedGenerationJob::class);
    }

    public function test_blocks_submission_when_credit_balance_is_insufficient(): void
    {
        Http::fake([
            'https://pixelforge.promptedit.dev/v1/pricing' => Http::response($this->pricingResponse(), 200),
        ]);

        $user = User::factory()->create([
            'credit_balance_picodollars' => 10_000_000_000, // $0.01 (too low for high quality)
        ]);

        $response = $this->actingAs($user)->post('/generations', [
            'prompt' => 'Expensive high quality',
            'size' => '1536x1024',
            'quality' => 'high',
        ]);

        $response->assertSessionHasErrors('credits');
        $this->assertSame(0, Generation::count());
        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/generations'));
    }

    public function test_user_cannot_sync_another_users_generation(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $generation = Generation::factory()->for($owner)->create(['pixelforge_id' => 'gen_owned']);

        $this->actingAs($other)
            ->post(route('generations.sync', $generation))
            ->assertForbidden();
    }

    /**
     * @return array<string, mixed>
     */
    private function pricingResponse(): array
    {
        return [
            'object' => 'pricing',
            'currency' => 'usd',
            'unit' => 'per_1m_tokens',
            'rates' => [
                'input_text_tokens' => '5.00',
                'input_image_tokens' => '10.00',
                'output_tokens' => '40.00',
                'reasoning_tokens' => '12.50',
            ],
            'tokens' => [
                'characters_per_text_token' => 4,
                'text_token_variance' => '0.15',
                'per_reference_image' => 765,
                'output' => [
                    'low' => ['1024x1024' => 280, '1536x1024' => 420, '1024x1536' => 420],
                    'medium' => ['1024x1024' => 1100, '1536x1024' => 1650, '1024x1536' => 1650],
                    'high' => ['1024x1024' => 4300, '1536x1024' => 6450, '1024x1536' => 6450],
                ],
                'reasoning' => [
                    'low' => ['min' => 0, 'max' => 500],
                    'medium' => ['min' => 200, 'max' => 2000],
                    'high' => ['min' => 1000, 'max' => 8000],
                ],
            ],
        ];
    }
}
