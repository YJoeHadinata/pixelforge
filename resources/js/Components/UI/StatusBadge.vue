<script setup lang="ts">
import { computed } from 'vue';
import type { GenerationStatus } from '@/types/pixelforge';

const props = defineProps<{
    status: GenerationStatus;
}>();

const badge = computed(() => {
    switch (props.status) {
        case 'submitting':
            return {
                label: 'submitting',
                classes: 'border-neutral-700 bg-neutral-900 text-neutral-300',
                pulse: true,
            };
        case 'queued_locally':
            return {
                label: 'queued (rate slot)',
                classes: 'border-amber-900/80 bg-amber-950/80 text-amber-300',
                pulse: true,
            };
        case 'queued':
            return {
                label: 'queued',
                classes: 'border-amber-900/80 bg-amber-950/80 text-amber-300',
                pulse: true,
            };
        case 'processing':
            return {
                label: 'processing',
                classes: 'border-neutral-700 bg-neutral-800 text-neutral-100',
                pulse: true,
            };
        case 'succeeded':
            return {
                label: 'succeeded',
                classes: 'border-emerald-900/80 bg-emerald-950/80 text-emerald-400',
                pulse: false,
            };
        case 'failed':
            return {
                label: 'failed',
                classes: 'border-red-900/80 bg-red-950/80 text-red-400',
                pulse: false,
            };
    }
});
</script>

<template>
    <span
        class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-0.5 text-xs font-medium"
        :class="badge.classes"
    >
        <span
            v-if="badge.pulse"
            class="h-1.5 w-1.5 animate-pulse rounded-full bg-current"
            aria-hidden="true"
        />
        {{ badge.label }}
    </span>
</template>
