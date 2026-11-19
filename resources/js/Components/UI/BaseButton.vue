<script setup lang="ts">
import { computed } from 'vue';

const props = withDefaults(
    defineProps<{
        type?: 'button' | 'submit' | 'reset';
        variant?: 'primary' | 'secondary' | 'danger' | 'ghost';
        size?: 'sm' | 'md';
        disabled?: boolean;
        loading?: boolean;
    }>(),
    {
        type: 'button',
        variant: 'primary',
        size: 'md',
        disabled: false,
        loading: false,
    },
);

const classes = computed(() => {
    const base =
        'inline-flex items-center justify-center gap-2 rounded-lg font-medium transition focus:outline-none focus:ring-2 focus:ring-neutral-400 focus:ring-offset-2 focus:ring-offset-[#0a0a0a] disabled:cursor-not-allowed disabled:opacity-50';
    const sizes = props.size === 'sm' ? 'px-3 py-1.5 text-xs' : 'px-4 py-2.5 text-sm';
    const variants: Record<string, string> = {
        primary: 'bg-white text-neutral-950 hover:bg-neutral-200',
        secondary: 'border border-neutral-800 bg-neutral-900 text-neutral-100 hover:border-neutral-700 hover:bg-neutral-800',
        danger: 'border border-red-900/80 bg-red-950/70 text-red-300 hover:bg-red-950',
        ghost: 'text-neutral-400 hover:bg-neutral-900 hover:text-white',
    };

    return `${base} ${sizes} ${variants[props.variant]}`;
});
</script>

<template>
    <button :type="type" :disabled="disabled || loading" :class="classes">
        <span
            v-if="loading"
            class="h-3.5 w-3.5 animate-spin rounded-full border-2 border-current border-t-transparent"
            aria-hidden="true"
        />
        <slot />
    </button>
</template>
