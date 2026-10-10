<script setup>
import { ref, computed, watch } from 'vue'
import { Link } from '@inertiajs/vue3'
import ViewToggle from '@/Components/ViewToggle.vue'
import FilterPopover from '@/Components/FilterPopover.vue'

const props = defineProps({
    // Search
    searchPlaceholder: {
        type: String,
        default: 'Search...'
    },
    searchValue: {
        type: String,
        default: ''
    },

    // Filters
    filters: {
        type: Array,
        default: () => []
    },
    activeFilters: {
        type: Object,
        default: () => ({})
    },
    presets: {
        type: Array,
        default: () => []
    },

    // View
    views: {
        type: Array,
        default: () => ['list', 'grid']
    },
    currentView: {
        type: String,
        default: 'list'
    },

    // Sort
    sortOptions: {
        type: Array,
        default: () => []
    },
    currentSort: {
        type: String,
        default: ''
    },
    currentSortOrder: {
        type: String,
        default: 'desc'
    },

    // Actions
    primaryAction: {
        type: Object,
        default: null
    },
    secondaryActions: {
        type: Array,
        default: () => []
    },
    bulkActions: {
        type: Array,
        default: () => []
    },

    // Selection
    selectedCount: {
        type: Number,
        default: 0
    },
    totalCount: {
        type: Number,
        default: 0
    },

    // Loading
    isLoading: {
        type: Boolean,
        default: false
    },

    // Sticky
    sticky: {
        type: Boolean,
        default: true
    }
})

const emit = defineEmits([
    'search',
    'filter-change',
    'preset-apply',
    'view-change',
    'sort-change',
    'action',
    'bulk-action',
    'clear-selection'
])

// Local state
const localSearchValue = ref(props.searchValue)
const showFiltersPopover = ref(false)
const showSortDropdown = ref(false)
const showActionsDropdown = ref(false)
const showPresetsDropdown = ref(false)

// Computed
const hasActiveFilters = computed(() => {
    return Object.values(props.activeFilters).some(v => {
        if (Array.isArray(v)) return v.length > 0
        return v !== null && v !== '' && v !== undefined
    })
})

const activeFilterCount = computed(() => {
    return Object.values(props.activeFilters).filter(v => {
        if (Array.isArray(v)) return v.length > 0
        return v !== null && v !== '' && v !== undefined
    }).length
})

const hasSelection = computed(() => props.selectedCount > 0)

// Watch for external search value changes
watch(() => props.searchValue, (newValue) => {
    localSearchValue.value = newValue
})

// Methods
const handleSearch = () => {
    emit('search', localSearchValue.value)
}

const clearSearch = () => {
    localSearchValue.value = ''
    emit('search', '')
}

const handleFilterChange = (filters) => {
    emit('filter-change', filters)
    showFiltersPopover.value = false
}

const handleViewChange = (view) => {
    emit('view-change', view)
}

const handleSortChange = (sortKey) => {
    emit('sort-change', sortKey)
    showSortDropdown.value = false
}

const handlePrimaryAction = () => {
    if (props.primaryAction?.action) {
        props.primaryAction.action()
    } else {
        emit('action', props.primaryAction)
    }
}

const handleSecondaryAction = (action) => {
    if (action.action) {
        action.action()
    } else {
        emit('action', action)
    }
    showActionsDropdown.value = false
}

const handleBulkAction = (action) => {
    emit('bulk-action', action)
}

const applyPreset = (preset) => {
    emit('preset-apply', preset)
    showPresetsDropdown.value = false
}

const clearAllFilters = () => {
    emit('filter-change', {})
}

const clearSelection = () => {
    emit('clear-selection')
}

// Close dropdowns on outside click
const closeDropdowns = () => {
    showSortDropdown.value = false
    showActionsDropdown.value = false
    showPresetsDropdown.value = false
}
</script>

<template>
    <div
        :class="[
            'bg-white dark:bg-slate-800 border-b border-gray-200 dark:border-slate-700',
            sticky ? 'sticky top-0 z-30 shadow-sm' : ''
        ]"
    >
        <!-- Main Toolbar Row -->
        <div class="px-4 sm:px-6 lg:px-8 py-3">
            <div class="flex flex-col sm:flex-row sm:items-center gap-3">
                <!-- Left: Search + Filters -->
                <div class="flex-1 flex items-center gap-3">
                    <!-- Search Input -->
                    <div class="relative flex-1 max-w-md">
                        <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                            <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                        </div>
                        <input
                            v-model="localSearchValue"
                            type="text"
                            :placeholder="searchPlaceholder"
                            @keyup.enter="handleSearch"
                            class="w-full pl-10 pr-10 py-2 bg-gray-50 dark:bg-slate-700 border border-gray-200 dark:border-slate-600 rounded-lg text-sm text-gray-900 dark:text-white placeholder-gray-400 focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all"
                            data-product-search
                        />
                        <button
                            v-if="localSearchValue"
                            @click="clearSearch"
                            class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <!-- Filter Button -->
                    <div v-if="filters.length > 0" class="relative">
                        <button
                            @click="showFiltersPopover = !showFiltersPopover"
                            :class="[
                                'inline-flex items-center gap-2 px-3 py-2 border rounded-lg text-sm font-medium transition-colors',
                                hasActiveFilters
                                    ? 'bg-indigo-50 dark:bg-indigo-900/30 border-indigo-300 dark:border-indigo-700 text-indigo-700 dark:text-indigo-300'
                                    : 'bg-white dark:bg-slate-700 border-gray-200 dark:border-slate-600 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-slate-600'
                            ]"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
                            </svg>
                            <span class="hidden sm:inline">Filters</span>
                            <span
                                v-if="activeFilterCount > 0"
                                class="inline-flex items-center justify-center w-5 h-5 text-xs font-bold text-white bg-indigo-600 rounded-full"
                            >
                                {{ activeFilterCount }}
                            </span>
                        </button>

                        <!-- Filter Popover -->
                        <FilterPopover
                            v-if="showFiltersPopover"
                            :filters="filters"
                            :active-filters="activeFilters"
                            @apply="handleFilterChange"
                            @close="showFiltersPopover = false"
                            class="absolute left-0 top-full mt-2 z-50"
                        />
                    </div>

                    <!-- Presets Dropdown -->
                    <div v-if="presets.length > 0" class="relative">
                        <button
                            @click="showPresetsDropdown = !showPresetsDropdown"
                            class="inline-flex items-center gap-2 px-3 py-2 bg-white dark:bg-slate-700 border border-gray-200 dark:border-slate-600 rounded-lg text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-slate-600 transition-colors"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z" />
                            </svg>
                            <span class="hidden sm:inline">Presets</span>
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>

                        <!-- Presets Dropdown Menu -->
                        <div
                            v-if="showPresetsDropdown"
                            class="absolute left-0 top-full mt-2 w-56 bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-lg shadow-lg z-50"
                        >
                            <div class="py-1">
                                <button
                                    v-for="preset in presets"
                                    :key="preset.id"
                                    @click="applyPreset(preset)"
                                    class="w-full px-4 py-2 text-left text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-slate-700 transition-colors"
                                >
                                    {{ preset.name }}
                                </button>
                                <div v-if="presets.length === 0" class="px-4 py-2 text-sm text-gray-500 dark:text-gray-400">
                                    No saved presets
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Active Filter Badges -->
                    <div v-if="hasActiveFilters" class="hidden lg:flex items-center gap-2">
                        <button
                            @click="clearAllFilters"
                            class="text-xs text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 transition-colors"
                        >
                            Clear all
                        </button>
                    </div>
                </div>

                <!-- Right: View Toggle + Sort + Actions -->
                <div class="flex items-center gap-3">
                    <!-- Results Count -->
                    <span v-if="totalCount > 0" class="text-sm text-gray-500 dark:text-gray-400 hidden sm:inline">
                        {{ totalCount.toLocaleString() }} results
                    </span>

                    <!-- Sort Dropdown -->
                    <div v-if="sortOptions.length > 0" class="relative">
                        <button
                            @click="showSortDropdown = !showSortDropdown"
                            class="inline-flex items-center gap-2 px-3 py-2 bg-white dark:bg-slate-700 border border-gray-200 dark:border-slate-600 rounded-lg text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-slate-600 transition-colors"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4h13M3 8h9m-9 4h6m4 0l4-4m0 0l4 4m-4-4v12" />
                            </svg>
                            <span class="hidden sm:inline">Sort</span>
                        </button>

                        <!-- Sort Dropdown Menu -->
                        <div
                            v-if="showSortDropdown"
                            class="absolute right-0 top-full mt-2 w-48 bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-lg shadow-lg z-50"
                        >
                            <div class="py-1">
                                <button
                                    v-for="option in sortOptions"
                                    :key="option.value"
                                    @click="handleSortChange(option.value)"
                                    :class="[
                                        'w-full px-4 py-2 text-left text-sm transition-colors flex items-center justify-between',
                                        currentSort === option.value
                                            ? 'bg-indigo-50 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300'
                                            : 'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-slate-700'
                                    ]"
                                >
                                    <span>{{ option.label }}</span>
                                    <svg
                                        v-if="currentSort === option.value"
                                        :class="[
                                            'w-4 h-4 transition-transform',
                                            currentSortOrder === 'asc' ? 'rotate-180' : ''
                                        ]"
                                        fill="none"
                                        stroke="currentColor"
                                        viewBox="0 0 24 24"
                                    >
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- View Toggle -->
                    <ViewToggle
                        v-if="views.length > 1"
                        :views="views"
                        :current-view="currentView"
                        @change="handleViewChange"
                    />

                    <!-- Secondary Actions Dropdown -->
                    <div v-if="secondaryActions.length > 0" class="relative">
                        <button
                            @click="showActionsDropdown = !showActionsDropdown"
                            class="inline-flex items-center gap-1 px-3 py-2 bg-white dark:bg-slate-700 border border-gray-200 dark:border-slate-600 rounded-lg text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-slate-600 transition-colors"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z" />
                            </svg>
                        </button>

                        <!-- Actions Dropdown Menu -->
                        <div
                            v-if="showActionsDropdown"
                            class="absolute right-0 top-full mt-2 w-48 bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-lg shadow-lg z-50"
                        >
                            <div class="py-1">
                                <button
                                    v-for="action in secondaryActions"
                                    :key="action.id"
                                    @click="handleSecondaryAction(action)"
                                    class="w-full px-4 py-2 text-left text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-slate-700 transition-colors flex items-center gap-2"
                                >
                                    <i v-if="action.icon" :class="['fas', `fa-${action.icon}`, 'w-4 text-gray-400']"></i>
                                    <span>{{ action.label }}</span>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Primary Action -->
                    <Link
                        v-if="primaryAction?.route"
                        :href="route(primaryAction.route)"
                        class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-sm font-medium transition-colors"
                    >
                        <svg v-if="primaryAction.icon === 'plus'" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        <span class="hidden sm:inline">{{ primaryAction.label }}</span>
                    </Link>
                    <button
                        v-else-if="primaryAction"
                        @click="handlePrimaryAction"
                        class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-sm font-medium transition-colors"
                    >
                        <svg v-if="primaryAction.icon === 'plus'" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        <span class="hidden sm:inline">{{ primaryAction.label }}</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- Bulk Actions Row (when items selected) -->
        <div
            v-if="hasSelection"
            class="px-4 sm:px-6 lg:px-8 py-2 bg-indigo-50 dark:bg-indigo-900/20 border-t border-indigo-100 dark:border-indigo-800"
        >
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <span class="text-sm font-medium text-indigo-700 dark:text-indigo-300">
                        {{ selectedCount }} selected
                    </span>
                    <div class="flex items-center gap-2">
                        <button
                            v-for="action in bulkActions"
                            :key="action.id"
                            @click="handleBulkAction(action)"
                            :class="[
                                'inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md text-sm font-medium transition-colors',
                                action.variant === 'danger'
                                    ? 'bg-red-100 text-red-700 hover:bg-red-200 dark:bg-red-900/30 dark:text-red-400 dark:hover:bg-red-900/50'
                                    : action.variant === 'success'
                                        ? 'bg-emerald-100 text-emerald-700 hover:bg-emerald-200 dark:bg-emerald-900/30 dark:text-emerald-400 dark:hover:bg-emerald-900/50'
                                        : 'bg-white text-gray-700 hover:bg-gray-100 dark:bg-slate-700 dark:text-gray-300 dark:hover:bg-slate-600'
                            ]"
                        >
                            <i v-if="action.icon" :class="['fas', `fa-${action.icon}`, 'text-xs']"></i>
                            {{ action.label }}
                        </button>
                    </div>
                </div>
                <button
                    @click="clearSelection"
                    class="text-sm text-indigo-600 dark:text-indigo-400 hover:text-indigo-800 dark:hover:text-indigo-200 transition-colors"
                >
                    Clear selection
                </button>
            </div>
        </div>

        <!-- Loading Indicator -->
        <div
            v-if="isLoading"
            class="absolute bottom-0 left-0 right-0 h-0.5 bg-indigo-200 dark:bg-indigo-800 overflow-hidden"
        >
            <div class="h-full w-1/3 bg-indigo-600 animate-loading-bar"></div>
        </div>
    </div>
</template>

<style scoped>
@keyframes loading-bar {
    0% {
        transform: translateX(-100%);
    }
    100% {
        transform: translateX(400%);
    }
}

.animate-loading-bar {
    animation: loading-bar 1.5s ease-in-out infinite;
}
</style>
