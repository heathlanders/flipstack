<template>
    <Modal :show="show" max-width="2xl" @close="$emit('close')">
        <form @submit.prevent="submit">
            <div class="px-6 py-4">
                <h3 class="text-lg font-semibold leading-6 text-gray-900 dark:text-white mb-4">
                    {{ mode === 'create' ? 'Create New Location' : 'Edit Location' }}
                </h3>

                <div class="space-y-4">
                    <!-- Basic Information -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Name <span class="text-red-500">*</span>
                            </label>
                            <input
                                v-model="form.name"
                                type="text"
                                class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                :class="{ 'border-red-500': form.errors.name }"
                                required
                            />
                            <p v-if="form.errors.name" class="mt-1 text-sm text-red-500">{{ form.errors.name }}</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Code <span class="text-red-500">*</span>
                            </label>
                            <input
                                v-model="form.code"
                                type="text"
                                class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                :class="{ 'border-red-500': form.errors.code }"
                                maxlength="50"
                                required
                            />
                            <p v-if="form.errors.code" class="mt-1 text-sm text-red-500">{{ form.errors.code }}</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Type <span class="text-red-500">*</span>
                            </label>
                            <select
                                v-model="form.type"
                                class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                required
                            >
                                <option value="">Select Type</option>
                                <option v-for="(label, value) in types" :key="value" :value="value">
                                    {{ label }}
                                </option>
                            </select>
                            <p v-if="form.errors.type" class="mt-1 text-sm text-red-500">{{ form.errors.type }}</p>
                        </div>

                        <div class="flex items-center space-x-6 pt-6">
                            <label class="flex items-center">
                                <input
                                    v-model="form.is_active"
                                    type="checkbox"
                                    class="rounded border-gray-300 text-blue-600 shadow-sm focus:ring-blue-500"
                                />
                                <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Active</span>
                            </label>

                            <label class="flex items-center">
                                <input
                                    v-model="form.is_default"
                                    type="checkbox"
                                    class="rounded border-gray-300 text-blue-600 shadow-sm focus:ring-blue-500"
                                />
                                <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Default Location</span>
                            </label>
                        </div>
                    </div>

                    <!-- Address Section -->
                    <div class="border-t border-gray-200 dark:border-gray-700 pt-4 mt-4">
                        <h4 class="text-sm font-medium text-gray-900 dark:text-white mb-3">Address</h4>
                        <div class="space-y-3">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                    Address Line 1
                                </label>
                                <input
                                    v-model="form.address_line1"
                                    type="text"
                                    class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                />
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                    Address Line 2
                                </label>
                                <input
                                    v-model="form.address_line2"
                                    type="text"
                                    class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                />
                            </div>

                            <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                        City
                                    </label>
                                    <input
                                        v-model="form.city"
                                        type="text"
                                        class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                    />
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                        State
                                    </label>
                                    <input
                                        v-model="form.state"
                                        type="text"
                                        class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                    />
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                        Postal Code
                                    </label>
                                    <input
                                        v-model="form.postal_code"
                                        type="text"
                                        class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                    />
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                        Country
                                    </label>
                                    <input
                                        v-model="form.country"
                                        type="text"
                                        class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Contact Section -->
                    <div class="border-t border-gray-200 dark:border-gray-700 pt-4 mt-4">
                        <h4 class="text-sm font-medium text-gray-900 dark:text-white mb-3">Contact Information</h4>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                    Contact Person
                                </label>
                                <input
                                    v-model="form.contact_person"
                                    type="text"
                                    class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                />
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                    Email
                                </label>
                                <input
                                    v-model="form.email"
                                    type="email"
                                    class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                />
                                <p v-if="form.errors.email" class="mt-1 text-sm text-red-500">{{ form.errors.email }}</p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                    Phone
                                </label>
                                <input
                                    v-model="form.phone"
                                    type="text"
                                    class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                />
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex justify-end space-x-3 px-6 py-4 bg-gray-100 dark:bg-gray-800">
                <button
                    type="button"
                    class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                    @click="$emit('close')"
                >
                    Cancel
                </button>
                <button
                    type="submit"
                    class="px-4 py-2 text-sm font-medium text-white bg-blue-600 border border-transparent rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                    :disabled="form.processing"
                >
                    <span v-if="form.processing">
                        <i class="fas fa-spinner fa-spin mr-2"></i>
                        Saving...
                    </span>
                    <span v-else>
                        {{ mode === 'create' ? 'Create Location' : 'Update Location' }}
                    </span>
                </button>
            </div>
        </form>
    </Modal>
</template>

<script setup>
import { ref, watch } from 'vue'
import { useForm } from '@inertiajs/vue3'
import Modal from '@/Components/Modal.vue'

const props = defineProps({
    show: {
        type: Boolean,
        default: false
    },
    location: {
        type: Object,
        default: null
    },
    types: {
        type: Object,
        default: () => ({})
    },
    mode: {
        type: String,
        default: 'create'
    }
})

const emit = defineEmits(['close', 'saved'])

const defaultFormData = {
    name: '',
    code: '',
    type: 'warehouse',
    address_line1: '',
    address_line2: '',
    city: '',
    state: '',
    postal_code: '',
    country: '',
    contact_person: '',
    email: '',
    phone: '',
    is_active: true,
    is_default: false
}

const form = useForm({ ...defaultFormData })

const resetForm = () => {
    form.reset()
    form.clearErrors()
    Object.assign(form, defaultFormData)
}

const populateForm = (location) => {
    if (location) {
        form.name = location.name || ''
        form.code = location.code || ''
        form.type = location.type || 'warehouse'
        form.address_line1 = location.address_line1 || ''
        form.address_line2 = location.address_line2 || ''
        form.city = location.city || ''
        form.state = location.state || ''
        form.postal_code = location.postal_code || ''
        form.country = location.country || ''
        form.contact_person = location.contact_person || ''
        form.email = location.email || ''
        form.phone = location.phone || ''
        form.is_active = location.is_active !== undefined ? location.is_active : true
        form.is_default = location.is_default || false
    }
}

watch(() => props.show, (newVal) => {
    if (newVal) {
        if (props.mode === 'edit' && props.location) {
            populateForm(props.location)
        } else {
            resetForm()
        }
    }
})

watch(() => props.location, (newVal) => {
    if (props.show && props.mode === 'edit' && newVal) {
        populateForm(newVal)
    }
}, { deep: true })

const submit = () => {
    if (props.mode === 'create') {
        form.post(route('inventory.locations.store'), {
            preserveScroll: true,
            onSuccess: () => {
                resetForm()
                emit('saved')
            }
        })
    } else {
        form.put(route('inventory.locations.update', props.location.id), {
            preserveScroll: true,
            onSuccess: () => {
                emit('saved')
            }
        })
    }
}
</script>
