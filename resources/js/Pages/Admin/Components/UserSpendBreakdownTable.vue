<script setup lang="ts">
import type { UserSpendRow } from '@/types/pixelforge';

defineProps<{
    rows: UserSpendRow[];
}>();
</script>

<template>
    <section class="space-y-3">
        <h2 class="text-lg font-semibold text-white">Per-User Breakdown</h2>

        <div class="overflow-x-auto rounded-2xl border border-neutral-800 bg-[#121212]">
            <table class="min-w-full divide-y divide-neutral-800 text-left text-sm">
                <thead class="bg-[#121212] text-xs uppercase tracking-wider text-neutral-400">
                    <tr>
                        <th class="px-4 py-3">User</th>
                        <th class="px-4 py-3 text-right">Available</th>
                        <th class="px-4 py-3 text-right">Succeeded</th>
                        <th class="px-4 py-3 text-right">Failed ($0)</th>
                        <th class="px-4 py-3 text-right">Credit Spend</th>
                        <th class="px-4 py-3 text-right">Provider Cost</th>
                        <th class="px-4 py-3 text-right">Profit</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-800/80">
                    <tr v-if="rows.length === 0">
                        <td colspan="7" class="px-4 py-8 text-center text-neutral-400">
                            No users recorded yet.
                        </td>
                    </tr>
                    <tr v-for="row in rows" :key="row.user_id" class="hover:bg-neutral-900/60">
                        <td class="px-4 py-3">
                            <p class="font-medium text-white">{{ row.name }}</p>
                            <p class="text-xs text-neutral-400">{{ row.email }}</p>
                        </td>
                        <td class="whitespace-nowrap px-4 py-3 text-right font-mono text-emerald-400">
                            {{ row.available_balance_usd }}
                        </td>
                        <td class="whitespace-nowrap px-4 py-3 text-right font-mono text-neutral-200">
                            {{ row.succeeded_count }}
                        </td>
                        <td class="whitespace-nowrap px-4 py-3 text-right font-mono text-neutral-400">
                            {{ row.failed_count }}
                        </td>
                        <td class="whitespace-nowrap px-4 py-3 text-right font-mono text-white">
                            {{ row.credit_spend_usd }}
                        </td>
                        <td class="whitespace-nowrap px-4 py-3 text-right font-mono text-white">
                            {{ row.provider_cost_usd }}
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
