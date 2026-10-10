<script setup>
import { onMounted } from 'vue';
import { useSupplierTabFeed } from './useSupplierTabFeed';

const props = defineProps({ supplierId: { type: String, required: true } });

const { loading, page, result, load, goTo } = useSupplierTabFeed(
    'admin.suppliers.purchase-orders',
    props.supplierId
);

const formatCurrency = (a) => new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(a || 0);
const formatDate = (d) => (d ? new Date(d).toLocaleDateString('en-US') : '—');

const statusColors = {
    draft: 'text-gray-500',
    submitted: 'text-blue-600',
    partially_received: 'text-amber-600',
    received: 'text-green-600',
    cancelled: 'text-red-600',
};

onMounted(load);
</script>

<template>
    <div>
        <div class="flex justify-between items-center mb-3">
            <h3 class="text-sm font-medium text-gray-700 dark:text-gray-300">Purchase Orders</h3>
            <div class="text-xs text-gray-500" v-if="result.meta">{{ result.meta.total }} total</div>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-slate-700">
                    <tr>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">PO #</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Total</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Order Date</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Expected</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Received</th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-slate-800 divide-y divide-gray-200 dark:divide-gray-700">
                    <tr v-if="loading"><td colspan="6" class="px-4 py-6 text-center text-gray-500">Loading...</td></tr>
                    <tr v-else-if="!result.data.length"><td colspan="6" class="px-4 py-6 text-center text-gray-500">No purchase orders</td></tr>
                    <tr v-for="po in result.data" :key="po.id" class="hover:bg-gray-50 dark:hover:bg-slate-700">
                        <td class="px-4 py-2 text-sm font-medium text-gray-900 dark:text-white">{{ po.po_number }}</td>
                        <td class="px-4 py-2 text-sm capitalize" :class="statusColors[po.status] || 'text-gray-500'">{{ po.status.replace('_', ' ') }}</td>
                        <td class="px-4 py-2 text-sm text-gray-900 dark:text-white">{{ formatCurrency(po.total_amount) }}</td>
                        <td class="px-4 py-2 text-sm text-gray-500">{{ formatDate(po.order_date) }}</td>
                        <td class="px-4 py-2 text-sm text-gray-500">{{ formatDate(po.expected_delivery_date) }}</td>
                        <td class="px-4 py-2 text-sm text-gray-500">{{ formatDate(po.received_at) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div v-if="result.meta && result.meta.last_page > 1" class="flex justify-end gap-2 mt-3">
            <button @click="goTo(page - 1)" :disabled="page <= 1" class="px-3 py-1 text-sm rounded border border-gray-300 disabled:opacity-50">Prev</button>
            <span class="text-xs text-gray-500">Page {{ result.meta.current_page }} of {{ result.meta.last_page }}</span>
            <button @click="goTo(page + 1)" :disabled="page >= result.meta.last_page" class="px-3 py-1 text-sm rounded border border-gray-300 disabled:opacity-50">Next</button>
        </div>
    </div>
</template>
