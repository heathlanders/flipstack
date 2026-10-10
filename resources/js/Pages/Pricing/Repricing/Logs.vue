<script setup>
import { reactive, watch } from 'vue'
import { router, Head, Link } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { route } from 'ziggy-js'

const props = defineProps({
    logs: { type: Object, required: true },
    rules: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
})

const currentFilters = reactive({ ...props.filters })

const formatCurrency = (value) => {
    return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(value || 0)
}

function applyFilters() {
    router.get(route('pricing.repricing.logs'), currentFilters, {
        preserveState: true,
        preserveScroll: true,
    })
}

watch(() => currentFilters.rule_id, applyFilters)
</script>

<template>
    <Head title="Repricing Logs" />

    <AppLayout>
        <template #header>
            <div class="flex items-center gap-4">
                <Link :href="route('pricing.repricing.index')" class="text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200">
                    <i class="fas fa-arrow-left"></i>
                </Link>
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200">Repricing Logs</h2>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <!-- Filters -->
                <div class="mb-4">
                    <select v-model="currentFilters.rule_id" class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">All Rules</option>
                        <option v-for="rule in rules" :key="rule.id" :value="rule.id">{{ rule.name }}</option>
                    </select>
                </div>

                <!-- Logs Table -->
                <div class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Date</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Rule</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Product</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Old Price</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">New Price</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Change</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Reason</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            <tr v-for="log in logs.data" :key="log.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                    {{ log.applied_at ? new Date(log.applied_at).toLocaleString() : '-' }}
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-900 dark:text-gray-100">{{ log.rule?.name || '-' }}</td>
                                <td class="px-6 py-4 text-sm">
                                    <div class="text-gray-900 dark:text-gray-100">{{ log.product?.name || '-' }}</div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400">{{ log.product?.sku }}</div>
                                </td>
                                <td class="px-6 py-4 text-sm text-right text-gray-600 dark:text-gray-400">{{ formatCurrency(log.old_price) }}</td>
                                <td class="px-6 py-4 text-sm text-right font-medium text-gray-900 dark:text-gray-100">{{ formatCurrency(log.new_price) }}</td>
                                <td class="px-6 py-4 text-sm text-right" :class="log.new_price > log.old_price ? 'text-emerald-600' : 'text-red-600'">
                                    {{ log.new_price > log.old_price ? '+' : '' }}{{ formatCurrency(log.new_price - log.old_price) }}
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">{{ log.reason }}</td>
                            </tr>
                            <tr v-if="logs.data.length === 0">
                                <td colspan="7" class="px-6 py-12 text-center text-gray-500 dark:text-gray-400">
                                    No repricing logs found.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div v-if="logs.last_page > 1" class="mt-4 flex justify-center">
                    <nav class="flex gap-1">
                        <Link v-for="link in logs.links" :key="link.label" :href="link.url || '#'" v-html="link.label"
                            class="px-3 py-1 rounded text-sm"
                            :class="link.active ? 'bg-indigo-600 text-white' : 'bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-600'" />
                    </nav>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
