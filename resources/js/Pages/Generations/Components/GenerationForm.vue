<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
import BaseAlert from '@/Components/UI/BaseAlert.vue';
import BaseButton from '@/Components/UI/BaseButton.vue';
import ReferenceImagesField from '@/Pages/Generations/Components/ReferenceImagesField.vue';
import { countUnicodeChars } from '@/lib/pixelforgePricing';
import type { ImageQuality, ImageSize, SelectOption } from '@/types/pixelforge';

const props = defineProps<{
    sizes: SelectOption<ImageSize>[];
    qualities: SelectOption<ImageQuality>[];
    tokensPerImage: number;
    canAfford: boolean;
}>();

const emit = defineEmits<{
    draftChange: [draft: { prompt: string; size: ImageSize; quality: ImageQuality; referenceImages: string[] }];
    openBuyCredits: [];
}>();

const form = useForm({
    prompt: '',
    size: '1024x1024' as ImageSize,
    quality: 'medium' as ImageQuality,
    reference_images: [] as string[],
});

const charCount = computed(() => countUnicodeChars(form.prompt));
const creditError = computed(() => (form.errors as Record<string, string | undefined>).credits);

const testKeywords = ['[succeed]', '[fail]', '[slow]', '[duplicate]', '[blocked]', '[overloaded]'];

function appendKeyword(keyword: string): void {
    const trimmed = form.prompt.trim();
    if (trimmed.includes(keyword)) {
        return;
    }

    form.prompt = trimmed === '' ? `A sunlit architectural courtyard ${keyword}` : `${trimmed} ${keyword}`;
}

watch(
    () => [form.prompt, form.size, form.quality, [...form.reference_images]] as const,
    ([prompt, size, quality, referenceImages]) => {
        emit('draftChange', { prompt, size, quality, referenceImages: [...referenceImages] });
    },
    { immediate: true, deep: true },
);

function submit(): void {
    form.post('/generations', {
        preserveScroll: true,
        onSuccess: () => {
            form.reset('prompt', 'reference_images');
        },
    });
}
</script>

<template>
    <form class="space-y-5 rounded-2xl border border-neutral-800 bg-[#121212] p-6 shadow-lg" @submit.prevent="submit">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h1 class="text-xl font-bold tracking-tight text-white">Generate Image</h1>
            <span class="text-xs text-neutral-400">Async via Pixelforge API</span>
        </div>

        <BaseAlert v-if="creditError" variant="warning" title="Insufficient Credits">
            <p>{{ creditError }}</p>
            <div class="mt-2">
                <BaseButton size="sm" @click="emit('openBuyCredits')">+ Buy Credits</BaseButton>
            </div>
        </BaseAlert>

        <div>
            <div class="flex items-center justify-between gap-2">
                <label for="generation-prompt" class="block text-sm font-medium text-neutral-200">Prompt</label>
                <span
                    class="font-mono text-xs"
                    :class="charCount > 4000 ? 'text-red-400' : 'text-neutral-400'"
                >
                    {{ charCount.toLocaleString() }} / 4,000 chars
                </span>
            </div>

            <textarea
                id="generation-prompt"
                v-model="form.prompt"
                rows="4"
                maxlength="4000"
                required
                placeholder="Describe the image you want to generate (e.g., A lighthouse on a cliff at dawn, oil painting)"
                class="mt-1.5 w-full rounded-xl border border-neutral-700 bg-[#0a0a0a] px-3.5 py-2.5 text-sm text-white placeholder-neutral-500 focus:border-neutral-200 focus:outline-none focus:ring-1 focus:ring-neutral-500"
            />

            <p v-if="form.errors.prompt" class="mt-1.5 text-xs text-red-400">
                {{ form.errors.prompt }}
            </p>

            <div class="mt-2 flex flex-wrap items-center gap-1.5">
                <span class="text-xs text-neutral-500">Test keywords:</span>
                <button
                    v-for="kw in testKeywords"
                    :key="kw"
                    type="button"
                    class="rounded-md border border-neutral-800 bg-neutral-900 px-2 py-0.5 font-mono text-[11px] text-neutral-300 transition hover:border-neutral-600 hover:text-white"
                    @click="appendKeyword(kw)"
                >
                    {{ kw }}
                </button>
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <p class="block text-sm font-medium text-neutral-200">Size</p>
                <div class="mt-1.5 space-y-2">
                    <label
                        v-for="option in sizes"
                        :key="option.value"
                        class="flex cursor-pointer items-center justify-between rounded-xl border px-3.5 py-2.5 text-sm transition"
                        :class="
                            form.size === option.value
                                ? 'border-neutral-200 bg-neutral-800/90 text-white'
                                : 'border-neutral-800 bg-[#0a0a0a]/60 text-neutral-300 hover:border-neutral-700'
                        "
                    >
                        <span>{{ option.label }}</span>
                        <input v-model="form.size" type="radio" :value="option.value" class="sr-only" />
                        <span
                            class="h-2.5 w-2.5 rounded-full"
                            :class="form.size === option.value ? 'bg-white' : 'bg-neutral-700'"
                        />
                    </label>
                </div>
                <p v-if="form.errors.size" class="mt-1 text-xs text-red-400">{{ form.errors.size }}</p>
            </div>

            <div>
                <p class="block text-sm font-medium text-neutral-200">Quality</p>
                <div class="mt-1.5 space-y-2">
                    <label
                        v-for="option in qualities"
                        :key="option.value"
                        class="flex cursor-pointer items-center justify-between rounded-xl border px-3.5 py-2.5 text-sm transition"
                        :class="
                            form.quality === option.value
                                ? 'border-neutral-200 bg-neutral-800/90 text-white'
                                : 'border-neutral-800 bg-[#0a0a0a]/60 text-neutral-300 hover:border-neutral-700'
                        "
                    >
                        <span>{{ option.label }}</span>
                        <input v-model="form.quality" type="radio" :value="option.value" class="sr-only" />
                        <span
                            class="h-2.5 w-2.5 rounded-full"
                            :class="form.quality === option.value ? 'bg-white' : 'bg-neutral-700'"
                        />
                    </label>
                </div>
                <p v-if="form.errors.quality" class="mt-1 text-xs text-red-400">{{ form.errors.quality }}</p>
            </div>
        </div>

        <ReferenceImagesField
            v-model="form.reference_images"
            :tokens-per-image="tokensPerImage"
            :errors="form.errors"
        />

        <div class="flex items-center justify-end gap-3 pt-1">
            <BaseButton
                type="submit"
                :loading="form.processing"
                :disabled="!canAfford || charCount === 0 || charCount > 4000"
            >
                Generate Image
            </BaseButton>
        </div>
    </form>
</template>
