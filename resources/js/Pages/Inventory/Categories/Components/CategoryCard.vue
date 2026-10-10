<template>
    <div
        :class="[
            'category-card group relative bg-white dark:bg-gray-800 rounded-xl border transition-all duration-200 overflow-hidden',
            selected 
                ? 'border-indigo-300 dark:border-indigo-600 ring-2 ring-indigo-500 ring-opacity-20 shadow-lg' 
                : 'border-gray-200 dark:border-gray-700 hover:border-gray-300 dark:hover:border-gray-600 hover:shadow-md',
            !category.is_active ? 'opacity-75' : ''
        ]"
        @click="handleCardClick"
    >
        <!-- Selection Checkbox -->
        <div class="absolute top-4 left-4 z-10">
            <input
                type="checkbox"
                :checked="selected"
                @click.stop
                @change="handleSelection"
                class="rounded border-gray-300 dark:border-gray-600 text-indigo-600 shadow-sm focus:ring-indigo-500"
            />
        </div>

        <!-- Actions Menu -->
        <div class="absolute top-4 right-4 z-10 opacity-0 group-hover:opacity-100 transition-opacity duration-200">
            <div class="relative" ref="dropdownRef">
                <button
                    @click.stop="showActionsMenu = !showActionsMenu"
                    class="p-2 rounded-lg bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-600 text-gray-500 hover:text-gray-700 dark:hover:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors shadow-sm"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z" />
                    </svg>
                </button>

                <!-- Dropdown Menu -->
                <transition
                    enter-active-class="transition ease-out duration-100"
                    enter-from-class="transform opacity-0 scale-95"
                    enter-to-class="transform opacity-100 scale-100"
                    leave-active-class="transition ease-in duration-75"
                    leave-from-class="transform opacity-100 scale-100"
                    leave-to-class="transform opacity-0 scale-95"
                >
                    <div
                        v-show="showActionsMenu"
                        class="absolute right-0 mt-2 w-48 rounded-lg shadow-lg bg-white dark:bg-gray-800 ring-1 ring-black ring-opacity-5 z-20 border border-gray-200 dark:border-gray-700"
                    >
                        <div class="py-1">
                            <button
                                @click="$emit('edit', category)"
                                class="flex items-center w-full px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors"
                            >
                                <svg class="w-4 h-4 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                </svg>
                                Edit Category
                            </button>
                            <button
                                @click="$emit('toggle-status', category)"
                                class="flex items-center w-full px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors"
                            >
                                <svg v-if="category.is_active" class="w-4 h-4 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.878 9.878L3 3m6.878 6.878L21 21" />
                                </svg>
                                <svg v-else class="w-4 h-4 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                </svg>
                                {{ category.is_active ? 'Deactivate' : 'Activate' }}
                            </button>
                            <div class="border-t border-gray-100 dark:border-gray-600 my-1"></div>
                            <button
                                @click="$emit('duplicate', category)"
                                class="flex items-center w-full px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors"
                            >
                                <svg class="w-4 h-4 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7v8a2 2 0 002 2h6M8 7V5a2 2 0 012-2h4.586a1 1 0 01.707.293l4.414 4.414a1 1 0 01.293.707V15a2 2 0 01-2 2h-2M8 7H6a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2v-2" />
                                </svg>
                                Duplicate
                            </button>
                            <button
                                @click="$emit('delete', category)"
                                :disabled="(category.products_count || 0) > 0"
                                :class="[
                                    'flex items-center w-full px-4 py-2 text-sm transition-colors',
                                    (category.products_count || 0) > 0
                                        ? 'text-gray-400 dark:text-gray-600 cursor-not-allowed'
                                        : 'text-red-700 dark:text-red-400 hover:bg-red-100 dark:hover:bg-red-900/30'
                                ]"
                            >
                                <svg class="w-4 h-4 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                </svg>
                                Delete
                            </button>
                        </div>
                    </div>
                </transition>
            </div>
        </div>

        <!-- Category Header -->
        <div class="p-6 pb-4">
            <!-- Category Icon and Color -->
            <div class="flex items-center mb-4">
                <div 
                    :class="[
                        'flex-shrink-0 w-12 h-12 rounded-xl flex items-center justify-center mr-4 shadow-sm',
                    ]"
                    :style="{ 
                        backgroundColor: category.color || '#6B7280',
                        background: category.image 
                            ? `url(${category.image}) center/cover` 
                            : category.color || '#6B7280'
                    }"
                >
                    <component
                        v-if="!category.image"
                        :is="getCategoryIcon"
                        class="w-6 h-6 text-white"
                    />
                </div>
                
                <div class="flex-1 min-w-0">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 truncate">
                        {{ category.name }}
                    </h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                        {{ getCategoryPath }}
                    </p>
                </div>
            </div>

            <!-- Category Description -->
            <p 
                v-if="category.description"
                class="text-sm text-gray-600 dark:text-gray-300 mb-4 line-clamp-2"
            >
                {{ category.description }}
            </p>

            <!-- Status Badges -->
            <div class="flex items-center space-x-2 mb-4">
                <span
                    v-if="!category.is_active"
                    class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400"
                >
                    Inactive
                </span>
                <span
                    v-if="category.is_system"
                    class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-400"
                >
                    System
                </span>
                <span
                    v-if="(category.children_count || 0) > 0"
                    class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400"
                >
                    {{ category.children_count }} subcategories
                </span>
            </div>
        </div>

        <!-- Statistics -->
        <div class="px-6 py-4 bg-gray-50 dark:bg-gray-700/50 border-t border-gray-100 dark:border-gray-600">
            <div class="grid grid-cols-2 gap-4">
                <div class="text-center">
                    <div class="text-2xl font-bold text-gray-900 dark:text-gray-100">
                        {{ category.products_count || 0 }}
                    </div>
                    <div class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                        Total Products
                    </div>
                </div>
                <div class="text-center">
                    <div class="text-2xl font-bold text-green-600 dark:text-green-400">
                        {{ category.active_products_count || 0 }}
                    </div>
                    <div class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                        Active Products
                    </div>
                </div>
            </div>
        </div>

        <!-- Metadata Section -->
        <div 
            v-if="hasVisibleMetadata" 
            class="px-6 py-3 border-t border-gray-100 dark:border-gray-600"
        >
            <div class="flex items-center justify-between text-xs text-gray-500 dark:text-gray-400 mb-2">
                <span class="font-medium uppercase tracking-wider">Metadata</span>
                <span v-if="metadataCount > 3">+{{ metadataCount - 3 }} more</span>
            </div>
            <div class="flex flex-wrap gap-1">
                <span
                    v-for="(value, key) in visibleMetadata"
                    :key="key"
                    class="inline-flex items-center px-2 py-1 rounded-md text-xs bg-gray-100 text-gray-700 dark:bg-gray-600 dark:text-gray-300"
                >
                    <span class="font-medium">{{ key }}:</span>
                    <span class="ml-1">{{ formatMetadataValue(value) }}</span>
                </span>
            </div>
        </div>

        <!-- Quick Actions Footer -->
        <div class="px-6 py-3 bg-gray-50 dark:bg-gray-700/50 border-t border-gray-100 dark:border-gray-600">
            <div class="flex items-center justify-between">
                <div class="text-xs text-gray-500 dark:text-gray-400">
                    Last updated: {{ formatDate(category.updated_at) }}
                </div>
                <div class="flex items-center space-x-2">
                    <button
                        @click.stop="$emit('edit', category)"
                        class="inline-flex items-center px-2 py-1 rounded-md text-xs font-medium text-indigo-700 dark:text-indigo-400 bg-indigo-100 dark:bg-indigo-900/30 hover:bg-indigo-200 dark:hover:bg-indigo-900/50 transition-colors"
                    >
                        Edit
                    </button>
                </div>
            </div>
        </div>

        <!-- Loading Overlay -->
        <div 
            v-if="isLoading"
            class="absolute inset-0 bg-white dark:bg-gray-800 bg-opacity-75 dark:bg-opacity-75 flex items-center justify-center"
        >
            <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-indigo-600"></div>
        </div>
    </div>
</template>

<script setup>
import { computed, ref, onMounted, onUnmounted } from 'vue'

const props = defineProps({
    category: {
        type: Object,
        required: true
    },
    selected: {
        type: Boolean,
        default: false
    }
})

const emit = defineEmits([
    'select', 'edit', 'delete', 'view', 'toggle-status', 'duplicate'
])

// State
const showActionsMenu = ref(false)
const dropdownRef = ref(null)
const isLoading = ref(false)

// Computed properties
const getCategoryIcon = computed(() => {
    // Map category icons to actual icon components
    // This would be expanded based on your icon system
    if (props.category.icon) {
        // Return appropriate icon component
        return 'svg' // Placeholder
    }
    return 'svg' // Default folder icon
})

const getCategoryPath = computed(() => {
    return props.category.full_path || props.category.path || 'Root Category'
})

const hasVisibleMetadata = computed(() => {
    return props.category.metadata && 
           typeof props.category.metadata === 'object' && 
           Object.keys(props.category.metadata).length > 0
})

const visibleMetadata = computed(() => {
    if (!hasVisibleMetadata.value) {
        return {}
    }
    
    const entries = Object.entries(props.category.metadata)
    return Object.fromEntries(entries.slice(0, 3))
})

const metadataCount = computed(() => {
    return hasVisibleMetadata.value ? Object.keys(props.category.metadata).length : 0
})

// Methods
const handleCardClick = () => {
    if (!showActionsMenu.value) {
        emit('view', props.category)
    }
}

const handleSelection = (event) => {
    emit('select', {
        id: props.category.id,
        selected: event.target.checked
    })
}

const formatMetadataValue = (value) => {
    if (Array.isArray(value)) {
        return value.slice(0, 2).join(', ') + (value.length > 2 ? '...' : '')
    }
    if (typeof value === 'object') {
        return 'Object'
    }
    const str = String(value)
    return str.length > 15 ? str.substring(0, 15) + '...' : str
}

const formatDate = (dateString) => {
    if (!dateString) return 'N/A'
    
    const date = new Date(dateString)
    const now = new Date()
    const diffTime = Math.abs(now - date)
    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24))
    
    if (diffDays === 0) {
        return 'Today'
    } else if (diffDays === 1) {
        return 'Yesterday'
    } else if (diffDays < 7) {
        return `${diffDays} days ago`
    } else {
        return date.toLocaleDateString()
    }
}

const closeActionsMenu = (event) => {
    if (dropdownRef.value && !dropdownRef.value.contains(event.target)) {
        showActionsMenu.value = false
    }
}

// Lifecycle
onMounted(() => {
    document.addEventListener('click', closeActionsMenu)
})

onUnmounted(() => {
    document.removeEventListener('click', closeActionsMenu)
})
</script>

<style scoped>
.category-card {
    min-height: 280px;
    max-width: 350px;
}

.line-clamp-2 {
    overflow: hidden;
    display: -webkit-box;
    -webkit-box-orient: vertical;
    -webkit-line-clamp: 2;
    line-height: 1.4;
    max-height: 2.8em;
}

/* Hover effects */
.category-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 25px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
}

.dark .category-card:hover {
    box-shadow: 0 10px 25px -3px rgba(0, 0, 0, 0.3), 0 4px 6px -2px rgba(0, 0, 0, 0.2);
}

/* Selected state animation */
.category-card.selected {
    animation: selectedPulse 2s ease-in-out infinite;
}

@keyframes selectedPulse {
    0%, 100% {
        box-shadow: 0 0 0 0 rgba(79, 70, 229, 0.4);
    }
    50% {
        box-shadow: 0 0 0 8px rgba(79, 70, 229, 0);
    }
}

/* Loading state */
.category-card.loading {
    pointer-events: none;
}

/* Responsive adjustments */
@media (max-width: 640px) {
    .category-card {
        min-height: 240px;
    }
}
</style>
