<?php

declare(strict_types=1);

namespace App\Services\Pixelforge;

use App\Data\PixelforgeGenerationData;
use App\Data\PricingConfigData;
use App\Enums\ImageQuality;
use App\Enums\ImageSize;
use App\Services\Pixelforge\Exceptions\PixelforgeApiException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

final class PixelforgeClient
{
    public function getPricing(): PricingConfigData
    {
        $response = Http::baseUrl($this->baseUrl())
            ->acceptJson()
            ->timeout(10)
            ->get('/pricing');

        if (! $response->successful()) {
            throw $this->toApiException($response);
        }

        /** @var array<string, mixed> $json */
        $json = $response->json() ?? [];

        return PricingConfigData::fromArray($json);
    }

    /**
     * @param  list<string>  $referenceImages
     * @param  array<string, string>  $metadata
     */
    public function createGeneration(
        string $prompt,
        ImageSize $size,
        ImageQuality $quality,
        array $referenceImages,
        array $metadata,
        string $idempotencyKey,
        int $maxOverloadRetries = 2,
    ): PixelforgeGenerationData {
        $payload = [
            'prompt' => $prompt,
            'size' => $size->value,
            'quality' => $quality->value,
        ];

        $cleanImages = array_values(array_filter(
            array_map(static fn (string $url): string => trim($url), $referenceImages),
            static fn (string $url): bool => $url !== '',
        ));
        if ($cleanImages !== []) {
            $payload['reference_images'] = $cleanImages;
        }

        if ($metadata !== []) {
            $stringMetadata = [];
            foreach ($metadata as $k => $v) {
                $stringMetadata[(string) $k] = (string) $v;
            }
            $payload['metadata'] = $stringMetadata;
        }

        $attempt = 0;

        while (true) {
            $response = $this->authenticatedRequest()
                ->withHeaders(['Idempotency-Key' => $idempotencyKey])
                ->post('/generations', $payload);

            if ($response->successful()) {
                /** @var array<string, mixed> $json */
                $json = $response->json() ?? [];

                return PixelforgeGenerationData::fromArray($json);
            }

            $exception = $this->toApiException($response);

            if ($exception->isOverloaded() && $attempt < $maxOverloadRetries) {
                $attempt++;
                $delay = max(1, min(5, $exception->retryAfterSeconds ?? 2));
                Sleep::for($delay)->seconds();

                continue;
            }

            throw $exception;
        }
    }

    public function getGeneration(string $pixelforgeId): PixelforgeGenerationData
    {
        $response = $this->authenticatedRequest()->get("/generations/{$pixelforgeId}");

        if (! $response->successful()) {
            throw $this->toApiException($response);
        }

        /** @var array<string, mixed> $json */
        $json = $response->json() ?? [];

        return PixelforgeGenerationData::fromArray($json);
    }

    /**
     * @return array<string, mixed>
     */
    public function getUsage(string $startDate, string $endDate): array
    {
        $response = $this->authenticatedRequest()->get('/usage', [
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]);

        if (! $response->successful()) {
            throw $this->toApiException($response);
        }

        /** @var array<string, mixed> $json */
        $json = $response->json() ?? [];

        return $json;
    }

    private function authenticatedRequest(): PendingRequest
    {
        $apiKey = (string) config('services.pixelforge.api_key', '');
        if ($apiKey === '') {
            throw new PixelforgeApiException(
                statusCode: 401,
                errorType: 'authentication_error',
                errorCode: 'invalid_api_key',
                errorMessage: 'Pixelforge API key is not configured.',
            );
        }

        return Http::baseUrl($this->baseUrl())
            ->withToken($apiKey)
            ->acceptJson()
            ->asJson()
            ->timeout(15);
    }

    private function baseUrl(): string
    {
        return rtrim((string) config('services.pixelforge.base_url', 'https://pixelforge.promptedit.dev/v1'), '/');
    }

    private function toApiException(Response $response): PixelforgeApiException
    {
        /** @var array<string, mixed>|null $body */
        $body = $response->json();

        /** @var array<string, mixed> $error */
        $error = is_array($body['error'] ?? null) ? $body['error'] : [];

        $retryAfterHeader = $response->header('Retry-After');
        $retryAfter = is_string($retryAfterHeader) && ctype_digit(trim($retryAfterHeader))
            ? (int) trim($retryAfterHeader)
            : null;

        /** @var array<string, list<string>> $validationErrors */
        $validationErrors = is_array($error['errors'] ?? null) ? $error['errors'] : [];

        return new PixelforgeApiException(
            statusCode: $response->status(),
            errorType: (string) ($error['type'] ?? 'api_error'),
            errorCode: (string) ($error['code'] ?? 'http_'.$response->status()),
            errorMessage: (string) ($error['message'] ?? "Pixelforge HTTP {$response->status()} error."),
            param: isset($error['param']) ? (string) $error['param'] : null,
            validationErrors: $validationErrors,
            retryAfterSeconds: $retryAfter,
        );
    }
}
