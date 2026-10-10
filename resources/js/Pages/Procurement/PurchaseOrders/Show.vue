<script setup>
import { ref } from 'vue'
import { router, Head, Link } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { route } from 'ziggy-js'

const props = defineProps({
    purchaseOrder: { type: Object, required: true },
})

const po = props.purchaseOrder
const showCancelModal = ref(false)
const cancelReason = ref('')

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

const statusColors = {
    draft: 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300',
    submitted: 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300',
    partially_received: 'bg-amber-100 text-amber-700 dark:bg-amber-900 dark:text-amber-300',
    received: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900 dark:text-emerald-300',
    cancelled: 'bg-red-100 text-red-700 dark:bg-red-900 dark:text-red-300',
}

const statusLabels = {
    draft: 'Draft',
    submitted: 'Submitted',
    partially_received: 'Partially Received',
    received: 'Received',
    cancelled: 'Cancelled',
}

function submitPo() {
    router.post(route('procurement.purchase-orders.submit', po.id))
}

function cancelPo() {
    router.post(route('procurement.purchase-orders.cancel', po.id), { reason: cancelReason.value })
    showCancelModal.value = false
}

function deletePo() {
    if (confirm('Are you sure you want to delete this purchase order?')) {
        router.delete(route('procurement.purchase-orders.destroy', po.id))
    }
}
</script>

<template>
    <Head :title="`PO ${po.po_number}`" />

    <AppLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <Link :href="route('procurement.purchase-orders.index')" class="text-gray-500 dark:text-gray-400 hover:text-gray-700">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                    </Link>
                    <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200">{{ po.po_number }}</h2>
                    <span :class="['px-3 py-1 text-sm font-medium rounded-full', statusColors[po.status]]">
                        {{ statusLabels[po.status] }}
                    </span>
                </div>
                <div class="flex gap-2">
                    <Link
                        v-if="po.status === 'draft'"
                        :href="route('procurement.purchase-orders.edit', po.id)"
                        class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50"
                    >
                        Edit
                    </Link>
                    <button
                        v-if="po.status === 'draft' && po.items?.length"
                        @click="submitPo"
                        class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-md hover:bg-blue-500"
                    >
                        Submit
                    </button>
                    <Link
                        v-if="['submitted', 'partially_received'].includes(po.status)"
                        :href="route('procurement.purchase-orders.receive', po.id)"
                        class="px-4 py-2 text-sm font-medium text-white bg-emerald-600 rounded-md hover:bg-emerald-500"
                    >
                        Receive Items
                    </Link>
                    <button
                        v-if="['draft', 'submitted'].includes(po.status)"
                        @click="showCancelModal = true"
                        class="px-4 py-2 text-sm font-medium text-red-600 dark:text-red-400 bg-white dark:bg-gray-700 border border-red-300 dark:border-red-600 rounded-md hover:bg-red-50"
                    >
                        Cancel
                    </button>
                    <button
                        v-if="po.status === 'draft'"
                        @click="deletePo"
                        class="px-4 py-2 text-sm font-medium text-red-600 hover:text-red-800"
                    >
                        Delete
                    </button>
                </div>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
                <!-- Order Info -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-6">
                        <div>
                            <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Supplier</p>
                            <p class="text-sm font-medium text-gray-900 dark:text-white mt-1">{{ po.supplier?.company_name || po.supplier?.name || '-' }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Location</p>
                            <p class="text-sm font-medium text-gray-900 dark:text-white mt-1">{{ po.location?.name || '-' }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Order Date</p>
                            <p class="text-sm text-gray-900 dark:text-white mt-1">{{ formatDate(po.order_date) }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Expected Delivery</p>
                            <p class="text-sm text-gray-900 dark:text-white mt-1">{{ formatDate(po.expected_delivery_date) }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Created By</p>
                            <p class="text-sm text-gray-900 dark:text-white mt-1">{{ po.creator?.name || '-' }}</p>
                        </div>
                        <div v-if="po.submitted_at">
                            <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Submitted</p>
                            <p class="text-sm text-gray-900 dark:text-white mt-1">{{ formatDateTime(po.submitted_at) }}</p>
                        </div>
                        <div v-if="po.received_at">
                            <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Received</p>
                            <p class="text-sm text-gray-900 dark:text-white mt-1">{{ formatDateTime(po.received_at) }}</p>
                        </div>
                    </div>
                    <div v-if="po.notes" class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                        <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase mb-1">Notes</p>
                        <p class="text-sm text-gray-700 dark:text-gray-300">{{ po.notes }}</p>
                    </div>
                </div>

                <!-- Line Items -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white">Line Items</h3>
                    </div>
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Item</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">SKU</th>
                                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Ordered</th>
                                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Received</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Unit Cost</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Total</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            <tr v-for="item in po.items" :key="item.id">
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-3">
                                        <img
                                            v-if="item.product?.featured_image"
                                            :src="item.product.featured_image"
                                            class="w-10 h-10 rounded object-cover"
                                        />
                                        <div>
                                            <p class="text-sm font-medium text-gray-900 dark:text-white">{{ item.description || item.product?.name || '-' }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">{{ item.sku || item.product?.sku || '-' }}</td>
                                <td class="px-6 py-4 text-center text-sm text-gray-900 dark:text-white">{{ item.quantity_ordered }}</td>
                                <td class="px-6 py-4 text-center">
                                    <span
                                        class="text-sm font-medium"
                                        :class="{
                                            'text-emerald-600 dark:text-emerald-400': item.quantity_received >= item.quantity_ordered,
                                            'text-amber-600 dark:text-amber-400': item.quantity_received > 0 && item.quantity_received < item.quantity_ordered,
                                            'text-gray-500 dark:text-gray-400': item.quantity_received === 0,
                                        }"
                                    >
                                        {{ item.quantity_received }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-right text-sm text-gray-900 dark:text-white">{{ formatCurrency(item.unit_cost) }}</td>
                                <td class="px-6 py-4 text-right text-sm font-medium text-gray-900 dark:text-white">{{ formatCurrency(item.total_cost) }}</td>
                            </tr>
                        </tbody>
                        <tfoot class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <td colspan="5" class="px-6 py-3 text-right text-sm font-medium text-gray-500 dark:text-gray-400">Subtotal</td>
                                <td class="px-6 py-3 text-right text-sm font-medium text-gray-900 dark:text-white">{{ formatCurrency(po.subtotal) }}</td>
                            </tr>
                            <tr v-if="po.tax_amount > 0">
                                <td colspan="5" class="px-6 py-2 text-right text-sm text-gray-500 dark:text-gray-400">Tax</td>
                                <td class="px-6 py-2 text-right text-sm text-gray-900 dark:text-white">{{ formatCurrency(po.tax_amount) }}</td>
                            </tr>
                            <tr v-if="po.shipping_cost > 0">
                                <td colspan="5" class="px-6 py-2 text-right text-sm text-gray-500 dark:text-gray-400">Shipping</td>
                                <td class="px-6 py-2 text-right text-sm text-gray-900 dark:text-white">{{ formatCurrency(po.shipping_cost) }}</td>
                            </tr>
                            <tr>
                                <td colspan="5" class="px-6 py-3 text-right text-sm font-bold text-gray-900 dark:text-white">Total</td>
                                <td class="px-6 py-3 text-right text-sm font-bold text-gray-900 dark:text-white">{{ formatCurrency(po.total_amount) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <!-- Receipts -->
                <div v-if="po.receipts?.length" class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Receiving History</h3>
                    <div class="space-y-4">
                        <div
                            v-for="receipt in po.receipts"
                            :key="receipt.id"
                            class="border border-gray-200 dark:border-gray-600 rounded-lg p-4"
                        >
                            <div class="flex items-center justify-between mb-2">
                                <div class="text-sm text-gray-900 dark:text-white">
                                    Received by <span class="font-medium">{{ receipt.received_by_user?.name || 'Unknown' }}</span>
                                </div>
                                <span class="text-sm text-gray-500 dark:text-gray-400">{{ formatDateTime(receipt.received_at) }}</span>
                            </div>
                            <div v-if="receipt.notes" class="text-sm text-gray-600 dark:text-gray-400 mb-2">{{ receipt.notes }}</div>
                            <div class="text-sm text-gray-500 dark:text-gray-400">
                                {{ receipt.items?.length || 0 }} item(s) received
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Cancel Modal -->
        <div v-if="showCancelModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl p-6 max-w-md w-full mx-4">
                <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Cancel Purchase Order</h3>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Reason (optional)</label>
                    <textarea
                        v-model="cancelReason"
                        rows="3"
                        class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-sm"
                        placeholder="Why is this PO being cancelled?"
                    />
                </div>
                <div class="flex justify-end gap-3">
                    <button
                        @click="showCancelModal = false"
                        class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50"
                    >
                        Keep Order
                    </button>
                    <button
                        @click="cancelPo"
                        class="px-4 py-2 text-sm font-medium text-white bg-red-600 rounded-md hover:bg-red-500"
                    >
                        Cancel Order
                    </button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
