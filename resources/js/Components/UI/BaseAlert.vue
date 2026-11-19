<script setup lang="ts">
import { computed } from 'vue';

const props = withDefaults(
    defineProps<{
        variant?: 'info' | 'success' | 'warning' | 'error';
        title?: string;
    }>(),
    {
        variant: 'info',
        title: undefined,
    },
);

const styles = computed(() => {
    const map: Record<string, string> = {
        info: 'border-neutral-800 bg-neutral-900 text-neutral-200',
        success: 'border-emerald-900/80 bg-emerald-950/60 text-emerald-300',
        warning: 'border-amber-900/80 bg-amber-950/60 text-amber-300',
        error: 'border-red-900/80 bg-red-950/60 text-red-300',
    };

    return map[props.variant];
});
</script>

<template>
    <div role="alert" class="rounded-xl border px-4 py-3 text-sm" :class="styles">
        <p v-if="title" class="font-semibold">{{ title }}</p>
        <div :class="title ? 'mt-1' : ''">
            <slot />
        </div>
    </div>
</template>
