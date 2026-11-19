<script setup lang="ts">
import type { DailyReconciliationRow } from '@/types/pixelforge';

defineProps<{
    rows: DailyReconciliationRow[];
}>();
</script>

<template>
    <section class="space-y-3">
        <div>
            <h2 class="text-lg font-semibold text-white">Daily Breakdown & GET /v1/usage Reconciliation (UTC)</h2>
            <p class="text-xs text-neutral-400">
                Grouped strictly by UTC date of <span class="font-mono">completed_at</span> to match Pixelforge billing.
            </p>
        </div>

        <div class="overflow-x-auto rounded-2xl border border-neutral-800 bg-[#121212]">
            <table class="min-w-full divide-y divide-neutral-800 text-left text-sm">
                <thead class="bg-[#121212] text-xs uppercase tracking-wider text-neutral-400">
                    <tr>
                        <th class="px-4 py-3">UTC Date</th>
                        <th class="px-4 py-3 text-right">Local Gens</th>
                        <th class="px-4 py-3 text-right">Pixelforge Gens</th>
                        <th class="px-4 py-3 text-right">Local Provider ($)</th>
                        <th class="px-4 py-3 text-right">Pixelforge Usage ($)</th>
                        <th class="px-4 py-3 text-right">Diff ($)</th>
                        <th class="px-4 py-3 text-right">Credit Spend</th>
                        <th class="px-4 py-3 text-right">Profit</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-800/80">
                    <tr v-for="row in rows" :key="row.date" class="hover:bg-neutral-900/60">
                        <td class="whitespace-nowrap px-4 py-3 font-mono text-xs text-neutral-200">{{ row.date }}</td>
                        <td class="whitespace-nowrap px-4 py-3 text-right font-mono text-neutral-200">
                            {{ row.local_generations }}
                        </td>
                        <td class="whitespace-nowrap px-4 py-3 text-right font-mono text-neutral-300">
                            {{ row.remote_generations ?? '—' }}
                        </td>
                        <td class="whitespace-nowrap px-4 py-3 text-right font-mono text-white">
                            ${{ row.local_provider_cost_usd }}
                        </td>
                        <td class="whitespace-nowrap px-4 py-3 text-right font-mono text-neutral-200">
                            {{ row.remote_provider_cost_usd !== null ? `$${row.remote_provider_cost_usd}` : '—' }}
                        </td>
                        <td
                            class="whitespace-nowrap px-4 py-3 text-right font-mono text-xs font-semibold"
                            :class="
                                row.diff_usd === null
                                    ? 'text-neutral-500'
                                    : row.is_reconciled
                                      ? 'text-emerald-400'
                                      : 'text-red-400'
                            "
                        >
                            {{ row.diff_usd !== null ? `$${row.diff_usd}` : '—' }}
                        </td>
                        <td class="whitespace-nowrap px-4 py-3 text-right font-mono text-white">
                            {{ row.credit_spend_usd }}
                        </td>
                        <td class="whitespace-nowrap px-4 py-3 text-right font-mono text-emerald-400">
                            {{ row.profit_usd }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>
</template>
