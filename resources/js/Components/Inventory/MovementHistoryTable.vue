<script setup>
import { ref, computed, watch } from 'vue';
import debounce from 'lodash/debounce';

const props = defineProps({
    movements: {
        type: Object,
        required: true,
    },
    filters: {
        type: Object,
        default: () => ({}),
    },
    filterOptions: {
        type: Object,
        default: () => ({}),
    },
    permissions: {
        type: Object,
        default: () => ({}),
    },
});

const emit = defineEmits(['filter-change', 'sort-change', 'page-change', 'export']);

// Local filter state
const localFilters = ref({
    type: props.filters.type || '',
    location_id: props.filters.location_id || '',
    date_from: props.filters.date_from || '',
    date_to: props.filters.date_to || '',
    search: props.filters.search || '',
});

const sortBy = ref(props.filters.sort_by || 'created_at');
const sortDirection = ref(props.filters.sort_direction || 'desc');
const expandedRows = ref(new Set());

// Type colors
const typeColors = {
    purchase: 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400',
    sale: 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400',
    adjustment: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400',
    transfer_in: 'bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-400',
    transfer_out: 'bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-400',
    return: 'bg-teal-100 text-teal-800 dark:bg-teal-900/30 dark:text-teal-400',
    damaged: 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400',
    lost: 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400',
    found: 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400',
};

// Methods
const applyFilters = debounce(() => {
    emit('filter-change', {
        ...localFilters.value,
        sort_by: sortBy.value,
        sort_direction: sortDirection.value,
    });
}, 300);

const toggleSort = (column) => {
    if (sortBy.value === column) {
        sortDirection.value = sortDirection.value === 'asc' ? 'desc' : 'asc';
    } else {
        sortBy.value = column;
        sortDirection.value = 'desc';
    }
    emit('sort-change', { sort_by: sortBy.value, sort_direction: sortDirection.value });
};

const toggleRowExpansion = (id) => {
    if (expandedRows.value.has(id)) {
        expandedRows.value.delete(id);
    } else {
        expandedRows.value.add(id);
    }
};

const goToPage = (page) => {
    emit('page-change', page);
};

const handleExport = () => {
    emit('export', localFilters.value);
};

const getQuantityColor = (movement) => {
    if (movement.is_stock_increase) return 'text-green-600 dark:text-green-400';
    if (movement.is_stock_decrease) return 'text-red-600 dark:text-red-400';
    return 'text-gray-600 dark:text-gray-400';
};

const formatQuantity = (movement) => {
    const prefix = movement.is_stock_increase ? '+' : movement.is_stock_decrease ? '-' : '';
    return `${prefix}${Math.abs(movement.quantity)}`;
};

// Watch for filter changes
watch(localFilters, applyFilters, { deep: true });

watch(() => props.filters, (newFilters) => {
    Object.assign(localFilters.value, newFilters);
}, { deep: true });
</script>

<template>
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700">
        <!-- Filters -->
        <div class="p-4 border-b border-gray-200 dark:border-gray-700">
            <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
                <!-- Search -->
                <div class="md:col-span-2">
                    <input
                        type="text"
                        v-model="localFilters.search"
                        placeholder="Search products..."
                        class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                    />
                </div>

                <!-- Movement Type -->
                <div>
                    <select
                        v-model="localFilters.type"
                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                    >
                        <option value="">All Types</option>
                        <option
                            v-for="type in filterOptions.movementTypes"
                            :key="type.value"
                            :value="type.value"
                        >
                            {{ type.label }}
                        </option>
                    </select>
                </div>

                <!-- Location -->
                <div>
                    <select
                        v-model="localFilters.location_id"
                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                    >
                        <option value="">All Locations</option>
                        <option
                            v-for="location in filterOptions.locations"
                            :key="location.id"
                            :value="location.id"
                        >
                            {{ location.name }}
                        </option>
                    </select>
                </div>

                <!-- Export -->
                <div class="flex items-center justify-end">
                    <button
                        v-if="permissions.can_export"
                        @click="handleExport"
                        class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors flex items-center gap-2"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        Export
                    </button>
                </div>
            </div>

            <!-- Date Range -->
            <div class="mt-3 flex gap-4">
                <div class="flex items-center gap-2">
                    <label class="text-sm text-gray-600 dark:text-gray-400">From:</label>
                    <input
                        type="date"
                        v-model="localFilters.date_from"
                        class="px-3 py-1.5 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                    />
                </div>
                <div class="flex items-center gap-2">
                    <label class="text-sm text-gray-600 dark:text-gray-400">To:</label>
                    <input
                        type="date"
                        v-model="localFilters.date_to"
                        class="px-3 py-1.5 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                    />
                </div>
            </div>
        </div>

        <!-- Table -->
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50 dark:bg-gray-700/50">
                    <tr>
                        <th class="w-8 px-4 py-3"></th>
                        <th
                            class="px-4 py-3 text-left text-xs font-semibold text-gray-600 dark:text-gray-400 uppercase tracking-wider cursor-pointer hover:text-gray-900 dark:hover:text-white"
                            @click="toggleSort('created_at')"
                        >
                            <div class="flex items-center gap-1">
                                Date
                                <svg v-if="sortBy === 'created_at'" class="w-3 h-3" :class="sortDirection === 'desc' ? 'rotate-180' : ''" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                </svg>
                            </div>
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 dark:text-gray-400 uppercase tracking-wider">Product</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 dark:text-gray-400 uppercase tracking-wider">Type</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 dark:text-gray-400 uppercase tracking-wider">Qty</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 dark:text-gray-400 uppercase tracking-wider">Location</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 dark:text-gray-400 uppercase tracking-wider">Balance</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 dark:text-gray-400 uppercase tracking-wider">By</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    <template v-for="movement in movements.data" :key="movement.id">
                        <tr
                            class="hover:bg-gray-50 dark:hover:bg-gray-700/30 cursor-pointer"
                            @click="toggleRowExpansion(movement.id)"
                        >
                            <td class="px-4 py-3">
                                <svg
                                    :class="['w-4 h-4 text-gray-400 transition-transform', expandedRows.has(movement.id) ? 'rotate-90' : '']"
                                    fill="currentColor"
                                    viewBox="0 0 20 20"
                                >
                                    <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                                </svg>
                            </td>
                            <td class="px-4 py-3">
                                <p class="text-sm text-gray-900 dark:text-white">{{ movement.created_at_formatted }}</p>
                            </td>
                            <td class="px-4 py-3">
                                <div>
                                    <p class="text-sm font-medium text-gray-900 dark:text-white">
                                        {{ movement.product?.name || 'Unknown' }}
                                    </p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">
                                        {{ movement.product?.sku }}
                                    </p>
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <span :class="['px-2 py-1 text-xs font-medium rounded-full', typeColors[movement.type] || 'bg-gray-100 text-gray-800']">
                                    {{ movement.type_label }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <span :class="['text-sm font-semibold', getQuantityColor(movement)]">
                                    {{ formatQuantity(movement) }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <p class="text-sm text-gray-600 dark:text-gray-400">
                                    {{ movement.location?.name || '-' }}
                                </p>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <p class="text-sm text-gray-600 dark:text-gray-400">
                                    {{ movement.balance_before ?? '-' }} → {{ movement.balance_after ?? '-' }}
                                </p>
                            </td>
                            <td class="px-4 py-3">
                                <p class="text-sm text-gray-600 dark:text-gray-400">
                                    {{ movement.created_by?.name || 'System' }}
                                </p>
                            </td>
                        </tr>
                        <!-- Expanded Row -->
                        <tr v-if="expandedRows.has(movement.id)" class="bg-gray-50 dark:bg-gray-700/30">
                            <td colspan="8" class="px-8 py-4">
                                <div class="grid grid-cols-2 md:grid-cols-5 gap-4 text-sm">
                                    <div>
                                        <p class="text-gray-500 dark:text-gray-400 text-xs uppercase mb-1">Reason</p>
                                        <p class="text-gray-900 dark:text-white">{{ movement.reason || 'Not specified' }}</p>
                                    </div>
                                    <div>
                                        <p class="text-gray-500 dark:text-gray-400 text-xs uppercase mb-1">Reference</p>
                                        <p class="text-gray-900 dark:text-white">{{ movement.reference_number || '-' }}</p>
                                    </div>
                                    <div>
                                        <p class="text-gray-500 dark:text-gray-400 text-xs uppercase mb-1">Unit Cost</p>
                                        <p class="text-gray-900 dark:text-white">{{ movement.unit_cost ? '$' + movement.unit_cost : '-' }}</p>
                                    </div>
                                    <div>
                                        <p class="text-gray-500 dark:text-gray-400 text-xs uppercase mb-1">Total Cost</p>
                                        <p class="text-gray-900 dark:text-white">{{ movement.total_cost ? '$' + movement.total_cost : '-' }}</p>
                                    </div>
                                    <div class="md:col-span-1">
                                        <p class="text-gray-500 dark:text-gray-400 text-xs uppercase mb-1">Notes</p>
                                        <p class="text-gray-900 dark:text-white">{{ movement.notes || 'No notes' }}</p>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    </template>
                    
                    <!-- Empty State -->
                    <tr v-if="movements.data.length === 0">
                        <td colspan="8" class="px-4 py-12 text-center">
                            <svg class="w-12 h-12 mx-auto text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
                            </svg>
                            <p class="mt-4 text-gray-500 dark:text-gray-400">No movements found</p>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div v-if="movements.last_page > 1" class="px-4 py-3 border-t border-gray-200 dark:border-gray-700 flex items-center justify-between">
            <div class="text-sm text-gray-500 dark:text-gray-400">
                Showing {{ movements.from }} to {{ movements.to }} of {{ movements.total }} results
            </div>
            <div class="flex items-center gap-1">
                <button
                    :disabled="movements.current_page === 1"
                    @click="goToPage(movements.current_page - 1)"
                    class="px-3 py-1 text-sm border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 disabled:opacity-50 disabled:cursor-not-allowed"
                >
                    Previous
                </button>
                <button
                    :disabled="movements.current_page === movements.last_page"
                    @click="goToPage(movements.current_page + 1)"
                    class="px-3 py-1 text-sm border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 disabled:opacity-50 disabled:cursor-not-allowed"
                >
                    Next
                </button>
            </div>
        </div>
    </div>
</template>
