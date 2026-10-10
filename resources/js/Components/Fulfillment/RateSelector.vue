<template>
    <div class="bg-white rounded-lg border border-gray-200">
        <div class="px-4 py-3 border-b border-gray-200">
            <h4 class="text-sm font-medium text-gray-900">Shipping Rates</h4>
            <p class="text-xs text-gray-500 mt-0.5">Select a shipping rate to purchase a label</p>
        </div>

        <div v-if="loading" class="p-6 text-center">
            <svg class="animate-spin h-6 w-6 text-indigo-600 mx-auto" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            <p class="mt-2 text-sm text-gray-500">Fetching rates...</p>
        </div>

        <div v-else-if="rates.length === 0" class="p-6 text-center text-sm text-gray-500">
            No rates available. Enter package dimensions and click "Get Rates".
        </div>

        <div v-else class="divide-y divide-gray-100 max-h-80 overflow-y-auto">
            <label
                v-for="rate in sortedRates"
                :key="rate.id"
                :class="['flex items-center justify-between px-4 py-3 cursor-pointer hover:bg-gray-50 transition-colors', selectedRateId === rate.easypost_rate_id ? 'bg-indigo-50 ring-1 ring-indigo-200' : '']"
            >
                <div class="flex items-center space-x-3">
                    <input
                        type="radio"
                        :value="rate.easypost_rate_id"
                        v-model="selectedRate"
                        class="h-4 w-4 text-indigo-600 border-gray-300 focus:ring-indigo-500"
                    />
                    <div>
                        <p class="text-sm font-medium text-gray-900">
                            {{ rate.carrier }} - {{ rate.service }}
                        </p>
                        <p class="text-xs text-gray-500">
                            <span v-if="rate.estimated_days">{{ rate.estimated_days }} business day{{ rate.estimated_days > 1 ? 's' : '' }}</span>
                            <span v-if="rate.delivery_date"> (est. {{ formatDate(rate.delivery_date) }})</span>
                            <span v-if="rate.delivery_guarantee" class="ml-1 text-green-600 font-medium">Guaranteed</span>
                        </p>
                    </div>
                </div>
                <div class="text-right">
                    <p class="text-sm font-semibold text-gray-900">${{ Number(rate.rate).toFixed(2) }}</p>
                </div>
            </label>
        </div>

        <div v-if="rates.length > 0" class="px-4 py-3 border-t border-gray-200 flex justify-end">
            <button
                @click="$emit('select', selectedRate)"
                :disabled="!selectedRate"
                :class="['px-4 py-2 text-sm font-medium rounded-md', selectedRate ? 'bg-indigo-600 text-white hover:bg-indigo-700' : 'bg-gray-100 text-gray-400 cursor-not-allowed']"
            >
                Purchase Label
            </button>
        </div>
    </div>
</template>

<script setup>
import { computed, ref } from 'vue';

const props = defineProps({
    rates: { type: Array, default: () => [] },
    loading: { type: Boolean, default: false },
    selectedRateId: { type: String, default: null },
});

const emit = defineEmits(['select']);

const selectedRate = ref(props.selectedRateId);

const sortedRates = computed(() => {
    return [...props.rates].sort((a, b) => Number(a.rate) - Number(b.rate));
});

function formatDate(date) {
    if (!date) return '';
    return new Date(date).toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
}
</script>
