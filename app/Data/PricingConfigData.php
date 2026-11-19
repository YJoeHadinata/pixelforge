<?php

declare(strict_types=1);

namespace App\Data;

use App\Enums\ImageQuality;
use App\Enums\ImageSize;

final readonly class PricingConfigData
{
    /**
     * @param  array<string, array<string, int>>  $outputMatrix
     * @param  array<string, array{min: int, max: int}>  $reasoningRanges
     */
    public function __construct(
        public TokenRatesData $rates,
        public int $charactersPerTextToken,
        public string $textTokenVariance,
        public int $perReferenceImage,
        public array $outputMatrix,
        public array $reasoningRanges,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        /** @var array<string, mixed> $tokens */
        $tokens = $data['tokens'] ?? [];

        /** @var array<string, array<string, int>> $output */
        $output = $tokens['output'] ?? [];

        /** @var array<string, array{min: int, max: int}> $reasoning */
        $reasoning = $tokens['reasoning'] ?? [];

        return new self(
            rates: TokenRatesData::fromArray($data['rates'] ?? []),
            charactersPerTextToken: max(1, (int) ($tokens['characters_per_text_token'] ?? 4)),
            textTokenVariance: (string) ($tokens['text_token_variance'] ?? '0.15'),
            perReferenceImage: (int) ($tokens['per_reference_image'] ?? 765),
            outputMatrix: $output,
            reasoningRanges: $reasoning,
        );
    }

    public static function defaultFallback(): self
    {
        return self::fromArray([
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
        ]);
    }

    public function outputTokensFor(ImageQuality $quality, ImageSize $size): int
    {
        return (int) ($this->outputMatrix[$quality->value][$size->value] ?? 0);
    }

    /**
     * @return array{min: int, max: int}
     */
    public function reasoningRangeFor(ImageQuality $quality): array
    {
        $range = $this->reasoningRanges[$quality->value] ?? ['min' => 0, 'max' => 0];

        return [
            'min' => (int) ($range['min'] ?? 0),
            'max' => (int) ($range['max'] ?? 0),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'rates' => $this->rates->toArray(),
            'tokens' => [
                'characters_per_text_token' => $this->charactersPerTextToken,
                'text_token_variance' => $this->textTokenVariance,
                'per_reference_image' => $this->perReferenceImage,
                'output' => $this->outputMatrix,
                'reasoning' => $this->reasoningRanges,
            ],
        ];
    }
}
