<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import debounce from 'lodash/debounce'

const props = defineProps({
    modelValue: {
        type: String,
        default: ''
    },
    placeholder: {
        type: String,
        default: 'Search products by name, SKU, barcode, or description...'
    },
    searchSuggestionsUrl: {
        type: String,
        default: '/inventory/products/search-suggestions'
    }
})

const emit = defineEmits(['update:modelValue', 'search', 'clear'])

// State
const searchQuery = ref(props.modelValue)
const isFocused = ref(false)
const isLoading = ref(false)
const suggestions = ref([])
const recentSearches = ref([])
const selectedIndex = ref(-1)
const inputRef = ref(null)
const dropdownRef = ref(null)

// Barcode scanner detection
const barcodeBuffer = ref('')
const lastKeyTime = ref(0)
const BARCODE_SCAN_TIMEOUT = 50 // ms between keystrokes for barcode scanner

// Search scope options
const searchScopes = [
    { value: 'all', label: 'All Fields', icon: 'M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z' },
    { value: 'name', label: 'Name', icon: 'M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z' },
    { value: 'sku', label: 'SKU', icon: 'M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4' },
    { value: 'barcode', label: 'Barcode', icon: 'M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z' },
    { value: 'description', label: 'Description', icon: 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z' }
]

const activeScope = ref('all')
const showScopeDropdown = ref(false)

// Local storage key for recent searches
const RECENT_SEARCHES_KEY = 'product_recent_searches'
const MAX_RECENT_SEARCHES = 8

// Computed
const showDropdown = computed(() => {
    return isFocused.value && (suggestions.value.length > 0 || recentSearches.value.length > 0 || searchQuery.value.length > 0)
})

const currentScopeIcon = computed(() => {
    return searchScopes.find(s => s.value === activeScope.value)?.icon || searchScopes[0].icon
})

// Load recent searches from localStorage
onMounted(() => {
    loadRecentSearches()
    document.addEventListener('click', handleClickOutside)
})

onUnmounted(() => {
    document.removeEventListener('click', handleClickOutside)
})

const loadRecentSearches = () => {
    try {
        const stored = localStorage.getItem(RECENT_SEARCHES_KEY)
        if (stored) {
            recentSearches.value = JSON.parse(stored)
        }
    } catch (e) {
        console.warn('Failed to load recent searches:', e)
    }
}

const saveRecentSearch = (query) => {
    if (!query.trim()) return
    
    // Remove if already exists
    const filtered = recentSearches.value.filter(s => s.query.toLowerCase() !== query.toLowerCase())
    
    // Add to beginning
    recentSearches.value = [
        { query: query.trim(), scope: activeScope.value, timestamp: Date.now() },
        ...filtered
    ].slice(0, MAX_RECENT_SEARCHES)
    
    try {
        localStorage.setItem(RECENT_SEARCHES_KEY, JSON.stringify(recentSearches.value))
    } catch (e) {
        console.warn('Failed to save recent searches:', e)
    }
}

const removeRecentSearch = (index) => {
    recentSearches.value.splice(index, 1)
    try {
        localStorage.setItem(RECENT_SEARCHES_KEY, JSON.stringify(recentSearches.value))
    } catch (e) {
        console.warn('Failed to save recent searches:', e)
    }
}

const clearRecentSearches = () => {
    recentSearches.value = []
    try {
        localStorage.removeItem(RECENT_SEARCHES_KEY)
    } catch (e) {
        console.warn('Failed to clear recent searches:', e)
    }
}

// Fetch suggestions with debounce
const fetchSuggestions = debounce(async (query) => {
    if (!query || query.length < 2) {
        suggestions.value = []
        return
    }
    
    isLoading.value = true
    
    try {
        const response = await fetch(`${props.searchSuggestionsUrl}?q=${encodeURIComponent(query)}&scope=${activeScope.value}`, {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        
        if (response.ok) {
            const data = await response.json()
            suggestions.value = data.suggestions || []
        }
    } catch (e) {
        console.error('Failed to fetch suggestions:', e)
        suggestions.value = []
    } finally {
        isLoading.value = false
    }
}, 300)

// Watch for input changes
watch(searchQuery, (newValue) => {
    emit('update:modelValue', newValue)
    selectedIndex.value = -1
    fetchSuggestions(newValue)
})

// Barcode scanner detection
const handleKeyDown = (event) => {
    const now = Date.now()
    
    // Check for barcode scanner input (rapid keystrokes)
    if (now - lastKeyTime.value < BARCODE_SCAN_TIMEOUT) {
        barcodeBuffer.value += event.key
    } else {
        barcodeBuffer.value = event.key
    }
    lastKeyTime.value = now
    
    // Handle keyboard navigation
    switch (event.key) {
        case 'ArrowDown':
            event.preventDefault()
            navigateDown()
            break
        case 'ArrowUp':
            event.preventDefault()
            navigateUp()
            break
        case 'Enter':
            event.preventDefault()
            handleEnter()
            break
        case 'Escape':
            isFocused.value = false
            inputRef.value?.blur()
            break
        case 'Tab':
            if (showScopeDropdown.value) {
                event.preventDefault()
                showScopeDropdown.value = false
            }
            break
    }
}

// Check for barcode scan completion
const handleKeyUp = (event) => {
    // If we have a substantial buffer and Enter was pressed, it's likely a barcode scan
    if (event.key === 'Enter' && barcodeBuffer.value.length > 5) {
        // Detected barcode scan
        const barcode = barcodeBuffer.value.replace('Enter', '').trim()
        if (barcode && /^[\d\w-]+$/.test(barcode)) {
            searchQuery.value = barcode
            activeScope.value = 'barcode'
            performSearch()
        }
        barcodeBuffer.value = ''
    }
}

const navigateDown = () => {
    const totalItems = suggestions.value.length + recentSearches.value.length
    if (selectedIndex.value < totalItems - 1) {
        selectedIndex.value++
    }
}

const navigateUp = () => {
    if (selectedIndex.value > -1) {
        selectedIndex.value--
    }
}

const handleEnter = () => {
    if (selectedIndex.value >= 0) {
        const suggestionCount = suggestions.value.length
        if (selectedIndex.value < suggestionCount) {
            selectSuggestion(suggestions.value[selectedIndex.value])
        } else {
            const recentIndex = selectedIndex.value - suggestionCount
            if (recentSearches.value[recentIndex]) {
                useRecentSearch(recentSearches.value[recentIndex])
            }
        }
    } else {
        performSearch()
    }
}

const selectSuggestion = (suggestion) => {
    searchQuery.value = suggestion.name || suggestion.sku || suggestion.text
    isFocused.value = false
    performSearch()
}

const useRecentSearch = (recent) => {
    searchQuery.value = recent.query
    activeScope.value = recent.scope || 'all'
    isFocused.value = false
    performSearch()
}

const performSearch = () => {
    if (searchQuery.value.trim()) {
        saveRecentSearch(searchQuery.value)
    }
    emit('search', {
        query: searchQuery.value,
        scope: activeScope.value
    })
    isFocused.value = false
}

const clearSearch = () => {
    searchQuery.value = ''
    suggestions.value = []
    selectedIndex.value = -1
    emit('clear')
    emit('search', { query: '', scope: activeScope.value })
    inputRef.value?.focus()
}

const handleClickOutside = (event) => {
    if (dropdownRef.value && !dropdownRef.value.contains(event.target) && 
        inputRef.value && !inputRef.value.contains(event.target)) {
        isFocused.value = false
        showScopeDropdown.value = false
    }
}

const selectScope = (scope) => {
    activeScope.value = scope
    showScopeDropdown.value = false
    inputRef.value?.focus()
    if (searchQuery.value) {
        fetchSuggestions(searchQuery.value)
    }
}

// Format time ago for recent searches
const formatTimeAgo = (timestamp) => {
    const seconds = Math.floor((Date.now() - timestamp) / 1000)
    if (seconds < 60) return 'Just now'
    if (seconds < 3600) return `${Math.floor(seconds / 60)}m ago`
    if (seconds < 86400) return `${Math.floor(seconds / 3600)}h ago`
    return `${Math.floor(seconds / 86400)}d ago`
}

// Highlight matching text
const highlightMatch = (text, query) => {
    if (!query || !text) return text
    const regex = new RegExp(`(${query.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')})`, 'gi')
    return text.replace(regex, '<mark class="bg-yellow-200 dark:bg-yellow-800 px-0.5 rounded">$1</mark>')
}
</script>

<template>
    <div class="relative w-full" ref="dropdownRef">
        <!-- Search Input Container -->
        <div 
            :class="[
                'flex items-center gap-2 bg-white dark:bg-gray-800 border rounded-xl transition-all duration-200',
                isFocused 
                    ? 'border-indigo-500 ring-4 ring-indigo-500/20 shadow-lg' 
                    : 'border-gray-300 dark:border-gray-600 hover:border-gray-400 dark:hover:border-gray-500'
            ]"
        >
            <!-- Scope Selector -->
            <div class="relative">
                <button
                    @click.stop="showScopeDropdown = !showScopeDropdown"
                    class="flex items-center gap-1.5 pl-4 pr-2 py-3 text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 transition-colors"
                    type="button"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="currentScopeIcon" />
                    </svg>
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </button>
                
                <!-- Scope Dropdown -->
                <div 
                    v-show="showScopeDropdown"
                    class="absolute top-full left-0 mt-1 w-48 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-lg z-50 py-1"
                >
                    <button
                        v-for="scope in searchScopes"
                        :key="scope.value"
                        @click="selectScope(scope.value)"
                        :class="[
                            'w-full flex items-center gap-2 px-3 py-2 text-sm text-left transition-colors',
                            activeScope === scope.value
                                ? 'bg-indigo-50 text-indigo-700 dark:bg-indigo-900/50 dark:text-indigo-300'
                                : 'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700'
                        ]"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="scope.icon" />
                        </svg>
                        <span>{{ scope.label }}</span>
                        <svg v-if="activeScope === scope.value" class="w-4 h-4 ml-auto text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                    </button>
                </div>
            </div>

            <div class="h-6 w-px bg-gray-300 dark:bg-gray-600"></div>

            <!-- Search Input -->
            <input
                ref="inputRef"
                v-model="searchQuery"
                type="text"
                :placeholder="placeholder"
                @focus="isFocused = true"
                @keydown="handleKeyDown"
                @keyup="handleKeyUp"
                class="flex-1 py-3 pr-4 bg-transparent text-gray-900 dark:text-white placeholder-gray-400 focus:outline-none text-base"
            />

            <!-- Loading Spinner -->
            <div v-if="isLoading" class="pr-3">
                <svg class="w-5 h-5 text-indigo-500 animate-spin" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
            </div>

            <!-- Clear Button -->
            <button
                v-if="searchQuery && !isLoading"
                @click="clearSearch"
                class="pr-3 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition-colors"
                type="button"
            >
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>

            <!-- Search Button -->
            <button
                @click="performSearch"
                class="flex items-center gap-2 px-4 py-2 mr-1.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg transition-colors"
                type="button"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
                <span class="hidden sm:inline">Search</span>
            </button>
        </div>

        <!-- Dropdown Results -->
        <div 
            v-show="showDropdown"
            class="absolute top-full left-0 right-0 mt-2 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl shadow-xl z-40 overflow-hidden max-h-96 overflow-y-auto"
        >
            <!-- Suggestions Section -->
            <div v-if="suggestions.length > 0" class="border-b border-gray-200 dark:border-gray-700">
                <div class="px-4 py-2 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider bg-gray-50 dark:bg-gray-750">
                    Suggestions
                </div>
                <div>
                    <button
                        v-for="(suggestion, index) in suggestions"
                        :key="suggestion.id || index"
                        @click="selectSuggestion(suggestion)"
                        @mouseenter="selectedIndex = index"
                        :class="[
                            'w-full flex items-center gap-3 px-4 py-3 text-left transition-colors',
                            selectedIndex === index
                                ? 'bg-indigo-50 dark:bg-indigo-900/30'
                                : 'hover:bg-gray-50 dark:hover:bg-gray-700/50'
                        ]"
                    >
                        <!-- Product Image -->
                        <div class="w-10 h-10 bg-gray-100 dark:bg-gray-700 rounded-lg flex-shrink-0 overflow-hidden">
                            <img 
                                v-if="suggestion.image"
                                :src="suggestion.image"
                                :alt="suggestion.name"
                                class="w-full h-full object-cover"
                            />
                            <svg v-else class="w-full h-full p-2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                        </div>
                        
                        <div class="flex-1 min-w-0">
                            <div class="text-sm font-medium text-gray-900 dark:text-white truncate" v-html="highlightMatch(suggestion.name, searchQuery)"></div>
                            <div class="flex items-center gap-2 text-xs text-gray-500 dark:text-gray-400">
                                <span v-if="suggestion.sku">SKU: {{ suggestion.sku }}</span>
                                <span v-if="suggestion.category" class="px-1.5 py-0.5 bg-gray-100 dark:bg-gray-700 rounded">{{ suggestion.category }}</span>
                            </div>
                        </div>
                        
                        <div v-if="suggestion.price" class="text-sm font-semibold text-gray-900 dark:text-white">
                            ${{ suggestion.price }}
                        </div>
                    </button>
                </div>
            </div>

            <!-- No Results Message -->
            <div v-else-if="searchQuery.length >= 2 && !isLoading" class="px-4 py-6 text-center text-gray-500 dark:text-gray-400">
                <svg class="w-12 h-12 mx-auto mb-2 text-gray-300 dark:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <p class="text-sm">No products found for "<span class="font-medium">{{ searchQuery }}</span>"</p>
                <p class="text-xs mt-1">Try different keywords or adjust your search scope</p>
            </div>

            <!-- Recent Searches Section -->
            <div v-if="recentSearches.length > 0 && !searchQuery">
                <div class="px-4 py-2 flex items-center justify-between bg-gray-50 dark:bg-gray-750">
                    <span class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Recent Searches</span>
                    <button 
                        @click="clearRecentSearches"
                        class="text-xs text-gray-400 hover:text-red-500 dark:hover:text-red-400 transition-colors"
                    >
                        Clear all
                    </button>
                </div>
                <div>
                    <div
                        v-for="(recent, index) in recentSearches"
                        :key="index"
                        :class="[
                            'flex items-center gap-3 px-4 py-2.5 transition-colors',
                            selectedIndex === (suggestions.length + index)
                                ? 'bg-indigo-50 dark:bg-indigo-900/30'
                                : 'hover:bg-gray-50 dark:hover:bg-gray-700/50'
                        ]"
                    >
                        <button
                            @click="useRecentSearch(recent)"
                            @mouseenter="selectedIndex = suggestions.length + index"
                            class="flex-1 flex items-center gap-3 text-left"
                        >
                            <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <span class="text-sm text-gray-700 dark:text-gray-300">{{ recent.query }}</span>
                            <span class="text-xs text-gray-400">{{ formatTimeAgo(recent.timestamp) }}</span>
                        </button>
                        <button
                            @click.stop="removeRecentSearch(index)"
                            class="p-1 text-gray-400 hover:text-red-500 dark:hover:text-red-400 transition-colors"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Barcode Scanner Hint -->
            <div v-if="!searchQuery && recentSearches.length === 0" class="px-4 py-4 text-center border-t border-gray-200 dark:border-gray-700">
                <div class="flex items-center justify-center gap-2 text-sm text-gray-500 dark:text-gray-400">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z" />
                    </svg>
                    <span>Barcode scanner ready - scan any product barcode</span>
                </div>
            </div>

            <!-- Keyboard Shortcuts Hint -->
            <div class="px-4 py-2 bg-gray-50 dark:bg-gray-750 border-t border-gray-200 dark:border-gray-700">
                <div class="flex items-center justify-center gap-4 text-xs text-gray-400">
                    <span class="flex items-center gap-1">
                        <kbd class="px-1.5 py-0.5 bg-gray-200 dark:bg-gray-600 rounded text-gray-600 dark:text-gray-300">↑↓</kbd>
                        Navigate
                    </span>
                    <span class="flex items-center gap-1">
                        <kbd class="px-1.5 py-0.5 bg-gray-200 dark:bg-gray-600 rounded text-gray-600 dark:text-gray-300">Enter</kbd>
                        Select
                    </span>
                    <span class="flex items-center gap-1">
                        <kbd class="px-1.5 py-0.5 bg-gray-200 dark:bg-gray-600 rounded text-gray-600 dark:text-gray-300">Esc</kbd>
                        Close
                    </span>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* Smooth dropdown animation */
.max-h-96 {
    transition: max-height 0.2s ease-out;
}

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
