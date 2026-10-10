<script setup>
import { reactive } from 'vue'
import { router, Head } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { route } from 'ziggy-js'

const props = defineProps({
    summary: { type: Object, default: () => ({}) },
    byMarketplace: { type: Array, default: () => [] },
    dailyTrend: { type: Array, default: () => [] },
    topProducts: { type: Array, default: () => [] },
    feeSummary: { type: Object, default: () => ({}) },
    filters: { type: Object, default: () => ({}) },
})

const dateRange = reactive({
    start_date: props.filters.start_date || '',
    end_date: props.filters.end_date || '',
})

const formatCurrency = (value) => new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(value || 0)
const formatPercent = (value) => `${(value || 0).toFixed(1)}%`
const formatNumber = (num) => new Intl.NumberFormat('en-US').format(num || 0)

function applyDateRange() {
    router.get(route('analytics.profit-loss.dashboard'), dateRange, { preserveState: true, preserveScroll: true })
}

const s = props.summary
</script>

<template>
    <Head title="Profit & Loss" />
    <AppLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200">Profit & Loss</h2>
                <div class="flex items-center gap-3">
                    <input v-model="dateRange.start_date" type="date" class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-sm" />
                    <span class="text-gray-500 dark:text-gray-400">to</span>
                    <input v-model="dateRange.end_date" type="date" class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-sm" />
                    <button @click="applyDateRange" class="px-3 py-2 text-sm font-medium text-white bg-indigo-600 rounded-md hover:bg-indigo-500">Apply</button>
                </div>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
                <!-- Summary Cards -->
                <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-7 gap-4">
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
                        <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Revenue</p>
                        <p class="text-xl font-bold text-gray-900 dark:text-white mt-1">{{ formatCurrency(s.total_revenue) }}</p>
                    </div>
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
                        <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">COGS</p>
                        <p class="text-xl font-bold text-red-600 dark:text-red-400 mt-1">{{ formatCurrency(s.total_cogs) }}</p>
                    </div>
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
                        <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Gross Profit</p>
                        <p class="text-xl font-bold text-emerald-600 dark:text-emerald-400 mt-1">{{ formatCurrency(s.gross_profit) }}</p>
                    </div>
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
                        <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Marketplace Fees</p>
                        <p class="text-xl font-bold text-amber-600 dark:text-amber-400 mt-1">{{ formatCurrency(s.total_fees) }}</p>
                    </div>
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
                        <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Shipping Cost</p>
                        <p class="text-xl font-bold text-gray-600 dark:text-gray-300 mt-1">{{ formatCurrency(s.total_shipping_cost) }}</p>
                    </div>
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4 border-l-4 border-indigo-500">
                        <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Net Profit</p>
                        <p class="text-xl font-bold mt-1" :class="s.total_net_profit >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400'">
                            {{ formatCurrency(s.total_net_profit) }}
                        </p>
                    </div>
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
                        <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Avg Margin</p>
                        <p class="text-xl font-bold mt-1" :class="s.avg_margin >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400'">
                            {{ formatPercent(s.avg_margin) }}
                        </p>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- By Marketplace -->
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden">
                        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-white">By Marketplace</h3>
                        </div>
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Platform</th>
                                    <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Orders</th>
                                    <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Revenue</th>
                                    <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Profit</th>
                                    <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Margin</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                <tr v-for="mp in byMarketplace" :key="mp.marketplace">
                                    <td class="px-4 py-3 text-sm font-medium text-gray-900 dark:text-white capitalize">{{ mp.marketplace }}</td>
                                    <td class="px-4 py-3 text-sm text-right text-gray-500 dark:text-gray-400">{{ mp.order_count }}</td>
                                    <td class="px-4 py-3 text-sm text-right text-gray-900 dark:text-white">{{ formatCurrency(mp.total_revenue) }}</td>
                                    <td class="px-4 py-3 text-sm text-right" :class="mp.total_net_profit >= 0 ? 'text-emerald-600' : 'text-red-600'">{{ formatCurrency(mp.total_net_profit) }}</td>
                                    <td class="px-4 py-3 text-sm text-right text-gray-500 dark:text-gray-400">{{ formatPercent(mp.avg_margin) }}</td>
                                </tr>
                                <tr v-if="!byMarketplace.length">
                                    <td colspan="5" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">No data for this period</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Fee Breakdown -->
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden">
                        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-white">Fee Breakdown</h3>
                            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Total: {{ formatCurrency(feeSummary.total_fees) }}</p>
                        </div>
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Fee Type</th>
                                    <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Count</th>
                                    <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Total</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                <tr v-for="fee in feeSummary.by_type" :key="fee.fee_type">
                                    <td class="px-4 py-3 text-sm text-gray-900 dark:text-white capitalize">{{ fee.fee_type?.replace(/_/g, ' ') }}</td>
                                    <td class="px-4 py-3 text-sm text-right text-gray-500 dark:text-gray-400">{{ fee.fee_count }}</td>
                                    <td class="px-4 py-3 text-sm text-right text-amber-600 dark:text-amber-400 font-medium">{{ formatCurrency(fee.total_fees) }}</td>
                                </tr>
                                <tr v-if="!feeSummary.by_type?.length">
                                    <td colspan="3" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">No fees recorded</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Top Products by Profit -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white">Top Products by Profit</h3>
                    </div>
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Product</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">SKU</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Units</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Revenue</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">COGS</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Gross Profit</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Margin</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            <tr v-for="(product, idx) in topProducts" :key="product.product_id">
                                <td class="px-6 py-3 text-sm text-gray-900 dark:text-white">
                                    <span v-if="idx < 3" class="inline-flex items-center justify-center w-5 h-5 rounded-full mr-2 text-xs font-bold" :class="{ 'bg-yellow-100 text-yellow-800': idx === 0, 'bg-gray-100 text-gray-600': idx === 1, 'bg-amber-100 text-amber-700': idx === 2 }">{{ idx + 1 }}</span>
                                    {{ product.product_name }}
                                </td>
                                <td class="px-6 py-3 text-sm text-gray-500 dark:text-gray-400">{{ product.sku || '-' }}</td>
                                <td class="px-6 py-3 text-sm text-right text-gray-900 dark:text-white">{{ formatNumber(product.units_sold) }}</td>
                                <td class="px-6 py-3 text-sm text-right text-gray-900 dark:text-white">{{ formatCurrency(product.total_revenue) }}</td>
                                <td class="px-6 py-3 text-sm text-right text-red-600 dark:text-red-400">{{ formatCurrency(product.total_cogs) }}</td>
                                <td class="px-6 py-3 text-sm text-right font-medium" :class="product.gross_profit >= 0 ? 'text-emerald-600' : 'text-red-600'">{{ formatCurrency(product.gross_profit) }}</td>
                                <td class="px-6 py-3 text-sm text-right text-gray-500 dark:text-gray-400">{{ formatPercent(product.margin) }}</td>
                            </tr>
                            <tr v-if="!topProducts.length">
                                <td colspan="7" class="px-6 py-12 text-center text-gray-500 dark:text-gray-400">No product data for this period</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Daily Trend -->
                <div v-if="dailyTrend.length" class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Daily Trend</h3>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead>
                                <tr>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                                    <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Orders</th>
                                    <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Revenue</th>
                                    <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Net Profit</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                <tr v-for="day in dailyTrend" :key="day.date">
                                    <td class="px-3 py-2 text-gray-700 dark:text-gray-300">{{ day.date }}</td>
                                    <td class="px-3 py-2 text-right text-gray-700 dark:text-gray-300">{{ day.order_count }}</td>
                                    <td class="px-3 py-2 text-right text-gray-900 dark:text-white">{{ formatCurrency(day.revenue) }}</td>
                                    <td class="px-3 py-2 text-right" :class="day.net_profit >= 0 ? 'text-emerald-600' : 'text-red-600'">{{ formatCurrency(day.net_profit) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
