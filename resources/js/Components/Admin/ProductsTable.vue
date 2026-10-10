<script setup>
import { ref, watch } from 'vue';
import axios from 'axios';
import { route } from 'ziggy-js';

const props = defineProps({
    supplierId: { type: String, required: true },
});

const emit = defineEmits(['view']);

const search = ref('');
const sort = ref('created_at');
const direction = ref('desc');
const loading = ref(false);
const page = ref(1);
const result = ref({ data: [], links: [], meta: null });

async function load() {
    loading.value = true;
    try {
        const url = route('admin.suppliers.products', props.supplierId);
        const { data } = await axios.get(url, {
            params: {
                search: search.value,
                sort: sort.value,
                direction: direction.value,
                page: page.value,
            },
        });
        result.value = data;
    } finally {
        loading.value = false;
    }
}

function setSort(column) {
    if (sort.value === column) {
        direction.value = direction.value === 'asc' ? 'desc' : 'asc';
    } else {
        sort.value = column;
        direction.value = 'asc';
    }
    page.value = 1;
    load();
}

function goTo(p) {
    page.value = p;
    load();
}

let searchTimer = null;
watch(search, () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
        page.value = 1;
        load();
    }, 300);
});

load();

const formatCurrency = (amount) =>
    new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(amount || 0);

function sortIcon(column) {
    if (sort.value !== column) return '';
    return direction.value === 'asc' ? 'asc' : 'desc';
}
</script>

<template>
    <div>
        <div class="flex items-center justify-between mb-3 gap-3">
            <input
                v-model="search"
                type="text"
                placeholder="Search by SKU or name..."
                class="w-full max-w-sm rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm"
            />
            <div class="text-xs text-gray-500 dark:text-gray-400" v-if="result.meta">
                {{ result.meta.from || 0 }}-{{ result.meta.to || 0 }} of {{ result.meta.total }}
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-slate-700">
                    <tr>
                        <th @click="setSort('sku')" class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase cursor-pointer select-none">
                            SKU <span class="text-indigo-500">{{ sortIcon('sku') }}</span>
                        </th>
                        <th @click="setSort('name')" class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase cursor-pointer select-none">
                            Product <span class="text-indigo-500">{{ sortIcon('name') }}</span>
                        </th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Supplier SKU</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Cost</th>
                        <th @click="setSort('price')" class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase cursor-pointer select-none">
                            Price <span class="text-indigo-500">{{ sortIcon('price') }}</span>
                        </th>
                        <th @click="setSort('stock')" class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase cursor-pointer select-none">
                            Stock <span class="text-indigo-500">{{ sortIcon('stock') }}</span>
                        </th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Status</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Preferred</th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-slate-800 divide-y divide-gray-200 dark:divide-gray-700">
                    <tr v-if="loading">
                        <td colspan="8" class="px-4 py-6 text-center text-gray-500">Loading...</td>
                    </tr>
                    <tr v-else-if="!result.data.length">
                        <td colspan="8" class="px-4 py-6 text-center text-gray-500">No products found</td>
                    </tr>
                    <tr
                        v-for="p in result.data"
                        :key="p.id"
                        @click="emit('view', p)"
                        class="hover:bg-gray-50 dark:hover:bg-slate-700 cursor-pointer"
                    >
                        <td class="px-4 py-2 text-sm text-gray-500 dark:text-gray-400">{{ p.sku }}</td>
                        <td class="px-4 py-2 text-sm font-medium text-gray-900 dark:text-white">{{ p.name }}</td>
                        <td class="px-4 py-2 text-sm text-gray-500 dark:text-gray-400">{{ p.pivot?.supplier_sku || '—' }}</td>
                        <td class="px-4 py-2 text-sm text-gray-500 dark:text-gray-400">{{ formatCurrency(p.pivot?.cost_price) }}</td>
                        <td class="px-4 py-2 text-sm text-gray-900 dark:text-white">{{ formatCurrency(p.selling_price) }}</td>
                        <td class="px-4 py-2 text-sm text-gray-500 dark:text-gray-400">{{ p.stock_quantity || 0 }}</td>
                        <td class="px-4 py-2 text-sm">
                            <span :class="p.status === 'active' ? 'text-green-600' : 'text-gray-500'">{{ p.status }}</span>
                        </td>
                        <td class="px-4 py-2 text-sm">
                            <span v-if="p.pivot?.is_preferred" class="text-amber-600">Yes</span>
                            <span v-else class="text-gray-400">—</span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div v-if="result.meta && result.meta.last_page > 1" class="flex items-center justify-end gap-2 mt-3">
            <button
                @click="goTo(page - 1)"
                :disabled="page <= 1"
                class="px-3 py-1 text-sm rounded border border-gray-300 dark:border-gray-600 disabled:opacity-50"
            >
                Prev
            </button>
            <span class="text-xs text-gray-500">Page {{ result.meta.current_page }} of {{ result.meta.last_page }}</span>
            <button
                @click="goTo(page + 1)"
                :disabled="page >= result.meta.last_page"
                class="px-3 py-1 text-sm rounded border border-gray-300 dark:border-gray-600 disabled:opacity-50"
            >
                Next
            </button>
        </div>
    </div>
</template>
