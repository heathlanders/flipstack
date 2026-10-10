<script setup>
import { ref, reactive, watch } from 'vue'
import { router, Head, Link } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { route } from 'ziggy-js'

const props = defineProps({
    customers: { type: Object, required: true },
    stats: { type: Object, default: () => ({}) },
    filters: { type: Object, default: () => ({}) },
})

const currentFilters = reactive({ ...props.filters })

const formatCurrency = (value) => new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(value || 0)
const formatDate = (date) => date ? new Date(date).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) : '-'

function applyFilters() {
    router.get(route('customers.index'), currentFilters, { preserveState: true, preserveScroll: true })
}

function clearFilters() {
    Object.assign(currentFilters, { search: '', marketplace: '', flagged: '', repeat: '' })
    applyFilters()
}

let searchTimeout = null
watch(() => currentFilters.search, () => {
    clearTimeout(searchTimeout)
    searchTimeout = setTimeout(applyFilters, 300)
})
watch(() => currentFilters.marketplace, applyFilters)
watch(() => currentFilters.repeat, applyFilters)
</script>

<template>
    <Head title="Customers" />
    <AppLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200">Customers</h2>
        </template>

        <div class="py-6">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <!-- Stats -->
                <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-6">
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
                        <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Total Customers</p>
                        <p class="text-2xl font-bold text-gray-900 dark:text-white mt-1">{{ stats.total || 0 }}</p>
                    </div>
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
                        <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Repeat Buyers</p>
                        <p class="text-2xl font-bold text-emerald-600 dark:text-emerald-400 mt-1">{{ stats.repeat_buyers || 0 }}</p>
                    </div>
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
                        <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Total Revenue</p>
                        <p class="text-2xl font-bold text-gray-900 dark:text-white mt-1">{{ formatCurrency(stats.total_revenue) }}</p>
                    </div>
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
                        <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Avg LTV</p>
                        <p class="text-2xl font-bold text-indigo-600 dark:text-indigo-400 mt-1">{{ formatCurrency(stats.avg_ltv) }}</p>
                    </div>
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
                        <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Flagged</p>
                        <p class="text-2xl font-bold text-red-600 dark:text-red-400 mt-1">{{ stats.flagged || 0 }}</p>
                    </div>
                </div>

                <!-- Filters -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow mb-6 p-4">
                    <div class="flex flex-wrap gap-4">
                        <div class="flex-1 min-w-[200px]">
                            <input v-model="currentFilters.search" type="text" placeholder="Search name, email, or phone..." class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-sm" />
                        </div>
                        <select v-model="currentFilters.marketplace" @change="applyFilters" class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-sm">
                            <option value="">All Marketplaces</option>
                            <option value="ebay">eBay</option>
                            <option value="shopify">Shopify</option>
                            <option value="amazon">Amazon</option>
                        </select>
                        <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                            <input v-model="currentFilters.repeat" type="checkbox" true-value="1" false-value="" @change="applyFilters" class="rounded border-gray-300 dark:border-gray-600" />
                            Repeat Buyers
                        </label>
                        <button @click="clearFilters" class="text-sm text-gray-500 hover:text-gray-700">Clear</button>
                    </div>
                </div>

                <!-- Table -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Customer</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Source</th>
                                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Orders</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Total Spent</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Avg Order</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Last Order</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            <tr
                                v-for="customer in customers.data"
                                :key="customer.id"
                                class="hover:bg-gray-50 dark:hover:bg-gray-750 cursor-pointer"
                                @click="router.visit(route('customers.show', customer.id))"
                            >
                                <td class="px-6 py-4">
                                    <div>
                                        <p class="text-sm font-medium text-gray-900 dark:text-white">
                                            {{ customer.name }}
                                            <span v-if="customer.is_flagged" class="ml-1 text-xs text-red-500">[Flagged]</span>
                                        </p>
                                        <p v-if="customer.email" class="text-xs text-gray-500 dark:text-gray-400">{{ customer.email }}</p>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <span v-if="customer.marketplace_source" class="px-2 py-1 text-xs font-medium rounded-full bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300">
                                        {{ customer.marketplace_source }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-center text-sm text-gray-900 dark:text-white">{{ customer.total_orders }}</td>
                                <td class="px-6 py-4 text-right text-sm font-medium text-gray-900 dark:text-white">{{ formatCurrency(customer.total_spent) }}</td>
                                <td class="px-6 py-4 text-right text-sm text-gray-500 dark:text-gray-400">{{ formatCurrency(customer.average_order_value) }}</td>
                                <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">{{ formatDate(customer.last_order_at) }}</td>
                            </tr>
                            <tr v-if="!customers.data?.length">
                                <td colspan="6" class="px-6 py-12 text-center text-gray-500 dark:text-gray-400">
                                    No customers found. Customers are created automatically when orders are imported.
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <div v-if="customers.links?.length > 3" class="px-6 py-3 bg-gray-50 dark:bg-gray-700 border-t border-gray-200 dark:border-gray-600 flex items-center justify-between">
                        <p class="text-sm text-gray-500 dark:text-gray-400">Showing {{ customers.from }} to {{ customers.to }} of {{ customers.total }}</p>
                        <div class="flex gap-1">
                            <template v-for="link in customers.links" :key="link.label">
                                <Link v-if="link.url" :href="link.url" class="px-3 py-1 text-sm rounded" :class="link.active ? 'bg-indigo-600 text-white' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-100'" v-html="link.label" preserve-scroll />
                                <span v-else class="px-3 py-1 text-sm text-gray-400" v-html="link.label" />
                            </template>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
