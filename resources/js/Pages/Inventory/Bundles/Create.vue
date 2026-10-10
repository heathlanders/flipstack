<script setup>
import { ref, reactive, computed } from 'vue'
import { router, Head, Link } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { route } from 'ziggy-js'

const props = defineProps({
    products: { type: Array, default: () => [] },
})

const form = reactive({
    product_id: '',
    name: '',
    description: '',
    bundle_type: 'fixed',
    pick_n_quantity: null,
    components: [],
})

const productSearch = ref('')

const filteredProducts = computed(() => {
    if (!productSearch.value) return []
    const search = productSearch.value.toLowerCase()
    return props.products
        .filter(p => (p.name.toLowerCase().includes(search) || p.sku?.toLowerCase().includes(search)) && !form.components.find(c => c.product_id === p.id) && p.id !== form.product_id)
        .slice(0, 20)
})

const selectedBundleProduct = computed(() => props.products.find(p => p.id === form.product_id))

function addComponent(product) {
    form.components.push({
        product_id: product.id,
        name: product.name,
        sku: product.sku,
        available_quantity: product.available_quantity,
        quantity: 1,
        is_required: true,
    })
    productSearch.value = ''
}

function removeComponent(index) {
    form.components.splice(index, 1)
}

function submit() {
    router.post(route('inventory.bundles.store'), form)
}

const formatCurrency = (value) => {
    return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(value || 0)
}
</script>

<template>
    <Head title="Create Bundle" />

    <AppLayout>
        <template #header>
            <div class="flex items-center gap-4">
                <Link :href="route('inventory.bundles.index')" class="text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200">
                    <i class="fas fa-arrow-left"></i>
                </Link>
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200">Create Product Bundle</h2>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
                <form @submit.prevent="submit" class="space-y-6">
                    <!-- Bundle Product -->
                    <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6 space-y-4">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Bundle Product</h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Select the product that represents this bundle in listings.</p>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Bundle Product</label>
                            <select v-model="form.product_id" required class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">Select a product...</option>
                                <option v-for="product in products" :key="product.id" :value="product.id">{{ product.sku }} - {{ product.name }}</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Bundle Name</label>
                            <input v-model="form.name" type="text" required class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500" :placeholder="selectedBundleProduct?.name || 'Bundle name'" />
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Description</label>
                            <textarea v-model="form.description" rows="2" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500"></textarea>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Bundle Type</label>
                                <select v-model="form.bundle_type" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <option value="fixed">Fixed (all components included)</option>
                                    <option value="pick_n">Pick N (buyer chooses)</option>
                                </select>
                            </div>
                            <div v-if="form.bundle_type === 'pick_n'">
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Pick Quantity</label>
                                <input v-model.number="form.pick_n_quantity" type="number" min="1" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                            </div>
                        </div>
                    </div>

                    <!-- Components -->
                    <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Components</h3>

                        <div class="relative mb-4">
                            <input v-model="productSearch" type="text" placeholder="Search products to add as components..." class="block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                            <div v-if="filteredProducts.length" class="absolute z-10 mt-1 w-full bg-white dark:bg-gray-700 rounded-md shadow-lg border dark:border-gray-600 max-h-48 overflow-y-auto">
                                <button v-for="product in filteredProducts" :key="product.id" type="button" @click="addComponent(product)" class="w-full text-left px-4 py-2 text-sm hover:bg-gray-100 dark:hover:bg-gray-600 text-gray-900 dark:text-gray-100">
                                    <span class="font-medium">{{ product.sku }}</span> - {{ product.name }}
                                    <span class="text-gray-500 dark:text-gray-400 ml-2">({{ product.available_quantity }} avail)</span>
                                </button>
                            </div>
                        </div>

                        <table v-if="form.components.length" class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead>
                                <tr>
                                    <th class="text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase pb-2">Product</th>
                                    <th class="text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase pb-2">Available</th>
                                    <th class="text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase pb-2 w-28">Qty per Bundle</th>
                                    <th class="w-10 pb-2"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                <tr v-for="(comp, idx) in form.components" :key="comp.product_id">
                                    <td class="py-2 text-sm text-gray-900 dark:text-gray-100">
                                        <span class="text-gray-500 dark:text-gray-400">{{ comp.sku }}</span> - {{ comp.name }}
                                    </td>
                                    <td class="py-2 text-sm text-right text-gray-500 dark:text-gray-400">{{ comp.available_quantity }}</td>
                                    <td class="py-2">
                                        <input v-model.number="comp.quantity" type="number" min="1" class="w-full text-right rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm" />
                                    </td>
                                    <td class="py-2 text-center">
                                        <button type="button" @click="removeComponent(idx)" class="text-red-500 hover:text-red-700"><i class="fas fa-times"></i></button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                        <div v-else class="text-center py-8 text-gray-500 dark:text-gray-400 text-sm">
                            Add at least one component product to the bundle.
                        </div>
                    </div>

                    <div class="flex justify-end gap-3">
                        <Link :href="route('inventory.bundles.index')" class="inline-flex items-center px-4 py-2 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md font-semibold text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest hover:bg-gray-50 dark:hover:bg-gray-600 transition">
                            Cancel
                        </Link>
                        <button type="submit" :disabled="!form.product_id || form.components.length === 0" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-500 transition disabled:opacity-50">
                            Create Bundle
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
