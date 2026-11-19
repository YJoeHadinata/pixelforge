<script setup lang="ts">
import type { TokenRates, TokenUsage } from '@/types/pixelforge';

defineProps<{
    usage: TokenUsage;
    rates: TokenRates;
}>();
</script>

<template>
    <div class="mt-3 rounded-xl border border-neutral-800 bg-[#0a0a0a]/75 p-3 text-xs">
        <p class="font-semibold text-neutral-300">Token Usage Breakdown</p>
        <div class="mt-2 grid grid-cols-2 gap-x-4 gap-y-1.5 sm:grid-cols-5">
            <div>
                <span class="text-neutral-500">Text (${{ rates.input_text_tokens }}/1M)</span>
                <p class="font-mono text-neutral-200">{{ usage.input_text_tokens.toLocaleString() }}</p>
            </div>
            <div>
                <span class="text-neutral-500">Images (${{ rates.input_image_tokens }}/1M)</span>
                <p class="font-mono text-neutral-200">{{ usage.input_image_tokens.toLocaleString() }}</p>
            </div>
            <div>
                <span class="text-neutral-500">Output (${{ rates.output_tokens }}/1M)</span>
                <p class="font-mono text-neutral-200">{{ usage.output_tokens.toLocaleString() }}</p>
            </div>
            <div>
                <span class="text-neutral-500">Reasoning (${{ rates.reasoning_tokens }}/1M)</span>
                <p class="font-mono text-neutral-200">{{ usage.reasoning_tokens.toLocaleString() }}</p>
            </div>
            <div>
                <span class="text-neutral-500">Total Tokens</span>
                <p class="font-mono font-semibold text-white">{{ usage.total_tokens.toLocaleString() }}</p>
            </div>
        </div>
    </div>
</template>
