<template>
    <AppLayout :title="template.name">
        <template #header>
            <div class="flex justify-between items-center">
                <div class="flex items-center space-x-4">
                    <Link :href="route('inventory.templates.index')" class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">
                        <i class="fas fa-arrow-left"></i>
                    </Link>
                    <div>
                        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">{{ template.name }}</h2>
                        <div class="flex items-center space-x-2 mt-1">
                            <span class="px-2 py-0.5 text-xs font-medium rounded bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300 capitalize">
                                {{ template.platform }}
                            </span>
                            <span class="text-xs text-gray-500 dark:text-gray-400">Used in {{ template.product_listings_count || 0 }} listings</span>
                        </div>
                    </div>
                </div>
                <div class="flex space-x-3">
                    <Link
                        :href="route('inventory.templates.edit', template.id)"
                        class="inline-flex items-center px-4 py-2 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md font-semibold text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest hover:bg-gray-50 dark:hover:bg-gray-600"
                    >
                        <i class="fas fa-edit mr-2"></i>Edit
                    </Link>
                    <button
                        type="button"
                        @click="confirmDelete"
                        class="inline-flex items-center px-4 py-2 bg-red-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-red-700"
                    >
                        <i class="fas fa-trash mr-2"></i>Delete
                    </button>
                </div>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">
                <!-- Description -->
                <div v-if="template.description" class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">Description</h3>
                    <p class="text-sm text-gray-700 dark:text-gray-300 whitespace-pre-wrap">{{ template.description }}</p>
                </div>

                <!-- Pricing Strategy -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Pricing Strategy</h3>
                    <dl class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
                        <div>
                            <dt class="text-gray-500 dark:text-gray-400">Strategy</dt>
                            <dd class="text-gray-900 dark:text-white capitalize">{{ (template.pricing_strategy || '—').replace('_', ' ') }}</dd>
                        </div>
                        <div v-if="template.markup_percentage != null">
                            <dt class="text-gray-500 dark:text-gray-400">Markup %</dt>
                            <dd class="text-gray-900 dark:text-white">{{ template.markup_percentage }}%</dd>
                        </div>
                        <div v-if="template.fixed_markup_amount != null">
                            <dt class="text-gray-500 dark:text-gray-400">Fixed Markup</dt>
                            <dd class="text-gray-900 dark:text-white">${{ template.fixed_markup_amount }}</dd>
                        </div>
                    </dl>
                </div>

                <!-- Listing Description Template -->
                <div v-if="template.description_template" class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">Listing Description Template</h3>
                    <pre class="text-sm text-gray-700 dark:text-gray-300 bg-gray-50 dark:bg-gray-900 rounded p-4 whitespace-pre-wrap font-sans">{{ template.description_template }}</pre>
                </div>

                <!-- Item Specifics -->
                <div v-if="hasObject(template.default_item_specifics)" class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Default Item Specifics</h3>
                    <dl class="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
                        <div v-for="(val, key) in template.default_item_specifics" :key="key" class="flex justify-between p-2 bg-gray-50 dark:bg-gray-900 rounded">
                            <dt class="text-gray-500 dark:text-gray-400 font-medium">{{ key }}</dt>
                            <dd class="text-gray-900 dark:text-white">{{ Array.isArray(val) ? val.join(', ') : val }}</dd>
                        </div>
                    </dl>
                </div>

                <!-- JSON sections -->
                <div v-for="section in jsonSections" :key="section.key">
                    <div v-if="hasObject(template[section.key])" class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">{{ section.label }}</h3>
                        <pre class="text-xs text-gray-700 dark:text-gray-300 bg-gray-50 dark:bg-gray-900 rounded p-4 overflow-x-auto">{{ JSON.stringify(template[section.key], null, 2) }}</pre>
                    </div>
                </div>

                <!-- Metadata -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6">
                    <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wider mb-3">Metadata</h3>
                    <dl class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
                        <div>
                            <dt class="text-gray-500 dark:text-gray-400">Created By</dt>
                            <dd class="text-gray-900 dark:text-white">{{ template.creator?.name || '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500 dark:text-gray-400">Created</dt>
                            <dd class="text-gray-900 dark:text-white">{{ formatDate(template.created_at) }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500 dark:text-gray-400">Updated</dt>
                            <dd class="text-gray-900 dark:text-white">{{ formatDate(template.updated_at) }}</dd>
                        </div>
                    </dl>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { Link, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

const props = defineProps({
    template: { type: Object, required: true },
})

const jsonSections = [
    { key: 'shipping_template', label: 'Shipping Policy' },
    { key: 'return_policy_template', label: 'Return Policy' },
    { key: 'payment_template', label: 'Payment Policy' },
    { key: 'default_categories', label: 'Default Categories' },
]

const hasObject = (obj) => obj && typeof obj === 'object' && Object.keys(obj).length > 0

const formatDate = (date) => {
    if (!date) return '—'
    return new Date(date).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' })
}

const confirmDelete = () => {
    if (confirm(`Delete template "${props.template.name}"?`)) {
        router.delete(route('inventory.templates.destroy', props.template.id))
    }
}
</script>
