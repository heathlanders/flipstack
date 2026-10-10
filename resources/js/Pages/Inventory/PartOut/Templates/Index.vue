<script setup>
import { reactive, watch } from 'vue'
import { router, Head, Link } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { route } from 'ziggy-js'

const props = defineProps({
    templates: { type: Object, required: true },
    stats: { type: Object, default: () => ({}) },
    filters: { type: Object, default: () => ({}) },
})

const currentFilters = reactive({ ...props.filters })

let searchTimeout = null
watch(() => currentFilters.search, () => {
    clearTimeout(searchTimeout)
    searchTimeout = setTimeout(() => {
        router.get(route('inventory.part-out.templates.index'), currentFilters, { preserveState: true, preserveScroll: true })
    }, 300)
})
</script>

<template>
    <Head title="Part-Out Templates" />

    <AppLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200">Part-Out Templates</h2>
                <Link
                    :href="route('inventory.part-out.templates.create')"
                    class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-500 transition"
                >
                    Create Template
                </Link>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <!-- Stats -->
                <div class="grid grid-cols-2 gap-4 mb-6">
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
                        <div class="text-sm text-gray-500 dark:text-gray-400">Total Templates</div>
                        <div class="text-2xl font-bold text-gray-900 dark:text-white">{{ stats.total ?? 0 }}</div>
                    </div>
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
                        <div class="text-sm text-gray-500 dark:text-gray-400">Active Templates</div>
                        <div class="text-2xl font-bold text-emerald-600">{{ stats.active ?? 0 }}</div>
                    </div>
                </div>

                <!-- Search -->
                <div class="mb-4">
                    <input v-model="currentFilters.search" type="text" placeholder="Search templates..." class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                </div>

                <!-- Table -->
                <div class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Name</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Source Product</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Method</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Items</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Work Orders</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            <tr v-for="template in templates.data" :key="template.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                <td class="px-6 py-4 text-sm">
                                    <Link :href="route('inventory.part-out.templates.show', template.id)" class="text-indigo-600 dark:text-indigo-400 hover:underline font-medium">
                                        {{ template.name }}
                                    </Link>
                                </td>
                                <td class="px-6 py-4 text-sm">
                                    <div class="text-gray-900 dark:text-gray-100">{{ template.source_product?.name || '-' }}</div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400">{{ template.source_product?.sku }}</div>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-400">
                                    {{ { market_value: 'Market Value', percentage: 'Percentage', even: 'Even Distribution' }[template.cost_allocation_method] || template.cost_allocation_method }}
                                </td>
                                <td class="px-6 py-4 text-sm text-right text-gray-900 dark:text-gray-100">{{ template.items_count ?? 0 }}</td>
                                <td class="px-6 py-4 text-sm text-right text-gray-900 dark:text-gray-100">{{ template.work_orders_count ?? 0 }}</td>
                                <td class="px-6 py-4">
                                    <span :class="template.is_active ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900 dark:text-emerald-300' : 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300'"
                                        class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium">
                                        {{ template.is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-sm text-right">
                                    <Link :href="route('inventory.part-out.templates.show', template.id)" class="text-indigo-600 dark:text-indigo-400 hover:underline">View</Link>
                                </td>
                            </tr>
                            <tr v-if="templates.data.length === 0">
                                <td colspan="7" class="px-6 py-12 text-center text-gray-500 dark:text-gray-400">
                                    No part-out templates found. Create a template to define reusable disassembly plans.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div v-if="templates.links && templates.links.length > 3" class="mt-4 flex justify-center">
                    <nav class="flex items-center gap-1">
                        <template v-for="link in templates.links" :key="link.label">
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
