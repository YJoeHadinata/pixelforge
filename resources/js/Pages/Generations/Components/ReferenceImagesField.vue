<script setup lang="ts">
import BaseButton from '@/Components/UI/BaseButton.vue';

const props = defineProps<{
    modelValue: string[];
    tokensPerImage: number;
    errors?: Record<string, string>;
}>();

const emit = defineEmits<{
    'update:modelValue': [value: string[]];
}>();

function addUrl(): void {
    if (props.modelValue.length >= 4) {
        return;
    }

    emit('update:modelValue', [...props.modelValue, '']);
}

function removeUrl(index: number): void {
    const next = [...props.modelValue];
    next.splice(index, 1);
    emit('update:modelValue', next);
}

function updateUrl(index: number, value: string): void {
    const next = [...props.modelValue];
    next[index] = value;
    emit('update:modelValue', next);
}
</script>

<template>
    <div class="space-y-2">
        <div class="flex items-center justify-between gap-2">
            <label class="text-sm font-medium text-neutral-200">
                Reference Image URLs
                <span class="ml-1 text-xs font-normal text-neutral-400">
                    (optional, max 4 · {{ tokensPerImage }} tokens each)
                </span>
            </label>

            <BaseButton
                v-if="modelValue.length < 4"
                type="button"
                size="sm"
                variant="secondary"
                @click="addUrl"
            >
                + Add URL
            </BaseButton>
        </div>

        <div v-if="modelValue.length === 0" class="rounded-lg border border-dashed border-neutral-800 px-3 py-2 text-xs text-neutral-500">
            No reference images attached. Click "+ Add URL" to guide image style.
        </div>

        <div v-else class="space-y-2">
            <div v-for="(url, index) in modelValue" :key="index" class="space-y-1">
                <div class="flex items-center gap-2">
                    <input
                        :value="url"
                        type="url"
                        placeholder="https://example.com/style.png"
                        class="w-full rounded-lg border border-neutral-700 bg-[#0a0a0a] px-3 py-2 text-sm text-white placeholder-neutral-500 focus:border-neutral-200 focus:outline-none focus:ring-1 focus:ring-neutral-500"
                        @input="updateUrl(index, ($event.target as HTMLInputElement).value)"
                    />
                    <BaseButton type="button" size="sm" variant="ghost" @click="removeUrl(index)">
                        Remove
                    </BaseButton>
                </div>
                <p v-if="errors?.[`reference_images.${index}`]" class="text-xs text-red-400">
                    {{ errors[`reference_images.${index}`] }}
                </p>
            </div>
        </div>

        <p v-if="errors?.reference_images" class="text-xs text-red-400">
            {{ errors.reference_images }}
        </p>
    </div>
</template>
