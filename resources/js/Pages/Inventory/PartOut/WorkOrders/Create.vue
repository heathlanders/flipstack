<script setup>
import { ref, reactive, computed, watch } from 'vue'
import { router, Head, Link } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { route } from 'ziggy-js'

const props = defineProps({
    products: { type: Array, default: () => [] },
    templates: { type: Array, default: () => [] },
    locations: { type: Array, default: () => [] },
    preselectedProductId: { type: [String, Number], default: null },
    preselectedTemplateId: { type: [String, Number], default: null },
})

const allocationMethodLabels = {
    market_value: 'Market Value',
    percentage: 'Percentage',
    even: 'Even Distribution',
}

const form = reactive({
    source_product_id: props.preselectedProductId || '',
    source_quantity: 1,
    template_id: props.preselectedTemplateId || '',
    location_id: '',
    parent_continues: false,
    parent_new_name: '',
    remaining_parent_cost: null,
    cost_allocation_method: 'market_value',
    items: [],
})

const formatCurrency = (value) => {
    return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(value || 0)
}

const selectedProduct = computed(() => props.products.find(p => p.id === form.source_product_id))
const selectedTemplate = computed(() => props.templates.find(t => t.id === form.template_id))

const hasComponentBreakdown = computed(() => {
    return selectedProduct.value?.component_breakdown && selectedProduct.value.component_breakdown.length > 0
})

const sourceCost = computed(() => {
    return (selectedProduct.value?.cost_price || 0) * (form.source_quantity || 1)
})

const remainingParentCost = computed(() => {
    return form.parent_continues ? (form.remaining_parent_cost || 0) : 0
})

const totalAllocated = computed(() => {
    if (form.cost_allocation_method === 'percentage') {
        const totalPct = form.items.reduce((sum, item) => sum + (parseFloat(item.cost_allocation_pct) || 0), 0)
        return (sourceCost.value - remainingParentCost.value) * (totalPct / 100)
    }
    if (form.cost_allocation_method === 'even') {
        const totalItems = form.items.reduce((sum, item) => sum + (item.quantity || 0), 0)
        return totalItems > 0 ? sourceCost.value - remainingParentCost.value : 0
    }
    // market_value: based on suggested selling prices
    const totalMarketValue = form.items.reduce((sum, item) => sum + ((item.suggested_selling_price || 0) * (item.quantity || 0)), 0)
    return totalMarketValue > 0 ? sourceCost.value - remainingParentCost.value : 0
})

const unallocated = computed(() => {
    return sourceCost.value - remainingParentCost.value - totalAllocated.value
})

const totalComponentValue = computed(() => {
    return form.items.reduce((sum, item) => sum + ((item.suggested_selling_price || 0) * (item.quantity || 0)), 0)
})

const projectedProfit = computed(() => {
    return totalComponentValue.value - sourceCost.value
})

const roi = computed(() => {
    if (sourceCost.value === 0) return 0
    return (projectedProfit.value / sourceCost.value) * 100
})

const costPreviewItems = computed(() => {
    const allocatable = sourceCost.value - remainingParentCost.value
    const totalMarketValue = form.items.reduce((sum, item) => sum + ((item.suggested_selling_price || 0) * (item.quantity || 0)), 0)
    const totalQty = form.items.reduce((sum, item) => sum + (item.quantity || 0), 0)

    return form.items.map(item => {
        const qty = item.quantity || 0
        const marketPrice = (item.suggested_selling_price || 0) * qty
        let allocatedCost = 0

        if (form.cost_allocation_method === 'percentage') {
            allocatedCost = allocatable * ((parseFloat(item.cost_allocation_pct) || 0) / 100)
        } else if (form.cost_allocation_method === 'even') {
            allocatedCost = totalQty > 0 ? (allocatable / totalQty) * qty : 0
        } else {
            // market_value
            allocatedCost = totalMarketValue > 0 ? (marketPrice / totalMarketValue) * allocatable : 0
        }

        return {
            name: item.name || '(unnamed)',
            quantity: qty,
            marketPrice,
            allocatedCost,
            costPerUnit: qty > 0 ? allocatedCost / qty : 0,
        }
    })
})

function autoPopulateFromProduct() {
    if (!hasComponentBreakdown.value) return
    form.items = selectedProduct.value.component_breakdown.map(comp => ({
        name: comp.name || '',
        part_number: comp.part_number || '',
        manufacturer: comp.manufacturer || '',
        category: comp.category || '',
        quantity: comp.expected_quantity ?? 1,
        suggested_selling_price: comp.suggested_selling_price ?? null,
        cost_allocation_pct: comp.cost_allocation_pct ?? null,
        condition: comp.default_condition || 'used',
        notes: '',
    }))
}

function autoPopulateFromTemplate() {
    if (!selectedTemplate.value?.items) return
    form.cost_allocation_method = selectedTemplate.value.cost_allocation_method || form.cost_allocation_method
    form.items = selectedTemplate.value.items.map(item => ({
        name: item.name || '',
        part_number: item.part_number || '',
        manufacturer: item.manufacturer || '',
        category: item.category || '',
        quantity: item.expected_quantity ?? 1,
        suggested_selling_price: item.suggested_selling_price ?? null,
        cost_allocation_pct: item.cost_allocation_pct ?? null,
        condition: item.default_condition || 'used',
        notes: '',
    }))
}

function addItem() {
    form.items.push({
        name: '',
        part_number: '',
        manufacturer: '',
        category: '',
        quantity: 1,
        suggested_selling_price: null,
        cost_allocation_pct: null,
        condition: 'used',
        notes: '',
    })
}

function removeItem(index) {
    form.items.splice(index, 1)
}

function submit() {
    router.post(route('inventory.part-out.work-orders.store'), form)
}
</script>

<template>
    <Head title="Create Part-Out Work Order" />

    <AppLayout>
        <template #header>
            <div class="flex items-center gap-4">
                <Link :href="route('inventory.part-out.work-orders.index')" class="text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200">
                    <i class="fas fa-arrow-left"></i>
                </Link>
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200">Create Part-Out Work Order</h2>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
                <form @submit.prevent="submit" class="space-y-6">
                    <!-- Source & Configuration -->
                    <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6 space-y-4">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Source Product</h3>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Source Product</label>
                                <select v-model="form.source_product_id" required class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <option value="">Select a product...</option>
                                    <option v-for="product in products" :key="product.id" :value="product.id">{{ product.sku }} - {{ product.name }}</option>
                                </select>
                                <div v-if="hasComponentBreakdown" class="mt-1">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300">Has component breakdown</span>
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Source Quantity</label>
                                <input v-model.number="form.source_quantity" type="number" min="1" required class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                                <p v-if="selectedProduct" class="mt-1 text-xs text-gray-500 dark:text-gray-400">Available: {{ selectedProduct.available_quantity }} | Cost: {{ formatCurrency(selectedProduct.cost_price) }}/unit</p>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Template (optional)</label>
                                <select v-model="form.template_id" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <option value="">No template</option>
                                    <option v-for="tpl in templates" :key="tpl.id" :value="tpl.id">{{ tpl.name }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Location</label>
                                <select v-model="form.location_id" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <option value="">Select location...</option>
                                    <option v-for="loc in locations" :key="loc.id" :value="loc.id">{{ loc.name }}</option>
                                </select>
                            </div>
                        </div>

                        <div class="flex gap-4">
                            <button v-if="hasComponentBreakdown" type="button" @click="autoPopulateFromProduct" class="inline-flex items-center px-3 py-2 bg-emerald-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-emerald-500 transition">
                                Auto-populate from Product
                            </button>
                            <button v-if="selectedTemplate" type="button" @click="autoPopulateFromTemplate" class="inline-flex items-center px-3 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-500 transition">
                                Auto-populate from Template
                            </button>
                        </div>
                    </div>

                    <!-- Parent Continues & Cost Method -->
                    <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6 space-y-4">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Cost Allocation</h3>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Cost Allocation Method</label>
                            <select v-model="form.cost_allocation_method" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 max-w-xs">
                                <option value="market_value">Market Value</option>
                                <option value="percentage">Percentage</option>
                                <option value="even">Even Distribution</option>
                            </select>
                        </div>

                        <div class="border-t dark:border-gray-700 pt-4">
                            <label class="flex items-center gap-2">
                                <input v-model="form.parent_continues" type="checkbox" class="rounded border-gray-300 dark:border-gray-600 text-indigo-600 focus:ring-indigo-500" />
                                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Parent product continues to exist after part-out</span>
                            </label>
                        </div>

                        <div v-if="form.parent_continues" class="grid grid-cols-2 gap-4 pl-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Parent New Name</label>
                                <input v-model="form.parent_new_name" type="text" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="e.g. iPhone 14 Pro - Shell Only" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Remaining Parent Cost</label>
                                <input v-model.number="form.remaining_parent_cost" type="number" min="0" step="0.01" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="0.00" />
                            </div>
                        </div>
                    </div>

                    <!-- Items -->
                    <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Component Items</h3>
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
                                        <th class="text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase pb-2 w-16">Qty</th>
                                        <th class="text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase pb-2 w-24">Price</th>
                                        <th v-if="form.cost_allocation_method === 'percentage'" class="text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase pb-2 w-20">Cost %</th>
                                        <th class="text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase pb-2">Condition</th>
                                        <th class="text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase pb-2">Notes</th>
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
                                            <input v-model="item.manufacturer" type="text" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm" placeholder="Mfr" />
                                        </td>
                                        <td class="py-2 pr-2">
                                            <input v-model="item.category" type="text" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm" placeholder="Category" />
                                        </td>
                                        <td class="py-2 pr-2">
                                            <input v-model.number="item.quantity" type="number" min="1" class="w-full text-right rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm" />
                                        </td>
                                        <td class="py-2 pr-2">
                                            <input v-model.number="item.suggested_selling_price" type="number" min="0" step="0.01" class="w-full text-right rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm" placeholder="0.00" />
                                        </td>
                                        <td v-if="form.cost_allocation_method === 'percentage'" class="py-2 pr-2">
                                            <input v-model.number="item.cost_allocation_pct" type="number" min="0" max="100" step="0.01" class="w-full text-right rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm" placeholder="%" />
                                        </td>
                                        <td class="py-2 pr-2">
                                            <select v-model="item.condition" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm">
                                                <option value="new">New</option>
                                                <option value="like_new">Like New</option>
                                                <option value="used">Used</option>
                                                <option value="refurbished">Refurbished</option>
                                                <option value="for_parts">For Parts</option>
                                            </select>
                                        </td>
                                        <td class="py-2 pr-2">
                                            <input v-model="item.notes" type="text" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm" placeholder="Notes" />
                                        </td>
                                        <td class="py-2 text-center">
                                            <button type="button" @click="removeItem(idx)" class="text-red-500 hover:text-red-700"><i class="fas fa-times"></i></button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div v-else class="text-center py-8 text-gray-500 dark:text-gray-400 text-sm">
                            Add component items or auto-populate from a product or template.
                        </div>
                    </div>

                    <!-- Cost Allocation Preview -->
                    <div v-if="form.items.length > 0 && selectedProduct" class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Cost Allocation Preview</h3>

                        <div class="overflow-x-auto mb-4">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead>
                                    <tr>
                                        <th class="text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase pb-2">Component Name</th>
                                        <th class="text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase pb-2">Qty</th>
                                        <th class="text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase pb-2">Market Price</th>
                                        <th class="text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase pb-2">Allocated Cost</th>
                                        <th class="text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase pb-2">Cost/Unit</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                    <tr v-for="(item, idx) in costPreviewItems" :key="idx">
                                        <td class="py-2 text-sm text-gray-900 dark:text-gray-100">{{ item.name }}</td>
                                        <td class="py-2 text-sm text-right text-gray-600 dark:text-gray-400">{{ item.quantity }}</td>
                                        <td class="py-2 text-sm text-right text-gray-600 dark:text-gray-400">{{ formatCurrency(item.marketPrice) }}</td>
                                        <td class="py-2 text-sm text-right text-gray-900 dark:text-gray-100">{{ formatCurrency(item.allocatedCost) }}</td>
                                        <td class="py-2 text-sm text-right text-gray-600 dark:text-gray-400">{{ formatCurrency(item.costPerUnit) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Totals -->
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 pt-4 border-t dark:border-gray-700">
                            <div>
                                <div class="text-sm text-gray-500 dark:text-gray-400">Source Cost</div>
                                <div class="text-lg font-bold text-gray-900 dark:text-white">{{ formatCurrency(sourceCost) }}</div>
                            </div>
                            <div v-if="form.parent_continues">
                                <div class="text-sm text-gray-500 dark:text-gray-400">Remaining Parent Cost</div>
                                <div class="text-lg font-bold text-gray-900 dark:text-white">{{ formatCurrency(remainingParentCost) }}</div>
                            </div>
                            <div>
                                <div class="text-sm text-gray-500 dark:text-gray-400">Total Allocated</div>
                                <div class="text-lg font-bold text-gray-900 dark:text-white">{{ formatCurrency(totalAllocated) }}</div>
                            </div>
                            <div>
                                <div class="text-sm text-gray-500 dark:text-gray-400">Unallocated</div>
                                <div class="text-lg font-bold" :class="unallocated > 0.01 ? 'text-amber-600' : unallocated < -0.01 ? 'text-red-600' : 'text-green-600'">{{ formatCurrency(unallocated) }}</div>
                            </div>
                        </div>

                        <!-- Profit Projection -->
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 pt-4 mt-4 border-t dark:border-gray-700">
                            <div>
                                <div class="text-sm text-gray-500 dark:text-gray-400">Total Component Value</div>
                                <div class="text-lg font-bold text-gray-900 dark:text-white">{{ formatCurrency(totalComponentValue) }}</div>
                            </div>
                            <div>
                                <div class="text-sm text-gray-500 dark:text-gray-400">Total Cost</div>
                                <div class="text-lg font-bold text-gray-900 dark:text-white">{{ formatCurrency(sourceCost) }}</div>
                            </div>
                            <div>
                                <div class="text-sm text-gray-500 dark:text-gray-400">Projected Profit</div>
                                <div class="text-lg font-bold" :class="projectedProfit >= 0 ? 'text-green-600' : 'text-red-600'">{{ formatCurrency(projectedProfit) }}</div>
                            </div>
                            <div>
                                <div class="text-sm text-gray-500 dark:text-gray-400">ROI</div>
                                <div class="text-lg font-bold" :class="roi >= 0 ? 'text-green-600' : 'text-red-600'">{{ roi.toFixed(1) }}%</div>
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end gap-3">
                        <Link :href="route('inventory.part-out.work-orders.index')" class="inline-flex items-center px-4 py-2 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md font-semibold text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest hover:bg-gray-50 dark:hover:bg-gray-600 transition">
                            Cancel
                        </Link>
                        <button type="submit" :disabled="!form.source_product_id || form.items.length === 0" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-500 transition disabled:opacity-50">
                            Create Work Order
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
