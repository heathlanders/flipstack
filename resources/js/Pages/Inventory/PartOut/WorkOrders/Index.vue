<script setup>
import { reactive, watch } from 'vue'
import { router, Head, Link } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { route } from 'ziggy-js'

const props = defineProps({
    workOrders: { type: Object, required: true },
    stats: { type: Object, default: () => ({}) },
    filters: { type: Object, default: () => ({}) },
})

const currentFilters = reactive({ ...props.filters })

const statusBadgeClasses = {
    draft: 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300',
    in_progress: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300',
    completed: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300',
    cancelled: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300',
}

const statusLabels = {
    draft: 'Draft',
    in_progress: 'In Progress',
    completed: 'Completed',
    cancelled: 'Cancelled',
}

const formatDate = (date) => {
    if (!date) return '-'
    return new Date(date).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })
}

let searchTimeout = null
watch(() => currentFilters.search, () => {
    clearTimeout(searchTimeout)
    searchTimeout = setTimeout(() => {
        router.get(route('inventory.part-out.work-orders.index'), currentFilters, { preserveState: true, preserveScroll: true })
    }, 300)
})

function filterByStatus(status) {
    currentFilters.status = status
    router.get(route('inventory.part-out.work-orders.index'), currentFilters, { preserveState: true, preserveScroll: true })
}
</script>

<template>
    <Head title="Part-Out Work Orders" />

    <AppLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200">Part-Out Work Orders</h2>
                <Link
                    :href="route('inventory.part-out.work-orders.create')"
                    class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-500 transition"
                >
                    New Work Order
                </Link>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <!-- Stats -->
                <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-6">
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
                        <div class="text-sm text-gray-500 dark:text-gray-400">Draft</div>
                        <div class="text-2xl font-bold text-gray-600 dark:text-gray-300">{{ stats.draft ?? 0 }}</div>
                    </div>
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
                        <div class="text-sm text-gray-500 dark:text-gray-400">In Progress</div>
                        <div class="text-2xl font-bold text-yellow-600">{{ stats.in_progress ?? 0 }}</div>
                    </div>
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
                        <div class="text-sm text-gray-500 dark:text-gray-400">Completed</div>
                        <div class="text-2xl font-bold text-green-600">{{ stats.completed ?? 0 }}</div>
                    </div>
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
                        <div class="text-sm text-gray-500 dark:text-gray-400">Cancelled</div>
                        <div class="text-2xl font-bold text-red-600">{{ stats.cancelled ?? 0 }}</div>
                    </div>
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
                        <div class="text-sm text-gray-500 dark:text-gray-400">Total Components</div>
                        <div class="text-2xl font-bold text-indigo-600">{{ stats.total_components ?? 0 }}</div>
                    </div>
                </div>

                <!-- Status Filter Buttons -->
                <div class="flex items-center gap-2 mb-4">
                    <button
                        v-for="(label, key) in { '': 'All', draft: 'Draft', in_progress: 'In Progress', completed: 'Completed', cancelled: 'Cancelled' }"
                        :key="key"
                        @click="filterByStatus(key)"
                        :class="currentFilters.status === key ? 'bg-indigo-600 text-white' : 'bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-300 border border-gray-300 dark:border-gray-600'"
                        class="px-3 py-1.5 rounded-md text-sm font-medium transition"
                    >
                        {{ label }}
                    </button>
                </div>

                <!-- Search -->
                <div class="mb-4">
                    <input v-model="currentFilters.search" type="text" placeholder="Search work orders..." class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                </div>

                <!-- Table -->
                <div class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">WO #</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Source Product</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Qty</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Items</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Template</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Created</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Completed</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            <tr v-for="wo in workOrders.data" :key="wo.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                <td class="px-6 py-4 text-sm">
                                    <Link :href="route('inventory.part-out.work-orders.show', wo.id)" class="text-indigo-600 dark:text-indigo-400 hover:underline font-medium">
                                        {{ wo.work_order_number }}
                                    </Link>
                                </td>
                                <td class="px-6 py-4 text-sm">
                                    <div class="text-gray-900 dark:text-gray-100">{{ wo.source_product?.name || '-' }}</div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400">{{ wo.source_product?.sku }}</div>
                                </td>
                                <td class="px-6 py-4 text-sm text-right text-gray-900 dark:text-gray-100">{{ wo.source_quantity ?? 1 }}</td>
                                <td class="px-6 py-4">
                                    <span :class="statusBadgeClasses[wo.status] || 'bg-gray-100 text-gray-800'"
                                        class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium">
                                        {{ statusLabels[wo.status] || wo.status }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-sm text-right text-gray-900 dark:text-gray-100">{{ wo.items_count ?? 0 }}</td>
                                <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-400">{{ wo.template?.name || '-' }}</td>
                                <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-400">{{ formatDate(wo.created_at) }}</td>
                                <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-400">{{ formatDate(wo.completed_at) }}</td>
                            </tr>
                            <tr v-if="workOrders.data.length === 0">
                                <td colspan="8" class="px-6 py-12 text-center text-gray-500 dark:text-gray-400">
                                    No work orders found. Create a work order to start disassembling products.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div v-if="workOrders.links && workOrders.links.length > 3" class="mt-4 flex justify-center">
                    <nav class="flex items-center gap-1">
                        <template v-for="link in workOrders.links" :key="link.label">
                            <Link
                                v-if="link.url"
                                :href="link.url"
                                class="px-3 py-1 text-sm rounded-md"
                                :class="link.active ? 'bg-indigo-600 text-white' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700'"
                                v-html="link.label"
                                preserve-state
                                preserve-scroll
                            />
                            <span v-else class="px-3 py-1 text-sm text-gray-400 dark:text-gray-600" v-html="link.label" />
                        </template>
                    </nav>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
