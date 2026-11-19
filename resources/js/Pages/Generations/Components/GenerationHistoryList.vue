<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import GenerationCard from '@/Pages/Generations/Components/GenerationCard.vue';
import type { GenerationItem, PaginationMeta } from '@/types/pixelforge';

defineProps<{
    items: GenerationItem[];
    pagination: PaginationMeta;
    hasInFlight: boolean;
}>();
</script>

<template>
    <section class="space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <div>
                <h2 class="text-lg font-semibold text-white">Generation History</h2>
                <p class="text-xs text-neutral-400">
                    {{ pagination.total }} total generations ·
                    <span v-if="hasInFlight" class="text-white">Live polling active while jobs run…</span>
                    <span v-else>All visible generations settled</span>
                </p>
            </div>
        </div>

        <div
            v-if="items.length === 0"
            class="rounded-2xl border border-dashed border-neutral-800 bg-[#121212]/70 p-10 text-center text-sm text-neutral-400"
        >
            No generations yet. Submit a prompt above to see live cost estimation, webhook updates, and generated images.
        </div>

        <div v-else class="space-y-4">
            <GenerationCard v-for="item in items" :key="item.id" :generation="item" />
        </div>

        <div
            v-if="pagination.last_page > 1"
            class="flex items-center justify-between border-t border-neutral-800 pt-4 text-sm text-neutral-400"
        >
            <span>Page {{ pagination.current_page }} of {{ pagination.last_page }}</span>
            <div class="flex gap-2">
                <Link
                    v-if="pagination.current_page > 1"
                    :href="`/generations?page=${pagination.current_page - 1}`"
                    class="rounded-lg border border-neutral-700 bg-neutral-800 px-3 py-1.5 text-xs text-neutral-200 hover:bg-neutral-700"
                >
                    Previous
                </Link>
                <Link
                    v-if="pagination.current_page < pagination.last_page"
                    :href="`/generations?page=${pagination.current_page + 1}`"
                    class="rounded-lg border border-neutral-700 bg-neutral-800 px-3 py-1.5 text-xs text-neutral-200 hover:bg-neutral-700"
                >
                    Next
                </Link>
            </div>
        </div>
    </section>
</template>
