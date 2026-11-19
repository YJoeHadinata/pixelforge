<?php

declare(strict_types=1);

namespace App\Services\Pixelforge;

use App\Data\ActualCostData;
use App\Data\CostEstimateData;
use App\Data\PricingConfigData;
use App\Data\TokenRatesData;
use App\Data\TokenUsageData;
use App\Enums\ImageQuality;
use App\Enums\ImageSize;

final class PixelforgeCostCalculator
{
    public function estimate(
        string $prompt,
        ImageSize $size,
        ImageQuality $quality,
        int $referenceImageCount,
        PricingConfigData $pricing,
        ?int $markupPercent = null,
    ): CostEstimateData {
        $markup = $markupPercent ?? (int) config('services.pixelforge.markup_percent', 25);
        $unicodeCharCount = mb_strlen($prompt, 'UTF-8');

        $expectedTextTokens = $unicodeCharCount === 0
            ? 0
            : intdiv($unicodeCharCount + $pricing->charactersPerTextToken - 1, $pricing->charactersPerTextToken);

        [$minTextTokens, $maxTextTokens] = $this->textTokenBounds(
            $unicodeCharCount,
            $expectedTextTokens,
            $pricing->textTokenVariance,
        );

        $imageTokens = max(0, $referenceImageCount) * $pricing->perReferenceImage;
        $outputTokens = $pricing->outputTokensFor($quality, $size);
        $reasoningRange = $pricing->reasoningRangeFor($quality);
        $minReasoningTokens = $reasoningRange['min'];
        $maxReasoningTokens = $reasoningRange['max'];
        $expectedReasoningTokens = intdiv($minReasoningTokens + $maxReasoningTokens, 2);

        $minProviderCost = $this->providerCostPicodollars(
            $minTextTokens,
            $imageTokens,
            $outputTokens,
            $minReasoningTokens,
            $pricing->rates,
        );
        $expectedProviderCost = $this->providerCostPicodollars(
            $expectedTextTokens,
            $imageTokens,
            $outputTokens,
            $expectedReasoningTokens,
            $pricing->rates,
        );
        $maxProviderCost = $this->providerCostPicodollars(
            $maxTextTokens,
            $imageTokens,
            $outputTokens,
            $maxReasoningTokens,
            $pricing->rates,
        );

        return new CostEstimateData(
            unicodeCharCount: $unicodeCharCount,
            minTextTokens: $minTextTokens,
            expectedTextTokens: $expectedTextTokens,
            maxTextTokens: $maxTextTokens,
            imageTokens: $imageTokens,
            outputTokens: $outputTokens,
            minReasoningTokens: $minReasoningTokens,
            expectedReasoningTokens: $expectedReasoningTokens,
            maxReasoningTokens: $maxReasoningTokens,
            minProviderCostPicodollars: $minProviderCost,
            expectedProviderCostPicodollars: $expectedProviderCost,
            maxProviderCostPicodollars: $maxProviderCost,
            minUserCostPicodollars: MoneyFormatter::applyMarkup($minProviderCost, $markup),
            expectedUserCostPicodollars: MoneyFormatter::applyMarkup($expectedProviderCost, $markup),
            maxUserCostPicodollars: MoneyFormatter::applyMarkup($maxProviderCost, $markup),
            rates: $pricing->rates,
        );
    }

    public function calculateActual(
        TokenUsageData $usage,
        TokenRatesData $rates,
        ?int $markupPercent = null,
    ): ActualCostData {
        $markup = $markupPercent ?? (int) config('services.pixelforge.markup_percent', 25);

        $textCost = $usage->inputTextTokens * MoneyFormatter::ratePer1MToPicodollarsPerToken($rates->inputTextTokens);
        $imageCost = $usage->inputImageTokens * MoneyFormatter::ratePer1MToPicodollarsPerToken($rates->inputImageTokens);
        $outputCost = $usage->outputTokens * MoneyFormatter::ratePer1MToPicodollarsPerToken($rates->outputTokens);
        $reasoningCost = $usage->reasoningTokens * MoneyFormatter::ratePer1MToPicodollarsPerToken($rates->reasoningTokens);

        $providerCost = $textCost + $imageCost + $outputCost + $reasoningCost;
        $userCost = MoneyFormatter::applyMarkup($providerCost, $markup);

        return new ActualCostData(
            inputTextCostPicodollars: $textCost,
            inputImageCostPicodollars: $imageCost,
            outputCostPicodollars: $outputCost,
            reasoningCostPicodollars: $reasoningCost,
            providerCostPicodollars: $providerCost,
            userCostPicodollars: $userCost,
            profitPicodollars: $userCost - $providerCost,
        );
    }

    private function providerCostPicodollars(
        int $textTokens,
        int $imageTokens,
        int $outputTokens,
        int $reasoningTokens,
        TokenRatesData $rates,
    ): int {
        return ($textTokens * MoneyFormatter::ratePer1MToPicodollarsPerToken($rates->inputTextTokens))
            + ($imageTokens * MoneyFormatter::ratePer1MToPicodollarsPerToken($rates->inputImageTokens))
            + ($outputTokens * MoneyFormatter::ratePer1MToPicodollarsPerToken($rates->outputTokens))
            + ($reasoningTokens * MoneyFormatter::ratePer1MToPicodollarsPerToken($rates->reasoningTokens));
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function textTokenBounds(int $unicodeCharCount, int $expectedTextTokens, string $variance): array
    {
        if ($unicodeCharCount === 0 || $expectedTextTokens === 0) {
            return [0, 0];
        }

        $minRaw = bcmul((string) $expectedTextTokens, bcsub('1', $variance, 6), 6);
        [$minWhole] = explode('.', $minRaw);
        $minTokens = max(1, (int) $minWhole);

        $maxRaw = bcmul((string) $expectedTextTokens, bcadd('1', $variance, 6), 6);
        [$maxWhole, $maxFraction] = explode('.', $maxRaw);
        $maxTokens = (int) $maxWhole + ((int) $maxFraction > 0 ? 1 : 0);

        return [$minTokens, $maxTokens];
    }
}
