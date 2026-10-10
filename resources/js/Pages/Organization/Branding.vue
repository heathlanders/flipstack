<template>
    <AppLayout title="Branding">
        <template #header>
            <div class="flex items-center space-x-4">
                <Link :href="route('organization.settings')" class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">
                    <i class="fas fa-arrow-left"></i>
                </Link>
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Branding — {{ organization.name }}</h2>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-5xl mx-auto sm:px-6 lg:px-8">
                <form @submit.prevent="submit" enctype="multipart/form-data">
                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                        <!-- Form -->
                        <div class="lg:col-span-2 space-y-6">
                            <!-- Colors -->
                            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6">
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Brand Colors</h3>
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                    <div v-for="key in ['primary_color', 'secondary_color', 'accent_color']" :key="key">
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1 capitalize">
                                            {{ key.replace('_', ' ') }}
                                        </label>
                                        <div class="flex items-center space-x-2">
                                            <input v-model="form[key]" type="color" :disabled="!canEdit" class="h-10 w-14 rounded border-gray-300 dark:border-gray-600 cursor-pointer disabled:cursor-not-allowed" />
                                            <input
                                                v-model="form[key]"
                                                type="text"
                                                pattern="^#[0-9A-Fa-f]{6}$"
                                                :disabled="!canEdit"
                                                class="flex-1 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 shadow-sm focus:border-blue-500 focus:ring-blue-500 font-mono text-sm"
                                            />
                                        </div>
                                        <p v-if="form.errors[key]" class="mt-1 text-sm text-red-500">{{ form.errors[key] }}</p>
                                    </div>
                                </div>
                            </div>

                            <!-- Image uploads -->
                            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6">
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Brand Assets</h3>
                                <div class="space-y-5">
                                    <div v-for="upload in uploads" :key="upload.field">
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                            {{ upload.label }}
                                            <span class="text-xs text-gray-400 ml-1">({{ upload.hint }})</span>
                                        </label>
                                        <div class="flex items-center space-x-3">
                                            <div v-if="branding[upload.urlKey]" class="flex-shrink-0">
                                                <img :src="assetUrl(branding[upload.urlKey])" :alt="upload.label" class="h-12 w-12 object-contain rounded border border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 p-1" />
                                            </div>
                                            <input
                                                type="file"
                                                :accept="upload.accept"
                                                :disabled="!canEdit"
                                                @change="onFileChange($event, upload.field)"
                                                class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-medium file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 dark:file:bg-blue-900/30 dark:file:text-blue-300"
                                            />
                                        </div>
                                        <p v-if="form.errors[upload.field]" class="mt-1 text-sm text-red-500">{{ form.errors[upload.field] }}</p>
                                    </div>
                                </div>
                            </div>

                            <div v-if="canEdit" class="flex justify-end">
                                <button
                                    type="submit"
                                    :disabled="form.processing"
                                    class="px-4 py-2 text-sm font-medium text-white bg-blue-600 border border-transparent rounded-md hover:bg-blue-700 disabled:opacity-50"
                                >
                                    <span v-if="form.processing"><i class="fas fa-spinner fa-spin mr-2"></i>Saving...</span>
                                    <span v-else>Save Branding</span>
                                </button>
                            </div>
                        </div>

                        <!-- Preview -->
                        <div class="space-y-6">
                            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6">
                                <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wider mb-4">Live Preview</h3>
                                <div class="space-y-3">
                                    <div class="rounded-lg p-4" :style="{ backgroundColor: form.primary_color, color: '#fff' }">
                                        <div class="text-xs uppercase tracking-wider opacity-80">Primary</div>
                                        <div class="text-lg font-semibold">{{ form.primary_color }}</div>
                                    </div>
                                    <div class="rounded-lg p-4" :style="{ backgroundColor: form.secondary_color, color: '#fff' }">
                                        <div class="text-xs uppercase tracking-wider opacity-80">Secondary</div>
                                        <div class="text-lg font-semibold">{{ form.secondary_color }}</div>
                                    </div>
                                    <div class="rounded-lg p-4" :style="{ backgroundColor: form.accent_color, color: '#fff' }">
                                        <div class="text-xs uppercase tracking-wider opacity-80">Accent</div>
                                        <div class="text-lg font-semibold">{{ form.accent_color }}</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { useForm, Link } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

const props = defineProps({
    organization: { type: Object, required: true },
    branding: { type: Object, required: true },
    can_edit: { type: Boolean, default: false },
})

const canEdit = props.can_edit

const uploads = [
    { field: 'logo', urlKey: 'logo_url', label: 'Logo', accept: 'image/png,image/jpeg,image/svg+xml', hint: 'PNG/JPG/SVG, max 2MB' },
    { field: 'favicon', urlKey: 'favicon_url', label: 'Favicon', accept: 'image/png,image/jpeg,image/x-icon', hint: 'PNG/JPG/ICO, max 1MB' },
    { field: 'login_background', urlKey: 'login_background_url', label: 'Login Background', accept: 'image/png,image/jpeg', hint: 'PNG/JPG, max 5MB' },
    { field: 'email_logo', urlKey: 'email_logo_url', label: 'Email Logo', accept: 'image/png,image/jpeg,image/svg+xml', hint: 'PNG/JPG/SVG, max 2MB' },
]

const form = useForm({
    primary_color: props.branding.primary_color || '#3B82F6',
    secondary_color: props.branding.secondary_color || '#10B981',
    accent_color: props.branding.accent_color || '#F59E0B',
    logo: null,
    favicon: null,
    login_background: null,
    email_logo: null,
    _method: 'PUT',
})

const onFileChange = (e, field) => {
    form[field] = e.target.files[0] || null
}

const assetUrl = (path) => {
    if (!path) return ''
    if (path.startsWith('http')) return path
    return `/storage/${path}`
}

const submit = () => {
    form.post(route('organization.update-branding'), { forceFormData: true })
}
</script>
