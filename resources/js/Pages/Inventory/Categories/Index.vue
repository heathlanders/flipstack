<template>
    <AppLayout
        title="Category Management"
        page-title="Category Management"
        page-description="Organize your products with hierarchical categories"
        :page-actions="pageActions"
    >

        <!-- Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-6 gap-4 mb-6">
            <StatCard
                title="Total Categories"
                :value="stats.total"
                change="+12%"
                icon="folder"
                color="blue"
                class="lg:col-span-2"
            />
            <StatCard 
                title="Active"
                :value="stats.active"
                icon="check-circle"
                color="green"
            />
            <StatCard 
                title="Inactive"
                :value="stats.inactive"
                icon="x-circle"
                color="gray"
            />
            <StatCard 
                title="With Products"
                :value="stats.with_products"
                icon="cube"
                color="purple"
            />
            <StatCard 
                title="Empty"
                :value="stats.empty"
                icon="exclamation-triangle"
                color="amber"
            />
        </div>

        <!-- Search and Filters -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 mb-6">
            <div class="p-6">
                <div class="flex flex-col lg:flex-row lg:items-center space-y-4 lg:space-y-0 lg:space-x-4">
                    <!-- Search -->
                    <div class="flex-1">
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <svg class="h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                </svg>
                            </div>
                            <input
                                v-model="filters.search"
                                type="text"
                                placeholder="Search categories..."
                                class="block w-full pl-10 pr-3 py-2.5 border border-gray-200 dark:border-gray-600 rounded-lg bg-gray-50 dark:bg-gray-700 text-gray-900 dark:text-gray-100 placeholder-gray-500 dark:placeholder-gray-400 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-colors"
                                @input="debouncedSearch"
                            />
                        </div>
                    </div>
                    
                    <!-- Status Filter -->
                    <div class="w-full lg:w-48">
                        <select
                            v-model="filters.status"
                            @change="applyFilters"
                            class="w-full px-3 py-2.5 border border-gray-200 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-colors"
                        >
                            <option value="all">All Status</option>
                            <option value="active">Active Only</option>
                            <option value="inactive">Inactive Only</option>
                        </select>
                    </div>
                    
                    <!-- Parent Filter -->
                    <div class="w-full lg:w-48" v-if="viewMode !== 'tree'">
                        <select
                            v-model="filters.parent_id"
                            @change="applyFilters"
                            class="w-full px-3 py-2.5 border border-gray-200 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-colors"
                        >
                            <option value="">All Categories</option>
                            <option value="root">Root Only</option>
                            <option v-for="parent in parentOptions" :key="parent.id" :value="parent.id">
                                {{ parent.name }}
                            </option>
                        </select>
                    </div>
                    
                    <!-- Clear Filters -->
                    <button
                        v-if="hasActiveFilters"
                        @click="clearFilters"
                        class="px-4 py-2.5 text-sm font-medium text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 bg-gray-100 dark:bg-gray-700 rounded-lg border border-gray-200 dark:border-gray-600 hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors"
                    >
                        Clear Filters
                    </button>
                </div>
            </div>
        </div>

        <!-- Bulk Actions -->
        <BulkActionsBar
            v-if="selectedCategories.length > 0"
            :selected-count="selectedCategories.length"
            @bulk-activate="handleBulkActivate"
            @bulk-deactivate="handleBulkDeactivate"  
            @bulk-delete="showBulkDeleteModal = true"
            class="mb-6"
        />

        <!-- Categories Display -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700">
            <!-- Tree View -->
            <div v-if="viewMode === 'tree'" class="p-6">
                <CategoryTree
                    :categories="Array.isArray(categories) ? categories : (categories.data || [])"
                    :selected="selectedCategories"
                    @select="handleSelection"
                    @edit="editCategory"
                    @delete="deleteCategory"
                    @move="handleMove"
                    @toggle-status="toggleCategoryStatus"
                    @view="viewCategory"
                />
            </div>
            
            <!-- Grid View -->
            <div v-else class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                    <CategoryCard
                        v-for="category in categories.data || categories"
                        :key="category.id"
                        :category="category"
                        :selected="selectedCategories.includes(category.id)"
                        @select="handleSelection"
                        @edit="editCategory"
                        @delete="deleteCategory"
                        @view="viewCategory"
                        @toggle-status="toggleCategoryStatus"
                        class="transform transition-all duration-200 hover:scale-105"
                    />
                </div>
                
                <!-- Pagination -->
                <div v-if="categories.links" class="mt-8 flex justify-center">
                    <Pagination :links="categories.links" />
                </div>
                
                <!-- Empty State -->
                <div v-if="(!categories.data || categories.data.length === 0) && !categories.length" class="text-center py-12">
                    <div class="mx-auto w-24 h-24 bg-gray-100 dark:bg-gray-700 rounded-full flex items-center justify-center mb-4">
                        <svg class="w-12 h-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                        </svg>
                    </div>
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-2">No categories found</h3>
                    <p class="text-gray-500 dark:text-gray-400 mb-6">Get started by creating your first category.</p>
                    <PrimaryButton type="button" @click="showCreateModal = true">
                        Create Category
                    </PrimaryButton>
                </div>
            </div>
        </div>

        <!-- Create/Edit Modal -->
        <CategoryModal
            :show="showCreateModal || showEditModal"
            :category="selectedCategory"
            :parent-options="parentOptions"
            :mode="showCreateModal ? 'create' : 'edit'"
            @close="closeModals"
            @save="handleSave"
        />

        <!-- Delete Confirmation Modal -->
        <ConfirmationModal
            :show="showDeleteModal"
            @close="showDeleteModal = false"
            @confirm="confirmDelete"
        >
            <template #title>
                Delete Category
            </template>
            <template #content>
                Are you sure you want to delete "{{ categoryToDelete?.name }}"? This action cannot be undone.
                <div v-if="categoryToDelete?.products_count > 0" class="mt-3 p-3 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg">
                    <div class="flex">
                        <svg class="w-5 h-5 text-red-400 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16c-.77.833.192 2.5 1.732 2.5z" />
                        </svg>
                        <div class="ml-3">
                            <h3 class="text-sm font-medium text-red-800 dark:text-red-400">
                                Category contains products
                            </h3>
                            <div class="text-sm text-red-700 dark:text-red-300 mt-1">
                                This category has {{ categoryToDelete?.products_count }} product(s). Please move or delete these products first.
                            </div>
                        </div>
                    </div>
                </div>
            </template>
            <template #footer>
                <SecondaryButton @click="showDeleteModal = false">
                    Cancel
                </SecondaryButton>
                <DangerButton 
                    @click="confirmDelete" 
                    class="ml-3"
                    :disabled="categoryToDelete?.products_count > 0"
                >
                    Delete Category
                </DangerButton>
            </template>
        </ConfirmationModal>

        <!-- Bulk Delete Modal -->
        <ConfirmationModal
            :show="showBulkDeleteModal"
            @close="showBulkDeleteModal = false"
            @confirm="confirmBulkDelete"
        >
            <template #title>
                Delete Categories
            </template>
            <template #content>
                Are you sure you want to delete {{ selectedCategories.length }} categories? This action cannot be undone.
            </template>
            <template #footer>
                <SecondaryButton @click="showBulkDeleteModal = false">
                    Cancel
                </SecondaryButton>
                <DangerButton @click="confirmBulkDelete" class="ml-3">
                    Delete Categories
                </DangerButton>
            </template>
        </ConfirmationModal>
    </AppLayout>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import { debounce } from 'lodash'
import AppLayout from '@/Layouts/AppLayout.vue'
import CategoryTree from './Components/CategoryTree.vue'
import CategoryCard from './Components/CategoryCard.vue'
import CategoryModal from './Components/CategoryModal.vue'
import BulkActionsBar from './Components/BulkActionsBar.vue'
import StatCard from '@/Components/StatCard.vue'
import Pagination from '@/Components/Pagination.vue'
import PrimaryButton from '@/Components/PrimaryButton.vue'
import SecondaryButton from '@/Components/SecondaryButton.vue'
import DangerButton from '@/Components/DangerButton.vue'
import ConfirmationModal from '@/Components/ConfirmationModal.vue'

const props = defineProps({
    categories: {
        type: [Array, Object],
        required: true
    },
    parentOptions: {
        type: Array,
        default: () => []
    },
    stats: {
        type: Object,
        required: true
    },
    filters: {
        type: Object,
        default: () => ({})
    },
    viewMode: {
        type: String,
        default: 'tree'
    }
})

// Reactive state
const viewMode = ref(props.viewMode)
const selectedCategories = ref([])
const showCreateModal = ref(false)
const showEditModal = ref(false)
const showDeleteModal = ref(false)
const showBulkDeleteModal = ref(false)
const selectedCategory = ref(null)
const categoryToDelete = ref(null)

const filters = reactive({
    search: props.filters.search || '',
    status: props.filters.status || 'all',
    parent_id: props.filters.parent_id || '',
    view: viewMode.value
})

// Computed properties
const hasActiveFilters = computed(() => {
    return filters.search || filters.status !== 'all' || filters.parent_id
})

// Methods
const setViewMode = (mode) => {
    viewMode.value = mode
    filters.view = mode
    applyFilters()
}

const handleSelection = (event) => {
    const categoryId = typeof event === 'string' ? event : event.id
    const isSelected = typeof event === 'object' ? event.selected : !selectedCategories.value.includes(categoryId)
    
    if (isSelected) {
        if (!selectedCategories.value.includes(categoryId)) {
            selectedCategories.value.push(categoryId)
        }
    } else {
        selectedCategories.value = selectedCategories.value.filter(id => id !== categoryId)
    }
}

const editCategory = (category) => {
    selectedCategory.value = category
    showEditModal.value = true
}

const deleteCategory = (category) => {
    categoryToDelete.value = category
    showDeleteModal.value = true
}

const viewCategory = (category) => {
    // Open edit modal in view mode (reusing edit modal for viewing details)
    editCategory(category)
}

const toggleCategoryStatus = async (category) => {
    try {
        await router.put(route('inventory.categories.update', category.id), {
            ...category,
            is_active: !category.is_active
        }, {
            preserveScroll: true,
            onSuccess: () => {
                category.is_active = !category.is_active
            }
        })
    } catch (error) {
        console.error('Failed to toggle category status:', error)
    }
}

const handleMove = async (moveData) => {
    try {
        await router.post(route('inventory.categories.move', moveData.category.id), {
            new_parent_id: moveData.newParentId,
            position: moveData.position
        }, {
            preserveScroll: true
        })
    } catch (error) {
        console.error('Failed to move category:', error)
    }
}

const applyFilters = () => {
    router.get(route('inventory.categories.index'), filters, {
        preserveState: true,
        preserveScroll: true,
        replace: true
    })
}

const debouncedSearch = debounce(() => {
    applyFilters()
}, 300)

const clearFilters = () => {
    Object.assign(filters, {
        search: '',
        status: 'all',
        parent_id: '',
        view: viewMode.value
    })
    selectedCategories.value = []
    applyFilters()
}

const closeModals = () => {
    showCreateModal.value = false
    showEditModal.value = false
    selectedCategory.value = null
}

const handleSave = () => {
    closeModals()
    selectedCategories.value = []
}

const confirmDelete = async () => {
    if (!categoryToDelete.value || categoryToDelete.value.products_count > 0) {
        return
    }
    
    try {
        await router.delete(route('inventory.categories.destroy', categoryToDelete.value.id), {
            preserveScroll: true
        })
        showDeleteModal.value = false
        categoryToDelete.value = null
    } catch (error) {
        console.error('Failed to delete category:', error)
    }
}

const handleBulkActivate = async () => {
    // Implementation for bulk activate
    selectedCategories.value = []
}

const handleBulkDeactivate = async () => {
    // Implementation for bulk deactivate
    selectedCategories.value = []
}

const confirmBulkDelete = async () => {
    // Implementation for bulk delete
    showBulkDeleteModal.value = false
    selectedCategories.value = []
}

// Page actions for the toolbar
const pageActions = computed(() => [
    {
        label: viewMode.value === 'tree' ? 'Grid View' : 'Tree View',
        icon: viewMode.value === 'tree' ? 'fas fa-th-large' : 'fas fa-sitemap',
        onClick: () => setViewMode(viewMode.value === 'tree' ? 'grid' : 'tree'),
        variant: 'secondary'
    },
    {
        label: 'Create Category',
        icon: 'fas fa-plus',
        onClick: () => showCreateModal.value = true,
        variant: 'primary'
    }
])

// Lifecycle
onMounted(() => {
    // Any initialization code
})
</script>

<style scoped>
/* Custom styles for the category management interface */
.category-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 1.5rem;
}

/* Smooth transitions for view switching */
.view-transition-enter-active,
.view-transition-leave-active {
    transition: opacity 0.3s ease, transform 0.3s ease;
}

.view-transition-enter-from {
    opacity: 0;
    transform: translateY(10px);
}

.view-transition-leave-to {
    opacity: 0;
    transform: translateY(-10px);
}

/* Custom scrollbar for tree view */
.category-tree::-webkit-scrollbar {
    width: 6px;
}

.category-tree::-webkit-scrollbar-track {
    background: transparent;
}

.category-tree::-webkit-scrollbar-thumb {
    background: rgba(156, 163, 175, 0.4);
    border-radius: 3px;
}

.category-tree::-webkit-scrollbar-thumb:hover {
    background: rgba(156, 163, 175, 0.6);
}
</style>
