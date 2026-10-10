<script setup>
import { onMounted } from 'vue';
import { useSupplierTabFeed } from './useSupplierTabFeed';

const props = defineProps({ supplierId: { type: String, required: true } });

const { loading, page, result, load, goTo } = useSupplierTabFeed(
    'admin.suppliers.inventory-batches',
    props.supplierId
);

const formatCurrency = (a) => new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(a || 0);
const formatDate = (d) => (d ? new Date(d).toLocaleDateString('en-US') : '—');

onMounted(load);
</script>

<template>
    <div>
        <div class="flex justify-between items-center mb-3">
            <h3 class="text-sm font-medium text-gray-700 dark:text-gray-300">Inventory Batches</h3>
            <div class="text-xs text-gray-500" v-if="result.meta">{{ result.meta.total }} total</div>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-slate-700">
                    <tr>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Batch #</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Items</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Acquisition Cost</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Acquired</th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-slate-800 divide-y divide-gray-200 dark:divide-gray-700">
                    <tr v-if="loading"><td colspan="6" class="px-4 py-6 text-center text-gray-500">Loading...</td></tr>
                    <tr v-else-if="!result.data.length"><td colspan="6" class="px-4 py-6 text-center text-gray-500">No inventory batches</td></tr>
                    <tr v-for="b in result.data" :key="b.id" class="hover:bg-gray-50 dark:hover:bg-slate-700">
                        <td class="px-4 py-2 text-sm font-medium text-gray-900 dark:text-white">{{ b.batch_number }}</td>
                        <td class="px-4 py-2 text-sm text-gray-900 dark:text-white">{{ b.name }}</td>
                        <td class="px-4 py-2 text-sm text-gray-500 capitalize">{{ b.status }}</td>
                        <td class="px-4 py-2 text-sm text-gray-500">{{ b.items_count }}</td>
                        <td class="px-4 py-2 text-sm text-gray-500">{{ formatCurrency(b.acquisition_cost) }}</td>
                        <td class="px-4 py-2 text-sm text-gray-500">{{ formatDate(b.acquisition_date) }}</td>
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
