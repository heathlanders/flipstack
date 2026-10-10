<script setup>
import { ref, computed } from 'vue';

const props = defineProps({
    selectedCount: {
        type: Number,
        required: true,
    },
    permissions: {
        type: Object,
        default: () => ({}),
    },
    filterOptions: {
        type: Object,
        default: () => ({}),
    },
});

const emit = defineEmits(['action', 'clear', 'export']);

const showBulkEditModal = ref(false);
const showStockAdjustModal = ref(false);
const bulkEditType = ref('');

const bulkEditData = ref({
    status: '',
    category_id: '',
    price_type: 'fixed',
    price_field: 'selling_price',
    value: '',
    operation: 'increase',
});

const stockAdjustData = ref({
    adjustment_type: 'add',
    quantity: '',
    reason: '',
});

// Flatten categories for select
const flattenedCategories = computed(() => {
    const flatten = (categories, result = []) => {
        categories.forEach(cat => {
            result.push({
                id: cat.id,
                name: cat.display_name || cat.name,
            });
            if (cat.children && cat.children.length > 0) {
                flatten(cat.children, result);
            }
        });
        return result;
    };
    return flatten(props.filterOptions.categories || []);
});

const openBulkEdit = (type) => {
    bulkEditType.value = type;
    showBulkEditModal.value = true;
};

const closeBulkEditModal = () => {
    showBulkEditModal.value = false;
    bulkEditType.value = '';
    bulkEditData.value = {
        status: '',
        category_id: '',
        price_type: 'fixed',
        price_field: 'selling_price',
        value: '',
        operation: 'increase',
    };
};

const submitBulkEdit = () => {
    if (bulkEditType.value === 'status' && bulkEditData.value.status) {
        emit('action', 'update_status', { status: bulkEditData.value.status });
    } else if (bulkEditType.value === 'category' && bulkEditData.value.category_id) {
        emit('action', 'update_category', { category_id: bulkEditData.value.category_id });
    } else if (bulkEditType.value === 'price' && bulkEditData.value.value) {
        emit('action', 'update_price', {
            price_type: bulkEditData.value.price_type,
            price_field: bulkEditData.value.price_field,
            value: parseFloat(bulkEditData.value.value),
            operation: bulkEditData.value.operation,
        });
    }
    closeBulkEditModal();
};

const openStockAdjust = () => {
    showStockAdjustModal.value = true;
};

const closeStockAdjustModal = () => {
    showStockAdjustModal.value = false;
    stockAdjustData.value = {
        adjustment_type: 'add',
        quantity: '',
        reason: '',
    };
};

const submitStockAdjust = () => {
    if (stockAdjustData.value.quantity) {
        emit('action', 'adjust_stock', {
            adjustment_type: stockAdjustData.value.adjustment_type,
            quantity: parseInt(stockAdjustData.value.quantity),
            reason: stockAdjustData.value.reason,
        });
    }
    closeStockAdjustModal();
};

const handleDelete = () => {
    if (confirm(`Are you sure you want to delete ${props.selectedCount} product(s)? This action cannot be undone.`)) {
        emit('action', 'delete');
    }
};

const handleExport = (format) => {
    emit('export', format);
};
</script>

<template>
    <div class="sticky top-0 z-20 flex items-center justify-between px-4 py-3 bg-indigo-600 shadow-lg">
        <div class="flex items-center gap-4">
            <span class="text-sm font-medium text-white">
                {{ selectedCount }} product{{ selectedCount !== 1 ? 's' : '' }} selected
            </span>
            <button
                @click="emit('clear')"
                class="text-sm text-indigo-200 hover:text-white"
            >
                Clear selection
            </button>
        </div>

        <div class="flex items-center gap-2">
            <!-- Bulk Edit Dropdown -->
            <div class="relative" v-if="permissions.can_edit">
                <div class="relative inline-block text-left">
                    <button
                        type="button"
                        class="inline-flex items-center px-3 py-1.5 text-sm font-medium text-indigo-700 bg-white rounded-md hover:bg-indigo-50"
                        @click="$refs.editDropdown.classList.toggle('hidden')"
                    >
                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                        </svg>
                        Edit
                        <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>
                    <div
                        ref="editDropdown"
                        class="absolute right-0 z-50 hidden w-48 mt-2 bg-white rounded-md shadow-lg ring-1 ring-black ring-opacity-5"
                    >
                        <div class="py-1">
                            <button
                                @click="openBulkEdit('status'); $refs.editDropdown.classList.add('hidden')"
                                class="block w-full px-4 py-2 text-sm text-left text-gray-700 hover:bg-gray-100"
                            >
                                Change Status
                            </button>
                            <button
                                @click="openBulkEdit('category'); $refs.editDropdown.classList.add('hidden')"
                                class="block w-full px-4 py-2 text-sm text-left text-gray-700 hover:bg-gray-100"
                            >
                                Change Category
                            </button>
                            <button
                                @click="openBulkEdit('price'); $refs.editDropdown.classList.add('hidden')"
                                class="block w-full px-4 py-2 text-sm text-left text-gray-700 hover:bg-gray-100"
                            >
                                Update Prices
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Stock Adjust -->
            <button
                v-if="permissions.can_adjust_stock"
                @click="openStockAdjust"
                class="inline-flex items-center px-3 py-1.5 text-sm font-medium text-indigo-700 bg-white rounded-md hover:bg-indigo-50"
            >
                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4" />
                </svg>
                Adjust Stock
            </button>

            <!-- Export Dropdown -->
            <div class="relative" v-if="permissions.can_export">
                <div class="relative inline-block text-left">
                    <button
                        type="button"
                        class="inline-flex items-center px-3 py-1.5 text-sm font-medium text-indigo-700 bg-white rounded-md hover:bg-indigo-50"
                        @click="$refs.exportDropdown.classList.toggle('hidden')"
                    >
                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        Export
                        <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>
                    <div
                        ref="exportDropdown"
                        class="absolute right-0 z-50 hidden w-40 mt-2 bg-white rounded-md shadow-lg ring-1 ring-black ring-opacity-5"
                    >
                        <div class="py-1">
                            <button
                                @click="handleExport('csv'); $refs.exportDropdown.classList.add('hidden')"
                                class="block w-full px-4 py-2 text-sm text-left text-gray-700 hover:bg-gray-100"
                            >
                                Export as CSV
                            </button>
                            <button
                                @click="handleExport('xlsx'); $refs.exportDropdown.classList.add('hidden')"
                                class="block w-full px-4 py-2 text-sm text-left text-gray-700 hover:bg-gray-100"
                            >
                                Export as Excel
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Delete -->
            <button
                v-if="permissions.can_delete"
                @click="handleDelete"
                class="inline-flex items-center px-3 py-1.5 text-sm font-medium text-white bg-red-600 rounded-md hover:bg-red-700"
            >
                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                </svg>
                Delete
            </button>
        </div>
    </div>

    <!-- Bulk Edit Modal -->
    <Teleport to="body">
        <div v-if="showBulkEditModal" class="fixed inset-0 z-50 overflow-y-auto">
            <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:p-0">
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" @click="closeBulkEditModal"></div>

                <div class="relative z-10 w-full max-w-md p-6 mx-auto bg-white rounded-lg shadow-xl dark:bg-gray-800">
                    <h3 class="mb-4 text-lg font-medium text-gray-900 dark:text-white">
                        {{ bulkEditType === 'status' ? 'Change Status' : bulkEditType === 'category' ? 'Change Category' : 'Update Prices' }}
                    </h3>

                    <!-- Status Edit -->
                    <div v-if="bulkEditType === 'status'" class="mb-4">
                        <label class="block mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">New Status</label>
                        <select
                            v-model="bulkEditData.status"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                        >
                            <option value="">Select status...</option>
                            <option value="draft">Draft</option>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                            <option value="archived">Archived</option>
                        </select>
                    </div>

                    <!-- Category Edit -->
                    <div v-if="bulkEditType === 'category'" class="mb-4">
                        <label class="block mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">New Category</label>
                        <select
                            v-model="bulkEditData.category_id"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                        >
                            <option value="">Select category...</option>
                            <option v-for="category in flattenedCategories" :key="category.id" :value="category.id">
                                {{ category.name }}
                            </option>
                        </select>
                    </div>

                    <!-- Price Edit -->
                    <div v-if="bulkEditType === 'price'" class="space-y-4">
                        <div>
                            <label class="block mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">Price Field</label>
                            <select
                                v-model="bulkEditData.price_field"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                            >
                                <option value="selling_price">Selling Price</option>
                                <option value="cost_price">Cost Price</option>
                            </select>
                        </div>
                        <div>
                            <label class="block mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">Adjustment Type</label>
                            <select
                                v-model="bulkEditData.price_type"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                            >
                                <option value="fixed">Set Fixed Price</option>
                                <option value="percentage">Percentage Change</option>
                            </select>
                        </div>
                        <div v-if="bulkEditData.price_type === 'percentage'">
                            <label class="block mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">Operation</label>
                            <select
                                v-model="bulkEditData.operation"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                            >
                                <option value="increase">Increase by %</option>
                                <option value="decrease">Decrease by %</option>
                            </select>
                        </div>
                        <div>
                            <label class="block mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">
                                {{ bulkEditData.price_type === 'fixed' ? 'New Price ($)' : 'Percentage (%)' }}
                            </label>
                            <input
                                v-model="bulkEditData.value"
                                type="number"
                                step="0.01"
                                min="0"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                                :placeholder="bulkEditData.price_type === 'fixed' ? '0.00' : '10'"
                            />
                        </div>
                    </div>

                    <div class="flex justify-end gap-3 mt-6">
                        <button
                            @click="closeBulkEditModal"
                            class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 dark:bg-gray-700 dark:text-gray-200 dark:border-gray-600"
                        >
                            Cancel
                        </button>
                        <button
                            @click="submitBulkEdit"
                            class="px-4 py-2 text-sm font-medium text-white bg-indigo-600 rounded-lg hover:bg-indigo-700"
                        >
                            Apply to {{ selectedCount }} Product{{ selectedCount !== 1 ? 's' : '' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </Teleport>

    <!-- Stock Adjust Modal -->
    <Teleport to="body">
        <div v-if="showStockAdjustModal" class="fixed inset-0 z-50 overflow-y-auto">
            <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:p-0">
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" @click="closeStockAdjustModal"></div>

                <div class="relative z-10 w-full max-w-md p-6 mx-auto bg-white rounded-lg shadow-xl dark:bg-gray-800">
                    <h3 class="mb-4 text-lg font-medium text-gray-900 dark:text-white">
                        Adjust Stock for {{ selectedCount }} Product{{ selectedCount !== 1 ? 's' : '' }}
                    </h3>

                    <div class="space-y-4">
                        <div>
                            <label class="block mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">Adjustment Type</label>
                            <select
                                v-model="stockAdjustData.adjustment_type"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                            >
                                <option value="set">Set to exact quantity</option>
                                <option value="add">Add to current stock</option>
                                <option value="subtract">Subtract from current stock</option>
                            </select>
                        </div>
                        <div>
                            <label class="block mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">Quantity</label>
                            <input
                                v-model="stockAdjustData.quantity"
                                type="number"
                                min="0"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                                placeholder="0"
                            />
                        </div>
                        <div>
                            <label class="block mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">Reason (optional)</label>
                            <input
                                v-model="stockAdjustData.reason"
                                type="text"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                                placeholder="e.g., Inventory count adjustment"
                            />
                        </div>
                    </div>

                    <div class="flex justify-end gap-3 mt-6">
                        <button
                            @click="closeStockAdjustModal"
                            class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 dark:bg-gray-700 dark:text-gray-200 dark:border-gray-600"
                        >
                            Cancel
                        </button>
                        <button
                            @click="submitStockAdjust"
                            class="px-4 py-2 text-sm font-medium text-white bg-indigo-600 rounded-lg hover:bg-indigo-700"
                        >
                            Adjust Stock
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </Teleport>
</template>
