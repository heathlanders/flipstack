<template>
    <DialogModal :show="show" max-width="3xl" @close="$emit('close')">
        <template #title>
            <div class="flex items-center">
                <div class="w-8 h-8 rounded-lg bg-indigo-100 dark:bg-indigo-900/30 flex items-center justify-center mr-3">
                    <svg class="w-5 h-5 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path v-if="mode === 'create'" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                        <path v-else stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                    </svg>
                </div>
                {{ mode === 'create' ? 'Create Category' : 'Edit Category' }}
            </div>
        </template>

        <template #content>
            <form @submit.prevent="handleSubmit" class="space-y-6">
                <!-- Basic Information -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- Name -->
                    <div class="lg:col-span-2">
                        <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Category Name *
                        </label>
                        <input
                            id="name"
                            v-model="form.name"
                            type="text"
                            required
                            maxlength="255"
                            class="block w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 placeholder-gray-500 dark:placeholder-gray-400 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-colors"
                            placeholder="Enter category name"
                            :class="{ 'border-red-500 focus:border-red-500 focus:ring-red-500': errors.name }"
                            @blur="validateName"
                        />
                        <p v-if="errors.name" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ errors.name }}</p>
                        <p v-if="form.slug" class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            Slug: {{ form.slug }}
                        </p>
                    </div>

                    <!-- Parent Category -->
                    <div>
                        <label for="parent_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Parent Category
                        </label>
                        <select
                            id="parent_id"
                            v-model="form.parent_id"
                            class="block w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-colors"
                            :class="{ 'border-red-500 focus:border-red-500 focus:ring-red-500': errors.parent_id }"
                        >
                            <option value="">Root Category</option>
                            <option 
                                v-for="parent in availableParents" 
                                :key="parent.id" 
                                :value="parent.id"
                                :disabled="parent.id === category?.id"
                            >
                                {{ parent.name }}
                            </option>
                        </select>
                        <p v-if="errors.parent_id" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ errors.parent_id }}</p>
                    </div>

                    <!-- Status -->
                    <div>
                        <label for="is_active" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Status
                        </label>
                        <div class="flex items-center space-x-3">
                            <button
                                type="button"
                                @click="form.is_active = true"
                                :class="[
                                    'flex-1 px-4 py-3 rounded-lg border text-sm font-medium transition-colors',
                                    form.is_active
                                        ? 'bg-green-50 dark:bg-green-900/30 border-green-300 dark:border-green-600 text-green-700 dark:text-green-400'
                                        : 'bg-gray-50 dark:bg-gray-700 border-gray-300 dark:border-gray-600 text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-600'
                                ]"
                            >
                                <svg class="w-4 h-4 mr-2 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                </svg>
                                Active
                            </button>
                            <button
                                type="button"
                                @click="form.is_active = false"
                                :class="[
                                    'flex-1 px-4 py-3 rounded-lg border text-sm font-medium transition-colors',
                                    !form.is_active
                                        ? 'bg-red-50 dark:bg-red-900/30 border-red-300 dark:border-red-600 text-red-700 dark:text-red-400'
                                        : 'bg-gray-50 dark:bg-gray-700 border-gray-300 dark:border-gray-600 text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-600'
                                ]"
                            >
                                <svg class="w-4 h-4 mr-2 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                                Inactive
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Description -->
                <div>
                    <label for="description" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        Description
                    </label>
                    <textarea
                        id="description"
                        v-model="form.description"
                        rows="3"
                        maxlength="1000"
                        class="block w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 placeholder-gray-500 dark:placeholder-gray-400 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-colors resize-none"
                        placeholder="Enter category description"
                        :class="{ 'border-red-500 focus:border-red-500 focus:ring-red-500': errors.description }"
                    ></textarea>
                    <div class="flex justify-between mt-1">
                        <p v-if="errors.description" class="text-sm text-red-600 dark:text-red-400">{{ errors.description }}</p>
                        <p class="text-sm text-gray-500 dark:text-gray-400 ml-auto">
                            {{ form.description?.length || 0 }}/1000
                        </p>
                    </div>
                </div>

                <!-- Visual Customization -->
                <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-6">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Visual Customization</h3>
                    
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <!-- Color -->
                        <div>
                            <label for="color" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Category Color
                            </label>
                            <div class="flex items-center space-x-3">
                                <input
                                    id="color"
                                    v-model="form.color"
                                    type="color"
                                    class="w-12 h-12 rounded-lg border border-gray-300 dark:border-gray-600 cursor-pointer"
                                />
                                <input
                                    v-model="form.color"
                                    type="text"
                                    pattern="^#[0-9A-Fa-f]{6}$"
                                    class="flex-1 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                    placeholder="#6B7280"
                                />
                            </div>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                Choose a color to represent this category
                            </p>
                        </div>

                        <!-- Icon -->
                        <div>
                            <label for="icon" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Icon
                            </label>
                            <div class="flex items-center space-x-3">
                                <div 
                                    class="w-12 h-12 rounded-lg flex items-center justify-center"
                                    :style="{ backgroundColor: form.color || '#6B7280' }"
                                >
                                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                                    </svg>
                                </div>
                                <input
                                    id="icon"
                                    v-model="form.icon"
                                    type="text"
                                    class="flex-1 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                    placeholder="folder-open"
                                />
                            </div>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                Icon name (e.g., folder-open, shopping-bag)
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Metadata -->
                <div>
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Metadata</h3>
                        <button
                            type="button"
                            @click="addMetadataField"
                            class="inline-flex items-center px-3 py-1.5 border border-transparent text-sm font-medium rounded-md text-indigo-700 dark:text-indigo-400 bg-indigo-100 dark:bg-indigo-900/30 hover:bg-indigo-200 dark:hover:bg-indigo-900/50 transition-colors"
                        >
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                            </svg>
                            Add Field
                        </button>
                    </div>

                    <div class="space-y-3">
                        <div
                            v-for="(field, index) in metadataFields"
                            :key="index"
                            class="flex items-center space-x-3"
                        >
                            <input
                                v-model="field.key"
                                type="text"
                                placeholder="Key"
                                class="flex-1 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                            />
                            <input
                                v-model="field.value"
                                type="text"
                                placeholder="Value"
                                class="flex-1 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                            />
                            <button
                                type="button"
                                @click="removeMetadataField(index)"
                                class="p-2 text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/30 rounded-lg transition-colors"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                </svg>
                            </button>
                        </div>
                        
                        <div v-if="metadataFields.length === 0" class="text-center py-6 text-gray-500 dark:text-gray-400">
                            No metadata fields added. Click "Add Field" to create custom properties.
                        </div>
                    </div>
                </div>

                <!-- Preview -->
                <div v-if="form.name" class="bg-indigo-50 dark:bg-indigo-900/20 rounded-lg p-4">
                    <h3 class="text-sm font-medium text-indigo-900 dark:text-indigo-300 mb-3">Preview</h3>
                    <div class="flex items-center space-x-3">
                        <div 
                            class="w-8 h-8 rounded-lg flex items-center justify-center"
                            :style="{ backgroundColor: form.color || '#6B7280' }"
                        >
                            <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                            </svg>
                        </div>
                        <div>
                            <div class="font-medium text-indigo-900 dark:text-indigo-300">{{ form.name }}</div>
                            <div v-if="form.description" class="text-sm text-indigo-700 dark:text-indigo-400">
                                {{ form.description.substring(0, 50) }}{{ form.description.length > 50 ? '...' : '' }}
                            </div>
                        </div>
                        <div v-if="!form.is_active" class="text-xs bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400 px-2 py-1 rounded">
                            Inactive
                        </div>
                    </div>
                </div>
            </form>
        </template>

        <template #footer>
            <div class="flex items-center justify-between w-full">
                <div>
                    <p v-if="hasErrors" class="text-sm text-red-600 dark:text-red-400">
                        Please correct the errors above before saving.
                    </p>
                </div>
                <div class="flex items-center space-x-3">
                    <SecondaryButton @click="$emit('close')">
                        Cancel
                    </SecondaryButton>
                    <PrimaryButton 
                        @click="handleSubmit" 
                        :disabled="isSubmitting || hasErrors"
                        :class="{ 'opacity-75 cursor-not-allowed': isSubmitting }"
                    >
                        <svg v-if="isSubmitting" class="animate-spin -ml-1 mr-3 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        {{ mode === 'create' ? 'Create Category' : 'Update Category' }}
                    </PrimaryButton>
                </div>
            </div>
        </template>
    </DialogModal>
</template>

<script setup>
import { ref, reactive, computed, watch, nextTick } from 'vue'
import { router } from '@inertiajs/vue3'
import DialogModal from '@/Components/DialogModal.vue'
import PrimaryButton from '@/Components/PrimaryButton.vue'
import SecondaryButton from '@/Components/SecondaryButton.vue'

const props = defineProps({
    show: {
        type: Boolean,
        default: false
    },
    category: {
        type: Object,
        default: null
    },
    parentOptions: {
        type: Array,
        default: () => []
    },
    mode: {
        type: String,
        default: 'create',
        validator: (value) => ['create', 'edit'].includes(value)
    }
})

const emit = defineEmits(['close', 'save'])

// Form state
const form = reactive({
    name: '',
    slug: '',
    description: '',
    parent_id: '',
    color: '#6B7280',
    icon: '',
    is_active: true,
    metadata: {}
})

const errors = reactive({})
const metadataFields = ref([])
const isSubmitting = ref(false)

// Computed
const availableParents = computed(() => {
    return props.parentOptions.filter(parent => parent.id !== props.category?.id)
})

const hasErrors = computed(() => {
    return Object.keys(errors).length > 0
})

// Methods
const resetForm = () => {
    Object.assign(form, {
        name: '',
        slug: '',
        description: '',
        parent_id: '',
        color: '#6B7280',
        icon: '',
        is_active: true,
        metadata: {}
    })
    Object.keys(errors).forEach(key => delete errors[key])
    metadataFields.value = []
}

const populateForm = () => {
    if (props.category && props.mode === 'edit') {
        Object.assign(form, {
            name: props.category.name || '',
            slug: props.category.slug || '',
            description: props.category.description || '',
            parent_id: props.category.parent_id || '',
            color: props.category.color || '#6B7280',
            icon: props.category.icon || '',
            is_active: props.category.is_active ?? true,
            metadata: props.category.metadata || {}
        })
        
        // Convert metadata object to array for editing
        metadataFields.value = Object.entries(form.metadata).map(([key, value]) => ({
            key,
            value: Array.isArray(value) ? value.join(', ') : String(value)
        }))
    }
}

const generateSlug = (name) => {
    return name
        .toLowerCase()
        .replace(/[^a-z0-9\s-]/g, '')
        .replace(/\s+/g, '-')
        .replace(/-+/g, '-')
        .trim('-')
}

const validateName = () => {
    delete errors.name
    
    if (!form.name.trim()) {
        errors.name = 'Category name is required'
    } else if (form.name.length > 255) {
        errors.name = 'Category name cannot exceed 255 characters'
    } else {
        form.slug = generateSlug(form.name)
    }
}

const validateForm = () => {
    Object.keys(errors).forEach(key => delete errors[key])
    
    validateName()
    
    if (form.description && form.description.length > 1000) {
        errors.description = 'Description cannot exceed 1000 characters'
    }
    
    if (form.color && !/^#[0-9A-Fa-f]{6}$/.test(form.color)) {
        errors.color = 'Please enter a valid hex color code'
    }
    
    return Object.keys(errors).length === 0
}

const addMetadataField = () => {
    metadataFields.value.push({ key: '', value: '' })
}

const removeMetadataField = (index) => {
    metadataFields.value.splice(index, 1)
}

const buildMetadataObject = () => {
    const metadata = {}
    metadataFields.value.forEach(field => {
        if (field.key && field.value) {
            // Try to parse arrays
            if (field.value.includes(',')) {
                metadata[field.key] = field.value.split(',').map(v => v.trim())
            } else {
                metadata[field.key] = field.value
            }
        }
    })
    return metadata
}

const handleSubmit = async () => {
    if (!validateForm() || isSubmitting.value) {
        return
    }
    
    isSubmitting.value = true
    
    try {
        const submitData = {
            ...form,
            metadata: buildMetadataObject()
        }
        
        if (props.mode === 'create') {
            await router.post(route('inventory.categories.store'), submitData)
        } else {
            await router.put(route('inventory.categories.update', props.category.id), submitData)
        }
        
        emit('save')
    } catch (error) {
        if (error.response?.data?.errors) {
            Object.assign(errors, error.response.data.errors)
        }
    } finally {
        isSubmitting.value = false
    }
}

// Watch for changes
watch(() => props.show, (show) => {
    if (show) {
        resetForm()
        nextTick(() => {
            populateForm()
        })
    }
})

watch(() => form.name, () => {
    if (form.name && !errors.name) {
        form.slug = generateSlug(form.name)
    }
})
</script>

<style scoped>
/* Custom styles for the modal */
.dialog-content {
    max-height: 90vh;
    overflow-y: auto;
}

/* Color picker styling */
input[type="color"] {
    -webkit-appearance: none;
    -moz-appearance: none;
    appearance: none;
    background-color: transparent;
    border: none;
    cursor: pointer;
}

input[type="color"]::-webkit-color-swatch {
    border-radius: 8px;
    border: none;
}

input[type="color"]::-moz-color-swatch {
    border-radius: 8px;
    border: none;
}

/* Smooth transitions */
.metadata-field-enter-active,
.metadata-field-leave-active {
    transition: all 0.3s ease;
}

.metadata-field-enter-from {
    opacity: 0;
    transform: translateX(-20px);
}

.metadata-field-leave-to {
    opacity: 0;
    transform: translateX(20px);
}
</style>
