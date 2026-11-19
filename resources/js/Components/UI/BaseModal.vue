<script setup lang="ts">
import { onBeforeUnmount, onMounted } from 'vue';

const props = defineProps<{
    open: boolean;
    title: string;
}>();

const emit = defineEmits<{
    close: [];
}>();

function onKeydown(event: KeyboardEvent): void {
    if (event.key === 'Escape' && props.open) {
        emit('close');
    }
}

onMounted(() => window.addEventListener('keydown', onKeydown));
onBeforeUnmount(() => window.removeEventListener('keydown', onKeydown));
</script>

<template>
    <Teleport to="body">
        <div
            v-if="open"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 p-4 backdrop-blur-sm"
            role="dialog"
            aria-modal="true"
            :aria-label="title"
            @click.self="emit('close')"
        >
            <div class="w-full max-w-md rounded-xl border border-neutral-800 bg-[#121212] p-6 shadow-2xl">
                <div class="flex items-center justify-between gap-4 border-b border-neutral-800 pb-4">
                    <h2 class="text-lg font-semibold text-white">{{ title }}</h2>
                    <button
                        type="button"
                        class="rounded-lg p-1.5 text-neutral-400 hover:bg-neutral-800 hover:text-white"
                        aria-label="Close modal"
                        @click="emit('close')"
                    >
                        ✕
                    </button>
                </div>
                <div class="mt-4">
                    <slot />
                </div>
            </div>
        </div>
    </Teleport>
</template>
