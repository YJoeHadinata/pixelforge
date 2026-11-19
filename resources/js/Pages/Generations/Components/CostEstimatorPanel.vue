<script setup lang="ts">
import { computed } from 'vue';
import BaseAlert from '@/Components/UI/BaseAlert.vue';
import BaseButton from '@/Components/UI/BaseButton.vue';
import type { CostEstimate, PricingConfig } from '@/types/pixelforge';

const props = defineProps<{
    estimate: CostEstimate;
    pricing: PricingConfig;
    markupPercent: number;
    availableBalancePicodollars: number;
    availableBalanceUsd: string;
}>();

const emit = defineEmits<{
    openBuyCredits: [];
}>();

const hasSufficientBalance = computed(
    () => BigInt(props.availableBalancePicodollars) >= props.estimate.maxUserCostPicodollars,
);
</script>

<template>
    <aside class="rounded-2xl border border-neutral-800 bg-[#121212] p-5 shadow-lg">
        <div class="flex items-start justify-between gap-3 border-b border-neutral-800 pb-4">
            <div>
                <h2 class="text-sm font-semibold uppercase tracking-wider text-neutral-300">
                    Up-Front Cost Estimate
                </h2>
                <p class="mt-0.5 text-xs text-neutral-400">
                    Provider rates + {{ markupPercent }}% platform markup
                </p>
            </div>
            <span class="rounded-md bg-neutral-800 px-2 py-1 font-mono text-xs text-white">
                Live
            </span>
        </div>

        <div class="mt-4 space-y-2.5 text-xs">
            <div class="flex items-center justify-between text-neutral-300">
                <span>Input text tokens (±15%)</span>
                <span class="font-mono text-neutral-200">
                    {{ estimate.minTextTokens }}–{{ estimate.maxTextTokens }} (est. {{ estimate.expectedTextTokens }})
                </span>
            </div>
            <div class="flex items-center justify-between text-neutral-300">
                <span>Input image tokens (${{ pricing.rates.input_image_tokens }}/1M)</span>
                <span class="font-mono text-neutral-200">{{ estimate.imageTokens.toLocaleString() }}</span>
            </div>
            <div class="flex items-center justify-between text-neutral-300">
                <span>Output tokens (exact)</span>
                <span class="font-mono text-neutral-200">{{ estimate.outputTokens.toLocaleString() }}</span>
            </div>
            <div class="flex items-center justify-between text-neutral-300">
                <span>Reasoning tokens (range)</span>
                <span class="font-mono text-neutral-200">
                    {{ estimate.minReasoningTokens.toLocaleString() }}–{{ estimate.maxReasoningTokens.toLocaleString() }}
                </span>
            </div>
        </div>

        <div class="mt-4 space-y-2 border-t border-neutral-800 pt-4 text-sm">
            <div class="flex items-center justify-between text-neutral-400">
                <span>Expected Provider Cost</span>
                <span class="font-mono">{{ estimate.expectedProviderCostUsd }}</span>
            </div>
            <div class="flex items-center justify-between text-neutral-400">
                <span>Markup ({{ markupPercent }}%)</span>
                <span class="font-mono">{{ estimate.expectedMarkupUsd }}</span>
            </div>
            <div class="flex items-center justify-between pt-1 text-base font-semibold text-white">
                <span>Estimated Total</span>
                <span class="font-mono text-white">{{ estimate.expectedUserCostUsd }}</span>
            </div>
            <div class="flex items-center justify-between text-xs text-neutral-400">
                <span>Possible Range (Min – Max)</span>
                <span class="font-mono text-neutral-200">
                    {{ estimate.minUserCostUsd }} – {{ estimate.maxUserCostUsd }}
                </span>
            </div>
            <div class="flex items-center justify-between text-xs text-amber-300/90">
                <span>Temporary Hold on Submit (Max)</span>
                <span class="font-mono">{{ estimate.maxUserCostUsd }}</span>
            </div>
        </div>

        <p class="mt-3 text-xs text-neutral-400">
            Exact billing settles upon completion. Failed or rejected generations cost <span class="font-mono text-emerald-400">$0.00</span>.
        </p>

        <div v-if="!hasSufficientBalance" class="mt-4 space-y-3">
            <BaseAlert variant="warning" title="Insufficient available balance">
                <p>
                    Available balance is <span class="font-mono font-semibold">{{ availableBalanceUsd }}</span>, but up to
                    <span class="font-mono font-semibold">{{ estimate.maxUserCostUsd }}</span> is needed to cover the maximum estimate.
                </p>
                <div class="mt-2.5">
                    <BaseButton size="sm" @click="emit('openBuyCredits')">+ Buy Credits</BaseButton>
                </div>
            </BaseAlert>
        </div>
    </aside>
</template>
