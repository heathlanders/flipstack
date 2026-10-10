<script setup>
import { useForm, Head, Link } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { route } from 'ziggy-js'

const props = defineProps({
    packagePresets: Array,
    carriers: Array,
})

const form = useForm({
    name: '',
    priority: 0,
    conditions: {
        weight_min: null,
        weight_max: null,
        value_min: null,
        value_max: null,
        destination_states: [],
        destination_countries: [],
        marketplace: null,
        item_count_min: null,
        item_count_max: null,
    },
    action_carrier: 'USPS',
    action_service: '',
    action_package_preset_id: null,
    is_active: true,
})

const statesInput = defineModel('statesInput', { default: '' })
const countriesInput = defineModel('countriesInput', { default: '' })

function parseCommaSeparated(value) {
    if (!value) return []
    return value.split(',').map(s => s.trim()).filter(s => s.length > 0)
}

function submit() {
    form.conditions.destination_states = parseCommaSeparated(statesInput.value)
    form.conditions.destination_countries = parseCommaSeparated(countriesInput.value)

    form.post(route('fulfillment.shipping-rules.store'))
}
</script>

<template>
    <AppLayout title="Create Shipping Rule">
        <Head title="Create Shipping Rule" />

        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
            <div class="mb-6">
                <Link :href="route('fulfillment.shipping-rules.index')" class="text-sm text-indigo-600 hover:text-indigo-800 dark:text-indigo-400">
                    &larr; Back to Shipping Rules
                </Link>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100 mt-2">Create Shipping Rule</h1>
            </div>

            <form @submit.prevent="submit" class="space-y-6">
                <!-- Basic Info -->
                <div class="bg-white dark:bg-gray-800 rounded-lg border dark:border-gray-700 p-6">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">Basic Information</h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Rule Name</label>
                            <input
                                v-model="form.name"
                                type="text"
                                class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 text-sm"
                                placeholder="e.g., Heavy items via UPS Ground"
                            />
                            <p v-if="form.errors.name" class="mt-1 text-sm text-red-600">{{ form.errors.name }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Priority</label>
                            <input
                                v-model.number="form.priority"
                                type="number"
                                min="0"
                                class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 text-sm"
                            />
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Lower number = higher priority. Rules are evaluated in order.</p>
                            <p v-if="form.errors.priority" class="mt-1 text-sm text-red-600">{{ form.errors.priority }}</p>
                        </div>
                    </div>
                </div>

                <!-- Conditions -->
                <div class="bg-white dark:bg-gray-800 rounded-lg border dark:border-gray-700 p-6">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-1">Conditions</h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">Leave fields empty to skip that condition. All specified conditions must match for the rule to apply.</p>

                    <div class="space-y-4">
                        <!-- Weight -->
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Min Weight (oz)</label>
                                <input
                                    v-model.number="form.conditions.weight_min"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 text-sm"
                                    placeholder="No minimum"
                                />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Max Weight (oz)</label>
                                <input
                                    v-model.number="form.conditions.weight_max"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 text-sm"
                                    placeholder="No maximum"
                                />
                            </div>
                        </div>

                        <!-- Value -->
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Min Order Value ($)</label>
                                <input
                                    v-model.number="form.conditions.value_min"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 text-sm"
                                    placeholder="No minimum"
                                />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Max Order Value ($)</label>
                                <input
                                    v-model.number="form.conditions.value_max"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 text-sm"
                                    placeholder="No maximum"
                                />
                            </div>
                        </div>

                        <!-- Destination -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Destination States</label>
                                <input
                                    v-model="statesInput"
                                    type="text"
                                    class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 text-sm"
                                    placeholder="CA, NY, TX (comma-separated)"
                                />
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Leave empty to match all states.</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Destination Countries</label>
                                <input
                                    v-model="countriesInput"
                                    type="text"
                                    class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 text-sm"
                                    placeholder="US, CA, GB (comma-separated)"
                                />
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Leave empty to match all countries.</p>
                            </div>
                        </div>

                        <!-- Marketplace -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Marketplace</label>
                            <select
                                v-model="form.conditions.marketplace"
                                class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 text-sm"
                            >
                                <option :value="null">Any marketplace</option>
                                <option value="ebay">eBay</option>
                                <option value="shopify">Shopify</option>
                                <option value="amazon">Amazon</option>
                                <option value="manual">Manual</option>
                            </select>
                        </div>

                        <!-- Item Count -->
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Min Item Count</label>
                                <input
                                    v-model.number="form.conditions.item_count_min"
                                    type="number"
                                    min="0"
                                    class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 text-sm"
                                    placeholder="No minimum"
                                />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Max Item Count</label>
                                <input
                                    v-model.number="form.conditions.item_count_max"
                                    type="number"
                                    min="0"
                                    class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 text-sm"
                                    placeholder="No maximum"
                                />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Action -->
                <div class="bg-white dark:bg-gray-800 rounded-lg border dark:border-gray-700 p-6">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">Action</h2>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Carrier</label>
                            <select
                                v-model="form.action_carrier"
                                class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 text-sm"
                            >
                                <option v-for="carrier in carriers" :key="carrier" :value="carrier">{{ carrier }}</option>
                            </select>
                            <p v-if="form.errors.action_carrier" class="mt-1 text-sm text-red-600">{{ form.errors.action_carrier }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Service</label>
                            <input
                                v-model="form.action_service"
                                type="text"
                                class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 text-sm"
                                placeholder="e.g., Priority, Ground, Express"
                            />
                            <p v-if="form.errors.action_service" class="mt-1 text-sm text-red-600">{{ form.errors.action_service }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Package Preset (optional)</label>
                            <select
                                v-model="form.action_package_preset_id"
                                class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 text-sm"
                            >
                                <option :value="null">-- None --</option>
                                <option v-for="preset in packagePresets" :key="preset.id" :value="preset.id">
                                    {{ preset.name }}
                                </option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Active Toggle & Submit -->
                <div class="bg-white dark:bg-gray-800 rounded-lg border dark:border-gray-700 p-6">
                    <div class="flex items-center justify-between">
                        <label class="flex items-center gap-2">
                            <input
                                v-model="form.is_active"
                                type="checkbox"
                                class="rounded border-gray-300 text-indigo-600 dark:border-gray-600 dark:bg-gray-700"
                            />
                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Rule is active</span>
                        </label>
                        <div class="flex items-center gap-3">
                            <Link
                                :href="route('fulfillment.shipping-rules.index')"
                                class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 hover:text-gray-900 dark:hover:text-gray-100"
                            >
                                Cancel
                            </Link>
                            <button
                                type="submit"
                                :disabled="form.processing"
                                class="px-6 py-2 bg-indigo-600 text-white text-sm font-medium rounded-md hover:bg-indigo-700 disabled:opacity-50 transition-colors"
                            >
                                {{ form.processing ? 'Saving...' : 'Create Rule' }}
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
