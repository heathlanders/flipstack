<script setup>
import { computed } from 'vue';

const props = defineProps({
    product: {
        type: Object,
        required: true,
    },
    selected: {
        type: Boolean,
        default: false,
    },
    canSelect: {
        type: Boolean,
        default: true,
    },
    canEdit: {
        type: Boolean,
        default: true,
    },
    canDelete: {
        type: Boolean,
        default: true,
    },
});

const emit = defineEmits(['select', 'view', 'edit', 'duplicate', 'delete']);

const stockStatusClasses = computed(() => {
    const status = props.product.stock_status;
    return {
        'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200': status === 'in_stock',
        'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200': status === 'low_stock',
        'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200': status === 'out_of_stock',
        'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200': status === 'backorder',
        'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200': status === 'overstocked',
    };
});

const stockStatusLabel = computed(() => {
    const labels = {
        'in_stock': 'In Stock',
        'low_stock': 'Low Stock',
        'out_of_stock': 'Out of Stock',
        'backorder': 'Backorder',
        'overstocked': 'Overstocked',
    };
    return labels[props.product.stock_status] || 'Unknown';
});

const statusClasses = computed(() => {
    const status = props.product.status;
    return {
        'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200': status === 'active',
        'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200': status === 'draft',
        'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200': status === 'inactive',
        'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200': status === 'archived',
    };
});

const handleSelect = (e) => {
    e.stopPropagation();
    emit('select', props.product.id);
};

const handleView = () => {
    emit('view', props.product.id);
};

const handleEdit = (e) => {
    e.stopPropagation();
    emit('edit', props.product.id);
};

const handleDuplicate = (e) => {
    e.stopPropagation();
    emit('duplicate', props.product.id);
};

const handleDelete = (e) => {
    e.stopPropagation();
    emit('delete', props.product.id);
};
</script>

<template>
    <div
        class="relative overflow-hidden transition-all bg-white border border-gray-200 rounded-lg shadow-sm cursor-pointer group dark:bg-gray-800 dark:border-gray-700 hover:shadow-md hover:border-indigo-300 dark:hover:border-indigo-600"
        :class="{ 'ring-2 ring-indigo-500': selected }"
        @click="handleView"
    >
        <!-- Selection Checkbox -->
        <div
            v-if="canSelect"
            class="absolute z-10 top-2 left-2"
            @click.stop
        >
            <input
                type="checkbox"
                :checked="selected"
                @change="handleSelect"
                class="w-4 h-4 text-indigo-600 transition-opacity bg-white border-gray-300 rounded opacity-0 cursor-pointer focus:ring-indigo-500 group-hover:opacity-100"
                :class="{ 'opacity-100': selected }"
            />
        </div>

        <!-- Quick Actions -->
        <div class="absolute z-10 flex gap-1 transition-opacity opacity-0 top-2 right-2 group-hover:opacity-100">
            <button
                v-if="canEdit"
                @click="handleEdit"
                class="p-1.5 bg-white rounded-full shadow-sm hover:bg-gray-100 dark:bg-gray-700 dark:hover:bg-gray-600"
                title="Edit"
            >
                <svg class="w-4 h-4 text-gray-600 dark:text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                </svg>
            </button>
            <button
                @click="handleDuplicate"
                class="p-1.5 bg-white rounded-full shadow-sm hover:bg-gray-100 dark:bg-gray-700 dark:hover:bg-gray-600"
                title="Duplicate"
            >
                <svg class="w-4 h-4 text-gray-600 dark:text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                </svg>
            </button>
            <button
                v-if="canDelete"
                @click="handleDelete"
                class="p-1.5 bg-white rounded-full shadow-sm hover:bg-red-100 dark:bg-gray-700 dark:hover:bg-red-900"
                title="Delete"
            >
                <svg class="w-4 h-4 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                </svg>
            </button>
        </div>

        <!-- Product Image -->
        <div class="relative aspect-square bg-gray-100 dark:bg-gray-700">
            <img
                v-if="product.featured_image_url"
                :src="product.featured_image_url"
                :alt="product.name"
                class="object-cover w-full h-full"
                loading="lazy"
            />
            <div v-else class="flex items-center justify-center w-full h-full">
                <svg class="w-16 h-16 text-gray-300 dark:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
            </div>

            <!-- Image count badge -->
            <div v-if="product.images_count > 1" class="absolute px-2 py-0.5 text-xs font-medium text-white bg-black bg-opacity-60 rounded bottom-2 right-2">
                +{{ product.images_count - 1 }}
            </div>
        </div>

        <!-- Product Info -->
        <div class="p-4">
            <!-- Status Badges -->
            <div class="flex flex-wrap gap-1 mb-2">
                <span
                    class="px-2 py-0.5 text-xs font-medium rounded-full"
                    :class="statusClasses"
                >
                    {{ product.status_label }}
                </span>
                <span
                    v-if="product.manage_stock"
                    class="px-2 py-0.5 text-xs font-medium rounded-full"
                    :class="stockStatusClasses"
                >
                    {{ stockStatusLabel }}
                </span>
                <span
                    v-if="product.condition"
                    class="px-2 py-0.5 text-xs font-medium bg-gray-100 text-gray-700 rounded-full dark:bg-gray-700 dark:text-gray-300"
                >
                    {{ product.condition_label }}
                </span>
            </div>

            <!-- Name -->
            <h3 class="mb-1 font-medium text-gray-900 truncate dark:text-white" :title="product.name">
                {{ product.name }}
            </h3>

            <!-- SKU -->
            <p class="mb-2 text-xs text-gray-500 dark:text-gray-400">
                SKU: {{ product.sku }}
            </p>

            <!-- Category & Brand -->
            <div class="mb-3 text-xs text-gray-500 dark:text-gray-400">
                <span v-if="product.category">{{ product.category.name }}</span>
                <span v-if="product.category && product.brand"> · </span>
                <span v-if="product.brand">{{ product.brand.name }}</span>
            </div>

            <!-- Price & Stock -->
            <div class="flex items-center justify-between">
                <div>
                    <span class="text-lg font-semibold text-gray-900 dark:text-white">
                        {{ product.formatted_selling_price }}
                    </span>
                    <span v-if="product.cost_price" class="block text-xs text-gray-500 dark:text-gray-400">
                        Cost: {{ product.formatted_cost_price }}
                    </span>
                </div>
                <div v-if="product.manage_stock" class="text-right">
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">
                        {{ product.stock_quantity }}
                    </span>
                    <span class="block text-xs text-gray-500 dark:text-gray-400">in stock</span>
                </div>
            </div>

            <!-- Profit Margin -->
            <div v-if="product.profit_margin !== null" class="mt-2 pt-2 border-t border-gray-100 dark:border-gray-700">
                <div class="flex items-center justify-between text-xs">
                    <span class="text-gray-500 dark:text-gray-400">Profit Margin</span>
                    <span
                        class="font-medium"
                        :class="product.profit_margin >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'"
                    >
                        {{ product.profit_margin }}%
                    </span>
                </div>
            </div>
        </div>
    </div>
</template>
