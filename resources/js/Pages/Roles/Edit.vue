<script setup>
import { ref, computed } from 'vue'
import { useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import PrimaryButton from '@/Components/PrimaryButton.vue'
import SecondaryButton from '@/Components/SecondaryButton.vue'
import TextInput from '@/Components/TextInput.vue'
import TextareaInput from '@/Components/TextareaInput.vue'
import Checkbox from '@/Components/Checkbox.vue'
import InputError from '@/Components/InputError.vue'
import InputLabel from '@/Components/InputLabel.vue'

const props = defineProps({
    role: Object,
    available_permissions: Object,
    permission_categories: Object,
    users_with_role: Number
})

const form = useForm({
    name: props.role.name,
    description: props.role.description || '',
    permissions: props.role.permissions || [],
    is_active: props.role.is_active
})

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

const submit = () => {
    form.put(route('roles.update', props.role.id), {
        onSuccess: () => {
            // Handled by redirect
        }
    })
}
</script>

<template>
    <AppLayout :title="`Edit Role - ${role.name}`">
        <template #header>
            <div class="flex items-center space-x-4">
                <button @click="$inertia.visit(route('roles.index'))"
                        class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                    <i class="fas fa-arrow-left text-lg"></i>
                </button>
                
                <div class="flex items-center space-x-3">
                    <div :class="[
                        'w-10 h-10 rounded-xl flex items-center justify-center',
                        `bg-${getRoleColor(role.slug)}-100 dark:bg-${getRoleColor(role.slug)}-900/30`
                    ]">
                        <i :class="[
                            getRoleIcon(role.slug),
                            `text-${getRoleColor(role.slug)}-600 dark:text-${getRoleColor(role.slug)}-400`
                        ]"></i>
                    </div>
                    
                    <div>
                        <h2 class="text-3xl font-bold text-gray-900 dark:text-white">Edit Role</h2>
                        <p class="text-gray-600 dark:text-gray-400 mt-1">
                            Modify role details and permissions
                        </p>
                    </div>
                </div>
            </div>
        </template>

        <div class="max-w-4xl mx-auto">
            <form @submit.prevent="submit" class="space-y-8">
                <!-- Role Information -->
                <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 p-8">
                    <div class="flex items-center space-x-3 mb-6">
                        <div :class="[
                            'w-10 h-10 rounded-lg flex items-center justify-center',
                            `bg-${getRoleColor(role.slug)}-100 dark:bg-${getRoleColor(role.slug)}-900/30`
                        ]">
                            <i :class="[
                                getRoleIcon(role.slug),
                                `text-${getRoleColor(role.slug)}-600 dark:text-${getRoleColor(role.slug)}-400`
                            ]"></i>
                        </div>
                        <div>
                            <h3 class="text-xl font-semibold text-gray-900 dark:text-white">Role Information</h3>
                            <p class="text-sm text-gray-600 dark:text-gray-400">Basic role details and status</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Role Name -->
                        <div>
                            <InputLabel for="name" value="Role Name *" />
                            <TextInput
                                id="name"
                                v-model="form.name"
                                type="text"
                                class="mt-1 block w-full"
                                :class="{ 'opacity-50': role.is_system }"
                                :readonly="role.is_system"
                                required
                                autofocus
                            />
                            <InputError class="mt-2" :message="form.errors.name" />
                            <p v-if="role.is_system" class="text-xs text-amber-600 dark:text-amber-400 mt-1">
                                <i class="fas fa-lock mr-1"></i>
                                System role names cannot be modified
                            </p>
                        </div>

                        <!-- Active Status -->
                        <div class="flex items-start space-x-3 pt-6">
                            <Checkbox v-model:checked="form.is_active" id="is_active" />
                            <div>
                                <InputLabel for="is_active" value="Active Role" class="!mb-0" />
                                <p class="text-sm text-gray-600 dark:text-gray-400">
                                    Active roles can be assigned to users
                                </p>
                            </div>
                        </div>

                        <!-- Description -->
                        <div class="md:col-span-2">
                            <InputLabel for="description" value="Role Description" />
                            <TextareaInput
                                id="description"
                                v-model="form.description"
                                class="mt-1 block w-full"
                                rows="3"
                                placeholder="Describe the purpose and responsibilities of this role..."
                            />
                            <InputError class="mt-2" :message="form.errors.description" />
                        </div>
                    </div>
                </div>

                <!-- Role Statistics -->
                <div class="bg-gradient-to-r from-blue-50 to-indigo-50 dark:from-blue-900/20 dark:to-indigo-900/20 rounded-2xl border border-blue-200 dark:border-blue-800 p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-lg font-semibold text-blue-900 dark:text-blue-100 mb-1">Role Usage</h3>
                            <p class="text-blue-700 dark:text-blue-300">
                                This role is currently assigned to <strong>{{ users_with_role }}</strong> 
                                user{{ users_with_role === 1 ? '' : 's' }}.
                            </p>
                        </div>
                        <div class="text-blue-500 dark:text-blue-400">
                            <i class="fas fa-users text-2xl"></i>
                        </div>
                    </div>
                </div>

                <!-- Permissions Management -->
                <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 p-8">
                    <div class="flex items-center justify-between mb-6">
                        <div class="flex items-center space-x-3">
                            <div class="w-10 h-10 bg-emerald-100 dark:bg-emerald-900/30 rounded-lg flex items-center justify-center">
                                <i class="fas fa-key text-emerald-600 dark:text-emerald-400"></i>
                            </div>
                            <div>
                                <h3 class="text-xl font-semibold text-gray-900 dark:text-white">Role Permissions</h3>
                                <p class="text-sm text-gray-600 dark:text-gray-400">Select permissions for this role</p>
                            </div>
                        </div>
                        
                        <div class="text-sm text-gray-500 dark:text-gray-400">
                            {{ form.permissions.length }} permission{{ form.permissions.length === 1 ? '' : 's' }} selected
                        </div>
                    </div>

                    <!-- Permission Categories -->
                    <div class="space-y-6">
                        <div v-for="(categoryPermissions, category) in permission_categories" :key="category"
                             class="border border-gray-200 dark:border-gray-600 rounded-xl p-4">
                            <div class="flex items-center justify-between mb-3">
                                <h4 class="font-medium text-gray-900 dark:text-white">{{ category }}</h4>
                                
                                <!-- Select All for Category -->
                                <button type="button"
                                        @click="toggleCategoryPermissions(category, categoryPermissions)"
                                        class="text-sm text-blue-600 hover:text-blue-800 dark:text-blue-400">
                                    {{ categoryPermissions.every(p => form.permissions.includes(p)) ? 'Deselect All' : 'Select All' }}
                                </button>
                            </div>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                                <label v-for="permission in categoryPermissions" :key="permission"
                                       class="flex items-start space-x-3 p-2 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700">
                                    <Checkbox
                                        :value="permission"
                                        v-model:checked="form.permissions"
                                        class="mt-0.5 flex-shrink-0"
                                    />
                                    <div class="min-w-0 flex-1">
                                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300 block">
                                            {{ available_permissions[permission] || permission }}
                                        </span>
                                        <span class="text-xs text-gray-500 dark:text-gray-400 block">
                                            {{ permission }}
                                        </span>
                                    </div>
                                </label>
                            </div>
                        </div>
                    </div>

                    <InputError class="mt-4" :message="form.errors.permissions" />
                </div>

                <!-- Warning for System Roles -->
                <div v-if="role.is_system" 
                     class="bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-2xl p-6">
                    <div class="flex items-start space-x-3">
                        <div class="flex-shrink-0">
                            <i class="fas fa-exclamation-triangle text-amber-500 text-xl"></i>
                        </div>
                        <div>
                            <h3 class="text-lg font-semibold text-amber-800 dark:text-amber-300 mb-1">System Role</h3>
                            <p class="text-amber-700 dark:text-amber-400 text-sm">
                                This is a system role. Changes to system roles affect the entire platform. 
                                Please ensure you understand the implications before modifying permissions.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Form Actions -->
                <div class="flex items-center justify-between pt-6">
                    <SecondaryButton @click="$inertia.visit(route('roles.show', role.id))" type="button">
                        <i class="fas fa-eye mr-2"></i>
                        View Role Details
                    </SecondaryButton>
                    
                    <div class="flex items-center space-x-3">
                        <SecondaryButton @click="$inertia.visit(route('roles.index'))" type="button">
                            Cancel
                        </SecondaryButton>
                        
                        <PrimaryButton 
                            :class="{ 'opacity-25': form.processing }" 
                            :disabled="form.processing"
                            type="submit"
                        >
                            <i class="fas fa-save mr-2"></i>
                            Update Role
                        </PrimaryButton>
                    </div>
                </div>
            </form>
        </div>
    </AppLayout>
</template>

<script>
export default {
    methods: {
        toggleCategoryPermissions(category, categoryPermissions) {
            const allSelected = categoryPermissions.every(permission => 
                this.form.permissions.includes(permission)
            );
            
            if (allSelected) {
                // Remove all category permissions
                this.form.permissions = this.form.permissions.filter(permission => 
                    !categoryPermissions.includes(permission)
                );
            } else {
                // Add all category permissions
                const newPermissions = [...this.form.permissions];
                categoryPermissions.forEach(permission => {
                    if (!newPermissions.includes(permission)) {
                        newPermissions.push(permission);
                    }
                });
                this.form.permissions = newPermissions;
            }
        }
    }
}
</script>