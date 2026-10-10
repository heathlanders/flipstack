<script setup>
import { ref, reactive, computed } from 'vue'
import { router, Head, Link, useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { route } from 'ziggy-js'

const props = defineProps({
    purchaseOrder: { type: Object, required: true },
    suppliers: { type: Array, default: () => [] },
    locations: { type: Array, default: () => [] },
})

const po = props.purchaseOrder

const form = useForm({
    supplier_id: po.supplier_id || '',
    location_id: po.location_id || '',
    order_date: po.order_date ? po.order_date.split('T')[0] : '',
    expected_delivery_date: po.expected_delivery_date ? po.expected_delivery_date.split('T')[0] : '',
    notes: po.notes || '',
    tax_amount: po.tax_amount || 0,
    shipping_cost: po.shipping_cost || 0,
    items: (po.items || []).map(item => ({
        id: item.id,
        product_id: item.product_id || '',
        description: item.description || item.product?.name || '',
        sku: item.sku || item.product?.sku || '',
        quantity_ordered: item.quantity_ordered,
        unit_cost: item.unit_cost,
        notes: item.notes || '',
    })),
})

const subtotal = computed(() => {
    return form.items.reduce((sum, item) => sum + (item.quantity_ordered * item.unit_cost), 0)
})

const total = computed(() => {
    return subtotal.value + parseFloat(form.tax_amount || 0) + parseFloat(form.shipping_cost || 0)
})

const formatCurrency = (value) => {
    return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(value || 0)
}

function addItem() {
    form.items.push({
        id: null,
        product_id: '',
        description: '',
        sku: '',
        quantity_ordered: 1,
        unit_cost: 0,
        notes: '',
    })
}

function removeItem(index) {
    form.items.splice(index, 1)
}

function submit() {
    form.put(route('procurement.purchase-orders.update', po.id))
}
</script>

<template>
    <Head :title="`Edit ${po.po_number}`" />

    <AppLayout>
        <template #header>
            <div class="flex items-center gap-4">
                <Link :href="route('procurement.purchase-orders.show', po.id)" class="text-gray-500 dark:text-gray-400 hover:text-gray-700">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                </Link>
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200">Edit {{ po.po_number }}</h2>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
                <form @submit.prevent="submit" class="space-y-6">
                    <!-- Header Fields -->
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Order Details</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Supplier</label>
                                <select
                                    v-model="form.supplier_id"
                                    class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                >
                                    <option value="">Select supplier...</option>
                                    <option v-for="s in suppliers" :key="s.id" :value="s.id">{{ s.company_name || s.name }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Receiving Location</label>
                                <select
                                    v-model="form.location_id"
                                    class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                >
                                    <option value="">Select location...</option>
                                    <option v-for="l in locations" :key="l.id" :value="l.id">{{ l.name }} ({{ l.code }})</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Order Date</label>
                                <input v-model="form.order_date" type="date" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-sm" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Expected Delivery</label>
                                <input v-model="form.expected_delivery_date" type="date" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-sm" />
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Notes</label>
                                <textarea v-model="form.notes" rows="2" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-sm" />
                            </div>
                        </div>
                    </div>

                    <!-- Line Items -->
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-white">Line Items</h3>
                            <button type="button" @click="addItem" class="text-sm text-indigo-600 dark:text-indigo-400 hover:text-indigo-800 font-medium">+ Add Item</button>
                        </div>
                        <div v-if="form.items.length" class="space-y-4">
                            <div v-for="(item, index) in form.items" :key="index" class="border border-gray-200 dark:border-gray-600 rounded-lg p-4">
                                <div class="grid grid-cols-12 gap-3 items-end">
                                    <div class="col-span-5">
                                        <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Description</label>
                                        <input v-model="item.description" type="text" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-sm" />
                                    </div>
                                    <div class="col-span-2">
                                        <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">SKU</label>
                                        <input v-model="item.sku" type="text" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-sm" />
                                    </div>
                                    <div class="col-span-1">
                                        <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Qty</label>
                                        <input v-model.number="item.quantity_ordered" type="number" min="1" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-sm" />
                                    </div>
                                    <div class="col-span-2">
                                        <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Unit Cost</label>
                                        <input v-model.number="item.unit_cost" type="number" step="0.01" min="0" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-sm" />
                                    </div>
                                    <div class="col-span-1 text-right text-sm font-medium text-gray-700 dark:text-gray-300">
                                        {{ formatCurrency(item.quantity_ordered * item.unit_cost) }}
                                    </div>
                                    <div class="col-span-1 text-right">
                                        <button type="button" @click="removeItem(index)" class="text-red-500 hover:text-red-700 text-sm">Remove</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Totals & Actions -->
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Tax Amount</label>
                                <input v-model.number="form.tax_amount" type="number" step="0.01" min="0" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-sm" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Shipping Cost</label>
                                <input v-model.number="form.shipping_cost" type="number" step="0.01" min="0" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-sm" />
                            </div>
                            <div class="flex items-end justify-end">
                                <div class="text-right">
                                    <p class="text-sm text-gray-500 dark:text-gray-400">Subtotal: {{ formatCurrency(subtotal) }}</p>
                                    <p class="text-lg font-bold text-gray-900 dark:text-white">Total: {{ formatCurrency(total) }}</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end gap-3">
                        <Link :href="route('procurement.purchase-orders.show', po.id)" class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50">Cancel</Link>
                        <button type="submit" :disabled="form.processing" class="px-4 py-2 text-sm font-medium text-white bg-indigo-600 rounded-md hover:bg-indigo-500 disabled:opacity-50">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
