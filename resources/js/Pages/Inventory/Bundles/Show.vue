<script setup>
import { ref } from 'vue'
import { router, Head, Link } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { route } from 'ziggy-js'

const props = defineProps({
    bundle: { type: Object, required: true },
    availableQuantity: { type: Number, default: 0 },
})

const formatCurrency = (value) => {
    return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(value || 0)
}

function toggleActive() {
    router.put(route('inventory.bundles.update', props.bundle.id), {
        name: props.bundle.name,
        description: props.bundle.description,
        is_active: !props.bundle.is_active,
    }, { preserveScroll: true })
}

function removeComponent(item) {
    if (confirm('Remove this component from the bundle?')) {
        router.delete(route('inventory.bundles.remove-component', [props.bundle.id, item.id]), { preserveScroll: true })
    }
}

function deleteBundle() {
    if (confirm('Delete this bundle? The underlying products will not be deleted.')) {
        router.delete(route('inventory.bundles.destroy', props.bundle.id))
    }
}
</script>

<template>
    <Head :title="`Bundle: ${bundle.name}`" />

    <AppLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <Link :href="route('inventory.bundles.index')" class="text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200">
                        <i class="fas fa-arrow-left"></i>
                    </Link>
                    <div>
                        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200">{{ bundle.name }}</h2>
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ bundle.product?.sku }} | {{ bundle.bundle_type === 'fixed' ? 'Fixed Bundle' : `Pick ${bundle.pick_n_quantity}` }}</p>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <button @click="toggleActive" :class="bundle.is_active ? 'bg-amber-600 hover:bg-amber-500' : 'bg-emerald-600 hover:bg-emerald-500'" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest transition">
                        {{ bundle.is_active ? 'Deactivate' : 'Activate' }}
                    </button>
                    <button @click="deleteBundle" class="inline-flex items-center px-4 py-2 bg-red-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-red-500 transition">
                        Delete
                    </button>
                </div>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <!-- Bundle Info -->
                <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6 mb-6">
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                        <div>
                            <div class="text-sm text-gray-500 dark:text-gray-400">Bundle Product</div>
                            <div class="font-medium text-gray-900 dark:text-gray-100">{{ bundle.product?.name }}</div>
                        </div>
                        <div>
                            <div class="text-sm text-gray-500 dark:text-gray-400">Selling Price</div>
                            <div class="font-medium text-gray-900 dark:text-gray-100">{{ formatCurrency(bundle.product?.selling_price) }}</div>
                        </div>
                        <div>
                            <div class="text-sm text-gray-500 dark:text-gray-400">Available Quantity</div>
                            <div class="text-2xl font-bold" :class="availableQuantity > 0 ? 'text-emerald-600' : 'text-red-600'">{{ availableQuantity }}</div>
                        </div>
                        <div>
                            <div class="text-sm text-gray-500 dark:text-gray-400">Status</div>
                            <span :class="bundle.is_active ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900 dark:text-emerald-300' : 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300'"
                                class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium mt-1">
                                {{ bundle.is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </div>
                    </div>
                    <div v-if="bundle.description" class="mt-4 pt-4 border-t dark:border-gray-700 text-sm text-gray-600 dark:text-gray-400">
                        {{ bundle.description }}
                    </div>
                </div>

                <!-- Components -->
                <div class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden">
                    <div class="px-6 py-4 border-b dark:border-gray-700">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Components ({{ bundle.items?.length ?? 0 }})</h3>
                    </div>
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Product</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Qty per Bundle</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Stock Available</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Bundles Possible</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Cost</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            <tr v-for="item in bundle.items" :key="item.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                <td class="px-6 py-4 text-sm">
                                    <div class="text-gray-900 dark:text-gray-100">{{ item.component_product?.name || '-' }}</div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400">{{ item.component_product?.sku }}</div>
                                </td>
                                <td class="px-6 py-4 text-sm text-right text-gray-900 dark:text-gray-100">{{ item.quantity }}</td>
                                <td class="px-6 py-4 text-sm text-right text-gray-600 dark:text-gray-400">{{ item.component_product?.available_quantity ?? 0 }}</td>
                                <td class="px-6 py-4 text-sm text-right text-gray-600 dark:text-gray-400">
                                    {{ item.quantity > 0 ? Math.floor((item.component_product?.available_quantity ?? 0) / item.quantity) : 0 }}
                                </td>
                                <td class="px-6 py-4 text-sm text-right text-gray-600 dark:text-gray-400">{{ formatCurrency(item.component_product?.cost_price) }}</td>
                                <td class="px-6 py-4 text-sm text-right">
                                    <button @click="removeComponent(item)" class="text-red-600 dark:text-red-400 hover:underline">Remove</button>
                                </td>
                            </tr>
                            <tr v-if="!bundle.items?.length">
                                <td colspan="6" class="px-6 py-12 text-center text-gray-500 dark:text-gray-400">
                                    No components in this bundle.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
