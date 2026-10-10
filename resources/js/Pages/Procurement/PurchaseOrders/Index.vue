<script setup>
import { ref, reactive, computed, watch } from 'vue'
import { router, Head, Link } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { route } from 'ziggy-js'

const props = defineProps({
    purchaseOrders: { type: Object, required: true },
    stats: { type: Object, default: () => ({}) },
    suppliers: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
})

const currentFilters = reactive({ ...props.filters })
const isLoading = ref(false)

const formatCurrency = (value) => {
    return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(value || 0)
}

const formatDate = (date) => {
    if (!date) return '-'
    return new Date(date).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })
}

const statusColors = {
    draft: 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300',
    submitted: 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300',
    partially_received: 'bg-amber-100 text-amber-700 dark:bg-amber-900 dark:text-amber-300',
    received: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900 dark:text-emerald-300',
    cancelled: 'bg-red-100 text-red-700 dark:bg-red-900 dark:text-red-300',
}

const statusLabels = {
    draft: 'Draft',
    submitted: 'Submitted',
    partially_received: 'Partially Received',
    received: 'Received',
    cancelled: 'Cancelled',
}

function applyFilters() {
    isLoading.value = true
    router.get(route('procurement.purchase-orders.index'), currentFilters, {
        preserveState: true,
        preserveScroll: true,
        onFinish: () => { isLoading.value = false },
    })
}

function clearFilters() {
    Object.assign(currentFilters, { search: '', status: '', supplier_id: '' })
    applyFilters()
}

let searchTimeout = null
watch(() => currentFilters.search, () => {
    clearTimeout(searchTimeout)
    searchTimeout = setTimeout(applyFilters, 300)
})

watch(() => currentFilters.status, applyFilters)
watch(() => currentFilters.supplier_id, applyFilters)
</script>

<template>
    <Head title="Purchase Orders" />

    <AppLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200">Purchase Orders</h2>
                <Link
                    :href="route('procurement.purchase-orders.create')"
                    class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-500 transition"
                >
                    New Purchase Order
                </Link>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <!-- Stats Cards -->
                <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-7 gap-4 mb-6">
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
                        <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Total</p>
                        <p class="text-2xl font-bold text-gray-900 dark:text-white mt-1">{{ stats.total || 0 }}</p>
                    </div>
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
                        <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Draft</p>
                        <p class="text-2xl font-bold text-gray-600 dark:text-gray-300 mt-1">{{ stats.draft || 0 }}</p>
                    </div>
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
                        <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Submitted</p>
                        <p class="text-2xl font-bold text-blue-600 dark:text-blue-400 mt-1">{{ stats.submitted || 0 }}</p>
                    </div>
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
                        <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Partial</p>
                        <p class="text-2xl font-bold text-amber-600 dark:text-amber-400 mt-1">{{ stats.partially_received || 0 }}</p>
                    </div>
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
                        <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Received</p>
                        <p class="text-2xl font-bold text-emerald-600 dark:text-emerald-400 mt-1">{{ stats.received || 0 }}</p>
                    </div>
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
                        <p class="text-xs font-medium text-red-500 dark:text-red-400 uppercase">Overdue</p>
                        <p class="text-2xl font-bold text-red-600 dark:text-red-400 mt-1">{{ stats.overdue || 0 }}</p>
                    </div>
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
                        <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Open Value</p>
                        <p class="text-lg font-bold text-gray-900 dark:text-white mt-1">{{ formatCurrency(stats.total_value_open) }}</p>
                    </div>
                </div>

                <!-- Filters -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow mb-6 p-4">
                    <div class="flex flex-wrap gap-4">
                        <div class="flex-1 min-w-[200px]">
                            <input
                                v-model="currentFilters.search"
                                type="text"
                                placeholder="Search PO number or supplier..."
                                class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                            />
                        </div>
                        <select
                            v-model="currentFilters.status"
                            class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                        >
                            <option value="">All Statuses</option>
                            <option v-for="(label, key) in statusLabels" :key="key" :value="key">{{ label }}</option>
                        </select>
                        <select
                            v-model="currentFilters.supplier_id"
                            class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                        >
                            <option value="">All Suppliers</option>
                            <option v-for="supplier in suppliers" :key="supplier.id" :value="supplier.id">
                                {{ supplier.company_name || supplier.name }}
                            </option>
                        </select>
                        <button
                            @click="clearFilters"
                            class="text-sm text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200"
                        >
                            Clear
                        </button>
                    </div>
                </div>

                <!-- Table -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">PO Number</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Supplier</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Order Date</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Expected</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Total</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            <tr
                                v-for="po in purchaseOrders.data"
                                :key="po.id"
                                class="hover:bg-gray-50 dark:hover:bg-gray-750 cursor-pointer"
                                @click="router.visit(route('procurement.purchase-orders.show', po.id))"
                            >
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-indigo-600 dark:text-indigo-400">
                                    {{ po.po_number }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                                    {{ po.supplier?.company_name || po.supplier?.name || '-' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span :class="['px-2 py-1 text-xs font-medium rounded-full', statusColors[po.status] || '']">
                                        {{ statusLabels[po.status] || po.status }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                    {{ formatDate(po.order_date) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                    {{ formatDate(po.expected_delivery_date) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right font-medium text-gray-900 dark:text-gray-100">
                                    {{ formatCurrency(po.total_amount) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm" @click.stop>
                                    <Link
                                        :href="route('procurement.purchase-orders.show', po.id)"
                                        class="text-indigo-600 dark:text-indigo-400 hover:text-indigo-900 mr-3"
                                    >
                                        View
                                    </Link>
                                    <Link
                                        v-if="po.status === 'draft'"
                                        :href="route('procurement.purchase-orders.edit', po.id)"
                                        class="text-gray-600 dark:text-gray-400 hover:text-gray-900"
                                    >
                                        Edit
                                    </Link>
                                </td>
                            </tr>
                            <tr v-if="!purchaseOrders.data?.length">
                                <td colspan="7" class="px-6 py-12 text-center text-gray-500 dark:text-gray-400">
                                    No purchase orders found.
                                    <Link :href="route('procurement.purchase-orders.create')" class="text-indigo-600 dark:text-indigo-400 hover:underline ml-1">
                                        Create one
                                    </Link>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <!-- Pagination -->
                    <div v-if="purchaseOrders.links?.length > 3" class="px-6 py-3 bg-gray-50 dark:bg-gray-700 border-t border-gray-200 dark:border-gray-600 flex items-center justify-between">
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            Showing {{ purchaseOrders.from }} to {{ purchaseOrders.to }} of {{ purchaseOrders.total }}
                        </p>
                        <div class="flex gap-1">
                            <template v-for="link in purchaseOrders.links" :key="link.label">
                                <Link
                                    v-if="link.url"
                                    :href="link.url"
                                    class="px-3 py-1 text-sm rounded"
                                    :class="link.active ? 'bg-indigo-600 text-white' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-600'"
                                    v-html="link.label"
                                    preserve-scroll
                                />
                                <span v-else class="px-3 py-1 text-sm text-gray-400 dark:text-gray-500" v-html="link.label" />
                            </template>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
