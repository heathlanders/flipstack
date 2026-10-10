<script setup>
import { ref } from 'vue'
import { router, Head, Link } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { route } from 'ziggy-js'

const props = defineProps({
    batch: { type: Object, required: true },
})

const formatCurrency = (value) => {
    return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(value || 0)
}

const formatDate = (date) => {
    if (!date) return '-'
    return new Date(date).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })
}

// Add item form
const showAddItem = ref(false)
const productSearch = ref('')
const searchResults = ref([])
const newItem = ref({ product_id: '', name: '', quantity: 1, unit_cost: 0 })

async function searchProducts() {
    if (productSearch.value.length < 2) {
        searchResults.value = []
        return
    }
    try {
        const response = await fetch(route('inventory.batches.product-search') + '?q=' + encodeURIComponent(productSearch.value), {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        })
        if (response.ok) {
            searchResults.value = await response.json()
        }
    } catch { /* ignore */ }
}

function selectProduct(product) {
    newItem.value = { product_id: product.id, name: `${product.sku} - ${product.name}`, quantity: 1, unit_cost: parseFloat(product.cost_price) || 0 }
    productSearch.value = ''
    searchResults.value = []
}

function submitItem() {
    router.post(route('inventory.batches.add-item', props.batch.id), {
        product_id: newItem.value.product_id,
        quantity: newItem.value.quantity,
        unit_cost: newItem.value.unit_cost,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            showAddItem.value = false
            newItem.value = { product_id: '', name: '', quantity: 1, unit_cost: 0 }
        },
    })
}

function closeBatch() {
    if (confirm('Close this batch? No more items can be added after closing.')) {
        router.post(route('inventory.batches.close', props.batch.id), {}, { preserveScroll: true })
    }
}
</script>

<template>
    <Head :title="`Batch ${batch.batch_number}`" />

    <AppLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <Link :href="route('inventory.batches.index')" class="text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200">
                        <i class="fas fa-arrow-left"></i>
                    </Link>
                    <div>
                        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200">{{ batch.batch_number }}</h2>
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ batch.name }}</p>
                    </div>
                    <span :class="batch.status === 'open' ? 'bg-amber-100 text-amber-700 dark:bg-amber-900 dark:text-amber-300' : 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300'"
                        class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium capitalize">
                        {{ batch.status }}
                    </span>
                </div>
                <div class="flex items-center gap-3">
                    <button v-if="batch.status === 'open'" @click="showAddItem = true" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-500 transition">
                        Add Item
                    </button>
                    <button v-if="batch.status === 'open'" @click="closeBatch" class="inline-flex items-center px-4 py-2 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md font-semibold text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest hover:bg-gray-50 dark:hover:bg-gray-600 transition">
                        Close Batch
                    </button>
                </div>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <!-- Batch Info -->
                <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6 mb-6">
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                        <div>
                            <div class="text-sm text-gray-500 dark:text-gray-400">Supplier</div>
                            <div class="font-medium text-gray-900 dark:text-gray-100">{{ batch.supplier?.company_name || batch.supplier?.name || '-' }}</div>
                        </div>
                        <div>
                            <div class="text-sm text-gray-500 dark:text-gray-400">Acquisition Date</div>
                            <div class="font-medium text-gray-900 dark:text-gray-100">{{ formatDate(batch.acquisition_date) }}</div>
                        </div>
                        <div>
                            <div class="text-sm text-gray-500 dark:text-gray-400">Total Cost</div>
                            <div class="font-medium text-gray-900 dark:text-gray-100">{{ formatCurrency(batch.acquisition_cost) }}</div>
                        </div>
                        <div>
                            <div class="text-sm text-gray-500 dark:text-gray-400">Cost Per Item</div>
                            <div class="font-medium text-gray-900 dark:text-gray-100">{{ formatCurrency(batch.cost_per_item) }}</div>
                        </div>
                    </div>
                    <div v-if="batch.notes" class="mt-4 pt-4 border-t dark:border-gray-700">
                        <div class="text-sm text-gray-500 dark:text-gray-400">Notes</div>
                        <div class="text-sm text-gray-900 dark:text-gray-100">{{ batch.notes }}</div>
                    </div>
                </div>

                <!-- Add Item Modal -->
                <div v-if="showAddItem" class="bg-white dark:bg-gray-800 shadow rounded-lg p-6 mb-6 border-2 border-indigo-500">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Add Item to Batch</h3>
                    <div class="space-y-4">
                        <div class="relative">
                            <input v-model="productSearch" @input="searchProducts" type="text" placeholder="Search products..." class="block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                            <div v-if="searchResults.length" class="absolute z-10 mt-1 w-full bg-white dark:bg-gray-700 rounded-md shadow-lg border dark:border-gray-600 max-h-48 overflow-y-auto">
                                <button v-for="product in searchResults" :key="product.id" type="button" @click="selectProduct(product)" class="w-full text-left px-4 py-2 text-sm hover:bg-gray-100 dark:hover:bg-gray-600 text-gray-900 dark:text-gray-100">
                                    <span class="font-medium">{{ product.sku }}</span> - {{ product.name }}
                                </button>
                            </div>
                        </div>
                        <div v-if="newItem.product_id" class="text-sm text-gray-900 dark:text-gray-100 font-medium">{{ newItem.name }}</div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm text-gray-500 dark:text-gray-400">Quantity</label>
                                <input v-model.number="newItem.quantity" type="number" min="1" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm" />
                            </div>
                            <div>
                                <label class="block text-sm text-gray-500 dark:text-gray-400">Unit Cost</label>
                                <input v-model.number="newItem.unit_cost" type="number" min="0" step="0.01" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm" />
                            </div>
                        </div>
                        <div class="flex gap-3">
                            <button @click="submitItem" :disabled="!newItem.product_id" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-500 transition disabled:opacity-50">
                                Add
                            </button>
                            <button @click="showAddItem = false" class="inline-flex items-center px-4 py-2 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md font-semibold text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest hover:bg-gray-50 dark:hover:bg-gray-600 transition">
                                Cancel
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Items Table -->
                <div class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden">
                    <div class="px-6 py-4 border-b dark:border-gray-700">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Items ({{ batch.items?.length ?? 0 }})</h3>
                    </div>
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Product</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Quantity</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Unit Cost</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Total</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            <tr v-for="item in batch.items" :key="item.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                <td class="px-6 py-4 text-sm">
                                    <div class="text-gray-900 dark:text-gray-100">{{ item.product?.name || '-' }}</div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400">{{ item.product?.sku }}</div>
                                </td>
                                <td class="px-6 py-4 text-sm text-right text-gray-900 dark:text-gray-100">{{ item.quantity }}</td>
                                <td class="px-6 py-4 text-sm text-right text-gray-600 dark:text-gray-400">{{ formatCurrency(item.unit_cost) }}</td>
                                <td class="px-6 py-4 text-sm text-right font-medium text-gray-900 dark:text-gray-100">{{ formatCurrency(item.total_cost) }}</td>
                            </tr>
                            <tr v-if="!batch.items?.length">
                                <td colspan="4" class="px-6 py-12 text-center text-gray-500 dark:text-gray-400">
                                    No items in this batch yet.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
