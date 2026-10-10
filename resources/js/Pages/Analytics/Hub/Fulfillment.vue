<script setup>
import { computed } from 'vue';
import { router, Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { route } from 'ziggy-js';
import AnalyticsSidebar from '@/Components/Analytics/AnalyticsSidebar.vue';
import ComparisonDatePicker from '@/Components/Analytics/ComparisonDatePicker.vue';
import KPICard from '@/Components/Analytics/KPICard.vue';
import TrendLineChart from '@/Components/Analytics/TrendLineChart.vue';
import BarChart from '@/Components/Analytics/BarChart.vue';
import DoughnutChart from '@/Components/Analytics/DoughnutChart.vue';
import TimeInStageChart from '@/Components/Analytics/TimeInStageChart.vue';

const props = defineProps({
    overview: { type: Object, default: () => ({}) },
    orderToShipTime: { type: Object, default: () => ({}) },
    onTimeRate: { type: Number, default: 0 },
    pickAccuracy: { type: Number, default: 0 },
    backlogAge: { type: Object, default: () => ({}) },
    returnMetrics: { type: Object, default: () => ({}) },
    carrierPerformance: { type: Array, default: () => [] },
    timeInStage: { type: Object, default: () => ({}) },
    processingTrend: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
});

import { formatCurrency, formatPercent, formatNumber, formatDecimal } from '@/Composables/useAnalyticsFormatters';

function applyFilters(filters) {
    router.get(route('analytics.hub.fulfillment'), filters, { preserveState: true, preserveScroll: true });
}

// Processing Trend chart data
const processingTrendLabels = computed(() => props.processingTrend.map(d => d.date));
const processingTrendDatasets = computed(() => [
    { label: 'Orders Shipped', data: props.processingTrend.map(d => d.orders_shipped), color: '#3b82f6' },
    { label: 'Avg Processing Hours', data: props.processingTrend.map(d => d.avg_processing_hours), color: '#f59e0b', dashed: true },
]);

// Backlog Age chart data
const backlogLabels = ['0-1 Day', '1-3 Days', '3-7 Days', '7+ Days'];
const backlogDatasets = computed(() => [
    {
        label: 'Orders',
        data: [
            props.backlogAge.zero_to_one || 0,
            props.backlogAge.one_to_three || 0,
            props.backlogAge.three_to_seven || 0,
            props.backlogAge.seven_plus || 0,
        ],
        color: '#6366f1',
    },
]);

// Return Reasons chart data
const returnReasonLabels = computed(() => (props.returnMetrics.reasons || []).map(r => r.reason));
const returnReasonData = computed(() => (props.returnMetrics.reasons || []).map(r => r.count));
</script>

<template>
    <Head title="Fulfillment Analytics" />
    <AppLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200">Fulfillment Analytics</h2>
                <ComparisonDatePicker :filters="filters" @change="applyFilters" />
            </div>
        </template>
        <div class="py-6">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex gap-6">
                    <AnalyticsSidebar currentPage="fulfillment" />
                    <div class="flex-1 space-y-6">
                        <!-- KPI Row -->
                        <div class="grid grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-4">
                            <KPICard
                                title="Orders Shipped"
                                :value="formatNumber(overview.orders_shipped)"
                                icon="cube"
                                color="blue"
                            />
                            <KPICard
                                title="Avg Processing Time"
                                :value="`${formatDecimal(overview.avg_processing_hours)}h`"
                                icon="chart-bar"
                                color="indigo"
                            />
                            <KPICard
                                title="On-Time Rate"
                                :value="formatPercent(onTimeRate)"
                                icon="chart-bar"
                                color="emerald"
                                :alertLevel="onTimeRate < 90 ? 'warning' : 'normal'"
                            />
                            <KPICard
                                title="Pick Accuracy"
                                :value="formatPercent(pickAccuracy)"
                                icon="chart-bar"
                                color="violet"
                                :alertLevel="pickAccuracy < 98 ? 'warning' : 'normal'"
                            />
                            <KPICard
                                title="Return Rate"
                                :value="formatPercent(returnMetrics.return_rate)"
                                icon="archive"
                                color="amber"
                                :alertLevel="(returnMetrics.return_rate || 0) > 5 ? 'warning' : 'normal'"
                            />
                            <KPICard
                                title="Cost / Order"
                                :value="formatCurrency(overview.cost_per_order)"
                                icon="currency-dollar"
                                color="cyan"
                            />
                        </div>

                        <!-- Time in Stage -->
                        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Time in Stage</h3>
                            <TimeInStageChart :stages="timeInStage" />
                        </div>

                        <!-- Processing Trend -->
                        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Processing Trend</h3>
                            <TrendLineChart
                                :labels="processingTrendLabels"
                                :datasets="processingTrendDatasets"
                                :height="300"
                            />
                        </div>

                        <!-- Backlog Age -->
                        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Backlog Age Distribution</h3>
                            <BarChart
                                :labels="backlogLabels"
                                :datasets="backlogDatasets"
                                :height="260"
                            />
                        </div>

                        <!-- Return Metrics Panel -->
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                            <div class="space-y-4">
                                <h3 class="text-lg font-medium text-gray-900 dark:text-white">Return Metrics</h3>
                                <KPICard
                                    title="Return Rate"
                                    :value="formatPercent(returnMetrics.return_rate)"
                                    icon="archive"
                                    color="amber"
                                />
                                <KPICard
                                    title="Avg Refund Time"
                                    :value="`${formatDecimal(returnMetrics.avg_refund_days)} days`"
                                    icon="chart-bar"
                                    color="indigo"
                                />
                                <KPICard
                                    title="Restocking Rate"
                                    :value="formatPercent(returnMetrics.restocking_rate)"
                                    icon="cube"
                                    color="emerald"
                                />
                            </div>
                            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                                <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Return Reasons</h3>
                                <DoughnutChart
                                    v-if="returnReasonLabels.length"
                                    :labels="returnReasonLabels"
                                    :data="returnReasonData"
                                    :height="260"
                                />
                                <p v-else class="text-center text-gray-500 dark:text-gray-400 py-8">No return data for this period</p>
                            </div>
                        </div>

                        <!-- Carrier Performance Table -->
                        <div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden">
                            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                                <h3 class="text-lg font-medium text-gray-900 dark:text-white">Carrier Performance</h3>
                            </div>
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-50 dark:bg-gray-700">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Carrier</th>
                                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Volume</th>
                                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Avg Cost</th>
                                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Avg Transit Days</th>
                                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">On-Time Rate</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                    <tr v-for="carrier in carrierPerformance" :key="carrier.name">
                                        <td class="px-6 py-3 text-sm font-medium text-gray-900 dark:text-white">{{ carrier.name }}</td>
                                        <td class="px-6 py-3 text-sm text-right text-gray-900 dark:text-white">{{ formatNumber(carrier.volume) }}</td>
                                        <td class="px-6 py-3 text-sm text-right text-gray-900 dark:text-white">{{ formatCurrency(carrier.avg_cost) }}</td>
                                        <td class="px-6 py-3 text-sm text-right text-gray-500 dark:text-gray-400">{{ formatDecimal(carrier.avg_transit_days) }}</td>
                                        <td class="px-6 py-3 text-sm text-right">
                                            <span
                                                class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium"
                                                :class="carrier.on_time_rate >= 95
                                                    ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-400'
                                                    : carrier.on_time_rate >= 85
                                                        ? 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400'
                                                        : 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400'"
                                            >
                                                {{ formatPercent(carrier.on_time_rate) }}
                                            </span>
                                        </td>
                                    </tr>
                                    <tr v-if="!carrierPerformance.length">
                                        <td colspan="5" class="px-6 py-12 text-center text-gray-500 dark:text-gray-400">No carrier data for this period</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
