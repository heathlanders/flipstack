<script setup>
import { Link } from '@inertiajs/vue3'
import StatusBadge from '@/Components/StatusBadge.vue'

defineProps({
    settlements: { type: Array, default: () => [] },
    showSupplier: { type: Boolean, default: false }
})

const formatCurrency = (amount) => {
    return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(amount || 0)
}

const formatDate = (date) => {
    if (!date) return '-'
    return new Date(date).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' })
}
</script>

<template>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
            <thead class="bg-gray-50 dark:bg-slate-700">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">ID</th>
                    <th v-if="showSupplier" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Supplier</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Period</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Amount</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Created</th>
                </tr>
            </thead>
            <tbody class="bg-white dark:bg-slate-800 divide-y divide-gray-200 dark:divide-gray-700">
                <tr v-if="settlements.length === 0">
                    <td :colspan="showSupplier ? 6 : 5" class="px-6 py-4 text-center text-gray-500">No settlements found</td>
                </tr>
                <tr v-for="settlement in settlements" :key="settlement.id" class="hover:bg-gray-50 dark:hover:bg-slate-700">
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">
                        #{{ settlement.id?.toString().slice(-8) }}
                    </td>
                    <td v-if="showSupplier" class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                        {{ settlement.supplier?.name || '-' }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                        {{ formatDate(settlement.period_start) }} - {{ formatDate(settlement.period_end) }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">
                        {{ formatCurrency(settlement.total_amount) }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <StatusBadge :status="settlement.status" />
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                        {{ formatDate(settlement.created_at) }}
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
