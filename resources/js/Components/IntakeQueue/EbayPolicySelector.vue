<script setup>
import { ref, onMounted, watch, computed } from 'vue';
import axios from 'axios';

const props = defineProps({
    credentialId: {
        type: [String, Number],
        default: null,
    },
    productData: {
        type: Object,
        default: () => ({}),
    },
    modelValue: {
        type: Object,
        default: () => ({
            fulfillment: null,
            payment: null,
            return: null,
        }),
    },
});

const emit = defineEmits(['update:modelValue']);

const loading = ref(false);
const error = ref(null);

const policies = ref({
    fulfillment: [],
    payment: [],
    return: [],
});

const selectedPolicies = ref({
    fulfillment: props.modelValue?.fulfillment || null,
    payment: props.modelValue?.payment || null,
    return: props.modelValue?.return || null,
});

const matchedRule = ref(null);
const suggestedPolicies = ref(null);

const hasRuleSuggestion = computed(() => {
    return matchedRule.value !== null;
});

const fetchPolicies = async () => {
    loading.value = true;
    error.value = null;

    try {
        const response = await axios.get(route('marketplaces.ebay.policies.index'));

        if (response.data?.policies) {
            policies.value = {
                fulfillment: response.data.policies.fulfillment || [],
                payment: response.data.policies.payment || [],
                return: response.data.policies.return || [],
            };

            // Set defaults if not already selected
            if (!selectedPolicies.value.fulfillment && policies.value.fulfillment.length > 0) {
                const defaultFulfillment = policies.value.fulfillment.find(p => p.is_default) || policies.value.fulfillment[0];
                selectedPolicies.value.fulfillment = defaultFulfillment?.ebay_policy_id;
            }
            if (!selectedPolicies.value.payment && policies.value.payment.length > 0) {
                const defaultPayment = policies.value.payment.find(p => p.is_default) || policies.value.payment[0];
                selectedPolicies.value.payment = defaultPayment?.ebay_policy_id;
            }
            if (!selectedPolicies.value.return && policies.value.return.length > 0) {
                const defaultReturn = policies.value.return.find(p => p.is_default) || policies.value.return[0];
                selectedPolicies.value.return = defaultReturn?.ebay_policy_id;
            }

            emitPolicies();
        }
    } catch (e) {
        error.value = e.response?.data?.error || 'Failed to fetch policies';
        console.error('Policy fetch error:', e);
    } finally {
        loading.value = false;
    }
};

const evaluatePolicies = async () => {
    if (!props.productData || Object.keys(props.productData).length === 0) {
        return;
    }

    try {
        const response = await axios.post(route('marketplaces.ebay.policies.evaluate'), props.productData);

        if (response.data.success) {
            matchedRule.value = response.data.matched_rule;
            suggestedPolicies.value = response.data.policy_ids;

            // Apply suggestions if available
            if (suggestedPolicies.value) {
                if (suggestedPolicies.value.fulfillment) {
                    selectedPolicies.value.fulfillment = suggestedPolicies.value.fulfillment;
                }
                if (suggestedPolicies.value.payment) {
                    selectedPolicies.value.payment = suggestedPolicies.value.payment;
                }
                if (suggestedPolicies.value.return) {
                    selectedPolicies.value.return = suggestedPolicies.value.return;
                }
                emitPolicies();
            }
        }
    } catch (e) {
        console.error('Policy evaluation error:', e);
    }
};

const emitPolicies = () => {
    emit('update:modelValue', { ...selectedPolicies.value });
};

const handlePolicyChange = () => {
    // Clear matched rule indicator when user manually changes
    matchedRule.value = null;
    emitPolicies();
};

const getPolicyName = (type, policyId) => {
    const policy = policies.value[type]?.find(p => p.ebay_policy_id === policyId);
    return policy?.name || 'Unknown';
};

watch(() => props.productData, (newData) => {
    if (newData && Object.keys(newData).length > 0) {
        evaluatePolicies();
    }
}, { deep: true });

onMounted(() => {
    fetchPolicies();
});
</script>

<template>
    <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-4">
        <div class="flex items-center justify-between mb-4">
            <h4 class="text-md font-semibold text-gray-900 dark:text-gray-100 flex items-center">
                <svg class="w-5 h-5 mr-2 text-blue-600" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M9 4.804A7.968 7.968 0 005.5 4c-1.255 0-2.443.29-3.5.804v10A7.969 7.969 0 015.5 14c1.669 0 3.218.51 4.5 1.385A7.962 7.962 0 0114.5 14c1.255 0 2.443.29 3.5.804v-10A7.968 7.968 0 0014.5 4c-1.255 0-2.443.29-3.5.804V12a1 1 0 11-2 0V4.804z"/>
                </svg>
                eBay Business Policies
            </h4>
        </div>

        <!-- Rule Match Indicator -->
        <div v-if="hasRuleSuggestion" class="mb-4 p-3 bg-blue-50 dark:bg-blue-900/20 rounded-lg border border-blue-200 dark:border-blue-800">
            <div class="flex items-center gap-2">
                <svg class="w-5 h-5 text-blue-600" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                </svg>
                <div>
                    <span class="text-sm font-medium text-blue-800 dark:text-blue-200">
                        Auto-selected by rule: {{ matchedRule.name }}
                    </span>
                </div>
            </div>
        </div>

        <!-- Loading State -->
        <div v-if="loading" class="py-4 text-center">
            <svg class="animate-spin h-6 w-6 mx-auto text-blue-600" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Loading policies...</p>
        </div>

        <!-- Error State -->
        <div v-else-if="error" class="py-4 text-center">
            <p class="text-sm text-red-600 dark:text-red-400">{{ error }}</p>
            <button @click="fetchPolicies" class="mt-2 text-xs text-blue-600 hover:underline">
                Try again
            </button>
        </div>

        <!-- Policy Selectors -->
        <div v-else class="space-y-4">
            <!-- Fulfillment Policy -->
            <div>
                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">
                    Shipping/Fulfillment Policy
                </label>
                <select
                    v-model="selectedPolicies.fulfillment"
                    @change="handlePolicyChange"
                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm dark:bg-gray-900 dark:text-gray-100"
                >
                    <option :value="null" disabled>Select a policy</option>
                    <option
                        v-for="policy in policies.fulfillment"
                        :key="policy.id"
                        :value="policy.ebay_policy_id"
                    >
                        {{ policy.name }}
                        <template v-if="policy.is_default">(Default)</template>
                    </option>
                </select>
            </div>

            <!-- Payment Policy -->
            <div>
                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">
                    Payment Policy
                </label>
                <select
                    v-model="selectedPolicies.payment"
                    @change="handlePolicyChange"
                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm dark:bg-gray-900 dark:text-gray-100"
                >
                    <option :value="null" disabled>Select a policy</option>
                    <option
                        v-for="policy in policies.payment"
                        :key="policy.id"
                        :value="policy.ebay_policy_id"
                    >
                        {{ policy.name }}
                        <template v-if="policy.is_default">(Default)</template>
                    </option>
                </select>
            </div>

            <!-- Return Policy -->
            <div>
                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">
                    Return Policy
                </label>
                <select
                    v-model="selectedPolicies.return"
                    @change="handlePolicyChange"
                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm dark:bg-gray-900 dark:text-gray-100"
                >
                    <option :value="null" disabled>Select a policy</option>
                    <option
                        v-for="policy in policies.return"
                        :key="policy.id"
                        :value="policy.ebay_policy_id"
                    >
                        {{ policy.name }}
                        <template v-if="policy.is_default">(Default)</template>
                    </option>
                </select>
            </div>

            <!-- No Policies Warning -->
            <div
                v-if="policies.fulfillment.length === 0 || policies.payment.length === 0 || policies.return.length === 0"
                class="p-3 bg-yellow-50 dark:bg-yellow-900/20 rounded-lg border border-yellow-200 dark:border-yellow-800"
            >
                <div class="flex items-start gap-2">
                    <svg class="w-5 h-5 text-yellow-600 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                    </svg>
                    <div class="text-sm text-yellow-800 dark:text-yellow-200">
                        <p class="font-medium">Missing policies</p>
                        <p class="mt-1">Sync your eBay policies in Settings > Marketplaces > eBay > Policies</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
