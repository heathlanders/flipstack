<script setup>
import { router, Head } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { route } from 'ziggy-js'
import AnalyticsSidebar from '@/Components/Analytics/AnalyticsSidebar.vue'
import ComparisonDatePicker from '@/Components/Analytics/ComparisonDatePicker.vue'
import KPICard from '@/Components/Analytics/KPICard.vue'
import BarChart from '@/Components/Analytics/BarChart.vue'
import DoughnutChart from '@/Components/Analytics/DoughnutChart.vue'
import TrendLineChart from '@/Components/Analytics/TrendLineChart.vue'

const props = defineProps({
    overview: { type: Object, default: () => ({}) },
    syncHealth: { type: Object, default: () => ({}) },
    listingHealth: { type: Object, default: () => ({}) },
    responseTimeTrend: { type: Array, default: () => [] },
    errorBreakdown: { type: Array, default: () => [] },
    feeAnalysis: { type: Object, default: () => ({}) },
    crossMarketplace: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
})

import { formatCurrency, formatPercent, formatNumber, formatDecimal } from '@/Composables/useAnalyticsFormatters'

function applyFilters(newFilters) {
    router.get(route('analytics.hub.marketplace'), newFilters, { preserveState: true, preserveScroll: true })
}

// Sync / Response time trend
const syncTrendLabels = props.responseTimeTrend.map(t => t.date || t.label || '')
const syncTrendDatasets = [{
    label: 'Avg Response Time (ms)',
    data: props.responseTimeTrend.map(t => t.avg_ms || t.value || 0),
    color: '#8b5cf6',
}]

// Sync health trend if available
const syncHealthTrendLabels = (props.syncHealth.trend || props.responseTimeTrend).map(t => t.date || t.label || '')
const syncHealthTrendDatasets = [{
    label: 'Sync Success Rate %',
    data: (props.syncHealth.trend || props.responseTimeTrend).map(t => t.success_rate || t.sync_rate || 0),
    color: '#10b981',
}]

// Fee analysis
const feeByType = props.feeAnalysis.by_type || []
const feeByMarketplace = props.feeAnalysis.by_marketplace || []

const feeTypeLabels = feeByType.map(f => f.type || f.name || '')
const feeTypeDatasets = [{
    label: 'Fee Amount',
    data: feeByType.map(f => f.amount || f.total || 0),
    color: '#f59e0b',
}]

const feeMarketplaceLabels = feeByMarketplace.map(f => f.marketplace || f.name || '')
const feeMarketplaceData = feeByMarketplace.map(f => f.amount || f.total || 0)

// Listing health
const listingLabels = Object.keys(props.listingHealth).length
    ? [
        props.listingHealth.active !== undefined ? 'Active' : null,
        props.listingHealth.pending !== undefined ? 'Pending' : null,
        props.listingHealth.error !== undefined ? 'Error' : null,
        props.listingHealth.ended !== undefined ? 'Ended' : null,
    ].filter(Boolean)
    : []
const listingData = listingLabels.map(l => props.listingHealth[l.toLowerCase()] || 0)
const listingColors = ['#10b981', '#f59e0b', '#ef4444', '#6b7280']
</script>

<template>
    <Head title="Marketplace Analytics" />
    <AppLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200">Marketplace Analytics</h2>
                <ComparisonDatePicker :filters="filters" @change="applyFilters" />
            </div>
        </template>
        <div class="py-6">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex gap-6">
                    <AnalyticsSidebar currentPage="marketplace" />
                    <div class="flex-1 space-y-6">
                        <!-- KPI Row -->
                        <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
                            <KPICard
                                title="Total Listings"
                                :value="formatNumber(overview.total_listings)"
                                color="blue"
                                icon="cube"
                            />
                            <KPICard
                                title="Active %"
                                :value="formatPercent(overview.active_pct)"
                                color="emerald"
                                icon="chart-bar"
                            />
                            <KPICard
                                title="Sync Success Rate"
                                :value="formatPercent(overview.sync_success_rate)"
                                color="indigo"
                                icon="chart-bar"
                            />
                            <KPICard
                                title="Avg Response Time"
                                :value="`${formatNumber(overview.avg_response_time_ms)} ms`"
                                color="violet"
                                icon="chart-bar"
                            />
                            <KPICard
                                title="Error Rate"
                                :value="formatPercent(overview.error_rate)"
                                color="red"
                                icon="exclamation-triangle"
                            />
                        </div>

                        <!-- Cross-Marketplace Comparison -->
                        <div class="bg-white dark:bg-gray-800 rounded-xl shadow p-6">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Marketplace Comparison</h3>
                            <div class="overflow-x-auto">
                                <table class="w-full text-sm text-left">
                                    <thead>
                                        <tr class="border-b border-gray-200 dark:border-gray-700">
                                            <th class="px-4 py-3 font-medium text-gray-500 dark:text-gray-400">Marketplace</th>
                                            <th class="px-4 py-3 font-medium text-gray-500 dark:text-gray-400 text-right">Active Listings</th>
                                            <th class="px-4 py-3 font-medium text-gray-500 dark:text-gray-400 text-right">Sync Success %</th>
                                            <th class="px-4 py-3 font-medium text-gray-500 dark:text-gray-400 text-right">Avg Response Time</th>
                                            <th class="px-4 py-3 font-medium text-gray-500 dark:text-gray-400 text-right">Revenue</th>
                                            <th class="px-4 py-3 font-medium text-gray-500 dark:text-gray-400 text-right">Fees</th>
                                            <th class="px-4 py-3 font-medium text-gray-500 dark:text-gray-400 text-right">Fee Rate %</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr
                                            v-for="(mp, index) in crossMarketplace"
                                            :key="index"
                                            class="border-b border-gray-100 dark:border-gray-700/50 hover:bg-gray-50 dark:hover:bg-gray-700/30 transition-colors"
                                            :class="index % 2 === 0 ? 'bg-white dark:bg-gray-800' : 'bg-gray-50/50 dark:bg-gray-800/50'"
                                        >
                                            <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">{{ mp.marketplace || mp.name }}</td>
                                            <td class="px-4 py-3 text-right text-gray-600 dark:text-gray-300">{{ formatNumber(mp.active_listings) }}</td>
                                            <td class="px-4 py-3 text-right text-gray-600 dark:text-gray-300">{{ formatPercent(mp.sync_success_rate) }}</td>
                                            <td class="px-4 py-3 text-right text-gray-600 dark:text-gray-300">{{ formatNumber(mp.avg_response_time) }} ms</td>
                                            <td class="px-4 py-3 text-right text-gray-600 dark:text-gray-300">{{ formatCurrency(mp.revenue) }}</td>
                                            <td class="px-4 py-3 text-right text-gray-600 dark:text-gray-300">{{ formatCurrency(mp.fees) }}</td>
                                            <td class="px-4 py-3 text-right text-gray-600 dark:text-gray-300">{{ formatPercent(mp.fee_rate) }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                                <p v-if="!crossMarketplace.length" class="text-center py-8 text-gray-400 dark:text-gray-500">No marketplace data available.</p>
                            </div>
                        </div>

                        <!-- Two-column: Sync Trend + Response Time -->
                        <div class="grid lg:grid-cols-2 gap-6">
                            <div class="bg-white dark:bg-gray-800 rounded-xl shadow p-6">
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Sync Success Rate Trend</h3>
                                <TrendLineChart
                                    :labels="syncHealthTrendLabels"
                                    :datasets="syncHealthTrendDatasets"
                                    :height="280"
                                />
                            </div>
                            <div class="bg-white dark:bg-gray-800 rounded-xl shadow p-6">
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Response Time Trend</h3>
                                <TrendLineChart
                                    :labels="syncTrendLabels"
                                    :datasets="syncTrendDatasets"
                                    :height="280"
                                />
                            </div>
                        </div>

                        <!-- Error Breakdown -->
                        <div class="bg-white dark:bg-gray-800 rounded-xl shadow p-6">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Top Errors</h3>
                            <div class="overflow-x-auto">
                                <table class="w-full text-sm text-left">
                                    <thead>
                                        <tr class="border-b border-gray-200 dark:border-gray-700">
                                            <th class="px-4 py-3 font-medium text-gray-500 dark:text-gray-400">Error Message</th>
                                            <th class="px-4 py-3 font-medium text-gray-500 dark:text-gray-400 text-right">Count</th>
                                            <th class="px-4 py-3 font-medium text-gray-500 dark:text-gray-400">Marketplace</th>
                                            <th class="px-4 py-3 font-medium text-gray-500 dark:text-gray-400">Last Occurred</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr
                                            v-for="(error, index) in errorBreakdown"
                                            :key="index"
                                            class="border-b border-gray-100 dark:border-gray-700/50 hover:bg-gray-50 dark:hover:bg-gray-700/30 transition-colors"
                                            :class="index % 2 === 0 ? 'bg-white dark:bg-gray-800' : 'bg-gray-50/50 dark:bg-gray-800/50'"
                                        >
                                            <td class="px-4 py-3 text-gray-900 dark:text-white max-w-md truncate">{{ error.message || error.error }}</td>
                                            <td class="px-4 py-3 text-right font-semibold text-red-600 dark:text-red-400">{{ formatNumber(error.count) }}</td>
                                            <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ error.marketplace || '-' }}</td>
                                            <td class="px-4 py-3 text-gray-500 dark:text-gray-400">{{ error.last_occurred || error.last_at || '-' }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                                <p v-if="!errorBreakdown.length" class="text-center py-8 text-gray-400 dark:text-gray-500">No errors recorded.</p>
                            </div>
                        </div>

                        <!-- Fee Analysis -->
                        <div class="grid lg:grid-cols-2 gap-6">
                            <div class="bg-white dark:bg-gray-800 rounded-xl shadow p-6">
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Fees by Type</h3>
                                <BarChart
                                    v-if="feeByType.length"
                                    :labels="feeTypeLabels"
                                    :datasets="feeTypeDatasets"
                                    yAxisPrefix="$"
                                    :height="280"
                                />
                                <p v-else class="text-center py-8 text-gray-400 dark:text-gray-500">No fee data available.</p>
                            </div>
                            <div class="bg-white dark:bg-gray-800 rounded-xl shadow p-6">
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Fees by Marketplace</h3>
                                <DoughnutChart
                                    v-if="feeByMarketplace.length"
                                    :labels="feeMarketplaceLabels"
                                    :data="feeMarketplaceData"
                                    :height="280"
                                />
                                <p v-else class="text-center py-8 text-gray-400 dark:text-gray-500">No fee data available.</p>
                            </div>
                        </div>

                        <!-- Listing Health -->
                        <div class="bg-white dark:bg-gray-800 rounded-xl shadow p-6">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Listing Health</h3>
                            <div class="max-w-md mx-auto">
                                <DoughnutChart
                                    v-if="listingLabels.length"
                                    :labels="listingLabels"
                                    :data="listingData"
                                    :colors="listingColors"
                                    :height="280"
                                />
                                <p v-else class="text-center py-8 text-gray-400 dark:text-gray-500">No listing health data available.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
