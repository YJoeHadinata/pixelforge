<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { reactive } from 'vue';
import AppLayout from '@/Components/Layout/AppLayout.vue';
import BaseAlert from '@/Components/UI/BaseAlert.vue';
import BaseButton from '@/Components/UI/BaseButton.vue';
import DailyUtcReconciliationTable from '@/Pages/Admin/Components/DailyUtcReconciliationTable.vue';
import SummaryKpiCards from '@/Pages/Admin/Components/SummaryKpiCards.vue';
import UserSpendBreakdownTable from '@/Pages/Admin/Components/UserSpendBreakdownTable.vue';
import type { AdminReportData } from '@/types/pixelforge';

const props = defineProps<{
    report: AdminReportData;
}>();

const filters = reactive({
    start_date: props.report.filters.start_date,
    end_date: props.report.filters.end_date,
});

function applyDateFilter(): void {
    router.get('/admin/reports', filters, {
        preserveState: true,
        preserveScroll: true,
    });
}
</script>

<template>
    <Head title="Admin Report" />

    <AppLayout>
        <div class="space-y-8">
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight text-white">Operator & Usage Report</h1>
                    <p class="mt-1 text-sm text-neutral-400">
                        Live financial reconciliation between local database records and Pixelforge <span class="font-mono">GET /v1/usage</span>.
                    </p>
                </div>

                <form class="flex flex-wrap items-end gap-2" @submit.prevent="applyDateFilter">
                    <div>
                        <label for="report-start" class="block text-xs text-neutral-400">Start (UTC)</label>
                        <input
                            id="report-start"
                            v-model="filters.start_date"
                            type="date"
                            class="mt-1 rounded-lg border border-neutral-700 bg-neutral-900 px-3 py-1.5 font-mono text-xs text-white focus:border-neutral-200 focus:outline-none"
                        />
                    </div>
                    <div>
                        <label for="report-end" class="block text-xs text-neutral-400">End (UTC)</label>
                        <input
                            id="report-end"
                            v-model="filters.end_date"
                            type="date"
                            class="mt-1 rounded-lg border border-neutral-700 bg-neutral-900 px-3 py-1.5 font-mono text-xs text-white focus:border-neutral-200 focus:outline-none"
                        />
                    </div>
                    <BaseButton type="submit" size="sm" variant="secondary">Apply Range</BaseButton>
                </form>
            </div>

            <BaseAlert v-if="report.summary.remote_error" variant="warning" title="Pixelforge /v1/usage warning">
                {{ report.summary.remote_error }}
            </BaseAlert>

            <SummaryKpiCards :summary="report.summary" />
            <DailyUtcReconciliationTable :rows="report.daily" />
            <UserSpendBreakdownTable :rows="report.by_user" />
        </div>
    </AppLayout>
</template>
