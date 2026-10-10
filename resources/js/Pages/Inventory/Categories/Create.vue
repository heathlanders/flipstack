<template>
    <AppLayout title="Create Category">
        <template #header>
            <div class="flex items-center space-x-4">
                <Link :href="route('inventory.categories.index')" class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">
                    <i class="fas fa-arrow-left"></i>
                </Link>
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Create Category</h2>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm">
                    <form @submit.prevent="submit" class="p-6 space-y-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="md:col-span-2">
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                    Name <span class="text-red-500">*</span>
                                </label>
                                <input
                                    v-model="form.name"
                                    type="text"
                                    required
                                    maxlength="255"
                                    class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                    :class="{ 'border-red-500': form.errors.name }"
                                />
                                <p v-if="form.errors.name" class="mt-1 text-sm text-red-500">{{ form.errors.name }}</p>
                            </div>

                            <div class="md:col-span-2">
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Description</label>
                                <textarea
                                    v-model="form.description"
                                    rows="3"
                                    class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                ></textarea>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Parent Category</label>
                                <select
                                    v-model="form.parent_id"
                                    class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                >
                                    <option :value="null">— Root Category —</option>
                                    <option v-for="opt in parentOptions" :key="opt.id" :value="opt.id">
                                        {{ opt.name }}
                                    </option>
                                </select>
                                <p v-if="form.errors.parent_id" class="mt-1 text-sm text-red-500">{{ form.errors.parent_id }}</p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Color</label>
                                <div class="flex items-center space-x-2">
                                    <input
                                        v-model="form.color"
                                        type="color"
                                        class="h-10 w-14 rounded border-gray-300 dark:border-gray-600 cursor-pointer"
                                    />
                                    <input
                                        v-model="form.color"
                                        type="text"
                                        maxlength="7"
                                        placeholder="#6B7280"
                                        class="flex-1 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 shadow-sm focus:border-blue-500 focus:ring-blue-500 font-mono text-sm"
                                    />
                                </div>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Icon (Font Awesome class)</label>
                                <input
                                    v-model="form.icon"
                                    type="text"
                                    maxlength="100"
                                    placeholder="fas fa-tag"
                                    class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 shadow-sm focus:border-blue-500 focus:ring-blue-500 font-mono text-sm"
                                />
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Image URL</label>
                                <input
                                    v-model="form.image"
                                    type="url"
                                    maxlength="500"
                                    class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                />
                            </div>

                            <div class="md:col-span-2 flex items-center pt-2">
                                <label class="flex items-center">
                                    <input v-model="form.is_active" type="checkbox" class="rounded border-gray-300 text-blue-600 shadow-sm focus:ring-blue-500" />
                                    <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Active</span>
                                </label>
                            </div>
                        </div>

                        <!-- Actions -->
                        <div class="flex justify-end space-x-3 border-t border-gray-200 dark:border-gray-700 pt-6">
                            <Link
                                :href="route('inventory.categories.index')"
                                class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-gray-600"
                            >
                                Cancel
                            </Link>
                            <button
                                type="submit"
                                :disabled="form.processing"
                                class="px-4 py-2 text-sm font-medium text-white bg-blue-600 border border-transparent rounded-md hover:bg-blue-700 disabled:opacity-50"
                            >
                                <span v-if="form.processing"><i class="fas fa-spinner fa-spin mr-2"></i>Creating...</span>
                                <span v-else>Create Category</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { useForm, Link } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

defineProps({
    parentOptions: { type: Array, default: () => [] },
})

const form = useForm({
    name: '',
    description: '',
    parent_id: null,
    image: '',
    color: '#6B7280',
    icon: '',
    is_active: true,
})

const submit = () => {
    form.post(route('inventory.categories.store'))
}
</script>
