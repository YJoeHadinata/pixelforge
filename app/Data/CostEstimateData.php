<?php

declare(strict_types=1);

namespace App\Data;

final readonly class CostEstimateData
{
    public function __construct(
        public int $unicodeCharCount,
        public int $minTextTokens,
        public int $expectedTextTokens,
        public int $maxTextTokens,
        public int $imageTokens,
        public int $outputTokens,
        public int $minReasoningTokens,
        public int $expectedReasoningTokens,
        public int $maxReasoningTokens,
        public int $minProviderCostPicodollars,
        public int $expectedProviderCostPicodollars,
        public int $maxProviderCostPicodollars,
        public int $minUserCostPicodollars,
        public int $expectedUserCostPicodollars,
        public int $maxUserCostPicodollars,
        public TokenRatesData $rates,
    ) {}
}
