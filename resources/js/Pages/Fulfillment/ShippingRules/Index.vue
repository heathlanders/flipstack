<script setup>
import { ref } from 'vue'
import { router, Head, Link } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { route } from 'ziggy-js'

const props = defineProps({
    rules: Array,
    orders: Array,
})

const testOrderId = ref('')
const testResult = ref(null)
const testLoading = ref(false)

const carrierColors = {
    USPS: 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
    UPS: 'bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-200',
    FedEx: 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200',
    DHL: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
}

function summarizeConditions(conditions) {
    if (!conditions || Object.keys(conditions).length === 0) {
        return 'No conditions (matches all orders)'
    }

    const parts = []

    if (conditions.weight_min != null || conditions.weight_max != null) {
        const min = conditions.weight_min ?? '0'
        const max = conditions.weight_max ?? 'any'
        parts.push(`Weight: ${min}-${max} oz`)
    }

    if (conditions.value_min != null || conditions.value_max != null) {
        const min = conditions.value_min ?? '0'
        const max = conditions.value_max ?? 'any'
        parts.push(`Value: $${min}-$${max}`)
    }

    if (conditions.destination_states && conditions.destination_states.length > 0) {
        parts.push(`States: ${conditions.destination_states.join(', ')}`)
    }

    if (conditions.destination_countries && conditions.destination_countries.length > 0) {
        parts.push(`Countries: ${conditions.destination_countries.join(', ')}`)
    }

    if (conditions.marketplace) {
        parts.push(`Marketplace: ${conditions.marketplace}`)
    }

    if (conditions.item_count_min != null || conditions.item_count_max != null) {
        const min = conditions.item_count_min ?? '0'
        const max = conditions.item_count_max ?? 'any'
        parts.push(`Items: ${min}-${max}`)
    }

    return parts.length > 0 ? parts.join(' | ') : 'No conditions (matches all orders)'
}

function toggleRule(rule) {
    router.post(route('fulfillment.shipping-rules.toggle', rule.id), {}, {
        preserveScroll: true,
    })
}

function deleteRule(rule) {
    if (!confirm(`Delete shipping rule "${rule.name}"?`)) return
    router.delete(route('fulfillment.shipping-rules.destroy', rule.id), {
        preserveScroll: true,
    })
}

function runTest() {
    if (!testOrderId.value) return
    testLoading.value = true
    testResult.value = null

    fetch(route('fulfillment.shipping-rules.test'), {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
        },
        body: JSON.stringify({ order_id: testOrderId.value }),
    })
        .then(res => res.json())
        .then(data => {
            testResult.value = data
        })
        .catch(() => {
            testResult.value = { error: 'Failed to test rules against order.' }
        })
        .finally(() => {
            testLoading.value = false
        })
}
</script>

<template>
    <AppLayout title="Shipping Rules">
        <Head title="Shipping Rules" />

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
            <!-- Header -->
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">Shipping Rules</h1>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                        Automatically assign carriers and services based on order conditions. Rules are evaluated in priority order (lowest first).
                    </p>
                </div>
                <Link
                    :href="route('fulfillment.shipping-rules.create')"
                    class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-md hover:bg-indigo-700 transition-colors"
                >
                    Create Rule
                </Link>
            </div>

            <!-- Rules Table -->
            <div class="bg-white dark:bg-gray-800 rounded-lg border dark:border-gray-700 overflow-hidden mb-8">
                <table v-if="rules.length > 0" class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-900">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Priority</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Name</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Conditions</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Carrier</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Service</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Package</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        <tr v-for="rule in rules" :key="rule.id" :class="{ 'opacity-50': !rule.is_active }">
                            <td class="px-4 py-3 text-sm font-mono text-gray-700 dark:text-gray-300">{{ rule.priority }}</td>
                            <td class="px-4 py-3 text-sm font-medium text-gray-900 dark:text-gray-100">{{ rule.name }}</td>
                            <td class="px-4 py-3 text-xs text-gray-600 dark:text-gray-400 max-w-xs truncate">
                                {{ summarizeConditions(rule.conditions) }}
                            </td>
                            <td class="px-4 py-3">
                                <span :class="carrierColors[rule.action_carrier] || 'bg-gray-100 text-gray-800'" class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium">
                                    {{ rule.action_carrier }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">{{ rule.action_service }}</td>
                            <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-400">
                                {{ rule.package_preset?.name || '--' }}
                            </td>
                            <td class="px-4 py-3">
                                <span
                                    :class="rule.is_active
                                        ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200'
                                        : 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400'"
                                    class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium"
                                >
                                    {{ rule.is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right space-x-2">
                                <Link
                                    :href="route('fulfillment.shipping-rules.edit', rule.id)"
                                    class="text-indigo-600 hover:text-indigo-800 dark:text-indigo-400 dark:hover:text-indigo-300 text-sm font-medium"
                                >
                                    Edit
                                </Link>
                                <button
                                    @click="toggleRule(rule)"
                                    class="text-sm font-medium"
                                    :class="rule.is_active ? 'text-amber-600 hover:text-amber-800 dark:text-amber-400' : 'text-green-600 hover:text-green-800 dark:text-green-400'"
                                >
                                    {{ rule.is_active ? 'Disable' : 'Enable' }}
                                </button>
                                <button
                                    @click="deleteRule(rule)"
                                    class="text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-300 text-sm font-medium"
                                >
                                    Delete
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <div v-else class="p-8 text-center text-gray-500 dark:text-gray-400">
                    <p class="text-lg font-medium mb-2">No shipping rules configured</p>
                    <p class="text-sm mb-4">Create rules to automatically assign carriers and services to orders based on conditions.</p>
                    <Link
                        :href="route('fulfillment.shipping-rules.create')"
                        class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-md hover:bg-indigo-700"
                    >
                        Create First Rule
                    </Link>
                </div>
            </div>

            <!-- Test Section -->
            <div class="bg-white dark:bg-gray-800 rounded-lg border dark:border-gray-700 p-6">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">Test Rules Against Order</h2>
                <div class="flex items-end gap-4 mb-4">
                    <div class="flex-1 max-w-md">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Select an Order</label>
                        <select
                            v-model="testOrderId"
                            class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 text-sm"
                        >
                            <option value="">-- Select Order --</option>
                            <option v-for="order in orders" :key="order.id" :value="order.id">
                                #{{ order.id.substring(0, 8) }} - {{ order.marketplace }} - ${{ Number(order.total_amount).toFixed(2) }} - {{ order.shipping_state || 'N/A' }}, {{ order.shipping_country || 'N/A' }}
                            </option>
                        </select>
                    </div>
                    <button
                        @click="runTest"
                        :disabled="!testOrderId || testLoading"
                        class="px-4 py-2 bg-gray-800 text-white text-sm font-medium rounded-md hover:bg-gray-700 disabled:opacity-50 disabled:cursor-not-allowed dark:bg-gray-600 dark:hover:bg-gray-500"
                    >
                        {{ testLoading ? 'Testing...' : 'Test Rules' }}
                    </button>
                </div>

                <div v-if="testResult && !testResult.error" class="space-y-4">
                    <!-- Order Summary -->
                    <div class="bg-gray-50 dark:bg-gray-900 rounded-lg p-4">
                        <h3 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Order Summary</h3>
                        <div class="grid grid-cols-2 md:grid-cols-3 gap-2 text-sm">
                            <div><span class="text-gray-500 dark:text-gray-400">Total:</span> ${{ testResult.order_summary.total_amount.toFixed(2) }}</div>
                            <div><span class="text-gray-500 dark:text-gray-400">Weight:</span> {{ testResult.order_summary.total_weight_oz }} oz</div>
                            <div><span class="text-gray-500 dark:text-gray-400">Items:</span> {{ testResult.order_summary.item_count }}</div>
                            <div><span class="text-gray-500 dark:text-gray-400">State:</span> {{ testResult.order_summary.shipping_state || 'N/A' }}</div>
                            <div><span class="text-gray-500 dark:text-gray-400">Country:</span> {{ testResult.order_summary.shipping_country || 'N/A' }}</div>
                            <div><span class="text-gray-500 dark:text-gray-400">Marketplace:</span> {{ testResult.order_summary.marketplace || 'N/A' }}</div>
                        </div>
                    </div>

                    <!-- Match Result -->
                    <div v-if="testResult.matched_rule" class="bg-green-50 dark:bg-green-900/30 border border-green-200 dark:border-green-800 rounded-lg p-4">
                        <h3 class="text-sm font-semibold text-green-800 dark:text-green-300 mb-1">Matched Rule: {{ testResult.matched_rule.rule_name }}</h3>
                        <p class="text-sm text-green-700 dark:text-green-400">
                            Carrier: {{ testResult.matched_rule.action.carrier }} | Service: {{ testResult.matched_rule.action.service }}
                        </p>
                    </div>
                    <div v-else class="bg-amber-50 dark:bg-amber-900/30 border border-amber-200 dark:border-amber-800 rounded-lg p-4">
                        <p class="text-sm text-amber-800 dark:text-amber-300 font-medium">No rule matched this order.</p>
                    </div>

                    <!-- Detailed Results -->
                    <div>
                        <h3 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Rule Evaluation Details</h3>
                        <div class="space-y-2">
                            <div
                                v-for="ruleResult in testResult.all_rules"
                                :key="ruleResult.rule_id"
                                :class="ruleResult.matched ? 'border-green-300 dark:border-green-700 bg-green-50 dark:bg-green-900/20' : 'border-gray-200 dark:border-gray-700'"
                                class="border rounded-lg p-3"
                            >
                                <div class="flex items-center justify-between mb-1">
                                    <span class="text-sm font-medium text-gray-900 dark:text-gray-100">
                                        [{{ ruleResult.priority }}] {{ ruleResult.rule_name }}
                                    </span>
                                    <span
                                        :class="ruleResult.matched ? 'text-green-700 dark:text-green-400' : 'text-red-600 dark:text-red-400'"
                                        class="text-xs font-medium"
                                    >
                                        {{ ruleResult.matched ? 'MATCHED' : 'NOT MATCHED' }}
                                    </span>
                                </div>
                                <div class="flex flex-wrap gap-2 text-xs">
                                    <span
                                        v-for="(passed, check) in ruleResult.checks"
                                        :key="check"
                                        :class="passed ? 'bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300' : 'bg-red-100 text-red-700 dark:bg-red-900 dark:text-red-300'"
                                        class="px-2 py-0.5 rounded"
                                    >
                                        {{ check.replace('_', ' ') }}: {{ passed ? 'pass' : 'fail' }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-if="testResult?.error" class="bg-red-50 dark:bg-red-900/30 border border-red-200 dark:border-red-800 rounded-lg p-4">
                    <p class="text-sm text-red-700 dark:text-red-300">{{ testResult.error }}</p>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
