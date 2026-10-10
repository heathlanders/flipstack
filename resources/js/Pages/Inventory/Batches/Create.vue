<script setup>
import { ref, reactive } from 'vue'
import { router, Head, Link } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { route } from 'ziggy-js'

const props = defineProps({
    suppliers: { type: Array, default: () => [] },
    purchaseOrders: { type: Array, default: () => [] },
})

const form = reactive({
    name: '',
    supplier_id: '',
    purchase_order_id: '',
    description: '',
    acquisition_date: new Date().toISOString().split('T')[0],
    source_description: '',
    notes: '',
    items: [],
})

const productSearch = ref('')
const searchResults = ref([])
let searchTimeout = null

async function searchProducts() {
    clearTimeout(searchTimeout)
    if (productSearch.value.length < 2) {
        searchResults.value = []
        return
    }
    searchTimeout = setTimeout(async () => {
        try {
            const response = await fetch(route('inventory.batches.product-search') + '?q=' + encodeURIComponent(productSearch.value), {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            })
            if (response.ok) {
                searchResults.value = await response.json()
            }
        } catch { /* ignore */ }
    }, 300)
}

function addItem(product) {
    if (form.items.find(i => i.product_id === product.id)) return
    form.items.push({
        product_id: product.id,
        name: product.name,
        sku: product.sku,
        quantity: 1,
        unit_cost: parseFloat(product.cost_price) || 0,
    })
    productSearch.value = ''
    searchResults.value = []
}

function removeItem(index) {
    form.items.splice(index, 1)
}

function submit() {
    router.post(route('inventory.batches.store'), form)
}
</script>

<template>
    <Head title="Create Batch" />

    <AppLayout>
        <template #header>
            <div class="flex items-center gap-4">
                <Link :href="route('inventory.batches.index')" class="text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200">
                    <i class="fas fa-arrow-left"></i>
                </Link>
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200">Create Inventory Batch</h2>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
                <form @submit.prevent="submit" class="space-y-6">
                    <!-- Batch Details -->
                    <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6 space-y-4">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Batch Details</h3>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Name</label>
                            <input v-model="form.name" type="text" required class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="e.g., Pallet from Liquidation.com" />
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Supplier</label>
                                <select v-model="form.supplier_id" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <option value="">None</option>
                                    <option v-for="s in suppliers" :key="s.id" :value="s.id">{{ s.company_name || s.name }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Purchase Order</label>
                                <select v-model="form.purchase_order_id" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <option value="">None</option>
                                    <option v-for="po in purchaseOrders" :key="po.id" :value="po.id">{{ po.po_number }}</option>
                                </select>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Acquisition Date</label>
                                <input v-model="form.acquisition_date" type="date" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Source</label>
                                <input v-model="form.source_description" type="text" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="e.g., Liquidation.com, Estate sale" />
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Notes</label>
                            <textarea v-model="form.notes" rows="2" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500"></textarea>
                        </div>
                    </div>

                    <!-- Items -->
                    <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Items (optional - add later)</h3>

                        <div class="relative mb-4">
                            <input
                                v-model="productSearch"
                                @input="searchProducts"
                                type="text"
                                placeholder="Search products by name or SKU..."
                                class="block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            />
                            <div v-if="searchResults.length" class="absolute z-10 mt-1 w-full bg-white dark:bg-gray-700 rounded-md shadow-lg border dark:border-gray-600 max-h-48 overflow-y-auto">
                                <button
                                    v-for="product in searchResults"
                                    :key="product.id"
                                    type="button"
                                    @click="addItem(product)"
                                    class="w-full text-left px-4 py-2 text-sm hover:bg-gray-100 dark:hover:bg-gray-600 text-gray-900 dark:text-gray-100"
                                >
                                    <span class="font-medium">{{ product.sku }}</span> - {{ product.name }}
                                </button>
                            </div>
                        </div>

                        <table v-if="form.items.length" class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead>
                                <tr>
                                    <th class="text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase pb-2">Product</th>
                                    <th class="text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase pb-2 w-28">Qty</th>
                                    <th class="text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase pb-2 w-32">Unit Cost</th>
                                    <th class="w-10 pb-2"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                <tr v-for="(item, idx) in form.items" :key="item.product_id">
                                    <td class="py-2 text-sm text-gray-900 dark:text-gray-100">
                                        <span class="text-gray-500 dark:text-gray-400">{{ item.sku }}</span> - {{ item.name }}
                                    </td>
                                    <td class="py-2">
                                        <input v-model.number="item.quantity" type="number" min="1" class="w-full text-right rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm" />
                                    </td>
                                    <td class="py-2">
                                        <input v-model.number="item.unit_cost" type="number" min="0" step="0.01" class="w-full text-right rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm" />
                                    </td>
                                    <td class="py-2 text-center">
                                        <button type="button" @click="removeItem(idx)" class="text-red-500 hover:text-red-700"><i class="fas fa-times"></i></button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="flex justify-end gap-3">
                        <Link :href="route('inventory.batches.index')" class="inline-flex items-center px-4 py-2 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md font-semibold text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest hover:bg-gray-50 dark:hover:bg-gray-600 transition">
                            Cancel
                        </Link>
                        <button type="submit" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-500 transition">
                            Create Batch
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
