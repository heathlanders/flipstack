<script setup>
import { ref, computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import axios from 'axios';

const props = defineProps({
    rules: {
        type: Array,
        default: () => [],
    },
    policies: {
        type: Object,
        default: () => ({
            fulfillment: [],
            payment: [],
            return: [],
        }),
    },
    availableFields: {
        type: Object,
        default: () => ({}),
    },
    operators: {
        type: Object,
        default: () => ({}),
    },
    error: String,
});

const showModal = ref(false);
const editingRule = ref(null);
const saving = ref(false);
const testing = ref(false);
const testResult = ref(null);

const formData = ref({
    name: '',
    description: '',
    priority: 10,
    conditions: [{ field: 'price', operator: '>=', value: '' }],
    condition_logic: 'AND',
    fulfillment_policy_id: null,
    payment_policy_id: null,
    return_policy_id: null,
    is_active: true,
});

const testProductData = ref({
    price: 100,
    weight_lbs: 2,
    shipping_class: 'small_package',
    condition: 'new',
    category: '',
    estimated_shipping_cost: 10,
});

const openCreateModal = () => {
    editingRule.value = null;
    formData.value = {
        name: '',
        description: '',
        priority: 10,
        conditions: [{ field: 'price', operator: '>=', value: '' }],
        condition_logic: 'AND',
        fulfillment_policy_id: null,
        payment_policy_id: null,
        return_policy_id: null,
        is_active: true,
    };
    showModal.value = true;
};

const openEditModal = (rule) => {
    editingRule.value = rule;
    formData.value = {
        name: rule.name,
        description: rule.description || '',
        priority: rule.priority,
        conditions: [...rule.conditions],
        condition_logic: rule.condition_logic,
        fulfillment_policy_id: rule.fulfillment_policy_id,
        payment_policy_id: rule.payment_policy_id,
        return_policy_id: rule.return_policy_id,
        is_active: rule.is_active,
    };
    showModal.value = true;
};

const closeModal = () => {
    showModal.value = false;
    editingRule.value = null;
};

const addCondition = () => {
    formData.value.conditions.push({ field: 'price', operator: '>=', value: '' });
};

const removeCondition = (index) => {
    if (formData.value.conditions.length > 1) {
        formData.value.conditions.splice(index, 1);
    }
};

const saveRule = async () => {
    saving.value = true;

    try {
        const url = editingRule.value
            ? route('marketplaces.ebay.policy-rules.update', editingRule.value.id)
            : route('marketplaces.ebay.policy-rules.store');

        const method = editingRule.value ? 'put' : 'post';

        const response = await axios[method](url, formData.value);

        if (response.data.success) {
            window.location.reload();
        }
    } catch (e) {
        alert(e.response?.data?.error || 'Failed to save rule');
    } finally {
        saving.value = false;
    }
};

const deleteRule = async (rule) => {
    if (!confirm(`Are you sure you want to delete "${rule.name}"?`)) {
        return;
    }

    try {
        const response = await axios.delete(route('marketplaces.ebay.policy-rules.destroy', rule.id));

        if (response.data.success) {
            window.location.reload();
        }
    } catch (e) {
        alert(e.response?.data?.error || 'Failed to delete rule');
    }
};

const testRules = async () => {
    testing.value = true;
    testResult.value = null;

    try {
        const response = await axios.post(route('marketplaces.ebay.policy-rules.test'), {
            product_data: testProductData.value,
        });

        if (response.data.success) {
            testResult.value = response.data.result;
        }
    } catch (e) {
        testResult.value = { error: e.response?.data?.error || 'Test failed' };
    } finally {
        testing.value = false;
    }
};

const getFieldLabel = (field) => {
    return props.availableFields[field]?.label || field;
};

const getOperatorLabel = (op) => {
    return props.operators[op] || op;
};

const getPolicyName = (policies, id) => {
    const policy = policies.find(p => p.id === id);
    return policy?.name || 'Not set';
};
</script>

<template>
    <AppLayout title="eBay Policy Rules">
        <Head title="eBay Policy Rules" />

        <div class="py-6">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <!-- Header -->
                <div class="flex items-center justify-between mb-6">
                    <div>
                        <div class="flex items-center gap-3">
                            <Link
                                :href="route('marketplaces.ebay.policies.index')"
                                class="text-gray-500 hover:text-gray-700 dark:text-gray-400"
                            >
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                                </svg>
                            </Link>
                            <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">
                                Policy Selection Rules
                            </h1>
                        </div>
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                            Auto-select eBay policies based on product criteria
                        </p>
                    </div>

                    <button
                        @click="openCreateModal"
                        class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700"
                    >
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                        </svg>
                        Create Rule
                    </button>
                </div>

                <!-- Error State -->
                <div v-if="error" class="mb-6 p-4 bg-red-50 border border-red-200 rounded-lg text-red-800">
                    {{ error }}
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <!-- Rules List -->
                    <div class="lg:col-span-2 space-y-4">
                        <div v-if="rules.length > 0">
                            <div
                                v-for="(rule, index) in rules"
                                :key="rule.id"
                                class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-5"
                            >
                                <div class="flex items-start justify-between">
                                    <div>
                                        <div class="flex items-center gap-2">
                                            <span class="px-2 py-0.5 bg-gray-100 dark:bg-gray-700 rounded text-xs font-mono">
                                                #{{ rule.priority }}
                                            </span>
                                            <h3 class="font-semibold text-gray-900 dark:text-gray-100">
                                                {{ rule.name }}
                                            </h3>
                                            <span
                                                v-if="!rule.is_active"
                                                class="px-2 py-0.5 bg-yellow-100 text-yellow-800 rounded text-xs"
                                            >
                                                Inactive
                                            </span>
                                        </div>
                                        <p v-if="rule.description" class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                            {{ rule.description }}
                                        </p>
                                    </div>

                                    <div class="flex items-center gap-2">
                                        <button
                                            @click="openEditModal(rule)"
                                            class="p-2 text-gray-400 hover:text-blue-600"
                                        >
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                            </svg>
                                        </button>
                                        <button
                                            @click="deleteRule(rule)"
                                            class="p-2 text-gray-400 hover:text-red-600"
                                        >
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                            </svg>
                                        </button>
                                    </div>
                                </div>

                                <!-- Conditions -->
                                <div class="mt-4 p-3 bg-gray-50 dark:bg-gray-900 rounded-lg">
                                    <div class="text-xs font-medium text-gray-500 dark:text-gray-400 mb-2">
                                        Conditions ({{ rule.condition_logic }})
                                    </div>
                                    <div class="space-y-1">
                                        <div
                                            v-for="(condition, i) in rule.conditions"
                                            :key="i"
                                            class="text-sm text-gray-700 dark:text-gray-300"
                                        >
                                            <span class="font-medium">{{ getFieldLabel(condition.field) }}</span>
                                            <span class="text-gray-500"> {{ getOperatorLabel(condition.operator) }} </span>
                                            <span class="font-mono bg-gray-200 dark:bg-gray-700 px-1 rounded">{{ condition.value }}</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Selected Policies -->
                                <div class="mt-3 grid grid-cols-3 gap-2 text-xs">
                                    <div>
                                        <span class="text-gray-500">Fulfillment:</span>
                                        <span class="ml-1 text-gray-900 dark:text-gray-100">
                                            {{ getPolicyName(policies.fulfillment, rule.fulfillment_policy_id) }}
                                        </span>
                                    </div>
                                    <div>
                                        <span class="text-gray-500">Payment:</span>
                                        <span class="ml-1 text-gray-900 dark:text-gray-100">
                                            {{ getPolicyName(policies.payment, rule.payment_policy_id) }}
                                        </span>
                                    </div>
                                    <div>
                                        <span class="text-gray-500">Return:</span>
                                        <span class="ml-1 text-gray-900 dark:text-gray-100">
                                            {{ getPolicyName(policies.return, rule.return_policy_id) }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Empty State -->
                        <div v-else class="text-center py-12 bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700">
                            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"/>
                            </svg>
                            <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-gray-100">
                                No policy rules yet
                            </h3>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                Create rules to automatically select policies based on product criteria.
                            </p>
                        </div>
                    </div>

                    <!-- Test Panel -->
                    <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-5 h-fit">
                        <h3 class="font-semibold text-gray-900 dark:text-gray-100 mb-4">
                            Test Rules
                        </h3>

                        <div class="space-y-3">
                            <div>
                                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Price ($)</label>
                                <input
                                    v-model.number="testProductData.price"
                                    type="number"
                                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm dark:bg-gray-900"
                                />
                            </div>

                            <div>
                                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Weight (lbs)</label>
                                <input
                                    v-model.number="testProductData.weight_lbs"
                                    type="number"
                                    step="0.1"
                                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm dark:bg-gray-900"
                                />
                            </div>

                            <div>
                                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Condition</label>
                                <select
                                    v-model="testProductData.condition"
                                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm dark:bg-gray-900"
                                >
                                    <option value="new">New</option>
                                    <option value="like_new">Like New</option>
                                    <option value="good">Good</option>
                                    <option value="fair">Fair</option>
                                </select>
                            </div>

                            <button
                                @click="testRules"
                                :disabled="testing"
                                class="w-full px-4 py-2 bg-gray-800 text-white rounded-lg text-sm font-medium hover:bg-gray-700 disabled:opacity-50"
                            >
                                {{ testing ? 'Testing...' : 'Run Test' }}
                            </button>
                        </div>

                        <!-- Test Result -->
                        <div v-if="testResult" class="mt-4 p-3 rounded-lg" :class="testResult.error ? 'bg-red-50' : 'bg-green-50'">
                            <div v-if="testResult.error" class="text-sm text-red-800">
                                {{ testResult.error }}
                            </div>
                            <div v-else-if="testResult.matched_rule" class="text-sm text-green-800">
                                <div class="font-medium">Match found!</div>
                                <div>Rule: {{ testResult.matched_rule.name }}</div>
                            </div>
                            <div v-else class="text-sm text-gray-600">
                                No rule matched - default policies will be used.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Create/Edit Modal -->
        <div v-if="showModal" class="fixed inset-0 z-50 overflow-y-auto">
            <div class="flex items-center justify-center min-h-screen px-4">
                <div class="fixed inset-0 bg-black/50" @click="closeModal"></div>

                <div class="relative bg-white dark:bg-gray-800 rounded-lg max-w-2xl w-full p-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">
                        {{ editingRule ? 'Edit Rule' : 'Create Rule' }}
                    </h3>

                    <div class="space-y-4 max-h-[60vh] overflow-y-auto">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Name</label>
                                <input
                                    v-model="formData.name"
                                    type="text"
                                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md dark:bg-gray-900"
                                    placeholder="e.g., High Value Items"
                                />
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Priority</label>
                                <input
                                    v-model.number="formData.priority"
                                    type="number"
                                    min="0"
                                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md dark:bg-gray-900"
                                />
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Description</label>
                            <textarea
                                v-model="formData.description"
                                rows="2"
                                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md dark:bg-gray-900"
                            ></textarea>
                        </div>

                        <!-- Conditions -->
                        <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                            <div class="flex items-center justify-between mb-3">
                                <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Conditions</label>
                                <select
                                    v-model="formData.condition_logic"
                                    class="px-2 py-1 border border-gray-300 dark:border-gray-600 rounded text-xs dark:bg-gray-900"
                                >
                                    <option value="AND">Match ALL</option>
                                    <option value="OR">Match ANY</option>
                                </select>
                            </div>

                            <div class="space-y-2">
                                <div
                                    v-for="(condition, index) in formData.conditions"
                                    :key="index"
                                    class="flex items-center gap-2"
                                >
                                    <select
                                        v-model="condition.field"
                                        class="flex-1 px-2 py-1 border border-gray-300 dark:border-gray-600 rounded text-sm dark:bg-gray-900"
                                    >
                                        <option v-for="(field, key) in availableFields" :key="key" :value="key">
                                            {{ field.label }}
                                        </option>
                                    </select>

                                    <select
                                        v-model="condition.operator"
                                        class="w-24 px-2 py-1 border border-gray-300 dark:border-gray-600 rounded text-sm dark:bg-gray-900"
                                    >
                                        <option v-for="(label, op) in operators" :key="op" :value="op">
                                            {{ op }}
                                        </option>
                                    </select>

                                    <input
                                        v-model="condition.value"
                                        type="text"
                                        class="flex-1 px-2 py-1 border border-gray-300 dark:border-gray-600 rounded text-sm dark:bg-gray-900"
                                        placeholder="Value"
                                    />

                                    <button
                                        @click="removeCondition(index)"
                                        :disabled="formData.conditions.length === 1"
                                        class="p-1 text-red-500 hover:text-red-700 disabled:opacity-30"
                                    >
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                        </svg>
                                    </button>
                                </div>
                            </div>

                            <button
                                @click="addCondition"
                                class="mt-2 text-sm text-blue-600 hover:text-blue-800"
                            >
                                + Add Condition
                            </button>
                        </div>

                        <!-- Policy Selection -->
                        <div class="grid grid-cols-3 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Fulfillment Policy</label>
                                <select
                                    v-model="formData.fulfillment_policy_id"
                                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm dark:bg-gray-900"
                                >
                                    <option :value="null">Default</option>
                                    <option v-for="p in policies.fulfillment" :key="p.id" :value="p.id">
                                        {{ p.name }}
                                    </option>
                                </select>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Payment Policy</label>
                                <select
                                    v-model="formData.payment_policy_id"
                                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm dark:bg-gray-900"
                                >
                                    <option :value="null">Default</option>
                                    <option v-for="p in policies.payment" :key="p.id" :value="p.id">
                                        {{ p.name }}
                                    </option>
                                </select>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Return Policy</label>
                                <select
                                    v-model="formData.return_policy_id"
                                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm dark:bg-gray-900"
                                >
                                    <option :value="null">Default</option>
                                    <option v-for="p in policies.return" :key="p.id" :value="p.id">
                                        {{ p.name }}
                                    </option>
                                </select>
                            </div>
                        </div>

                        <div class="flex items-center gap-2">
                            <input
                                v-model="formData.is_active"
                                type="checkbox"
                                id="rule-active"
                                class="rounded border-gray-300"
                            />
                            <label for="rule-active" class="text-sm text-gray-700 dark:text-gray-300">
                                Rule is active
                            </label>
                        </div>
                    </div>

                    <div class="mt-6 flex justify-end gap-3">
                        <button
                            @click="closeModal"
                            class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-sm"
                        >
                            Cancel
                        </button>
                        <button
                            @click="saveRule"
                            :disabled="saving"
                            class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700 disabled:opacity-50"
                        >
                            {{ saving ? 'Saving...' : (editingRule ? 'Update' : 'Create') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
