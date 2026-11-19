<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import TopNavbar from '@/Components/Layout/TopNavbar.vue';
import BaseAlert from '@/Components/UI/BaseAlert.vue';
import BuyCreditsModal from '@/Pages/Credits/Components/BuyCreditsModal.vue';
import type { AuthUser } from '@/types/pixelforge';

const props = defineProps<{
    availableBalanceOverride?: string;
    reservedBalanceOverride?: string;
    buyModalOpen?: boolean;
}>();

const emit = defineEmits<{
    'update:buyModalOpen': [value: boolean];
}>();

const page = usePage();
const user = computed(() => page.props.auth?.user as AuthUser | null);
const flash = computed(() => (page.props.flash ?? {}) as { success?: string | null; error?: string | null });

const internalBuyOpen = ref(false);

const isBuyOpen = computed({
    get: () => (props.buyModalOpen !== undefined ? props.buyModalOpen : internalBuyOpen.value),
    set: (val: boolean) => {
        internalBuyOpen.value = val;
        emit('update:buyModalOpen', val);
    },
});

const availableBalanceUsd = computed(
    () => props.availableBalanceOverride ?? user.value?.available_balance_usd ?? '$0.00',
);
const reservedBalanceUsd = computed(
    () => props.reservedBalanceOverride ?? user.value?.reserved_balance_usd ?? '$0.00',
);
</script>

<template>
    <div class="min-h-screen bg-[#0a0a0a] text-neutral-100">
        <TopNavbar
            :available-balance-usd="availableBalanceUsd"
            :reserved-balance-usd="reservedBalanceUsd"
            @open-buy-credits="isBuyOpen = true"
        />

        <main class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:py-8">
            <div v-if="flash.success || flash.error" class="mb-6 space-y-3">
                <BaseAlert v-if="flash.success" variant="success">
                    {{ flash.success }}
                </BaseAlert>
                <BaseAlert v-if="flash.error" variant="error">
                    {{ flash.error }}
                </BaseAlert>
            </div>

            <slot :open-buy-credits="() => (isBuyOpen = true)" />
        </main>

        <BuyCreditsModal
            :open="isBuyOpen"
            :available-balance-usd="availableBalanceUsd"
            :reserved-balance-usd="reservedBalanceUsd"
            @close="isBuyOpen = false"
        />
    </div>
</template>
