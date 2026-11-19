<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

interface ErrorCta {
    label: string;
    href: string;
}

const props = defineProps<{
    status: number;
    title: string;
    description: string;
    primaryCta: ErrorCta;
    secondaryCta?: ErrorCta | null;
    canRetry?: boolean;
}>();

const badgeClass = computed(() => {
    if (props.status === 403 || props.status >= 500) {
        return 'border-red-900/70 bg-red-950/60 text-red-400';
    }
    if (props.status === 419 || props.status === 429) {
        return 'border-amber-900/70 bg-amber-950/60 text-amber-300';
    }

    return 'border-neutral-700 bg-neutral-900 text-neutral-300';
});

function reloadPage(): void {
    window.location.reload();
}

function goBack(): void {
    if (window.history.length > 1) {
        window.history.back();

        return;
    }

    window.location.href = props.primaryCta.href;
}
</script>

<template>
    <Head :title="`${status} — ${title}`" />

    <main class="flex min-h-screen items-center justify-center bg-[#0a0a0a] px-4 py-12 text-neutral-100">
        <div class="w-full max-w-lg rounded-2xl border border-neutral-800 bg-[#121212] p-8 shadow-2xl">
            <div class="flex items-center justify-between">
                <Link
                    :href="primaryCta.href"
                    class="inline-flex items-center gap-2.5 text-sm font-semibold text-white transition hover:opacity-90"
                >
                    <span class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-white font-mono text-xs font-extrabold text-neutral-950">
                        PF
                    </span>
                    <span>Pixelforge Studio</span>
                </Link>

                <span
                    class="inline-flex items-center rounded-full border px-2.5 py-0.5 font-mono text-xs font-semibold"
                    :class="badgeClass"
                >
                    HTTP {{ status }}
                </span>
            </div>

            <div class="mt-8">
                <p class="font-mono text-5xl font-bold tracking-tight text-white">
                    {{ status }}
                </p>
                <h1 class="mt-3 text-xl font-semibold tracking-tight text-white">
                    {{ title }}
                </h1>
                <p class="mt-2 text-sm leading-relaxed text-neutral-400">
                    {{ description }}
                </p>
            </div>

            <div class="mt-8 flex flex-wrap items-center gap-3 border-t border-neutral-800 pt-6">
                <Link
                    :href="primaryCta.href"
                    class="inline-flex items-center justify-center rounded-lg bg-white px-4 py-2 text-sm font-medium text-neutral-950 transition hover:bg-neutral-200 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-neutral-300"
                >
                    {{ primaryCta.label }}
                </Link>

                <button
                    v-if="canRetry"
                    type="button"
                    class="inline-flex cursor-pointer items-center justify-center rounded-lg border border-neutral-700 bg-neutral-900 px-4 py-2 text-sm font-medium text-neutral-100 transition hover:bg-neutral-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-neutral-400"
                    @click="reloadPage"
                >
                    Try Again
                </button>

                <Link
                    v-if="secondaryCta"
                    :href="secondaryCta.href"
                    class="inline-flex items-center justify-center rounded-lg border border-neutral-700 bg-neutral-900 px-4 py-2 text-sm font-medium text-neutral-100 transition hover:bg-neutral-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-neutral-400"
                >
                    {{ secondaryCta.label }}
                </Link>

                <button
                    v-else
                    type="button"
                    class="inline-flex cursor-pointer items-center justify-center rounded-lg border border-neutral-800 bg-transparent px-4 py-2 text-sm font-medium text-neutral-300 transition hover:border-neutral-700 hover:bg-neutral-900 hover:text-white focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-neutral-400"
                    @click="goBack"
                >
                    Go Back
                </button>
            </div>
        </div>
    </main>
</template>
