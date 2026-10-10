<script setup>
import { ref, computed, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import DashboardCard from '@/Components/Cards/DashboardCard.vue'
import SearchInput from '@/Components/Forms/SearchInput.vue'
import PaginationLinks from '@/Components/Pagination/PaginationLinks.vue'
import Modal from '@/Components/Modal.vue'
import DangerButton from '@/Components/DangerButton.vue'
import SecondaryButton from '@/Components/SecondaryButton.vue'
import PrimaryButton from '@/Components/PrimaryButton.vue'

const props = defineProps({
    roles: Object,
    stats: Object,
    filters: Object,
    available_permissions: Object,
    can_create_role: Boolean,
    can_manage_permissions: Boolean
})

// Reactive filters
const search = ref(props.filters.search || '')
const selectedScope = ref(props.filters.scope || '')

// Modal states
const showDeleteModal = ref(false)
const showPermissionsModal = ref(false)
const roleToDelete = ref(null)
const roleForPermissions = ref(null)

// Update URL when filters change
watch([search, selectedScope], () => {
    const params = {}
    if (search.value) params.search = search.value
    if (selectedScope.value) params.scope = selectedScope.value
    
    router.get(route('roles.index'), params, {
        preserveState: true,
        preserveScroll: true,
        replace: true
    })
}, { debounce: 300 })

// Clear all filters
const clearFilters = () => {
    search.value = ''
    selectedScope.value = ''
}

// Role actions
const confirmDeleteRole = (role) => {
    roleToDelete.value = role
    showDeleteModal.value = true
}

const deleteRole = () => {
    if (roleToDelete.value) {
        router.delete(route('roles.destroy', roleToDelete.value.id), {
            preserveScroll: true,
            onSuccess: () => {
                showDeleteModal.value = false
                roleToDelete.value = null
            }
        })
    }
}

const viewPermissions = (role) => {
    roleForPermissions.value = role
    showPermissionsModal.value = true
}

const viewDetails = (role) => {
    router.visit(route('roles.show', role.id))
}

const editRole = (role) => {
    router.visit(route('roles.edit', role.id))
}

// Get role color and styling
const getRoleClasses = (roleSlug) => {
    const colorMap = {
        organization_owner: {
            bg: 'bg-purple-100 dark:bg-purple-900/30',
            text: 'text-purple-600 dark:text-purple-400',
            bgIcon: 'bg-purple-100 dark:bg-purple-900/30',
            textIcon: 'text-purple-600 dark:text-purple-400'
        },
        admin: {
            bg: 'bg-red-100 dark:bg-red-900/30',
            text: 'text-red-600 dark:text-red-400',
            bgIcon: 'bg-red-100 dark:bg-red-900/30',
            textIcon: 'text-red-600 dark:text-red-400'
        },
        buyer: {
            bg: 'bg-blue-100 dark:bg-blue-900/30',
            text: 'text-blue-600 dark:text-blue-400',
            bgIcon: 'bg-blue-100 dark:bg-blue-900/30',
            textIcon: 'text-blue-600 dark:text-blue-400'
        },
        lister: {
            bg: 'bg-emerald-100 dark:bg-emerald-900/30',
            text: 'text-emerald-600 dark:text-emerald-400',
            bgIcon: 'bg-emerald-100 dark:bg-emerald-900/30',
            textIcon: 'text-emerald-600 dark:text-emerald-400'
        },
        fulfillment_manager: {
            bg: 'bg-orange-100 dark:bg-orange-900/30',
            text: 'text-orange-600 dark:text-orange-400',
            bgIcon: 'bg-orange-100 dark:bg-orange-900/30',
            textIcon: 'text-orange-600 dark:text-orange-400'
        },
        support_agent: {
            bg: 'bg-pink-100 dark:bg-pink-900/30',
            text: 'text-pink-600 dark:text-pink-400',
            bgIcon: 'bg-pink-100 dark:bg-pink-900/30',
            textIcon: 'text-pink-600 dark:text-pink-400'
        }
    }
    return colorMap[roleSlug] || {
        bg: 'bg-gray-100 dark:bg-gray-900/30',
        text: 'text-gray-600 dark:text-gray-400',
        bgIcon: 'bg-gray-100 dark:bg-gray-900/30',
        textIcon: 'text-gray-600 dark:text-gray-400'
    }
}

const getRoleIcon = (roleSlug) => {
    const icons = {
        organization_owner: 'fas fa-crown',
        admin: 'fas fa-user-cog',
        buyer: 'fas fa-shopping-cart',
        lister: 'fas fa-list-alt',
        fulfillment_manager: 'fas fa-truck',
        support_agent: 'fas fa-headset'
    }
    return icons[roleSlug] || 'fas fa-user'
}

// Permission categorization
const categorizePermissions = (permissions) => {
    const categories = {}
    permissions.forEach(permission => {
        const category = permission.split('.')[0]
        const categoryName = category.charAt(0).toUpperCase() + category.slice(1)

        if (!categories[categoryName]) {
            categories[categoryName] = []
        }
        categories[categoryName].push(permission)
    })
    return categories
}

// Page actions for the toolbar
const pageActions = computed(() => {
    const actions = []

    if (props.can_create_role) {
        actions.push({
            label: 'Create Role',
            icon: 'fas fa-plus',
            href: route('roles.create'),
            variant: 'primary'
        })
    }

    return actions
})
</script>

<template>
    <AppLayout
        title="Role Management"
        page-title="Role Management"
        page-description="Manage roles and permissions across your organization"
        :page-actions="pageActions"
    >

        <div class="space-y-6">
            <!-- Role Statistics Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <DashboardCard 
                    title="Total Roles" 
                    :value="stats.total_roles"
                    icon="fas fa-user-tag"
                    color="blue"
                    subtitle="System and custom roles"
                />
                
                <DashboardCard 
                    title="Custom Roles" 
                    :value="stats.custom_roles"
                    icon="fas fa-user-cog"
                    color="purple"
                    subtitle="Organization-specific"
                />
                
                <DashboardCard 
                    title="System Roles" 
                    :value="stats.system_roles"
                    icon="fas fa-shield-alt"
                    color="emerald"
                    subtitle="Built-in roles"
                />
                
                <DashboardCard 
                    title="Roles in Use" 
                    :value="stats.roles_in_use"
                    icon="fas fa-users"
                    color="orange"
                    subtitle="Assigned to users"
                />
            </div>

            <!-- Filters & Search -->
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                <div class="flex flex-col md:flex-row md:items-center md:justify-between space-y-4 md:space-y-0">
                    <!-- Search -->
                    <div class="flex-1 max-w-md">
                        <SearchInput 
                            v-model="search"
                            placeholder="Search roles by name or description..."
                            class="w-full"
                        />
                    </div>
                    
                    <!-- Filters -->
                    <div class="flex flex-col sm:flex-row space-y-2 sm:space-y-0 sm:space-x-4">
                        <!-- Scope Filter -->
                        <select v-model="selectedScope"
                                class="rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm">
                            <option value="">All Scopes</option>
                            <option value="system">System Roles</option>
                            <option value="organization">Organization Roles</option>
                            <option value="team">Team Roles</option>
                        </select>
                        
                        <!-- Clear Filters -->
                        <button @click="clearFilters"
                                v-if="search || selectedScope"
                                class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 text-sm px-3 py-2">
                            <i class="fas fa-times mr-1"></i>
                            Clear
                        </button>
                    </div>
                </div>
            </div>

            <!-- Roles Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <div v-for="role in roles.data" :key="role.id"
                     class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 p-6 hover:shadow-md transition-shadow">
                    
                    <!-- Role Header -->
                    <div class="flex items-start space-x-3 mb-4">
                        <!-- Role Icon -->
                        <div :class="[
                            'w-12 h-12 rounded-xl flex items-center justify-center flex-shrink-0',
                            getRoleClasses(role.slug).bg
                        ]">
                            <i :class="[
                                getRoleIcon(role.slug),
                                'text-lg',
                                getRoleClasses(role.slug).text
                            ]"></i>
                        </div>
                        
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center space-x-2 mb-1">
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-white truncate">
                                    {{ role.name }}
                                </h3>
                                <!-- System/Custom Badge -->
                                <span :class="[
                                    'px-2 py-1 text-xs font-medium rounded-full flex-shrink-0',
                                    role.is_system 
                                        ? 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300'
                                        : 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-300'
                                ]">
                                    {{ role.is_system ? 'System' : 'Custom' }}
                                </span>
                            </div>
                            
                            <p v-if="role.description" 
                               class="text-sm text-gray-600 dark:text-gray-400 line-clamp-2 mb-2">
                                {{ role.description }}
                            </p>
                            
                            <!-- User Count -->
                            <div class="flex items-center space-x-4 text-sm text-gray-500 dark:text-gray-400">
                                <div class="flex items-center space-x-1">
                                    <i class="fas fa-users"></i>
                                    <span>{{ role.user_count }} user{{ role.user_count === 1 ? '' : 's' }}</span>
                                </div>
                                <div class="flex items-center space-x-1">
                                    <i class="fas fa-key"></i>
                                    <span>{{ role.permissions.length }} permission{{ role.permissions.length === 1 ? '' : 's' }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Permissions Preview -->
                    <div class="mb-6">
                        <div class="text-xs text-gray-500 uppercase tracking-wide mb-2">Key Permissions</div>
                        <div class="flex flex-wrap gap-1">
                            <template v-if="role.permissions.length > 0">
                                <span v-for="permission in role.permissions.slice(0, 3)" :key="permission"
                                      class="px-2 py-1 text-xs bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded">
                                    {{ available_permissions[permission] || permission.split('.').pop() }}
                                </span>
                                <span v-if="role.permissions.length > 3"
                                      class="px-2 py-1 text-xs bg-gray-100 dark:bg-gray-700 text-gray-500 rounded">
                                    +{{ role.permissions.length - 3 }} more
                                </span>
                            </template>
                            <span v-else class="text-xs text-gray-400 italic">No permissions assigned</span>
                        </div>
                    </div>

                    <!-- IMPROVED ACTION BUTTONS WITH TEXT LABELS -->
                    <div class="space-y-3">
                        <!-- View & Manage Buttons -->
                        <div class="flex space-x-2">
                            <!-- View Details Button -->
                            <button @click="viewDetails(role)"
                                    class="flex-1 flex items-center justify-center space-x-2 px-3 py-2 bg-blue-50 hover:bg-blue-100 dark:bg-blue-900/20 dark:hover:bg-blue-900/40 text-blue-700 dark:text-blue-300 rounded-lg border border-blue-200 dark:border-blue-800 transition-all duration-200 group">
                                <i class="fas fa-eye text-sm"></i>
                                <span class="text-sm font-medium">View Details</span>
                            </button>
                            
                            <!-- View Permissions Button -->
                            <button @click="viewPermissions(role)"
                                    class="flex-1 flex items-center justify-center space-x-2 px-3 py-2 bg-emerald-50 hover:bg-emerald-100 dark:bg-emerald-900/20 dark:hover:bg-emerald-900/40 text-emerald-700 dark:text-emerald-300 rounded-lg border border-emerald-200 dark:border-emerald-800 transition-all duration-200 group">
                                <i class="fas fa-key text-sm"></i>
                                <span class="text-sm font-medium">Permissions</span>
                            </button>
                        </div>
                        
                        <!-- Edit & Delete Buttons -->
                        <div class="flex space-x-2">
                            <!-- Edit Role Button -->
                            <button v-if="role.can_edit"
                                    @click="editRole(role)"
                                    class="flex-1 flex items-center justify-center space-x-2 px-3 py-2 bg-purple-50 hover:bg-purple-100 dark:bg-purple-900/20 dark:hover:bg-purple-900/40 text-purple-700 dark:text-purple-300 rounded-lg border border-purple-200 dark:border-purple-800 transition-all duration-200 group">
                                <i class="fas fa-edit text-sm"></i>
                                <span class="text-sm font-medium">Edit Role</span>
                            </button>
                            
                            <!-- Spacer if can't edit -->
                            <div v-else class="flex-1"></div>
                            
                            <!-- Delete Role Button -->
                            <button v-if="role.can_delete"
                                    @click="confirmDeleteRole(role)"
                                    class="flex-1 flex items-center justify-center space-x-2 px-3 py-2 bg-red-50 hover:bg-red-100 dark:bg-red-900/20 dark:hover:bg-red-900/40 text-red-700 dark:text-red-300 rounded-lg border border-red-200 dark:border-red-800 transition-all duration-200 group">
                                <i class="fas fa-trash text-sm"></i>
                                <span class="text-sm font-medium">Delete</span>
                            </button>
                            
                            <!-- Spacer if can't delete -->
                            <div v-else class="flex-1"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Empty State -->
            <div v-if="roles.data.length === 0" 
                 class="text-center py-12 bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700">
                <div class="max-w-sm mx-auto">
                    <i class="fas fa-user-tag text-gray-300 dark:text-gray-600 text-6xl mb-4"></i>
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-2">
                        No roles found
                    </h3>
                    <p class="text-gray-600 dark:text-gray-400 mb-6">
                        {{ (search || selectedScope) 
                            ? "Try adjusting your filters or search terms." 
                            : "Get started by creating your first custom role." }}
                    </p>
                    
                    <div class="flex justify-center space-x-3">
                        <button v-if="search || selectedScope"
                                @click="clearFilters"
                                class="px-4 py-2 text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200">
                            Clear Filters
                        </button>
                        
                        <button v-if="can_create_role"
                                @click="$inertia.visit(route('roles.create'))"
                                class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-medium transition-colors">
                            Create First Role
                        </button>
                    </div>
                </div>
            </div>

            <!-- Pagination -->
            <div v-if="roles.links && roles.data.length > 0">
                <PaginationLinks :links="roles.links" />
            </div>
        </div>

        <!-- Delete Role Confirmation Modal -->
        <Modal :show="showDeleteModal" @close="showDeleteModal = false">
            <div v-if="roleToDelete" class="p-6">
                <div class="flex items-center space-x-3 mb-4">
                    <div class="flex-shrink-0">
                        <i class="fas fa-exclamation-triangle text-red-500 text-2xl"></i>
                    </div>
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Delete Role</h2>
                        <p class="text-gray-600 dark:text-gray-400 mt-1">
                            Are you sure you want to delete the role "{{ roleToDelete.name }}"?
                        </p>
                    </div>
                </div>
                
                <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-4 mb-6">
                    <p class="text-red-800 dark:text-red-300 text-sm">
                        <strong>Warning:</strong> This action cannot be undone. The role will be permanently removed.
                    </p>
                    <p v-if="roleToDelete.user_count > 0" class="text-red-700 dark:text-red-400 text-sm mt-1">
                        This role is currently assigned to {{ roleToDelete.user_count }} user{{ roleToDelete.user_count === 1 ? '' : 's' }}.
                    </p>
                </div>
                
                <div class="flex justify-end space-x-3">
                    <SecondaryButton @click="showDeleteModal = false">Cancel</SecondaryButton>
                    <DangerButton @click="deleteRole">Delete Role</DangerButton>
                </div>
            </div>
        </Modal>

        <!-- Role Permissions Modal -->
        <Modal :show="showPermissionsModal" @close="showPermissionsModal = false" max-width="2xl">
            <div v-if="roleForPermissions" class="p-6">
                <div class="flex items-center space-x-3 mb-6">
                    <div :class="[
                        'w-10 h-10 rounded-lg flex items-center justify-center',
                        getRoleClasses(roleForPermissions.slug).bg
                    ]">
                        <i :class="[
                            getRoleIcon(roleForPermissions.slug),
                            getRoleClasses(roleForPermissions.slug).text
                        ]"></i>
                    </div>
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">{{ roleForPermissions.name }}</h2>
                        <p class="text-sm text-gray-600 dark:text-gray-400">{{ roleForPermissions.permissions.length }} permissions</p>
                    </div>
                </div>
                
                <div v-if="roleForPermissions.permissions.length > 0" class="space-y-4 max-h-96 overflow-y-auto">
                    <div v-for="(permissions, category) in categorizePermissions(roleForPermissions.permissions)" :key="category">
                        <h3 class="font-medium text-gray-900 dark:text-white mb-2">{{ category }}</h3>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-1 pl-4">
                            <div v-for="permission in permissions" :key="permission"
                                 class="flex items-center space-x-2">
                                <i class="fas fa-check text-emerald-500 text-xs"></i>
                                <span class="text-sm text-gray-700 dark:text-gray-300">
                                    {{ available_permissions[permission] || permission }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div v-else class="text-center py-8">
                    <i class="fas fa-key text-gray-300 dark:text-gray-600 text-3xl mb-2"></i>
                    <p class="text-gray-500 dark:text-gray-400">No permissions assigned</p>
                </div>
                
                <div class="flex justify-end space-x-3 mt-6">
                    <SecondaryButton @click="showPermissionsModal = false">Close</SecondaryButton>
                    <PrimaryButton v-if="roleForPermissions.can_edit && can_manage_permissions"
                                   @click="$inertia.visit(route('roles.permissions', roleForPermissions.id))">
                        <i class="fas fa-edit mr-2"></i>
                        Edit Permissions
                    </PrimaryButton>
                </div>
            </div>
        </Modal>
    </AppLayout>
</template>

<style scoped>
.line-clamp-2 {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

/* Fallback for browsers that don't support line-clamp */
@supports not (-webkit-line-clamp: 2) {
    .line-clamp-2 {
        display: block;
        max-height: 2.5rem;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
}

/* Ensure buttons are always visible and well-styled */
.group:hover i {
    transform: scale(1.1);
    transition: transform 0.2s ease;
}

/* Force button visibility */
button[class*="flex items-center justify-center"] {
    display: flex !important;
    opacity: 1 !important;
    visibility: visible !important;
}
</style>