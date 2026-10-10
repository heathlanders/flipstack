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
    available_permissions: Object,
    permission_categories: Object,
    suggested_roles: Array
})

const form = useForm({
    name: '',
    description: '',
    permissions: [],
    is_active: true,
    template: ''
})

const selectedTemplate = ref('')

const applyTemplate = (template) => {
    form.name = template.name
    form.description = template.description
    form.permissions = [...template.permissions]
    selectedTemplate.value = template.name
}

const clearTemplate = () => {
    form.reset()
    selectedTemplate.value = ''
}

const togglePermissionCategory = (category) => {
    const categoryPermissions = props.permission_categories[category] || []
    const allSelected = categoryPermissions.every(perm => form.permissions.includes(perm))
    
    if (allSelected) {
        // Remove all permissions from this category
        form.permissions = form.permissions.filter(perm => !categoryPermissions.includes(perm))
    } else {
        // Add all permissions from this category
        const newPermissions = [...form.permissions]
        categoryPermissions.forEach(perm => {
            if (!newPermissions.includes(perm)) {
                newPermissions.push(perm)
            }
        })
        form.permissions = newPermissions
    }
}

const isCategoryFullySelected = (category) => {
    const categoryPermissions = props.permission_categories[category] || []
    return categoryPermissions.length > 0 && categoryPermissions.every(perm => form.permissions.includes(perm))
}

const isCategoryPartiallySelected = (category) => {
    const categoryPermissions = props.permission_categories[category] || []
    return categoryPermissions.some(perm => form.permissions.includes(perm)) && 
           !categoryPermissions.every(perm => form.permissions.includes(perm))
}

const submit = () => {
    form.post(route('roles.store'), {
        onSuccess: () => {
            // Handle success
        }
    })
}

const permissionCount = computed(() => form.permissions.length)
</script>

<template>
    <AppLayout title="Create Role">
        <template #header>
            <div class="flex items-center space-x-4">
                <button @click="$inertia.visit(route('roles.index'))"
                        class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                    <i class="fas fa-arrow-left text-lg"></i>
                </button>
                
                <div>
                    <h2 class="text-3xl font-bold text-gray-900 dark:text-white">Create New Role</h2>
                    <p class="text-gray-600 dark:text-gray-400 mt-1">
                        Define a new role with specific permissions and responsibilities
                    </p>
                </div>
            </div>
        </template>

        <div class="max-w-5xl mx-auto">
            <!-- Role Templates -->
            <div v-if="suggested_roles.length > 0" 
                 class="bg-gradient-to-r from-blue-50 to-indigo-50 dark:from-gray-800 dark:to-gray-700 rounded-2xl shadow-sm border border-blue-200 dark:border-gray-600 p-6 mb-8">
                <div class="flex items-center space-x-3 mb-4">
                    <div class="w-10 h-10 bg-blue-100 dark:bg-blue-900/30 rounded-lg flex items-center justify-center">
                        <i class="fas fa-magic text-blue-600 dark:text-blue-400"></i>
                    </div>
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Quick Start Templates</h3>
                        <p class="text-sm text-gray-600 dark:text-gray-400">Start with a pre-configured role template</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    <button v-for="template in suggested_roles" :key="template.name"
                            @click="applyTemplate(template)"
                            :class="[
                                'text-left p-4 rounded-lg border transition-colors',
                                selectedTemplate === template.name
                                    ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/20'
                                    : 'border-gray-200 dark:border-gray-600 hover:border-blue-300 dark:hover:border-blue-500 hover:bg-blue-25'
                            ]">
                        <div class="font-medium text-gray-900 dark:text-white mb-1">{{ template.name }}</div>
                        <div class="text-sm text-gray-600 dark:text-gray-400 mb-2">{{ template.description }}</div>
                        <div class="text-xs text-blue-600 dark:text-blue-400">{{ template.permissions.length }} permissions</div>
                    </button>
                </div>

                <div v-if="selectedTemplate" class="mt-4 flex items-center justify-between">
                    <span class="text-sm text-blue-700 dark:text-blue-300">
                        <i class="fas fa-info-circle mr-1"></i>
                        Template "{{ selectedTemplate }}" applied. You can customize it below.
                    </span>
                    <button @click="clearTemplate"
                            class="text-sm text-gray-600 hover:text-gray-800 dark:text-gray-400 dark:hover:text-gray-200">
                        Clear Template
                    </button>
                </div>
            </div>

            <form @submit.prevent="submit" class="space-y-8">
                <!-- Role Information -->
                <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 p-8">
                    <div class="flex items-center space-x-3 mb-6">
                        <div class="w-10 h-10 bg-purple-100 dark:bg-purple-900/30 rounded-lg flex items-center justify-center">
                            <i class="fas fa-user-tag text-purple-600 dark:text-purple-400"></i>
                        </div>
                        <div>
                            <h3 class="text-xl font-semibold text-gray-900 dark:text-white">Role Information</h3>
                            <p class="text-sm text-gray-600 dark:text-gray-400">Basic details about this role</p>
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
                                placeholder="e.g., Team Lead, Inventory Manager"
                                required
                                autofocus
                            />
                            <InputError class="mt-2" :message="form.errors.name" />
                        </div>

                        <!-- Status -->
                        <div>
                            <InputLabel value="Role Status" />
                            <div class="mt-2">
                                <label class="flex items-center">
                                    <Checkbox v-model:checked="form.is_active" />
                                    <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">
                                        Role is active and can be assigned to users
                                    </span>
                                </label>
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
                                placeholder="Describe the role's responsibilities, scope, and purpose..."
                            />
                            <InputError class="mt-2" :message="form.errors.description" />
                        </div>
                    </div>
                </div>

                <!-- Permissions -->
                <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 p-8">
                    <div class="flex items-center justify-between mb-6">
                        <div class="flex items-center space-x-3">
                            <div class="w-10 h-10 bg-emerald-100 dark:bg-emerald-900/30 rounded-lg flex items-center justify-center">
                                <i class="fas fa-key text-emerald-600 dark:text-emerald-400"></i>
                            </div>
                            <div>
                                <h3 class="text-xl font-semibold text-gray-900 dark:text-white">Role Permissions</h3>
                                <p class="text-sm text-gray-600 dark:text-gray-400">Select what this role can access and do</p>
                            </div>
                        </div>
                        
                        <div class="text-sm text-gray-600 dark:text-gray-400">
                            {{ permissionCount }} permission{{ permissionCount === 1 ? '' : 's' }} selected
                        </div>
                    </div>

                    <div class="space-y-6">
                        <div v-for="(permissions, category) in permission_categories" :key="category">
                            <div class="flex items-center justify-between mb-3">
                                <h4 class="font-medium text-gray-900 dark:text-white">{{ category }}</h4>
                                
                                <!-- Category Toggle -->
                                <button type="button"
                                        @click="togglePermissionCategory(category)"
                                        :class="[
                                            'text-sm px-3 py-1 rounded-md transition-colors',
                                            isCategoryFullySelected(category)
                                                ? 'bg-red-100 text-red-700 hover:bg-red-200 dark:bg-red-900/30 dark:text-red-300'
                                                : 'bg-emerald-100 text-emerald-700 hover:bg-emerald-200 dark:bg-emerald-900/30 dark:text-emerald-300'
                                        ]">
                                    {{ isCategoryFullySelected(category) ? 'Deselect All' : 'Select All' }}
                                </button>
                            </div>
                            
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2 pl-4">
                                <label v-for="permission in permissions" :key="permission"
                                       class="flex items-start space-x-2 p-2 rounded hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                                    <Checkbox 
                                        :value="permission"
                                        v-model:checked="form.permissions"
                                        class="mt-0.5"
                                    />
                                    <div class="flex-1">
                                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">
                                            {{ available_permissions[permission] }}
                                        </span>
                                        <div class="text-xs text-gray-500 dark:text-gray-400">{{ permission }}</div>
                                    </div>
                                </label>
                            </div>
                        </div>
                    </div>

                    <InputError class="mt-4" :message="form.errors.permissions" />
                </div>

                <!-- Permission Summary -->
                <div v-if="permissionCount > 0" 
                     class="bg-gray-50 dark:bg-gray-700 rounded-2xl border border-gray-200 dark:border-gray-600 p-6">
                    <h4 class="font-medium text-gray-900 dark:text-white mb-3">Permission Summary</h4>
                    
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                        <div v-for="(permissions, category) in permission_categories" :key="category"
                             class="text-center">
                            <div class="text-2xl font-bold text-gray-900 dark:text-white">
                                {{ permissions.filter(p => form.permissions.includes(p)).length }}
                            </div>
                            <div class="text-sm text-gray-600 dark:text-gray-400">{{ category }}</div>
                            <div class="text-xs text-gray-500">
                                of {{ permissions.length }}
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Form Actions -->
                <div class="flex items-center justify-end space-x-4 pt-6">
                    <SecondaryButton @click="$inertia.visit(route('roles.index'))" type="button">
                        Cancel
                    </SecondaryButton>
                    
                    <PrimaryButton 
                        :class="{ 'opacity-25': form.processing }" 
                        :disabled="form.processing"
                        type="submit"
                    >
                        <i class="fas fa-user-tag mr-2"></i>
                        Create Role
                    </PrimaryButton>
                </div>
            </form>
        </div>
    </AppLayout>
</template>