<script setup lang="ts">
import { ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import BaseAlert from '@/Components/UI/BaseAlert.vue';
import BaseButton from '@/Components/UI/BaseButton.vue';
import BaseModal from '@/Components/UI/BaseModal.vue';

defineProps<{
    open: boolean;
    availableBalanceUsd: string;
    reservedBalanceUsd: string;
}>();

const emit = defineEmits<{
    close: [];
}>();

const presets = ['1.00', '5.00', '10.00', '25.00'];
const draining = ref(false);

const form = useForm({
    amount_usd: '5.00',
});

function submitTopUp(): void {
    form.post('/credits', {
        preserveScroll: true,
        onSuccess: () => {
            emit('close');
        },
    });
}

function drainBalance(): void {
    draining.value = true;
    router.post(
        '/credits/drain',
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                draining.value = false;
            },
            onSuccess: () => {
                emit('close');
            },
        },
    );
}
</script>

<template>
    <BaseModal :open="open" title="Buy Simulated Credits" @close="emit('close')">
        <div class="space-y-4">
            <div class="rounded-xl border border-neutral-800 bg-[#0a0a0a]/70 p-3.5 text-sm">
                <div class="flex items-center justify-between text-neutral-300">
                    <span>Available Balance</span>
                    <span class="font-mono font-semibold text-emerald-400">{{ availableBalanceUsd }}</span>
                </div>
                <div class="mt-1 flex items-center justify-between text-xs text-neutral-400">
                    <span>Reserved (In-Flight Holds)</span>
                    <span class="font-mono">{{ reservedBalanceUsd }}</span>
                </div>
            </div>

            <form class="space-y-4" @submit.prevent="submitTopUp">
                <div>
                    <p class="text-xs font-medium uppercase tracking-wider text-neutral-400">Quick Amounts</p>
                    <div class="mt-2 grid grid-cols-4 gap-2">
                        <button
                            v-for="preset in presets"
                            :key="preset"
                            type="button"
                            class="rounded-lg border px-3 py-2 font-mono text-xs font-semibold transition"
                            :class="
                                form.amount_usd === preset
                                    ? 'border-neutral-200 bg-neutral-800 text-white'
                                    : 'border-neutral-700 bg-neutral-900 text-neutral-300 hover:border-neutral-700'
                            "
                            @click="form.amount_usd = preset"
                        >
                            +${{ preset }}
                        </button>
                    </div>
                </div>

                <div>
                    <label for="buy-credits-amount" class="block text-sm font-medium text-neutral-200">
                        Custom Amount (USD)
                    </label>
                    <input
                        id="buy-credits-amount"
                        v-model="form.amount_usd"
                        type="text"
                        inputmode="decimal"
                        placeholder="5.00"
                        class="mt-1.5 w-full rounded-lg border border-neutral-700 bg-[#0a0a0a] px-3 py-2 font-mono text-sm text-white focus:border-neutral-200 focus:outline-none focus:ring-1 focus:ring-neutral-500"
                    />
                    <p v-if="form.errors.amount_usd" class="mt-1 text-xs text-red-400">
                        {{ form.errors.amount_usd }}
                    </p>
                </div>

                <BaseAlert variant="info">
                    Simulated billing only — no real payment is charged. Every top-up is recorded in the credit ledger.
                </BaseAlert>

                <div class="flex flex-wrap items-center justify-between gap-2 pt-2">
                    <BaseButton
                        type="button"
                        variant="danger"
                        size="sm"
                        :loading="draining"
                        @click="drainBalance"
                    >
                        Set $0.00 (Test Block)
                    </BaseButton>

                    <div class="flex items-center gap-2">
                        <BaseButton type="button" variant="ghost" @click="emit('close')">Cancel</BaseButton>
                        <BaseButton type="submit" :loading="form.processing">
                            Add ${{ form.amount_usd || '0.00' }}
                        </BaseButton>
                    </div>
                </div>
            </form>
        </div>
    </BaseModal>
</template>
