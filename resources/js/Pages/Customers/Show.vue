<script setup>
import { Head, Link } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { route } from 'ziggy-js'

const props = defineProps({
    customer: { type: Object, required: true },
})

const c = props.customer
const formatCurrency = (value) => new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(value || 0)
const formatDate = (date) => date ? new Date(date).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) : '-'
const formatDateTime = (date) => date ? new Date(date).toLocaleString('en-US', { month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit' }) : '-'

const statusColors = {
    pending: 'bg-gray-100 text-gray-700',
    picking: 'bg-blue-100 text-blue-700',
    packed: 'bg-indigo-100 text-indigo-700',
    shipped: 'bg-emerald-100 text-emerald-700',
    delivered: 'bg-green-100 text-green-700',
    cancelled: 'bg-red-100 text-red-700',
}
</script>

<template>
    <Head :title="c.name" />
    <AppLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <Link :href="route('customers.index')" class="text-gray-500 dark:text-gray-400 hover:text-gray-700">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                    </Link>
                    <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200">{{ c.name }}</h2>
                    <span v-if="c.is_flagged" class="px-2 py-1 text-xs font-medium rounded-full bg-red-100 text-red-700">Flagged</span>
                </div>
                <Link :href="route('customers.edit', c.id)" class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50">
                    Edit
                </Link>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
                <!-- Stats -->
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
                        <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Total Orders</p>
                        <p class="text-2xl font-bold text-gray-900 dark:text-white mt-1">{{ c.total_orders }}</p>
                    </div>
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
                        <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Total Spent</p>
                        <p class="text-2xl font-bold text-gray-900 dark:text-white mt-1">{{ formatCurrency(c.total_spent) }}</p>
                    </div>
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
                        <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Avg Order</p>
                        <p class="text-2xl font-bold text-indigo-600 dark:text-indigo-400 mt-1">{{ formatCurrency(c.average_order_value) }}</p>
                    </div>
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
                        <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Customer Since</p>
                        <p class="text-lg font-bold text-gray-900 dark:text-white mt-1">{{ formatDate(c.first_order_at) }}</p>
                    </div>
                </div>

                <!-- Info -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Customer Info</h3>
                    <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                        <div>
                            <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Email</p>
                            <p class="text-sm text-gray-900 dark:text-white mt-1">{{ c.email || '-' }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Phone</p>
                            <p class="text-sm text-gray-900 dark:text-white mt-1">{{ c.phone || '-' }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Source</p>
                            <p class="text-sm text-gray-900 dark:text-white mt-1">{{ c.marketplace_source || 'Manual' }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Last Order</p>
                            <p class="text-sm text-gray-900 dark:text-white mt-1">{{ formatDate(c.last_order_at) }}</p>
                        </div>
                    </div>
                    <div v-if="c.notes" class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                        <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase mb-1">Notes</p>
                        <p class="text-sm text-gray-700 dark:text-gray-300">{{ c.notes }}</p>
                    </div>
                    <div v-if="c.tags?.length" class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                        <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase mb-2">Tags</p>
                        <div class="flex flex-wrap gap-2">
                            <span v-for="tag in c.tags" :key="tag" class="px-2 py-1 text-xs rounded-full bg-indigo-100 dark:bg-indigo-900 text-indigo-700 dark:text-indigo-300">{{ tag }}</span>
                        </div>
                    </div>
                </div>

                <!-- Addresses -->
                <div v-if="c.addresses?.length" class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Addresses</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div v-for="addr in c.addresses" :key="addr.id" class="border border-gray-200 dark:border-gray-600 rounded-lg p-4">
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ addr.type }}</span>
                                <span v-if="addr.is_default" class="text-xs text-indigo-600 dark:text-indigo-400 font-medium">Default</span>
                            </div>
                            <p class="text-sm text-gray-900 dark:text-white">{{ addr.name }}</p>
                            <p class="text-sm text-gray-600 dark:text-gray-400">{{ addr.line1 }}</p>
                            <p v-if="addr.line2" class="text-sm text-gray-600 dark:text-gray-400">{{ addr.line2 }}</p>
                            <p class="text-sm text-gray-600 dark:text-gray-400">{{ addr.city }}, {{ addr.state }} {{ addr.postal_code }}</p>
                        </div>
                    </div>
                </div>

                <!-- Order History -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white">Order History</h3>
                    </div>
                    <table v-if="c.orders?.length" class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Order</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Marketplace</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Status</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Total</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Date</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            <tr v-for="order in c.orders" :key="order.id" class="hover:bg-gray-50 dark:hover:bg-gray-750">
                                <td class="px-6 py-3 text-sm font-medium text-indigo-600 dark:text-indigo-400">
                                    <Link :href="route('fulfillment.orders.show', order.id)">{{ order.marketplace_order_id || order.id.substring(0, 8) }}</Link>
                                </td>
                                <td class="px-6 py-3 text-sm text-gray-500 dark:text-gray-400">{{ order.marketplace || '-' }}</td>
                                <td class="px-6 py-3">
                                    <span :class="['px-2 py-1 text-xs font-medium rounded-full', statusColors[order.status] || 'bg-gray-100 text-gray-700']">{{ order.status }}</span>
                                </td>
                                <td class="px-6 py-3 text-right text-sm font-medium text-gray-900 dark:text-white">{{ formatCurrency(order.total_amount) }}</td>
                                <td class="px-6 py-3 text-sm text-gray-500 dark:text-gray-400">{{ formatDate(order.created_at) }}</td>
                            </tr>
                        </tbody>
                    </table>
                    <div v-else class="px-6 py-12 text-center text-gray-500 dark:text-gray-400">No orders found.</div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
