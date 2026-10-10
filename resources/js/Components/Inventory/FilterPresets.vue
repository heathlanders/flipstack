<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import { router } from '@inertiajs/vue3'

const props = defineProps({
    presets: {
        type: Array,
        default: () => []
    },
    currentFilters: {
        type: Object,
        default: () => ({})
    },
    saveUrl: {
        type: String,
        default: '/api/inventory/filter-presets'
    },
    deleteUrl: {
        type: String,
        default: '/api/inventory/filter-presets'
    }
})

const emit = defineEmits(['preset-applied', 'preset-saved', 'preset-deleted'])

// State
const showModal = ref(false)
const presetName = ref('')
const setAsDefault = ref(false)
const isSaving = ref(false)
const isDeleting = ref(null)
const error = ref('')
const showConfirmDelete = ref(null)
const localPresets = ref([...props.presets])

// Computed
const hasActiveFilters = computed(() => {
    const filters = props.currentFilters
    return Object.keys(filters).some(key => {
        const value = filters[key]
        if (Array.isArray(value)) return value.length > 0
        if (typeof value === 'object' && value !== null) {
            return Object.values(value).some(v => v !== null && v !== '')
        }
        return value !== null && value !== '' && value !== undefined
    })
})

const activeFilterCount = computed(() => {
    let count = 0
    const filters = props.currentFilters
    
    if (filters.category_ids?.length) count++
    if (filters.brand_ids?.length) count++
    if (filters.stock_status?.length) count++
    if (filters.min_price !== undefined || filters.max_price !== undefined) count++
    if (filters.condition?.length) count++
    if (filters.location_ids?.length) count++
    if (filters.created_from || filters.created_to) count++
    if (filters.search) count++
    
    return count
})

const defaultPreset = computed(() => {
    return localPresets.value.find(p => p.is_default)
})

// Watch for preset changes from props
watch(() => props.presets, (newPresets) => {
    localPresets.value = [...newPresets]
}, { deep: true })

// Methods
const openSaveModal = () => {
    presetName.value = ''
    setAsDefault.value = false
    error.value = ''
    showModal.value = true
}

const closeModal = () => {
    showModal.value = false
    presetName.value = ''
    setAsDefault.value = false
    error.value = ''
}

const savePreset = async () => {
    if (!presetName.value.trim()) {
        error.value = 'Please enter a name for this preset'
        return
    }
    
    if (!hasActiveFilters.value) {
        error.value = 'No active filters to save'
        return
    }
    
    isSaving.value = true
    error.value = ''
    
    try {
        const response = await fetch(props.saveUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
            },
            body: JSON.stringify({
                name: presetName.value.trim(),
                filters: props.currentFilters,
                is_default: setAsDefault.value
            })
        })
        
        if (!response.ok) {
            const data = await response.json()
            throw new Error(data.message || 'Failed to save preset')
        }
        
        const data = await response.json()
        
        // If setting as default, update other presets
        if (setAsDefault.value) {
            localPresets.value = localPresets.value.map(p => ({
                ...p,
                is_default: false
            }))
        }
        
        localPresets.value.push(data.preset)
        emit('preset-saved', data.preset)
        closeModal()
    } catch (e) {
        error.value = e.message || 'Failed to save preset'
    } finally {
        isSaving.value = false
    }
}

const applyPreset = (preset) => {
    emit('preset-applied', preset.filters)
}

const confirmDelete = (preset) => {
    showConfirmDelete.value = preset.id
}

const cancelDelete = () => {
    showConfirmDelete.value = null
}

const deletePreset = async (preset) => {
    isDeleting.value = preset.id
    
    try {
        const response = await fetch(`${props.deleteUrl}/${preset.id}`, {
            method: 'DELETE',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
            }
        })
        
        if (!response.ok) {
            throw new Error('Failed to delete preset')
        }
        
        localPresets.value = localPresets.value.filter(p => p.id !== preset.id)
        emit('preset-deleted', preset)
    } catch (e) {
        console.error('Failed to delete preset:', e)
    } finally {
        isDeleting.value = null
        showConfirmDelete.value = null
    }
}

const setDefaultPreset = async (preset) => {
    try {
        const response = await fetch(`${props.saveUrl}/${preset.id}/default`, {
            method: 'PUT',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
            }
        })
        
        if (response.ok) {
            localPresets.value = localPresets.value.map(p => ({
                ...p,
                is_default: p.id === preset.id
            }))
        }
    } catch (e) {
        console.error('Failed to set default preset:', e)
    }
}

// Format filter summary for display
const formatFilterSummary = (filters) => {
    const parts = []
    
    if (filters.search) parts.push(`"${filters.search}"`)
    if (filters.category_ids?.length) parts.push(`${filters.category_ids.length} categories`)
    if (filters.brand_ids?.length) parts.push(`${filters.brand_ids.length} brands`)
    if (filters.stock_status?.length) parts.push(filters.stock_status.join(', '))
    if (filters.min_price !== undefined || filters.max_price !== undefined) {
        parts.push(`$${filters.min_price || 0} - $${filters.max_price || '∞'}`)
    }
    if (filters.condition?.length) parts.push(`${filters.condition.length} conditions`)
    
    return parts.length > 0 ? parts.join(' • ') : 'No filters'
}

// Count filters in a preset
const countPresetFilters = (filters) => {
    let count = 0
    if (filters.search) count++
    if (filters.category_ids?.length) count++
    if (filters.brand_ids?.length) count++
    if (filters.stock_status?.length) count++
    if (filters.min_price !== undefined || filters.max_price !== undefined) count++
    if (filters.condition?.length) count++
    if (filters.location_ids?.length) count++
    if (filters.created_from || filters.created_to) count++
    return count
}
</script>

<template>
    <div class="filter-presets">
        <!-- Quick Access Bar -->
        <div class="flex items-center gap-2 flex-wrap">
            <!-- Preset Pills -->
            <button
                v-for="preset in localPresets.slice(0, 5)"
                :key="preset.id"
                @click="applyPreset(preset)"
                :class="[
                    'inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-sm font-medium transition-all',
                    preset.is_default
                        ? 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900/50 dark:text-indigo-300 ring-2 ring-indigo-500/30'
                        : 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600'
                ]"
            >
                <svg v-if="preset.is_default" class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" clip-rule="evenodd" />
                </svg>
                {{ preset.name }}
                <span class="text-xs opacity-70">({{ countPresetFilters(preset.filters) }})</span>
            </button>

            <!-- More presets dropdown if > 5 -->
            <div v-if="localPresets.length > 5" class="relative group">
                <button class="inline-flex items-center gap-1 px-3 py-1.5 rounded-full text-sm font-medium bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400 hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors">
                    +{{ localPresets.length - 5 }} more
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </button>
                
                <div class="absolute top-full left-0 mt-1 w-56 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-lg z-20 py-1 hidden group-hover:block">
                    <button
                        v-for="preset in localPresets.slice(5)"
                        :key="preset.id"
                        @click="applyPreset(preset)"
                        class="w-full flex items-center justify-between px-3 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700"
                    >
                        <span class="truncate">{{ preset.name }}</span>
                        <span class="text-xs text-gray-400">({{ countPresetFilters(preset.filters) }})</span>
                    </button>
                </div>
            </div>

            <!-- Save Current Filters Button -->
            <button
                v-if="hasActiveFilters"
                @click="openSaveModal"
                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-sm font-medium bg-indigo-50 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-400 hover:bg-indigo-100 dark:hover:bg-indigo-900/50 transition-colors border border-dashed border-indigo-300 dark:border-indigo-700"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                Save filters
                <span class="text-xs opacity-70">({{ activeFilterCount }})</span>
            </button>

            <!-- Manage Presets Link -->
            <button
                v-if="localPresets.length > 0"
                @click="showModal = true; presetName = '___MANAGE___'"
                class="text-xs text-gray-500 dark:text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors"
            >
                Manage presets
            </button>
        </div>

        <!-- Save/Manage Modal -->
        <Teleport to="body">
            <div 
                v-if="showModal"
                class="fixed inset-0 z-50 overflow-y-auto"
            >
                <div class="flex min-h-screen items-center justify-center p-4">
                    <!-- Backdrop -->
                    <div 
                        class="fixed inset-0 bg-black/50 transition-opacity"
                        @click="closeModal"
                    ></div>

                    <!-- Modal Content -->
                    <div class="relative w-full max-w-lg bg-white dark:bg-gray-800 rounded-2xl shadow-xl">
                        <!-- Header -->
                        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                                {{ presetName === '___MANAGE___' ? 'Manage Filter Presets' : 'Save Filter Preset' }}
                            </h3>
                            <button
                                @click="closeModal"
                                class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition-colors"
                            >
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>

                        <!-- Save Form -->
                        <div v-if="presetName !== '___MANAGE___'" class="p-6 space-y-4">
                            <!-- Error Message -->
                            <div 
                                v-if="error"
                                class="p-3 bg-red-50 dark:bg-red-900/30 border border-red-200 dark:border-red-800 rounded-lg text-sm text-red-600 dark:text-red-400"
                            >
                                {{ error }}
                            </div>

                            <!-- Preset Name Input -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                    Preset Name
                                </label>
                                <input
                                    v-model="presetName"
                                    type="text"
                                    placeholder="e.g., Low Stock Electronics"
                                    class="w-full px-4 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white placeholder-gray-400 focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                                    @keydown.enter.prevent="savePreset"
                                />
                            </div>

                            <!-- Current Filters Preview -->
                            <div class="p-3 bg-gray-50 dark:bg-gray-750 rounded-lg">
                                <div class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-2">
                                    Filters to save
                                </div>
                                <div class="text-sm text-gray-700 dark:text-gray-300">
                                    {{ formatFilterSummary(currentFilters) }}
                                </div>
                            </div>

                            <!-- Set as Default Checkbox -->
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input
                                    v-model="setAsDefault"
                                    type="checkbox"
                                    class="w-4 h-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-700"
                                />
                                <span class="text-sm text-gray-700 dark:text-gray-300">Set as default filter</span>
                            </label>

                            <!-- Save Button -->
                            <button
                                @click="savePreset"
                                :disabled="isSaving"
                                class="w-full flex items-center justify-center gap-2 px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 disabled:bg-indigo-400 text-white font-medium rounded-lg transition-colors"
                            >
                                <svg v-if="isSaving" class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                <svg v-else class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4" />
                                </svg>
                                {{ isSaving ? 'Saving...' : 'Save Preset' }}
                            </button>
                        </div>

                        <!-- Manage Presets List -->
                        <div v-else class="p-6">
                            <div v-if="localPresets.length === 0" class="text-center py-8 text-gray-500 dark:text-gray-400">
                                <svg class="w-12 h-12 mx-auto mb-3 text-gray-300 dark:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                                </svg>
                                <p class="text-sm">No saved presets yet</p>
                                <p class="text-xs mt-1">Apply some filters and save them for quick access</p>
                            </div>

                            <div v-else class="space-y-2 max-h-96 overflow-y-auto">
                                <div
                                    v-for="preset in localPresets"
                                    :key="preset.id"
                                    class="group flex items-start gap-3 p-3 bg-gray-50 dark:bg-gray-750 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors"
                                >
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center gap-2">
                                            <span class="font-medium text-gray-900 dark:text-white">{{ preset.name }}</span>
                                            <span 
                                                v-if="preset.is_default"
                                                class="px-1.5 py-0.5 text-xs bg-indigo-100 text-indigo-700 dark:bg-indigo-900/50 dark:text-indigo-300 rounded"
                                            >
                                                Default
                                            </span>
                                        </div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400 mt-1 truncate">
                                            {{ formatFilterSummary(preset.filters) }}
                                        </div>
                                    </div>
                                    
                                    <div class="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                                        <!-- Apply -->
                                        <button
                                            @click="applyPreset(preset); closeModal()"
                                            class="p-1.5 text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors"
                                            title="Apply this preset"
                                        >
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                            </svg>
                                        </button>
                                        
                                        <!-- Set Default -->
                                        <button
                                            v-if="!preset.is_default"
                                            @click="setDefaultPreset(preset)"
                                            class="p-1.5 text-gray-400 hover:text-amber-500 dark:hover:text-amber-400 transition-colors"
                                            title="Set as default"
                                        >
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z" />
                                            </svg>
                                        </button>
                                        
                                        <!-- Delete -->
                                        <button
                                            v-if="showConfirmDelete !== preset.id"
                                            @click="confirmDelete(preset)"
                                            class="p-1.5 text-gray-400 hover:text-red-600 dark:hover:text-red-400 transition-colors"
                                            title="Delete preset"
                                        >
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                            </svg>
                                        </button>
                                        
                                        <!-- Confirm Delete -->
                                        <div v-if="showConfirmDelete === preset.id" class="flex items-center gap-1">
                                            <button
                                                @click="deletePreset(preset)"
                                                :disabled="isDeleting === preset.id"
                                                class="px-2 py-1 text-xs bg-red-600 hover:bg-red-700 text-white rounded transition-colors"
                                            >
                                                {{ isDeleting === preset.id ? '...' : 'Delete' }}
                                            </button>
                                            <button
                                                @click="cancelDelete"
                                                class="px-2 py-1 text-xs bg-gray-200 dark:bg-gray-600 text-gray-700 dark:text-gray-300 rounded hover:bg-gray-300 dark:hover:bg-gray-500 transition-colors"
                                            >
                                                Cancel
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </Teleport>
    </div>
</template>

<style scoped>
/* Smooth modal animation */
.fixed {
    animation: fadeIn 0.15s ease-out;
}

@keyframes fadeIn {
    from {
        opacity: 0;
    }
    to {
        opacity: 1;
    }
}

/* Custom scrollbar for preset list */
.max-h-96::-webkit-scrollbar {
    width: 6px;
}

.max-h-96::-webkit-scrollbar-track {
    background: transparent;
}

.max-h-96::-webkit-scrollbar-thumb {
    background-color: rgba(156, 163, 175, 0.5);
    border-radius: 3px;
}

.dark .max-h-96::-webkit-scrollbar-thumb {
    background-color: rgba(75, 85, 99, 0.5);
}
</style>
