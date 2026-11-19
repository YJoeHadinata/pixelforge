import { describe, expect, it } from 'vitest';
import {
    countUnicodeChars,
    estimateGenerationCost,
    formatExactDisplayUsd,
    formatNanodollarString,
} from './pixelforgePricing';
import type { PricingConfig } from '@/types/pixelforge';

const defaultPricing: PricingConfig = {
    rates: {
        input_text_tokens: '5.00',
        input_image_tokens: '10.00',
        output_tokens: '40.00',
        reasoning_tokens: '12.50',
    },
    tokens: {
        characters_per_text_token: 4,
        text_token_variance: '0.15',
        per_reference_image: 765,
        output: {
            low: { '1024x1024': 280, '1536x1024': 420, '1024x1536': 420 },
            medium: { '1024x1024': 1100, '1536x1024': 1650, '1024x1536': 1650 },
            high: { '1024x1024': 4300, '1536x1024': 6450, '1024x1536': 6450 },
        },
        reasoning: {
            low: { min: 0, max: 500 },
            medium: { min: 200, max: 2000 },
            high: { min: 1000, max: 8000 },
        },
    },
};

describe('pixelforgePricing', () => {
    it('matches the Pixelforge docs worked example using BigInt picodollars', () => {
        const prompt = 'a'.repeat(400);
        const estimate = estimateGenerationCost(prompt, '1536x1024', 'high', 2, defaultPricing, 25);

        expect(estimate.unicodeCharCount).toBe(400);
        expect(estimate.expectedTextTokens).toBe(100);
        expect(estimate.maxTextTokens).toBe(115);
        expect(estimate.imageTokens).toBe(1530);
        expect(estimate.outputTokens).toBe(6450);
        expect(estimate.maxReasoningTokens).toBe(8000);

        expect(estimate.maxProviderCostPicodollars).toBe(373_875_000_000n);
        expect(formatNanodollarString(estimate.maxProviderCostPicodollars)).toBe('0.373875000');

        expect(estimate.maxUserCostPicodollars).toBe(467_343_750_000n);
        expect(estimate.maxUserCostUsd).toBe('$0.46734375');
    });

    it('counts Unicode code points rather than UTF-16 code units or UTF-8 bytes', () => {
        expect(countUnicodeChars('🎨✨🚀🌅')).toBe(4);
        const estimate = estimateGenerationCost('🎨✨🚀🌅', '1024x1024', 'low', 0, defaultPricing, 25);
        expect(estimate.unicodeCharCount).toBe(4);
        expect(estimate.expectedTextTokens).toBe(1);
    });

    it('formats exact USD strings with at least 2 decimals and no rounding', () => {
        expect(formatExactDisplayUsd(5_000_000_000_000n)).toBe('$5.00');
        expect(formatExactDisplayUsd(0n)).toBe('$0.00');
        expect(formatExactDisplayUsd(381_887_500_000n)).toBe('$0.3818875');
    });
});
