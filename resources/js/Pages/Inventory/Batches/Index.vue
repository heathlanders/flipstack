<script setup>
import { reactive, watch } from 'vue'
import { router, Head, Link } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { route } from 'ziggy-js'

const props = defineProps({
    batches: { type: Object, required: true },
    stats: { type: Object, default: () => ({}) },
    filters: { type: Object, default: () => ({}) },
})

const currentFilters = reactive({ ...props.filters })

const formatCurrency = (value) => {
    return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(value || 0)
}

const formatDate = (date) => {
    if (!date) return '-'
    return new Date(date).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })
}

function applyFilters() {
    router.get(route('inventory.batches.index'), currentFilters, {
        preserveState: true,
        preserveScroll: true,
    })
}

let searchTimeout = null
watch(() => currentFilters.search, () => {
    clearTimeout(searchTimeout)
    searchTimeout = setTimeout(applyFilters, 300)
})
watch(() => currentFilters.status, applyFilters)
</script>

<template>
    <Head title="Inventory Batches" />

    <AppLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200">Inventory Batches</h2>
                <Link
                    :href="route('inventory.batches.create')"
                    class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-500 transition"
                >
                    New Batch
                </Link>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <!-- Stats -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
                        <div class="text-sm text-gray-500 dark:text-gray-400">Total Batches</div>
                        <div class="text-2xl font-bold text-gray-900 dark:text-white">{{ stats.total ?? 0 }}</div>
                    </div>
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
                        <div class="text-sm text-gray-500 dark:text-gray-400">Open</div>
                        <div class="text-2xl font-bold text-amber-600">{{ stats.open ?? 0 }}</div>
                    </div>
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
                        <div class="text-sm text-gray-500 dark:text-gray-400">Total Items</div>
                        <div class="text-2xl font-bold text-indigo-600">{{ stats.total_items ?? 0 }}</div>
                    </div>
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
                        <div class="text-sm text-gray-500 dark:text-gray-400">Total Cost</div>
                        <div class="text-2xl font-bold text-emerald-600">{{ formatCurrency(stats.total_acquisition_cost) }}</div>
                    </div>
                </div>

                <!-- Filters -->
                <div class="flex gap-4 mb-4">
                    <input
                        v-model="currentFilters.search"
                        type="text"
                        placeholder="Search batches..."
                        class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    />
                    <select v-model="currentFilters.status" class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">All Status</option>
                        <option value="open">Open</option>
                        <option value="closed">Closed</option>
                    </select>
                </div>

                <!-- Table -->
                <div class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Batch #</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Name</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Supplier</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Items</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Total Cost</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Per Item</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Date</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            <tr v-for="batch in batches.data" :key="batch.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                <td class="px-6 py-4 text-sm">
                                    <Link :href="route('inventory.batches.show', batch.id)" class="text-indigo-600 dark:text-indigo-400 hover:underline font-medium">
                                        {{ batch.batch_number }}
                                    </Link>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-900 dark:text-gray-100">{{ batch.name }}</td>
                                <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-400">{{ batch.supplier?.company_name || batch.supplier?.name || '-' }}</td>
                                <td class="px-6 py-4 text-sm text-right text-gray-900 dark:text-gray-100">{{ batch.item_count ?? batch.items_count ?? 0 }}</td>
                                <td class="px-6 py-4 text-sm text-right text-gray-900 dark:text-gray-100">{{ formatCurrency(batch.acquisition_cost) }}</td>
                                <td class="px-6 py-4 text-sm text-right text-gray-600 dark:text-gray-400">{{ formatCurrency(batch.cost_per_item) }}</td>
                                <td class="px-6 py-4">
                                    <span :class="batch.status === 'open' ? 'bg-amber-100 text-amber-700 dark:bg-amber-900 dark:text-amber-300' : 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300'"
                                        class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium capitalize">
                                        {{ batch.status }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">{{ formatDate(batch.acquisition_date) }}</td>
                            </tr>
                            <tr v-if="batches.data.length === 0">
                                <td colspan="8" class="px-6 py-12 text-center text-gray-500 dark:text-gray-400">
                                    No batches found. Create your first batch to start tracking inventory lots.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div v-if="batches.last_page > 1" class="mt-4 flex justify-center">
                    <nav class="flex gap-1">
                        <Link v-for="link in batches.links" :key="link.label" :href="link.url || '#'" v-html="link.label"
                            class="px-3 py-1 rounded text-sm"
                            :class="link.active ? 'bg-indigo-600 text-white' : 'bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-600'" />
                    </nav>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
