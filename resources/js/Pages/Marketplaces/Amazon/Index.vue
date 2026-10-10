<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    integrationStatus: {
        type: Object,
        default: () => ({})
    },
    stats: {
        type: Object,
        default: () => ({})
    },
    amazonConfig: {
        type: Object,
        default: () => ({})
    }
});

const isConnected = computed(() => props.integrationStatus?.connected);
const showDisconnectModal = ref(false);

const connectToAmazon = () => {
    window.location.href = route('amazon.auth.install');
};

const disconnect = () => {
    router.post(route('amazon.auth.disconnect'), {}, {
        onSuccess: () => {
            showDisconnectModal.value = false;
        }
    });
};

const toggleAutoSync = () => {
    router.post(route('marketplaces.amazon.auto-sync.toggle'));
};

const importingProducts = ref(false);
const startImport = () => {
    importingProducts.value = true;
    router.post(route('marketplaces.amazon.import.start'), {}, {
        preserveScroll: true,
        onFinish: () => { importingProducts.value = false; }
    });
};

const cards = computed(() => [
    {
        title: 'Products',
        description: 'View products available to list on Amazon.',
        icon: 'fab fa-amazon',
        route: 'marketplaces.amazon.products',
    },
    {
        title: 'Sync Logs',
        description: 'View Amazon SP-API operation history.',
        icon: 'fas fa-history',
        route: 'marketplaces.amazon.logs',
    },
]);
</script>

<template>
    <AppLayout title="Amazon Marketplace">
        <Head title="Amazon - Marketplaces" />

        <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Amazon</h1>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                        Manage your Amazon Selling Partner integration
                    </p>
                </div>
                <div class="flex items-center gap-3">
                    <span v-if="amazonConfig.environment === 'sandbox'" class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-medium bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400">
                        Sandbox
                    </span>
                    <span v-if="isConnected" class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-sm font-medium bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-400">
                        <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                        Connected
                    </span>
                    <span v-else class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-sm font-medium bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400">
                        <span class="w-2 h-2 rounded-full bg-gray-400"></span>
                        Disconnected
                    </span>
                </div>
            </div>

            <div v-if="isConnected" class="mb-6 text-sm text-gray-500 dark:text-gray-400">
                <span class="font-medium">Seller ID:</span> {{ integrationStatus.seller_id }}
                <span v-if="integrationStatus.marketplace_name" class="ml-4">
                    <span class="font-medium">Marketplace:</span>
                    {{ integrationStatus.marketplace_name }} ({{ integrationStatus.marketplace_id }})
                </span>
            </div>

            <div v-if="!isConnected" class="mb-8 bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-6">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Connect your Amazon Seller Account</h2>
                <div v-if="!amazonConfig.configured" class="mb-4 p-3 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-lg text-sm text-amber-700 dark:text-amber-400">
                    Amazon SP-API credentials are not configured. Set <code>AMAZON_LWA_CLIENT_ID</code>, <code>AMAZON_LWA_CLIENT_SECRET</code>, and <code>AMAZON_REDIRECT_URI</code> in your environment.
                </div>
                <p v-else class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                    You'll be redirected to Amazon Seller Central to authorize this app. Authorization grants
                    access to your listings, orders, inventory, and pricing.
                </p>
                <button
                    @click="connectToAmazon"
                    :disabled="!amazonConfig.configured"
                    class="px-4 py-2 bg-yellow-500 hover:bg-yellow-600 disabled:opacity-50 text-black font-medium rounded-lg"
                >
                    Connect to Amazon
                </button>
            </div>

            <div v-if="isConnected" class="mb-8 flex items-center gap-6">
                <div class="flex items-center gap-3">
                    <button
                        @click="toggleAutoSync"
                        :class="[
                            'relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none',
                            integrationStatus.auto_sync_enabled ? 'bg-yellow-500' : 'bg-gray-200 dark:bg-gray-600'
                        ]"
                    >
                        <span
                            :class="[
                                'pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out',
                                integrationStatus.auto_sync_enabled ? 'translate-x-5' : 'translate-x-0'
                            ]"
                        />
                    </button>
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">
                        Auto-Sync {{ integrationStatus.auto_sync_enabled ? 'Enabled' : 'Disabled' }}
                    </span>
                    <span v-if="!integrationStatus.auto_sync_enabled" class="text-xs text-amber-600 dark:text-amber-400">
                        Inventory will not push to Amazon
                    </span>
                </div>
                <button
                    @click="showDisconnectModal = true"
                    class="px-4 py-2 border border-red-300 text-red-600 hover:bg-red-50 dark:border-red-700 dark:text-red-400 dark:hover:bg-red-900/20 rounded-lg text-sm"
                >
                    Disconnect Account
                </button>
            </div>

            <div v-if="isConnected" class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <Link
                    v-for="card in cards"
                    :key="card.route"
                    :href="route(card.route)"
                    class="block p-6 bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 hover:border-yellow-400 dark:hover:border-yellow-500 hover:shadow-md transition-all"
                >
                    <div class="flex items-center gap-3">
                        <div class="flex-shrink-0 w-10 h-10 rounded-lg bg-yellow-50 dark:bg-yellow-900/20 flex items-center justify-center">
                            <i :class="card.icon" class="text-yellow-600 dark:text-yellow-400"></i>
                        </div>
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">{{ card.title }}</h3>
                    </div>
                    <p class="mt-3 text-sm text-gray-600 dark:text-gray-400">{{ card.description }}</p>
                </Link>

                <button
                    @click="startImport"
                    :disabled="importingProducts"
                    class="block w-full text-left p-6 bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 hover:border-yellow-400 dark:hover:border-yellow-500 hover:shadow-md transition-all disabled:opacity-50"
                >
                    <div class="flex items-center gap-3">
                        <div class="flex-shrink-0 w-10 h-10 rounded-lg bg-yellow-50 dark:bg-yellow-900/20 flex items-center justify-center">
                            <i class="fas fa-download text-yellow-600 dark:text-yellow-400"></i>
                        </div>
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                            {{ importingProducts ? 'Importing...' : 'Import Listings' }}
                        </h3>
                    </div>
                    <p class="mt-3 text-sm text-gray-600 dark:text-gray-400">
                        Pull existing Amazon listings into the app for matching and inventory sync.
                    </p>
                </button>
            </div>

            <div v-if="stats.total > 0" class="mt-8 grid grid-cols-2 md:grid-cols-4 gap-4">
                <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-4">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Total Listings</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ stats.total }}</p>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-4">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Active</p>
                    <p class="text-2xl font-bold text-emerald-600 dark:text-emerald-400">{{ stats.active }}</p>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-4">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Pending</p>
                    <p class="text-2xl font-bold text-amber-600 dark:text-amber-400">{{ stats.pending }}</p>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-4">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Errors</p>
                    <p class="text-2xl font-bold text-red-600 dark:text-red-400">{{ stats.error }}</p>
                </div>
            </div>
        </div>

        <Teleport to="body">
            <div v-if="showDisconnectModal" class="fixed inset-0 z-50 flex items-center justify-center">
                <div class="fixed inset-0 bg-black/50" @click="showDisconnectModal = false"></div>
                <div class="relative bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-md w-full mx-4 p-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">Disconnect Amazon Account?</h3>
                    <p class="text-sm text-gray-600 dark:text-gray-400 mb-6">
                        This deactivates the connection. Existing listings remain on Amazon but stop syncing.
                    </p>
                    <div class="flex justify-end gap-3">
                        <button
                            @click="showDisconnectModal = false"
                            class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-sm"
                        >
                            Cancel
                        </button>
                        <button
                            @click="disconnect"
                            class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg text-sm"
                        >
                            Disconnect
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>
    </AppLayout>
</template>
