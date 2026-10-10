<script setup>
import { computed } from 'vue'
import { router, Head } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { route } from 'ziggy-js'
import AnalyticsSidebar from '@/Components/Analytics/AnalyticsSidebar.vue'
import ComparisonDatePicker from '@/Components/Analytics/ComparisonDatePicker.vue'
import KPICard from '@/Components/Analytics/KPICard.vue'
import TrendLineChart from '@/Components/Analytics/TrendLineChart.vue'
import DoughnutChart from '@/Components/Analytics/DoughnutChart.vue'

const props = defineProps({
    overview: Object,
    repricingFrequency: Array,
    marginImpact: Object,
    competitivePosition: Object,
    rulePerformance: Array,
    filters: Object,
})

import { formatCurrency, formatPercent, formatNumber, formatDecimal } from '@/Composables/useAnalyticsFormatters'

const sortedRulePerformance = computed(() => {
    return [...(props.rulePerformance || [])].sort((a, b) => (b.reprices || 0) - (a.reprices || 0))
})

const applyFilters = (newFilters) => {
    router.get(route('analytics.hub.pricing'), newFilters, { preserveState: true, preserveScroll: true })
}
</script>

<template>
    <Head :title="'Pricing Analytics'" />
    <AppLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200">Pricing Analytics</h2>
                <ComparisonDatePicker :filters="filters" @change="applyFilters" />
            </div>
        </template>
        <div class="py-6">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex gap-6">
                    <AnalyticsSidebar currentPage="pricing" />
                    <div class="flex-1 space-y-6">
                        <!-- KPI Row -->
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                            <KPICard
                                title="Total Reprices"
                                :value="formatNumber(overview?.total_reprices)"
                                color="blue"
                            />
                            <KPICard
                                title="Avg Price Change"
                                :value="formatPercent(overview?.avg_price_change)"
                                color="indigo"
                            />
                            <KPICard
                                title="Active Rules"
                                :value="formatNumber(overview?.active_rules)"
                                color="violet"
                            />
                            <KPICard
                                title="Margin Impact"
                                :value="formatCurrency(marginImpact?.avg_change)"
                                color="emerald"
                            />
                        </div>

                        <!-- Repricing Frequency -->
                        <div class="bg-white dark:bg-gray-800 rounded-xl shadow p-6">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Repricing Frequency</h3>
                            <TrendLineChart
                                :labels="(repricingFrequency || []).map(r => r.date)"
                                :datasets="[{ label: 'Reprices', data: (repricingFrequency || []).map(r => r.reprice_count), color: '#6366f1' }]"
                            />
                        </div>

                        <!-- Competitive Position + Price Impact -->
                        <div class="grid lg:grid-cols-2 gap-6">
                            <div class="bg-white dark:bg-gray-800 rounded-xl shadow p-6">
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Competitive Position</h3>
                                <DoughnutChart
                                    :labels="['Cheaper', 'Equal', 'Higher']"
                                    :data="[competitivePosition?.cheaper || 0, competitivePosition?.equal || 0, competitivePosition?.higher || 0]"
                                    :colors="['#10b981', '#6366f1', '#ef4444']"
                                />
                            </div>
                            <div class="bg-white dark:bg-gray-800 rounded-xl shadow p-6">
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Average Price Impact</h3>
                                <div class="flex items-center justify-center gap-6 py-8">
                                    <div class="text-center">
                                        <p class="text-sm text-gray-500 dark:text-gray-400 mb-1">Before</p>
                                        <p class="text-3xl font-bold text-gray-900 dark:text-white">{{ formatCurrency(marginImpact?.avg_before) }}</p>
                                    </div>
                                    <div class="flex items-center">
                                        <svg class="w-8 h-8 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6" />
                                        </svg>
                                    </div>
                                    <div class="text-center">
                                        <p class="text-sm text-gray-500 dark:text-gray-400 mb-1">After</p>
                                        <p class="text-3xl font-bold text-gray-900 dark:text-white">{{ formatCurrency(marginImpact?.avg_after) }}</p>
                                    </div>
                                    <div class="text-center ml-4">
                                        <p class="text-sm text-gray-500 dark:text-gray-400 mb-1">Change</p>
                                        <p
                                            class="text-3xl font-bold"
                                            :class="(marginImpact?.avg_change || 0) >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400'"
                                        >{{ formatCurrency(marginImpact?.avg_change) }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Rule Performance Table -->
                        <div class="bg-white dark:bg-gray-800 rounded-xl shadow overflow-hidden">
                            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Rule Performance</h3>
                            </div>
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                    <thead class="bg-gray-50 dark:bg-gray-700">
                                        <tr>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Rule Name</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Reprices</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Avg Change %</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                        <tr v-for="rule in sortedRulePerformance" :key="rule.id">
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">{{ rule.name }}</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">{{ formatNumber(rule.reprices) }}</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">{{ formatPercent(rule.avg_change_percent) }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
