<script setup>
import { router, Head, Link } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { route } from 'ziggy-js'

const props = defineProps({
    workOrder: { type: Object, required: true },
})

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

const allocationMethodLabels = {
    market_value: 'Market Value',
    percentage: 'Percentage',
    even: 'Even Distribution',
}

const formatCurrency = (value) => {
    return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(value || 0)
}

const formatDate = (date) => {
    if (!date) return '-'
    return new Date(date).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })
}

const formatDateTime = (date) => {
    if (!date) return '-'
    return new Date(date).toLocaleString('en-US', { month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit' })
}

function executeWorkOrder() {
    if (confirm('Execute this work order? This will create component products and adjust inventory.')) {
        router.post(route('inventory.part-out.work-orders.execute', props.workOrder.id), {}, { preserveScroll: true })
    }
}

function cancelWorkOrder() {
    if (confirm('Cancel this work order?')) {
        router.post(route('inventory.part-out.work-orders.cancel', props.workOrder.id), {}, { preserveScroll: true })
    }
}
</script>

<template>
    <Head :title="`Work Order: ${workOrder.work_order_number}`" />

    <AppLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <Link :href="route('inventory.part-out.work-orders.index')" class="text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200">
                        <i class="fas fa-arrow-left"></i>
                    </Link>
                    <div>
                        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200">{{ workOrder.work_order_number }}</h2>
                        <div class="flex items-center gap-2 mt-1">
                            <span :class="statusBadgeClasses[workOrder.status] || 'bg-gray-100 text-gray-800'"
                                class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium">
                                {{ statusLabels[workOrder.status] || workOrder.status }}
                            </span>
                            <span class="text-sm text-gray-500 dark:text-gray-400">Created {{ formatDate(workOrder.created_at) }}</span>
                            <span v-if="workOrder.completed_at" class="text-sm text-gray-500 dark:text-gray-400">| Completed {{ formatDate(workOrder.completed_at) }}</span>
                        </div>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <button v-if="workOrder.status === 'draft' || workOrder.status === 'in_progress'" @click="executeWorkOrder" class="inline-flex items-center px-4 py-2 bg-emerald-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-emerald-500 transition">
                        Execute
                    </button>
                    <button v-if="workOrder.status === 'draft'" @click="cancelWorkOrder" class="inline-flex items-center px-4 py-2 bg-red-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-red-500 transition">
                        Cancel
                    </button>
                </div>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <!-- Source Product Card -->
                <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6 mb-6">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Source Product</h3>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                        <div>
                            <div class="text-sm text-gray-500 dark:text-gray-400">Product</div>
                            <div class="font-medium text-gray-900 dark:text-gray-100">{{ workOrder.source_product?.name || '-' }}</div>
                            <div class="text-xs text-gray-500 dark:text-gray-400">{{ workOrder.source_product?.sku }}</div>
                        </div>
                        <div>
                            <div class="text-sm text-gray-500 dark:text-gray-400">Cost Price</div>
                            <div class="font-medium text-gray-900 dark:text-gray-100">{{ formatCurrency(workOrder.source_product?.cost_price) }}</div>
                        </div>
                        <div>
                            <div class="text-sm text-gray-500 dark:text-gray-400">Quantity</div>
                            <div class="text-xl font-bold text-gray-900 dark:text-white">{{ workOrder.source_quantity ?? 1 }}</div>
                        </div>
                        <div>
                            <div class="text-sm text-gray-500 dark:text-gray-400">Allocation Method</div>
                            <div class="font-medium text-gray-900 dark:text-gray-100">{{ allocationMethodLabels[workOrder.cost_allocation_method] || workOrder.cost_allocation_method }}</div>
                        </div>
                    </div>

                    <!-- Parent Continues Info -->
                    <div v-if="workOrder.parent_continues" class="mt-4 pt-4 border-t dark:border-gray-700">
                        <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                            <div>
                                <div class="text-sm text-gray-500 dark:text-gray-400">Parent Continues</div>
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300">Yes</span>
                            </div>
                            <div>
                                <div class="text-sm text-gray-500 dark:text-gray-400">Parent New Name</div>
                                <div class="font-medium text-gray-900 dark:text-gray-100">{{ workOrder.parent_new_name || '-' }}</div>
                            </div>
                            <div>
                                <div class="text-sm text-gray-500 dark:text-gray-400">Remaining Parent Cost</div>
                                <div class="font-medium text-gray-900 dark:text-gray-100">{{ formatCurrency(workOrder.remaining_parent_cost) }}</div>
                            </div>
                        </div>
                    </div>

                    <!-- Template reference -->
                    <div v-if="workOrder.template" class="mt-4 pt-4 border-t dark:border-gray-700">
                        <div class="text-sm text-gray-500 dark:text-gray-400">Template</div>
                        <Link :href="route('inventory.part-out.templates.show', workOrder.template.id)" class="text-indigo-600 dark:text-indigo-400 hover:underline font-medium">
                            {{ workOrder.template.name }}
                        </Link>
                    </div>
                </div>

                <!-- Items Table -->
                <div class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden mb-6">
                    <div class="px-6 py-4 border-b dark:border-gray-700">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Component Items ({{ workOrder.items?.length ?? 0 }})</h3>
                    </div>
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Name</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Part #</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Category</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Qty</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Allocated Cost</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Cost/Unit</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Allocation %</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Suggested Price</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Condition</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            <tr v-for="item in workOrder.items" :key="item.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                <td class="px-6 py-4 text-sm text-gray-900 dark:text-gray-100">{{ item.name }}</td>
                                <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-400">{{ item.part_number || '-' }}</td>
                                <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-400">{{ item.category || '-' }}</td>
                                <td class="px-6 py-4 text-sm text-right text-gray-900 dark:text-gray-100">{{ item.quantity }}</td>
                                <td class="px-6 py-4 text-sm text-right text-gray-900 dark:text-gray-100">{{ formatCurrency(item.allocated_cost) }}</td>
                                <td class="px-6 py-4 text-sm text-right text-gray-600 dark:text-gray-400">{{ item.quantity > 0 ? formatCurrency(item.allocated_cost / item.quantity) : '-' }}</td>
                                <td class="px-6 py-4 text-sm text-right text-gray-600 dark:text-gray-400">{{ item.cost_allocation_pct != null ? item.cost_allocation_pct + '%' : '-' }}</td>
                                <td class="px-6 py-4 text-sm text-right text-gray-900 dark:text-gray-100">{{ item.suggested_selling_price ? formatCurrency(item.suggested_selling_price) : '-' }}</td>
                                <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-400 capitalize">{{ item.condition?.replace('_', ' ') || '-' }}</td>
                            </tr>
                            <tr v-if="!workOrder.items?.length">
                                <td colspan="9" class="px-6 py-12 text-center text-gray-500 dark:text-gray-400">
                                    No component items in this work order.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Created Products (if completed) -->
                <div v-if="workOrder.status === 'completed' && workOrder.created_products?.length" class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden mb-6">
                    <div class="px-6 py-4 border-b dark:border-gray-700">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Created Products ({{ workOrder.created_products.length }})</h3>
                    </div>
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Product</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">SKU</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Cost</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Selling Price</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Qty</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            <tr v-for="product in workOrder.created_products" :key="product.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                <td class="px-6 py-4 text-sm">
                                    <Link :href="route('inventory.products.show', product.id)" class="text-indigo-600 dark:text-indigo-400 hover:underline font-medium">
                                        {{ product.name }}
                                    </Link>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-400">{{ product.sku }}</td>
                                <td class="px-6 py-4 text-sm text-right text-gray-900 dark:text-gray-100">{{ formatCurrency(product.cost_price) }}</td>
                                <td class="px-6 py-4 text-sm text-right text-gray-900 dark:text-gray-100">{{ formatCurrency(product.selling_price) }}</td>
                                <td class="px-6 py-4 text-sm text-right text-gray-900 dark:text-gray-100">{{ product.available_quantity }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Completion Info -->
                <div v-if="workOrder.status === 'completed'" class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Completion Details</h3>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <div class="text-sm text-gray-500 dark:text-gray-400">Completed By</div>
                            <div class="font-medium text-gray-900 dark:text-gray-100">{{ workOrder.completed_by?.name || '-' }}</div>
                        </div>
                        <div>
                            <div class="text-sm text-gray-500 dark:text-gray-400">Completed At</div>
                            <div class="font-medium text-gray-900 dark:text-gray-100">{{ formatDateTime(workOrder.completed_at) }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
