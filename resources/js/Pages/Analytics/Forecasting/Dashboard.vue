<script setup>
import { router, Head, Link } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { route } from 'ziggy-js'

const props = defineProps({
    stats: { type: Object, default: () => ({}) },
})

const formatNumber = (num) => new Intl.NumberFormat('en-US').format(num || 0)
const formatDecimal = (num) => new Intl.NumberFormat('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(num || 0)

function priorityClasses(priority) {
    const map = {
        critical: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
        high: 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200',
        medium: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
        low: 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
    }
    return map[priority] || 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200'
}

function generateForecasts() {
    router.post(route('analytics.forecasting.generate'), {}, { preserveScroll: true })
}
</script>

<template>
    <Head title="Demand Forecasting" />
    <AppLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200">Demand Forecasting</h2>
                <div class="flex items-center gap-3">
                    <Link
                        :href="route('analytics.forecasting.suggestions')"
                        class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-gray-600"
                    >
                        Reorder Suggestions
                    </Link>
                    <button
                        @click="generateForecasts"
                        class="px-4 py-2 text-sm font-medium text-white bg-indigo-600 rounded-md hover:bg-indigo-500"
                    >
                        Generate Forecasts
                    </button>
                </div>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
                <!-- KPI Cards -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-5">
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Products at Risk</p>
                        <p class="text-3xl font-bold text-red-600 dark:text-red-400 mt-2" v-text="formatNumber(stats.products_at_risk)"></p>
                        <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">Stockout within 14 days</p>
                    </div>
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-5">
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Avg Daily Velocity</p>
                        <p class="text-3xl font-bold text-gray-900 dark:text-white mt-2" v-text="formatDecimal(stats.avg_daily_velocity)"></p>
                        <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">Units per day (active products)</p>
                    </div>
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-5">
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Forecasts Generated</p>
                        <p class="text-3xl font-bold text-indigo-600 dark:text-indigo-400 mt-2" v-text="formatNumber(stats.forecasts_generated)"></p>
                        <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">Last 7 days</p>
                    </div>
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-5">
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Critical Suggestions</p>
                        <p class="text-3xl font-bold text-red-600 dark:text-red-400 mt-2" v-text="formatNumber(stats.critical_suggestions)"></p>
                        <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">Pending action</p>
                    </div>
                </div>

                <!-- At-Risk Products Table -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow">
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">At-Risk Products</h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Products projected to stock out within 14 days</p>
                    </div>
                    <div class="overflow-x-auto">
                        <table v-if="stats.at_risk_products && stats.at_risk_products.length" class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-700/50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Product</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Current Stock</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Daily Velocity</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Est. Stockout</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Days Left</th>
                                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Priority</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                <tr v-for="product in stats.at_risk_products" :key="product.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900 dark:text-white" v-text="product.name"></div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400" v-text="product.sku"></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-gray-900 dark:text-white" v-text="formatNumber(product.current_stock)"></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-gray-900 dark:text-white" v-text="formatDecimal(product.daily_velocity)"></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white" v-text="product.estimated_stockout_date"></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium" :class="product.days_left <= 3 ? 'text-red-600 dark:text-red-400' : 'text-gray-900 dark:text-white'" v-text="product.days_left"></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-center">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium" :class="priorityClasses(product.priority)" v-text="product.priority"></span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                        <div v-else class="px-6 py-12 text-center">
                            <p class="text-gray-500 dark:text-gray-400">No products are at risk of stockout. All inventory levels are healthy.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
