<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLayout from '@/Components/Layout/AppLayout.vue';
import BaseButton from '@/Components/UI/BaseButton.vue';
import CreditLedgerTable from '@/Pages/Credits/Components/CreditLedgerTable.vue';
import type { AuthUser, CreditTransactionItem } from '@/types/pixelforge';

defineProps<{
    transactions: CreditTransactionItem[];
}>();

const page = usePage();
const user = computed(() => page.props.auth?.user as AuthUser | null);
</script>

<template>
    <Head title="Credits & Ledger" />

    <AppLayout v-slot="{ openBuyCredits }">
        <div class="space-y-6">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight text-white">Credit Balance & Ledger</h1>
                    <p class="mt-1 text-sm text-neutral-400">
                        Every reservation, release, charge (+25% markup), and simulated top-up is tracked at picodollar precision.
                    </p>
                </div>

                <BaseButton @click="openBuyCredits">+ Buy Credits</BaseButton>
            </div>

            <div v-if="user" class="grid gap-4 sm:grid-cols-3">
                <div class="rounded-2xl border border-neutral-800 bg-[#121212] p-5">
                    <p class="text-xs font-medium uppercase tracking-wider text-neutral-400">Available Balance</p>
                    <p class="mt-2 font-mono text-2xl font-bold text-emerald-400">{{ user.available_balance_usd }}</p>
                </div>
                <div class="rounded-2xl border border-neutral-800 bg-[#121212] p-5">
                    <p class="text-xs font-medium uppercase tracking-wider text-neutral-400">Settled Credit Balance</p>
                    <p class="mt-2 font-mono text-2xl font-bold text-white">{{ user.credit_balance_usd }}</p>
                </div>
                <div class="rounded-2xl border border-neutral-800 bg-[#121212] p-5">
                    <p class="text-xs font-medium uppercase tracking-wider text-neutral-400">Reserved (In-Flight)</p>
                    <p class="mt-2 font-mono text-2xl font-bold text-amber-300">{{ user.reserved_balance_usd }}</p>
                </div>
            </div>

            <CreditLedgerTable :transactions="transactions" />
        </div>
    </AppLayout>
</template>
