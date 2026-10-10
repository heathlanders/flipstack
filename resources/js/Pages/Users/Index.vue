<script setup>
import { ref, computed, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import DashboardCard from '@/Components/Cards/DashboardCard.vue'
import SearchInput from '@/Components/Forms/SearchInput.vue'
import PaginationLinks from '@/Components/Pagination/PaginationLinks.vue'
import Checkbox from '@/Components/Checkbox.vue'
import Modal from '@/Components/Modal.vue'
import DangerButton from '@/Components/DangerButton.vue'
import SecondaryButton from '@/Components/SecondaryButton.vue'
import PrimaryButton from '@/Components/PrimaryButton.vue'
import ActionToolbar from '@/Components/ActionToolbar.vue'

const props = defineProps({
    users: Object,
    stats: Object,
    filters: Object,
    available_roles: Array,
    available_teams: Array,
    can_create_user: Boolean,
    can_bulk_actions: Boolean
})

// Reactive filters
const search = ref(props.filters.search || '')
const selectedRole = ref(props.filters.role || '')
const selectedTeam = ref(props.filters.team || '')
const selectedStatus = ref(props.filters.status || '')

// Bulk actions
const selectedUsers = ref([])
const showBulkActionsModal = ref(false)
const bulkAction = ref('')

// Update URL when filters change
watch([search, selectedRole, selectedTeam, selectedStatus], () => {
    const params = {}
    if (search.value) params.search = search.value
    if (selectedRole.value) params.role = selectedRole.value
    if (selectedTeam.value) params.team = selectedTeam.value
    if (selectedStatus.value) params.status = selectedStatus.value
    
    router.get(route('users.index'), params, {
        preserveState: true,
        preserveScroll: true,
        replace: true
    })
}, { debounce: 300 })

// Clear all filters
const clearFilters = () => {
    search.value = ''
    selectedRole.value = ''
    selectedTeam.value = ''
    selectedStatus.value = ''
}

// Bulk selection
const selectAllUsers = computed({
    get() {
        return props.users.data.length > 0 && selectedUsers.value.length === props.users.data.length
    },
    set(value) {
        selectedUsers.value = value 
            ? props.users.data.map(user => user.id).filter(id => !props.users.data.find(u => u.id === id && u.is_current_user))
            : []
    }
})

const isUserSelected = (userId) => {
    return selectedUsers.value.includes(userId)
}

const toggleUserSelection = (userId, isCurrentUser) => {
    if (isCurrentUser) return // Can't select current user
    
    const index = selectedUsers.value.indexOf(userId)
    if (index > -1) {
        selectedUsers.value.splice(index, 1)
    } else {
        selectedUsers.value.push(userId)
    }
}

// Bulk actions
const performBulkAction = () => {
    if (selectedUsers.value.length === 0) return

    router.post(route('users.bulk-action'), {
        action: bulkAction.value,
        user_ids: selectedUsers.value
    }, {
        preserveScroll: true,
        onSuccess: () => {
            selectedUsers.value = []
            showBulkActionsModal.value = false
            bulkAction.value = ''
        }
    })
}

const confirmBulkAction = (action) => {
    if (selectedUsers.value.length === 0) return
    
    bulkAction.value = action
    showBulkActionsModal.value = true
}

// Role color mapping
const getRoleClasses = (roleSlug) => {
    const colorMap = {
        organization_owner: {
            bg: 'bg-purple-100 dark:bg-purple-900/30',
            text: 'text-purple-800 dark:text-purple-300'
        },
        admin: {
            bg: 'bg-red-100 dark:bg-red-900/30',
            text: 'text-red-800 dark:text-red-300'
        },
        buyer: {
            bg: 'bg-blue-100 dark:bg-blue-900/30',
            text: 'text-blue-800 dark:text-blue-300'
        },
        lister: {
            bg: 'bg-emerald-100 dark:bg-emerald-900/30',
            text: 'text-emerald-800 dark:text-emerald-300'
        },
        fulfillment_manager: {
            bg: 'bg-orange-100 dark:bg-orange-900/30',
            text: 'text-orange-800 dark:text-orange-300'
        },
        support_agent: {
            bg: 'bg-pink-100 dark:bg-pink-900/30',
            text: 'text-pink-800 dark:text-pink-300'
        }
    }
    return colorMap[roleSlug] || {
        bg: 'bg-gray-100 dark:bg-gray-900/30',
        text: 'text-gray-800 dark:text-gray-300'
    }
}

const getTeamClasses = (departmentColor) => {
    const colorMap = {
        buying: {
            bg: 'bg-emerald-100 dark:bg-emerald-900/30',
            text: 'text-emerald-700 dark:text-emerald-300'
        },
        listing: {
            bg: 'bg-blue-100 dark:bg-blue-900/30',
            text: 'text-blue-700 dark:text-blue-300'
        },
        fulfillment: {
            bg: 'bg-orange-100 dark:bg-orange-900/30',
            text: 'text-orange-700 dark:text-orange-300'
        },
        support: {
            bg: 'bg-red-100 dark:bg-red-900/30',
            text: 'text-red-700 dark:text-red-300'
        },
        admin: {
            bg: 'bg-purple-100 dark:bg-purple-900/30',
            text: 'text-purple-700 dark:text-purple-300'
        }
    }
    return colorMap[departmentColor] || {
        bg: 'bg-gray-100 dark:bg-gray-900/30',
        text: 'text-gray-700 dark:text-gray-300'
    }
}

// Page actions for the toolbar
const pageActions = computed(() => {
    const actions = []

    // Bulk actions when users selected
    if (props.can_bulk_actions && selectedUsers.value.length > 0) {
        actions.push(
            {
                label: `Activate (${selectedUsers.value.length})`,
                icon: 'fas fa-check',
                onClick: () => confirmBulkAction('activate'),
                variant: 'success'
            },
            {
                label: `Deactivate (${selectedUsers.value.length})`,
                icon: 'fas fa-pause',
                onClick: () => confirmBulkAction('deactivate'),
                variant: 'warning'
            },
            {
                label: `Delete (${selectedUsers.value.length})`,
                icon: 'fas fa-trash',
                onClick: () => confirmBulkAction('delete'),
                variant: 'danger'
            }
        )
    }

    // Primary action - Invite User (via organization invitation flow)
    if (props.can_create_user) {
        actions.push({
            label: 'Invite User',
            icon: 'fas fa-envelope',
            href: route('organization.invitations.index'),
            variant: 'primary'
        })
    }

    return actions
})
</script>

<template>
    <AppLayout
        title="User Management"
        page-title="User Management"
        page-description="Manage users, roles, and permissions across your organization"
        :page-actions="pageActions"
    >

        <div class="space-y-6">
            <!-- User Statistics Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <DashboardCard 
                    title="Total Users" 
                    :value="stats.total_users"
                    icon="fas fa-users"
                    color="blue"
                    subtitle="All organization users"
                />
                
                <DashboardCard 
                    title="Active Users" 
                    :value="stats.active_users"
                    icon="fas fa-user-check"
                    color="emerald"
                    subtitle="Currently active"
                />
                
                <DashboardCard 
                    title="Online Now" 
                    :value="stats.users_online"
                    icon="fas fa-circle"
                    color="green"
                    subtitle="Last 15 minutes"
                />
                
                <DashboardCard 
                    title="New This Month" 
                    :value="stats.new_this_month"
                    icon="fas fa-user-plus"
                    color="purple"
                    subtitle="Recent additions"
                />
            </div>

            <!-- Filters & Search -->
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between space-y-4 lg:space-y-0">
                    <!-- Search -->
                    <div class="flex-1 max-w-md">
                        <SearchInput 
                            v-model="search"
                            placeholder="Search by name, email, or employee ID..."
                            class="w-full"
                        />
                    </div>
                    
                    <!-- Filters -->
                    <div class="flex flex-col sm:flex-row space-y-2 sm:space-y-0 sm:space-x-4">
                        <!-- Role Filter -->
                        <select v-model="selectedRole"
                                class="rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm">
                            <option value="">All Roles</option>
                            <option v-for="role in available_roles" :key="role.slug" :value="role.slug">
                                {{ role.name }}
                            </option>
                        </select>
                        
                        <!-- Team Filter -->
                        <select v-model="selectedTeam"
                                class="rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm">
                            <option value="">All Teams</option>
                            <option v-for="team in available_teams" :key="team.id" :value="team.id">
                                {{ team.name }} ({{ team.department }})
                            </option>
                        </select>
                        
                        <!-- Status Filter -->
                        <select v-model="selectedStatus"
                                class="rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm">
                            <option value="">All Status</option>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                        
                        <!-- Clear Filters -->
                        <button @click="clearFilters"
                                v-if="search || selectedRole || selectedTeam || selectedStatus"
                                class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 text-sm px-3 py-2">
                            <i class="fas fa-times mr-1"></i>
                            Clear
                        </button>
                    </div>
                </div>

                <!-- Bulk Selection Header -->
                <div v-if="can_bulk_actions" class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-600">
                    <div class="flex items-center justify-between">
                        <label class="flex items-center space-x-2">
                            <Checkbox v-model:checked="selectAllUsers" />
                            <span class="text-sm text-gray-600 dark:text-gray-400">
                                Select All ({{ selectedUsers.length }} selected)
                            </span>
                        </label>
                        
                        <div v-if="selectedUsers.length > 0" class="text-sm text-blue-600 dark:text-blue-400">
                            {{ selectedUsers.length }} user{{ selectedUsers.length === 1 ? '' : 's' }} selected
                        </div>
                    </div>
                </div>
            </div>

            <!-- Users Grid/List View -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <div v-for="user in users.data" :key="user.id"
                     class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 p-6 hover:shadow-md transition-shadow">
                    
                    <!-- User Header -->
                    <div class="flex items-start justify-between mb-4">
                        <div class="flex items-center space-x-3">
                            <!-- Selection Checkbox -->
                            <Checkbox v-if="can_bulk_actions && !user.is_current_user"
                                      :checked="isUserSelected(user.id)"
                                      @change="toggleUserSelection(user.id, user.is_current_user)" />
                            
                            <!-- Avatar -->
                            <img :src="user.avatar" 
                                 :alt="user.name"
                                 class="h-12 w-12 rounded-full">
                            
                            <div class="flex-1">
                                <div class="flex items-center space-x-2">
                                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">{{ user.name }}</h3>
                                    <span v-if="user.is_current_user"
                                          class="px-2 py-1 text-xs font-medium bg-blue-100 text-blue-800 rounded-full">
                                        You
                                    </span>
                                </div>
                                <p class="text-sm text-gray-600 dark:text-gray-400">{{ user.email }}</p>
                                <p v-if="user.employee_id" class="text-xs text-gray-400">ID: {{ user.employee_id }}</p>
                            </div>
                        </div>
                        
                        <!-- Status Indicator -->
                        <div class="flex-shrink-0">
                            <span :class="[
                                'w-3 h-3 rounded-full',
                                user.is_active ? 'bg-emerald-500' : 'bg-red-500'
                            ]"></span>
                        </div>
                    </div>

                    <!-- Role & Teams -->
                    <div class="space-y-3 mb-4">
                        <!-- Role -->
                        <div>
                            <span class="text-xs text-gray-500 uppercase tracking-wide">Role</span>
                            <div class="mt-1">
                                <span :class="[
                                    'px-2 py-1 text-xs font-medium rounded-full',
                                    getRoleClasses(user.primary_role.slug).bg,
                                    getRoleClasses(user.primary_role.slug).text
                                ]">
                                    {{ user.primary_role.display_name }}
                                </span>
                            </div>
                        </div>
                        
                        <!-- Teams -->
                        <div>
                            <span class="text-xs text-gray-500 uppercase tracking-wide">Teams ({{ user.teams_count }})</span>
                            <div class="mt-1 flex flex-wrap gap-1">
                                <template v-if="user.teams.length > 0">
                                    <span v-for="team in user.teams.slice(0, 3)" :key="team.id"
                                          :class="[
                                              'px-2 py-1 text-xs rounded-md',
                                              getTeamClasses(team.department_color).bg,
                                              getTeamClasses(team.department_color).text
                                          ]">
                                        {{ team.name }}
                                    </span>
                                    <span v-if="user.teams.length > 3"
                                          class="px-2 py-1 text-xs bg-gray-100 text-gray-600 rounded-md">
                                        +{{ user.teams.length - 3 }} more
                                    </span>
                                </template>
                                <span v-else class="text-xs text-gray-400">No teams assigned</span>
                            </div>
                        </div>
                    </div>

                    <!-- Stats -->
                    <div class="grid grid-cols-3 gap-4 mb-4 p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
                        <div class="text-center">
                            <div class="text-sm font-semibold text-gray-900 dark:text-white">{{ user.stats.products_created }}</div>
                            <div class="text-xs text-gray-500">Products</div>
                        </div>
                        <div class="text-center">
                            <div class="text-sm font-semibold text-gray-900 dark:text-white">{{ user.stats.items_processed }}</div>
                            <div class="text-xs text-gray-500">Processed</div>
                        </div>
                        <div class="text-center">
                            <div class="text-sm font-semibold text-gray-900 dark:text-white">{{ user.stats.teams_led }}</div>
                            <div class="text-xs text-gray-500">Teams Led</div>
                        </div>
                    </div>

                    <!-- Last Activity -->
                    <div class="text-xs text-gray-500 mb-4">
                        <i class="fas fa-clock mr-1"></i>
                        Last active: {{ user.last_login }}
                    </div>

                    <!-- Actions -->
                    <div class="flex items-center justify-between pt-4 border-t border-gray-200 dark:border-gray-600">
                        <!-- Status -->
                        <div class="flex items-center space-x-2">
                            <span :class="[
                                'px-2 py-1 text-xs font-medium rounded-full',
                                user.is_active 
                                    ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300'
                                    : 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300'
                            ]">
                                {{ user.is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </div>
                        
                        <!-- Action Buttons -->
                        <div class="flex space-x-2">
                            <!-- View User -->
                            <button @click="$inertia.visit(route('users.show', user.id))"
                                    class="p-2 text-blue-600 hover:bg-blue-50 rounded-lg transition-colors">
                                <i class="fas fa-eye"></i>
                            </button>
                            
                            <!-- Edit User -->
                            <button v-if="user.can_manage"
                                    @click="$inertia.visit(route('users.edit', user.id))"
                                    class="p-2 text-emerald-600 hover:bg-emerald-50 rounded-lg transition-colors">
                                <i class="fas fa-edit"></i>
                            </button>
                            
                            <!-- Quick Status Toggle -->
                            <button v-if="user.can_manage && !user.is_current_user"
                                    @click="$inertia.patch(route(user.is_active ? 'users.deactivate' : 'users.reactivate', user.id))"
                                    :class="[
                                        'p-2 rounded-lg transition-colors',
                                        user.is_active 
                                            ? 'text-yellow-600 hover:bg-yellow-50'
                                            : 'text-emerald-600 hover:bg-emerald-50'
                                    ]">
                                <i :class="user.is_active ? 'fas fa-pause' : 'fas fa-play'"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Empty State -->
            <div v-if="users.data.length === 0" 
                 class="text-center py-12 bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700">
                <div class="max-w-sm mx-auto">
                    <i class="fas fa-users text-gray-300 dark:text-gray-600 text-6xl mb-4"></i>
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-2">
                        No users found
                    </h3>
                    <p class="text-gray-600 dark:text-gray-400 mb-6">
                        {{ (search || selectedRole || selectedTeam || selectedStatus) 
                            ? "Try adjusting your filters or search terms." 
                            : "Get started by adding your first user." }}
                    </p>
                    
                    <div class="flex justify-center space-x-3">
                        <button v-if="search || selectedRole || selectedTeam || selectedStatus"
                                @click="clearFilters"
                                class="px-4 py-2 text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200">
                            Clear Filters
                        </button>
                        
                        <button v-if="can_create_user"
                                @click="$inertia.visit(route('organization.invitations.index'))"
                                class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-medium transition-colors">
                            Invite User
                        </button>
                    </div>
                </div>
            </div>

            <!-- Pagination -->
            <div v-if="users.links && users.data.length > 0">
                <PaginationLinks :links="users.links" />
            </div>
        </div>

        <!-- Bulk Actions Confirmation Modal -->
        <Modal :show="showBulkActionsModal" @close="showBulkActionsModal = false">
            <div class="p-6">
                <div class="flex items-center space-x-3 mb-4">
                    <div class="flex-shrink-0">
                        <i :class="[
                            'text-2xl',
                            bulkAction === 'delete' ? 'fas fa-exclamation-triangle text-red-500' : 'fas fa-question-circle text-yellow-500'
                        ]"></i>
                    </div>
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                            Confirm Bulk Action
                        </h2>
                        <p class="text-gray-600 dark:text-gray-400 mt-1">
                            Are you sure you want to {{ bulkAction }} {{ selectedUsers.length }} user{{ selectedUsers.length === 1 ? '' : 's' }}?
                        </p>
                    </div>
                </div>
                
                <div v-if="bulkAction === 'delete'" 
                     class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-4 mb-6">
                    <p class="text-red-800 dark:text-red-300 text-sm">
                        <strong>Warning:</strong> This action cannot be undone. Deleted users will be permanently removed from all teams and lose access to the system.
                    </p>
                </div>
                
                <div class="flex justify-end space-x-3">
                    <SecondaryButton @click="showBulkActionsModal = false">Cancel</SecondaryButton>
                    <DangerButton v-if="bulkAction === 'delete'" @click="performBulkAction">
                        Delete Users
                    </DangerButton>
                    <PrimaryButton v-else @click="performBulkAction">
                        {{ bulkAction.charAt(0).toUpperCase() + bulkAction.slice(1) }} Users
                    </PrimaryButton>
                </div>
            </div>
        </Modal>
    </AppLayout>
</template>