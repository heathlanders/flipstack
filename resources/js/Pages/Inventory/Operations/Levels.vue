<template>
    <AppLayout title="Stock Levels">
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Stock Levels</h2>
                <div class="flex space-x-2">
                    <Link
                        :href="route('inventory.operations.movements')"
                        class="inline-flex items-center px-3 py-2 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md text-xs font-medium text-gray-700 dark:text-gray-300 uppercase hover:bg-gray-50 dark:hover:bg-gray-600"
                    >
                        <i class="fas fa-history mr-1.5"></i>Movements
                    </Link>
                    <Link
                        :href="route('inventory.operations.adjust.form')"
                        class="inline-flex items-center px-3 py-2 bg-blue-600 text-white text-xs font-medium uppercase rounded-md hover:bg-blue-700"
                    >
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
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Product</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Location</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">On Hand</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Reserved</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Available</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Reorder Pt</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            <tr v-for="level in levels.data" :key="level.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                <td class="px-6 py-4">
                                    <div class="text-sm font-medium text-gray-900 dark:text-white">{{ level.product_name }}</div>
                                    <div class="text-xs font-mono text-gray-500 dark:text-gray-400">{{ level.sku }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300">{{ level.location_name }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white text-right">{{ level.quantity_on_hand ?? 0 }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300 text-right">{{ level.quantity_reserved ?? 0 }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-right" :class="isLow(level) ? 'text-amber-600 dark:text-amber-400' : 'text-gray-900 dark:text-white'">
                                    {{ level.quantity_available ?? 0 }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400 text-right">{{ level.reorder_point ?? '—' }}</td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span v-if="isLow(level)" class="px-2 py-0.5 text-xs font-medium rounded bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300">
                                        Low Stock
                                    </span>
                                    <span v-else-if="(level.quantity_available ?? 0) === 0" class="px-2 py-0.5 text-xs font-medium rounded bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300">
                                        Out of Stock
                                    </span>
                                    <span v-else class="px-2 py-0.5 text-xs font-medium rounded bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300">
                                        OK
                                    </span>
                                </td>
                            </tr>
                            <tr v-if="!levels.data?.length">
                                <td colspan="7" class="px-6 py-12 text-center text-sm text-gray-500 dark:text-gray-400">
                                    <i class="fas fa-warehouse text-3xl mb-2 block"></i>
                                    No stock levels recorded.
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <div v-if="levels.links?.length > 3" class="px-4 py-3 border-t border-gray-200 dark:border-gray-700 flex items-center justify-between">
                        <div class="text-sm text-gray-600 dark:text-gray-400">
                            Showing {{ levels.from || 0 }}-{{ levels.to || 0 }} of {{ levels.total }}
                        </div>
                        <div class="flex space-x-1">
                            <Link
                                v-for="(link, i) in levels.links"
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
    levels: { type: Object, required: true },
})

const isLow = (level) => {
    const avail = level.quantity_available ?? 0
    const reorder = level.reorder_point
    return reorder != null && reorder > 0 && avail > 0 && avail <= reorder
}
</script>
