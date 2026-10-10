<script setup>
import { computed } from 'vue'
import { router, Head } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { route } from 'ziggy-js'
import AnalyticsSidebar from '@/Components/Analytics/AnalyticsSidebar.vue'
import ComparisonDatePicker from '@/Components/Analytics/ComparisonDatePicker.vue'
import KPICard from '@/Components/Analytics/KPICard.vue'
import TrendLineChart from '@/Components/Analytics/TrendLineChart.vue'
import EngagementFunnel from '@/Components/Analytics/EngagementFunnel.vue'

const props = defineProps({
    overview: Object,
    campaignPerformance: Array,
    deliverability: Object,
    engagementTrend: Array,
    filters: Object,
})

import { formatCurrency, formatPercent, formatNumber, formatDecimal } from '@/Composables/useAnalyticsFormatters'

const funnelStages = computed(() => [
    { label: 'Sent', count: props.deliverability?.sent || 0 },
    { label: 'Delivered', count: props.deliverability?.delivered || 0, rate: props.deliverability?.delivery_rate },
    { label: 'Opened', count: Math.round((props.deliverability?.sent || 0) * (props.overview?.open_rate || 0) / 100), rate: props.overview?.open_rate },
    { label: 'Clicked', count: Math.round((props.deliverability?.sent || 0) * (props.overview?.click_rate || 0) / 100), rate: props.overview?.click_rate },
])

const applyFilters = (newFilters) => {
    router.get(route('analytics.hub.marketing'), newFilters, { preserveState: true, preserveScroll: true })
}
</script>

<template>
    <Head :title="'Marketing Analytics'" />
    <AppLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200">Marketing Analytics</h2>
                <ComparisonDatePicker :filters="filters" @change="applyFilters" />
            </div>
        </template>
        <div class="py-6">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex gap-6">
                    <AnalyticsSidebar currentPage="marketing" />
                    <div class="flex-1 space-y-6">
                        <!-- KPI Row -->
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                            <KPICard
                                title="Campaigns Sent"
                                :value="formatNumber(overview?.campaigns_sent)"
                                color="blue"
                            />
                            <KPICard
                                title="Total Sends"
                                :value="formatNumber(overview?.total_sends)"
                                color="indigo"
                            />
                            <KPICard
                                title="Open Rate"
                                :value="formatPercent(overview?.open_rate)"
                                color="emerald"
                            />
                            <KPICard
                                title="Click Rate"
                                :value="formatPercent(overview?.click_rate)"
                                color="violet"
                            />
                        </div>

                        <!-- Engagement Funnel -->
                        <div class="bg-white dark:bg-gray-800 rounded-xl shadow p-6">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Engagement Funnel</h3>
                            <EngagementFunnel :stages="funnelStages" />
                        </div>

                        <!-- Campaign Performance Table -->
                        <div class="bg-white dark:bg-gray-800 rounded-xl shadow overflow-hidden">
                            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Campaign Performance</h3>
                            </div>
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                    <thead class="bg-gray-50 dark:bg-gray-700">
                                        <tr>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Campaign Name</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Status</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Sends</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Delivered</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Opened</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Clicked</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Open Rate</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Click Rate</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                        <tr v-for="campaign in (campaignPerformance || [])" :key="campaign.id">
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">{{ campaign.name }}</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                                <span
                                                    class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                                                    :class="{
                                                        'bg-emerald-100 text-emerald-800 dark:bg-emerald-900 dark:text-emerald-200': campaign.status === 'completed',
                                                        'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200': campaign.status === 'sending',
                                                        'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300': campaign.status === 'draft',
                                                    }"
                                                >{{ campaign.status }}</span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">{{ formatNumber(campaign.sends) }}</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">{{ formatNumber(campaign.delivered) }}</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">{{ formatNumber(campaign.opened) }}</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">{{ formatNumber(campaign.clicked) }}</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">{{ formatPercent(campaign.open_rate) }}</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">{{ formatPercent(campaign.click_rate) }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Engagement Trend -->
                        <div class="bg-white dark:bg-gray-800 rounded-xl shadow p-6">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Engagement Trend</h3>
                            <TrendLineChart
                                :labels="(engagementTrend || []).map(t => t.date)"
                                :datasets="[
                                    { label: 'Open Rate', data: (engagementTrend || []).map(t => t.open_rate), color: '#6366f1' },
                                    { label: 'Click Rate', data: (engagementTrend || []).map(t => t.click_rate), color: '#10b981' },
                                ]"
                            />
                        </div>

                        <!-- Deliverability KPIs -->
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <KPICard
                                title="Delivery Rate"
                                :value="formatPercent(deliverability?.delivery_rate)"
                                color="emerald"
                            />
                            <KPICard
                                title="Bounce Rate"
                                :value="formatPercent(deliverability?.bounce_rate)"
                                color="amber"
                            />
                            <KPICard
                                title="Failed"
                                :value="formatNumber(deliverability?.failed)"
                                color="red"
                            />
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
