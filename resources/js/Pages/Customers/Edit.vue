<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { route } from 'ziggy-js'

const props = defineProps({
    customer: { type: Object, required: true },
})

const form = useForm({
    name: props.customer.name || '',
    email: props.customer.email || '',
    phone: props.customer.phone || '',
    notes: props.customer.notes || '',
    is_flagged: props.customer.is_flagged || false,
    flag_reason: props.customer.flag_reason || '',
    tags: props.customer.tags || [],
})

const newTag = $ref('')

function addTag() {
    if (newTag && !form.tags.includes(newTag)) {
        form.tags.push(newTag)
        newTag = ''
    }
}

function removeTag(index) {
    form.tags.splice(index, 1)
}

function submit() {
    form.put(route('customers.update', props.customer.id))
}
</script>

<template>
    <Head :title="`Edit ${customer.name}`" />
    <AppLayout>
        <template #header>
            <div class="flex items-center gap-4">
                <Link :href="route('customers.show', customer.id)" class="text-gray-500 dark:text-gray-400 hover:text-gray-700">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                </Link>
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200">Edit {{ customer.name }}</h2>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
                <form @submit.prevent="submit" class="space-y-6">
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Customer Details</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Name</label>
                                <input v-model="form.name" type="text" required class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-sm" />
                                <p v-if="form.errors.name" class="mt-1 text-sm text-red-600">{{ form.errors.name }}</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Email</label>
                                <input v-model="form.email" type="email" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-sm" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Phone</label>
                                <input v-model="form.phone" type="text" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-sm" />
                            </div>
                        </div>
                        <div class="mt-4">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Notes</label>
                            <textarea v-model="form.notes" rows="3" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-sm" />
                        </div>
                    </div>

                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Flagging</h3>
                        <label class="flex items-center gap-2 mb-3">
                            <input v-model="form.is_flagged" type="checkbox" class="rounded border-gray-300 dark:border-gray-600" />
                            <span class="text-sm text-gray-700 dark:text-gray-300">Flag this customer</span>
                        </label>
                        <div v-if="form.is_flagged">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Reason</label>
                            <input v-model="form.flag_reason" type="text" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-sm" />
                        </div>
                    </div>

                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Tags</h3>
                        <div class="flex flex-wrap gap-2 mb-3">
                            <span v-for="(tag, idx) in form.tags" :key="idx" class="inline-flex items-center gap-1 px-2 py-1 text-xs rounded-full bg-indigo-100 dark:bg-indigo-900 text-indigo-700 dark:text-indigo-300">
                                {{ tag }}
                                <button type="button" @click="removeTag(idx)" class="hover:text-red-500">x</button>
                            </span>
                        </div>
                        <div class="flex gap-2">
                            <input v-model="newTag" type="text" placeholder="Add tag..." @keydown.enter.prevent="addTag" class="flex-1 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-sm" />
                            <button type="button" @click="addTag" class="px-3 py-2 text-sm text-indigo-600 hover:text-indigo-800 font-medium">Add</button>
                        </div>
                    </div>

                    <div class="flex justify-end gap-3">
                        <Link :href="route('customers.show', customer.id)" class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50">Cancel</Link>
                        <button type="submit" :disabled="form.processing" class="px-4 py-2 text-sm font-medium text-white bg-indigo-600 rounded-md hover:bg-indigo-500 disabled:opacity-50">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
