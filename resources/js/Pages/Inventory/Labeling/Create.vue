<script setup>
import { ref, computed } from 'vue'
import { router, Head, Link, useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { route } from 'ziggy-js'

const props = defineProps({
    products: { type: Array, default: () => [] },
    locations: { type: Array, default: () => [] },
})

const form = useForm({
    product_ids: [],
    location_id: '',
    priority: 'standard',
    notes: '',
})

const searchQuery = ref('')

const filteredProducts = computed(() => {
    if (!searchQuery.value) return props.products
    const q = searchQuery.value.toLowerCase()
    return props.products.filter(p =>
        p.name.toLowerCase().includes(q) || p.sku.toLowerCase().includes(q)
    )
})

const selectedProducts = computed(() =>
    props.products.filter(p => form.product_ids.includes(p.id))
)

function toggleProduct(productId) {
    const idx = form.product_ids.indexOf(productId)
    if (idx >= 0) {
        form.product_ids.splice(idx, 1)
    } else {
        form.product_ids.push(productId)
    }
}

function removeProduct(productId) {
    const idx = form.product_ids.indexOf(productId)
    if (idx >= 0) form.product_ids.splice(idx, 1)
}

function submit() {
    form.post(route('inventory.labeling.store'))
}
</script>

<template>
    <Head title="Create Labeling Work Order" />

    <AppLayout>
        <template #header>
            <div class="flex items-center gap-3">
                <Link :href="route('inventory.labeling.index')" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                    <i class="fas fa-arrow-left"></i>
                </Link>
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200">Create Labeling Work Order</h2>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
                <form @submit.prevent="submit" class="space-y-6">
                    <!-- Location -->
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                        <h3 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-4">Work Order Details</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Location *</label>
                                <select
                                    v-model="form.location_id"
                                    class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 text-sm"
                                    required
                                >
                                    <option value="">Select location...</option>
                                    <option v-for="loc in locations" :key="loc.id" :value="loc.id">{{ loc.name }} ({{ loc.code }})</option>
                                </select>
                                <p v-if="form.errors.location_id" class="text-sm text-red-500 mt-1">{{ form.errors.location_id }}</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Priority</label>
                                <select
                                    v-model="form.priority"
                                    class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 text-sm"
                                >
                                    <option value="standard">Standard</option>
                                    <option value="high">High</option>
                                    <option value="urgent">Urgent</option>
                                </select>
                            </div>
                        </div>
                        <div class="mt-4">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Notes</label>
                            <textarea
                                v-model="form.notes"
                                rows="2"
                                class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 text-sm"
                                placeholder="Optional notes..."
                            ></textarea>
                        </div>
                    </div>

                    <!-- Product Selection -->
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                        <h3 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-4">
                            Select Products ({{ form.product_ids.length }} selected)
                        </h3>
                        <p v-if="form.errors.product_ids" class="text-sm text-red-500 mb-3">{{ form.errors.product_ids }}</p>

                        <!-- Selected products pills -->
                        <div v-if="selectedProducts.length > 0" class="flex flex-wrap gap-2 mb-4">
                            <span
                                v-for="product in selectedProducts"
                                :key="product.id"
                                class="inline-flex items-center gap-1 px-2 py-1 bg-indigo-100 dark:bg-indigo-900 text-indigo-800 dark:text-indigo-200 rounded-full text-xs"
                            >
                                {{ product.sku }} - {{ product.name.substring(0, 30) }}
                                <button @click="removeProduct(product.id)" class="ml-1 text-indigo-500 hover:text-indigo-700">&times;</button>
                            </span>
                        </div>

                        <!-- Search -->
                        <input
                            v-model="searchQuery"
                            type="text"
                            placeholder="Search products by name or SKU..."
                            class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 text-sm mb-3"
                        />

                        <!-- Product list -->
                        <div class="max-h-64 overflow-y-auto border border-gray-200 dark:border-gray-700 rounded-md">
                            <div
                                v-for="product in filteredProducts"
                                :key="product.id"
                                @click="toggleProduct(product.id)"
                                class="flex items-center gap-3 px-3 py-2 cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-750 border-b border-gray-100 dark:border-gray-700 last:border-0"
                                :class="{ 'bg-indigo-50 dark:bg-indigo-900/20': form.product_ids.includes(product.id) }"
                            >
                                <input
                                    type="checkbox"
                                    :checked="form.product_ids.includes(product.id)"
                                    class="rounded border-gray-300 dark:border-gray-600 text-indigo-600"
                                    @click.stop
                                    @change="toggleProduct(product.id)"
                                />
                                <div class="flex-1 min-w-0">
                                    <div class="text-sm font-medium text-gray-800 dark:text-gray-200 truncate">{{ product.name }}</div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400 font-mono">{{ product.sku }}</div>
                                </div>
                                <span v-if="product.labeling_status" class="text-xs text-gray-400">{{ product.labeling_status }}</span>
                            </div>
                            <div v-if="filteredProducts.length === 0" class="px-3 py-4 text-center text-sm text-gray-500 dark:text-gray-400">
                                No products found.
                            </div>
                        </div>
                    </div>

                    <!-- Submit -->
                    <div class="flex items-center justify-end gap-3">
                        <Link :href="route('inventory.labeling.index')" class="text-sm text-gray-500 dark:text-gray-400 hover:underline">Cancel</Link>
                        <button
                            type="submit"
                            :disabled="form.processing || form.product_ids.length === 0 || !form.location_id"
                            class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-500 disabled:opacity-50 transition"
                        >
                            Create Work Order
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
