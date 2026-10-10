<template>
    <AppLayout title="Stock Movements">
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Stock Movements</h2>
                <div class="flex space-x-2">
                    <Link :href="route('inventory.operations.levels')" class="inline-flex items-center px-3 py-2 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md text-xs font-medium text-gray-700 dark:text-gray-300 uppercase hover:bg-gray-50 dark:hover:bg-gray-600">
                        <i class="fas fa-warehouse mr-1.5"></i>Stock Levels
                    </Link>
                    <Link :href="route('inventory.operations.adjust.form')" class="inline-flex items-center px-3 py-2 bg-blue-600 text-white text-xs font-medium uppercase rounded-md hover:bg-blue-700">
                        <i class="fas fa-sliders-h mr-1.5"></i>Adjust Stock
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm overflow-hidden">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-700/50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">When</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Product</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Location</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Type</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Qty</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">By</th>
                                <th class="px-6 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            <tr v-for="m in movements.data" :key="m.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                <td class="px-6 py-3 whitespace-nowrap text-sm">
                                    <div class="text-gray-900 dark:text-white">{{ formatDate(m.created_at) }}</div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400">{{ formatTime(m.created_at) }}</div>
                                </td>
                                <td class="px-6 py-3">
                                    <div class="text-sm text-gray-900 dark:text-white">{{ m.product_name }}</div>
                                    <div class="text-xs font-mono text-gray-500 dark:text-gray-400">{{ m.sku }}</div>
                                </td>
                                <td class="px-6 py-3 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300">{{ m.location_name }}</td>
                                <td class="px-6 py-3 whitespace-nowrap">
                                    <span class="px-2 py-0.5 text-xs font-medium rounded bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300 capitalize">
                                        {{ (m.type || m.movement_type || '—').replace('_', ' ') }}
                                    </span>
                                </td>
                                <td class="px-6 py-3 whitespace-nowrap text-sm text-right font-semibold" :class="(m.quantity || 0) < 0 ? 'text-red-600 dark:text-red-400' : 'text-green-600 dark:text-green-400'">
                                    {{ (m.quantity || 0) > 0 ? '+' : '' }}{{ m.quantity }}
                                </td>
                                <td class="px-6 py-3 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300">{{ m.created_by_name || '—' }}</td>
                                <td class="px-6 py-3 whitespace-nowrap text-right">
                                    <Link :href="route('inventory.movements.show', m.id)" class="text-gray-500 hover:text-blue-600">
                                        <i class="fas fa-eye"></i>
                                    </Link>
                                </td>
                            </tr>
                            <tr v-if="!movements.data?.length">
                                <td colspan="7" class="px-6 py-12 text-center text-sm text-gray-500 dark:text-gray-400">
                                    <i class="fas fa-history text-3xl mb-2 block"></i>
                                    No movements recorded.
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <div v-if="movements.links?.length > 3" class="px-4 py-3 border-t border-gray-200 dark:border-gray-700 flex items-center justify-between">
                        <div class="text-sm text-gray-600 dark:text-gray-400">
                            Showing {{ movements.from || 0 }}-{{ movements.to || 0 }} of {{ movements.total }}
                        </div>
                        <div class="flex space-x-1">
                            <Link
                                v-for="(link, i) in movements.links"
                                :key="i"
                                :href="link.url || '#'"
                                v-html="link.label"
                                class="px-3 py-1 text-sm rounded"
                                :class="[
                                    link.active ? 'bg-blue-600 text-white' : 'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700',
                                    !link.url ? 'opacity-50 pointer-events-none' : ''
                                ]"
                                preserve-scroll
                            />
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
    movements: { type: Object, required: true },
})

const formatDate = (date) => {
    if (!date) return '—'
    return new Date(date).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })
}
const formatTime = (date) => {
    if (!date) return ''
    return new Date(date).toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' })
}
</script>
