<script setup>
import { ref, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import PrimaryButton from '@/Components/PrimaryButton.vue'
import SecondaryButton from '@/Components/SecondaryButton.vue'
import DangerButton from '@/Components/DangerButton.vue'
import Modal from '@/Components/Modal.vue'

const props = defineProps({
    role: Object,
    users: Array,
    permission_analysis: Object,
    available_permissions: Object,
    permission_categories: Object,
    can_edit: Boolean,
    can_delete: Boolean,
    can_assign_users: Boolean
})

const showDeleteModal = ref(false)

const getRoleIcon = (roleSlug) => {
    const icons = {
        organization_owner: 'fas fa-crown',
        admin: 'fas fa-user-cog',
        buyer: 'fas fa-shopping-cart',
        lister: 'fas fa-list-alt',
        fulfillment_manager: 'fas fa-truck',
        support_agent: 'fas fa-headset'
    }
    return icons[roleSlug] || 'fas fa-user-tag'
}

const getRoleColor = (roleSlug) => {
    const colors = {
        organization_owner: 'purple',
        admin: 'red', 
        buyer: 'blue',
        lister: 'emerald',
        fulfillment_manager: 'orange',
        support_agent: 'pink'
    }
    return colors[roleSlug] || 'gray'
}

const deleteRole = () => {
    router.delete(route('roles.destroy', props.role.id), {
        onSuccess: () => router.visit(route('roles.index'))
    })
}

// Get performance color for permission analysis
const getPerformanceColor = (percentage) => {
    if (percentage >= 80) return 'emerald'
    if (percentage >= 50) return 'yellow' 
    return 'red'
}

// Helper functions with safety checks
const safeRole = computed(() => props.role || {})
const safeUsers = computed(() => props.users || [])
const safePermissionAnalysis = computed(() => props.permission_analysis || {})
const safeAvailablePermissions = computed(() => props.available_permissions || {})
const safePermissionCategories = computed(() => props.permission_categories || {})
const safeRolePermissions = computed(() => safeRole.value.permissions || [])
</script>

<template>
    <AppLayout :title="`${safeRole.name || 'Role'} - Role Details`">
        <template #header>
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-4">
                    <button @click="$inertia.visit(route('roles.index'))"
                            class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                        <i class="fas fa-arrow-left text-lg"></i>
                    </button>
                    
                    <div class="flex items-center space-x-4">
                        <div :class="[
                            'w-12 h-12 rounded-xl flex items-center justify-center',
                            `bg-${getRoleColor(safeRole.slug)}-100 dark:bg-${getRoleColor(safeRole.slug)}-900/30`
                        ]">
                            <i :class="[
                                getRoleIcon(safeRole.slug),
                                'text-lg',
                                `text-${getRoleColor(safeRole.slug)}-600 dark:text-${getRoleColor(safeRole.slug)}-400`
                            ]"></i>
                        </div>
                        
                        <div>
                            <div class="flex items-center space-x-3">
                                <h2 class="text-3xl font-bold text-gray-900 dark:text-white">{{ safeRole.name || 'Unknown Role' }}</h2>
                                
                                <!-- Role Type Badge -->
                                <span :class="[
                                    'px-3 py-1 text-sm font-medium rounded-full',
                                    safeRole.is_system 
                                        ? 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300'
                                        : 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-300'
                                ]">
                                    {{ safeRole.is_system ? 'System Role' : 'Custom Role' }}
                                </span>
                                
                                <!-- Status Badge -->
                                <span :class="[
                                    'px-2 py-1 text-xs font-medium rounded-full',
                                    safeRole.is_active 
                                        ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300'
                                        : 'bg-gray-100 text-gray-700 dark:bg-gray-900/30 dark:text-gray-300'
                                ]">
                                    {{ safeRole.is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </div>
                            
                            <p v-if="safeRole.description" class="text-gray-600 dark:text-gray-400 mt-1">
                                {{ safeRole.description }}
                            </p>
                        </div>
                    </div>
                </div>
                
                <!-- Action Buttons -->
                <div class="flex space-x-3">
                    <button v-if="can_edit"
                            @click="$inertia.visit(route('roles.edit', safeRole.id))"
                            class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg font-medium transition-colors">
                        <i class="fas fa-edit mr-2"></i>
                        Edit Role
                    </button>
                    
                    <button v-if="can_edit"
                            @click="$inertia.visit(route('roles.permissions', safeRole.id))"
                            class="bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2 rounded-lg font-medium transition-colors">
                        <i class="fas fa-key mr-2"></i>
                        Manage Permissions
                    </button>
                </div>
            </div>
        </template>

        <div class="space-y-6">
            <!-- Role Overview Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <!-- Total Permissions -->
                <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-600 dark:text-gray-400">Total Permissions</p>
                            <p class="text-3xl font-bold text-gray-900 dark:text-white">{{ safeRolePermissions.length }}</p>
                        </div>
                        <div class="text-blue-500">
                            <i class="fas fa-key text-2xl"></i>
                        </div>
                    </div>
                </div>

                <!-- Users Assigned -->
                <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-600 dark:text-gray-400">Users Assigned</p>
                            <p class="text-3xl font-bold text-gray-900 dark:text-white">{{ safeUsers.length }}</p>
                        </div>
                        <div class="text-emerald-500">
                            <i class="fas fa-users text-2xl"></i>
                        </div>
                    </div>
                </div>

                <!-- Role Status -->
                <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-600 dark:text-gray-400">Status</p>
                            <p :class="[
                                'text-2xl font-bold',
                                safeRole.is_active ? 'text-emerald-600' : 'text-gray-400'
                            ]">
                                {{ safeRole.is_active ? 'Active' : 'Inactive' }}
                            </p>
                        </div>
                        <div :class="safeRole.is_active ? 'text-emerald-500' : 'text-gray-400'">
                            <i :class="[
                                'text-2xl',
                                safeRole.is_active ? 'fas fa-check-circle' : 'fas fa-pause-circle'
                            ]"></i>
                        </div>
                    </div>
                </div>

                <!-- Created Date -->
                <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-600 dark:text-gray-400">Created</p>
                            <p class="text-lg font-bold text-gray-900 dark:text-white">{{ safeRole.created_at || 'Unknown' }}</p>
                            <p class="text-xs text-gray-500">{{ safeRole.updated_at || 'Unknown' }}</p>
                        </div>
                        <div class="text-orange-500">
                            <i class="fas fa-calendar text-2xl"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Assigned Users -->
                <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                    <div class="flex items-center justify-between mb-6">
                        <h3 class="text-xl font-semibold text-gray-900 dark:text-white">Assigned Users</h3>
                        <span class="text-sm text-gray-500">{{ safeUsers.length }} user{{ safeUsers.length === 1 ? '' : 's' }}</span>
                    </div>

                    <div v-if="safeUsers.length > 0" class="space-y-4">
                        <div v-for="user in safeUsers" :key="user.id || 'unknown'"
                             class="flex items-center justify-between p-4 bg-gray-50 dark:bg-gray-700 rounded-xl">
                            <div class="flex items-center space-x-3">
                                <img :src="user.avatar || '/default-avatar.png'" 
                                     :alt="user.name || 'User'"
                                     class="w-10 h-10 rounded-full">
                                <div>
                                    <div class="font-medium text-gray-900 dark:text-white">{{ user.name || 'Unknown User' }}</div>
                                    <div class="text-sm text-gray-500 dark:text-gray-400">{{ user.email || 'No email' }}</div>
                                    <div v-if="user.teams && user.teams.length > 0" class="flex items-center space-x-1 mt-1">
                                        <i class="fas fa-users text-xs text-gray-400"></i>
                                        <span class="text-xs text-gray-500">
                                            {{ user.teams.map(t => t.name || 'Unknown Team').join(', ') }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="text-sm text-gray-500 dark:text-gray-400">
                                <div class="flex items-center space-x-2">
                                    <span :class="[
                                        'w-2 h-2 rounded-full',
                                        user.is_active ? 'bg-emerald-500' : 'bg-gray-400'
                                    ]"></span>
                                    <span>{{ user.last_login || 'Never' }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div v-else class="text-center py-8 border-2 border-dashed border-gray-200 dark:border-gray-600 rounded-lg">
                        <i class="fas fa-users text-gray-300 dark:text-gray-600 text-3xl mb-2"></i>
                        <p class="text-gray-500 dark:text-gray-400">No users assigned to this role</p>
                    </div>
                </div>

                <!-- Permission Analysis -->
                <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                    <div class="flex items-center justify-between mb-6">
                        <h3 class="text-xl font-semibold text-gray-900 dark:text-white">Permission Analysis</h3>
                        <span class="text-sm text-gray-500">By category</span>
                    </div>

                    <div v-if="Object.keys(safePermissionAnalysis).length > 0" class="space-y-4">
                        <div v-for="(analysis, category) in safePermissionAnalysis" :key="category">
                            <div class="flex justify-between items-center mb-2">
                                <span class="text-sm font-medium text-gray-900 dark:text-white">{{ category }}</span>
                                <span class="text-sm text-gray-500">
                                    {{ analysis.granted || 0 }}/{{ analysis.total || 0 }}
                                </span>
                            </div>
                            
                            <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                                <div :class="[
                                    'h-2 rounded-full transition-all duration-300',
                                    `bg-${getPerformanceColor(analysis.percentage || 0)}-500`
                                ]" :style="`width: ${analysis.percentage || 0}%`"></div>
                            </div>
                            
                            <div class="flex justify-between items-center mt-1">
                                <span class="text-xs text-gray-500">{{ analysis.percentage || 0 }}% coverage</span>
                                <span :class="[
                                    'text-xs font-medium',
                                    `text-${getPerformanceColor(analysis.percentage || 0)}-600`
                                ]">
                                    {{ (analysis.percentage || 0) >= 80 ? 'Full' : (analysis.percentage || 0) >= 50 ? 'Partial' : 'Limited' }}
                                </span>
                            </div>
                        </div>
                    </div>
                    
                    <div v-else class="text-center py-8">
                        <i class="fas fa-chart-bar text-gray-300 dark:text-gray-600 text-3xl mb-2"></i>
                        <p class="text-gray-500 dark:text-gray-400">No permission analysis available</p>
                    </div>
                </div>
            </div>

            <!-- Full Permissions List -->
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                <div class="flex items-center justify-between mb-6">
                    <h3 class="text-xl font-semibold text-gray-900 dark:text-white">All Permissions</h3>
                    <button v-if="can_edit"
                            @click="$inertia.visit(route('roles.permissions', safeRole.id))"
                            class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                        <i class="fas fa-edit mr-1"></i>
                        Modify Permissions
                    </button>
                </div>

                <div v-if="safeRolePermissions.length > 0" class="space-y-4">
                    <div v-for="(categoryPermissions, category) in safePermissionCategories" :key="category"
                         v-if="Array.isArray(categoryPermissions) && categoryPermissions.some(p => safeRolePermissions.includes(p))"
                         class="border border-gray-200 dark:border-gray-600 rounded-xl p-4">
                        <h4 class="font-medium text-gray-900 dark:text-white mb-3">{{ category }}</h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                            <div v-for="permission in (categoryPermissions || [])" :key="permission"
                                 v-if="safeRolePermissions.includes(permission)"
                                 class="flex items-center space-x-2">
                                <i class="fas fa-check text-emerald-500 text-sm"></i>
                                <span class="text-sm text-gray-700 dark:text-gray-300">
                                    {{ safeAvailablePermissions[permission] || permission || 'Unknown Permission' }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-else class="text-center py-8">
                    <i class="fas fa-key text-gray-300 dark:text-gray-600 text-3xl mb-2"></i>
                    <p class="text-gray-500 dark:text-gray-400">No permissions assigned to this role</p>
                </div>
            </div>

            <!-- Danger Zone -->
            <div v-if="can_delete" 
                 class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-2xl p-6">
                <div class="flex items-start justify-between">
                    <div>
                        <h3 class="text-lg font-semibold text-red-800 dark:text-red-300 mb-1">Delete Role</h3>
                        <p class="text-red-700 dark:text-red-400 text-sm">
                            Permanently delete this role. This action cannot be undone.
                        </p>
                        <p v-if="safeUsers.length > 0" class="text-red-600 dark:text-red-400 text-sm mt-1">
                            <i class="fas fa-exclamation-triangle mr-1"></i>
                            This role is currently assigned to {{ safeUsers.length }} user{{ safeUsers.length === 1 ? '' : 's' }} and cannot be deleted.
                        </p>
                    </div>
                    
                    <DangerButton @click="showDeleteModal = true" :disabled="safeUsers.length > 0">
                        Delete Role
                    </DangerButton>
                </div>
            </div>
        </div>

        <!-- Delete Confirmation Modal -->
        <Modal :show="showDeleteModal" @close="showDeleteModal = false">
            <div class="p-6">
                <div class="flex items-center space-x-3 mb-4">
                    <div class="flex-shrink-0">
                        <i class="fas fa-exclamation-triangle text-red-500 text-2xl"></i>
                    </div>
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Delete Role</h2>
                        <p class="text-gray-600 dark:text-gray-400 mt-1">
                            Are you sure you want to delete the role "{{ safeRole.name || 'Unknown Role' }}"?
                        </p>
                    </div>
                </div>
                
                <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-4 mb-6">
                    <p class="text-red-800 dark:text-red-300 text-sm">
                        <strong>This will:</strong>
                    </p>
                    <ul class="text-red-700 dark:text-red-400 text-sm mt-1 ml-4 list-disc">
                        <li>Permanently delete the role</li>
                        <li>Remove all associated permissions</li>
                        <li>This action cannot be undone</li>
                    </ul>
                </div>
                
                <div class="flex justify-end space-x-3">
                    <SecondaryButton @click="showDeleteModal = false">Cancel</SecondaryButton>
                    <DangerButton @click="deleteRole">Delete Role</DangerButton>
                </div>
            </div>
        </Modal>
    </AppLayout>
</template>