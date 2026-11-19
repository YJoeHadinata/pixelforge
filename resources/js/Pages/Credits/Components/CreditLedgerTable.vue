<script setup lang="ts">
import { formatLocalTimestamp, formatUtcTimestamp } from '@/lib/formatDateTime';
import type { CreditTransactionItem } from '@/types/pixelforge';

defineProps<{
    transactions: CreditTransactionItem[];
}>();

function typeBadgeClass(type: CreditTransactionItem['type']): string {
    switch (type) {
        case 'top_up':
            return 'border-emerald-900/80 bg-emerald-950/80 text-emerald-400';
        case 'reserve':
            return 'border-amber-900/80 bg-amber-950/80 text-amber-300';
        case 'release':
            return 'border-neutral-700 bg-neutral-800/90 text-white';
        case 'charge':
            return 'border-red-900/80 bg-red-950/80 text-red-300';
        case 'adjustment':
            return 'border-neutral-700 bg-neutral-800 text-neutral-300';
    }
}
</script>

<template>
    <div class="overflow-x-auto rounded-2xl border border-neutral-800 bg-[#121212]">
        <table class="min-w-full divide-y divide-neutral-800 text-left text-sm">
            <thead class="bg-[#121212] text-xs uppercase tracking-wider text-neutral-400">
                <tr>
                    <th class="px-4 py-3">Time</th>
                    <th class="px-4 py-3">Type</th>
                    <th class="px-4 py-3">Description</th>
                    <th class="px-4 py-3 text-right">Amount</th>
                    <th class="px-4 py-3 text-right">Balance After</th>
                    <th class="px-4 py-3 text-right">Reserved After</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-neutral-800/80">
                <tr v-if="transactions.length === 0">
                    <td colspan="6" class="px-4 py-8 text-center text-neutral-400">
                        No credit transactions recorded yet.
                    </td>
                </tr>
                <tr v-for="tx in transactions" :key="tx.id" class="hover:bg-neutral-900/60">
                    <td class="whitespace-nowrap px-4 py-3 text-xs text-neutral-400">
                        <time
                            v-if="tx.created_at"
                            :datetime="tx.created_at"
                            :title="formatUtcTimestamp(tx.created_at)"
                        >
                            {{ formatLocalTimestamp(tx.created_at) }}
                        </time>
                        <span v-else>—</span>
                    </td>
                    <td class="whitespace-nowrap px-4 py-3">
                        <span
                            class="inline-flex rounded-full border px-2 py-0.5 text-xs font-medium uppercase"
                            :class="typeBadgeClass(tx.type)"
                        >
                            {{ tx.type }}
                        </span>
                    </td>
                    <td class="px-4 py-3 text-neutral-200">{{ tx.description }}</td>
                    <td
                        class="whitespace-nowrap px-4 py-3 text-right font-mono font-semibold"
                        :class="tx.amount_picodollars >= 0 ? 'text-emerald-400' : 'text-red-400'"
                    >
                        {{ tx.amount_picodollars > 0 ? `+${tx.amount_usd}` : tx.amount_usd }}
                    </td>
                    <td class="whitespace-nowrap px-4 py-3 text-right font-mono text-neutral-200">
                        {{ tx.balance_after_usd }}
                    </td>
                    <td class="whitespace-nowrap px-4 py-3 text-right font-mono text-amber-300">
                        {{ tx.reserved_after_usd }}
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
