<script setup lang="ts">
import type { AdminReportData } from '@/types/pixelforge';

defineProps<{
    summary: AdminReportData['summary'];
}>();
</script>

<template>
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-2xl border border-neutral-800 bg-[#121212] p-5">
            <p class="text-xs font-medium uppercase tracking-wider text-neutral-400">User Credit Spend</p>
            <p class="mt-2 font-mono text-2xl font-bold text-white">{{ summary.credit_spend_usd }}</p>
            <p class="mt-1 text-xs text-neutral-400">
                {{ summary.succeeded_generations }} succeeded · {{ summary.failed_generations }} failed ($0)
            </p>
        </div>

        <div class="rounded-2xl border border-neutral-800 bg-[#121212] p-5">
            <p class="text-xs font-medium uppercase tracking-wider text-neutral-400">Local Provider Cost</p>
            <p class="mt-2 font-mono text-2xl font-bold text-white">${{ summary.provider_cost_usd }}</p>
            <p class="mt-1 text-xs text-neutral-400">Exact 9-decimal USD sum</p>
        </div>

        <div class="rounded-2xl border border-neutral-800 bg-[#121212] p-5">
            <p class="text-xs font-medium uppercase tracking-wider text-neutral-400">Net Profit (+25%)</p>
            <p class="mt-2 font-mono text-2xl font-bold text-emerald-400">{{ summary.profit_usd }}</p>
            <p class="mt-1 text-xs text-neutral-400">Credit spend minus provider cost</p>
        </div>

        <div class="rounded-2xl border border-neutral-800 bg-[#121212] p-5">
            <p class="text-xs font-medium uppercase tracking-wider text-neutral-400">GET /v1/usage Match</p>
            <p
                class="mt-2 font-mono text-2xl font-bold"
                :class="
                    summary.remote_error
                        ? 'text-amber-300'
                        : summary.is_fully_reconciled
                          ? 'text-emerald-400'
                          : 'text-red-400'
                "
            >
                {{
                    summary.remote_error
                        ? 'Unavailable'
                        : summary.is_fully_reconciled
                          ? 'Exact Match'
                          : `Diff $${summary.reconciliation_diff_usd}`
                }}
            </p>
            <p class="mt-1 font-mono text-xs text-neutral-400">
                <template v-if="summary.remote_provider_cost_usd !== null">
                    Pixelforge: ${{ summary.remote_provider_cost_usd }} ({{ summary.remote_generations }} gens)
                </template>
                <template v-else>
                    {{ summary.remote_error ?? 'No remote data' }}
                </template>
            </p>
        </div>
    </div>
</template>
