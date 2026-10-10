<script setup>
import { ref, computed } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { route } from 'ziggy-js';

const props = defineProps({
    baseCurrency: {
        type: String,
        default: 'USD',
    },
    rates: {
        type: Array,
        default: () => [],
    },
    supportedCurrencies: {
        type: Array,
        default: () => [],
    },
    organizationId: {
        type: [Number, String],
        default: null,
    },
});

// Sync state
const syncing = ref(false);

function syncRates() {
    syncing.value = true;
    router.post(route('settings.currency.sync'), {}, {
        preserveScroll: true,
        onFinish: () => {
            syncing.value = false;
        },
    });
}

// Converter state
const converterAmount = ref(100);
const converterFrom = ref('USD');
const converterTo = ref('EUR');
const converting = ref(false);
const conversionResult = ref(null);
const conversionError = ref(null);

function convertCurrency() {
    converting.value = true;
    conversionResult.value = null;
    conversionError.value = null;

    fetch(route('settings.currency.convert'), {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
            'Accept': 'application/json',
        },
        body: JSON.stringify({
            amount: converterAmount.value,
            from: converterFrom.value,
            to: converterTo.value,
        }),
    })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                conversionResult.value = data;
            } else {
                conversionError.value = data.error || 'Conversion failed.';
            }
        })
        .catch(() => {
            conversionError.value = 'An error occurred during conversion.';
        })
        .finally(() => {
            converting.value = false;
        });
}

function formatDate(dateStr) {
    if (!dateStr) return '--';
    const d = new Date(dateStr);
    return d.toLocaleString();
}
</script>

<template>
    <AppLayout title="Currency Settings">
        <Head title="Currency Settings" />

        <div class="max-w-7xl mx-auto py-10 sm:px-6 lg:px-8">
            <!-- Page Header -->
            <div class="mb-8">
                <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">
                    Currency Settings
                </h1>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                    Manage exchange rates and convert between currencies.
                </p>
            </div>

            <!-- Organization Base Currency -->
            <div class="mb-8 bg-white dark:bg-gray-800 shadow rounded-lg p-6">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-2">
                    Base Currency
                </h2>
                <p class="text-sm text-gray-600 dark:text-gray-400 mb-3">
                    Your organization's base currency for all financial calculations.
                </p>
                <div class="inline-flex items-center gap-2 px-4 py-2 bg-blue-50 dark:bg-blue-900/30 rounded-md">
                    <span class="text-lg font-bold text-blue-700 dark:text-blue-300">{{ baseCurrency }}</span>
                    <span class="text-sm text-blue-600 dark:text-blue-400">
                        {{ supportedCurrencies.find(c => c.code === baseCurrency)?.label || baseCurrency }}
                    </span>
                </div>
            </div>

            <!-- Exchange Rates Table -->
            <div class="mb-8 bg-white dark:bg-gray-800 shadow rounded-lg p-6">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                        Current Exchange Rates
                    </h2>
                    <button
                        @click="syncRates"
                        :disabled="syncing"
                        class="inline-flex items-center px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-md hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed transition"
                    >
                        <svg v-if="syncing" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        {{ syncing ? 'Syncing...' : 'Sync Rates' }}
                    </button>
                </div>

                <div v-if="rates.length === 0" class="text-center py-8 text-gray-500 dark:text-gray-400">
                    No exchange rates available. Click "Sync Rates" to fetch the latest rates.
                </div>

                <div v-else class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                    Currency Pair
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                    Rate
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                    Source
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                    Last Updated
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                            <tr v-for="rate in rates" :key="rate.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-gray-100">
                                    {{ rate.base_currency }} / {{ rate.target_currency }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300 font-mono">
                                    {{ parseFloat(rate.rate).toFixed(8) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                    <span
                                        class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                                        :class="rate.source === 'manual'
                                            ? 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-300'
                                            : 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300'"
                                    >
                                        {{ rate.source }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                    {{ formatDate(rate.fetched_at) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Currency Converter -->
            <div class="mb-8 bg-white dark:bg-gray-800 shadow rounded-lg p-6">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">
                    Currency Converter
                </h2>

                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Amount
                        </label>
                        <input
                            v-model.number="converterAmount"
                            type="number"
                            min="0"
                            step="0.01"
                            class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                        />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            From
                        </label>
                        <select
                            v-model="converterFrom"
                            class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                        >
                            <option v-for="currency in supportedCurrencies" :key="currency.code" :value="currency.code">
                                {{ currency.code }} - {{ currency.label }}
                            </option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            To
                        </label>
                        <select
                            v-model="converterTo"
                            class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                        >
                            <option v-for="currency in supportedCurrencies" :key="currency.code" :value="currency.code">
                                {{ currency.code }} - {{ currency.label }}
                            </option>
                        </select>
                    </div>
                    <div>
                        <button
                            @click="convertCurrency"
                            :disabled="converting"
                            class="w-full inline-flex justify-center items-center px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-md hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed transition"
                        >
                            {{ converting ? 'Converting...' : 'Convert' }}
                        </button>
                    </div>
                </div>

                <!-- Conversion Result -->
                <div v-if="conversionResult" class="mt-4 p-4 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-md">
                    <p class="text-sm text-green-800 dark:text-green-300">
                        <span class="font-semibold">{{ conversionResult.original_amount.toFixed(2) }} {{ conversionResult.from }}</span>
                        =
                        <span class="font-semibold">{{ conversionResult.formatted }}</span>
                        ({{ conversionResult.converted_amount.toFixed(2) }} {{ conversionResult.to }})
                    </p>
                </div>

                <!-- Conversion Error -->
                <div v-if="conversionError" class="mt-4 p-4 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-md">
                    <p class="text-sm text-red-800 dark:text-red-300">{{ conversionError }}</p>
                </div>
            </div>

            <!-- Supported Currencies -->
            <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">
                    Supported Currencies
                </h2>
                <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-3">
                    <div
                        v-for="currency in supportedCurrencies"
                        :key="currency.code"
                        class="flex items-center gap-2 p-3 bg-gray-50 dark:bg-gray-700 rounded-md"
                    >
                        <span class="text-sm font-bold text-gray-900 dark:text-gray-100">{{ currency.code }}</span>
                        <span class="text-xs text-gray-500 dark:text-gray-400">{{ currency.label }}</span>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
