<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue'
import { router } from '@inertiajs/vue3'
import debounce from 'lodash/debounce'

const props = defineProps({
    modelValue: {
        type: String,
        default: ''
    },
    placeholder: {
        type: String,
        default: 'Search products, suppliers, orders...'
    },
    searchUrl: {
        type: String,
        default: '/api/omni-search'
    },
    autofocus: {
        type: Boolean,
        default: false
    }
})

const emit = defineEmits(['update:modelValue', 'search', 'select'])

// State
const query = ref(props.modelValue)
const isFocused = ref(false)
const isLoading = ref(false)
const results = ref({
    products: [],
    suppliers: [],
    orders: []
})
const selectedIndex = ref(-1)
const inputRef = ref(null)
const dropdownRef = ref(null)

// Type prefixes for filtered search
const typePrefixes = {
    'p:': 'product',
    'product:': 'product',
    's:': 'supplier',
    'supplier:': 'supplier',
    'o:': 'order',
    'order:': 'order'
}

// Parse query to detect type prefix
const parsedQuery = computed(() => {
    const q = query.value.trim()

    for (const [prefix, type] of Object.entries(typePrefixes)) {
        if (q.toLowerCase().startsWith(prefix)) {
            return {
                type,
                query: q.slice(prefix.length).trim()
            }
        }
    }

    return {
        type: 'all',
        query: q
    }
})

// Computed
const showDropdown = computed(() => {
    return isFocused.value && (hasResults.value || (query.value.length >= 2 && !isLoading.value))
})

const hasResults = computed(() => {
    return results.value.products.length > 0 ||
        results.value.suppliers.length > 0 ||
        results.value.orders.length > 0
})

const totalResults = computed(() => {
    return results.value.products.length +
        results.value.suppliers.length +
        results.value.orders.length
})

// Flat list for keyboard navigation
const flatResults = computed(() => {
    const items = []

    results.value.products.forEach(p => items.push({ ...p, resultType: 'product' }))
    results.value.suppliers.forEach(s => items.push({ ...s, resultType: 'supplier' }))
    results.value.orders.forEach(o => items.push({ ...o, resultType: 'order' }))

    return items
})

// Fetch search results
const fetchResults = debounce(async () => {
    const { type, query: searchQuery } = parsedQuery.value

    if (!searchQuery || searchQuery.length < 2) {
        results.value = { products: [], suppliers: [], orders: [] }
        return
    }

    isLoading.value = true

    try {
        const params = new URLSearchParams({
            q: searchQuery,
            ...(type !== 'all' && { type })
        })

        const response = await fetch(`${props.searchUrl}?${params}`, {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
            },
            credentials: 'same-origin'
        })

        if (response.ok) {
            const data = await response.json()
            results.value = {
                products: data.products || [],
                suppliers: data.suppliers || [],
                orders: data.orders || []
            }
        }
    } catch (e) {
        console.error('Search failed:', e)
        results.value = { products: [], suppliers: [], orders: [] }
    } finally {
        isLoading.value = false
    }
}, 300)

// Watch query changes
watch(query, (newValue) => {
    emit('update:modelValue', newValue)
    selectedIndex.value = -1
    fetchResults()
})

// Keyboard navigation
const handleKeyDown = (event) => {
    switch (event.key) {
        case 'ArrowDown':
            event.preventDefault()
            if (selectedIndex.value < flatResults.value.length - 1) {
                selectedIndex.value++
            }
            break
        case 'ArrowUp':
            event.preventDefault()
            if (selectedIndex.value > 0) {
                selectedIndex.value--
            }
            break
        case 'Enter':
            event.preventDefault()
            if (selectedIndex.value >= 0) {
                selectResult(flatResults.value[selectedIndex.value])
            } else {
                emit('search', parsedQuery.value)
            }
            break
        case 'Escape':
            isFocused.value = false
            inputRef.value?.blur()
            break
    }
}

// Select a result
const selectResult = (result) => {
    emit('select', result)
    isFocused.value = false

    // Navigate to the result
    switch (result.resultType) {
        case 'product':
            router.visit(`/inventory/products/${result.id}`)
            break
        case 'supplier':
            router.visit(`/admin/suppliers/${result.id}`)
            break
        case 'order':
            router.visit(`/orders/${result.id}`)
            break
    }
}

// Clear search
const clearSearch = () => {
    query.value = ''
    results.value = { products: [], suppliers: [], orders: [] }
    inputRef.value?.focus()
}

// Click outside handler
const handleClickOutside = (event) => {
    if (dropdownRef.value && !dropdownRef.value.contains(event.target) &&
        inputRef.value && !inputRef.value.contains(event.target)) {
        isFocused.value = false
    }
}

// View all results
const viewAllResults = () => {
    emit('search', parsedQuery.value)
    isFocused.value = false
    router.visit(`/search?q=${encodeURIComponent(parsedQuery.value.query)}`)
}

onMounted(() => {
    document.addEventListener('click', handleClickOutside)
    if (props.autofocus) {
        inputRef.value?.focus()
    }
})

onUnmounted(() => {
    document.removeEventListener('click', handleClickOutside)
})

// Highlight matching text
const highlightMatch = (text, searchQuery) => {
    if (!searchQuery || !text) return text
    const regex = new RegExp(`(${searchQuery.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')})`, 'gi')
    return text.replace(regex, '<mark class="bg-yellow-200 dark:bg-yellow-800 px-0.5 rounded">$1</mark>')
}
</script>

<template>
    <div class="relative w-full" ref="dropdownRef">
        <!-- Search Input -->
        <div
            :class="[
                'flex items-center gap-2 bg-white dark:bg-slate-800 border rounded-xl transition-all duration-200',
                isFocused
                    ? 'border-indigo-500 ring-4 ring-indigo-500/20 shadow-lg'
                    : 'border-gray-300 dark:border-slate-600 hover:border-gray-400 dark:hover:border-slate-500'
            ]"
        >
            <div class="pl-4">
                <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
            </div>

            <input
                ref="inputRef"
                v-model="query"
                type="text"
                :placeholder="placeholder"
                @focus="isFocused = true"
                @keydown="handleKeyDown"
                class="flex-1 py-3 bg-transparent text-gray-900 dark:text-white placeholder-gray-400 focus:outline-none text-base"
            />

            <!-- Type indicator -->
            <span
                v-if="parsedQuery.type !== 'all'"
                class="px-2 py-1 text-xs font-medium bg-indigo-100 dark:bg-indigo-900/50 text-indigo-700 dark:text-indigo-300 rounded"
            >
                {{ parsedQuery.type }}
            </span>

            <!-- Loading -->
            <div v-if="isLoading" class="pr-3">
                <svg class="w-5 h-5 text-indigo-500 animate-spin" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
            </div>

            <!-- Clear button -->
            <button
                v-if="query && !isLoading"
                @click="clearSearch"
                class="pr-4 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition-colors"
            >
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <!-- Results Dropdown -->
        <div
            v-show="showDropdown"
            class="absolute top-full left-0 right-0 mt-2 bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-xl shadow-xl z-50 overflow-hidden max-h-96 overflow-y-auto"
        >
            <!-- Products -->
            <div v-if="results.products.length > 0" class="border-b border-gray-200 dark:border-slate-700">
                <div class="px-4 py-2 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider bg-gray-50 dark:bg-slate-750">
                    Products ({{ results.products.length }})
                </div>
                <button
                    v-for="(product, index) in results.products.slice(0, 5)"
                    :key="product.id"
                    @click="selectResult({ ...product, resultType: 'product' })"
                    @mouseenter="selectedIndex = index"
                    :class="[
                        'w-full flex items-center gap-3 px-4 py-3 text-left transition-colors',
                        flatResults.indexOf(flatResults.find(r => r.id === product.id && r.resultType === 'product')) === selectedIndex
                            ? 'bg-indigo-50 dark:bg-indigo-900/30'
                            : 'hover:bg-gray-50 dark:hover:bg-slate-700/50'
                    ]"
                >
                    <!-- Product Image -->
                    <div class="w-10 h-10 bg-gray-100 dark:bg-slate-700 rounded-lg flex-shrink-0 overflow-hidden">
                        <img
                            v-if="product.image"
                            :src="product.image"
                            :alt="product.name"
                            class="w-full h-full object-cover"
                        />
                        <svg v-else class="w-full h-full p-2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                    </div>

                    <div class="flex-1 min-w-0">
                        <div class="text-sm font-medium text-gray-900 dark:text-white truncate" v-html="highlightMatch(product.name, parsedQuery.query)"></div>
                        <div class="flex items-center gap-2 text-xs text-gray-500 dark:text-gray-400">
                            <span>SKU: {{ product.sku }}</span>
                            <span v-if="product.category" class="px-1.5 py-0.5 bg-gray-100 dark:bg-slate-700 rounded">{{ product.category }}</span>
                        </div>
                    </div>

                    <div v-if="product.price" class="text-sm font-semibold text-gray-900 dark:text-white">
                        ${{ product.price }}
                    </div>
                </button>
            </div>

            <!-- Suppliers -->
            <div v-if="results.suppliers.length > 0" class="border-b border-gray-200 dark:border-slate-700">
                <div class="px-4 py-2 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider bg-gray-50 dark:bg-slate-750">
                    Suppliers ({{ results.suppliers.length }})
                </div>
                <button
                    v-for="supplier in results.suppliers.slice(0, 3)"
                    :key="supplier.id"
                    @click="selectResult({ ...supplier, resultType: 'supplier' })"
                    class="w-full flex items-center gap-3 px-4 py-3 text-left hover:bg-gray-50 dark:hover:bg-slate-700/50 transition-colors"
                >
                    <div class="w-10 h-10 bg-indigo-100 dark:bg-indigo-900/50 rounded-lg flex items-center justify-center flex-shrink-0">
                        <i class="fas fa-truck text-indigo-600 dark:text-indigo-400"></i>
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="text-sm font-medium text-gray-900 dark:text-white truncate" v-html="highlightMatch(supplier.name, parsedQuery.query)"></div>
                        <div class="text-xs text-gray-500 dark:text-gray-400">{{ supplier.email }}</div>
                    </div>
                </button>
            </div>

            <!-- Orders -->
            <div v-if="results.orders.length > 0" class="border-b border-gray-200 dark:border-slate-700">
                <div class="px-4 py-2 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider bg-gray-50 dark:bg-slate-750">
                    Orders ({{ results.orders.length }})
                </div>
                <button
                    v-for="order in results.orders.slice(0, 3)"
                    :key="order.id"
                    @click="selectResult({ ...order, resultType: 'order' })"
                    class="w-full flex items-center gap-3 px-4 py-3 text-left hover:bg-gray-50 dark:hover:bg-slate-700/50 transition-colors"
                >
                    <div class="w-10 h-10 bg-emerald-100 dark:bg-emerald-900/50 rounded-lg flex items-center justify-center flex-shrink-0">
                        <i class="fas fa-shopping-cart text-emerald-600 dark:text-emerald-400"></i>
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="text-sm font-medium text-gray-900 dark:text-white">Order #{{ order.order_number }}</div>
                        <div class="text-xs text-gray-500 dark:text-gray-400">{{ order.customer_name }}</div>
                    </div>
                    <span :class="[
                        'px-2 py-1 text-xs font-medium rounded-full',
                        order.status === 'completed' ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-400' :
                        order.status === 'pending' ? 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400' :
                        'bg-gray-100 text-gray-800 dark:bg-slate-700 dark:text-gray-300'
                    ]">
                        {{ order.status }}
                    </span>
                </button>
            </div>

            <!-- No Results -->
            <div v-if="!hasResults && query.length >= 2 && !isLoading" class="px-4 py-6 text-center">
                <svg class="w-12 h-12 mx-auto mb-2 text-gray-300 dark:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <p class="text-sm text-gray-500 dark:text-gray-400">No results for "<span class="font-medium">{{ parsedQuery.query }}</span>"</p>
                <p class="text-xs text-gray-400 mt-1">Try p: for products, s: for suppliers, o: for orders</p>
            </div>

            <!-- View All Results -->
            <div v-if="hasResults" class="px-4 py-3 bg-gray-50 dark:bg-slate-750 border-t border-gray-200 dark:border-slate-700">
                <button
                    @click="viewAllResults"
                    class="w-full text-center text-sm text-indigo-600 dark:text-indigo-400 hover:text-indigo-800 dark:hover:text-indigo-200 font-medium transition-colors"
                >
                    View all {{ totalResults }} results
                </button>
            </div>

            <!-- Keyboard Shortcuts -->
            <div class="px-4 py-2 bg-gray-50 dark:bg-slate-750 border-t border-gray-200 dark:border-slate-700">
                <div class="flex items-center justify-center gap-4 text-xs text-gray-400">
                    <span class="flex items-center gap-1">
                        <kbd class="px-1.5 py-0.5 bg-gray-200 dark:bg-slate-600 rounded text-gray-600 dark:text-gray-300">p:</kbd>
                        Products
                    </span>
                    <span class="flex items-center gap-1">
                        <kbd class="px-1.5 py-0.5 bg-gray-200 dark:bg-slate-600 rounded text-gray-600 dark:text-gray-300">s:</kbd>
                        Suppliers
                    </span>
                    <span class="flex items-center gap-1">
                        <kbd class="px-1.5 py-0.5 bg-gray-200 dark:bg-slate-600 rounded text-gray-600 dark:text-gray-300">o:</kbd>
                        Orders
                    </span>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* Custom scrollbar */
.overflow-y-auto::-webkit-scrollbar {
    width: 6px;
}

.overflow-y-auto::-webkit-scrollbar-track {
    background: transparent;
}

.overflow-y-auto::-webkit-scrollbar-thumb {
    background-color: rgba(156, 163, 175, 0.5);
    border-radius: 3px;
}

.dark .overflow-y-auto::-webkit-scrollbar-thumb {
    background-color: rgba(75, 85, 99, 0.5);
}
</style>
