<script setup>
import { reactive } from 'vue'
import { router, Head, Link } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { route } from 'ziggy-js'

const props = defineProps({
    suggestions: { type: Object, default: () => ({}) },
    filters: { type: Object, default: () => ({}) },
})

const currentFilters = reactive({
    status: props.filters.status || 'all',
    priority: props.filters.priority || '',
})

const formatNumber = (num) => new Intl.NumberFormat('en-US').format(num || 0)

function applyFilters() {
    router.get(route('analytics.forecasting.suggestions'), {
        status: currentFilters.status,
        priority: currentFilters.priority || undefined,
    }, { preserveState: true, preserveScroll: true })
}

function setStatus(status) {
    currentFilters.status = status
    applyFilters()
}

function acceptSuggestion(id) {
    router.post(route('analytics.forecasting.accept', { suggestion: id }), {}, { preserveScroll: true })
}

function dismissSuggestion(id) {
    router.post(route('analytics.forecasting.dismiss', { suggestion: id }), {}, { preserveScroll: true })
}

function priorityClasses(priority) {
    const map = {
        critical: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
        high: 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200',
        medium: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
        low: 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
    }
    return map[priority] || 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200'
}

function statusClasses(status) {
    const map = {
        pending: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
        accepted: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
        dismissed: 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300',
    }
    return map[status] || 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200'
}

function reasonLabel(reason) {
    const map = {
        low_stock_forecast: 'Low Stock Forecast',
        seasonal_trend: 'Seasonal Trend',
        velocity_increase: 'Velocity Increase',
    }
    return map[reason] || reason
}

const statusTabs = [
    { key: 'all', label: 'All' },
    { key: 'pending', label: 'Pending' },
    { key: 'accepted', label: 'Accepted' },
    { key: 'dismissed', label: 'Dismissed' },
]

const priorityOptions = [
    { value: '', label: 'All Priorities' },
    { value: 'critical', label: 'Critical' },
    { value: 'high', label: 'High' },
    { value: 'medium', label: 'Medium' },
    { value: 'low', label: 'Low' },
]
</script>

<template>
    <Head title="Reorder Suggestions" />
    <AppLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200">Reorder Suggestions</h2>
                <Link
                    :href="route('analytics.forecasting.dashboard')"
                    class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-gray-600"
                >
                    Back to Dashboard
                </Link>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
                <!-- Filters -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow px-6 py-4">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                        <!-- Status Tabs -->
                        <div class="flex gap-1 bg-gray-100 dark:bg-gray-700 rounded-lg p-1">
                            <button
                                v-for="tab in statusTabs"
                                :key="tab.key"
                                @click="setStatus(tab.key)"
                                class="px-3 py-1.5 text-sm font-medium rounded-md transition-colors"
                                :class="currentFilters.status === tab.key
                                    ? 'bg-white dark:bg-gray-600 text-gray-900 dark:text-white shadow-sm'
                                    : 'text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300'"
                                v-text="tab.label"
                            ></button>
                        </div>
                        <!-- Priority Filter -->
                        <select
                            v-model="currentFilters.priority"
                            @change="applyFilters"
                            class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-sm"
                        >
                            <option v-for="opt in priorityOptions" :key="opt.value" :value="opt.value" v-text="opt.label"></option>
                        </select>
                    </div>
                </div>

                <!-- Suggestions Table -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden">
                    <div class="overflow-x-auto">
                        <table v-if="suggestions.data && suggestions.data.length" class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-700/50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Product</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Supplier</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Suggested Qty</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Reason</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Stockout Date</th>
                                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Priority</th>
                                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                <tr v-for="s in suggestions.data" :key="s.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900 dark:text-white" v-text="s.product?.name || '--'"></div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400" v-text="s.product?.sku || ''"></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white" v-text="s.supplier?.name || '--'"></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium text-gray-900 dark:text-white" v-text="formatNumber(s.suggested_quantity)"></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white" v-text="reasonLabel(s.reason)"></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white" v-text="s.estimated_stockout_date || '--'"></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-center">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium" :class="priorityClasses(s.priority)" v-text="s.priority"></span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-center">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium" :class="statusClasses(s.status)" v-text="s.status"></span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm">
                                        <div v-if="s.status === 'pending'" class="flex items-center justify-end gap-2">
                                            <button
                                                @click="acceptSuggestion(s.id)"
                                                class="text-green-600 dark:text-green-400 hover:text-green-800 dark:hover:text-green-300 font-medium"
                                            >
                                                Accept
                                            </button>
                                            <button
                                                @click="dismissSuggestion(s.id)"
                                                class="text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 font-medium"
                                            >
                                                Dismiss
                                            </button>
                                        </div>
                                        <span v-else class="text-gray-400 dark:text-gray-500">--</span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                        <div v-else class="px-6 py-12 text-center">
                            <p class="text-gray-500 dark:text-gray-400">No reorder suggestions found matching your filters.</p>
                        </div>
                    </div>

                    <!-- Pagination -->
                    <div v-if="suggestions.links && suggestions.links.length > 3" class="px-6 py-3 border-t border-gray-200 dark:border-gray-700 flex items-center justify-between">
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            Showing {{ suggestions.from }} to {{ suggestions.to }} of {{ suggestions.total }} results
                        </p>
                        <div class="flex gap-1">
                            <template v-for="link in suggestions.links" :key="link.label">
                                <Link
                                    v-if="link.url"
                                    :href="link.url"
                                    class="px-3 py-1 text-sm rounded-md"
                                    :class="link.active
                                        ? 'bg-indigo-600 text-white'
                                        : 'text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700'"
                                    v-html="link.label"
                                    preserve-scroll
                                />
                                <span
                                    v-else
                                    class="px-3 py-1 text-sm text-gray-400 dark:text-gray-500"
                                    v-html="link.label"
                                />
                            </template>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
