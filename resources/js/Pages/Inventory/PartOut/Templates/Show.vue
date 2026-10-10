<script setup>
import { router, Head, Link } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { route } from 'ziggy-js'

const props = defineProps({
    template: { type: Object, required: true },
    recentWorkOrders: { type: Array, default: () => [] },
})

const allocationMethodLabels = {
    market_value: 'Market Value',
    percentage: 'Percentage',
    even: 'Even Distribution',
}

const statusBadgeClasses = {
    draft: 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300',
    in_progress: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300',
    completed: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300',
    cancelled: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300',
}

const statusLabels = {
    draft: 'Draft',
    in_progress: 'In Progress',
    completed: 'Completed',
    cancelled: 'Cancelled',
}

const formatCurrency = (value) => {
    return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(value || 0)
}

const formatDate = (date) => {
    if (!date) return '-'
    return new Date(date).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })
}

function duplicateTemplate() {
    router.post(route('inventory.part-out.templates.duplicate', props.template.id))
}

function deleteTemplate() {
    if (confirm('Delete this part-out template? This will not affect existing work orders.')) {
        router.delete(route('inventory.part-out.templates.destroy', props.template.id))
    }
}
</script>

<template>
    <Head :title="`Template: ${template.name}`" />

    <AppLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <Link :href="route('inventory.part-out.templates.index')" class="text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200">
                        <i class="fas fa-arrow-left"></i>
                    </Link>
                    <div>
                        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200">{{ template.name }}</h2>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Part-Out Template</p>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <Link :href="route('inventory.part-out.templates.edit', template.id)" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-500 transition">
                        Edit
                    </Link>
                    <button @click="duplicateTemplate" class="inline-flex items-center px-4 py-2 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md font-semibold text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest hover:bg-gray-50 dark:hover:bg-gray-600 transition">
                        Duplicate
                    </button>
                    <Link :href="route('inventory.part-out.work-orders.create', { template_id: template.id })" class="inline-flex items-center px-4 py-2 bg-emerald-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-emerald-500 transition">
                        Create Work Order
                    </Link>
                    <button @click="deleteTemplate" class="inline-flex items-center px-4 py-2 bg-red-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-red-500 transition">
                        Delete
                    </button>
                </div>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <!-- Template Info -->
                <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6 mb-6">
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                        <div>
                            <div class="text-sm text-gray-500 dark:text-gray-400">Source Product</div>
                            <div class="font-medium text-gray-900 dark:text-gray-100">{{ template.source_product?.name || 'None specified' }}</div>
                            <div v-if="template.source_product?.sku" class="text-xs text-gray-500 dark:text-gray-400">{{ template.source_product.sku }}</div>
                        </div>
                        <div>
                            <div class="text-sm text-gray-500 dark:text-gray-400">Allocation Method</div>
                            <div class="font-medium text-gray-900 dark:text-gray-100">{{ allocationMethodLabels[template.cost_allocation_method] || template.cost_allocation_method }}</div>
                        </div>
                        <div>
                            <div class="text-sm text-gray-500 dark:text-gray-400">Status</div>
                            <span :class="template.is_active ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900 dark:text-emerald-300' : 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300'"
                                class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium mt-1">
                                {{ template.is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </div>
                        <div>
                            <div class="text-sm text-gray-500 dark:text-gray-400">Created By</div>
                            <div class="font-medium text-gray-900 dark:text-gray-100">{{ template.creator?.name || '-' }}</div>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mt-4 pt-4 border-t dark:border-gray-700">
                        <div>
                            <div class="text-sm text-gray-500 dark:text-gray-400">Times Used</div>
                            <div class="text-xl font-bold text-gray-900 dark:text-white">{{ template.times_used ?? 0 }}</div>
                        </div>
                    </div>
                    <div v-if="template.description" class="mt-4 pt-4 border-t dark:border-gray-700 text-sm text-gray-600 dark:text-gray-400">
                        {{ template.description }}
                    </div>
                </div>

                <!-- Items Table -->
                <div class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden mb-6">
                    <div class="px-6 py-4 border-b dark:border-gray-700">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Template Items ({{ template.items?.length ?? 0 }})</h3>
                    </div>
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Name</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Part #</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Manufacturer</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Category</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Qty</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Cost %</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Condition</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Suggested Price</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            <tr v-for="item in template.items" :key="item.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                <td class="px-6 py-4 text-sm text-gray-900 dark:text-gray-100">{{ item.name }}</td>
                                <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-400">{{ item.part_number || '-' }}</td>
                                <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-400">{{ item.manufacturer || '-' }}</td>
                                <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-400">{{ item.category || '-' }}</td>
                                <td class="px-6 py-4 text-sm text-right text-gray-900 dark:text-gray-100">{{ item.expected_quantity }}</td>
                                <td class="px-6 py-4 text-sm text-right text-gray-600 dark:text-gray-400">{{ item.cost_allocation_pct != null ? item.cost_allocation_pct + '%' : '-' }}</td>
                                <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-400 capitalize">{{ item.default_condition?.replace('_', ' ') || '-' }}</td>
                                <td class="px-6 py-4 text-sm text-right text-gray-900 dark:text-gray-100">{{ item.suggested_selling_price ? formatCurrency(item.suggested_selling_price) : '-' }}</td>
                            </tr>
                            <tr v-if="!template.items?.length">
                                <td colspan="8" class="px-6 py-12 text-center text-gray-500 dark:text-gray-400">
                                    No items defined in this template.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Recent Work Orders -->
                <div class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden">
                    <div class="px-6 py-4 border-b dark:border-gray-700">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Recent Work Orders</h3>
                    </div>
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">WO #</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Source Product</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Created</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Completed</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            <tr v-for="wo in recentWorkOrders" :key="wo.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                <td class="px-6 py-4 text-sm">
                                    <Link :href="route('inventory.part-out.work-orders.show', wo.id)" class="text-indigo-600 dark:text-indigo-400 hover:underline font-medium">
                                        {{ wo.work_order_number }}
                                    </Link>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-900 dark:text-gray-100">{{ wo.source_product?.name || '-' }}</td>
                                <td class="px-6 py-4">
                                    <span :class="statusBadgeClasses[wo.status] || 'bg-gray-100 text-gray-800'"
                                        class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium">
                                        {{ statusLabels[wo.status] || wo.status }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-400">{{ formatDate(wo.created_at) }}</td>
                                <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-400">{{ formatDate(wo.completed_at) }}</td>
                            </tr>
                            <tr v-if="recentWorkOrders.length === 0">
                                <td colspan="5" class="px-6 py-12 text-center text-gray-500 dark:text-gray-400">
                                    No work orders have used this template yet.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
