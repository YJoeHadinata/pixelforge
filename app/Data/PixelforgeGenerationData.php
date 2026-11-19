<?php

declare(strict_types=1);

namespace App\Data;

use App\Enums\GenerationStatus;
use Carbon\CarbonImmutable;

final readonly class PixelforgeGenerationData
{
    /**
     * @param  list<string>  $referenceImages
     * @param  array<string, string>  $metadata
     */
    public function __construct(
        public string $id,
        public GenerationStatus $status,
        public string $prompt,
        public string $size,
        public string $quality,
        public array $referenceImages,
        public array $metadata,
        public ?string $imageUrl,
        public ?CarbonImmutable $imageUrlExpiresAt,
        public ?TokenUsageData $usage,
        public TokenRatesData $rates,
        public ?string $errorCode,
        public ?string $errorMessage,
        public ?CarbonImmutable $createdAt,
        public ?CarbonImmutable $startedAt,
        public ?CarbonImmutable $completedAt,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        /** @var array<string, mixed>|null $error */
        $error = is_array($data['error'] ?? null) ? $data['error'] : null;

        /** @var array<string, mixed>|null $usage */
        $usage = is_array($data['usage'] ?? null) ? $data['usage'] : null;

        /** @var array<string, string> $metadata */
        $metadata = is_array($data['metadata'] ?? null)
            ? array_map(static fn (mixed $v): string => (string) $v, $data['metadata'])
            : [];

        /** @var list<string> $referenceImages */
        $referenceImages = is_array($data['reference_images'] ?? null)
            ? array_values(array_map(static fn (mixed $v): string => (string) $v, $data['reference_images']))
            : [];

        return new self(
            id: (string) ($data['id'] ?? ''),
            status: GenerationStatus::fromPixelforge((string) ($data['status'] ?? 'queued')),
            prompt: (string) ($data['prompt'] ?? ''),
            size: (string) ($data['size'] ?? '1024x1024'),
            quality: (string) ($data['quality'] ?? 'medium'),
            referenceImages: $referenceImages,
            metadata: $metadata,
            imageUrl: isset($data['image_url']) ? (string) $data['image_url'] : null,
            imageUrlExpiresAt: self::parseUtc($data['image_url_expires_at'] ?? null),
            usage: $usage !== null ? TokenUsageData::fromArray($usage) : null,
            rates: TokenRatesData::fromArray($data['rates'] ?? []),
            errorCode: isset($error['code']) ? (string) $error['code'] : null,
            errorMessage: isset($error['message']) ? (string) $error['message'] : null,
            createdAt: self::parseUtc($data['created_at'] ?? null),
            startedAt: self::parseUtc($data['started_at'] ?? null),
            completedAt: self::parseUtc($data['completed_at'] ?? null),
        );
    }

    public function localGenerationId(): ?string
    {
        return $this->metadata['local_id'] ?? null;
    }

    private static function parseUtc(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        return CarbonImmutable::parse($value)->utc();
    }
}
