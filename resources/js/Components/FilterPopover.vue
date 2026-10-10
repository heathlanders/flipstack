<script setup>
import { ref, reactive, computed, watch, onMounted, onUnmounted } from 'vue'

const props = defineProps({
    filters: {
        type: Array,
        default: () => []
        // Each filter: { key: string, label: string, type: 'select'|'multiselect'|'range'|'date'|'boolean', options?: [], min?: number, max?: number }
    },
    activeFilters: {
        type: Object,
        default: () => ({})
    }
})

const emit = defineEmits(['apply', 'close'])

// Local filter state
const localFilters = reactive({})

// Initialize local filters from active filters
onMounted(() => {
    props.filters.forEach(filter => {
        if (props.activeFilters[filter.key] !== undefined) {
            localFilters[filter.key] = props.activeFilters[filter.key]
        } else {
            localFilters[filter.key] = filter.type === 'multiselect' ? [] : null
        }
    })

    // Add click outside listener
    document.addEventListener('click', handleClickOutside)
})

onUnmounted(() => {
    document.removeEventListener('click', handleClickOutside)
})

// Handle click outside
const popoverRef = ref(null)
const handleClickOutside = (event) => {
    if (popoverRef.value && !popoverRef.value.contains(event.target)) {
        emit('close')
    }
}

// Check if any filter has value
const hasChanges = computed(() => {
    return props.filters.some(filter => {
        const value = localFilters[filter.key]
        if (Array.isArray(value)) return value.length > 0
        return value !== null && value !== '' && value !== undefined
    })
})

// Apply filters
const applyFilters = () => {
    const cleanFilters = {}
    props.filters.forEach(filter => {
        const value = localFilters[filter.key]
        if (Array.isArray(value) && value.length > 0) {
            cleanFilters[filter.key] = value
        } else if (value !== null && value !== '' && value !== undefined) {
            cleanFilters[filter.key] = value
        }
    })
    emit('apply', cleanFilters)
}

// Clear all filters
const clearFilters = () => {
    props.filters.forEach(filter => {
        localFilters[filter.key] = filter.type === 'multiselect' ? [] : null
    })
}

// Toggle multiselect option
const toggleMultiselect = (filterKey, optionValue) => {
    if (!Array.isArray(localFilters[filterKey])) {
        localFilters[filterKey] = []
    }

    const index = localFilters[filterKey].indexOf(optionValue)
    if (index === -1) {
        localFilters[filterKey].push(optionValue)
    } else {
        localFilters[filterKey].splice(index, 1)
    }
}

// Check if multiselect option is selected
const isMultiselectSelected = (filterKey, optionValue) => {
    return Array.isArray(localFilters[filterKey]) && localFilters[filterKey].includes(optionValue)
}
</script>

<template>
    <div
        ref="popoverRef"
        class="w-80 bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-xl shadow-xl overflow-hidden"
        @click.stop
    >
        <!-- Header -->
        <div class="px-4 py-3 border-b border-gray-200 dark:border-slate-700 flex items-center justify-between">
            <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Filters</h3>
            <button
                v-if="hasChanges"
                @click="clearFilters"
                class="text-xs text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 transition-colors"
            >
                Clear all
            </button>
        </div>

        <!-- Filter List -->
        <div class="max-h-96 overflow-y-auto">
            <div class="p-4 space-y-4">
                <div v-for="filter in filters" :key="filter.key" class="space-y-2">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        {{ filter.label }}
                    </label>

                    <!-- Select Filter -->
                    <select
                        v-if="filter.type === 'select'"
                        v-model="localFilters[filter.key]"
                        class="w-full px-3 py-2 bg-gray-50 dark:bg-slate-700 border border-gray-200 dark:border-slate-600 rounded-lg text-sm text-gray-900 dark:text-white focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                    >
                        <option :value="null">All</option>
                        <option
                            v-for="option in filter.options"
                            :key="option.value"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </option>
                    </select>

                    <!-- Multiselect Filter -->
                    <div v-else-if="filter.type === 'multiselect'" class="space-y-1.5 max-h-40 overflow-y-auto">
                        <label
                            v-for="option in filter.options"
                            :key="option.value"
                            class="flex items-center gap-2 px-2 py-1.5 rounded-lg hover:bg-gray-50 dark:hover:bg-slate-700 cursor-pointer transition-colors"
                        >
                            <input
                                type="checkbox"
                                :checked="isMultiselectSelected(filter.key, option.value)"
                                @change="toggleMultiselect(filter.key, option.value)"
                                class="w-4 h-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-700"
                            />
                            <span class="text-sm text-gray-700 dark:text-gray-300">{{ option.label }}</span>
                            <span v-if="option.count !== undefined" class="ml-auto text-xs text-gray-400">
                                {{ option.count }}
                            </span>
                        </label>
                    </div>

                    <!-- Range Filter -->
                    <div v-else-if="filter.type === 'range'" class="flex items-center gap-2">
                        <input
                            v-model.number="localFilters[filter.key + '_min']"
                            type="number"
                            :placeholder="filter.minPlaceholder || 'Min'"
                            :min="filter.min"
                            :max="filter.max"
                            class="w-full px-3 py-2 bg-gray-50 dark:bg-slate-700 border border-gray-200 dark:border-slate-600 rounded-lg text-sm text-gray-900 dark:text-white focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                        />
                        <span class="text-gray-400">-</span>
                        <input
                            v-model.number="localFilters[filter.key + '_max']"
                            type="number"
                            :placeholder="filter.maxPlaceholder || 'Max'"
                            :min="filter.min"
                            :max="filter.max"
                            class="w-full px-3 py-2 bg-gray-50 dark:bg-slate-700 border border-gray-200 dark:border-slate-600 rounded-lg text-sm text-gray-900 dark:text-white focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                        />
                    </div>

                    <!-- Date Filter -->
                    <div v-else-if="filter.type === 'date'" class="flex items-center gap-2">
                        <input
                            v-model="localFilters[filter.key + '_from']"
                            type="date"
                            class="w-full px-3 py-2 bg-gray-50 dark:bg-slate-700 border border-gray-200 dark:border-slate-600 rounded-lg text-sm text-gray-900 dark:text-white focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                        />
                        <span class="text-gray-400">to</span>
                        <input
                            v-model="localFilters[filter.key + '_to']"
                            type="date"
                            class="w-full px-3 py-2 bg-gray-50 dark:bg-slate-700 border border-gray-200 dark:border-slate-600 rounded-lg text-sm text-gray-900 dark:text-white focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                        />
                    </div>

                    <!-- Boolean Filter -->
                    <div v-else-if="filter.type === 'boolean'" class="flex items-center gap-4">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input
                                type="radio"
                                :name="filter.key"
                                :value="null"
                                v-model="localFilters[filter.key]"
                                class="w-4 h-4 text-indigo-600 border-gray-300 focus:ring-indigo-500 dark:border-slate-600"
                            />
                            <span class="text-sm text-gray-700 dark:text-gray-300">All</span>
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input
                                type="radio"
                                :name="filter.key"
                                :value="true"
                                v-model="localFilters[filter.key]"
                                class="w-4 h-4 text-indigo-600 border-gray-300 focus:ring-indigo-500 dark:border-slate-600"
                            />
                            <span class="text-sm text-gray-700 dark:text-gray-300">{{ filter.trueLabel || 'Yes' }}</span>
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input
                                type="radio"
                                :name="filter.key"
                                :value="false"
                                v-model="localFilters[filter.key]"
                                class="w-4 h-4 text-indigo-600 border-gray-300 focus:ring-indigo-500 dark:border-slate-600"
                            />
                            <span class="text-sm text-gray-700 dark:text-gray-300">{{ filter.falseLabel || 'No' }}</span>
                        </label>
                    </div>

                    <!-- Text Filter (default) -->
                    <input
                        v-else
                        v-model="localFilters[filter.key]"
                        type="text"
                        :placeholder="filter.placeholder || `Filter by ${filter.label.toLowerCase()}...`"
                        class="w-full px-3 py-2 bg-gray-50 dark:bg-slate-700 border border-gray-200 dark:border-slate-600 rounded-lg text-sm text-gray-900 dark:text-white focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                    />
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="px-4 py-3 border-t border-gray-200 dark:border-slate-700 bg-gray-50 dark:bg-slate-750 flex items-center justify-end gap-2">
            <button
                @click="emit('close')"
                class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white transition-colors"
            >
                Cancel
            </button>
            <button
                @click="applyFilters"
                class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg transition-colors"
            >
                Apply Filters
            </button>
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
