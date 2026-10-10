<template>
    <div class="category-tree">
        <div class="space-y-1" v-if="categories.length > 0">
            <CategoryTreeNode
                v-for="category in rootCategories"
                :key="category.id"
                :category="category"
                :level="0"
                :selected="selected.includes(category.id)"
                :expanded="expandedCategories.has(category.id)"
                :has-children="hasChildren(category.id)"
                :children="categories"
                @select="$emit('select', $event)"
                @edit="$emit('edit', $event)"
                @delete="$emit('delete', $event)"
                @move="$emit('move', $event)"
                @toggle-status="$emit('toggle-status', $event)"
                @toggle-expand="toggleExpand"
                @view="$emit('view', $event)"
                @drop="handleDrop"
                @dragover="handleDragOver"
                @dragstart="handleDragStart"
            />
        </div>
        
        <!-- Empty State -->
        <div v-else class="text-center py-12">
            <div class="mx-auto w-16 h-16 bg-gray-100 dark:bg-gray-700 rounded-full flex items-center justify-center mb-4">
                <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                </svg>
            </div>
            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-2">No categories found</h3>
            <p class="text-gray-500 dark:text-gray-400">Categories will appear here once created.</p>
        </div>
    </div>
</template>

<script setup>
import { computed, ref, onMounted } from 'vue'
import CategoryTreeNode from './CategoryTreeNode.vue'

const props = defineProps({
    categories: {
        type: Array,
        required: true
    },
    selected: {
        type: Array,
        default: () => []
    }
})

const emit = defineEmits(['select', 'edit', 'delete', 'move', 'toggle-status', 'view'])

// State
const expandedCategories = ref(new Set())
const draggedCategory = ref(null)

// Computed
const rootCategories = computed(() => {
    return props.categories.filter(category => !category.parent_id)
})

// Methods
const toggleExpand = (categoryId) => {
    if (expandedCategories.value.has(categoryId)) {
        expandedCategories.value.delete(categoryId)
    } else {
        expandedCategories.value.add(categoryId)
    }
}

const expandAll = () => {
    props.categories.forEach(category => {
        if (hasChildren(category.id)) {
            expandedCategories.value.add(category.id)
        }
    })
}

const collapseAll = () => {
    expandedCategories.value.clear()
}

const hasChildren = (categoryId) => {
    return props.categories.some(category => category.parent_id === categoryId)
}

const getChildren = (parentId) => {
    return props.categories.filter(category => category.parent_id === parentId)
}

// Drag and Drop handlers
const handleDragStart = (event, category) => {
    if (!category) return // Guard against native event without category
    draggedCategory.value = category
    event.dataTransfer.effectAllowed = 'move'
    event.dataTransfer.setData('text/plain', category.id)
}

const handleDragOver = (event) => {
    event.preventDefault()
    event.dataTransfer.dropEffect = 'move'
}

const handleDrop = (event, targetCategory) => {
    event.preventDefault()

    if (!targetCategory || !draggedCategory.value || draggedCategory.value.id === targetCategory.id) {
        return
    }
    
    // Prevent dropping a parent onto its own child
    if (isDescendant(targetCategory.id, draggedCategory.value.id)) {
        return
    }
    
    emit('move', {
        category: draggedCategory.value,
        newParentId: targetCategory.id,
        position: 0
    })
    
    draggedCategory.value = null
}

const isDescendant = (ancestorId, descendantId) => {
    const children = getChildren(ancestorId)
    for (const child of children) {
        if (child.id === descendantId || isDescendant(child.id, descendantId)) {
            return true
        }
    }
    return false
}

// Auto-expand categories with children on mount
onMounted(() => {
    props.categories.forEach(category => {
        if (hasChildren(category.id) && category.depth < 2) {
            expandedCategories.value.add(category.id)
        }
    })
})

// Expose methods for parent component
defineExpose({
    expandAll,
    collapseAll,
    getChildren,
    hasChildren
})
</script>

<style scoped>
.category-tree {
    max-height: 60vh;
    overflow-y: auto;
}

/* Drag and drop visual feedback */
.dragging {
    opacity: 0.5;
}

.drag-over {
    background-color: rgba(59, 130, 246, 0.1);
    border: 2px dashed #3b82f6;
    border-radius: 8px;
}
</style>
