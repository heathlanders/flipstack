<template>
    <div class="category-tree-node">
        <!-- Main Category Row -->
        <div
            :class="[
                'group flex items-center p-3 rounded-lg transition-all duration-200',
                'hover:bg-gray-50 dark:hover:bg-gray-700/50',
                selected ? 'bg-blue-50 dark:bg-blue-900/30 border border-blue-200 dark:border-blue-700' : '',
                isDragOver ? 'bg-blue-100 dark:bg-blue-800/40 border-2 border-dashed border-blue-400' : ''
            ]"
            :style="{ marginLeft: `${level * 24}px` }"
            draggable="true"
            @dragstart="handleDragStart"
            @dragover="handleDragOver"
            @dragleave="handleDragLeave"
            @drop="handleDrop"
        >
            <!-- Expand/Collapse Toggle -->
            <button
                v-if="hasChildren"
                @click="$emit('toggle-expand', category.id)"
                class="flex-shrink-0 w-6 h-6 flex items-center justify-center rounded hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors mr-2"
            >
                <svg
                    :class="[
                        'w-4 h-4 text-gray-500 transition-transform duration-200',
                        expanded ? 'transform rotate-90' : ''
                    ]"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                >
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                </svg>
            </button>
            <div v-else class="w-8 flex-shrink-0"></div>

            <!-- Selection Checkbox -->
            <input
                type="checkbox"
                :checked="selected"
                @change="handleSelectionChange"
                class="flex-shrink-0 rounded border-gray-300 dark:border-gray-600 text-indigo-600 shadow-sm focus:ring-indigo-500 mr-3"
            />

            <!-- Category Icon -->
            <div 
                :class="[
                    'flex-shrink-0 w-8 h-8 flex items-center justify-center rounded-lg mr-3',
                    category.color ? '' : 'bg-gray-100 dark:bg-gray-700'
                ]"
                :style="{ backgroundColor: category.color || '#6B7280' }"
            >
                <component
                    :is="getCategoryIcon"
                    :class="[
                        'w-5 h-5',
                        category.color ? 'text-white' : 'text-gray-500 dark:text-gray-400'
                    ]"
                />
            </div>

            <!-- Category Info -->
            <div class="flex-1 min-w-0">
                <div class="flex items-center">
                    <h3 class="font-medium text-gray-900 dark:text-gray-100 truncate">
                        {{ category.name }}
                    </h3>
                    
                    <!-- Status Badge -->
                    <span
                        v-if="!category.is_active"
                        class="ml-2 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400"
                    >
                        Inactive
                    </span>
                    
                    <!-- System Badge -->
                    <span
                        v-if="category.is_system"
                        class="ml-2 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-400"
                    >
                        System
                    </span>
                </div>
                
                <p v-if="category.description" class="text-sm text-gray-500 dark:text-gray-400 mt-1 line-clamp-1">
                    {{ category.description }}
                </p>
                
                <!-- Metadata pills -->
                <div v-if="category.metadata && Object.keys(category.metadata).length > 0" class="flex items-center mt-2 space-x-2">
                    <span
                        v-for="(value, key) in getVisibleMetadata"
                        :key="key"
                        class="inline-flex items-center px-2 py-1 rounded-full text-xs bg-gray-100 text-gray-700 dark:bg-gray-600 dark:text-gray-300"
                    >
                        {{ key }}: {{ formatMetadataValue(value) }}
                    </span>
                </div>
            </div>

            <!-- Product Count -->
            <div class="flex-shrink-0 text-right mr-4">
                <div class="text-sm font-medium text-gray-900 dark:text-gray-100">
                    {{ category.products_count || 0 }}
                </div>
                <div class="text-xs text-gray-500 dark:text-gray-400">
                    {{ (category.products_count || 0) === 1 ? 'product' : 'products' }}
                </div>
                <div v-if="category.active_products_count !== undefined" class="text-xs text-green-600 dark:text-green-400">
                    {{ category.active_products_count || 0 }} active
                </div>
            </div>

            <!-- Actions Menu -->
            <div class="flex-shrink-0 opacity-0 group-hover:opacity-100 transition-opacity duration-200">
                <div class="flex items-center space-x-1">
                    <!-- Edit -->
                    <button
                        @click.stop="$emit('edit', category)"
                        class="p-1.5 rounded-md text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-400 hover:bg-indigo-50 dark:hover:bg-indigo-900/30 transition-colors"
                        title="Edit Category"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                        </svg>
                    </button>

                    <!-- Toggle Status -->
                    <button
                        @click.stop="$emit('toggle-status', category)"
                        :class="[
                            'p-1.5 rounded-md transition-colors',
                            category.is_active
                                ? 'text-gray-400 hover:text-orange-600 dark:hover:text-orange-400 hover:bg-orange-50 dark:hover:bg-orange-900/30'
                                : 'text-gray-400 hover:text-green-600 dark:hover:text-green-400 hover:bg-green-50 dark:hover:bg-green-900/30'
                        ]"
                        :title="category.is_active ? 'Deactivate' : 'Activate'"
                    >
                        <svg v-if="category.is_active" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.878 9.878L3 3m6.878 6.878L21 21" />
                        </svg>
                        <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                        </svg>
                    </button>

                    <!-- Delete -->
                    <button
                        @click.stop="$emit('delete', category)"
                        class="p-1.5 rounded-md text-gray-400 hover:text-red-600 dark:hover:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/30 transition-colors"
                        title="Delete Category"
                        :disabled="(category.products_count || 0) > 0 || hasChildren"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                        </svg>
                    </button>

                    <!-- More Actions Dropdown -->
                    <div class="relative" ref="dropdownRef">
                        <button
                            @click.stop="toggleDropdown"
                            class="p-1.5 rounded-md text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors"
                            title="More Actions"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z" />
                            </svg>
                        </button>

                        <!-- Dropdown Menu -->
                        <div
                            v-show="showDropdown"
                            class="absolute right-0 mt-1 w-48 rounded-md shadow-lg bg-white dark:bg-gray-800 ring-1 ring-black ring-opacity-5 z-20"
                        >
                            <div class="py-1">
                                <button
                                    @click="addSubcategory"
                                    class="flex items-center w-full px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700"
                                >
                                    <svg class="w-4 h-4 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                                    </svg>
                                    Add Subcategory
                                </button>
                                <button
                                    @click="duplicateCategory"
                                    class="flex items-center w-full px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700"
                                >
                                    <svg class="w-4 h-4 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7v8a2 2 0 002 2h6M8 7V5a2 2 0 012-2h4.586a1 1 0 01.707.293l4.414 4.414a1 1 0 01.293.707V15a2 2 0 01-2 2h-2M8 7H6a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2v-2" />
                                    </svg>
                                    Duplicate
                                </button>
                                <button
                                    @click="exportCategory"
                                    class="flex items-center w-full px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700"
                                >
                                    <svg class="w-4 h-4 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                    </svg>
                                    Export Data
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Children Categories -->
        <div v-if="hasChildren && expanded" class="mt-1 ml-4 space-y-1">
            <CategoryTreeNode
                v-for="child in children"
                :key="child.id"
                :category="child"
                :level="level + 1"
                :selected="selected.includes ? selected.includes(child.id) : false"
                :expanded="expanded"
                :has-children="hasChildCategory(child.id)"
                :children="getChildCategories(child.id)"
                v-bind="$attrs"
                @select="$emit('select', $event)"
                @edit="$emit('edit', $event)"
                @delete="$emit('delete', $event)"
                @move="$emit('move', $event)"
                @toggle-status="$emit('toggle-status', $event)"
                @toggle-expand="$emit('toggle-expand', $event)"
                @view="$emit('view', $event)"
            />
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
    level: {
        type: Number,
        default: 0
    },
    selected: {
        type: [Boolean, Array],
        default: false
    },
    expanded: {
        type: Boolean,
        default: false
    },
    hasChildren: {
        type: Boolean,
        default: false
    },
    children: {
        type: Array,
        default: () => []
    }
})

const emit = defineEmits([
    'select', 'edit', 'delete', 'move', 'toggle-status',
    'toggle-expand', 'view', 'dragstart', 'dragover', 'drop'
])

// State
const showDropdown = ref(false)
const dropdownRef = ref(null)
const isDragOver = ref(false)

// Computed
const getCategoryIcon = computed(() => {
    if (props.category.icon) {
        // Return the icon component or SVG based on the icon name
        return 'svg' // Placeholder - you'd map icon names to actual icon components
    }
    return 'svg' // Default folder icon
})

const getVisibleMetadata = computed(() => {
    if (!props.category.metadata || typeof props.category.metadata !== 'object') {
        return {}
    }
    
    // Show only the first 3 metadata items
    const entries = Object.entries(props.category.metadata)
    return Object.fromEntries(entries.slice(0, 3))
})

// Methods
const handleSelectionChange = (event) => {
    emit('select', {
        id: props.category.id,
        selected: event.target.checked
    })
}

const toggleDropdown = () => {
    showDropdown.value = !showDropdown.value
}

const closeDropdown = (event) => {
    if (dropdownRef.value && !dropdownRef.value.contains(event.target)) {
        showDropdown.value = false
    }
}

const addSubcategory = () => {
    // Emit event to parent to open create modal with this category as parent
    emit('add-subcategory', props.category)
    showDropdown.value = false
}

const duplicateCategory = () => {
    // Emit event to parent to duplicate this category
    emit('duplicate', props.category)
    showDropdown.value = false
}

const exportCategory = () => {
    // Emit event to parent to export this category
    emit('export', props.category)
    showDropdown.value = false
}

const formatMetadataValue = (value) => {
    if (Array.isArray(value)) {
        return value.join(', ')
    }
    if (typeof value === 'object') {
        return JSON.stringify(value)
    }
    return String(value).substring(0, 20) + (String(value).length > 20 ? '...' : '')
}

const hasChildCategory = (categoryId) => {
    return props.children.some(child => child.parent_id === categoryId)
}

const getChildCategories = (parentId) => {
    return props.children.filter(child => child.parent_id === parentId)
}

// Drag and Drop
const handleDragStart = (event) => {
    event.dataTransfer.effectAllowed = 'move'
    event.dataTransfer.setData('application/json', JSON.stringify(props.category))
    emit('dragstart', event, props.category)
}

const handleDragOver = (event) => {
    event.preventDefault()
    event.dataTransfer.dropEffect = 'move'
    isDragOver.value = true
    emit('dragover', event)
}

const handleDragLeave = (event) => {
    isDragOver.value = false
}

const handleDrop = (event) => {
    event.preventDefault()
    isDragOver.value = false
    
    try {
        const draggedData = JSON.parse(event.dataTransfer.getData('application/json'))
        if (draggedData.id !== props.category.id) {
            emit('drop', event, props.category)
        }
    } catch (error) {
        console.error('Error handling drop:', error)
    }
}

// Lifecycle
onMounted(() => {
    document.addEventListener('click', closeDropdown)
})

onUnmounted(() => {
    document.removeEventListener('click', closeDropdown)
})
</script>

<style scoped>
.line-clamp-1 {
    overflow: hidden;
    display: -webkit-box;
    -webkit-box-orient: vertical;
    -webkit-line-clamp: 1;
}

/* Drag visual feedback */
.category-tree-node.dragging {
    opacity: 0.5;
}

/* Drop zone highlighting */
.category-tree-node.drag-over {
    background-color: rgba(59, 130, 246, 0.1);
    border: 2px dashed #3b82f6;
}

/* Smooth transitions */
.category-tree-node {
    transition: all 0.2s ease;
}

/* Custom scrollbar for metadata */
.metadata-container::-webkit-scrollbar {
    height: 4px;
}

.metadata-container::-webkit-scrollbar-track {
    background: transparent;
}

.metadata-container::-webkit-scrollbar-thumb {
    background: rgba(156, 163, 175, 0.4);
    border-radius: 2px;
}
</style>
