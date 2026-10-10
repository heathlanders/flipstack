<script setup>
import { reactive, watch } from 'vue'
import { router, Head, Link } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { route } from 'ziggy-js'

const props = defineProps({
    bundles: { type: Object, required: true },
    stats: { type: Object, default: () => ({}) },
    filters: { type: Object, default: () => ({}) },
})

const currentFilters = reactive({ ...props.filters })

const formatCurrency = (value) => {
    return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(value || 0)
}

let searchTimeout = null
watch(() => currentFilters.search, () => {
    clearTimeout(searchTimeout)
    searchTimeout = setTimeout(() => {
        router.get(route('inventory.bundles.index'), currentFilters, { preserveState: true, preserveScroll: true })
    }, 300)
})
</script>

<template>
    <Head title="Product Bundles" />

    <AppLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200">Product Bundles</h2>
                <Link
                    :href="route('inventory.bundles.create')"
                    class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-500 transition"
                >
                    New Bundle
                </Link>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <!-- Stats -->
                <div class="grid grid-cols-2 gap-4 mb-6">
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
                        <div class="text-sm text-gray-500 dark:text-gray-400">Total Bundles</div>
                        <div class="text-2xl font-bold text-gray-900 dark:text-white">{{ stats.total ?? 0 }}</div>
                    </div>
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
                        <div class="text-sm text-gray-500 dark:text-gray-400">Active</div>
                        <div class="text-2xl font-bold text-emerald-600">{{ stats.active ?? 0 }}</div>
                    </div>
                </div>

                <!-- Search -->
                <div class="mb-4">
                    <input v-model="currentFilters.search" type="text" placeholder="Search bundles..." class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                </div>

                <!-- Table -->
                <div class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Bundle</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Product</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Type</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Components</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Price</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Available</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            <tr v-for="bundle in bundles.data" :key="bundle.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                <td class="px-6 py-4 text-sm">
                                    <Link :href="route('inventory.bundles.show', bundle.id)" class="text-indigo-600 dark:text-indigo-400 hover:underline font-medium">
                                        {{ bundle.name }}
                                    </Link>
                                </td>
                                <td class="px-6 py-4 text-sm">
                                    <div class="text-gray-900 dark:text-gray-100">{{ bundle.product?.name || '-' }}</div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400">{{ bundle.product?.sku }}</div>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-400 capitalize">{{ bundle.bundle_type }}</td>
                                <td class="px-6 py-4 text-sm text-right text-gray-900 dark:text-gray-100">{{ bundle.items_count }}</td>
                                <td class="px-6 py-4 text-sm text-right text-gray-900 dark:text-gray-100">{{ formatCurrency(bundle.product?.selling_price) }}</td>
                                <td class="px-6 py-4 text-sm text-right text-gray-900 dark:text-gray-100">{{ bundle.product?.available_quantity ?? '-' }}</td>
                                <td class="px-6 py-4">
                                    <span :class="bundle.is_active ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900 dark:text-emerald-300' : 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300'"
                                        class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium">
                                        {{ bundle.is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                            </tr>
                            <tr v-if="bundles.data.length === 0">
                                <td colspan="7" class="px-6 py-12 text-center text-gray-500 dark:text-gray-400">
                                    No bundles found. Create a bundle to sell multiple items as one listing.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
