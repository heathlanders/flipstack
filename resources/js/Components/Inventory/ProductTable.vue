<script setup>
import { computed } from 'vue';

const props = defineProps({
    products: {
        type: Array,
        required: true,
    },
    selectedProducts: {
        type: Array,
        default: () => [],
    },
    sortBy: {
        type: String,
        default: 'created_at',
    },
    sortOrder: {
        type: String,
        default: 'desc',
    },
    permissions: {
        type: Object,
        default: () => ({}),
    },
});

const emit = defineEmits(['select', 'select-all', 'sort', 'view', 'edit', 'duplicate', 'delete']);

const columns = [
    { key: 'name', label: 'Product', sortable: true },
    { key: 'sku', label: 'SKU', sortable: true },
    { key: 'category', label: 'Category', sortable: false },
    { key: 'selling_price', label: 'Price', sortable: true },
    { key: 'stock_quantity', label: 'Stock', sortable: true },
    { key: 'condition', label: 'Condition', sortable: false },
    { key: 'status', label: 'Status', sortable: false },
    { key: 'created_at', label: 'Created', sortable: true },
    { key: 'actions', label: '', sortable: false },
];

const allSelected = computed(() => {
    if (props.products.length === 0) return false;
    return props.products.every(p => props.selectedProducts.includes(p.id));
});

const someSelected = computed(() => {
    return props.selectedProducts.length > 0 && !allSelected.value;
});

const isSelected = (productId) => props.selectedProducts.includes(productId);

const getSortIcon = (key) => {
    if (props.sortBy !== key) return 'neutral';
    return props.sortOrder === 'asc' ? 'asc' : 'desc';
};

const getStockStatusClasses = (status) => {
    return {
        'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200': status === 'in_stock',
        'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200': status === 'low_stock',
        'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200': status === 'out_of_stock',
        'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200': status === 'backorder',
        'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200': status === 'overstocked',
    };
};

const getStockStatusLabel = (status) => {
    const labels = {
        'in_stock': 'In Stock',
        'low_stock': 'Low Stock',
        'out_of_stock': 'Out of Stock',
        'backorder': 'Backorder',
        'overstocked': 'Overstocked',
    };
    return labels[status] || status;
};

const getStatusClasses = (status) => {
    return {
        'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200': status === 'active',
        'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200': status === 'draft',
        'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200': status === 'inactive',
        'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200': status === 'archived',
    };
};

const handleSelectAll = () => {
    emit('select-all');
};

const handleSelect = (productId) => {
    emit('select', productId);
};

const handleSort = (key) => {
    emit('sort', key);
};

const handleView = (productId) => {
    emit('view', productId);
};

const handleEdit = (productId) => {
    emit('edit', productId);
};

const handleDuplicate = (productId) => {
    emit('duplicate', productId);
};

const handleDelete = (productId) => {
    emit('delete', productId);
};
</script>

<template>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
            <thead class="bg-gray-50 dark:bg-gray-800">
                <tr>
                    <!-- Select All Checkbox -->
                    <th v-if="permissions.can_bulk_manage" scope="col" class="w-10 px-4 py-3">
                        <input
                            type="checkbox"
                            :checked="allSelected"
                            :indeterminate="someSelected"
                            @change="handleSelectAll"
                            class="w-4 h-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-700"
                        />
                    </th>
                    
                    <!-- Column Headers -->
                    <th
                        v-for="column in columns"
                        :key="column.key"
                        scope="col"
                        class="px-4 py-3 text-xs font-medium tracking-wider text-left text-gray-500 uppercase dark:text-gray-400"
                        :class="{ 'cursor-pointer hover:text-gray-700 dark:hover:text-gray-200': column.sortable }"
                        @click="column.sortable && handleSort(column.key)"
                    >
                        <div class="flex items-center gap-1">
                            {{ column.label }}
                            <template v-if="column.sortable">
                                <svg v-if="getSortIcon(column.key) === 'neutral'" class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4" />
                                </svg>
                                <svg v-else-if="getSortIcon(column.key) === 'asc'" class="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7" />
                                </svg>
                                <svg v-else class="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                </svg>
                            </template>
                        </div>
                    </th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200 dark:bg-gray-900 dark:divide-gray-700">
                <tr
                    v-for="product in products"
                    :key="product.id"
                    class="transition-colors cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-800"
                    :class="{ 'bg-indigo-50 dark:bg-indigo-900/20': isSelected(product.id) }"
                    @click="handleView(product.id)"
                >
                    <!-- Select Checkbox -->
                    <td v-if="permissions.can_bulk_manage" class="px-4 py-3" @click.stop>
                        <input
                            type="checkbox"
                            :checked="isSelected(product.id)"
                            @change="handleSelect(product.id)"
                            class="w-4 h-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-700"
                        />
                    </td>

                    <!-- Product Name & Image -->
                    <td class="px-4 py-3">
                        <div class="flex items-center">
                            <div class="flex-shrink-0 w-10 h-10">
                                <img
                                    v-if="product.featured_image_url"
                                    :src="product.featured_image_url"
                                    :alt="product.name"
                                    class="object-cover w-10 h-10 rounded"
                                    loading="lazy"
                                />
                                <div v-else class="flex items-center justify-center w-10 h-10 bg-gray-100 rounded dark:bg-gray-700">
                                    <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                    </svg>
                                </div>
                            </div>
                            <div class="ml-3">
                                <div class="text-sm font-medium text-gray-900 dark:text-white">
                                    {{ product.name }}
                                </div>
                                <div v-if="product.brand" class="text-xs text-gray-500 dark:text-gray-400">
                                    {{ product.brand.name }}
                                </div>
                            </div>
                        </div>
                    </td>

                    <!-- SKU -->
                    <td class="px-4 py-3">
                        <div class="text-sm text-gray-900 dark:text-white">{{ product.sku }}</div>
                        <div v-if="product.barcode" class="text-xs text-gray-500 dark:text-gray-400">
                            {{ product.barcode }}
                        </div>
                    </td>

                    <!-- Category -->
                    <td class="px-4 py-3">
                        <span v-if="product.category" class="text-sm text-gray-900 dark:text-white">
                            {{ product.category.name }}
                        </span>
                        <span v-else class="text-sm text-gray-400 dark:text-gray-500">—</span>
                    </td>

                    <!-- Price -->
                    <td class="px-4 py-3">
                        <div class="text-sm font-medium text-gray-900 dark:text-white">
                            {{ product.formatted_selling_price }}
                        </div>
                        <div v-if="product.cost_price" class="text-xs text-gray-500 dark:text-gray-400">
                            Cost: {{ product.formatted_cost_price }}
                        </div>
                    </td>

                    <!-- Stock -->
                    <td class="px-4 py-3">
                        <template v-if="product.manage_stock">
                            <div class="text-sm font-medium text-gray-900 dark:text-white">
                                {{ product.stock_quantity }}
                            </div>
                            <span
                                class="inline-flex px-2 py-0.5 mt-1 text-xs font-medium rounded-full"
                                :class="getStockStatusClasses(product.stock_status)"
                            >
                                {{ getStockStatusLabel(product.stock_status) }}
                            </span>
                        </template>
                        <span v-else class="text-sm text-gray-400 dark:text-gray-500">N/A</span>
                    </td>

                    <!-- Condition -->
                    <td class="px-4 py-3">
                        <span
                            v-if="product.condition"
                            class="inline-flex px-2 py-0.5 text-xs font-medium bg-gray-100 text-gray-700 rounded-full dark:bg-gray-700 dark:text-gray-300"
                        >
                            {{ product.condition_label }}
                        </span>
                        <span v-else class="text-sm text-gray-400 dark:text-gray-500">—</span>
                    </td>

                    <!-- Status -->
                    <td class="px-4 py-3">
                        <span
                            class="inline-flex px-2 py-0.5 text-xs font-medium rounded-full"
                            :class="getStatusClasses(product.status)"
                        >
                            {{ product.status_label }}
                        </span>
                    </td>

                    <!-- Created -->
                    <td class="px-4 py-3">
                        <div class="text-sm text-gray-900 dark:text-white">
                            {{ product.created_at_formatted }}
                        </div>
                        <div class="text-xs text-gray-500 dark:text-gray-400">
                            {{ product.created_at_human }}
                        </div>
                    </td>

                    <!-- Actions -->
                    <td class="px-4 py-3" @click.stop>
                        <div class="flex items-center gap-2">
                            <button
                                v-if="permissions.can_edit"
                                @click="handleEdit(product.id)"
                                class="p-1 text-gray-400 rounded hover:text-indigo-600 hover:bg-gray-100 dark:hover:bg-gray-700"
                                title="Edit"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                </svg>
                            </button>
                            <button
                                @click="handleDuplicate(product.id)"
                                class="p-1 text-gray-400 rounded hover:text-indigo-600 hover:bg-gray-100 dark:hover:bg-gray-700"
                                title="Duplicate"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                </svg>
                            </button>
                            <button
                                v-if="permissions.can_delete"
                                @click="handleDelete(product.id)"
                                class="p-1 text-gray-400 rounded hover:text-red-600 hover:bg-gray-100 dark:hover:bg-gray-700"
                                title="Delete"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                </svg>
                            </button>
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
