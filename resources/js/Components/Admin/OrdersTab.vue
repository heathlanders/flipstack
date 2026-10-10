<script setup>
import { onMounted } from 'vue';
import { useSupplierTabFeed } from './useSupplierTabFeed';

const props = defineProps({ supplierId: { type: String, required: true } });

const { loading, page, result, load, goTo } = useSupplierTabFeed(
    'admin.suppliers.orders',
    props.supplierId
);

const formatCurrency = (a) => new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(a || 0);
const formatDate = (d) => (d ? new Date(d).toLocaleDateString('en-US') : '—');

onMounted(load);
</script>

<template>
    <div>
        <div class="flex justify-between items-center mb-3">
            <h3 class="text-sm font-medium text-gray-700 dark:text-gray-300">Orders (dropship / consignment)</h3>
            <div class="text-xs text-gray-500" v-if="result.meta">{{ result.meta.total }} total</div>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-slate-700">
                    <tr>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Order #</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Marketplace Status</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Total</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Placed</th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-slate-800 divide-y divide-gray-200 dark:divide-gray-700">
                    <tr v-if="loading"><td colspan="5" class="px-4 py-6 text-center text-gray-500">Loading...</td></tr>
                    <tr v-else-if="!result.data.length"><td colspan="5" class="px-4 py-6 text-center text-gray-500">No orders linked to this supplier</td></tr>
                    <tr v-for="o in result.data" :key="o.id" class="hover:bg-gray-50 dark:hover:bg-slate-700">
                        <td class="px-4 py-2 text-sm font-medium text-gray-900 dark:text-white">{{ o.order_number }}</td>
                        <td class="px-4 py-2 text-sm text-gray-500 capitalize">{{ (o.status || '').replace('_', ' ') }}</td>
                        <td class="px-4 py-2 text-sm text-gray-500">{{ o.marketplace_status || '—' }}</td>
                        <td class="px-4 py-2 text-sm text-gray-900 dark:text-white">{{ formatCurrency(o.total_amount) }}</td>
                        <td class="px-4 py-2 text-sm text-gray-500">{{ formatDate(o.created_at) }}</td>
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
