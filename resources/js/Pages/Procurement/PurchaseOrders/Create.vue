<script setup>
import { ref, reactive, computed } from 'vue'
import { router, Head, Link, useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { route } from 'ziggy-js'

const props = defineProps({
    suppliers: { type: Array, default: () => [] },
    locations: { type: Array, default: () => [] },
})

const form = useForm({
    supplier_id: '',
    location_id: '',
    order_date: new Date().toISOString().split('T')[0],
    expected_delivery_date: '',
    notes: '',
    tax_amount: 0,
    shipping_cost: 0,
    items: [],
})

const supplierProducts = ref([])
const loadingProducts = ref(false)

const subtotal = computed(() => {
    return form.items.reduce((sum, item) => sum + (item.quantity_ordered * item.unit_cost), 0)
})

const total = computed(() => {
    return subtotal.value + parseFloat(form.tax_amount || 0) + parseFloat(form.shipping_cost || 0)
})

const formatCurrency = (value) => {
    return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(value || 0)
}

async function onSupplierChange() {
    if (!form.supplier_id) {
        supplierProducts.value = []
        return
    }
    loadingProducts.value = true
    try {
        const response = await fetch(route('procurement.purchase-orders.supplier-products') + `?supplier_id=${form.supplier_id}`)
        supplierProducts.value = await response.json()
    } catch (e) {
        supplierProducts.value = []
    } finally {
        loadingProducts.value = false
    }
}

function addItem() {
    form.items.push({
        product_id: '',
        supplier_product_id: '',
        description: '',
        sku: '',
        quantity_ordered: 1,
        unit_cost: 0,
        notes: '',
    })
}

function addFromSupplierProduct(sp) {
    form.items.push({
        product_id: sp.product_id || '',
        supplier_product_id: sp.id,
        description: sp.product_name,
        sku: sp.sku || '',
        quantity_ordered: sp.minimum_order_quantity || 1,
        unit_cost: sp.cost_price || 0,
        notes: '',
    })
}

function removeItem(index) {
    form.items.splice(index, 1)
}

function submit() {
    form.post(route('procurement.purchase-orders.store'))
}
</script>

<template>
    <Head title="Create Purchase Order" />

    <AppLayout>
        <template #header>
            <div class="flex items-center gap-4">
                <Link :href="route('procurement.purchase-orders.index')" class="text-gray-500 dark:text-gray-400 hover:text-gray-700">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                </Link>
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200">New Purchase Order</h2>
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
                                    @change="onSupplierChange"
                                    class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                >
                                    <option value="">Select supplier...</option>
                                    <option v-for="s in suppliers" :key="s.id" :value="s.id">{{ s.company_name || s.name }}</option>
                                </select>
                                <p v-if="form.errors.supplier_id" class="mt-1 text-sm text-red-600">{{ form.errors.supplier_id }}</p>
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
                                <input
                                    v-model="form.order_date"
                                    type="date"
                                    class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Expected Delivery</label>
                                <input
                                    v-model="form.expected_delivery_date"
                                    type="date"
                                    class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                />
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Notes</label>
                                <textarea
                                    v-model="form.notes"
                                    rows="2"
                                    class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                />
                            </div>
                        </div>
                    </div>

                    <!-- Supplier Products Quick Add -->
                    <div v-if="supplierProducts.length" class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Supplier Products</h3>
                        <div class="space-y-2 max-h-48 overflow-y-auto">
                            <div
                                v-for="sp in supplierProducts"
                                :key="sp.id"
                                class="flex items-center justify-between p-2 rounded hover:bg-gray-50 dark:hover:bg-gray-700"
                            >
                                <div>
                                    <span class="text-sm font-medium text-gray-900 dark:text-white">{{ sp.product_name }}</span>
                                    <span v-if="sp.sku" class="ml-2 text-xs text-gray-500 dark:text-gray-400">{{ sp.sku }}</span>
                                    <span class="ml-2 text-sm text-gray-600 dark:text-gray-300">{{ formatCurrency(sp.cost_price) }}</span>
                                </div>
                                <button
                                    type="button"
                                    @click="addFromSupplierProduct(sp)"
                                    class="text-xs text-indigo-600 dark:text-indigo-400 hover:text-indigo-800 font-medium"
                                >
                                    + Add
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Line Items -->
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-white">Line Items</h3>
                            <button
                                type="button"
                                @click="addItem"
                                class="text-sm text-indigo-600 dark:text-indigo-400 hover:text-indigo-800 font-medium"
                            >
                                + Add Item
                            </button>
                        </div>

                        <div v-if="form.items.length" class="space-y-4">
                            <div
                                v-for="(item, index) in form.items"
                                :key="index"
                                class="border border-gray-200 dark:border-gray-600 rounded-lg p-4"
                            >
                                <div class="grid grid-cols-12 gap-3 items-end">
                                    <div class="col-span-5">
                                        <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Description</label>
                                        <input
                                            v-model="item.description"
                                            type="text"
                                            placeholder="Item description"
                                            class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-sm"
                                        />
                                    </div>
                                    <div class="col-span-2">
                                        <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">SKU</label>
                                        <input
                                            v-model="item.sku"
                                            type="text"
                                            class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-sm"
                                        />
                                    </div>
                                    <div class="col-span-1">
                                        <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Qty</label>
                                        <input
                                            v-model.number="item.quantity_ordered"
                                            type="number"
                                            min="1"
                                            class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-sm"
                                        />
                                    </div>
                                    <div class="col-span-2">
                                        <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Unit Cost</label>
                                        <input
                                            v-model.number="item.unit_cost"
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-sm"
                                        />
                                    </div>
                                    <div class="col-span-1 text-right text-sm font-medium text-gray-700 dark:text-gray-300">
                                        {{ formatCurrency(item.quantity_ordered * item.unit_cost) }}
                                    </div>
                                    <div class="col-span-1 text-right">
                                        <button
                                            type="button"
                                            @click="removeItem(index)"
                                            class="text-red-500 hover:text-red-700 text-sm"
                                        >
                                            Remove
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div v-else class="text-center py-8 text-gray-500 dark:text-gray-400 text-sm">
                            No items added yet. Click "Add Item" or select from supplier products above.
                        </div>
                    </div>

                    <!-- Totals -->
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Tax Amount</label>
                                <input
                                    v-model.number="form.tax_amount"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-sm"
                                />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Shipping Cost</label>
                                <input
                                    v-model.number="form.shipping_cost"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-sm"
                                />
                            </div>
                            <div class="flex items-end justify-end">
                                <div class="text-right">
                                    <p class="text-sm text-gray-500 dark:text-gray-400">Subtotal: {{ formatCurrency(subtotal) }}</p>
                                    <p class="text-lg font-bold text-gray-900 dark:text-white">Total: {{ formatCurrency(total) }}</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="flex justify-end gap-3">
                        <Link
                            :href="route('procurement.purchase-orders.index')"
                            class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-gray-600"
                        >
                            Cancel
                        </Link>
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="px-4 py-2 text-sm font-medium text-white bg-indigo-600 border border-transparent rounded-md hover:bg-indigo-500 disabled:opacity-50"
                        >
                            Create Purchase Order
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
