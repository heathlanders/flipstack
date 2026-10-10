<script setup>
import { ref, computed } from 'vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { route } from 'ziggy-js'

const props = defineProps({
    purchaseOrder: { type: Object, required: true },
    locations: { type: Array, default: () => [] },
})

const po = props.purchaseOrder

const form = useForm({
    location_id: po.location?.id || '',
    notes: '',
    items: po.items
        .filter(item => item.quantity_received < item.quantity_ordered)
        .map(item => ({
            purchase_order_item_id: item.id,
            quantity_received: item.quantity_ordered - item.quantity_received,
            condition: 'good',
            condition_notes: '',
            _description: item.description || item.product?.name || '-',
            _sku: item.sku || item.product?.sku || '-',
            _remaining: item.quantity_ordered - item.quantity_received,
        })),
})

const formatCurrency = (value) => {
    return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(value || 0)
}

const totalReceiving = computed(() => {
    return form.items.reduce((sum, item) => sum + (item.quantity_received || 0), 0)
})

function submit() {
    form.post(route('procurement.purchase-orders.process-receive', po.id))
}
</script>

<template>
    <Head :title="`Receive - ${po.po_number}`" />

    <AppLayout>
        <template #header>
            <div class="flex items-center gap-4">
                <Link :href="route('procurement.purchase-orders.show', po.id)" class="text-gray-500 dark:text-gray-400 hover:text-gray-700">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                </Link>
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200">Receive Items - {{ po.po_number }}</h2>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
                <form @submit.prevent="submit" class="space-y-6">
                    <!-- Receiving Location -->
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
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
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Notes</label>
                                <input
                                    v-model="form.notes"
                                    type="text"
                                    placeholder="Receipt notes..."
                                    class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-sm"
                                />
                            </div>
                        </div>
                    </div>

                    <!-- Items to Receive -->
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden">
                        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-white">Items to Receive</h3>
                        </div>

                        <div v-if="form.items.length" class="divide-y divide-gray-200 dark:divide-gray-700">
                            <div
                                v-for="(item, index) in form.items"
                                :key="index"
                                class="px-6 py-4"
                            >
                                <div class="flex items-center justify-between mb-3">
                                    <div>
                                        <p class="text-sm font-medium text-gray-900 dark:text-white">{{ item._description }}</p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">SKU: {{ item._sku }} | Remaining: {{ item._remaining }}</p>
                                    </div>
                                </div>
                                <div class="grid grid-cols-3 gap-4">
                                    <div>
                                        <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Quantity</label>
                                        <input
                                            v-model.number="item.quantity_received"
                                            type="number"
                                            min="0"
                                            :max="item._remaining"
                                            class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-sm"
                                        />
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Condition</label>
                                        <select
                                            v-model="item.condition"
                                            class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-sm"
                                        >
                                            <option value="good">Good</option>
                                            <option value="damaged">Damaged</option>
                                            <option value="defective">Defective</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Notes</label>
                                        <input
                                            v-model="item.condition_notes"
                                            type="text"
                                            placeholder="Optional..."
                                            class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-sm"
                                        />
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div v-else class="px-6 py-12 text-center text-gray-500 dark:text-gray-400">
                            All items have been fully received.
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="flex items-center justify-between">
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            Total items to receive: <span class="font-medium text-gray-900 dark:text-white">{{ totalReceiving }}</span>
                        </p>
                        <div class="flex gap-3">
                            <Link
                                :href="route('procurement.purchase-orders.show', po.id)"
                                class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50"
                            >
                                Cancel
                            </Link>
                            <button
                                type="submit"
                                :disabled="form.processing || !form.items.length"
                                class="px-4 py-2 text-sm font-medium text-white bg-emerald-600 rounded-md hover:bg-emerald-500 disabled:opacity-50"
                            >
                                Confirm Receipt
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
