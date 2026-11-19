<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Data\PricingConfigData;
use App\Data\TokenRatesData;
use App\Data\TokenUsageData;
use App\Enums\ImageQuality;
use App\Enums\ImageSize;
use App\Services\Pixelforge\MoneyFormatter;
use App\Services\Pixelforge\PixelforgeCostCalculator;
use PHPUnit\Framework\TestCase;

final class PixelforgeCostCalculatorTest extends TestCase
{
    public function test_matches_pixelforge_docs_worked_example_exactly(): void
    {
        $calculator = new PixelforgeCostCalculator;
        $pricing = PricingConfigData::defaultFallback();
        $prompt = str_repeat('a', 400);

        $estimate = $calculator->estimate(
            prompt: $prompt,
            size: ImageSize::Landscape,
            quality: ImageQuality::High,
            referenceImageCount: 2,
            pricing: $pricing,
            markupPercent: 25,
        );

        $this->assertSame(400, $estimate->unicodeCharCount);
        $this->assertSame(100, $estimate->expectedTextTokens);
        $this->assertSame(115, $estimate->maxTextTokens);
        $this->assertSame(1530, $estimate->imageTokens);
        $this->assertSame(6450, $estimate->outputTokens);
        $this->assertSame(8000, $estimate->maxReasoningTokens);

        $this->assertSame(373_875_000_000, $estimate->maxProviderCostPicodollars);
        $this->assertSame('0.373875000', MoneyFormatter::toNanodollarString($estimate->maxProviderCostPicodollars));

        $this->assertSame(467_343_750_000, $estimate->maxUserCostPicodollars);
        $this->assertSame('0.467343750000', MoneyFormatter::toPicodollarDecimalString($estimate->maxUserCostPicodollars));
        $this->assertSame('$0.46734375', MoneyFormatter::toExactDisplayUsd($estimate->maxUserCostPicodollars));
    }

    public function test_calculates_actual_cost_and_profit_with_exact_25_percent_markup(): void
    {
        $calculator = new PixelforgeCostCalculator;
        $usage = new TokenUsageData(
            inputTextTokens: 12,
            inputImageTokens: 765,
            outputTokens: 6450,
            reasoningTokens: 3184,
            totalTokens: 10411,
        );
        $rates = new TokenRatesData(
            inputTextTokens: '5.00',
            inputImageTokens: '10.00',
            outputTokens: '40.00',
            reasoningTokens: '12.50',
        );

        $actual = $calculator->calculateActual($usage, $rates, 25);

        $this->assertSame(60_000_000, $actual->inputTextCostPicodollars);
        $this->assertSame(7_650_000_000, $actual->inputImageCostPicodollars);
        $this->assertSame(258_000_000_000, $actual->outputCostPicodollars);
        $this->assertSame(39_800_000_000, $actual->reasoningCostPicodollars);
        $this->assertSame(305_510_000_000, $actual->providerCostPicodollars);
        $this->assertSame('0.305510000', MoneyFormatter::toNanodollarString($actual->providerCostPicodollars));

        $this->assertSame(381_887_500_000, $actual->userCostPicodollars);
        $this->assertSame(76_377_500_000, $actual->profitPicodollars);
        $this->assertSame($actual->userCostPicodollars - $actual->providerCostPicodollars, $actual->profitPicodollars);
    }

    public function test_uses_unicode_character_length_instead_of_utf8_byte_length(): void
    {
        $calculator = new PixelforgeCostCalculator;
        $pricing = PricingConfigData::defaultFallback();

        $estimate = $calculator->estimate(
            prompt: '🎨✨🚀🌅',
            size: ImageSize::Square,
            quality: ImageQuality::Low,
            referenceImageCount: 0,
            pricing: $pricing,
            markupPercent: 25,
        );

        $this->assertSame(4, $estimate->unicodeCharCount);
        $this->assertSame(1, $estimate->expectedTextTokens);
        $this->assertSame(1, $estimate->minTextTokens);
        $this->assertSame(2, $estimate->maxTextTokens);
    }

    public function test_uses_dynamic_pricing_config_rates_and_token_rules(): void
    {
        $calculator = new PixelforgeCostCalculator;
        $pricing = PricingConfigData::fromArray([
            'rates' => [
                'input_text_tokens' => '10.00',
                'input_image_tokens' => '20.00',
                'output_tokens' => '80.00',
                'reasoning_tokens' => '25.00',
            ],
            'tokens' => [
                'characters_per_text_token' => 2,
                'text_token_variance' => '0.10',
                'per_reference_image' => 500,
                'output' => [
                    'low' => ['1024x1024' => 100, '1536x1024' => 200, '1024x1536' => 200],
                ],
                'reasoning' => [
                    'low' => ['min' => 10, 'max' => 50],
                ],
            ],
        ]);

        $estimate = $calculator->estimate(
            prompt: 'abcd',
            size: ImageSize::Square,
            quality: ImageQuality::Low,
            referenceImageCount: 1,
            pricing: $pricing,
            markupPercent: 25,
        );

        $this->assertSame(2, $estimate->expectedTextTokens);
        $this->assertSame(500, $estimate->imageTokens);
        $this->assertSame(100, $estimate->outputTokens);
        $this->assertSame(10, $estimate->minReasoningTokens);
        $this->assertSame(50, $estimate->maxReasoningTokens);
    }
}
