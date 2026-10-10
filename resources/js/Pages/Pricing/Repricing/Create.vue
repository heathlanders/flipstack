<script setup>
import { reactive } from 'vue'
import { router, Head, Link } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { route } from 'ziggy-js'

const props = defineProps({
    strategies: { type: Array, default: () => [] },
})

const form = reactive({
    name: '',
    marketplace: '*',
    scope_type: 'all',
    scope_ids: [],
    strategy: 'cost_plus',
    parameters: {
        min_price: null,
        max_price: null,
        beat_amount: 1,
        beat_percent: 5,
        cost_plus_margin: 30,
    },
    priority: 1,
    is_active: true,
})

const isSubmitting = false

function submit() {
    router.post(route('pricing.repricing.store'), form)
}
</script>

<template>
    <Head title="Create Repricing Rule" />

    <AppLayout>
        <template #header>
            <div class="flex items-center gap-4">
                <Link :href="route('pricing.repricing.index')" class="text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200">
                    <i class="fas fa-arrow-left"></i>
                </Link>
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200">Create Repricing Rule</h2>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
                <form @submit.prevent="submit" class="bg-white dark:bg-gray-800 shadow rounded-lg p-6 space-y-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Rule Name</label>
                        <input v-model="form.name" type="text" required class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Marketplace</label>
                            <select v-model="form.marketplace" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="*">All Marketplaces</option>
                                <option value="ebay">eBay</option>
                                <option value="shopify">Shopify</option>
                                <option value="amazon">Amazon</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Scope</label>
                            <select v-model="form.scope_type" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="all">All Products</option>
                                <option value="category">By Category</option>
                                <option value="product">Specific Products</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Strategy</label>
                        <div class="space-y-2">
                            <label v-for="strategy in strategies" :key="strategy.value" class="flex items-start p-3 border rounded-lg cursor-pointer" :class="form.strategy === strategy.value ? 'border-indigo-500 bg-indigo-50 dark:bg-indigo-900/20' : 'border-gray-200 dark:border-gray-600'">
                                <input type="radio" v-model="form.strategy" :value="strategy.value" class="mt-0.5 text-indigo-600" />
                                <div class="ml-3">
                                    <div class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ strategy.label }}</div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400">{{ strategy.description }}</div>
                                </div>
                            </label>
                        </div>
                    </div>

                    <!-- Strategy-specific parameters -->
                    <div v-if="form.strategy === 'cost_plus'" class="p-4 bg-gray-50 dark:bg-gray-700/50 rounded-lg">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Margin Percentage</label>
                        <div class="mt-1 flex items-center gap-2">
                            <input v-model.number="form.parameters.cost_plus_margin" type="number" min="0" step="1" class="block w-32 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                            <span class="text-gray-500 dark:text-gray-400">%</span>
                        </div>
                    </div>

                    <div v-if="form.strategy === 'beat_by_amount'" class="p-4 bg-gray-50 dark:bg-gray-700/50 rounded-lg">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Beat Amount</label>
                        <div class="mt-1 flex items-center gap-2">
                            <span class="text-gray-500 dark:text-gray-400">$</span>
                            <input v-model.number="form.parameters.beat_amount" type="number" min="0" step="0.01" class="block w-32 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                        </div>
                    </div>

                    <div v-if="form.strategy === 'beat_by_percent'" class="p-4 bg-gray-50 dark:bg-gray-700/50 rounded-lg">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Beat Percentage</label>
                        <div class="mt-1 flex items-center gap-2">
                            <input v-model.number="form.parameters.beat_percent" type="number" min="0" max="100" step="0.5" class="block w-32 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                            <span class="text-gray-500 dark:text-gray-400">%</span>
                        </div>
                    </div>

                    <!-- Price Guards -->
                    <div class="p-4 bg-gray-50 dark:bg-gray-700/50 rounded-lg">
                        <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">Price Guards</h4>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs text-gray-500 dark:text-gray-400">Minimum Price</label>
                                <input v-model.number="form.parameters.min_price" type="number" min="0" step="0.01" placeholder="No minimum" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 dark:text-gray-400">Maximum Price</label>
                                <input v-model.number="form.parameters.max_price" type="number" min="0" step="0.01" placeholder="No maximum" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Priority</label>
                            <input v-model.number="form.priority" type="number" min="1" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Lower number = higher priority</p>
                        </div>
                        <div class="flex items-center pt-6">
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input v-model="form.is_active" type="checkbox" class="rounded border-gray-300 dark:border-gray-600 text-indigo-600 focus:ring-indigo-500" />
                                <span class="text-sm text-gray-700 dark:text-gray-300">Active immediately</span>
                            </label>
                        </div>
                    </div>

                    <div class="flex justify-end gap-3 pt-4 border-t dark:border-gray-700">
                        <Link :href="route('pricing.repricing.index')" class="inline-flex items-center px-4 py-2 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md font-semibold text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest hover:bg-gray-50 dark:hover:bg-gray-600 transition">
                            Cancel
                        </Link>
                        <button type="submit" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-500 transition">
                            Create Rule
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
