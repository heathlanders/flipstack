<script setup>
import { ref, reactive } from 'vue'
import { router, Head, Link } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

const props = defineProps({
    types: {
        type: Object,
        default: () => ({})
    },
    triggers: {
        type: Object,
        default: () => ({})
    },
    availableVariables: {
        type: Object,
        default: () => ({})
    }
})

const form = reactive({
    name: '',
    type: 'post_purchase_followup',
    trigger_event: 'order_delivered',
    trigger_delay_hours: 24,
    subject_template: '',
    body_template: '',
    is_active: false,
})

const errors = ref({})
const processing = ref(false)

function formatVariable(variable) {
    return '{{' + variable + '}}'
}

function insertVariable(variable, target) {
    const tag = '{{' + variable + '}}'
    if (target === 'subject') {
        form.subject_template += tag
    } else {
        form.body_template += tag
    }
}

function submit() {
    processing.value = true
    router.post(route('marketing.campaigns.store'), form, {
        onError: (errs) => {
            errors.value = errs
        },
        onFinish: () => {
            processing.value = false
        }
    })
}
</script>

<template>
    <AppLayout title="Create Campaign">
        <Head title="Create Email Campaign" />

        <div class="max-w-4xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Create Email Campaign</h1>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Set up an automated email campaign for your customers</p>
                </div>
                <Link
                    :href="route('marketing.campaigns.index')"
                    class="text-sm text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-200"
                >
                    Back to Campaigns
                </Link>
            </div>

            <form @submit.prevent="submit" class="space-y-6">
                <!-- Basic Info -->
                <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Campaign Details</h2>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Campaign Name</label>
                            <input
                                v-model="form.name"
                                type="text"
                                class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                placeholder="e.g., Post-Purchase Thank You"
                            />
                            <p v-if="errors.name" class="mt-1 text-sm text-red-600">{{ errors.name }}</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Type</label>
                            <select
                                v-model="form.type"
                                class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            >
                                <option v-for="(label, key) in types" :key="key" :value="key">{{ label }}</option>
                            </select>
                            <p v-if="errors.type" class="mt-1 text-sm text-red-600">{{ errors.type }}</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Trigger Event</label>
                            <select
                                v-model="form.trigger_event"
                                class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            >
                                <option v-for="(label, key) in triggers" :key="key" :value="key">{{ label }}</option>
                            </select>
                            <p v-if="errors.trigger_event" class="mt-1 text-sm text-red-600">{{ errors.trigger_event }}</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Delay (hours)</label>
                            <input
                                v-model.number="form.trigger_delay_hours"
                                type="number"
                                min="0"
                                max="8760"
                                class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            />
                            <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">Hours after trigger event before sending</p>
                            <p v-if="errors.trigger_delay_hours" class="mt-1 text-sm text-red-600">{{ errors.trigger_delay_hours }}</p>
                        </div>

                        <div class="flex items-center">
                            <label class="flex items-center cursor-pointer">
                                <input
                                    v-model="form.is_active"
                                    type="checkbox"
                                    class="rounded border-gray-300 dark:border-gray-600 text-indigo-600 shadow-sm focus:ring-indigo-500"
                                />
                                <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Activate campaign immediately</span>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Template Section -->
                <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Email Template</h2>

                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Subject Line</label>
                            <input
                                v-model="form.subject_template"
                                type="text"
                                class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                placeholder="e.g., Thanks for your order, {{customer_first_name}}!"
                            />
                            <p v-if="errors.subject_template" class="mt-1 text-sm text-red-600">{{ errors.subject_template }}</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Body</label>
                            <textarea
                                v-model="form.body_template"
                                rows="10"
                                class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 font-mono text-sm"
                                placeholder="Write your email body here. Use {{variable}} placeholders from the panel below."
                            ></textarea>
                            <p v-if="errors.body_template" class="mt-1 text-sm text-red-600">{{ errors.body_template }}</p>
                        </div>
                    </div>
                </div>

                <!-- Available Variables Panel -->
                <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Available Variables</h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mb-3">Click a variable to insert it into the subject or body.</p>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2">
                        <div
                            v-for="(description, variable) in availableVariables"
                            :key="variable"
                            class="border border-gray-200 dark:border-gray-600 rounded-md p-3"
                        >
                            <div class="flex items-center justify-between mb-1">
                                <code class="text-sm font-mono text-indigo-600 dark:text-indigo-400" v-text="formatVariable(variable)"></code>
                            </div>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mb-2">{{ description }}</p>
                            <div class="flex space-x-2">
                                <button
                                    type="button"
                                    @click="insertVariable(variable, 'subject')"
                                    class="text-xs px-2 py-1 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded hover:bg-gray-200 dark:hover:bg-gray-600"
                                >
                                    + Subject
                                </button>
                                <button
                                    type="button"
                                    @click="insertVariable(variable, 'body')"
                                    class="text-xs px-2 py-1 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded hover:bg-gray-200 dark:hover:bg-gray-600"
                                >
                                    + Body
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Submit -->
                <div class="flex items-center justify-end space-x-3">
                    <Link
                        :href="route('marketing.campaigns.index')"
                        class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700"
                    >
                        Cancel
                    </Link>
                    <button
                        type="submit"
                        :disabled="processing"
                        class="px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition disabled:opacity-50"
                    >
                        {{ processing ? 'Creating...' : 'Create Campaign' }}
                    </button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
