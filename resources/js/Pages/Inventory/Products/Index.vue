<script setup>
import { ref, reactive, computed, watch, onMounted } from 'vue'
import { router, usePage, Head, Link } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import AdvancedProductFilter from '@/Components/Inventory/AdvancedProductFilter.vue'
import ProductSearch from '@/Components/Inventory/ProductSearch.vue'
import FilterPresets from '@/Components/Inventory/FilterPresets.vue'
import Pagination from '@/Components/Pagination.vue'
import ActionToolbar from '@/Components/ActionToolbar.vue'

const props = defineProps({
    products: {
        type: Object,
        required: true
    },
    filters: {
        type: Object,
        default: () => ({})
    },
    filterOptions: {
        type: Object,
        default: () => ({})
    },
    savedSearches: {
        type: Array,
        default: () => []
    },
    permissions: {
        type: Object,
        default: () => ({})
    },
    stats: {
        type: Object,
        default: () => ({})
    },
    viewMode: {
        type: String,
        default: 'grid'
    },
    showArchived: {
        type: Boolean,
        default: false
    }
})

// State
const currentFilters = reactive({ ...props.filters })
const currentViewMode = ref(props.viewMode)
const selectedProducts = ref([])
const showFilters = ref(true)
const isLoading = ref(false)
const showBulkActions = ref(false)

// Computed
const hasSelectedProducts = computed(() => selectedProducts.value.length > 0)

const allSelected = computed(() => {
    if (!props.products?.data?.length) return false
    return props.products.data.every(p => selectedProducts.value.includes(p.id))
})

const someSelected = computed(() => {
    return selectedProducts.value.length > 0 && !allSelected.value
})

// Page actions for the toolbar
const pageActions = computed(() => {
    const actions = []
    if (props.permissions.can_create) {
        actions.push({
            label: 'Add Product',
            icon: 'fas fa-plus',
            href: route('intake-queue.create'),
            variant: 'primary'
        })
    }
    return actions
})

// Methods
const applyFilters = (newFilters) => {
    Object.assign(currentFilters, newFilters)
    fetchProducts()
}

const handleSearch = ({ query, scope }) => {
    currentFilters.search = query
    if (scope !== 'all') {
        currentFilters.search_scope = scope
    } else {
        delete currentFilters.search_scope
    }
    fetchProducts()
}

const clearSearch = () => {
    currentFilters.search = ''
    delete currentFilters.search_scope
    fetchProducts()
}

const handleFilterChange = (filters) => {
    Object.assign(currentFilters, filters)
    fetchProducts()
}

const handleFiltersReset = () => {
    Object.keys(currentFilters).forEach(key => {
        delete currentFilters[key]
    })
    fetchProducts()
}

const applyPreset = (filters) => {
    Object.keys(currentFilters).forEach(key => {
        delete currentFilters[key]
    })
    Object.assign(currentFilters, filters)
    fetchProducts()
}

const fetchProducts = () => {
    isLoading.value = true
    
    // Clean up empty values
    const cleanFilters = {}
    Object.keys(currentFilters).forEach(key => {
        const value = currentFilters[key]
        if (value !== null && value !== '' && value !== undefined) {
            if (Array.isArray(value) && value.length > 0) {
                cleanFilters[key] = value
            } else if (!Array.isArray(value)) {
                cleanFilters[key] = value
            }
        }
    })
    
    cleanFilters.view_mode = currentViewMode.value
    
    router.get(route('inventory.products.index'), cleanFilters, {
        preserveState: true,
        preserveScroll: true,
        onFinish: () => {
            isLoading.value = false
        }
    })
}

const toggleViewMode = (mode) => {
    currentViewMode.value = mode
    fetchProducts()
}

const toggleSelectAll = () => {
    if (allSelected.value) {
        selectedProducts.value = []
    } else {
        selectedProducts.value = props.products.data.map(p => p.id)
    }
}

const toggleProductSelection = (productId) => {
    const index = selectedProducts.value.indexOf(productId)
    if (index === -1) {
        selectedProducts.value.push(productId)
    } else {
        selectedProducts.value.splice(index, 1)
    }
}

const performBulkAction = (action) => {
    if (!hasSelectedProducts.value) return

    router.post(route('inventory.products.bulk-action'), {
        action: action,
        product_ids: selectedProducts.value
    }, {
        preserveState: true,
        onSuccess: () => {
            selectedProducts.value = []
        }
    })
}

const toggleArchivedView = () => {
    if (props.showArchived) {
        // Go back to normal view - remove status filter
        delete currentFilters.status
        fetchProducts()
    } else {
        // Show archived products
        currentFilters.status = 'archived'
        fetchProducts()
    }
}

const formatCurrency = (value) => {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD'
    }).format(value)
}

const getStockStatusClass = (product) => {
    if (product.stock_quantity === 0) {
        return 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400'
    }
    if (product.stock_quantity <= product.low_stock_threshold) {
        return 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400'
    }
    return 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-400'
}

const getStockStatusLabel = (product) => {
    if (product.stock_quantity === 0) return 'Out of Stock'
    if (product.stock_quantity <= product.low_stock_threshold) return 'Low Stock'
    return 'In Stock'
}

// Watch for filter changes from URL
watch(() => props.filters, (newFilters) => {
    Object.assign(currentFilters, newFilters)
}, { deep: true })
</script>

<template>
    <AppLayout title="Products" pageTitle="Products" :page-actions="pageActions">
        <Head title="Products" />

        <div class="py-6">
            <div class="max-w-full mx-auto px-4 sm:px-6 lg:px-8">
                <!-- Search Bar -->
                <div class="mb-4">
                    <ProductSearch
                        :model-value="currentFilters.search || ''"
                        :search-suggestions-url="route('inventory.products.search-suggestions')"
                        @search="handleSearch"
                        @clear="clearSearch"
                    />
                </div>

                <!-- Filter Presets -->
                <div class="mb-4">
                    <FilterPresets
                        :presets="savedSearches"
                        :current-filters="currentFilters"
                        :save-url="route('inventory.products.filter-presets.store')"
                        :delete-url="route('inventory.products.filter-presets.destroy', { preset: '__ID__' }).replace('__ID__', '')"
                        @preset-applied="applyPreset"
                    />
                </div>

                <!-- Archived View Banner -->
                <div v-if="showArchived" class="mb-4 rounded-lg border border-amber-300 bg-amber-50 dark:bg-amber-900/20 dark:border-amber-700 p-4 flex items-center justify-between">
                    <div class="flex items-center">
                        <svg class="w-5 h-5 text-amber-600 dark:text-amber-400 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4" />
                        </svg>
                        <p class="text-sm font-medium text-amber-800 dark:text-amber-300">
                            Viewing archived products. These are hidden from your active inventory and marketplace listings.
                        </p>
                    </div>
                    <button
                        @click="toggleArchivedView"
                        class="ml-4 text-sm font-medium text-amber-700 dark:text-amber-400 hover:text-amber-900 dark:hover:text-amber-200 underline"
                    >
                        Back to active
                    </button>
                </div>

                <!-- Stats Cards -->
                <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-6 gap-4 mb-6">
                    <div class="bg-white dark:bg-gray-800 rounded-xl p-4 border border-gray-200 dark:border-gray-700">
                        <div class="text-sm text-gray-500 dark:text-gray-400">Total Products</div>
                        <div class="text-2xl font-bold text-gray-900 dark:text-white">{{ stats.total_products?.toLocaleString() || 0 }}</div>
                    </div>
                    <div class="bg-white dark:bg-gray-800 rounded-xl p-4 border border-gray-200 dark:border-gray-700">
                        <div class="text-sm text-gray-500 dark:text-gray-400">Active</div>
                        <div class="text-2xl font-bold text-emerald-600 dark:text-emerald-400">{{ stats.active_products?.toLocaleString() || 0 }}</div>
                    </div>
                    <div class="bg-white dark:bg-gray-800 rounded-xl p-4 border border-gray-200 dark:border-gray-700">
                        <div class="text-sm text-gray-500 dark:text-gray-400">Low Stock</div>
                        <div class="text-2xl font-bold text-amber-600 dark:text-amber-400">{{ stats.low_stock_count?.toLocaleString() || 0 }}</div>
                    </div>
                    <div class="bg-white dark:bg-gray-800 rounded-xl p-4 border border-gray-200 dark:border-gray-700">
                        <div class="text-sm text-gray-500 dark:text-gray-400">Out of Stock</div>
                        <div class="text-2xl font-bold text-red-600 dark:text-red-400">{{ stats.out_of_stock_count?.toLocaleString() || 0 }}</div>
                    </div>
                    <div class="bg-white dark:bg-gray-800 rounded-xl p-4 border border-gray-200 dark:border-gray-700">
                        <div class="text-sm text-gray-500 dark:text-gray-400">Inventory Value</div>
                        <div class="text-xl font-bold text-gray-900 dark:text-white">${{ stats.total_retail_value || '0.00' }}</div>
                    </div>
                    <div class="bg-white dark:bg-gray-800 rounded-xl p-4 border border-gray-200 dark:border-gray-700">
                        <div class="text-sm text-gray-500 dark:text-gray-400">Total Stock</div>
                        <div class="text-2xl font-bold text-gray-900 dark:text-white">{{ stats.total_stock?.toLocaleString() || 0 }}</div>
                    </div>
                    <button
                        v-if="stats.archived_count > 0"
                        @click="toggleArchivedView"
                        class="bg-white dark:bg-gray-800 rounded-xl p-4 border border-gray-200 dark:border-gray-700 text-left hover:border-amber-300 dark:hover:border-amber-600 transition-colors"
                    >
                        <div class="text-sm text-gray-500 dark:text-gray-400">{{ showArchived ? 'Viewing Archived' : 'Archived' }}</div>
                        <div class="text-2xl font-bold text-amber-600 dark:text-amber-400">{{ stats.archived_count?.toLocaleString() || 0 }}</div>
                    </button>
                </div>

                <!-- Main Content Area -->
                <div class="flex gap-6">
                    <!-- Filters Sidebar -->
                    <div 
                        v-show="showFilters"
                        class="w-80 flex-shrink-0"
                    >
                        <AdvancedProductFilter
                            :filter-options="filterOptions"
                            :current-filters="currentFilters"
                            :permissions="permissions"
                            @filter-change="handleFilterChange"
                            @filters-reset="handleFiltersReset"
                        />
                    </div>

                    <!-- Products List/Grid -->
                    <div class="flex-1 min-w-0">
                        <!-- Toolbar -->
                        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4 mb-4">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-4">
                                    <!-- Toggle Filters -->
                                    <button
                                        @click="showFilters = !showFilters"
                                        class="inline-flex items-center gap-2 px-3 py-1.5 text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white transition-colors"
                                    >
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
                                        </svg>
                                        {{ showFilters ? 'Hide Filters' : 'Show Filters' }}
                                    </button>
                                    
                                    <!-- Select All -->
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input
                                            type="checkbox"
                                            :checked="allSelected"
                                            :indeterminate="someSelected"
                                            @change="toggleSelectAll"
                                            class="w-4 h-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-700"
                                        />
                                        <span class="text-sm text-gray-600 dark:text-gray-400">Select all</span>
                                    </label>
                                    
                                    <!-- Bulk Actions -->
                                    <div v-if="hasSelectedProducts" class="flex items-center gap-2">
                                        <span class="text-sm text-indigo-600 dark:text-indigo-400 font-medium">
                                            {{ selectedProducts.length }} selected
                                        </span>
                                        <template v-if="showArchived">
                                            <!-- Archived view: Restore and Permanently Delete -->
                                            <button
                                                v-if="permissions.can_restore"
                                                @click="performBulkAction('restore')"
                                                class="px-2 py-1 text-xs bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400 rounded hover:bg-emerald-200 dark:hover:bg-emerald-900/50 transition-colors"
                                            >
                                                Restore
                                            </button>
                                            <button
                                                v-if="permissions.can_force_delete"
                                                @click="performBulkAction('force_delete')"
                                                class="px-2 py-1 text-xs bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400 rounded hover:bg-red-200 dark:hover:bg-red-900/50 transition-colors"
                                            >
                                                Permanently Delete
                                            </button>
                                        </template>
                                        <template v-else>
                                            <!-- Normal view: Activate, Archive -->
                                            <button
                                                @click="performBulkAction('activate')"
                                                class="px-2 py-1 text-xs bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400 rounded hover:bg-emerald-200 dark:hover:bg-emerald-900/50 transition-colors"
                                            >
                                                Activate
                                            </button>
                                            <button
                                                v-if="permissions.can_delete"
                                                @click="performBulkAction('delete')"
                                                class="px-2 py-1 text-xs bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400 rounded hover:bg-red-200 dark:hover:bg-red-900/50 transition-colors"
                                            >
                                                Archive
                                            </button>
                                        </template>
                                    </div>
                                </div>
                                
                                <div class="flex items-center gap-2">
                                    <!-- Results count -->
                                    <span class="text-sm text-gray-500 dark:text-gray-400">
                                        {{ products.meta?.total?.toLocaleString() || products.data?.length || 0 }} results
                                    </span>
                                    
                                    <!-- View Mode Toggle -->
                                    <div class="flex items-center bg-gray-100 dark:bg-gray-700 rounded-lg p-1">
                                        <button
                                            @click="toggleViewMode('grid')"
                                            :class="[
                                                'p-1.5 rounded transition-colors',
                                                currentViewMode === 'grid'
                                                    ? 'bg-white dark:bg-gray-600 text-indigo-600 dark:text-indigo-400 shadow-sm'
                                                    : 'text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300'
                                            ]"
                                        >
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" />
                                            </svg>
                                        </button>
                                        <button
                                            @click="toggleViewMode('list')"
                                            :class="[
                                                'p-1.5 rounded transition-colors',
                                                currentViewMode === 'list'
                                                    ? 'bg-white dark:bg-gray-600 text-indigo-600 dark:text-indigo-400 shadow-sm'
                                                    : 'text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300'
                                            ]"
                                        >
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16" />
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Loading Overlay -->
                        <div v-if="isLoading" class="fixed inset-0 bg-black/20 dark:bg-black/40 z-40 flex items-center justify-center">
                            <div class="bg-white dark:bg-gray-800 rounded-xl p-6 shadow-xl flex items-center gap-3">
                                <svg class="w-6 h-6 text-indigo-600 animate-spin" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                <span class="text-gray-700 dark:text-gray-300">Loading products...</span>
                            </div>
                        </div>

                        <!-- Grid View -->
                        <div v-if="currentViewMode === 'grid'" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                            <div
                                v-for="product in products.data"
                                :key="product.id"
                                :class="[
                                    'group bg-white dark:bg-gray-800 rounded-xl border overflow-hidden hover:shadow-lg transition-all',
                                    showArchived
                                        ? 'border-amber-200 dark:border-amber-800 opacity-80'
                                        : 'border-gray-200 dark:border-gray-700'
                                ]"
                            >
                                <!-- Image -->
                                <div class="relative aspect-square bg-gray-100 dark:bg-gray-700">
                                    <img
                                        v-if="product.images?.[0]?.url"
                                        :src="product.images[0].url"
                                        :alt="product.name"
                                        class="w-full h-full object-cover"
                                    />
                                    <div v-else class="w-full h-full flex items-center justify-center text-gray-400">
                                        <svg class="w-16 h-16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                        </svg>
                                    </div>
                                    
                                    <!-- Selection Checkbox -->
                                    <div class="absolute top-2 left-2">
                                        <input
                                            type="checkbox"
                                            :checked="selectedProducts.includes(product.id)"
                                            @change="toggleProductSelection(product.id)"
                                            class="w-5 h-5 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500 bg-white dark:bg-gray-800 shadow-sm"
                                        />
                                    </div>
                                    
                                    <!-- Stock Badge -->
                                    <div class="absolute top-2 right-2">
                                        <span :class="['px-2 py-1 text-xs font-medium rounded-full', getStockStatusClass(product)]">
                                            {{ getStockStatusLabel(product) }}
                                        </span>
                                    </div>
                                    
                                    <!-- Quick Actions -->
                                    <div class="absolute bottom-2 right-2 flex gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                                        <Link
                                            :href="route('inventory.products.show', product.id)"
                                            class="p-2 bg-white dark:bg-gray-800 rounded-lg shadow-sm hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors"
                                        >
                                            <svg class="w-4 h-4 text-gray-600 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                            </svg>
                                        </Link>
                                        <Link
                                            v-if="permissions.can_update"
                                            :href="route('inventory.products.edit', product.id)"
                                            class="p-2 bg-white dark:bg-gray-800 rounded-lg shadow-sm hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors"
                                        >
                                            <svg class="w-4 h-4 text-gray-600 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                            </svg>
                                        </Link>
                                    </div>
                                </div>
                                
                                <!-- Content -->
                                <div class="p-4">
                                    <div class="mb-2">
                                        <Link
                                            :href="route('inventory.products.show', product.id)"
                                            class="font-medium text-gray-900 dark:text-white hover:text-indigo-600 dark:hover:text-indigo-400 line-clamp-2"
                                        >
                                            {{ product.name }}
                                        </Link>
                                    </div>
                                    
                                    <div class="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400 mb-2">
                                        <span>SKU: {{ product.sku }}</span>
                                        <span v-if="product.category" class="px-1.5 py-0.5 bg-gray-100 dark:bg-gray-700 rounded text-xs">
                                            {{ product.category.name }}
                                        </span>
                                    </div>
                                    
                                    <div class="flex items-center justify-between">
                                        <div class="text-lg font-bold text-gray-900 dark:text-white">
                                            {{ formatCurrency(product.selling_price) }}
                                        </div>
                                        <div class="text-sm text-gray-500 dark:text-gray-400">
                                            {{ product.stock_quantity }} in stock
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- List View -->
                        <div v-else class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
                            <table class="w-full">
                                <thead class="bg-gray-50 dark:bg-gray-750">
                                    <tr>
                                        <th class="w-10 px-4 py-3">
                                            <input
                                                type="checkbox"
                                                :checked="allSelected"
                                                :indeterminate="someSelected"
                                                @change="toggleSelectAll"
                                                class="w-4 h-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-700"
                                            />
                                        </th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 dark:text-gray-400 uppercase">Product</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 dark:text-gray-400 uppercase">SKU</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 dark:text-gray-400 uppercase">Category</th>
                                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 dark:text-gray-400 uppercase">Price</th>
                                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 dark:text-gray-400 uppercase">Stock</th>
                                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 dark:text-gray-400 uppercase">Status</th>
                                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 dark:text-gray-400 uppercase">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                    <tr 
                                        v-for="product in products.data" 
                                        :key="product.id"
                                        class="hover:bg-gray-50 dark:hover:bg-gray-750 transition-colors"
                                    >
                                        <td class="px-4 py-3">
                                            <input
                                                type="checkbox"
                                                :checked="selectedProducts.includes(product.id)"
                                                @change="toggleProductSelection(product.id)"
                                                class="w-4 h-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-700"
                                            />
                                        </td>
                                        <td class="px-4 py-3">
                                            <div class="flex items-center gap-3">
                                                <div class="w-12 h-12 bg-gray-100 dark:bg-gray-700 rounded-lg overflow-hidden flex-shrink-0">
                                                    <img
                                                        v-if="product.images?.[0]?.url"
                                                        :src="product.images[0].url"
                                                        :alt="product.name"
                                                        class="w-full h-full object-cover"
                                                    />
                                                    <div v-else class="w-full h-full flex items-center justify-center text-gray-400">
                                                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                                        </svg>
                                                    </div>
                                                </div>
                                                <Link
                                                    :href="route('inventory.products.show', product.id)"
                                                    class="font-medium text-gray-900 dark:text-white hover:text-indigo-600 dark:hover:text-indigo-400"
                                                >
                                                    {{ product.name }}
                                                </Link>
                                            </div>
                                        </td>
                                        <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-400">
                                            {{ product.sku }}
                                        </td>
                                        <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-400">
                                            {{ product.category?.name || '-' }}
                                        </td>
                                        <td class="px-4 py-3 text-right font-medium text-gray-900 dark:text-white">
                                            {{ formatCurrency(product.selling_price) }}
                                        </td>
                                        <td class="px-4 py-3 text-right text-sm text-gray-600 dark:text-gray-400">
                                            {{ product.stock_quantity }}
                                        </td>
                                        <td class="px-4 py-3 text-center">
                                            <span :class="['px-2 py-1 text-xs font-medium rounded-full', getStockStatusClass(product)]">
                                                {{ getStockStatusLabel(product) }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-right">
                                            <div class="flex items-center justify-end gap-1">
                                                <Link
                                                    :href="route('inventory.products.show', product.id)"
                                                    class="p-1.5 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition-colors"
                                                >
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                                    </svg>
                                                </Link>
                                                <Link
                                                    v-if="permissions.can_update"
                                                    :href="route('inventory.products.edit', product.id)"
                                                    class="p-1.5 text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors"
                                                >
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                    </svg>
                                                </Link>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Empty State -->
                        <div 
                            v-if="!products.data?.length && !isLoading"
                            class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-12 text-center"
                        >
                            <svg class="w-16 h-16 mx-auto mb-4 text-gray-300 dark:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                            </svg>
                            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-2">No products found</h3>
                            <p class="text-gray-500 dark:text-gray-400 mb-6">
                                {{ Object.keys(currentFilters).length > 0 
                                    ? 'Try adjusting your filters or search criteria'
                                    : 'Get started by adding your first product' 
                                }}
                            </p>
                            <div class="flex items-center justify-center gap-3">
                                <button
                                    v-if="Object.keys(currentFilters).length > 0"
                                    @click="handleFiltersReset"
                                    class="px-4 py-2 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors"
                                >
                                    Clear Filters
                                </button>
                                <Link
                                    v-if="permissions.can_create"
                                    :href="route('inventory.products.create')"
                                    class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg transition-colors"
                                >
                                    Add Product
                                </Link>
                            </div>
                        </div>

                        <!-- Pagination -->
                        <div v-if="products.meta?.last_page > 1" class="mt-6">
                            <Pagination :links="products.links" :meta="products.meta" />
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
.line-clamp-2 {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
</style>
