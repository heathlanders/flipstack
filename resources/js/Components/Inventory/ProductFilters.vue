<script setup>
import { ref, computed, watch } from 'vue';

const props = defineProps({
    filters: {
        type: Object,
        required: true,
    },
    filterOptions: {
        type: Object,
        default: () => ({}),
    },
});

const emit = defineEmits(['update', 'clear']);

// Local filter state for range inputs
const localMinPrice = ref(props.filters.min_price || '');
const localMaxPrice = ref(props.filters.max_price || '');
const localCreatedFrom = ref(props.filters.created_from || '');
const localCreatedTo = ref(props.filters.created_to || '');

// Computed for flattened categories
const flattenedCategories = computed(() => {
    const flatten = (categories, result = []) => {
        categories.forEach(cat => {
            result.push({
                id: cat.id,
                name: cat.display_name || cat.name,
                level: cat.level || 0,
            });
            if (cat.children && cat.children.length > 0) {
                flatten(cat.children, result);
            }
        });
        return result;
    };
    return flatten(props.filterOptions.categories || []);
});

// Methods
const handleFilterChange = (key, value) => {
    emit('update', key, value || null);
};

const handlePriceChange = () => {
    if (localMinPrice.value !== props.filters.min_price) {
        emit('update', 'min_price', localMinPrice.value ? parseFloat(localMinPrice.value) : null);
    }
    if (localMaxPrice.value !== props.filters.max_price) {
        emit('update', 'max_price', localMaxPrice.value ? parseFloat(localMaxPrice.value) : null);
    }
};

const handleDateChange = () => {
    if (localCreatedFrom.value !== props.filters.created_from) {
        emit('update', 'created_from', localCreatedFrom.value || null);
    }
    if (localCreatedTo.value !== props.filters.created_to) {
        emit('update', 'created_to', localCreatedTo.value || null);
    }
};

const clearAllFilters = () => {
    localMinPrice.value = '';
    localMaxPrice.value = '';
    localCreatedFrom.value = '';
    localCreatedTo.value = '';
    emit('clear');
};

// Watch for external filter changes
watch(() => props.filters.min_price, (val) => {
    localMinPrice.value = val || '';
});
watch(() => props.filters.max_price, (val) => {
    localMaxPrice.value = val || '';
});
watch(() => props.filters.created_from, (val) => {
    localCreatedFrom.value = val || '';
});
watch(() => props.filters.created_to, (val) => {
    localCreatedTo.value = val || '';
});
</script>

<template>
    <div class="p-4 border-t border-gray-200 bg-gray-50 dark:bg-gray-800 dark:border-gray-700">
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4">
            <!-- Category Filter -->
            <div>
                <label class="block mb-1 text-xs font-medium text-gray-700 dark:text-gray-300">Category</label>
                <select
                    :value="filters.category_id"
                    @change="handleFilterChange('category_id', $event.target.value)"
                    class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                >
                    <option value="">All Categories</option>
                    <option v-for="category in flattenedCategories" :key="category.id" :value="category.id">
                        {{ category.name }}
                    </option>
                </select>
            </div>

            <!-- Brand Filter -->
            <div>
                <label class="block mb-1 text-xs font-medium text-gray-700 dark:text-gray-300">Brand</label>
                <select
                    :value="filters.brand_id"
                    @change="handleFilterChange('brand_id', $event.target.value)"
                    class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                >
                    <option value="">All Brands</option>
                    <option v-for="brand in filterOptions.brands" :key="brand.id" :value="brand.id">
                        {{ brand.name }}
                    </option>
                </select>
            </div>

            <!-- Status Filter -->
            <div>
                <label class="block mb-1 text-xs font-medium text-gray-700 dark:text-gray-300">Status</label>
                <select
                    :value="filters.status"
                    @change="handleFilterChange('status', $event.target.value)"
                    class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                >
                    <option value="">All Statuses</option>
                    <option v-for="status in filterOptions.statuses" :key="status.value" :value="status.value">
                        {{ status.label }}
                    </option>
                </select>
            </div>

            <!-- Stock Status Filter -->
            <div>
                <label class="block mb-1 text-xs font-medium text-gray-700 dark:text-gray-300">Stock Status</label>
                <select
                    :value="filters.stock_status"
                    @change="handleFilterChange('stock_status', $event.target.value)"
                    class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                >
                    <option value="">All Stock Levels</option>
                    <option v-for="status in filterOptions.stockStatuses" :key="status.value" :value="status.value">
                        {{ status.label }}
                    </option>
                </select>
            </div>

            <!-- Condition Filter -->
            <div>
                <label class="block mb-1 text-xs font-medium text-gray-700 dark:text-gray-300">Condition</label>
                <select
                    :value="filters.condition"
                    @change="handleFilterChange('condition', $event.target.value)"
                    class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                >
                    <option value="">All Conditions</option>
                    <option v-for="condition in filterOptions.conditions" :key="condition.value" :value="condition.value">
                        {{ condition.label }}
                    </option>
                </select>
            </div>

            <!-- Location Filter -->
            <div v-if="filterOptions.locations && filterOptions.locations.length > 0">
                <label class="block mb-1 text-xs font-medium text-gray-700 dark:text-gray-300">Location</label>
                <select
                    :value="filters.location_id"
                    @change="handleFilterChange('location_id', $event.target.value)"
                    class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                >
                    <option value="">All Locations</option>
                    <option v-for="location in filterOptions.locations" :key="location.id" :value="location.id">
                        {{ location.name }}
                    </option>
                </select>
            </div>

            <!-- Price Range -->
            <div>
                <label class="block mb-1 text-xs font-medium text-gray-700 dark:text-gray-300">Price Range</label>
                <div class="flex items-center gap-2">
                    <input
                        v-model="localMinPrice"
                        type="number"
                        placeholder="Min"
                        min="0"
                        step="0.01"
                        @change="handlePriceChange"
                        class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                    />
                    <span class="text-gray-500">—</span>
                    <input
                        v-model="localMaxPrice"
                        type="number"
                        placeholder="Max"
                        min="0"
                        step="0.01"
                        @change="handlePriceChange"
                        class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                    />
                </div>
            </div>

            <!-- Date Range -->
            <div>
                <label class="block mb-1 text-xs font-medium text-gray-700 dark:text-gray-300">Created Date</label>
                <div class="flex items-center gap-2">
                    <input
                        v-model="localCreatedFrom"
                        type="date"
                        @change="handleDateChange"
                        class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                    />
                    <span class="text-gray-500">—</span>
                    <input
                        v-model="localCreatedTo"
                        type="date"
                        @change="handleDateChange"
                        class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                    />
                </div>
            </div>
        </div>

        <!-- Active Filters & Clear Button -->
        <div class="flex items-center justify-between mt-4">
            <div class="flex flex-wrap gap-2">
                <!-- Active filter tags -->
                <span
                    v-if="filters.category_id"
                    class="inline-flex items-center gap-1 px-2 py-1 text-xs bg-indigo-100 text-indigo-800 rounded-full dark:bg-indigo-900 dark:text-indigo-200"
                >
                    Category: {{ flattenedCategories.find(c => c.id == filters.category_id)?.name || 'Selected' }}
                    <button @click="handleFilterChange('category_id', null)" class="hover:text-indigo-600">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </span>
                <span
                    v-if="filters.brand_id"
                    class="inline-flex items-center gap-1 px-2 py-1 text-xs bg-indigo-100 text-indigo-800 rounded-full dark:bg-indigo-900 dark:text-indigo-200"
                >
                    Brand: {{ filterOptions.brands?.find(b => b.id == filters.brand_id)?.name || 'Selected' }}
                    <button @click="handleFilterChange('brand_id', null)" class="hover:text-indigo-600">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </span>
                <span
                    v-if="filters.status"
                    class="inline-flex items-center gap-1 px-2 py-1 text-xs bg-indigo-100 text-indigo-800 rounded-full dark:bg-indigo-900 dark:text-indigo-200"
                >
                    Status: {{ filters.status }}
                    <button @click="handleFilterChange('status', null)" class="hover:text-indigo-600">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </span>
                <span
                    v-if="filters.stock_status"
                    class="inline-flex items-center gap-1 px-2 py-1 text-xs bg-indigo-100 text-indigo-800 rounded-full dark:bg-indigo-900 dark:text-indigo-200"
                >
                    Stock: {{ filters.stock_status.replace('_', ' ') }}
                    <button @click="handleFilterChange('stock_status', null)" class="hover:text-indigo-600">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </span>
                <span
                    v-if="filters.condition"
                    class="inline-flex items-center gap-1 px-2 py-1 text-xs bg-indigo-100 text-indigo-800 rounded-full dark:bg-indigo-900 dark:text-indigo-200"
                >
                    Condition: {{ filters.condition.replace('_', ' ') }}
                    <button @click="handleFilterChange('condition', null)" class="hover:text-indigo-600">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </span>
            </div>

            <button
                @click="clearAllFilters"
                class="text-sm text-indigo-600 hover:text-indigo-800 dark:text-indigo-400 dark:hover:text-indigo-200"
            >
                Clear all filters
            </button>
        </div>
    </div>
</template>
