<script setup lang="ts">
defineProps<{
    value: string | null;
    placeholder?: string;
}>();
</script>

<template>
    <span class="font-mono tabular-nums">
        {{ value ?? placeholder ?? '—' }}
    </span>
</template>
