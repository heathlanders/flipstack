<script setup>
import { router, Head } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { route } from 'ziggy-js'
import AnalyticsSidebar from '@/Components/Analytics/AnalyticsSidebar.vue'
import ComparisonDatePicker from '@/Components/Analytics/ComparisonDatePicker.vue'
import KPICard from '@/Components/Analytics/KPICard.vue'
import BarChart from '@/Components/Analytics/BarChart.vue'

const props = defineProps({
    overview: Object,
    responseTime: Object,
    slaCompliance: Object,
    volumeTrend: Array,
    unreadBacklog: Array,
    byMarketplace: Array,
    filters: Object,
})

import { formatCurrency, formatPercent, formatNumber, formatDecimal } from '@/Composables/useAnalyticsFormatters'

const applyFilters = (newFilters) => {
    router.get(route('analytics.hub.communication'), newFilters, { preserveState: true, preserveScroll: true })
}
</script>

<template>
    <Head :title="'Communication Analytics'" />
    <AppLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200">Communication Analytics</h2>
                <ComparisonDatePicker :filters="filters" @change="applyFilters" />
            </div>
        </template>
        <div class="py-6">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex gap-6">
                    <AnalyticsSidebar currentPage="communication" />
                    <div class="flex-1 space-y-6">
                        <!-- KPI Row -->
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                            <KPICard
                                title="Messages Today"
                                :value="formatNumber(overview?.messages_today)"
                                color="blue"
                            />
                            <KPICard
                                title="Avg Response Time"
                                :value="`${formatDecimal(overview?.avg_response_hours)} hrs`"
                                color="indigo"
                            />
                            <KPICard
                                title="SLA Compliance"
                                :value="formatPercent(overview?.sla_compliance)"
                                color="emerald"
                            />
                            <KPICard
                                title="Unread Backlog"
                                :value="formatNumber(overview?.unread_count)"
                                color="amber"
                                :alertLevel="overview?.unread_count > 10 ? 'warning' : 'normal'"
                            />
                        </div>

                        <!-- Response Time Breakdown -->
                        <div class="bg-white dark:bg-gray-800 rounded-xl shadow p-6">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Response Time Breakdown</h3>
                            <div class="grid grid-cols-3 gap-6">
                                <div class="text-center">
                                    <p class="text-sm text-gray-500 dark:text-gray-400 mb-1">Average</p>
                                    <p class="text-3xl font-bold text-gray-900 dark:text-white">{{ formatDecimal(responseTime?.avg_hours) }}<span class="text-base font-normal text-gray-500 dark:text-gray-400 ml-1">hrs</span></p>
                                </div>
                                <div class="text-center">
                                    <p class="text-sm text-gray-500 dark:text-gray-400 mb-1">Median</p>
                                    <p class="text-3xl font-bold text-gray-900 dark:text-white">{{ formatDecimal(responseTime?.median_hours) }}<span class="text-base font-normal text-gray-500 dark:text-gray-400 ml-1">hrs</span></p>
                                </div>
                                <div class="text-center">
                                    <p class="text-sm text-gray-500 dark:text-gray-400 mb-1">P95</p>
                                    <p class="text-3xl font-bold text-gray-900 dark:text-white">{{ formatDecimal(responseTime?.p95_hours) }}<span class="text-base font-normal text-gray-500 dark:text-gray-400 ml-1">hrs</span></p>
                                </div>
                            </div>
                        </div>

                        <!-- Volume + SLA -->
                        <div class="grid lg:grid-cols-2 gap-6">
                            <div class="bg-white dark:bg-gray-800 rounded-xl shadow p-6">
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Message Volume</h3>
                                <BarChart
                                    :labels="(volumeTrend || []).map(v => v.date)"
                                    :datasets="[
                                        { label: 'Inbound', data: (volumeTrend || []).map(v => v.inbound_count), color: '#6366f1' },
                                        { label: 'Outbound', data: (volumeTrend || []).map(v => v.outbound_count), color: '#10b981' },
                                    ]"
                                    :stacked="true"
                                />
                            </div>
                            <div class="bg-white dark:bg-gray-800 rounded-xl shadow p-6">
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">SLA Compliance</h3>
                                <div class="flex flex-col items-center justify-center py-8">
                                    <p class="text-6xl font-bold text-gray-900 dark:text-white">{{ formatPercent(slaCompliance?.rate) }}</p>
                                    <div class="w-full max-w-xs mt-6">
                                        <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-4">
                                            <div
                                                class="h-4 rounded-full transition-all duration-500"
                                                :class="(slaCompliance?.rate || 0) >= 90 ? 'bg-emerald-500' : (slaCompliance?.rate || 0) >= 70 ? 'bg-amber-500' : 'bg-red-500'"
                                                :style="{ width: Math.min(slaCompliance?.rate || 0, 100) + '%' }"
                                            ></div>
                                        </div>
                                    </div>
                                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-3">Target: &lt;24 hours</p>
                                </div>
                            </div>
                        </div>

                        <!-- By Marketplace Table -->
                        <div class="bg-white dark:bg-gray-800 rounded-xl shadow overflow-hidden">
                            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">By Marketplace</h3>
                            </div>
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                    <thead class="bg-gray-50 dark:bg-gray-700">
                                        <tr>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Marketplace</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Messages</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Avg Response (hrs)</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">SLA Rate</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                        <tr v-for="row in (byMarketplace || [])" :key="row.marketplace">
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">{{ row.marketplace }}</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">{{ formatNumber(row.messages) }}</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">{{ formatDecimal(row.avg_response_hours) }}</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">{{ formatPercent(row.sla_rate) }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Unread Backlog -->
                        <div class="bg-white dark:bg-gray-800 rounded-xl shadow p-6">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Unread Backlog</h3>
                            <BarChart
                                :labels="(unreadBacklog || []).map(b => b.label)"
                                :datasets="[{ label: 'Unread Messages', data: (unreadBacklog || []).map(b => b.count), color: '#f59e0b' }]"
                            />
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
