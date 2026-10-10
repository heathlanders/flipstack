<template>
    <AppLayout title="Adjust Stock">
        <template #header>
            <div class="flex items-center space-x-4">
                <Link :href="route('inventory.operations.levels')" class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">
                    <i class="fas fa-arrow-left"></i>
                </Link>
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Stock Adjustment</h2>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm">
                    <form @submit.prevent="submit" class="p-6 space-y-5">
                        <div class="bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-700 rounded-lg p-3 text-sm text-amber-800 dark:text-amber-300">
                            <i class="fas fa-info-circle mr-1.5"></i>
                            Use positive quantity to add stock, negative to remove. Adjustments are logged as movements.
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Product <span class="text-red-500">*</span>
                            </label>
                            <select
                                v-model="form.product_id"
                                required
                                class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                :class="{ 'border-red-500': form.errors.product_id }"
                            >
                                <option value="">— Select Product —</option>
                                <option v-for="p in products" :key="p.id" :value="p.id">
                                    {{ p.name }} ({{ p.sku }})
                                </option>
                            </select>
                            <p v-if="form.errors.product_id" class="mt-1 text-sm text-red-500">{{ form.errors.product_id }}</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Location <span class="text-red-500">*</span>
                            </label>
                            <select
                                v-model="form.location_id"
                                required
                                class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                :class="{ 'border-red-500': form.errors.location_id }"
                            >
                                <option value="">— Select Location —</option>
                                <option v-for="l in locations" :key="l.id" :value="l.id">
                                    {{ l.name }} <span v-if="l.code">({{ l.code }})</span>
                                </option>
                            </select>
                            <p v-if="form.errors.location_id" class="mt-1 text-sm text-red-500">{{ form.errors.location_id }}</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Quantity Change <span class="text-red-500">*</span>
                            </label>
                            <div class="flex items-center space-x-2">
                                <button type="button" @click="form.quantity = -Math.abs(form.quantity || 1)" class="px-3 py-2 bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-300 rounded-md hover:bg-red-200 dark:hover:bg-red-900/50 text-sm font-medium">
                                    <i class="fas fa-minus mr-1"></i>Remove
                                </button>
                                <input
                                    v-model.number="form.quantity"
                                    type="number"
                                    required
                                    placeholder="0"
                                    class="flex-1 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-center text-lg font-semibold"
                                    :class="{ 'border-red-500': form.errors.quantity }"
                                />
                                <button type="button" @click="form.quantity = Math.abs(form.quantity || 1)" class="px-3 py-2 bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-300 rounded-md hover:bg-green-200 dark:hover:bg-green-900/50 text-sm font-medium">
                                    <i class="fas fa-plus mr-1"></i>Add
                                </button>
                            </div>
                            <p v-if="form.errors.quantity" class="mt-1 text-sm text-red-500">{{ form.errors.quantity }}</p>
                            <p v-else class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                Cannot be zero. Negative removes stock.
                            </p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Reason <span class="text-red-500">*</span>
                            </label>
                            <select
                                v-model="form.reason"
                                required
                                class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                :class="{ 'border-red-500': form.errors.reason }"
                            >
                                <option value="">— Select Reason —</option>
                                <option value="Cycle count adjustment">Cycle count adjustment</option>
                                <option value="Damaged goods">Damaged goods</option>
                                <option value="Lost / shrinkage">Lost / shrinkage</option>
                                <option value="Found / recovered">Found / recovered</option>
                                <option value="Returned to supplier">Returned to supplier</option>
                                <option value="Manual correction">Manual correction</option>
                                <option value="Other">Other</option>
                            </select>
                            <p v-if="form.errors.reason" class="mt-1 text-sm text-red-500">{{ form.errors.reason }}</p>
                        </div>

                        <div class="flex justify-end space-x-3 border-t border-gray-200 dark:border-gray-700 pt-5">
                            <Link
                                :href="route('inventory.operations.levels')"
                                class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-gray-600"
                            >
                                Cancel
                            </Link>
                            <button
                                type="submit"
                                :disabled="form.processing || !form.quantity"
                                class="px-4 py-2 text-sm font-medium text-white bg-blue-600 border border-transparent rounded-md hover:bg-blue-700 disabled:opacity-50"
                            >
                                <span v-if="form.processing"><i class="fas fa-spinner fa-spin mr-2"></i>Submitting...</span>
                                <span v-else>Apply Adjustment</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { useForm, Link } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

defineProps({
    products: { type: Array, default: () => [] },
    locations: { type: Array, default: () => [] },
})

const form = useForm({
    product_id: '',
    location_id: '',
    quantity: null,
    reason: '',
})

const submit = () => {
    form.post(route('inventory.operations.adjust.store'))
}
</script>
