<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import AppLayout from '@/Components/Layout/AppLayout.vue';
import CostEstimatorPanel from '@/Pages/Generations/Components/CostEstimatorPanel.vue';
import GenerationForm from '@/Pages/Generations/Components/GenerationForm.vue';
import GenerationHistoryList from '@/Pages/Generations/Components/GenerationHistoryList.vue';
import { estimateGenerationCost } from '@/lib/pixelforgePricing';
import type {
    AuthUser,
    GenerationItem,
    HistoryPayload,
    ImageQuality,
    ImageSize,
    PricingConfig,
    SelectOption,
} from '@/types/pixelforge';

const props = defineProps<{
    pricing: PricingConfig;
    markupPercent: number;
    history: HistoryPayload;
    sizes: SelectOption<ImageSize>[];
    qualities: SelectOption<ImageQuality>[];
}>();

const page = usePage();
const authUser = computed(() => page.props.auth?.user as AuthUser | null);

const draft = ref({
    prompt: '',
    size: '1024x1024' as ImageSize,
    quality: 'medium' as ImageQuality,
    referenceImages: [] as string[],
});

const liveItems = ref<GenerationItem[]>(props.history.items);
const liveHasInFlight = ref<boolean>(props.history.has_in_flight);
const liveBalance = ref<{
    available_balance_picodollars: number;
    available_balance_usd: string;
    reserved_balance_usd: string;
} | null>(null);

watch(
    () => props.history,
    (next) => {
        liveItems.value = next.items;
        liveHasInFlight.value = next.has_in_flight;
        liveBalance.value = null;
    },
    { deep: true },
);

const availableBalancePicodollars = computed(
    () => liveBalance.value?.available_balance_picodollars ?? authUser.value?.available_balance_picodollars ?? 0,
);
const availableBalanceUsd = computed(
    () => liveBalance.value?.available_balance_usd ?? authUser.value?.available_balance_usd ?? '$0.00',
);
const reservedBalanceUsd = computed(
    () => liveBalance.value?.reserved_balance_usd ?? authUser.value?.reserved_balance_usd ?? '$0.00',
);

const activeReferenceImageCount = computed(
    () => draft.value.referenceImages.filter((url) => url.trim() !== '').length,
);

const estimate = computed(() =>
    estimateGenerationCost(
        draft.value.prompt,
        draft.value.size,
        draft.value.quality,
        activeReferenceImageCount.value,
        props.pricing,
        props.markupPercent,
    ),
);

const canAfford = computed(() => BigInt(availableBalancePicodollars.value) >= estimate.value.maxUserCostPicodollars);

let pollTimer: ReturnType<typeof setInterval> | null = null;

async function pollStatus(): Promise<void> {
    try {
        const res = await fetch('/generations/status', {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        });
        if (!res.ok) {
            return;
        }
        const data = (await res.json()) as {
            items: GenerationItem[];
            has_in_flight: boolean;
            balance: {
                available_balance_picodollars: number;
                available_balance_usd: string;
                reserved_balance_usd: string;
            };
        };

        if (props.history.pagination.current_page === 1) {
            liveItems.value = data.items;
        }
        liveHasInFlight.value = data.has_in_flight;
        liveBalance.value = data.balance;
    } catch {
        // Ignore transient poll failure; retry next tick
    }
}

function startPolling(): void {
    if (pollTimer !== null) {
        return;
    }
    pollTimer = setInterval(() => {
        void pollStatus();
    }, 3000);
}

function stopPolling(): void {
    if (pollTimer !== null) {
        clearInterval(pollTimer);
        pollTimer = null;
    }
}

watch(
    liveHasInFlight,
    (inFlight) => {
        if (inFlight) {
            startPolling();
        } else {
            stopPolling();
        }
    },
    { immediate: true },
);

onBeforeUnmount(() => {
    stopPolling();
});
</script>

<template>
    <Head title="Studio" />

    <AppLayout
        v-slot="{ openBuyCredits }"
        :available-balance-override="availableBalanceUsd"
        :reserved-balance-override="reservedBalanceUsd"
    >
        <div class="space-y-8">
            <div class="grid gap-6 lg:grid-cols-12">
                <div class="lg:col-span-7">
                    <GenerationForm
                        :sizes="sizes"
                        :qualities="qualities"
                        :tokens-per-image="pricing.tokens.per_reference_image"
                        :can-afford="canAfford"
                        @draft-change="draft = $event"
                        @open-buy-credits="openBuyCredits"
                    />
                </div>

                <div class="lg:col-span-5">
                    <CostEstimatorPanel
                        :estimate="estimate"
                        :pricing="pricing"
                        :markup-percent="markupPercent"
                        :available-balance-picodollars="availableBalancePicodollars"
                        :available-balance-usd="availableBalanceUsd"
                        @open-buy-credits="openBuyCredits"
                    />
                </div>
            </div>

            <GenerationHistoryList
                :items="liveItems"
                :pagination="history.pagination"
                :has-in-flight="liveHasInFlight"
            />
        </div>
    </AppLayout>
</template>
