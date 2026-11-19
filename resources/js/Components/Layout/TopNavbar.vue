<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import BaseButton from '@/Components/UI/BaseButton.vue';
import type { AuthUser } from '@/types/pixelforge';

const props = defineProps<{
    availableBalanceUsd: string;
    reservedBalanceUsd: string;
}>();

const emit = defineEmits<{
    openBuyCredits: [];
}>();

const page = usePage();
const user = computed(() => page.props.auth?.user as AuthUser | null);
const currentUrl = computed(() => page.url);

function logout(): void {
    router.post('/logout');
}
</script>

<template>
    <header class="sticky top-0 z-40 border-b border-neutral-800/90 bg-[#0a0a0a]/95 backdrop-blur">
        <div class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-4 px-4 py-3 sm:px-6">
            <div class="flex items-center gap-6">
                <Link href="/generations" class="flex items-center gap-2.5 text-sm font-semibold tracking-tight text-white">
                    <span class="inline-flex h-7 w-7 items-center justify-center rounded-md bg-white font-mono text-xs font-extrabold text-neutral-950">
                        PF
                    </span>
                    <span>pixelforge</span>
                </Link>

                <nav class="flex items-center gap-1 text-sm" aria-label="Main navigation">
                    <Link
                        href="/generations"
                        class="rounded-md px-3 py-1.5 font-medium transition"
                        :class="
                            currentUrl.startsWith('/generations')
                                ? 'bg-neutral-800/90 text-white'
                                : 'text-neutral-400 hover:bg-neutral-900 hover:text-white'
                        "
                    >
                        Studio
                    </Link>
                    <Link
                        href="/credits"
                        class="rounded-md px-3 py-1.5 font-medium transition"
                        :class="
                            currentUrl.startsWith('/credits')
                                ? 'bg-neutral-800/90 text-white'
                                : 'text-neutral-400 hover:bg-neutral-900 hover:text-white'
                        "
                    >
                        Credits & Ledger
                    </Link>
                    <Link
                        v-if="user?.is_admin"
                        href="/admin/reports"
                        class="rounded-md px-3 py-1.5 font-medium transition"
                        :class="
                            currentUrl.startsWith('/admin/reports')
                                ? 'bg-neutral-800/90 text-white'
                                : 'text-neutral-400 hover:bg-neutral-900 hover:text-white'
                        "
                    >
                        Admin Report
                    </Link>
                </nav>
            </div>

            <div v-if="user" class="flex flex-wrap items-center gap-3">
                <div class="flex items-center gap-2 rounded-lg border border-neutral-800 bg-[#121212] px-3 py-1.5 text-xs">
                    <span class="text-neutral-400">Available:</span>
                    <span class="font-mono font-semibold text-white">{{ props.availableBalanceUsd }}</span>
                    <span v-if="props.reservedBalanceUsd !== '$0.00'" class="text-amber-300">
                        ({{ props.reservedBalanceUsd }} held)
                    </span>
                </div>

                <BaseButton size="sm" variant="secondary" @click="emit('openBuyCredits')">
                    + Buy Credits
                </BaseButton>

                <div class="hidden items-center gap-2 border-l border-neutral-800 pl-3 text-xs text-neutral-400 sm:flex">
                    <span>{{ user.email }}</span>
                </div>

                <BaseButton size="sm" variant="ghost" @click="logout">Log out</BaseButton>
            </div>
        </div>
    </header>
</template>
