<script setup>
import { ref, computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import axios from 'axios';

const props = defineProps({
    credential: Object,
    policies: {
        type: Object,
        default: () => ({
            fulfillment: [],
            payment: [],
            return: [],
        }),
    },
    error: String,
});

const activeTab = ref('fulfillment');
const syncing = ref(false);
const syncMessage = ref(null);
const showCreateModal = ref(false);
const editingPolicy = ref(null);
const saving = ref(false);

const tabs = [
    { key: 'fulfillment', label: 'Shipping/Fulfillment' },
    { key: 'payment', label: 'Payment' },
    { key: 'return', label: 'Return' },
];

const currentPolicies = computed(() => {
    return props.policies[activeTab.value] || [];
});

const syncPolicies = async () => {
    syncing.value = true;
    syncMessage.value = null;

    try {
        const response = await axios.post(route('marketplaces.ebay.policies.sync'));

        if (response.data.success) {
            syncMessage.value = {
                type: 'success',
                text: response.data.message,
            };
            // Reload page to get fresh data
            window.location.reload();
        } else {
            syncMessage.value = {
                type: 'error',
                text: response.data.error || 'Failed to sync policies',
            };
        }
    } catch (e) {
        syncMessage.value = {
            type: 'error',
            text: e.response?.data?.error || 'Failed to sync policies from eBay',
        };
    } finally {
        syncing.value = false;
    }
};

const setDefaultPolicy = async (policy) => {
    try {
        const response = await axios.post(route('marketplaces.ebay.policies.default', policy.id));

        if (response.data.success) {
            window.location.reload();
        }
    } catch (e) {
        alert(e.response?.data?.error || 'Failed to set default policy');
    }
};

const deletePolicy = async (policy) => {
    if (!confirm(`Are you sure you want to delete "${policy.name}"? This will also delete it from eBay.`)) {
        return;
    }

    try {
        const response = await axios.delete(route('marketplaces.ebay.policies.destroy', policy.id));

        if (response.data.success) {
            window.location.reload();
        }
    } catch (e) {
        alert(e.response?.data?.error || 'Failed to delete policy');
    }
};

const formatDate = (dateString) => {
    if (!dateString) return 'Never';
    return new Date(dateString).toLocaleString();
};
</script>

<template>
    <AppLayout title="eBay Policies">
        <Head title="eBay Policies" />

        <div class="py-6">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <!-- Header -->
                <div class="flex items-center justify-between mb-6">
                    <div>
                        <div class="flex items-center gap-3">
                            <Link
                                :href="route('marketplaces.ebay.index')"
                                class="text-gray-500 hover:text-gray-700 dark:text-gray-400"
                            >
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                                </svg>
                            </Link>
                            <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">
                                eBay Business Policies
                            </h1>
                        </div>
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                            Manage your shipping, payment, and return policies synced from eBay
                        </p>
                    </div>

                    <div class="flex items-center gap-3">
                        <Link
                            :href="route('marketplaces.ebay.policy-rules.index')"
                            class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700"
                        >
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"/>
                            </svg>
                            Policy Rules
                        </Link>
                        <button
                            @click="syncPolicies"
                            :disabled="syncing"
                            class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700 disabled:opacity-50"
                        >
                            <svg :class="{'animate-spin': syncing}" class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                            </svg>
                            {{ syncing ? 'Syncing...' : 'Sync from eBay' }}
                        </button>
                    </div>
                </div>

                <!-- Sync Message -->
                <div
                    v-if="syncMessage"
                    :class="{
                        'bg-green-50 border-green-200 text-green-800': syncMessage.type === 'success',
                        'bg-red-50 border-red-200 text-red-800': syncMessage.type === 'error',
                    }"
                    class="mb-6 p-4 rounded-lg border"
                >
                    {{ syncMessage.text }}
                </div>

                <!-- Error State -->
                <div v-if="error" class="mb-6 p-4 bg-red-50 border border-red-200 rounded-lg text-red-800">
                    {{ error }}
                </div>

                <!-- Tabs -->
                <div class="border-b border-gray-200 dark:border-gray-700 mb-6">
                    <nav class="flex space-x-8">
                        <button
                            v-for="tab in tabs"
                            :key="tab.key"
                            @click="activeTab = tab.key"
                            :class="{
                                'border-blue-500 text-blue-600 dark:text-blue-400': activeTab === tab.key,
                                'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300': activeTab !== tab.key,
                            }"
                            class="py-4 px-1 border-b-2 font-medium text-sm"
                        >
                            {{ tab.label }}
                            <span class="ml-2 px-2 py-0.5 rounded-full text-xs bg-gray-100 dark:bg-gray-800">
                                {{ policies[tab.key]?.length || 0 }}
                            </span>
                        </button>
                    </nav>
                </div>

                <!-- Policies Grid -->
                <div v-if="currentPolicies.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <div
                        v-for="policy in currentPolicies"
                        :key="policy.id"
                        class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-5"
                    >
                        <div class="flex items-start justify-between mb-3">
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2">
                                    <h3 class="font-semibold text-gray-900 dark:text-gray-100 truncate">
                                        {{ policy.name }}
                                    </h3>
                                    <span
                                        v-if="policy.is_default"
                                        class="px-2 py-0.5 bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200 rounded text-xs"
                                    >
                                        Default
                                    </span>
                                </div>
                                <p v-if="policy.description" class="mt-1 text-sm text-gray-500 dark:text-gray-400 line-clamp-2">
                                    {{ policy.description }}
                                </p>
                            </div>
                        </div>

                        <div class="text-xs text-gray-500 dark:text-gray-400 space-y-1">
                            <div class="flex items-center gap-2">
                                <span>eBay ID:</span>
                                <code class="bg-gray-100 dark:bg-gray-700 px-1 rounded">{{ policy.ebay_policy_id }}</code>
                            </div>
                            <div>
                                Last Synced: {{ formatDate(policy.last_synced_at) }}
                            </div>
                        </div>

                        <div class="mt-4 pt-3 border-t border-gray-200 dark:border-gray-700 flex items-center justify-between">
                            <button
                                v-if="!policy.is_default"
                                @click="setDefaultPolicy(policy)"
                                class="text-xs text-blue-600 hover:text-blue-800 dark:text-blue-400"
                            >
                                Set as Default
                            </button>
                            <span v-else class="text-xs text-gray-400">Current Default</span>

                            <button
                                @click="deletePolicy(policy)"
                                class="text-xs text-red-600 hover:text-red-800 dark:text-red-400"
                            >
                                Delete
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Empty State -->
                <div v-else class="text-center py-12">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-gray-100">
                        No {{ activeTab }} policies
                    </h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Click "Sync from eBay" to import your business policies.
                    </p>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
