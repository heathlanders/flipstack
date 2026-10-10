<script setup>
import { ref, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import Modal from '@/Components/Modal.vue'
import DangerButton from '@/Components/DangerButton.vue'
import SecondaryButton from '@/Components/SecondaryButton.vue'
import PrimaryButton from '@/Components/PrimaryButton.vue'
import TextInput from '@/Components/TextInput.vue'
import SelectInput from '@/Components/SelectInput.vue'

const props = defineProps({
    user: Object,
    teams: Array,
    recent_activity: Array,
    metrics: Object,
    permissions: Object,
    available_teams: Array,
    available_roles: Array
})

// Modal states
const showPasswordResetModal = ref(false)
const showAddToTeamModal = ref(false)
const showRemoveFromTeamModal = ref(false)
const showDeactivateModal = ref(false)

// Form data
const passwordResetForm = ref({
    password: '',
    password_confirmation: ''
})

const addToTeamForm = ref({
    team_id: '',
    role_id: ''
})

const teamToRemove = ref(null)

// Reset forms
const resetPasswordForm = () => {
    passwordResetForm.value = {
        password: '',
        password_confirmation: ''
    }
}

const resetAddToTeamForm = () => {
    addToTeamForm.value = {
        team_id: '',
        role_id: ''
    }
}

// Reset password
const resetPassword = () => {
    router.patch(route('users.reset-password', props.user.id), passwordResetForm.value, {
        preserveScroll: true,
        onSuccess: () => {
            showPasswordResetModal.value = false
            resetPasswordForm()
        }
    })
}

// Add to team
const addToTeam = () => {
    router.post(route('users.add-to-team', props.user.id), addToTeamForm.value, {
        preserveScroll: true,
        onSuccess: () => {
            showAddToTeamModal.value = false
            resetAddToTeamForm()
        }
    })
}

// Remove from team
const confirmRemoveFromTeam = (team) => {
    teamToRemove.value = team
    showRemoveFromTeamModal.value = true
}

const removeFromTeam = () => {
    router.delete(route('users.remove-from-team', [props.user.id, teamToRemove.value.id]), {
        preserveScroll: true,
        onSuccess: () => {
            showRemoveFromTeamModal.value = false
            teamToRemove.value = null
        }
    })
}

// User status actions
const toggleUserStatus = () => {
    const action = props.user.is_active ? 'deactivate' : 'reactivate'
    router.patch(route(`users.${action}`, props.user.id), {}, {
        preserveScroll: true
    })
}

const confirmDeactivate = () => {
    showDeactivateModal.value = true
}

const deactivateUser = () => {
    router.patch(route('users.deactivate', props.user.id), {}, {
        preserveScroll: true,
        onSuccess: () => {
            showDeactivateModal.value = false
        }
    })
}

// Computed values
const statusColor = computed(() => {
    return props.user.is_active ? 'emerald' : 'red'
})

const roleColor = computed(() => {
    const colors = {
        organization_owner: 'purple',
        admin: 'red',
        buyer: 'blue',
        lister: 'emerald',
        fulfillment_manager: 'orange',
        support_agent: 'pink'
    }
    return colors[props.user.primary_role?.slug] || 'gray'
})

// Activity type icons and colors
const getActivityTypeStyle = (action) => {
    const styles = {
        login: { icon: 'fas fa-sign-in-alt', color: 'green' },
        logout: { icon: 'fas fa-sign-out-alt', color: 'gray' },
        team_joined: { icon: 'fas fa-users', color: 'blue' },
        team_left: { icon: 'fas fa-user-minus', color: 'yellow' },
        role_changed: { icon: 'fas fa-user-cog', color: 'purple' },
        user_created: { icon: 'fas fa-user-plus', color: 'green' },
        user_updated: { icon: 'fas fa-edit', color: 'blue' },
        password_reset: { icon: 'fas fa-key', color: 'orange' },
        default: { icon: 'fas fa-circle', color: 'gray' }
    }
    
    return styles[action] || styles.default
}
</script>

<template>
    <AppLayout :title="`${user.name} - User Profile`">
        <template #header>
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-4">
                    <button @click="$inertia.visit(route('users.index'))"
                            class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                        <i class="fas fa-arrow-left text-lg"></i>
                    </button>
                    
                    <div class="flex items-center space-x-4">
                        <img :src="user.avatar" 
                             :alt="user.name"
                             class="h-16 w-16 rounded-full border-4 border-white shadow-lg">
                        
                        <div>
                            <div class="flex items-center space-x-3">
                                <h2 class="text-3xl font-bold text-gray-900 dark:text-white">{{ user.name }}</h2>
                                
                                <!-- Status Badge -->
                                <span :class="[
                                    'px-3 py-1 text-sm font-medium rounded-full',
                                    user.is_active 
                                        ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300'
                                        : 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300'
                                ]">
                                    {{ user.is_active ? 'Active' : 'Inactive' }}
                                </span>
                                
                                <!-- Role Badge -->
                                <span :class="[
                                    'px-3 py-1 text-sm font-medium rounded-full',
                                    `bg-${roleColor}-100 text-${roleColor}-700`,
                                    `dark:bg-${roleColor}-900/30 dark:text-${roleColor}-300`
                                ]">
                                    {{ user.primary_role?.display_name || 'No Role' }}
                                </span>
                            </div>
                            
                            <p class="text-gray-600 dark:text-gray-400 mt-1">{{ user.email }}</p>
                            <p v-if="user.employee_id" class="text-sm text-gray-500">Employee ID: {{ user.employee_id }}</p>
                        </div>
                    </div>
                </div>
                
                <!-- Action Buttons -->
                <div class="flex space-x-3">
                    <button v-if="permissions.can_edit"
                            @click="$inertia.visit(route('users.edit', user.id))"
                            class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg font-medium transition-colors">
                        <i class="fas fa-edit mr-2"></i>
                        Edit User
                    </button>
                    
                    <button v-if="permissions.can_edit && available_teams.length > 0"
                            @click="showAddToTeamModal = true"
                            class="bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2 rounded-lg font-medium transition-colors">
                        <i class="fas fa-user-plus mr-2"></i>
                        Add to Team
                    </button>
                    
                    <!-- Status Toggle -->
                    <button v-if="permissions.can_edit"
                            @click="user.is_active ? confirmDeactivate() : toggleUserStatus()"
                            :class="[
                                'px-4 py-2 rounded-lg font-medium transition-colors',
                                user.is_active 
                                    ? 'bg-yellow-600 hover:bg-yellow-700 text-white'
                                    : 'bg-emerald-600 hover:bg-emerald-700 text-white'
                            ]">
                        <i :class="[user.is_active ? 'fas fa-pause' : 'fas fa-play', 'mr-2']"></i>
                        {{ user.is_active ? 'Deactivate' : 'Activate' }}
                    </button>
                </div>
            </div>
        </template>

        <div class="space-y-6">
            <!-- User Overview Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <!-- Products Created -->
                <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-600 dark:text-gray-400">Products Created</p>
                            <p class="text-3xl font-bold text-gray-900 dark:text-white mt-1">{{ metrics.products_created }}</p>
                        </div>
                        <div class="text-blue-500">
                            <i class="fas fa-cube text-2xl"></i>
                        </div>
                    </div>
                </div>

                <!-- Items Processed -->
                <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-600 dark:text-gray-400">Items Processed</p>
                            <p class="text-3xl font-bold text-gray-900 dark:text-white mt-1">{{ metrics.items_processed }}</p>
                        </div>
                        <div class="text-emerald-500">
                            <i class="fas fa-tasks text-2xl"></i>
                        </div>
                    </div>
                </div>

                <!-- Teams Count -->
                <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-600 dark:text-gray-400">Active Teams</p>
                            <p class="text-3xl font-bold text-gray-900 dark:text-white mt-1">{{ metrics.teams_count }}</p>
                        </div>
                        <div class="text-purple-500">
                            <i class="fas fa-users text-2xl"></i>
                        </div>
                    </div>
                </div>

                <!-- Teams Led -->
                <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-600 dark:text-gray-400">Teams Led</p>
                            <p class="text-3xl font-bold text-gray-900 dark:text-white mt-1">{{ metrics.teams_led }}</p>
                        </div>
                        <div class="text-orange-500">
                            <i class="fas fa-crown text-2xl"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- User Details -->
                <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                    <h3 class="text-xl font-semibold text-gray-900 dark:text-white mb-6">User Information</h3>
                    
                    <div class="space-y-4">
                        <!-- Basic Info -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Full Name</label>
                                <p class="mt-1 text-gray-900 dark:text-white">{{ user.name }}</p>
                            </div>
                            
                            <div>
                                <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Email Address</label>
                                <p class="mt-1 text-gray-900 dark:text-white">{{ user.email }}</p>
                            </div>
                            
                            <div v-if="user.employee_id">
                                <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Employee ID</label>
                                <p class="mt-1 text-gray-900 dark:text-white">{{ user.employee_id }}</p>
                            </div>
                            
                            <div v-if="user.phone">
                                <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Phone</label>
                                <p class="mt-1 text-gray-900 dark:text-white">{{ user.phone }}</p>
                            </div>
                            
                            <div v-if="user.department">
                                <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Department</label>
                                <p class="mt-1 text-gray-900 dark:text-white capitalize">{{ user.department }}</p>
                            </div>
                            
                            <div v-if="user.hire_date">
                                <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Hire Date</label>
                                <p class="mt-1 text-gray-900 dark:text-white">{{ user.hire_date }}</p>
                            </div>
                            
                            <div v-if="user.timezone">
                                <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Timezone</label>
                                <p class="mt-1 text-gray-900 dark:text-white">{{ user.timezone }}</p>
                            </div>
                            
                            <div>
                                <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Last Login</label>
                                <p class="mt-1 text-gray-900 dark:text-white">{{ user.last_login_at || 'Never' }}</p>
                            </div>
                        </div>
                        
                        <!-- Bio -->
                        <div v-if="user.bio">
                            <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Bio</label>
                            <p class="mt-1 text-gray-900 dark:text-white">{{ user.bio }}</p>
                        </div>
                        
                        <!-- Account Details -->
                        <div class="pt-4 border-t border-gray-200 dark:border-gray-700">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Account Created</label>
                                    <p class="mt-1 text-gray-900 dark:text-white">{{ user.created_at }}</p>
                                </div>
                                
                                <div>
                                    <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Last Updated</label>
                                    <p class="mt-1 text-gray-900 dark:text-white">{{ user.updated_at }}</p>
                                </div>
                            </div>
                        </div>

                        <!-- Quick Actions -->
                        <div v-if="permissions.can_edit" class="pt-4 border-t border-gray-200 dark:border-gray-700">
                            <label class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3 block">Quick Actions</label>
                            <div class="flex flex-wrap gap-2">
                                <button @click="showPasswordResetModal = true"
                                        class="px-3 py-2 bg-orange-50 hover:bg-orange-100 text-orange-700 rounded-lg text-sm transition-colors">
                                    <i class="fas fa-key mr-1"></i>
                                    Reset Password
                                </button>
                                
                                <button v-if="permissions.can_view_activity"
                                        @click="$inertia.visit(route('users.activity', user.id))"
                                        class="px-3 py-2 bg-purple-50 hover:bg-purple-100 text-purple-700 rounded-lg text-sm transition-colors">
                                    <i class="fas fa-history mr-1"></i>
                                    View Activity
                                </button>
                                
                                <button v-if="permissions.can_impersonate"
                                        @click="$inertia.post(route('users.impersonate', user.id))"
                                        class="px-3 py-2 bg-gray-50 hover:bg-gray-100 text-gray-700 rounded-lg text-sm transition-colors">
                                    <i class="fas fa-user-secret mr-1"></i>
                                    Impersonate
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Teams & Recent Activity -->
                <div class="space-y-6">
                    <!-- User Teams -->
                    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                        <div class="flex items-center justify-between mb-6">
                            <h3 class="text-xl font-semibold text-gray-900 dark:text-white">Team Memberships</h3>
                            <span class="text-sm text-gray-500">{{ teams.length }} team{{ teams.length === 1 ? '' : 's' }}</span>
                        </div>

                        <div v-if="teams.length > 0" class="space-y-4">
                            <div v-for="team in teams" :key="team.id"
                                 class="flex items-center justify-between p-4 bg-gray-50 dark:bg-gray-700 rounded-xl">
                                <div class="flex items-center space-x-3">
                                    <!-- Department Color Indicator -->
                                    <div :class="`w-3 h-3 rounded-full bg-${team.department_color}-500`"></div>
                                    
                                    <div>
                                        <div class="flex items-center space-x-2">
                                            <h4 class="font-medium text-gray-900 dark:text-white">{{ team.name }}</h4>
                                            <i v-if="team.is_team_lead" 
                                               class="fas fa-crown text-yellow-500 text-sm" 
                                               title="Team Lead"></i>
                                        </div>
                                        <div class="flex items-center space-x-2 text-sm text-gray-500">
                                            <span class="px-2 py-1 bg-blue-100 text-blue-700 rounded-md text-xs">
                                                {{ team.role.name }}
                                            </span>
                                            <span>{{ team.department }}</span>
                                            <span>•</span>
                                            <span>Joined {{ team.joined_at }}</span>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Team Actions -->
                                <div v-if="permissions.can_manage_teams" class="flex space-x-2">
                                    <button @click="$inertia.visit(route('teams.show', team.id))"
                                            class="text-blue-600 hover:text-blue-800 text-sm">
                                        <i class="fas fa-external-link-alt"></i>
                                    </button>
                                    
                                    <button v-if="!team.is_team_lead"
                                            @click="confirmRemoveFromTeam(team)"
                                            class="text-red-600 hover:text-red-800 text-sm">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <div v-else class="text-center py-8">
                            <i class="fas fa-users text-gray-300 dark:text-gray-600 text-3xl mb-2"></i>
                            <p class="text-sm text-gray-500 dark:text-gray-400">Not a member of any teams</p>
                        </div>
                    </div>

                    <!-- Recent Activity -->
                    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                        <div class="flex items-center justify-between mb-6">
                            <h3 class="text-xl font-semibold text-gray-900 dark:text-white">Recent Activity</h3>
                            <button v-if="permissions.can_view_activity"
                                    @click="$inertia.visit(route('users.activity', user.id))"
                                    class="text-sm text-blue-600 hover:text-blue-800">
                                View All
                            </button>
                        </div>

                        <div v-if="recent_activity.length > 0" class="space-y-4">
                            <div v-for="activity in recent_activity" :key="activity.id"
                                 class="flex items-start space-x-3">
                                <div :class="[
                                    'flex-shrink-0 w-8 h-8 rounded-full flex items-center justify-center',
                                    `bg-${getActivityTypeStyle(activity.action).color}-100 text-${getActivityTypeStyle(activity.action).color}-600`
                                ]">
                                    <i :class="[getActivityTypeStyle(activity.action).icon, 'text-xs']"></i>
                                </div>
                                
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm text-gray-900 dark:text-white">{{ activity.description }}</p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ activity.created_at }}</p>
                                </div>
                            </div>
                        </div>
                        
                        <div v-else class="text-center py-8">
                            <i class="fas fa-history text-gray-300 dark:text-gray-600 text-3xl mb-2"></i>
                            <p class="text-sm text-gray-500 dark:text-gray-400">No recent activity</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Password Reset Modal -->
        <Modal :show="showPasswordResetModal" @close="showPasswordResetModal = false">
            <div class="p-6">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-6">Reset User Password</h2>
                
                <form @submit.prevent="resetPassword" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            New Password
                        </label>
                        <TextInput v-model="passwordResetForm.password" 
                                  type="password" 
                                  class="w-full" 
                                  required />
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Confirm Password
                        </label>
                        <TextInput v-model="passwordResetForm.password_confirmation" 
                                  type="password" 
                                  class="w-full" 
                                  required />
                    </div>
                    
                    <div class="flex justify-end space-x-3 pt-4">
                        <SecondaryButton @click="showPasswordResetModal = false">Cancel</SecondaryButton>
                        <PrimaryButton type="submit">Reset Password</PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>

        <!-- Add to Team Modal -->
        <Modal :show="showAddToTeamModal" @close="showAddToTeamModal = false">
            <div class="p-6">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-6">Add to Team</h2>
                
                <form @submit.prevent="addToTeam" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Select Team
                        </label>
                        <SelectInput v-model="addToTeamForm.team_id" class="w-full" required>
                            <option value="">Choose a team</option>
                            <option v-for="team in available_teams" :key="team.id" :value="team.id">
                                {{ team.name }} ({{ team.department }})
                            </option>
                        </SelectInput>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Role
                        </label>
                        <SelectInput v-model="addToTeamForm.role_id" class="w-full" required>
                            <option value="">Select a role</option>
                            <option v-for="role in available_roles" :key="role.id" :value="role.id">
                                {{ role.name }}
                            </option>
                        </SelectInput>
                    </div>
                    
                    <div class="flex justify-end space-x-3 pt-4">
                        <SecondaryButton @click="showAddToTeamModal = false">Cancel</SecondaryButton>
                        <PrimaryButton type="submit">Add to Team</PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>

        <!-- Remove from Team Confirmation Modal -->
        <Modal :show="showRemoveFromTeamModal" @close="showRemoveFromTeamModal = false">
            <div v-if="teamToRemove" class="p-6">
                <div class="flex items-center space-x-3 mb-4">
                    <div class="flex-shrink-0">
                        <i class="fas fa-exclamation-triangle text-red-500 text-2xl"></i>
                    </div>
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Remove from Team</h2>
                        <p class="text-gray-600 dark:text-gray-400 mt-1">
                            Are you sure you want to remove {{ user.name }} from {{ teamToRemove.name }}?
                        </p>
                    </div>
                </div>
                
                <div class="flex justify-end space-x-3">
                    <SecondaryButton @click="showRemoveFromTeamModal = false">Cancel</SecondaryButton>
                    <DangerButton @click="removeFromTeam">Remove from Team</DangerButton>
                </div>
            </div>
        </Modal>

        <!-- Deactivate User Confirmation Modal -->
        <Modal :show="showDeactivateModal" @close="showDeactivateModal = false">
            <div class="p-6">
                <div class="flex items-center space-x-3 mb-4">
                    <div class="flex-shrink-0">
                        <i class="fas fa-exclamation-triangle text-yellow-500 text-2xl"></i>
                    </div>
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Deactivate User</h2>
                        <p class="text-gray-600 dark:text-gray-400 mt-1">
                            Are you sure you want to deactivate {{ user.name }}?
                        </p>
                    </div>
                </div>
                
                <div class="bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg p-4 mb-6">
                    <p class="text-yellow-800 dark:text-yellow-300 text-sm">
                        <strong>This will:</strong>
                    </p>
                    <ul class="text-yellow-700 dark:text-yellow-400 text-sm mt-1 ml-4 list-disc">
                        <li>Revoke access to the system</li>
                        <li>Deactivate them from all teams</li>
                        <li>Keep their data and history intact</li>
                    </ul>
                </div>
                
                <div class="flex justify-end space-x-3">
                    <SecondaryButton @click="showDeactivateModal = false">Cancel</SecondaryButton>
                    <PrimaryButton @click="deactivateUser" class="bg-yellow-600 hover:bg-yellow-700">
                        Deactivate User
                    </PrimaryButton>
                </div>
            </div>
        </Modal>
    </AppLayout>
</template>