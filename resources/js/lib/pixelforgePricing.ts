import type { CostEstimate, ImageQuality, ImageSize, PricingConfig, TokenRates } from '@/types/pixelforge';

export const PICODOLLARS_PER_USD = 1_000_000_000_000n;
export const PICODOLLARS_PER_NANODOLLAR = 1_000n;

export function countUnicodeChars(text: string): number {
    return Array.from(text).length;
}

function parseScaledDecimal(value: string | number, scale: number): bigint {
    const raw = String(value).trim();
    const negative = raw.startsWith('-');
    const clean = negative ? raw.slice(1) : raw;
    const [whole = '0', frac = ''] = clean.split('.');
    const paddedFrac = frac.slice(0, scale).padEnd(scale, '0');
    const combined = `${whole || '0'}${paddedFrac}`;
    const result = BigInt(combined);

    return negative ? -result : result;
}

export function ratePer1MToPicodollarsPerToken(rateUsdPer1M: string): bigint {
    return parseScaledDecimal(rateUsdPer1M, 6);
}

export function applyMarkup(providerCostPicodollars: bigint, markupPercent = 25): bigint {
    return (providerCostPicodollars * BigInt(100 + markupPercent)) / 100n;
}

export function formatNanodollarString(picodollars: bigint | number): string {
    const pico = typeof picodollars === 'bigint' ? picodollars : BigInt(picodollars);
    const negative = pico < 0n;
    const absNano = (negative ? -pico : pico) / PICODOLLARS_PER_NANODOLLAR;
    const whole = absNano / 1_000_000_000n;
    const frac = (absNano % 1_000_000_000n).toString().padStart(9, '0');

    return `${negative ? '-' : ''}${whole}.${frac}`;
}

export function formatExactDisplayUsd(picodollars: bigint | number): string {
    const pico = typeof picodollars === 'bigint' ? picodollars : BigInt(picodollars);
    const negative = pico < 0n;
    const absPico = negative ? -pico : pico;
    const whole = absPico / PICODOLLARS_PER_USD;
    const rawFrac = (absPico % PICODOLLARS_PER_USD).toString().padStart(12, '0');
    let trimmed = rawFrac.replace(/0+$/, '');
    if (trimmed.length < 2) {
        trimmed = trimmed.padEnd(2, '0');
    }

    return `${negative ? '-$' : '$'}${whole}.${trimmed}`;
}

function calculateProviderCost(
    textTokens: number,
    imageTokens: number,
    outputTokens: number,
    reasoningTokens: number,
    rates: TokenRates,
): bigint {
    return (
        BigInt(textTokens) * ratePer1MToPicodollarsPerToken(rates.input_text_tokens) +
        BigInt(imageTokens) * ratePer1MToPicodollarsPerToken(rates.input_image_tokens) +
        BigInt(outputTokens) * ratePer1MToPicodollarsPerToken(rates.output_tokens) +
        BigInt(reasoningTokens) * ratePer1MToPicodollarsPerToken(rates.reasoning_tokens)
    );
}

export function estimateGenerationCost(
    prompt: string,
    size: ImageSize,
    quality: ImageQuality,
    referenceImageCount: number,
    pricing: PricingConfig,
    markupPercent = 25,
): CostEstimate {
    const unicodeCharCount = countUnicodeChars(prompt);
    const charsPerToken = Math.max(1, Number(pricing.tokens.characters_per_text_token || 4));
    const expectedTextTokens =
        unicodeCharCount === 0 ? 0 : Math.floor((unicodeCharCount + charsPerToken - 1) / charsPerToken);

    let minTextTokens = 0;
    let maxTextTokens = 0;

    if (unicodeCharCount > 0 && expectedTextTokens > 0) {
        const varianceScaled = parseScaledDecimal(pricing.tokens.text_token_variance, 6);
        const one = 1_000_000n;
        const minRaw = BigInt(expectedTextTokens) * (one - varianceScaled);
        const minFloor = Number(minRaw / one);
        minTextTokens = Math.max(1, minFloor);

        const maxRaw = BigInt(expectedTextTokens) * (one + varianceScaled);
        maxTextTokens = Number((maxRaw + 999_999n) / one);
    }

    const imageTokens = Math.max(0, referenceImageCount) * Number(pricing.tokens.per_reference_image || 765);
    const outputTokens = Number(pricing.tokens.output?.[quality]?.[size] ?? 0);
    const reasoningRange = pricing.tokens.reasoning?.[quality] ?? { min: 0, max: 0 };
    const minReasoningTokens = Number(reasoningRange.min ?? 0);
    const maxReasoningTokens = Number(reasoningRange.max ?? 0);
    const expectedReasoningTokens = Math.floor((minReasoningTokens + maxReasoningTokens) / 2);

    const minProvider = calculateProviderCost(minTextTokens, imageTokens, outputTokens, minReasoningTokens, pricing.rates);
    const expectedProvider = calculateProviderCost(
        expectedTextTokens,
        imageTokens,
        outputTokens,
        expectedReasoningTokens,
        pricing.rates,
    );
    const maxProvider = calculateProviderCost(maxTextTokens, imageTokens, outputTokens, maxReasoningTokens, pricing.rates);

    const minUser = applyMarkup(minProvider, markupPercent);
    const expectedUser = applyMarkup(expectedProvider, markupPercent);
    const maxUser = applyMarkup(maxProvider, markupPercent);

    return {
        unicodeCharCount,
        minTextTokens,
        expectedTextTokens,
        maxTextTokens,
        imageTokens,
        outputTokens,
        minReasoningTokens,
        expectedReasoningTokens,
        maxReasoningTokens,
        minProviderCostPicodollars: minProvider,
        expectedProviderCostPicodollars: expectedProvider,
        maxProviderCostPicodollars: maxProvider,
        minUserCostPicodollars: minUser,
        expectedUserCostPicodollars: expectedUser,
        maxUserCostPicodollars: maxUser,
        minUserCostUsd: formatExactDisplayUsd(minUser),
        expectedUserCostUsd: formatExactDisplayUsd(expectedUser),
        maxUserCostUsd: formatExactDisplayUsd(maxUser),
        expectedProviderCostUsd: formatExactDisplayUsd(expectedProvider),
        expectedMarkupUsd: formatExactDisplayUsd(expectedUser - expectedProvider),
    };
}
