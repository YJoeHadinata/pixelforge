<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\GenerationStatus;
use App\Enums\ImageQuality;
use App\Enums\ImageSize;
use App\Models\Generation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Generation>
 */
final class GenerationFactory extends Factory
{
    protected $model = Generation::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id' => (string) Str::uuid(),
            'user_id' => User::factory(),
            'pixelforge_id' => null,
            'idempotency_key' => (string) Str::uuid(),
            'status' => GenerationStatus::Submitting,
            'prompt' => 'A lighthouse on a cliff at dawn, oil painting',
            'size' => ImageSize::Square,
            'quality' => ImageQuality::Medium,
            'reference_images' => [],
            'rates_snapshot' => [
                'input_text_tokens' => '5.00',
                'input_image_tokens' => '10.00',
                'output_tokens' => '40.00',
                'reasoning_tokens' => '12.50',
            ],
            'usage' => null,
            'estimated_min_provider_cost_picodollars' => 46_500_000_000,
            'estimated_expected_provider_cost_picodollars' => 57_810_000_000,
            'estimated_max_provider_cost_picodollars' => 69_070_000_000,
            'estimated_min_user_cost_picodollars' => 58_125_000_000,
            'estimated_expected_user_cost_picodollars' => 72_262_500_000,
            'estimated_max_user_cost_picodollars' => 86_337_500_000,
            'reserved_user_cost_picodollars' => 86_337_500_000,
        ];
    }
}
