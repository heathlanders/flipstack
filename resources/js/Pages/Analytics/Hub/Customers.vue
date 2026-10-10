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
    overview: Object,
    ltvDistribution: Array,
    repeatRate: Object,
    aovTrend: Array,
    acquisitionByMarketplace: Array,
    topCustomers: Array,
    segments: Array,
    filters: Object,
})

import { formatCurrency, formatPercent, formatNumber, formatDecimal } from '@/Composables/useAnalyticsFormatters'

const applyFilters = (newFilters) => {
    router.get(route('analytics.hub.customers'), newFilters, { preserveState: true, preserveScroll: true })
}
</script>

<template>
    <Head :title="'Customer Analytics'" />
    <AppLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200">Customer Analytics</h2>
                <ComparisonDatePicker :filters="filters" @change="applyFilters" />
            </div>
        </template>
        <div class="py-6">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex gap-6">
                    <AnalyticsSidebar currentPage="customers" />
                    <div class="flex-1 space-y-6">
                        <!-- KPI Row -->
                        <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
                            <KPICard
                                title="Total Customers"
                                :value="formatNumber(overview?.total_customers)"
                                color="blue"
                            />
                            <KPICard
                                title="New This Period"
                                :value="formatNumber(overview?.new_customers)"
                                color="indigo"
                            />
                            <KPICard
                                title="Repeat Rate"
                                :value="formatPercent(overview?.repeat_rate)"
                                color="emerald"
                            />
                            <KPICard
                                title="Avg LTV"
                                :value="formatCurrency(overview?.avg_ltv)"
                                color="violet"
                            />
                            <KPICard
                                title="Avg AOV"
                                :value="formatCurrency(overview?.avg_aov)"
                                color="cyan"
                            />
                        </div>

                        <!-- LTV Distribution + Segments -->
                        <div class="grid lg:grid-cols-2 gap-6">
                            <div class="bg-white dark:bg-gray-800 rounded-xl shadow p-6">
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Lifetime Value Distribution</h3>
                                <BarChart
                                    :labels="(ltvDistribution || []).map(d => d.label)"
                                    :datasets="[{ label: 'Customers', data: (ltvDistribution || []).map(d => d.count), color: '#6366f1' }]"
                                />
                            </div>
                            <div class="bg-white dark:bg-gray-800 rounded-xl shadow p-6">
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Customer Segments</h3>
                                <DoughnutChart
                                    :labels="(segments || []).map(s => s.label)"
                                    :data="(segments || []).map(s => s.count)"
                                    :colors="['#6366f1', '#10b981', '#f59e0b', '#ef4444']"
                                />
                            </div>
                        </div>

                        <!-- AOV Trend -->
                        <div class="bg-white dark:bg-gray-800 rounded-xl shadow p-6">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Average Order Value Trend</h3>
                            <TrendLineChart
                                :labels="(aovTrend || []).map(t => t.month)"
                                :datasets="[{ label: 'Avg Order Value', data: (aovTrend || []).map(t => t.avg_order_value), color: '#06b6d4' }]"
                            />
                        </div>

                        <!-- Acquisition by Marketplace -->
                        <div class="bg-white dark:bg-gray-800 rounded-xl shadow p-6">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Acquisition by Marketplace</h3>
                            <BarChart
                                :labels="(acquisitionByMarketplace || []).map(a => a.marketplace)"
                                :datasets="[{ label: 'Customers', data: (acquisitionByMarketplace || []).map(a => a.count), color: '#8b5cf6' }]"
                            />
                        </div>

                        <!-- Top Customers Table -->
                        <div class="bg-white dark:bg-gray-800 rounded-xl shadow overflow-hidden">
                            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Top Customers</h3>
                            </div>
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                    <thead class="bg-gray-50 dark:bg-gray-700">
                                        <tr>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Name</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Email</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Orders</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Total Spent</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Avg Order</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">First Order</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Last Order</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                        <tr v-for="customer in (topCustomers || [])" :key="customer.id">
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">{{ customer.name }}</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{{ customer.email }}</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">{{ formatNumber(customer.orders_count) }}</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">{{ formatCurrency(customer.total_spent) }}</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">{{ formatCurrency(customer.avg_order) }}</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{{ customer.first_order }}</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{{ customer.last_order }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Repeat Purchase Stats -->
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <KPICard
                                title="One-Time Customers"
                                :value="formatNumber(repeatRate?.one_time_count)"
                                color="gray"
                            />
                            <KPICard
                                title="Repeat Customers"
                                :value="formatNumber(repeatRate?.repeat_count)"
                                color="emerald"
                            />
                            <KPICard
                                title="Repeat Rate"
                                :value="formatPercent(repeatRate?.rate)"
                                color="indigo"
                            />
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
