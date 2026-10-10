<template>
    <AppLayout :title="`${location.name} — Analytics`">
        <template #header>
            <div class="flex items-center space-x-4">
                <Link :href="route('inventory.locations.show', location.id)" class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">
                    <i class="fas fa-arrow-left"></i>
                </Link>
                <div>
                    <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">{{ location.name }}</h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Analytics</p>
                </div>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
                <!-- KPIs -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <div class="text-sm text-gray-500 dark:text-gray-400">Total Products</div>
                                <div class="text-3xl font-semibold text-gray-900 dark:text-white mt-1">{{ analytics.total_products || 0 }}</div>
                            </div>
                            <i class="fas fa-box text-3xl text-blue-500 opacity-50"></i>
                        </div>
                    </div>
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <div class="text-sm text-gray-500 dark:text-gray-400">Inventory Value</div>
                                <div class="text-3xl font-semibold text-gray-900 dark:text-white mt-1">{{ formatCurrency(analytics.total_value) }}</div>
                            </div>
                            <i class="fas fa-dollar-sign text-3xl text-green-500 opacity-50"></i>
                        </div>
                    </div>
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <div class="text-sm text-gray-500 dark:text-gray-400">Recent Movements</div>
                                <div class="text-3xl font-semibold text-gray-900 dark:text-white mt-1">{{ analytics.recent_movements?.length || 0 }}</div>
                            </div>
                            <i class="fas fa-exchange-alt text-3xl text-purple-500 opacity-50"></i>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- Top Products -->
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm overflow-hidden">
                        <div class="p-6 pb-3">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Top Products by Quantity</h3>
                        </div>
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-700/50">
                                <tr>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Product</th>
                                    <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Qty</th>
                                    <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Value</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                <tr v-for="row in analytics.top_products" :key="row.product?.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                    <td class="px-4 py-3">
                                        <Link v-if="row.product" :href="route('inventory.products.show', row.product.id)" class="text-sm font-medium text-blue-600 dark:text-blue-400 hover:underline">
                                            {{ row.product.name }}
                                        </Link>
                                        <div v-if="row.product" class="text-xs font-mono text-gray-500 dark:text-gray-400">{{ row.product.sku }}</div>
                                    </td>
                                    <td class="px-4 py-3 text-right text-sm text-gray-900 dark:text-white">{{ row.quantity }}</td>
                                    <td class="px-4 py-3 text-right text-sm text-gray-900 dark:text-white">{{ formatCurrency(row.value) }}</td>
                                </tr>
                                <tr v-if="!analytics.top_products?.length">
                                    <td colspan="3" class="px-4 py-8 text-center text-sm text-gray-500 dark:text-gray-400">No products at this location.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Recent Movements -->
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm overflow-hidden">
                        <div class="p-6 pb-3">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Recent Movements</h3>
                        </div>
                        <div class="divide-y divide-gray-200 dark:divide-gray-700 max-h-96 overflow-y-auto">
                            <div v-for="mv in analytics.recent_movements" :key="mv.id" class="px-6 py-3 hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                <div class="flex items-center justify-between">
                                    <div class="min-w-0 flex-1">
                                        <Link v-if="mv.product" :href="route('inventory.products.show', mv.product.id)" class="text-sm font-medium text-blue-600 dark:text-blue-400 hover:underline truncate">
                                            {{ mv.product.name }}
                                        </Link>
                                        <div class="text-xs text-gray-500 dark:text-gray-400 capitalize">{{ mv.type?.replace('_', ' ') }}</div>
                                    </div>
                                    <div class="ml-3 text-right">
                                        <div class="text-sm font-semibold" :class="mv.quantity < 0 ? 'text-red-600 dark:text-red-400' : 'text-green-600 dark:text-green-400'">
                                            {{ mv.quantity > 0 ? '+' : '' }}{{ mv.quantity }}
                                        </div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400">{{ formatRelative(mv.created_at) }}</div>
                                    </div>
                                </div>
                            </div>
                            <div v-if="!analytics.recent_movements?.length" class="px-6 py-8 text-center text-sm text-gray-500 dark:text-gray-400">No recent movements.</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { Link } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

defineProps({
    location: { type: Object, required: true },
    analytics: { type: Object, default: () => ({}) },
})

const formatCurrency = (val) => {
    if (val == null) return '$0.00'
    return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(val)
}

const formatRelative = (date) => {
    if (!date) return ''
    const diff = Date.now() - new Date(date).getTime()
    const mins = Math.floor(diff / 60000)
    if (mins < 1) return 'just now'
    if (mins < 60) return `${mins}m ago`
    const hrs = Math.floor(mins / 60)
    if (hrs < 24) return `${hrs}h ago`
    const days = Math.floor(hrs / 24)
    return `${days}d ago`
}
</script>
