<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { ref } from 'vue';
import BaseAlert from '@/Components/UI/BaseAlert.vue';
import BaseButton from '@/Components/UI/BaseButton.vue';
import StatusBadge from '@/Components/UI/StatusBadge.vue';
import UsdAmount from '@/Components/UI/UsdAmount.vue';
import { formatLocalTimestamp, formatUtcTimestamp } from '@/lib/formatDateTime';
import TokenBreakdownPopover from '@/Pages/Generations/Components/TokenBreakdownPopover.vue';
import type { GenerationItem } from '@/types/pixelforge';

const props = defineProps<{
    generation: GenerationItem;
}>();

const showTokens = ref(false);
const syncing = ref(false);
const imageLoadFailed = ref(false);

function syncStatus(): void {
    syncing.value = true;
    router.post(
        `/generations/${props.generation.id}/sync`,
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                syncing.value = false;
            },
        },
    );
}
</script>

<template>
    <article class="rounded-2xl border border-neutral-800 bg-[#121212] p-5 transition hover:border-neutral-700">
        <div class="flex flex-col gap-4 md:flex-row md:justify-between">
            <div class="min-w-0 flex-1 space-y-3">
                <div class="flex flex-wrap items-center gap-2">
                    <StatusBadge :status="generation.status" />
                    <span class="rounded-md bg-neutral-800 px-2 py-0.5 font-mono text-xs text-neutral-300">
                        {{ generation.size }}
                    </span>
                    <span class="rounded-md bg-neutral-800 px-2 py-0.5 text-xs capitalize text-neutral-300">
                        {{ generation.quality }}
                    </span>
                    <span v-if="generation.pixelforge_id" class="font-mono text-xs text-neutral-500">
                        {{ generation.pixelforge_id }}
                    </span>
                    <time
                        v-if="generation.created_at"
                        :datetime="generation.created_at"
                        :title="formatUtcTimestamp(generation.created_at)"
                        class="ml-auto text-xs text-neutral-500"
                    >
                        {{ formatLocalTimestamp(generation.created_at) }}
                    </time>
                </div>

                <p class="break-words text-sm leading-relaxed text-neutral-100">
                    {{ generation.prompt }}
                </p>

                <div v-if="generation.reference_images.length > 0" class="flex flex-wrap items-center gap-2 text-xs text-neutral-400">
                    <span>Reference images ({{ generation.reference_images.length }}):</span>
                    <a
                        v-for="(url, idx) in generation.reference_images"
                        :key="idx"
                        :href="url"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="truncate rounded bg-neutral-800 px-2 py-0.5 text-white hover:underline max-w-[220px]"
                    >
                        #{{ idx + 1 }} {{ url }}
                    </a>
                </div>

                <div class="grid gap-3 rounded-xl border border-neutral-800/80 bg-[#0a0a0a]/60 p-3 text-xs sm:grid-cols-3">
                    <div>
                        <span class="text-neutral-500">Estimated Cost</span>
                        <p class="mt-0.5 font-mono text-neutral-200">
                            {{ generation.estimated_expected_user_cost_usd }}
                            <span class="text-neutral-500">
                                ({{ generation.estimated_min_user_cost_usd }}–{{ generation.estimated_max_user_cost_usd }})
                            </span>
                        </p>
                    </div>
                    <div>
                        <span class="text-neutral-500">Actual Billed (+25%)</span>
                        <p
                            class="mt-0.5 font-mono font-semibold"
                            :class="
                                generation.status === 'succeeded'
                                    ? 'text-emerald-400'
                                    : generation.status === 'failed'
                                      ? 'text-neutral-400'
                                      : 'text-amber-300'
                            "
                        >
                            <UsdAmount
                                :value="generation.actual_user_cost_usd"
                                :placeholder="generation.status === 'failed' ? '$0.00' : 'Pending…'"
                            />
                        </p>
                    </div>
                    <div>
                        <span class="text-neutral-500">Provider Cost</span>
                        <p class="mt-0.5 font-mono text-neutral-300">
                            <UsdAmount
                                :value="generation.actual_provider_cost_usd"
                                :placeholder="generation.status === 'failed' ? '$0.000000000' : 'Pending…'"
                            />
                        </p>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <BaseButton
                        v-if="generation.usage"
                        size="sm"
                        variant="ghost"
                        @click="showTokens = !showTokens"
                    >
                        {{ showTokens ? 'Hide' : 'Show' }} token breakdown ({{ generation.usage.total_tokens.toLocaleString() }} tokens)
                    </BaseButton>

                    <BaseButton
                        v-if="generation.is_in_flight && generation.pixelforge_id"
                        size="sm"
                        variant="secondary"
                        :loading="syncing"
                        @click="syncStatus"
                    >
                        Sync Status
                    </BaseButton>
                </div>

                <TokenBreakdownPopover
                    v-if="showTokens && generation.usage"
                    :usage="generation.usage"
                    :rates="generation.rates_snapshot"
                />

                <BaseAlert v-if="generation.status === 'failed'" variant="error">
                    <span class="font-mono text-xs">[{{ generation.error_code ?? 'failed' }}]</span>
                    {{ generation.error_message ?? 'Generation failed. Nothing was charged.' }}
                </BaseAlert>
            </div>

            <div v-if="generation.status === 'succeeded'" class="w-full shrink-0 md:w-52">
                <div
                    v-if="generation.image_url && !generation.is_image_expired && !imageLoadFailed"
                    class="overflow-hidden rounded-xl border border-neutral-700 bg-[#0a0a0a]"
                >
                    <a :href="generation.image_url" target="_blank" rel="noopener noreferrer">
                        <img
                            :src="generation.image_url"
                            :alt="generation.prompt"
                            class="h-44 w-full object-cover transition hover:scale-105"
                            loading="lazy"
                            @error="imageLoadFailed = true"
                        />
                    </a>
                    <div class="flex items-center justify-between px-2.5 py-1.5 text-[11px] text-neutral-400">
                        <span>Signed PNG</span>
                        <a
                            :href="generation.image_url"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="text-neutral-200 hover:underline"
                        >
                            Open ↗
                        </a>
                    </div>
                </div>
                <div
                    v-else
                    class="flex h-44 items-center justify-center rounded-xl border border-dashed border-neutral-700 bg-[#0a0a0a]/60 p-4 text-center text-xs text-neutral-500"
                >
                    Image URL expired (Pixelforge signed URLs expire 24h after completion).
                </div>
            </div>
        </div>
    </article>
</template>
