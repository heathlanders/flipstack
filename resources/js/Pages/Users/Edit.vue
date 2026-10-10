<script setup>
import { ref, computed } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import PrimaryButton from '@/Components/PrimaryButton.vue'
import SecondaryButton from '@/Components/SecondaryButton.vue'
import DangerButton from '@/Components/DangerButton.vue'
import TextInput from '@/Components/TextInput.vue'
import SelectInput from '@/Components/SelectInput.vue'
import TextareaInput from '@/Components/TextareaInput.vue'
import Checkbox from '@/Components/Checkbox.vue'
import InputError from '@/Components/InputError.vue'
import InputLabel from '@/Components/InputLabel.vue'
import Modal from '@/Components/Modal.vue'

const props = defineProps({
    user: Object,
    available_roles: Array,
    departments: Array,
    timezones: Array,
    available_permissions: Object,
    user_teams: Array
})

const form = useForm({
    name: props.user.name,
    email: props.user.email,
    employee_id: props.user.employee_id,
    phone: props.user.phone,
    department: props.user.department,
    hire_date: props.user.hire_date,
    bio: props.user.bio,
    timezone: props.user.timezone,
    is_active: props.user.is_active,
    permissions: props.user.permissions || [],
    role_id: props.user.current_role_id
})

// Modal states
const showPasswordModal = ref(false)
const showDeactivateModal = ref(false)

// Password reset form
const passwordForm = useForm({
    password: '',
    password_confirmation: ''
})

const permissionCategories = computed(() => {
    const categories = {}
    Object.entries(props.available_permissions).forEach(([key, name]) => {
        const category = key.split('.')[0]
        const categoryName = category.charAt(0).toUpperCase() + category.slice(1)
        
        if (!categories[categoryName]) {
            categories[categoryName] = []
        }
        categories[categoryName].push({ id: key, name })
    })
    return categories
})

const generatePassword = () => {
    const charset = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*"
    let password = ""
    for (let i = 0; i < 12; i++) {
        password += charset.charAt(Math.floor(Math.random() * charset.length))
    }
    passwordForm.password = password
    passwordForm.password_confirmation = password
}

const updateUser = () => {
    form.put(route('users.update', props.user.id), {
        onSuccess: () => {
            // Handle success
        }
    })
}

const resetPassword = () => {
    passwordForm.patch(route('users.reset-password', props.user.id), {
        onSuccess: () => {
            showPasswordModal.value = false
            passwordForm.reset()
        }
    })
}

const toggleUserStatus = () => {
    const action = props.user.is_active ? 'deactivate' : 'reactivate'
    router.patch(route(`users.${action}`, props.user.id), {}, {
        preserveScroll: true,
        onSuccess: () => {
            showDeactivateModal.value = false
        }
    })
}

const removeFromTeam = (teamId) => {
    router.delete(route('users.remove-from-team', [props.user.id, teamId]), {
        preserveScroll: true
    })
}
</script>

<template>
    <AppLayout :title="`Edit ${user.name}`">
        <template #header>
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-4">
                    <button @click="$inertia.visit(route('users.show', user.id))"
                            class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                        <i class="fas fa-arrow-left text-lg"></i>
                    </button>
                    
                    <div class="flex items-center space-x-3">
                        <img :src="user.avatar || '/default-avatar.png'" 
                             :alt="user.name"
                             class="h-12 w-12 rounded-full">
                        <div>
                            <h2 class="text-3xl font-bold text-gray-900 dark:text-white">Edit {{ user.name }}</h2>
                            <p class="text-gray-600 dark:text-gray-400 mt-1">{{ user.email }}</p>
                        </div>
                    </div>
                </div>
                
                <div class="flex space-x-3">
                    <button @click="showPasswordModal = true"
                            class="bg-orange-600 hover:bg-orange-700 text-white px-4 py-2 rounded-lg font-medium transition-colors">
                        <i class="fas fa-key mr-2"></i>
                        Reset Password
                    </button>
                    
                    <button @click="user.is_active ? (showDeactivateModal = true) : toggleUserStatus()"
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

        <div class="max-w-4xl mx-auto">
            <form @submit.prevent="updateUser" class="space-y-8">
                <!-- Basic Information -->
                <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 p-8">
                    <div class="flex items-center space-x-3 mb-6">
                        <div class="w-10 h-10 bg-blue-100 dark:bg-blue-900/30 rounded-lg flex items-center justify-center">
                            <i class="fas fa-user text-blue-600 dark:text-blue-400"></i>
                        </div>
                        <div>
                            <h3 class="text-xl font-semibold text-gray-900 dark:text-white">Basic Information</h3>
                            <p class="text-sm text-gray-600 dark:text-gray-400">Personal details and contact information</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Full Name -->
                        <div>
                            <InputLabel for="name" value="Full Name" />
                            <TextInput
                                id="name"
                                v-model="form.name"
                                type="text"
                                class="mt-1 block w-full"
                                required
                            />
                            <InputError class="mt-2" :message="form.errors.name" />
                        </div>

                        <!-- Email -->
                        <div>
                            <InputLabel for="email" value="Email Address" />
                            <TextInput
                                id="email"
                                v-model="form.email"
                                type="email"
                                class="mt-1 block w-full"
                                required
                            />
                            <InputError class="mt-2" :message="form.errors.email" />
                        </div>

                        <!-- Employee ID -->
                        <div>
                            <InputLabel for="employee_id" value="Employee ID" />
                            <TextInput
                                id="employee_id"
                                v-model="form.employee_id"
                                type="text"
                                class="mt-1 block w-full"
                            />
                            <InputError class="mt-2" :message="form.errors.employee_id" />
                        </div>

                        <!-- Phone -->
                        <div>
                            <InputLabel for="phone" value="Phone Number" />
                            <TextInput
                                id="phone"
                                v-model="form.phone"
                                type="tel"
                                class="mt-1 block w-full"
                            />
                            <InputError class="mt-2" :message="form.errors.phone" />
                        </div>

                        <!-- Department -->
                        <div>
                            <InputLabel for="department" value="Department" />
                            <SelectInput
                                id="department"
                                v-model="form.department"
                                class="mt-1 block w-full"
                            >
                                <option value="">Select Department</option>
                                <option v-for="dept in departments" :key="dept.value" :value="dept.value">
                                    {{ dept.label }}
                                </option>
                            </SelectInput>
                            <InputError class="mt-2" :message="form.errors.department" />
                        </div>

                        <!-- Hire Date -->
                        <div>
                            <InputLabel for="hire_date" value="Hire Date" />
                            <TextInput
                                id="hire_date"
                                v-model="form.hire_date"
                                type="date"
                                class="mt-1 block w-full"
                            />
                            <InputError class="mt-2" :message="form.errors.hire_date" />
                        </div>

                        <!-- Timezone -->
                        <div>
                            <InputLabel for="timezone" value="Timezone" />
                            <SelectInput
                                id="timezone"
                                v-model="form.timezone"
                                class="mt-1 block w-full"
                            >
                                <option v-for="tz in timezones" :key="tz.value" :value="tz.value">
                                    {{ tz.label }}
                                </option>
                            </SelectInput>
                            <InputError class="mt-2" :message="form.errors.timezone" />
                        </div>

                        <!-- Status -->
                        <div>
                            <InputLabel value="Account Status" />
                            <div class="mt-2">
                                <label class="flex items-center">
                                    <Checkbox v-model:checked="form.is_active" />
                                    <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">
                                        User account is active
                                    </span>
                                </label>
                            </div>
                        </div>

                        <!-- Bio -->
                        <div class="md:col-span-2">
                            <InputLabel for="bio" value="Bio / Description" />
                            <TextareaInput
                                id="bio"
                                v-model="form.bio"
                                class="mt-1 block w-full"
                                rows="3"
                            />
                            <InputError class="mt-2" :message="form.errors.bio" />
                        </div>
                    </div>
                </div>

                <!-- Role & Permissions -->
                <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 p-8">
                    <div class="flex items-center space-x-3 mb-6">
                        <div class="w-10 h-10 bg-purple-100 dark:bg-purple-900/30 rounded-lg flex items-center justify-center">
                            <i class="fas fa-user-cog text-purple-600 dark:text-purple-400"></i>
                        </div>
                        <div>
                            <h3 class="text-xl font-semibold text-gray-900 dark:text-white">Role & Permissions</h3>
                            <p class="text-sm text-gray-600 dark:text-gray-400">Primary role and additional permissions</p>
                        </div>
                    </div>

                    <div class="space-y-6">
                        <!-- Primary Role -->
                        <div>
                            <InputLabel for="role_id" value="Primary Role" />
                            <SelectInput
                                id="role_id"
                                v-model="form.role_id"
                                class="mt-1 block w-full"
                            >
                                <option value="">Select a role</option>
                                <option v-for="role in available_roles" :key="role.id" :value="role.id">
                                    {{ role.name }}
                                </option>
                            </SelectInput>
                            <InputError class="mt-2" :message="form.errors.role_id" />
                        </div>

                        <!-- Additional Permissions -->
                        <div>
                            <InputLabel value="Additional Permissions" />
                            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Grant specific permissions beyond the primary role</p>
                            
                            <div class="mt-4 space-y-4">
                                <div v-for="(permissions, category) in permissionCategories" :key="category">
                                    <h4 class="font-medium text-gray-900 dark:text-white mb-2">{{ category }}</h4>
                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 pl-4">
                                        <label v-for="permission in permissions" :key="permission.id" 
                                               class="flex items-center space-x-2">
                                            <Checkbox 
                                                :value="permission.id"
                                                v-model:checked="form.permissions"
                                            />
                                            <span class="text-sm text-gray-700 dark:text-gray-300">{{ permission.name }}</span>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Current Teams -->
                <div v-if="user_teams.length > 0" class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 p-8">
                    <div class="flex items-center space-x-3 mb-6">
                        <div class="w-10 h-10 bg-emerald-100 dark:bg-emerald-900/30 rounded-lg flex items-center justify-center">
                            <i class="fas fa-users text-emerald-600 dark:text-emerald-400"></i>
                        </div>
                        <div>
                            <h3 class="text-xl font-semibold text-gray-900 dark:text-white">Team Memberships</h3>
                            <p class="text-sm text-gray-600 dark:text-gray-400">Current team assignments</p>
                        </div>
                    </div>

                    <div class="space-y-3">
                        <div v-for="team in user_teams" :key="team.id"
                             class="flex items-center justify-between p-4 bg-gray-50 dark:bg-gray-700 rounded-lg">
                            <div>
                                <div class="font-medium text-gray-900 dark:text-white">{{ team.name }}</div>
                                <div class="text-sm text-gray-600 dark:text-gray-400">
                                    Role: {{ team.role }} • Can be removed: {{ team.can_remove ? 'Yes' : 'No' }}
                                </div>
                            </div>
                            
                            <button v-if="team.can_remove"
                                    @click="removeFromTeam(team.id)"
                                    class="text-red-600 hover:text-red-800 text-sm">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Form Actions -->
                <div class="flex items-center justify-between pt-6">
                    <SecondaryButton @click="$inertia.visit(route('users.show', user.id))" type="button">
                        Cancel
                    </SecondaryButton>
                    
                    <PrimaryButton 
                        :class="{ 'opacity-25': form.processing }" 
                        :disabled="form.processing"
                        type="submit"
                    >
                        <i class="fas fa-save mr-2"></i>
                        Update User
                    </PrimaryButton>
                </div>
            </form>
        </div>

        <!-- Reset Password Modal -->
        <Modal :show="showPasswordModal" @close="showPasswordModal = false">
            <div class="p-6">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-6">Reset Password</h2>
                
                <form @submit.prevent="resetPassword" class="space-y-4">
                    <div>
                        <div class="flex items-center justify-between">
                            <InputLabel for="password" value="New Password" />
                            <button type="button" 
                                    @click="generatePassword"
                                    class="text-sm text-blue-600 hover:text-blue-800 dark:text-blue-400">
                                Generate
                            </button>
                        </div>
                        <TextInput
                            id="password"
                            v-model="passwordForm.password"
                            type="password"
                            class="mt-1 block w-full"
                            required
                        />
                        <InputError class="mt-2" :message="passwordForm.errors.password" />
                    </div>
                    
                    <div>
                        <InputLabel for="password_confirmation" value="Confirm Password" />
                        <TextInput
                            id="password_confirmation"
                            v-model="passwordForm.password_confirmation"
                            type="password"
                            class="mt-1 block w-full"
                            required
                        />
                        <InputError class="mt-2" :message="passwordForm.errors.password_confirmation" />
                    </div>
                    
                    <div class="flex justify-end space-x-3 pt-4">
                        <SecondaryButton @click="showPasswordModal = false" type="button">Cancel</SecondaryButton>
                        <PrimaryButton 
                            :disabled="passwordForm.processing"
                            type="submit"
                        >
                            Reset Password
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>

        <!-- Deactivate Confirmation Modal -->
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
                        This will revoke their access to the system but preserve their data and history.
                    </p>
                </div>
                
                <div class="flex justify-end space-x-3">
                    <SecondaryButton @click="showDeactivateModal = false">Cancel</SecondaryButton>
                    <DangerButton @click="toggleUserStatus">Deactivate User</DangerButton>
                </div>
            </div>
        </Modal>
    </AppLayout>
</template>