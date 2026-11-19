<?php

declare(strict_types=1);

namespace App\Services\Pixelforge;

use InvalidArgumentException;

final class MoneyFormatter
{
    public const PICODOLLARS_PER_USD = 1_000_000_000_000;

    public const PICODOLLARS_PER_NANODOLLAR = 1_000;

    public const PICODOLLARS_PER_TOKEN_AT_1_USD_PER_1M = 1_000_000;

    public static function usdToPicodollars(string $usdDecimal): int
    {
        $normalized = trim($usdDecimal);
        if (! preg_match('/^-?\d+(?:\.\d+)?$/', $normalized)) {
            throw new InvalidArgumentException("Invalid USD decimal string: {$usdDecimal}");
        }

        return (int) bcmul($normalized, (string) self::PICODOLLARS_PER_USD, 0);
    }

    public static function ratePer1MToPicodollarsPerToken(string $rateUsdPer1M): int
    {
        $normalized = trim($rateUsdPer1M);
        if (! preg_match('/^\d+(?:\.\d+)?$/', $normalized)) {
            throw new InvalidArgumentException("Invalid token rate string: {$rateUsdPer1M}");
        }

        return (int) bcmul($normalized, (string) self::PICODOLLARS_PER_TOKEN_AT_1_USD_PER_1M, 0);
    }

    public static function applyMarkup(int $providerCostPicodollars, int $markupPercent = 25): int
    {
        $multiplier = (string) (100 + $markupPercent);

        return (int) bcdiv(bcmul((string) $providerCostPicodollars, $multiplier, 0), '100', 0);
    }

    public static function toNanodollarString(int $picodollars): string
    {
        $nanodollars = bcdiv((string) $picodollars, (string) self::PICODOLLARS_PER_NANODOLLAR, 0);

        return bcdiv($nanodollars, '1000000000', 9);
    }

    public static function toPicodollarDecimalString(int $picodollars): string
    {
        return bcdiv((string) $picodollars, (string) self::PICODOLLARS_PER_USD, 12);
    }

    public static function toExactDisplayUsd(int $picodollars): string
    {
        $negative = $picodollars < 0;
        $absPico = $negative ? abs($picodollars) : $picodollars;
        $raw = bcdiv((string) $absPico, (string) self::PICODOLLARS_PER_USD, 12);

        [$whole, $fraction] = explode('.', $raw);
        $trimmedFraction = rtrim($fraction, '0');
        if (strlen($trimmedFraction) < 2) {
            $trimmedFraction = str_pad($trimmedFraction, 2, '0');
        }

        $prefix = $negative ? '-$' : '$';

        return "{$prefix}{$whole}.{$trimmedFraction}";
    }
}
