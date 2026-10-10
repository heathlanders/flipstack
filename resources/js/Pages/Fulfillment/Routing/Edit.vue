<script setup>
import { computed } from 'vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

const props = defineProps({
    rule: Object,
    locations: Array,
    ruleTypes: Array,
})

const typeLabels = {
    proximity: 'Nearest Warehouse',
    lowest_cost: 'Lowest Shipping Cost',
    highest_stock: 'Highest Stock',
    round_robin: 'Round Robin',
    specific_location: 'Fixed Location',
}

const typeDescriptions = {
    proximity: 'Routes orders to the warehouse nearest to the shipping address by comparing zip code prefixes (first 3 digits).',
    lowest_cost: 'Routes orders to the warehouse with the lowest estimated shipping cost based on proximity.',
    highest_stock: 'Routes orders to the warehouse with the most available stock for the ordered items.',
    round_robin: 'Distributes orders evenly across warehouses based on recent order counts.',
    specific_location: 'Always routes orders to a specific warehouse location.',
}

const form = useForm({
    name: props.rule.name,
    rule_type: props.rule.rule_type,
    priority: props.rule.priority,
    is_active: props.rule.is_active,
    parameters: props.rule.parameters || {},
})

const showLocationSelect = computed(() => form.rule_type === 'specific_location')

function setParameter(key, value) {
    form.parameters = { ...form.parameters, [key]: value }
}

function submit() {
    form.put(route('fulfillment.routing.update', props.rule.id))
}
</script>

<template>
    <AppLayout title="Edit Routing Rule">
        <Head title="Edit Routing Rule" />

        <div class="max-w-3xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
            <div class="mb-6">
                <Link
                    :href="route('fulfillment.routing.index')"
                    class="text-sm text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-300"
                >
                    &larr; Back to Routing Rules
                </Link>
                <h1 class="mt-2 text-2xl font-semibold text-gray-900 dark:text-gray-100">Edit Routing Rule</h1>
            </div>

            <form @submit.prevent="submit" class="bg-white dark:bg-gray-800 shadow rounded-lg p-6 space-y-6">
                <!-- Name -->
                <div>
                    <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Rule Name
                    </label>
                    <input
                        id="name"
                        v-model="form.name"
                        type="text"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100 sm:text-sm"
                        placeholder="e.g. Route to nearest warehouse"
                    />
                    <p v-if="form.errors.name" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ form.errors.name }}</p>
                </div>

                <!-- Rule Type -->
                <div>
                    <label for="rule_type" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Rule Type
                    </label>
                    <select
                        id="rule_type"
                        v-model="form.rule_type"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100 sm:text-sm"
                    >
                        <option v-for="ruleType in ruleTypes" :key="ruleType" :value="ruleType">
                            {{ typeLabels[ruleType] || ruleType }}
                        </option>
                    </select>
                    <p v-if="form.errors.rule_type" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ form.errors.rule_type }}</p>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        {{ typeDescriptions[form.rule_type] }}
                    </p>
                </div>

                <!-- Priority -->
                <div>
                    <label for="priority" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Priority
                    </label>
                    <input
                        id="priority"
                        v-model.number="form.priority"
                        type="number"
                        min="0"
                        class="mt-1 block w-32 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100 sm:text-sm"
                    />
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Lower numbers are evaluated first. Rules are checked in priority order until one matches.
                    </p>
                    <p v-if="form.errors.priority" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ form.errors.priority }}</p>
                </div>

                <!-- Active -->
                <div class="flex items-center">
                    <input
                        id="is_active"
                        v-model="form.is_active"
                        type="checkbox"
                        class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-700"
                    />
                    <label for="is_active" class="ml-2 block text-sm text-gray-700 dark:text-gray-300">
                        Active
                    </label>
                </div>

                <!-- Parameters: Specific Location -->
                <div v-if="showLocationSelect">
                    <label for="location_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Warehouse Location
                    </label>
                    <select
                        id="location_id"
                        :value="form.parameters.location_id || ''"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100 sm:text-sm"
                        @change="setParameter('location_id', $event.target.value)"
                    >
                        <option value="">-- Select a location --</option>
                        <option v-for="loc in locations" :key="loc.id" :value="loc.id">
                            {{ loc.name }} ({{ loc.code }}) - {{ loc.city }}, {{ loc.state }} {{ loc.postal_code }}
                        </option>
                    </select>
                    <p v-if="form.errors['parameters.location_id']" class="mt-1 text-sm text-red-600 dark:text-red-400">
                        {{ form.errors['parameters.location_id'] }}
                    </p>
                </div>

                <!-- Parameters: Info text for other types -->
                <div v-if="!showLocationSelect" class="rounded-md bg-blue-50 dark:bg-blue-900/30 p-4">
                    <p class="text-sm text-blue-700 dark:text-blue-300">
                        This rule type works automatically based on your warehouse locations and order data. No additional configuration is needed.
                    </p>
                </div>

                <!-- Submit -->
                <div class="flex items-center justify-end space-x-3 pt-4 border-t border-gray-200 dark:border-gray-700">
                    <Link
                        :href="route('fulfillment.routing.index')"
                        class="inline-flex items-center px-4 py-2 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md font-semibold text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest hover:bg-gray-50 dark:hover:bg-gray-600 transition ease-in-out duration-150"
                    >
                        Cancel
                    </Link>
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-500 active:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150 disabled:opacity-50"
                    >
                        Update Rule
                    </button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
