<script setup>
import { ref, computed } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import axios from 'axios';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    credential: {
        type: Object,
        default: null
    },
    policies: {
        type: Object,
        default: null
    },
    locations: {
        type: Array,
        default: null
    },
    inventoryLocations: {
        type: Array,
        default: () => []
    },
    ebayConfig: {
        type: Object,
        default: () => ({ configured: false, environment: 'sandbox', is_sandbox: true })
    }
});

const form = useForm({
    marketplace_id: props.credential?.marketplace_id || 'EBAY_US',
    fulfillment_policy_id: props.credential?.fulfillment_policy_id || '',
    payment_policy_id: props.credential?.payment_policy_id || '',
    return_policy_id: props.credential?.return_policy_id || '',
    merchant_location_key: props.credential?.merchant_location_key || '',
});

const showDisconnectModal = ref(false);
const isDisconnecting = ref(false);
const ebayConnecting = ref(false);
const selectedInventoryLocation = ref('');
const isSyncingLocation = ref(false);
const locationError = ref('');

const isConnected = computed(() => props.credential?.has_token);

const selectedLocationDetails = computed(() => {
    if (!selectedInventoryLocation.value) return null;
    return props.inventoryLocations.find(loc => loc.id === selectedInventoryLocation.value);
});

const saveSetup = () => {
    form.post(route('marketplaces.ebay.setup.save'), {
        preserveScroll: true
    });
};

const connectEbay = () => {
    ebayConnecting.value = true;
    axios.post(route('integrations.ebay.connect'))
        .then(response => {
            window.location.href = response.data.redirect_url;
        })
        .catch(() => {
            ebayConnecting.value = false;
        });
};

const disconnect = () => {
    isDisconnecting.value = true;
    const env = props.credential?.environment || 'production';
    router.post(route('integrations.ebay.disconnect', env), {}, {
        preserveScroll: true,
        onFinish: () => {
            isDisconnecting.value = false;
            showDisconnectModal.value = false;
        }
    });
};

const syncLocationToEbay = () => {
    if (!selectedInventoryLocation.value) return;

    isSyncingLocation.value = true;
    locationError.value = '';

    router.post(route('marketplaces.ebay.location.create'), {
        inventory_location_id: selectedInventoryLocation.value
    }, {
        preserveScroll: true,
        onSuccess: () => {
            selectedInventoryLocation.value = '';
        },
        onError: (errors) => {
            locationError.value = errors.location || 'Failed to sync location';
        },
        onFinish: () => {
            isSyncingLocation.value = false;
        }
    });
};
</script>

<template>
    <AppLayout title="eBay Setup">
        <Head title="eBay Setup - Marketplaces" />

        <div class="max-w-4xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
            <!-- Breadcrumb -->
            <nav class="flex mb-6" aria-label="Breadcrumb">
                <ol class="flex items-center space-x-2 text-sm text-gray-500 dark:text-gray-400">
                    <li><Link :href="route('marketplaces.ebay.index')" class="hover:text-gray-700 dark:hover:text-gray-200">eBay</Link></li>
                    <li><i class="fas fa-chevron-right text-xs mx-1"></i></li>
                    <li class="text-gray-900 dark:text-white font-medium">Setup</li>
                </ol>
            </nav>

            <!-- Connection Status Card -->
            <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 overflow-hidden mb-6">
                <div class="px-6 py-5 border-b border-gray-200 dark:border-gray-700">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-4">
                            <div class="w-12 h-12 bg-gradient-to-br from-blue-500 to-green-500 rounded-xl flex items-center justify-center">
                                <span class="text-white font-bold text-lg">eBay</span>
                            </div>
                            <div>
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">eBay Connection</h3>
                                <p class="text-sm text-gray-500 dark:text-gray-400">
                                    {{ isConnected ? 'Connected' : 'Not connected' }}
                                    <span class="text-xs opacity-75">({{ ebayConfig.is_sandbox ? 'Sandbox' : 'Production' }})</span>
                                </p>
                                <p v-if="isConnected && (credential?.account_username || credential?.seller_username)" class="text-xs text-gray-400 dark:text-gray-500">
                                    Seller: {{ credential.account_username || credential.seller_username }}
                                </p>
                            </div>
                        </div>
                        <span
                            :class="isConnected ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-400' : 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400'"
                            class="px-3 py-1 rounded-full text-sm font-medium"
                        >
                            {{ isConnected ? 'Connected' : 'Disconnected' }}
                        </span>
                    </div>
                </div>

                <div class="px-6 py-4 flex flex-wrap items-center gap-3">
                    <template v-if="isConnected">
                        <button
                            @click="connectEbay"
                            :disabled="ebayConnecting"
                            class="px-4 py-2 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 text-sm font-medium rounded-lg hover:bg-gray-50 dark:hover:bg-gray-600 transition disabled:opacity-50"
                        >
                            Re-authenticate
                        </button>
                        <button
                            @click="showDisconnectModal = true"
                            class="px-4 py-2 text-red-600 dark:text-red-400 hover:text-red-800 text-sm font-medium transition"
                        >
                            Disconnect
                        </button>
                    </template>
                    <template v-else>
                        <button
                            v-if="ebayConfig.configured"
                            @click="connectEbay"
                            :disabled="ebayConnecting"
                            class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition disabled:opacity-50"
                        >
                            {{ ebayConnecting ? 'Connecting...' : 'Connect to eBay' }}
                        </button>
                        <span v-else class="text-sm text-gray-500 dark:text-gray-400">
                            eBay integration is not configured. Please contact the administrator.
                        </span>
                    </template>
                </div>
            </div>

            <!-- Merchant Location -->
            <div v-if="isConnected" class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 overflow-hidden mb-6">
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Merchant Location</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400">eBay requires a merchant location for inventory-based listings</p>
                </div>

                <div class="p-6 space-y-4">
                    <!-- Current Location Status -->
                    <div v-if="credential?.merchant_location_key" class="flex items-center gap-3 p-3 bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-800 rounded-lg">
                        <i class="fas fa-check-circle text-emerald-600 dark:text-emerald-400"></i>
                        <div>
                            <p class="text-sm font-medium text-emerald-800 dark:text-emerald-300">Location configured</p>
                            <p class="text-xs text-emerald-600 dark:text-emerald-400">Key: {{ credential.merchant_location_key }}</p>
                        </div>
                    </div>
                    <div v-else class="flex items-center gap-3 p-3 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-lg">
                        <i class="fas fa-exclamation-triangle text-amber-600 dark:text-amber-400"></i>
                        <div>
                            <p class="text-sm font-medium text-amber-800 dark:text-amber-300">No location configured</p>
                            <p class="text-xs text-amber-600 dark:text-amber-400">You must sync a location before creating listings</p>
                        </div>
                    </div>

                    <!-- Location Sync Form -->
                    <div v-if="inventoryLocations.length > 0" class="space-y-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Select Inventory Location</label>
                            <select
                                v-model="selectedInventoryLocation"
                                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 text-sm"
                            >
                                <option value="">-- Select a location --</option>
                                <option v-for="loc in inventoryLocations" :key="loc.id" :value="loc.id">
                                    {{ loc.name }} ({{ loc.city }}, {{ loc.state }})
                                </option>
                            </select>
                        </div>

                        <!-- Selected Location Preview -->
                        <div v-if="selectedLocationDetails" class="p-3 bg-gray-50 dark:bg-gray-700/50 rounded-lg text-sm">
                            <p class="font-medium text-gray-900 dark:text-gray-100">{{ selectedLocationDetails.name }}</p>
                            <p class="text-gray-600 dark:text-gray-400">{{ selectedLocationDetails.address_line1 }}</p>
                            <p v-if="selectedLocationDetails.address_line2" class="text-gray-600 dark:text-gray-400">{{ selectedLocationDetails.address_line2 }}</p>
                            <p class="text-gray-600 dark:text-gray-400">{{ selectedLocationDetails.city }}, {{ selectedLocationDetails.state }} {{ selectedLocationDetails.postal_code }}</p>
                        </div>

                        <!-- Error Message -->
                        <p v-if="locationError" class="text-sm text-red-600 dark:text-red-400">{{ locationError }}</p>

                        <button
                            @click="syncLocationToEbay"
                            :disabled="!selectedInventoryLocation || isSyncingLocation"
                            class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            {{ isSyncingLocation ? 'Syncing...' : 'Sync to eBay' }}
                        </button>
                    </div>

                    <!-- No Locations Available -->
                    <div v-else class="text-sm text-gray-600 dark:text-gray-400">
                        <p>No inventory locations with complete address data found.</p>
                        <Link :href="route('inventory.locations.create')" class="text-blue-600 hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300">
                            Create a new location
                        </Link>
                    </div>
                </div>
            </div>

            <!-- Policy Configuration -->
            <div v-if="isConnected" class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Business Policies</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Configure your eBay business policies for listing creation</p>
                </div>

                <div class="p-6 space-y-5">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Fulfillment Policy ID</label>
                        <select v-if="policies?.fulfillment?.length" v-model="form.fulfillment_policy_id" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 text-sm">
                            <option value="">-- Select --</option>
                            <option v-for="p in policies.fulfillment" :key="p.fulfillmentPolicyId" :value="p.fulfillmentPolicyId">{{ p.name }}</option>
                        </select>
                        <input v-else v-model="form.fulfillment_policy_id" type="text" placeholder="Enter policy ID" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 text-sm" />
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Payment Policy ID</label>
                        <select v-if="policies?.payment?.length" v-model="form.payment_policy_id" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 text-sm">
                            <option value="">-- Select --</option>
                            <option v-for="p in policies.payment" :key="p.paymentPolicyId" :value="p.paymentPolicyId">{{ p.name }}</option>
                        </select>
                        <input v-else v-model="form.payment_policy_id" type="text" placeholder="Enter policy ID" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 text-sm" />
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Return Policy ID</label>
                        <select v-if="policies?.return?.length" v-model="form.return_policy_id" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 text-sm">
                            <option value="">-- Select --</option>
                            <option v-for="p in policies.return" :key="p.returnPolicyId" :value="p.returnPolicyId">{{ p.name }}</option>
                        </select>
                        <input v-else v-model="form.return_policy_id" type="text" placeholder="Enter policy ID" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 text-sm" />
                    </div>

                    <div class="pt-2">
                        <button
                            @click="saveSetup"
                            :disabled="form.processing"
                            class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition disabled:opacity-50"
                        >
                            {{ form.processing ? 'Saving...' : 'Save Configuration' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Disconnect Modal -->
        <Teleport to="body">
            <div v-if="showDisconnectModal" class="fixed inset-0 z-50 overflow-y-auto">
                <div class="flex items-center justify-center min-h-screen px-4">
                    <div class="fixed inset-0 bg-black/50" @click="showDisconnectModal = false"></div>
                    <div class="relative bg-white dark:bg-gray-800 rounded-xl shadow-xl max-w-md w-full p-6">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-2">Disconnect eBay</h3>
                        <p class="text-sm text-gray-600 dark:text-gray-400 mb-6">
                            Are you sure? This will remove your access token but keep your listing data.
                        </p>
                        <div class="flex justify-end gap-3">
                            <button @click="showDisconnectModal = false" class="px-4 py-2 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600 transition">Cancel</button>
                            <button @click="disconnect" :disabled="isDisconnecting" class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white text-sm font-medium rounded-lg transition disabled:opacity-50">
                                {{ isDisconnecting ? 'Disconnecting...' : 'Disconnect' }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </Teleport>
    </AppLayout>
</template>
