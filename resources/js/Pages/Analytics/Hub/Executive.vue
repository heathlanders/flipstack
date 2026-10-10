<script setup>
import { computed } from 'vue';
import { router, Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { route } from 'ziggy-js';
import AnalyticsSidebar from '@/Components/Analytics/AnalyticsSidebar.vue';
import ComparisonDatePicker from '@/Components/Analytics/ComparisonDatePicker.vue';
import KPICard from '@/Components/Analytics/KPICard.vue';
import BusinessHealthGauge from '@/Components/Analytics/BusinessHealthGauge.vue';
import TrendLineChart from '@/Components/Analytics/TrendLineChart.vue';
import BarChart from '@/Components/Analytics/BarChart.vue';
import DoughnutChart from '@/Components/Analytics/DoughnutChart.vue';
import { formatCurrency, formatPercent, formatNumber } from '@/Composables/useAnalyticsFormatters';

const props = defineProps({
    overview: { type: Object, default: () => ({}) },
    revenueByMarketplace: { type: Array, default: () => [] },
    revenueByCategory: { type: Array, default: () => [] },
    revenueTrend: { type: Array, default: () => [] },
    topProducts: { type: Array, default: () => [] },
    healthScore: { type: Object, default: () => ({ overall_score: 0, dimensions: {}, alerts: [] }) },
    healthHistory: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
});

function applyFilters(filters) {
    router.get(route('analytics.hub.executive'), filters, { preserveState: true, preserveScroll: true });
}

const current = computed(() => props.overview.current || {});
const changes = computed(() => props.overview.changes || {});

function changeTrend(changeValue) {
    if (changeValue === null || changeValue === undefined) return 'neutral';
    return changeValue >= 0 ? 'up' : 'down';
}

const revenueTrendLabels = computed(() => props.revenueTrend.map(d => d.date));
const revenueTrendDatasets = computed(() => [
    { label: 'Revenue', data: props.revenueTrend.map(d => d.revenue), color: '#3b82f6' },
    { label: 'Net Profit', data: props.revenueTrend.map(d => d.net_profit), color: '#10b981' },
]);

const marketplaceLabels = computed(() => props.revenueByMarketplace.map(m => m.name));
const marketplaceDatasets = computed(() => [
    { label: 'Revenue', data: props.revenueByMarketplace.map(m => m.revenue), color: '#6366f1' },
]);

const categoryLabels = computed(() => props.revenueByCategory.map(c => c.name));
const categoryData = computed(() => props.revenueByCategory.map(c => c.revenue));

const healthHistoryLabels = computed(() => props.healthHistory.map(h => h.score_date));
const healthHistoryDatasets = computed(() => [
    { label: 'Health Score', data: props.healthHistory.map(h => h.overall_score), color: '#8b5cf6' },
]);

const overallHealthScore = computed(() => Number(props.healthScore?.overall_score ?? 0));
const healthDimensions = computed(() => props.healthScore?.dimensions || {});
const healthAlerts = computed(() => (props.healthScore?.alerts || []).map(a => ({
    severity: a.level,
    message: a.message,
})));
</script>

<template>
    <Head title="Executive Dashboard" />
    <AppLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200">Executive Dashboard</h2>
                <ComparisonDatePicker :filters="filters" @change="applyFilters" />
            </div>
        </template>
        <div class="py-6">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex gap-6">
                    <AnalyticsSidebar currentPage="executive" :healthScore="overallHealthScore" />
                    <div class="flex-1 space-y-6">
                        <!-- Health Score + KPI Row -->
                        <div class="flex gap-4 items-stretch">
                            <div class="flex-shrink-0">
                                <BusinessHealthGauge
                                    :score="overallHealthScore"
                                    :dimensions="healthDimensions"
                                    :alerts="healthAlerts"
                                    compact
                                />
                            </div>
                            <div class="flex-1 grid grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-4">
                                <KPICard
                                    title="Revenue"
                                    :value="formatCurrency(current.total_revenue)"
                                    :change="changes.total_revenue"
                                    :trend="changeTrend(changes.total_revenue)"
                                    icon="currency-dollar"
                                    color="blue"
                                />
                                <KPICard
                                    title="Orders"
                                    :value="formatNumber(current.total_orders)"
                                    :change="changes.total_orders"
                                    :trend="changeTrend(changes.total_orders)"
                                    icon="cube"
                                    color="indigo"
                                />
                                <KPICard
                                    title="Net Profit"
                                    :value="formatCurrency(current.total_net_profit)"
                                    :change="changes.total_net_profit"
                                    :trend="changeTrend(changes.total_net_profit)"
                                    icon="trending-up"
                                    color="emerald"
                                />
                                <KPICard
                                    title="Avg Margin"
                                    :value="formatPercent(current.avg_margin)"
                                    :change="changes.avg_margin"
                                    :trend="changeTrend(changes.avg_margin)"
                                    icon="chart-bar"
                                    color="violet"
                                />
                                <KPICard
                                    title="Gross Profit"
                                    :value="formatCurrency(current.gross_profit)"
                                    :change="changes.gross_profit"
                                    :trend="changeTrend(changes.gross_profit)"
                                    icon="chart-bar"
                                    color="cyan"
                                />
                                <KPICard
                                    title="Total Fees"
                                    :value="formatCurrency(current.total_fees)"
                                    :change="changes.total_fees"
                                    :trend="changeTrend(changes.total_fees)"
                                    icon="currency-dollar"
                                    color="amber"
                                />
                            </div>
                        </div>

                        <!-- Revenue Trend -->
                        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Revenue Trend</h3>
                            <TrendLineChart
                                :labels="revenueTrendLabels"
                                :datasets="revenueTrendDatasets"
                                :height="300"
                                yAxisPrefix="$"
                            />
                        </div>

                        <!-- Revenue by Marketplace + Category -->
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                                <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Revenue by Marketplace</h3>
                                <BarChart
                                    :labels="marketplaceLabels"
                                    :datasets="marketplaceDatasets"
                                    :height="280"
                                    yAxisPrefix="$"
                                />
                            </div>
                            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                                <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Revenue by Category</h3>
                                <DoughnutChart
                                    :labels="categoryLabels"
                                    :data="categoryData"
                                    :height="280"
                                    prefix="$"
                                />
                            </div>
                        </div>

                        <!-- Top Products Table -->
                        <div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden">
                            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                                <h3 class="text-lg font-medium text-gray-900 dark:text-white">Top Products</h3>
                            </div>
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-50 dark:bg-gray-700">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Product</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">SKU</th>
                                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Units Sold</th>
                                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Revenue</th>
                                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">COGS</th>
                                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Gross Profit</th>
                                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Margin</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                    <tr v-for="(product, idx) in topProducts" :key="product.product_id || idx">
                                        <td class="px-6 py-3 text-sm text-gray-900 dark:text-white">
                                            <span
                                                v-if="idx < 3"
                                                class="inline-flex items-center justify-center w-5 h-5 rounded-full mr-2 text-xs font-bold"
                                                :class="{
                                                    'bg-yellow-100 text-yellow-800': idx === 0,
                                                    'bg-gray-100 text-gray-600': idx === 1,
                                                    'bg-amber-100 text-amber-700': idx === 2,
                                                }"
                                            >{{ idx + 1 }}</span>
                                            {{ product.product_name }}
                                        </td>
                                        <td class="px-6 py-3 text-sm text-gray-500 dark:text-gray-400">{{ product.sku || '-' }}</td>
                                        <td class="px-6 py-3 text-sm text-right text-gray-900 dark:text-white">{{ formatNumber(product.units_sold) }}</td>
                                        <td class="px-6 py-3 text-sm text-right text-gray-900 dark:text-white">{{ formatCurrency(product.total_revenue) }}</td>
                                        <td class="px-6 py-3 text-sm text-right text-red-600 dark:text-red-400">{{ formatCurrency(product.total_cogs) }}</td>
                                        <td class="px-6 py-3 text-sm text-right font-medium" :class="product.gross_profit >= 0 ? 'text-emerald-600' : 'text-red-600'">
                                            {{ formatCurrency(product.gross_profit) }}
                                        </td>
                                        <td class="px-6 py-3 text-sm text-right text-gray-500 dark:text-gray-400">{{ formatPercent(product.margin) }}</td>
                                    </tr>
                                    <tr v-if="!topProducts.length">
                                        <td colspan="7" class="px-6 py-12 text-center text-gray-500 dark:text-gray-400">No product data for this period</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Health Score History -->
                        <div v-if="healthHistory.length" class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Health Score History</h3>
                            <TrendLineChart
                                :labels="healthHistoryLabels"
                                :datasets="healthHistoryDatasets"
                                :height="200"
                                :showLegend="false"
                            />
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
