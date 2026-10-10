<script setup>
import { ref, onMounted, watch, computed } from 'vue';
import axios from 'axios';

const props = defineProps({
    queueItemId: {
        type: [String, Number],
        required: true,
    },
    dimensions: {
        type: Object,
        default: () => ({}),
    },
    estimatedCost: {
        type: Number,
        default: 0,
    },
    modelValue: {
        type: Object,
        default: null,
    },
    itemValue: {
        type: Number,
        default: 0,
    },
    shippingSettings: {
        type: Object,
        default: () => ({}),
    },
});

const emit = defineEmits(['update:modelValue', 'rate-selected']);

const loading = ref(false);
const error = ref(null);
const rates = ref([]);
const selectedRateId = ref(null);
const shippingSettings = ref(props.shippingSettings || {});

// Shipping options state
const insuranceEnabled = ref(false);
const confirmationType = ref('none');
const additionalItemCost = ref(null);
const optionsExpanded = ref(false);

// Debounce timer for re-fetching rates on option change
let refetchTimer = null;

const hasValidDimensions = computed(() => {
    return props.dimensions?.weight_lbs && props.dimensions.weight_lbs > 0;
});

const sortedRates = computed(() => {
    return [...rates.value].sort((a, b) => a.rate - b.rate);
});

const fastestRate = computed(() => {
    const withDays = rates.value.filter(r => r.estimated_days != null && r.estimated_days > 0);
    if (withDays.length === 0) return null;
    return withDays.reduce((fastest, r) => (r.estimated_days < fastest.estimated_days ? r : fastest), withDays[0]);
});

const selectedRate = computed(() => {
    return rates.value.find(r => r.id === selectedRateId.value);
});

const suggestedAdditionalItemCost = computed(() => {
    if (!selectedRate.value) return null;
    const settings = shippingSettings.value;
    if (settings.additional_item_cost_mode === 'fixed') {
        return settings.additional_item_cost_fixed ?? 3.00;
    }
    const percent = settings.additional_item_cost_percent ?? 50;
    return Math.round(selectedRate.value.rate * (percent / 100) * 100) / 100;
});

const insuranceThresholdNote = computed(() => {
    const threshold = shippingSettings.value.auto_insurance_threshold;
    if (!threshold) return null;
    if (props.itemValue >= threshold) {
        return `Auto-enabled: item value ($${props.itemValue.toFixed(2)}) exceeds $${threshold.toFixed(2)} threshold`;
    }
    return `Auto-enables when item value exceeds $${threshold.toFixed(2)}`;
});

const signatureThresholdNote = computed(() => {
    const threshold = shippingSettings.value.signature_threshold;
    if (!threshold) return null;
    if (props.itemValue >= threshold) {
        return `Auto-enabled: item value ($${props.itemValue.toFixed(2)}) exceeds $${threshold.toFixed(2)} threshold`;
    }
    return `Auto-enables when item value exceeds $${threshold.toFixed(2)}`;
});

// Apply auto-settings based on thresholds
const applyThresholdDefaults = () => {
    const settings = shippingSettings.value;

    if (settings.auto_insurance_enabled && props.itemValue >= (settings.auto_insurance_threshold || 100)) {
        insuranceEnabled.value = true;
    }

    if (settings.signature_confirmation === 'threshold' && props.itemValue >= (settings.signature_threshold || 250)) {
        confirmationType.value = 'signature';
    } else if (settings.signature_confirmation === 'always') {
        confirmationType.value = 'signature';
    }
};

const fetchRates = async () => {
    if (!hasValidDimensions.value) {
        error.value = 'Package dimensions required for rate lookup';
        return;
    }

    loading.value = true;
    error.value = null;

    try {
        const payload = {};
        if (insuranceEnabled.value && props.itemValue > 0) {
            payload.insurance_value = props.itemValue;
        }
        if (confirmationType.value !== 'none') {
            payload.confirmation = confirmationType.value;
        }

        const response = await axios.post(
            route('intake-queue.shipping-rates', props.queueItemId),
            payload
        );

        if (response.data.success) {
            rates.value = response.data.rates;

            if (response.data.shipping_settings) {
                shippingSettings.value = response.data.shipping_settings;
            }

            // Auto-select cheapest rate if none selected
            if (!selectedRateId.value && rates.value.length > 0) {
                const cheapest = sortedRates.value[0];
                selectRate(cheapest);
            }
        } else {
            error.value = response.data.error || 'Failed to fetch rates';
        }
    } catch (e) {
        error.value = e.response?.data?.error || 'Failed to fetch shipping rates';
        console.error('Shipping rate fetch error:', e);
    } finally {
        loading.value = false;
    }
};

const refreshRates = async () => {
    loading.value = true;
    error.value = null;

    try {
        const payload = {};
        if (insuranceEnabled.value && props.itemValue > 0) {
            payload.insurance_value = props.itemValue;
        }
        if (confirmationType.value !== 'none') {
            payload.confirmation = confirmationType.value;
        }

        const response = await axios.post(
            route('intake-queue.shipping-rates.refresh', props.queueItemId),
            payload
        );

        if (response.data.success) {
            rates.value = response.data.rates;

            if (response.data.shipping_settings) {
                shippingSettings.value = response.data.shipping_settings;
            }
        } else {
            error.value = response.data.error || 'Failed to refresh rates';
        }
    } catch (e) {
        error.value = e.response?.data?.error || 'Failed to refresh shipping rates';
    } finally {
        loading.value = false;
    }
};

const selectRate = (rate) => {
    selectedRateId.value = rate.id;

    // Auto-populate additional item cost from rate data or suggestion
    if (rate.additional_item_cost != null) {
        additionalItemCost.value = parseFloat(rate.additional_item_cost);
    } else if (suggestedAdditionalItemCost.value != null) {
        additionalItemCost.value = suggestedAdditionalItemCost.value;
    }

    emitEnhancedRate(rate);
};

const emitEnhancedRate = (rate) => {
    const enhanced = {
        ...rate,
        insurance_enabled: insuranceEnabled.value,
        confirmation_type: confirmationType.value,
        additional_item_cost: additionalItemCost.value,
    };
    emit('update:modelValue', enhanced);
    emit('rate-selected', enhanced);
};

const debouncedRefetch = () => {
    if (refetchTimer) clearTimeout(refetchTimer);
    refetchTimer = setTimeout(() => {
        fetchRates();
    }, 300);
};

const isFastest = (rate) => {
    return fastestRate.value && rate.id === fastestRate.value.id;
};

const isCheapest = (rate) => {
    return sortedRates.value.length > 0 && rate.id === sortedRates.value[0].id;
};

// Watch insurance/confirmation changes to re-fetch
watch(insuranceEnabled, () => {
    debouncedRefetch();
});

watch(confirmationType, () => {
    debouncedRefetch();
});

// Watch additional item cost edits and re-emit
watch(additionalItemCost, () => {
    if (selectedRate.value) {
        emitEnhancedRate(selectedRate.value);
    }
});

// Watch for dimension changes to refetch
watch(() => props.dimensions, (newDimensions) => {
    if (hasValidDimensions.value) {
        fetchRates();
    }
}, { deep: true });

// Watch itemValue to apply threshold defaults
watch(() => props.itemValue, () => {
    applyThresholdDefaults();
});

onMounted(() => {
    applyThresholdDefaults();
    fetchRates();
});
</script>

<template>
    <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-4">
        <div class="flex items-center justify-between mb-4">
            <h4 class="text-md font-semibold text-gray-900 dark:text-gray-100 flex items-center">
                <svg class="w-5 h-5 mr-2 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"/>
                </svg>
                Shipping Rates
            </h4>
            <button
                v-if="rates.length > 0"
                @click="refreshRates"
                :disabled="loading"
                class="text-xs text-blue-600 hover:text-blue-800 dark:text-blue-400 flex items-center"
            >
                <svg :class="{'animate-spin': loading}" class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                </svg>
                Refresh
            </button>
        </div>

        <!-- Loading State -->
        <div v-if="loading && rates.length === 0" class="py-8 text-center">
            <svg class="animate-spin h-8 w-8 mx-auto text-blue-600" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Fetching shipping rates...</p>
        </div>

        <!-- No Dimensions Warning -->
        <div v-else-if="!hasValidDimensions" class="py-4 text-center">
            <svg class="w-8 h-8 mx-auto text-yellow-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
            </svg>
            <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                Enter package weight to get shipping rates
            </p>
            <p v-if="estimatedCost > 0" class="mt-1 text-xs text-gray-500">
                Using estimated cost: ${{ estimatedCost.toFixed(2) }}
            </p>
        </div>

        <!-- Error State -->
        <div v-else-if="error" class="py-4 text-center">
            <svg class="w-8 h-8 mx-auto text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ error }}</p>
            <button
                @click="fetchRates"
                class="mt-2 text-xs text-blue-600 hover:underline"
            >
                Try again
            </button>
        </div>

        <!-- Rates List -->
        <div v-else-if="rates.length > 0" class="space-y-2">
            <div
                v-for="rate in sortedRates"
                :key="rate.id"
                @click="selectRate(rate)"
                :class="{
                    'border-blue-500 bg-blue-50 dark:bg-blue-900/20': selectedRateId === rate.id,
                    'border-gray-200 dark:border-gray-700 hover:border-gray-300': selectedRateId !== rate.id,
                }"
                class="border rounded-lg p-3 cursor-pointer transition"
            >
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div
                            :class="{
                                'bg-blue-600 border-blue-600': selectedRateId === rate.id,
                                'bg-white dark:bg-gray-800 border-gray-300 dark:border-gray-600': selectedRateId !== rate.id,
                            }"
                            class="w-4 h-4 rounded-full border-2 flex items-center justify-center flex-shrink-0"
                        >
                            <div v-if="selectedRateId === rate.id" class="w-2 h-2 rounded-full bg-white"></div>
                        </div>
                        <div>
                            <div class="font-medium text-gray-900 dark:text-gray-100 text-sm">
                                {{ rate.carrier }}
                            </div>
                            <div class="text-xs text-gray-500 dark:text-gray-400">
                                {{ rate.service }}
                            </div>
                        </div>
                    </div>
                    <div class="text-right">
                        <div class="font-bold text-gray-900 dark:text-gray-100">
                            {{ rate.insurance_included ? rate.formatted_total_rate : rate.formatted_rate }}
                        </div>
                        <div v-if="rate.insurance_included && rate.insurance_cost" class="text-xs text-gray-400">
                            {{ rate.formatted_rate }} + ${{ parseFloat(rate.insurance_cost).toFixed(2) }} ins.
                        </div>
                        <div v-if="rate.estimated_delivery" class="text-xs text-gray-500 dark:text-gray-400">
                            {{ rate.estimated_delivery }}
                        </div>
                    </div>
                </div>

                <!-- Badges -->
                <div class="mt-2 flex items-center gap-2">
                    <span v-if="rate.is_estimate" class="px-2 py-0.5 bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200 rounded text-xs">
                        Estimate
                    </span>
                    <span v-if="rate.is_guaranteed" class="px-2 py-0.5 bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200 rounded text-xs">
                        Guaranteed
                    </span>
                    <span v-if="isCheapest(rate)" class="px-2 py-0.5 bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200 rounded text-xs">
                        Cheapest
                    </span>
                    <span v-if="isFastest(rate) && !isCheapest(rate)" class="px-2 py-0.5 bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200 rounded text-xs">
                        Fastest
                    </span>
                    <span v-if="rate.confirmation_type && rate.confirmation_type !== 'none'" class="px-2 py-0.5 bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300 rounded text-xs">
                        Sig. Required
                    </span>
                </div>
            </div>
        </div>

        <!-- Empty State -->
        <div v-else class="py-4 text-center">
            <p class="text-sm text-gray-500 dark:text-gray-400">
                No shipping rates available
            </p>
        </div>

        <!-- Shipping Options Section -->
        <div v-if="rates.length > 0" class="mt-4 pt-3 border-t border-gray-200 dark:border-gray-700">
            <button
                @click="optionsExpanded = !optionsExpanded"
                class="flex items-center justify-between w-full text-sm font-medium text-gray-700 dark:text-gray-300"
            >
                <span>Shipping Options</span>
                <svg
                    :class="{ 'rotate-180': optionsExpanded }"
                    class="w-4 h-4 transition-transform"
                    fill="none" stroke="currentColor" viewBox="0 0 24 24"
                >
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                </svg>
            </button>

            <div v-show="optionsExpanded" class="mt-3 space-y-4">
                <!-- Insurance Toggle -->
                <div>
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input
                            type="checkbox"
                            v-model="insuranceEnabled"
                            class="rounded border-gray-300 dark:border-gray-600 text-blue-600"
                        >
                        <span class="text-sm text-gray-700 dark:text-gray-300">Include shipping insurance</span>
                    </label>
                    <p v-if="insuranceThresholdNote" class="ml-6 text-xs text-gray-500 dark:text-gray-400 mt-1">
                        {{ insuranceThresholdNote }}
                    </p>
                    <p v-if="insuranceEnabled && itemValue > 0" class="ml-6 text-xs text-gray-500 mt-1">
                        Insured value: ${{ itemValue.toFixed(2) }}
                    </p>
                </div>

                <!-- Signature Confirmation -->
                <div>
                    <label class="block text-sm text-gray-700 dark:text-gray-300 mb-1">Signature Confirmation</label>
                    <select
                        v-model="confirmationType"
                        class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm"
                    >
                        <option value="none">None</option>
                        <option value="signature">Signature Required</option>
                        <option value="adult_signature">Adult Signature Required</option>
                    </select>
                    <p v-if="signatureThresholdNote" class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                        {{ signatureThresholdNote }}
                    </p>
                </div>

                <!-- Additional Item Cost -->
                <div>
                    <label class="block text-sm text-gray-700 dark:text-gray-300 mb-1">Additional Item Shipping Cost</label>
                    <div class="flex items-center gap-2">
                        <span class="text-sm text-gray-500">$</span>
                        <input
                            v-model.number="additionalItemCost"
                            type="number"
                            step="0.01"
                            min="0"
                            class="w-24 rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm"
                            placeholder="0.00"
                        >
                    </div>
                    <p v-if="suggestedAdditionalItemCost != null" class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                        Suggested: ${{ suggestedAdditionalItemCost.toFixed(2) }}
                        <template v-if="shippingSettings.additional_item_cost_mode === 'percentage'">
                            ({{ shippingSettings.additional_item_cost_percent }}% of rate)
                        </template>
                        <template v-else>
                            (fixed amount)
                        </template>
                    </p>
                </div>
            </div>
        </div>

        <!-- Selected Rate Summary -->
        <div v-if="selectedRate" class="mt-4 pt-3 border-t border-gray-200 dark:border-gray-700">
            <div class="flex items-center justify-between text-sm">
                <span class="text-gray-600 dark:text-gray-400">Selected shipping:</span>
                <span class="font-semibold text-gray-900 dark:text-gray-100">
                    {{ selectedRate.carrier }} {{ selectedRate.service }} - {{ selectedRate.insurance_included ? selectedRate.formatted_total_rate : selectedRate.formatted_rate }}
                </span>
            </div>
            <div v-if="additionalItemCost != null && additionalItemCost > 0" class="flex items-center justify-between text-xs mt-1">
                <span class="text-gray-500 dark:text-gray-400">Additional item cost:</span>
                <span class="text-gray-700 dark:text-gray-300">${{ parseFloat(additionalItemCost).toFixed(2) }}</span>
            </div>
        </div>
    </div>
</template>
