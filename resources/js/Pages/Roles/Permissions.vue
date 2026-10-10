<script setup>
import { ref, computed } from 'vue'
import { useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import PrimaryButton from '@/Components/PrimaryButton.vue'
import SecondaryButton from '@/Components/SecondaryButton.vue'
import Checkbox from '@/Components/Checkbox.vue'
import InputError from '@/Components/InputError.vue'

const props = defineProps({
    role: Object,
    available_permissions: Object,
    permission_categories: Object,
    permission_dependencies: Object
})

const form = useForm({
    permissions: [...(props.role.permissions || [])]
})

// Search and filter
const searchTerm = ref('')
const selectedCategory = ref('')

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

// Filtered categories based on search
const filteredCategories = computed(() => {
    let categories = props.permission_categories;
    
    if (selectedCategory.value) {
        categories = {
            [selectedCategory.value]: categories[selectedCategory.value]
        };
    }
    
    if (searchTerm.value) {
        const filtered = {};
        Object.entries(categories).forEach(([category, permissions]) => {
            const matchingPermissions = permissions.filter(permission => {
                const permissionName = props.available_permissions[permission] || permission;
                return permissionName.toLowerCase().includes(searchTerm.value.toLowerCase()) ||
                       permission.toLowerCase().includes(searchTerm.value.toLowerCase());
            });
            
            if (matchingPermissions.length > 0) {
                filtered[category] = matchingPermissions;
            }
        });
        return filtered;
    }
    
    return categories;
});

// Permission statistics
const permissionStats = computed(() => {
    const total = Object.values(props.permission_categories).flat().length;
    const selected = form.permissions.length;
    const percentage = total > 0 ? Math.round((selected / total) * 100) : 0;
    
    return { total, selected, percentage };
});

// Check if all permissions in category are selected
const isCategoryFullySelected = (categoryPermissions) => {
    return categoryPermissions.every(permission => form.permissions.includes(permission));
};

// Check if some permissions in category are selected
const isCategoryPartiallySelected = (categoryPermissions) => {
    return categoryPermissions.some(permission => form.permissions.includes(permission)) &&
           !isCategoryFullySelected(categoryPermissions);
};

// Toggle entire category
const toggleCategory = (category, categoryPermissions) => {
    const allSelected = isCategoryFullySelected(categoryPermissions);
    
    if (allSelected) {
        // Remove all category permissions
        form.permissions = form.permissions.filter(permission => 
            !categoryPermissions.includes(permission)
        );
    } else {
        // Add all category permissions
        const newPermissions = [...form.permissions];
        categoryPermissions.forEach(permission => {
            if (!newPermissions.includes(permission)) {
                newPermissions.push(permission);
            }
        });
        form.permissions = newPermissions;
    }
};

// Select all permissions
const selectAllPermissions = () => {
    form.permissions = Object.values(props.permission_categories).flat();
};

// Clear all permissions
const clearAllPermissions = () => {
    form.permissions = [];
};

// Submit form
const submit = () => {
    form.put(route('roles.update-permissions', props.role.id), {
        onSuccess: () => {
            // Handle success in redirect
        }
    });
};

// Check if permission has dependencies
const hasDependencies = (permission) => {
    return props.permission_dependencies && props.permission_dependencies[permission];
};

// Get permission dependencies
const getDependencies = (permission) => {
    return props.permission_dependencies ? props.permission_dependencies[permission] || [] : [];
};
</script>

<template>
    <AppLayout :title="`${role.name} - Manage Permissions`">
        <template #header>
            <div class="flex items-center space-x-4">
                <button @click="$inertia.visit(route('roles.show', role.id))"
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
                        <h2 class="text-3xl font-bold text-gray-900 dark:text-white">Manage Permissions</h2>
                        <p class="text-gray-600 dark:text-gray-400 mt-1">
                            Configure permissions for <strong>{{ role.name }}</strong>
                        </p>
                    </div>
                </div>
            </div>
        </template>

        <div class="max-w-6xl mx-auto">
            <form @submit.prevent="submit" class="space-y-8">
                <!-- Permission Statistics -->
                <div class="bg-gradient-to-r from-blue-50 to-indigo-50 dark:from-blue-900/20 dark:to-indigo-900/20 rounded-2xl border border-blue-200 dark:border-blue-800 p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-lg font-semibold text-blue-900 dark:text-blue-100 mb-1">Permission Coverage</h3>
                            <p class="text-blue-700 dark:text-blue-300">
                                <strong>{{ permissionStats.selected }}</strong> of <strong>{{ permissionStats.total }}</strong> 
                                permissions selected ({{ permissionStats.percentage }}%)
                            </p>
                        </div>
                        <div class="text-blue-500 dark:text-blue-400">
                            <div class="text-3xl font-bold">{{ permissionStats.percentage }}%</div>
                        </div>
                    </div>
                    
                    <!-- Progress Bar -->
                    <div class="mt-4">
                        <div class="w-full bg-blue-200 dark:bg-blue-800 rounded-full h-2">
                            <div class="bg-blue-600 h-2 rounded-full transition-all duration-300" 
                                 :style="`width: ${permissionStats.percentage}%`"></div>
                        </div>
                    </div>
                </div>

                <!-- Search and Filters -->
                <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between space-y-4 lg:space-y-0">
                        <!-- Search -->
                        <div class="flex-1 max-w-md">
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <i class="fas fa-search text-gray-400"></i>
                                </div>
                                <input v-model="searchTerm"
                                       type="text"
                                       class="block w-full pl-10 pr-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm"
                                       placeholder="Search permissions...">
                            </div>
                        </div>
                        
                        <!-- Category Filter -->
                        <div class="flex items-center space-x-4">
                            <select v-model="selectedCategory"
                                    class="rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm">
                                <option value="">All Categories</option>
                                <option v-for="category in Object.keys(permission_categories)" :key="category" :value="category">
                                    {{ category }}
                                </option>
                            </select>
                            
                            <!-- Bulk Actions -->
                            <div class="flex space-x-2">
                                <button type="button"
                                        @click="selectAllPermissions"
                                        class="px-3 py-1 text-sm bg-emerald-50 hover:bg-emerald-100 text-emerald-600 rounded-lg transition-colors">
                                    Select All
                                </button>
                                <button type="button"
                                        @click="clearAllPermissions"
                                        class="px-3 py-1 text-sm bg-red-50 hover:bg-red-100 text-red-600 rounded-lg transition-colors">
                                    Clear All
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Permission Categories -->
                <div class="space-y-6">
                    <div v-for="(categoryPermissions, category) in filteredCategories" :key="category"
                         class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                        
                        <!-- Category Header -->
                        <div class="flex items-center justify-between mb-4">
                            <div class="flex items-center space-x-3">
                                <Checkbox
                                    :checked="isCategoryFullySelected(categoryPermissions)"
                                    :indeterminate="isCategoryPartiallySelected(categoryPermissions)"
                                    @change="toggleCategory(category, categoryPermissions)"
                                />
                                <h3 class="text-xl font-semibold text-gray-900 dark:text-white">{{ category }}</h3>
                                <span class="px-2 py-1 text-xs bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-400 rounded">
                                    {{ categoryPermissions.filter(p => form.permissions.includes(p)).length }}/{{ categoryPermissions.length }}
                                </span>
                            </div>
                            
                            <button type="button"
                                    @click="toggleCategory(category, categoryPermissions)"
                                    class="text-sm text-blue-600 hover:text-blue-800 dark:text-blue-400">
                                {{ isCategoryFullySelected(categoryPermissions) ? 'Deselect All' : 'Select All' }}
                            </button>
                        </div>
                        
                        <!-- Permissions Grid -->
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                            <label v-for="permission in categoryPermissions" :key="permission"
                                   class="flex items-start space-x-3 p-3 rounded-lg border border-gray-200 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700 cursor-pointer transition-colors">
                                <Checkbox
                                    :value="permission"
                                    v-model:checked="form.permissions"
                                    class="mt-0.5 flex-shrink-0"
                                />
                                <div class="min-w-0 flex-1">
                                    <div class="font-medium text-gray-900 dark:text-white text-sm">
                                        {{ available_permissions[permission] || permission.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase()) }}
                                    </div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                        {{ permission }}
                                    </div>
                                    
                                    <!-- Dependencies -->
                                    <div v-if="hasDependencies(permission)" class="mt-2">
                                        <div class="text-xs text-amber-600 dark:text-amber-400">
                                            <i class="fas fa-link mr-1"></i>
                                            Requires: {{ getDependencies(permission).join(', ') }}
                                        </div>
                                    </div>
                                </div>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- No Results -->
                <div v-if="Object.keys(filteredCategories).length === 0" 
                     class="text-center py-12 bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700">
                    <i class="fas fa-search text-gray-300 dark:text-gray-600 text-4xl mb-4"></i>
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-2">No permissions found</h3>
                    <p class="text-gray-600 dark:text-gray-400">
                        Try adjusting your search or filter criteria.
                    </p>
                </div>

                <!-- Form Errors -->
                <InputError :message="form.errors.permissions" />

                <!-- Form Actions -->
                <div class="flex items-center justify-between pt-6 border-t border-gray-200 dark:border-gray-600">
                    <div class="text-sm text-gray-600 dark:text-gray-400">
                        {{ permissionStats.selected }} permission{{ permissionStats.selected === 1 ? '' : 's' }} selected
                    </div>
                    
                    <div class="flex items-center space-x-3">
                        <SecondaryButton @click="$inertia.visit(route('roles.show', role.id))" type="button">
                            Cancel
                        </SecondaryButton>
                        
                        <PrimaryButton 
                            :class="{ 'opacity-25': form.processing }" 
                            :disabled="form.processing"
                            type="submit"
                        >
                            <i class="fas fa-save mr-2"></i>
                            Save Permissions
                        </PrimaryButton>
                    </div>
                </div>
            </form>
        </div>
    </AppLayout>
</template>