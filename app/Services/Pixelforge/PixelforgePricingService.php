<?php

declare(strict_types=1);

namespace App\Services\Pixelforge;

use App\Data\PricingConfigData;
use Illuminate\Support\Facades\Cache;
use Throwable;

final class PixelforgePricingService
{
    private const CACHE_KEY = 'pixelforge.pricing.v1';

    public function __construct(
        private readonly PixelforgeClient $client,
    ) {}

    public function current(): PricingConfigData
    {
        /** @var array<string, mixed>|null $cached */
        $cached = Cache::get(self::CACHE_KEY);
        if (is_array($cached)) {
            return PricingConfigData::fromArray($cached);
        }

        try {
            $pricing = $this->client->getPricing();
            Cache::put(self::CACHE_KEY, $pricing->toArray(), now()->addMinutes(5));

            return $pricing;
        } catch (Throwable) {
            return PricingConfigData::defaultFallback();
        }
    }
}
