<?php

declare(strict_types=1);

namespace App\Data;

final readonly class ActualCostData
{
    public function __construct(
        public int $inputTextCostPicodollars,
        public int $inputImageCostPicodollars,
        public int $outputCostPicodollars,
        public int $reasoningCostPicodollars,
        public int $providerCostPicodollars,
        public int $userCostPicodollars,
        public int $profitPicodollars,
    ) {}
}
