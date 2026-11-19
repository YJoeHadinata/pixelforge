<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ImageQuality;
use App\Enums\ImageSize;
use App\Services\Pixelforge\Exceptions\PixelforgeApiException;
use App\Services\Pixelforge\PixelforgeClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Tests\TestCase;

final class PixelforgeClientTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['services.pixelforge.api_key' => 'pf_test_key']);
        config(['services.pixelforge.base_url' => 'https://pixelforge.promptedit.dev/v1']);
    }

    public function test_omits_empty_reference_images_and_casts_metadata_to_strings(): void
    {
        Http::fake([
            'https://pixelforge.promptedit.dev/v1/generations' => Http::response([
                'id' => 'gen_01ABC',
                'object' => 'generation',
                'status' => 'queued',
                'prompt' => 'Test prompt',
                'size' => '1024x1024',
                'quality' => 'medium',
                'reference_images' => [],
                'metadata' => ['local_id' => 'uuid-1', 'user_id' => '42'],
                'image_url' => null,
                'image_url_expires_at' => null,
                'usage' => null,
                'rates' => [
                    'input_text_tokens' => '5.00',
                    'input_image_tokens' => '10.00',
                    'output_tokens' => '40.00',
                    'reasoning_tokens' => '12.50',
                ],
                'error' => null,
                'created_at' => '2026-11-19T00:00:00Z',
                'started_at' => null,
                'completed_at' => null,
            ], 202),
        ]);

        $result = app(PixelforgeClient::class)->createGeneration(
            prompt: 'Test prompt',
            size: ImageSize::Square,
            quality: ImageQuality::Medium,
            referenceImages: [],
            metadata: ['local_id' => 'uuid-1', 'user_id' => '42'],
            idempotencyKey: 'idem-123',
        );

        $this->assertSame('gen_01ABC', $result->id);

        Http::assertSent(function (Request $request): bool {
            $data = $request->data();

            return $request->hasHeader('Idempotency-Key', 'idem-123')
                && ! array_key_exists('reference_images', $data)
                && $data['metadata']['user_id'] === '42';
        });
    }

    public function test_retries_transient_503_overloaded_with_same_idempotency_key(): void
    {
        Sleep::fake();

        Http::fake([
            'https://pixelforge.promptedit.dev/v1/generations' => Http::sequence()
                ->push([
                    'error' => [
                        'type' => 'api_error',
                        'code' => 'overloaded',
                        'message' => 'Temporarily overloaded.',
                    ],
                ], 503, ['Retry-After' => '2'])
                ->push([
                    'id' => 'gen_01RETRY',
                    'object' => 'generation',
                    'status' => 'queued',
                    'prompt' => 'Test',
                    'size' => '1024x1024',
                    'quality' => 'low',
                    'reference_images' => [],
                    'metadata' => [],
                    'rates' => [
                        'input_text_tokens' => '5.00',
                        'input_image_tokens' => '10.00',
                        'output_tokens' => '40.00',
                        'reasoning_tokens' => '12.50',
                    ],
                ], 202),
        ]);

        $result = app(PixelforgeClient::class)->createGeneration(
            prompt: 'Test',
            size: ImageSize::Square,
            quality: ImageQuality::Low,
            referenceImages: [],
            metadata: [],
            idempotencyKey: 'idem-retry',
        );

        $this->assertSame('gen_01RETRY', $result->id);
        Http::assertSentCount(2);
    }

    public function test_stops_retrying_persistent_503_overloaded_and_throws(): void
    {
        Sleep::fake();

        Http::fake([
            'https://pixelforge.promptedit.dev/v1/generations' => Http::response([
                'error' => [
                    'type' => 'api_error',
                    'code' => 'overloaded',
                    'message' => 'Pixelforge is temporarily overloaded.',
                ],
            ], 503, ['Retry-After' => '2']),
        ]);

        try {
            app(PixelforgeClient::class)->createGeneration(
                prompt: 'Test [overloaded]',
                size: ImageSize::Square,
                quality: ImageQuality::Low,
                referenceImages: [],
                metadata: [],
                idempotencyKey: 'idem-overloaded',
                maxOverloadRetries: 2,
            );
            $this->fail('Expected PixelforgeApiException was not thrown.');
        } catch (PixelforgeApiException $e) {
            $this->assertSame(503, $e->statusCode);
            $this->assertSame('overloaded', $e->errorCode);
            Http::assertSentCount(3);
        }
    }

    public function test_does_not_retry_400_blocked_or_429_concurrency_limit(): void
    {
        Http::fake([
            'https://pixelforge.promptedit.dev/v1/generations' => Http::sequence()
                ->push([
                    'error' => [
                        'type' => 'invalid_request_error',
                        'code' => 'content_policy_violation',
                        'message' => 'Blocked prompt.',
                    ],
                ], 400)
                ->push([
                    'error' => [
                        'type' => 'rate_limit_error',
                        'code' => 'concurrency_limit_exceeded',
                        'message' => 'Too many concurrent generations.',
                    ],
                ], 429, ['Retry-After' => '5']),
        ]);

        try {
            app(PixelforgeClient::class)->createGeneration(
                prompt: '[blocked]',
                size: ImageSize::Square,
                quality: ImageQuality::Low,
                referenceImages: [],
                metadata: [],
                idempotencyKey: 'idem-blocked',
            );
            $this->fail('Expected 400 exception.');
        } catch (PixelforgeApiException $e) {
            $this->assertSame(400, $e->statusCode);
            $this->assertSame('content_policy_violation', $e->errorCode);
        }

        try {
            app(PixelforgeClient::class)->createGeneration(
                prompt: 'Fourth concurrent',
                size: ImageSize::Square,
                quality: ImageQuality::Low,
                referenceImages: [],
                metadata: [],
                idempotencyKey: 'idem-429',
            );
            $this->fail('Expected 429 exception.');
        } catch (PixelforgeApiException $e) {
            $this->assertSame(429, $e->statusCode);
            $this->assertTrue($e->isConcurrencyOrRateLimit());
            $this->assertSame(5, $e->retryAfterSeconds);
        }

        Http::assertSentCount(2);
    }
}
