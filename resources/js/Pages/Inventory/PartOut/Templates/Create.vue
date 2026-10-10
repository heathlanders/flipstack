<script setup>
import { ref, reactive, computed } from 'vue'
import { router, Head, Link } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { route } from 'ziggy-js'

const props = defineProps({
    products: { type: Array, default: () => [] },
    sourceProduct: { type: Object, default: null },
})

const allocationMethodLabels = {
    market_value: 'Market Value',
    percentage: 'Percentage',
    even: 'Even Distribution',
}

const form = reactive({
    name: '',
    description: '',
    source_product_id: props.sourceProduct?.id || '',
    cost_allocation_method: 'market_value',
    items: [],
})

const selectedProduct = computed(() => props.products.find(p => p.id === form.source_product_id))

const hasComponentBreakdown = computed(() => {
    return selectedProduct.value?.component_breakdown && selectedProduct.value.component_breakdown.length > 0
})

function autoPopulateFromProduct() {
    if (!hasComponentBreakdown.value) return
    form.items = selectedProduct.value.component_breakdown.map(comp => ({
        name: comp.name || '',
        part_number: comp.part_number || '',
        manufacturer: comp.manufacturer || '',
        category: comp.category || '',
        expected_quantity: comp.expected_quantity ?? 1,
        cost_allocation_pct: comp.cost_allocation_pct ?? null,
        default_condition: comp.default_condition || 'used',
        suggested_selling_price: comp.suggested_selling_price ?? null,
    }))
}

function addItem() {
    form.items.push({
        name: '',
        part_number: '',
        manufacturer: '',
        category: '',
        expected_quantity: 1,
        cost_allocation_pct: null,
        default_condition: 'used',
        suggested_selling_price: null,
    })
}

function removeItem(index) {
    form.items.splice(index, 1)
}

function submit() {
    router.post(route('inventory.part-out.templates.store'), form)
}
</script>

<template>
    <Head title="Create Part-Out Template" />

    <AppLayout>
        <template #header>
            <div class="flex items-center gap-4">
                <Link :href="route('inventory.part-out.templates.index')" class="text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200">
                    <i class="fas fa-arrow-left"></i>
                </Link>
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200">Create Part-Out Template</h2>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
                <form @submit.prevent="submit" class="space-y-6">
                    <!-- Template Info -->
                    <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6 space-y-4">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Template Details</h3>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Template Name</label>
                            <input v-model="form.name" type="text" required class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="e.g. iPhone 14 Pro Full Part-Out" />
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Description</label>
                            <textarea v-model="form.description" rows="2" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="Optional description of this template"></textarea>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Source Product</label>
                                <select v-model="form.source_product_id" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <option value="">Select a product...</option>
                                    <option v-for="product in products" :key="product.id" :value="product.id">{{ product.sku }} - {{ product.name }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Cost Allocation Method</label>
                                <select v-model="form.cost_allocation_method" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <option value="market_value">Market Value</option>
                                    <option value="percentage">Percentage</option>
                                    <option value="even">Even Distribution</option>
                                </select>
                            </div>
                        </div>

                        <div v-if="hasComponentBreakdown">
                            <button type="button" @click="autoPopulateFromProduct" class="inline-flex items-center px-3 py-2 bg-emerald-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-emerald-500 transition">
                                Auto-populate from Product
                            </button>
                            <span class="ml-2 text-sm text-gray-500 dark:text-gray-400">This product has a component breakdown defined.</span>
                        </div>
                    </div>

                    <!-- Items -->
                    <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Template Items</h3>
                            <button type="button" @click="addItem" class="inline-flex items-center px-3 py-1.5 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-500 transition">
                                Add Item
                            </button>
                        </div>

                        <div v-if="form.items.length" class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead>
                                    <tr>
                                        <th class="text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase pb-2">Name</th>
                                        <th class="text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase pb-2">Part #</th>
                                        <th class="text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase pb-2">Manufacturer</th>
                                        <th class="text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase pb-2">Category</th>
                                        <th class="text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase pb-2 w-20">Qty</th>
                                        <th v-if="form.cost_allocation_method === 'percentage'" class="text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase pb-2 w-20">Cost %</th>
                                        <th class="text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase pb-2">Condition</th>
                                        <th class="text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase pb-2 w-28">Suggested Price</th>
                                        <th class="w-10 pb-2"></th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                    <tr v-for="(item, idx) in form.items" :key="idx">
                                        <td class="py-2 pr-2">
                                            <input v-model="item.name" type="text" required class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm" placeholder="Part name" />
                                        </td>
                                        <td class="py-2 pr-2">
                                            <input v-model="item.part_number" type="text" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm" placeholder="Part #" />
                                        </td>
                                        <td class="py-2 pr-2">
                                            <input v-model="item.manufacturer" type="text" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm" placeholder="Manufacturer" />
                                        </td>
                                        <td class="py-2 pr-2">
                                            <input v-model="item.category" type="text" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm" placeholder="Category" />
                                        </td>
                                        <td class="py-2 pr-2">
                                            <input v-model.number="item.expected_quantity" type="number" min="1" class="w-full text-right rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm" />
                                        </td>
                                        <td v-if="form.cost_allocation_method === 'percentage'" class="py-2 pr-2">
                                            <input v-model.number="item.cost_allocation_pct" type="number" min="0" max="100" step="0.01" class="w-full text-right rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm" placeholder="%" />
                                        </td>
                                        <td class="py-2 pr-2">
                                            <select v-model="item.default_condition" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm">
                                                <option value="new">New</option>
                                                <option value="like_new">Like New</option>
                                                <option value="used">Used</option>
                                                <option value="refurbished">Refurbished</option>
                                                <option value="for_parts">For Parts</option>
                                            </select>
                                        </td>
                                        <td class="py-2 pr-2">
                                            <input v-model.number="item.suggested_selling_price" type="number" min="0" step="0.01" class="w-full text-right rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm" placeholder="0.00" />
                                        </td>
                                        <td class="py-2 text-center">
                                            <button type="button" @click="removeItem(idx)" class="text-red-500 hover:text-red-700"><i class="fas fa-times"></i></button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div v-else class="text-center py-8 text-gray-500 dark:text-gray-400 text-sm">
                            Add items to define the components that will be created when this template is used.
                        </div>
                    </div>

                    <div class="flex justify-end gap-3">
                        <Link :href="route('inventory.part-out.templates.index')" class="inline-flex items-center px-4 py-2 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md font-semibold text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest hover:bg-gray-50 dark:hover:bg-gray-600 transition">
                            Cancel
                        </Link>
                        <button type="submit" :disabled="!form.name || form.items.length === 0" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-500 transition disabled:opacity-50">
                            Create Template
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
