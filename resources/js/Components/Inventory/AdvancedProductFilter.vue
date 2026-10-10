<script setup>
import { ref, reactive, computed, watch, onMounted } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import debounce from 'lodash/debounce'

const props = defineProps({
    filterOptions: {
        type: Object,
        default: () => ({
            categories: [],
            brands: [],
            locations: [],
            conditions: [],
            priceRange: { min: 0, max: 1000 }
        })
    },
    currentFilters: {
        type: Object,
        default: () => ({})
    },
    permissions: {
        type: Object,
        default: () => ({})
    }
})

const emit = defineEmits(['filter-change', 'filters-reset'])

// Reactive filter state
const filters = reactive({
    category_ids: [],
    brand_ids: [],
    stock_status: [],
    price_range: { 
        min: props.filterOptions?.priceRange?.min ?? null, 
        max: props.filterOptions?.priceRange?.max ?? null 
    },
    condition: [],
    location_ids: [],
    date_range: { 
        created_from: null, 
        created_to: null 
    },
    sort_by: 'created_at',
    sort_order: 'desc'
})

// UI State
const expandedSections = reactive({
    categories: true,
    brands: false,
    stock: true,
    price: false,
    condition: false,
    location: false,
    dates: false
})

const expandedCategories = ref(new Set())
const brandSearchQuery = ref('')
const locationSearchQuery = ref('')
const isAdvancedOpen = ref(false)

// Stock status options
const stockStatusOptions = [
    { value: 'in_stock', label: 'In Stock', color: 'bg-emerald-500' },
    { value: 'low_stock', label: 'Low Stock', color: 'bg-amber-500' },
    { value: 'out_of_stock', label: 'Out of Stock', color: 'bg-red-500' },
    { value: 'overstocked', label: 'Overstocked', color: 'bg-blue-500' }
]

// Sort options
const sortOptions = [
    { value: 'created_at', label: 'Date Created' },
    { value: 'updated_at', label: 'Last Updated' },
    { value: 'name', label: 'Name' },
    { value: 'sku', label: 'SKU' },
    { value: 'selling_price', label: 'Price' },
    { value: 'stock_quantity', label: 'Stock Level' }
]

// Computed: Active filter count
const activeFilterCount = computed(() => {
    let count = 0
    if (filters.category_ids.length) count++
    if (filters.brand_ids.length) count++
    if (filters.stock_status.length) count++
    if (filters.price_range.min !== null || filters.price_range.max !== null) count++
    if (filters.condition.length) count++
    if (filters.location_ids.length) count++
    if (filters.date_range.created_from || filters.date_range.created_to) count++
    return count
})

// Computed: Filtered brands based on search
const filteredBrands = computed(() => {
    if (!brandSearchQuery.value) {
        return props.filterOptions?.brands || []
    }
    const query = brandSearchQuery.value.toLowerCase()
    return (props.filterOptions?.brands || []).filter(brand => 
        brand.name.toLowerCase().includes(query)
    )
})

// Computed: Filtered locations based on search
const filteredLocations = computed(() => {
    if (!locationSearchQuery.value) {
        return props.filterOptions?.locations || []
    }
    const query = locationSearchQuery.value.toLowerCase()
    return (props.filterOptions?.locations || []).filter(location => 
        location.name.toLowerCase().includes(query)
    )
})

// Computed: Build hierarchical category tree
const categoryTree = computed(() => {
    const categories = props.filterOptions?.categories || []
    const map = new Map()
    const roots = []

    // First pass: create map
    categories.forEach(cat => {
        map.set(cat.id, { ...cat, children: [] })
    })

    // Second pass: build tree
    categories.forEach(cat => {
        const node = map.get(cat.id)
        if (cat.parent_id && map.has(cat.parent_id)) {
            map.get(cat.parent_id).children.push(node)
        } else {
            roots.push(node)
        }
    })

    return roots
})

// Initialize filters from current filters prop
onMounted(() => {
    if (props.currentFilters) {
        Object.keys(props.currentFilters).forEach(key => {
            if (filters.hasOwnProperty(key) && props.currentFilters[key] !== undefined) {
                if (Array.isArray(filters[key])) {
                    filters[key] = Array.isArray(props.currentFilters[key]) 
                        ? props.currentFilters[key] 
                        : [props.currentFilters[key]]
                } else if (typeof filters[key] === 'object' && filters[key] !== null) {
                    Object.assign(filters[key], props.currentFilters[key])
                } else {
                    filters[key] = props.currentFilters[key]
                }
            }
        })
    }
})

// Methods
const toggleSection = (section) => {
    expandedSections[section] = !expandedSections[section]
}

const toggleCategory = (categoryId) => {
    if (expandedCategories.value.has(categoryId)) {
        expandedCategories.value.delete(categoryId)
    } else {
        expandedCategories.value.add(categoryId)
    }
}

const toggleCategorySelection = (categoryId) => {
    const index = filters.category_ids.indexOf(categoryId)
    if (index === -1) {
        filters.category_ids.push(categoryId)
    } else {
        filters.category_ids.splice(index, 1)
    }
}

const toggleBrandSelection = (brandId) => {
    const index = filters.brand_ids.indexOf(brandId)
    if (index === -1) {
        filters.brand_ids.push(brandId)
    } else {
        filters.brand_ids.splice(index, 1)
    }
}

const toggleStockStatus = (status) => {
    const index = filters.stock_status.indexOf(status)
    if (index === -1) {
        filters.stock_status.push(status)
    } else {
        filters.stock_status.splice(index, 1)
    }
}

const toggleCondition = (condition) => {
    const index = filters.condition.indexOf(condition)
    if (index === -1) {
        filters.condition.push(condition)
    } else {
        filters.condition.splice(index, 1)
    }
}

const toggleLocation = (locationId) => {
    const index = filters.location_ids.indexOf(locationId)
    if (index === -1) {
        filters.location_ids.push(locationId)
    } else {
        filters.location_ids.splice(index, 1)
    }
}

const clearAllFilters = () => {
    filters.category_ids = []
    filters.brand_ids = []
    filters.stock_status = []
    filters.price_range = { min: null, max: null }
    filters.condition = []
    filters.location_ids = []
    filters.date_range = { created_from: null, created_to: null }
    filters.sort_by = 'created_at'
    filters.sort_order = 'desc'
    emit('filters-reset')
}

const clearSection = (section) => {
    switch (section) {
        case 'categories':
            filters.category_ids = []
            break
        case 'brands':
            filters.brand_ids = []
            break
        case 'stock':
            filters.stock_status = []
            break
        case 'price':
            filters.price_range = { min: null, max: null }
            break
        case 'condition':
            filters.condition = []
            break
        case 'location':
            filters.location_ids = []
            break
        case 'dates':
            filters.date_range = { created_from: null, created_to: null }
            break
    }
}

// Emit filter changes with debounce
const emitFilterChange = debounce(() => {
    const cleanFilters = {}
    
    // Only include non-empty values
    if (filters.category_ids.length) cleanFilters.category_ids = filters.category_ids
    if (filters.brand_ids.length) cleanFilters.brand_ids = filters.brand_ids
    if (filters.stock_status.length) cleanFilters.stock_status = filters.stock_status
    if (filters.price_range.min !== null) cleanFilters.min_price = filters.price_range.min
    if (filters.price_range.max !== null) cleanFilters.max_price = filters.price_range.max
    if (filters.condition.length) cleanFilters.condition = filters.condition
    if (filters.location_ids.length) cleanFilters.location_ids = filters.location_ids
    if (filters.date_range.created_from) cleanFilters.created_from = filters.date_range.created_from
    if (filters.date_range.created_to) cleanFilters.created_to = filters.date_range.created_to
    if (filters.sort_by !== 'created_at') cleanFilters.sort_by = filters.sort_by
    if (filters.sort_order !== 'desc') cleanFilters.sort_order = filters.sort_order

    emit('filter-change', cleanFilters)
}, 300)

// Watch for filter changes
watch(filters, emitFilterChange, { deep: true })

// Recursive category component for tree rendering
const getCategoryCount = (category) => {
    return category.products_count || 0
}
</script>

<template>
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
        <!-- Filter Header -->
        <div class="px-4 py-3 bg-gradient-to-r from-indigo-50 to-purple-50 dark:from-gray-800 dark:to-gray-750 border-b border-gray-200 dark:border-gray-700">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <svg class="w-5 h-5 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
                    </svg>
                    <h3 class="font-semibold text-gray-900 dark:text-white">Filters</h3>
                    <span 
                        v-if="activeFilterCount > 0"
                        class="inline-flex items-center justify-center px-2 py-0.5 text-xs font-medium bg-indigo-100 text-indigo-800 dark:bg-indigo-900/50 dark:text-indigo-300 rounded-full"
                    >
                        {{ activeFilterCount }} active
                    </span>
                </div>
                <button
                    v-if="activeFilterCount > 0"
                    @click="clearAllFilters"
                    class="text-sm text-gray-500 hover:text-red-600 dark:text-gray-400 dark:hover:text-red-400 transition-colors"
                >
                    Clear all
                </button>
            </div>
        </div>

        <div class="divide-y divide-gray-200 dark:divide-gray-700">
            <!-- Categories Section -->
            <div class="p-4">
                <button 
                    @click="toggleSection('categories')"
                    class="w-full flex items-center justify-between text-left"
                >
                    <span class="font-medium text-gray-700 dark:text-gray-300">Categories</span>
                    <div class="flex items-center gap-2">
                        <span 
                            v-if="filters.category_ids.length"
                            class="text-xs text-indigo-600 dark:text-indigo-400"
                        >
                            {{ filters.category_ids.length }} selected
                        </span>
                        <svg 
                            :class="['w-5 h-5 text-gray-400 transition-transform', expandedSections.categories ? 'rotate-180' : '']"
                            fill="none" stroke="currentColor" viewBox="0 0 24 24"
                        >
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                        </svg>
                    </div>
                </button>
                
                <div v-show="expandedSections.categories" class="mt-3 space-y-1 max-h-64 overflow-y-auto">
                    <!-- Category Tree -->
                    <template v-for="category in categoryTree" :key="category.id">
                        <div class="category-item">
                            <div class="flex items-center gap-2 py-1.5 px-2 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                <button
                                    v-if="category.children?.length"
                                    @click.stop="toggleCategory(category.id)"
                                    class="p-0.5 hover:bg-gray-200 dark:hover:bg-gray-600 rounded"
                                >
                                    <svg 
                                        :class="['w-4 h-4 text-gray-400 transition-transform', expandedCategories.has(category.id) ? 'rotate-90' : '']"
                                        fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                    >
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                    </svg>
                                </button>
                                <span v-else class="w-5"></span>
                                
                                <label class="flex items-center gap-2 flex-1 cursor-pointer">
                                    <input
                                        type="checkbox"
                                        :checked="filters.category_ids.includes(category.id)"
                                        @change="toggleCategorySelection(category.id)"
                                        class="w-4 h-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-700"
                                    />
                                    <span class="text-sm text-gray-700 dark:text-gray-300">{{ category.name }}</span>
                                    <span class="text-xs text-gray-400">({{ getCategoryCount(category) }})</span>
                                </label>
                            </div>
                            
                            <!-- Child Categories -->
                            <div 
                                v-if="category.children?.length && expandedCategories.has(category.id)"
                                class="ml-6 mt-1 space-y-1 border-l-2 border-gray-200 dark:border-gray-600 pl-2"
                            >
                                <template v-for="child in category.children" :key="child.id">
                                    <div class="flex items-center gap-2 py-1.5 px-2 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                        <label class="flex items-center gap-2 flex-1 cursor-pointer">
                                            <input
                                                type="checkbox"
                                                :checked="filters.category_ids.includes(child.id)"
                                                @change="toggleCategorySelection(child.id)"
                                                class="w-4 h-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-700"
                                            />
                                            <span class="text-sm text-gray-700 dark:text-gray-300">{{ child.name }}</span>
                                            <span class="text-xs text-gray-400">({{ getCategoryCount(child) }})</span>
                                        </label>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </template>
                </div>
            </div>

            <!-- Brands Section -->
            <div class="p-4">
                <button 
                    @click="toggleSection('brands')"
                    class="w-full flex items-center justify-between text-left"
                >
                    <span class="font-medium text-gray-700 dark:text-gray-300">Brands</span>
                    <div class="flex items-center gap-2">
                        <span 
                            v-if="filters.brand_ids.length"
                            class="text-xs text-indigo-600 dark:text-indigo-400"
                        >
                            {{ filters.brand_ids.length }} selected
                        </span>
                        <svg 
                            :class="['w-5 h-5 text-gray-400 transition-transform', expandedSections.brands ? 'rotate-180' : '']"
                            fill="none" stroke="currentColor" viewBox="0 0 24 24"
                        >
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                        </svg>
                    </div>
                </button>
                
                <div v-show="expandedSections.brands" class="mt-3">
                    <!-- Brand Search -->
                    <div class="relative mb-3">
                        <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                        <input
                            v-model="brandSearchQuery"
                            type="text"
                            placeholder="Search brands..."
                            class="w-full pl-9 pr-4 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white placeholder-gray-400 focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                        />
                    </div>
                    
                    <div class="space-y-1 max-h-48 overflow-y-auto">
                        <label 
                            v-for="brand in filteredBrands" 
                            :key="brand.id"
                            class="flex items-center gap-2 py-1.5 px-2 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700/50 cursor-pointer"
                        >
                            <input
                                type="checkbox"
                                :checked="filters.brand_ids.includes(brand.id)"
                                @change="toggleBrandSelection(brand.id)"
                                class="w-4 h-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-700"
                            />
                            <span class="text-sm text-gray-700 dark:text-gray-300">{{ brand.name }}</span>
                            <span v-if="brand.products_count" class="text-xs text-gray-400">({{ brand.products_count }})</span>
                        </label>
                    </div>
                </div>
            </div>

            <!-- Stock Status Section -->
            <div class="p-4">
                <button 
                    @click="toggleSection('stock')"
                    class="w-full flex items-center justify-between text-left"
                >
                    <span class="font-medium text-gray-700 dark:text-gray-300">Stock Status</span>
                    <div class="flex items-center gap-2">
                        <span 
                            v-if="filters.stock_status.length"
                            class="text-xs text-indigo-600 dark:text-indigo-400"
                        >
                            {{ filters.stock_status.length }} selected
                        </span>
                        <svg 
                            :class="['w-5 h-5 text-gray-400 transition-transform', expandedSections.stock ? 'rotate-180' : '']"
                            fill="none" stroke="currentColor" viewBox="0 0 24 24"
                        >
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                        </svg>
                    </div>
                </button>
                
                <div v-show="expandedSections.stock" class="mt-3 flex flex-wrap gap-2">
                    <button
                        v-for="option in stockStatusOptions"
                        :key="option.value"
                        @click="toggleStockStatus(option.value)"
                        :class="[
                            'inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-sm font-medium transition-all',
                            filters.stock_status.includes(option.value)
                                ? 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900/50 dark:text-indigo-300 ring-2 ring-indigo-500'
                                : 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600'
                        ]"
                    >
                        <span :class="['w-2 h-2 rounded-full', option.color]"></span>
                        {{ option.label }}
                    </button>
                </div>
            </div>

            <!-- Price Range Section -->
            <div class="p-4">
                <button 
                    @click="toggleSection('price')"
                    class="w-full flex items-center justify-between text-left"
                >
                    <span class="font-medium text-gray-700 dark:text-gray-300">Price Range</span>
                    <div class="flex items-center gap-2">
                        <span 
                            v-if="filters.price_range.min !== null || filters.price_range.max !== null"
                            class="text-xs text-indigo-600 dark:text-indigo-400"
                        >
                            ${{ filters.price_range.min || 0 }} - ${{ filters.price_range.max || '∞' }}
                        </span>
                        <svg 
                            :class="['w-5 h-5 text-gray-400 transition-transform', expandedSections.price ? 'rotate-180' : '']"
                            fill="none" stroke="currentColor" viewBox="0 0 24 24"
                        >
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                        </svg>
                    </div>
                </button>
                
                <div v-show="expandedSections.price" class="mt-3">
                    <div class="flex items-center gap-3">
                        <div class="flex-1">
                            <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Min Price</label>
                            <div class="relative">
                                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400">$</span>
                                <input
                                    v-model.number="filters.price_range.min"
                                    type="number"
                                    min="0"
                                    :max="filters.price_range.max || undefined"
                                    :placeholder="String(filterOptions?.priceRange?.min || 0)"
                                    class="w-full pl-7 pr-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                                />
                            </div>
                        </div>
                        <span class="text-gray-400 mt-5">–</span>
                        <div class="flex-1">
                            <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Max Price</label>
                            <div class="relative">
                                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400">$</span>
                                <input
                                    v-model.number="filters.price_range.max"
                                    type="number"
                                    :min="filters.price_range.min || 0"
                                    :placeholder="String(filterOptions?.priceRange?.max || 1000)"
                                    class="w-full pl-7 pr-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                                />
                            </div>
                        </div>
                    </div>
                    
                    <!-- Quick Price Ranges -->
                    <div class="mt-3 flex flex-wrap gap-2">
                        <button
                            v-for="range in [
                                { label: 'Under $25', min: 0, max: 25 },
                                { label: '$25 - $50', min: 25, max: 50 },
                                { label: '$50 - $100', min: 50, max: 100 },
                                { label: '$100+', min: 100, max: null }
                            ]"
                            :key="range.label"
                            @click="filters.price_range = { min: range.min, max: range.max }"
                            class="px-2.5 py-1 text-xs rounded-md bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300 hover:bg-indigo-100 hover:text-indigo-700 dark:hover:bg-indigo-900/50 dark:hover:text-indigo-300 transition-colors"
                        >
                            {{ range.label }}
                        </button>
                    </div>
                </div>
            </div>

            <!-- Advanced Filters Toggle -->
            <div class="p-4">
                <button 
                    @click="isAdvancedOpen = !isAdvancedOpen"
                    class="w-full flex items-center justify-center gap-2 py-2 text-sm text-indigo-600 dark:text-indigo-400 hover:text-indigo-700 dark:hover:text-indigo-300 transition-colors"
                >
                    <span>{{ isAdvancedOpen ? 'Hide' : 'Show' }} Advanced Filters</span>
                    <svg 
                        :class="['w-4 h-4 transition-transform', isAdvancedOpen ? 'rotate-180' : '']"
                        fill="none" stroke="currentColor" viewBox="0 0 24 24"
                    >
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </button>
            </div>

            <!-- Advanced Filters Section -->
            <div v-show="isAdvancedOpen" class="border-t border-gray-200 dark:border-gray-700 divide-y divide-gray-200 dark:divide-gray-700">
                <!-- Condition Section -->
                <div class="p-4">
                    <button 
                        @click="toggleSection('condition')"
                        class="w-full flex items-center justify-between text-left"
                    >
                        <span class="font-medium text-gray-700 dark:text-gray-300">Condition</span>
                        <div class="flex items-center gap-2">
                            <span 
                                v-if="filters.condition.length"
                                class="text-xs text-indigo-600 dark:text-indigo-400"
                            >
                                {{ filters.condition.length }} selected
                            </span>
                            <svg 
                                :class="['w-5 h-5 text-gray-400 transition-transform', expandedSections.condition ? 'rotate-180' : '']"
                                fill="none" stroke="currentColor" viewBox="0 0 24 24"
                            >
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </div>
                    </button>
                    
                    <div v-show="expandedSections.condition" class="mt-3 flex flex-wrap gap-2">
                        <button
                            v-for="condition in (filterOptions?.conditions || [])"
                            :key="condition.value"
                            @click="toggleCondition(condition.value)"
                            :class="[
                                'px-3 py-1.5 rounded-lg text-sm font-medium transition-all',
                                filters.condition.includes(condition.value)
                                    ? 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900/50 dark:text-indigo-300 ring-2 ring-indigo-500'
                                    : 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600'
                            ]"
                        >
                            {{ condition.label }}
                        </button>
                    </div>
                </div>

                <!-- Location Section -->
                <div v-if="permissions?.can_view_all_locations" class="p-4">
                    <button 
                        @click="toggleSection('location')"
                        class="w-full flex items-center justify-between text-left"
                    >
                        <span class="font-medium text-gray-700 dark:text-gray-300">Location</span>
                        <div class="flex items-center gap-2">
                            <span 
                                v-if="filters.location_ids.length"
                                class="text-xs text-indigo-600 dark:text-indigo-400"
                            >
                                {{ filters.location_ids.length }} selected
                            </span>
                            <svg 
                                :class="['w-5 h-5 text-gray-400 transition-transform', expandedSections.location ? 'rotate-180' : '']"
                                fill="none" stroke="currentColor" viewBox="0 0 24 24"
                            >
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </div>
                    </button>
                    
                    <div v-show="expandedSections.location" class="mt-3">
                        <div class="relative mb-3">
                            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                            <input
                                v-model="locationSearchQuery"
                                type="text"
                                placeholder="Search locations..."
                                class="w-full pl-9 pr-4 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white placeholder-gray-400 focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                            />
                        </div>
                        
                        <div class="space-y-1 max-h-40 overflow-y-auto">
                            <label 
                                v-for="location in filteredLocations" 
                                :key="location.id"
                                class="flex items-center gap-2 py-1.5 px-2 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700/50 cursor-pointer"
                            >
                                <input
                                    type="checkbox"
                                    :checked="filters.location_ids.includes(location.id)"
                                    @change="toggleLocation(location.id)"
                                    class="w-4 h-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-700"
                                />
                                <span class="text-sm text-gray-700 dark:text-gray-300">{{ location.name }}</span>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Date Range Section -->
                <div class="p-4">
                    <button 
                        @click="toggleSection('dates')"
                        class="w-full flex items-center justify-between text-left"
                    >
                        <span class="font-medium text-gray-700 dark:text-gray-300">Date Created</span>
                        <div class="flex items-center gap-2">
                            <span 
                                v-if="filters.date_range.created_from || filters.date_range.created_to"
                                class="text-xs text-indigo-600 dark:text-indigo-400"
                            >
                                Custom range
                            </span>
                            <svg 
                                :class="['w-5 h-5 text-gray-400 transition-transform', expandedSections.dates ? 'rotate-180' : '']"
                                fill="none" stroke="currentColor" viewBox="0 0 24 24"
                            >
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </div>
                    </button>
                    
                    <div v-show="expandedSections.dates" class="mt-3">
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">From</label>
                                <input
                                    v-model="filters.date_range.created_from"
                                    type="date"
                                    class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                                />
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">To</label>
                                <input
                                    v-model="filters.date_range.created_to"
                                    type="date"
                                    class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                                />
                            </div>
                        </div>
                        
                        <!-- Quick Date Ranges -->
                        <div class="mt-3 flex flex-wrap gap-2">
                            <button
                                v-for="preset in [
                                    { label: 'Today', days: 0 },
                                    { label: 'Last 7 days', days: 7 },
                                    { label: 'Last 30 days', days: 30 },
                                    { label: 'Last 90 days', days: 90 }
                                ]"
                                :key="preset.label"
                                @click="() => {
                                    const today = new Date()
                                    const from = new Date()
                                    from.setDate(today.getDate() - preset.days)
                                    filters.date_range.created_from = from.toISOString().split('T')[0]
                                    filters.date_range.created_to = today.toISOString().split('T')[0]
                                }"
                                class="px-2.5 py-1 text-xs rounded-md bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300 hover:bg-indigo-100 hover:text-indigo-700 dark:hover:bg-indigo-900/50 dark:hover:text-indigo-300 transition-colors"
                            >
                                {{ preset.label }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sort Section -->
            <div class="p-4 bg-gray-50 dark:bg-gray-750">
                <div class="flex items-center gap-3">
                    <label class="text-sm font-medium text-gray-700 dark:text-gray-300 whitespace-nowrap">Sort by:</label>
                    <select
                        v-model="filters.sort_by"
                        class="flex-1 px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                    >
                        <option v-for="option in sortOptions" :key="option.value" :value="option.value">
                            {{ option.label }}
                        </option>
                    </select>
                    <button
                        @click="filters.sort_order = filters.sort_order === 'asc' ? 'desc' : 'asc'"
                        class="p-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors"
                        :title="filters.sort_order === 'asc' ? 'Ascending' : 'Descending'"
                    >
                        <svg 
                            :class="['w-5 h-5 text-gray-600 dark:text-gray-300 transition-transform', filters.sort_order === 'asc' ? 'rotate-180' : '']"
                            fill="none" stroke="currentColor" viewBox="0 0 24 24"
                        >
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4h13M3 8h9m-9 4h6m4 0l4-4m0 0l4 4m-4-4v12" />
                        </svg>
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* Custom scrollbar for filter sections */
.max-h-64::-webkit-scrollbar,
.max-h-48::-webkit-scrollbar,
.max-h-40::-webkit-scrollbar {
    width: 6px;
}

.max-h-64::-webkit-scrollbar-track,
.max-h-48::-webkit-scrollbar-track,
.max-h-40::-webkit-scrollbar-track {
    background: transparent;
}

.max-h-64::-webkit-scrollbar-thumb,
.max-h-48::-webkit-scrollbar-thumb,
.max-h-40::-webkit-scrollbar-thumb {
    background-color: rgba(156, 163, 175, 0.5);
    border-radius: 3px;
}

.dark .max-h-64::-webkit-scrollbar-thumb,
.dark .max-h-48::-webkit-scrollbar-thumb,
.dark .max-h-40::-webkit-scrollbar-thumb {
    background-color: rgba(75, 85, 99, 0.5);
}
</style>
