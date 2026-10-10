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
    supplierLeadTime: { type: Array, default: () => [] },
    supplierOnTimeRate: { type: Array, default: () => [] },
    receivingAccuracy: { type: Object, default: () => ({}) },
    costTrends: { type: Array, default: () => [] },
    spendBySupplier: { type: Array, default: () => [] },
    supplierRanking: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
})

import { formatCurrency, formatPercent, formatNumber, formatDecimal } from '@/Composables/useAnalyticsFormatters'

function applyFilters(newFilters) {
    router.get(route('analytics.hub.procurement'), newFilters, { preserveState: true, preserveScroll: true })
}

const rankBadge = (rank) => {
    if (rank === 1) return 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400'
    if (rank === 2) return 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300'
    if (rank === 3) return 'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-400'
    return 'bg-transparent text-gray-500 dark:text-gray-400'
}

const leadTimeChartLabels = props.supplierLeadTime.map(s => s.supplier || s.name || '')
const leadTimeChartDatasets = [{
    label: 'Avg Lead Time (days)',
    data: props.supplierLeadTime.map(s => s.avg_lead_time || s.lead_time || 0),
    color: '#6366f1',
}]

const spendLabels = props.spendBySupplier.map(s => s.supplier || s.name || '')
const spendData = props.spendBySupplier.map(s => s.total_spend || s.spend || 0)

const costTrendLabels = props.costTrends.map(t => t.month || t.date || t.label || '')
const costTrendDatasets = [{
    label: 'Avg Unit Cost',
    data: props.costTrends.map(t => t.avg_unit_cost || t.cost || t.value || 0),
    color: '#06b6d4',
}]
</script>

<template>
    <Head title="Procurement Analytics" />
    <AppLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200">Procurement Analytics</h2>
                <ComparisonDatePicker :filters="filters" @change="applyFilters" />
            </div>
        </template>
        <div class="py-6">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex gap-6">
                    <AnalyticsSidebar currentPage="procurement" />
                    <div class="flex-1 space-y-6">
                        <!-- KPI Row -->
                        <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
                            <KPICard
                                title="Active POs"
                                :value="formatNumber(overview.active_pos)"
                                color="blue"
                                icon="cube"
                            />
                            <KPICard
                                title="Avg Lead Time"
                                :value="`${formatDecimal(overview.avg_lead_time)} days`"
                                color="indigo"
                                icon="chart-bar"
                            />
                            <KPICard
                                title="On-Time Rate"
                                :value="formatPercent(overview.on_time_rate)"
                                color="emerald"
                                icon="chart-bar"
                            />
                            <KPICard
                                title="Receiving Accuracy"
                                :value="formatPercent(overview.receiving_accuracy)"
                                color="violet"
                                icon="chart-bar"
                            />
                            <KPICard
                                title="Total Spend"
                                :value="formatCurrency(overview.total_spend)"
                                color="cyan"
                                icon="currency-dollar"
                            />
                        </div>

                        <!-- Supplier Ranking Table -->
                        <div class="bg-white dark:bg-gray-800 rounded-xl shadow p-6">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Supplier Rankings</h3>
                            <div class="overflow-x-auto">
                                <table class="w-full text-sm text-left">
                                    <thead>
                                        <tr class="border-b border-gray-200 dark:border-gray-700">
                                            <th class="px-4 py-3 font-medium text-gray-500 dark:text-gray-400">Rank</th>
                                            <th class="px-4 py-3 font-medium text-gray-500 dark:text-gray-400">Supplier</th>
                                            <th class="px-4 py-3 font-medium text-gray-500 dark:text-gray-400 text-right">Lead Time (days)</th>
                                            <th class="px-4 py-3 font-medium text-gray-500 dark:text-gray-400 text-right">On-Time Rate</th>
                                            <th class="px-4 py-3 font-medium text-gray-500 dark:text-gray-400 text-right">Quality Score</th>
                                            <th class="px-4 py-3 font-medium text-gray-500 dark:text-gray-400 text-right">Overall Score</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr
                                            v-for="(supplier, index) in supplierRanking"
                                            :key="index"
                                            class="border-b border-gray-100 dark:border-gray-700/50 hover:bg-gray-50 dark:hover:bg-gray-700/30 transition-colors"
                                            :class="index % 2 === 0 ? 'bg-white dark:bg-gray-800' : 'bg-gray-50/50 dark:bg-gray-800/50'"
                                        >
                                            <td class="px-4 py-3">
                                                <span
                                                    class="inline-flex items-center justify-center w-7 h-7 rounded-full text-xs font-bold"
                                                    :class="rankBadge(index + 1)"
                                                >
                                                    {{ index + 1 }}
                                                </span>
                                            </td>
                                            <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">{{ supplier.name || supplier.supplier }}</td>
                                            <td class="px-4 py-3 text-right text-gray-600 dark:text-gray-300">{{ formatDecimal(supplier.lead_time) }}</td>
                                            <td class="px-4 py-3 text-right text-gray-600 dark:text-gray-300">{{ formatPercent(supplier.on_time_rate) }}</td>
                                            <td class="px-4 py-3 text-right text-gray-600 dark:text-gray-300">{{ formatDecimal(supplier.quality_score) }}</td>
                                            <td class="px-4 py-3 text-right font-semibold text-gray-900 dark:text-white">{{ formatDecimal(supplier.overall_score) }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                                <p v-if="!supplierRanking.length" class="text-center py-8 text-gray-400 dark:text-gray-500">No supplier ranking data available.</p>
                            </div>
                        </div>

                        <!-- Two-column: Lead Time + Spend -->
                        <div class="grid lg:grid-cols-2 gap-6">
                            <div class="bg-white dark:bg-gray-800 rounded-xl shadow p-6">
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Lead Time by Supplier</h3>
                                <BarChart
                                    :labels="leadTimeChartLabels"
                                    :datasets="leadTimeChartDatasets"
                                    :horizontal="true"
                                    :height="Math.max(200, supplierLeadTime.length * 40)"
                                />
                                <p v-if="!supplierLeadTime.length" class="text-center py-8 text-gray-400 dark:text-gray-500">No lead time data available.</p>
                            </div>
                            <div class="bg-white dark:bg-gray-800 rounded-xl shadow p-6">
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Spend by Supplier</h3>
                                <DoughnutChart
                                    :labels="spendLabels"
                                    :data="spendData"
                                    :height="280"
                                />
                                <p v-if="!spendBySupplier.length" class="text-center py-8 text-gray-400 dark:text-gray-500">No spend data available.</p>
                            </div>
                        </div>

                        <!-- Cost Trends -->
                        <div class="bg-white dark:bg-gray-800 rounded-xl shadow p-6">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Cost Trends</h3>
                            <TrendLineChart
                                :labels="costTrendLabels"
                                :datasets="costTrendDatasets"
                                yAxisPrefix="$"
                                :height="300"
                            />
                            <p v-if="!costTrends.length" class="text-center py-8 text-gray-400 dark:text-gray-500">No cost trend data available.</p>
                        </div>

                        <!-- Receiving Accuracy Details -->
                        <div v-if="receivingAccuracy && receivingAccuracy.items_ordered" class="bg-white dark:bg-gray-800 rounded-xl shadow p-6">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Receiving Accuracy Details</h3>
                            <div class="grid grid-cols-3 gap-6">
                                <div class="text-center">
                                    <p class="text-sm text-gray-500 dark:text-gray-400">Items Ordered</p>
                                    <p class="text-2xl font-bold text-gray-900 dark:text-white mt-1">{{ formatNumber(receivingAccuracy.items_ordered) }}</p>
                                </div>
                                <div class="text-center">
                                    <p class="text-sm text-gray-500 dark:text-gray-400">Received Correct</p>
                                    <p class="text-2xl font-bold text-emerald-600 dark:text-emerald-400 mt-1">{{ formatNumber(receivingAccuracy.items_received_correct) }}</p>
                                </div>
                                <div class="text-center">
                                    <p class="text-sm text-gray-500 dark:text-gray-400">Accuracy</p>
                                    <p class="text-2xl font-bold text-indigo-600 dark:text-indigo-400 mt-1">{{ formatPercent(receivingAccuracy.accuracy_pct) }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
