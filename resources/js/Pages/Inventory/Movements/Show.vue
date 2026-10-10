<template>
    <AppLayout :title="`Movement ${m.id}`">
        <template #header>
            <div class="flex items-center space-x-4">
                <Link :href="route('inventory.movements.index')" class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">
                    <i class="fas fa-arrow-left"></i>
                </Link>
                <div>
                    <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Stock Movement</h2>
                    <p class="text-xs text-gray-500 dark:text-gray-400 font-mono">#{{ m.id }}</p>
                </div>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">
                <!-- Summary -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6">
                    <div class="flex items-center justify-between mb-4">
                        <div class="flex items-center space-x-3">
                            <span
                                class="inline-flex items-center justify-center h-12 w-12 rounded-lg text-white text-xl"
                                :class="iconBg"
                            >
                                <i :class="iconClass"></i>
                            </span>
                            <div>
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">{{ m.type_label }}</h3>
                                <p class="text-sm text-gray-500 dark:text-gray-400">{{ formatDate(m.created_at) }}</p>
                            </div>
                        </div>
                        <div class="text-right">
                            <div class="text-3xl font-semibold" :class="m.is_stock_decrease ? 'text-red-600 dark:text-red-400' : 'text-green-600 dark:text-green-400'">
                                {{ m.is_stock_decrease ? '-' : '+' }}{{ Math.abs(m.net_quantity_change || m.quantity) }}
                            </div>
                            <div class="text-xs text-gray-500 dark:text-gray-400">Net change</div>
                        </div>
                    </div>

                    <dl class="grid grid-cols-1 md:grid-cols-3 gap-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                        <div>
                            <dt class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Balance Before</dt>
                            <dd class="mt-1 text-lg font-semibold text-gray-900 dark:text-white">{{ m.balance_before ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Quantity</dt>
                            <dd class="mt-1 text-lg font-semibold text-gray-900 dark:text-white">{{ m.quantity }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Balance After</dt>
                            <dd class="mt-1 text-lg font-semibold text-gray-900 dark:text-white">{{ m.balance_after ?? '—' }}</dd>
                        </div>
                    </dl>
                </div>

                <!-- Product + Location -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6">
                        <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wider mb-3">Product</h3>
                        <div v-if="m.product">
                            <Link :href="route('inventory.products.show', m.product.id)" class="text-base font-medium text-blue-600 dark:text-blue-400 hover:underline">
                                {{ m.product.name }}
                            </Link>
                            <div class="text-sm font-mono text-gray-500 dark:text-gray-400 mt-1">{{ m.product.sku }}</div>
                            <div class="text-sm text-gray-700 dark:text-gray-300 mt-2">Current stock: {{ m.product.stock_quantity }}</div>
                        </div>
                        <div v-else class="text-sm text-gray-400">—</div>
                    </div>
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6">
                        <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wider mb-3">Location</h3>
                        <div v-if="m.location">
                            <Link :href="route('inventory.locations.show', m.location.id)" class="text-base font-medium text-blue-600 dark:text-blue-400 hover:underline">
                                {{ m.location.name }}
                            </Link>
                            <div class="text-sm text-gray-500 dark:text-gray-400 capitalize mt-1">{{ m.location.type }}</div>
                        </div>
                        <div v-else class="text-sm text-gray-400">—</div>
                    </div>
                </div>

                <!-- Reference + Cost -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Reference & Cost</h3>
                    <dl class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
                        <div>
                            <dt class="text-gray-500 dark:text-gray-400">Reference Type</dt>
                            <dd class="text-gray-900 dark:text-white">{{ m.reference_type || '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500 dark:text-gray-400">Reference Number</dt>
                            <dd class="text-gray-900 dark:text-white font-mono">{{ m.reference_number || '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500 dark:text-gray-400">Reason</dt>
                            <dd class="text-gray-900 dark:text-white">{{ m.reason || '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500 dark:text-gray-400">Unit Cost</dt>
                            <dd class="text-gray-900 dark:text-white">{{ m.unit_cost ? `$${m.unit_cost}` : '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500 dark:text-gray-400">Total Cost</dt>
                            <dd class="text-gray-900 dark:text-white font-semibold">{{ m.total_cost ? `$${m.total_cost}` : '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500 dark:text-gray-400">Created By</dt>
                            <dd class="text-gray-900 dark:text-white">{{ m.created_by?.name || '—' }}</dd>
                        </div>
                    </dl>
                </div>

                <!-- Batch / Lot info -->
                <div v-if="m.batch_number || m.lot_number || m.expiry_date" class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Batch / Lot</h3>
                    <dl class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
                        <div v-if="m.batch_number">
                            <dt class="text-gray-500 dark:text-gray-400">Batch</dt>
                            <dd class="text-gray-900 dark:text-white font-mono">{{ m.batch_number }}</dd>
                        </div>
                        <div v-if="m.lot_number">
                            <dt class="text-gray-500 dark:text-gray-400">Lot</dt>
                            <dd class="text-gray-900 dark:text-white font-mono">{{ m.lot_number }}</dd>
                        </div>
                        <div v-if="m.expiry_date">
                            <dt class="text-gray-500 dark:text-gray-400">Expires</dt>
                            <dd class="text-gray-900 dark:text-white">{{ m.expiry_date }}</dd>
                        </div>
                    </dl>
                </div>

                <!-- Notes -->
                <div v-if="m.notes" class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">Notes</h3>
                    <p class="text-sm text-gray-700 dark:text-gray-300 whitespace-pre-wrap">{{ m.notes }}</p>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

const props = defineProps({
    movement: { type: Object, required: true },
})

const m = computed(() => props.movement.data || props.movement)

const iconBg = computed(() => {
    if (m.value.is_stock_increase) return 'bg-green-500'
    if (m.value.is_stock_decrease) return 'bg-red-500'
    if (m.value.is_transfer) return 'bg-blue-500'
    return 'bg-gray-500'
})

const iconClass = computed(() => {
    if (m.value.is_stock_increase) return 'fas fa-arrow-up'
    if (m.value.is_stock_decrease) return 'fas fa-arrow-down'
    if (m.value.is_transfer) return 'fas fa-exchange-alt'
    return 'fas fa-circle'
})

const formatDate = (date) => {
    if (!date) return '—'
    return new Date(date).toLocaleString('en-US', { dateStyle: 'medium', timeStyle: 'short' })
}
</script>
