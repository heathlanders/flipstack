<script setup>
import { ref } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

const props = defineProps({
    rules: Array,
})

const typeLabels = {
    proximity: 'Nearest Warehouse',
    lowest_cost: 'Lowest Shipping Cost',
    highest_stock: 'Highest Stock',
    round_robin: 'Round Robin',
    specific_location: 'Fixed Location',
}

const confirmingDelete = ref(null)

function toggleRule(rule) {
    router.post(route('fulfillment.routing.toggle', rule.id), {}, {
        preserveScroll: true,
    })
}

function deleteRule(rule) {
    if (confirmingDelete.value === rule.id) {
        router.delete(route('fulfillment.routing.destroy', rule.id), {
            preserveScroll: true,
            onSuccess: () => {
                confirmingDelete.value = null
            },
        })
    } else {
        confirmingDelete.value = rule.id
    }
}

function cancelDelete() {
    confirmingDelete.value = null
}
</script>

<template>
    <AppLayout title="Fulfillment Routing">
        <Head title="Fulfillment Routing" />

        <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
            <!-- Header -->
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h1 class="text-2xl font-semibold text-gray-900 dark:text-gray-100">Fulfillment Routing Rules</h1>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Configure how orders are routed to warehouse locations. Rules are evaluated in priority order (lowest number first).
                    </p>
                </div>
                <Link
                    :href="route('fulfillment.routing.create')"
                    class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-500 active:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150"
                >
                    Add Routing Rule
                </Link>
            </div>

            <!-- Rules Table -->
            <div class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden">
                <table v-if="rules.length" class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-700">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                Priority
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                Name
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                Type
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                Status
                            </th>
                            <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                        <tr v-for="rule in rules" :key="rule.id">
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                                {{ rule.priority }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-gray-100">
                                {{ rule.name }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                {{ typeLabels[rule.rule_type] || rule.rule_type }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span
                                    :class="rule.is_active
                                        ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200'
                                        : 'bg-gray-100 text-gray-800 dark:bg-gray-600 dark:text-gray-300'"
                                    class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                                >
                                    {{ rule.is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium space-x-2">
                                <Link
                                    :href="route('fulfillment.routing.edit', rule.id)"
                                    class="text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-300"
                                >
                                    Edit
                                </Link>
                                <button
                                    type="button"
                                    class="text-yellow-600 hover:text-yellow-900 dark:text-yellow-400 dark:hover:text-yellow-300"
                                    @click="toggleRule(rule)"
                                >
                                    {{ rule.is_active ? 'Deactivate' : 'Activate' }}
                                </button>
                                <button
                                    v-if="confirmingDelete !== rule.id"
                                    type="button"
                                    class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300"
                                    @click="deleteRule(rule)"
                                >
                                    Delete
                                </button>
                                <span v-else class="inline-flex items-center space-x-1">
                                    <button
                                        type="button"
                                        class="text-red-700 font-semibold hover:text-red-900 dark:text-red-300"
                                        @click="deleteRule(rule)"
                                    >
                                        Confirm
                                    </button>
                                    <button
                                        type="button"
                                        class="text-gray-500 hover:text-gray-700 dark:text-gray-400"
                                        @click="cancelDelete"
                                    >
                                        Cancel
                                    </button>
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <div v-else class="px-6 py-12 text-center">
                    <p class="text-gray-500 dark:text-gray-400 text-sm">
                        No routing rules configured yet. Add a rule to control how orders are assigned to warehouse locations.
                    </p>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
