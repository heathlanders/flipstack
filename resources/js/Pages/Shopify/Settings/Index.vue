<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
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
    shopifyConfig: {
        type: Object,
        default: () => ({})
    },
    locations: {
        type: Array,
        default: () => []
    }
});

const importLocationId = ref(props.locations?.[0]?.id ?? null);

const isConnected = computed(() => props.integrationStatus?.connected);
const showDisconnectModal = ref(false);
const connectForm = useForm({
    shop_domain: '',
});

const connectToShopify = () => {
    connectForm.get(route('shopify.auth.install'), {
        preserveState: true,
    });
};

const disconnect = () => {
    router.post(route('shopify.auth.disconnect'), {}, {
        onSuccess: () => {
            showDisconnectModal.value = false;
        }
    });
};

const toggleAutoSync = () => {
    router.post(route('marketplaces.shopify.auto-sync.toggle'));
};

const importingProducts = ref(false);

const startImport = () => {
    importingProducts.value = true;
    router.post(route('marketplaces.shopify.import.start'), {
        skip_existing_skus: true,
        location_id: importLocationId.value,
    }, {
        preserveScroll: true,
        onFinish: () => { importingProducts.value = false; }
    });
};

const cards = computed(() => [
    {
        title: 'Products',
        description: 'View products ready to sync to Shopify.',
        icon: 'fas fa-box',
        route: 'marketplaces.shopify.products',
        badge: null,
        badgeClass: '',
    },
    {
        title: 'Sync Logs',
        description: 'View sync operation history and troubleshoot issues.',
        icon: 'fas fa-history',
        route: 'marketplaces.shopify.logs',
        badge: null,
        badgeClass: '',
    },
    {
        title: 'Category & Collections',
        description: 'Configure product type source and map categories to Shopify collections.',
        icon: 'fas fa-sitemap',
        route: 'marketplaces.shopify.category-settings',
        badge: null,
        badgeClass: '',
    },
    {
        title: 'Link Products',
        description: 'Match Shopify listings to existing products by SKU, UPC, or title similarity.',
        icon: 'fas fa-link',
        route: 'marketplaces.shopify.match.review',
        badge: null,
        badgeClass: '',
    },
]);
</script>

<template>
    <AppLayout title="Shopify Marketplace">
        <Head title="Shopify - Marketplaces" />

        <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
            <!-- Header -->
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Shopify</h1>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                        Manage your Shopify marketplace integration
                    </p>
                </div>
                <div class="flex items-center gap-3">
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

            <!-- Shop info -->
            <div v-if="integrationStatus.shop_name" class="mb-6 text-sm text-gray-500 dark:text-gray-400">
                <span class="font-medium">Store:</span> {{ integrationStatus.shop_name }}
                <span class="ml-4"><span class="font-medium">Domain:</span> {{ integrationStatus.shop_domain }}</span>
            </div>

            <!-- Connect Card -->
            <div v-if="!isConnected" class="mb-8 bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-6">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Connect your Shopify Store</h2>
                <div v-if="!shopifyConfig.configured" class="mb-4 p-3 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-lg text-sm text-amber-700 dark:text-amber-400">
                    Shopify API credentials are not configured. Please set SHOPIFY_API_KEY and SHOPIFY_API_SECRET in your environment.
                </div>
                <div v-else class="flex items-end gap-4">
                    <div class="flex-1">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Shopify Store Domain</label>
                        <input
                            v-model="connectForm.shop_domain"
                            type="text"
                            placeholder="your-store.myshopify.com"
                            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100"
                        />
                        <p v-if="connectForm.errors.shop_domain" class="mt-1 text-sm text-red-600">{{ connectForm.errors.shop_domain }}</p>
                    </div>
                    <button
                        @click="connectToShopify"
                        :disabled="!connectForm.shop_domain || connectForm.processing"
                        class="px-4 py-2 bg-green-600 hover:bg-green-700 disabled:opacity-50 text-white font-medium rounded-lg"
                    >
                        Connect to Shopify
                    </button>
                </div>
            </div>

            <!-- Auto-Sync Toggle & Disconnect -->
            <div v-if="isConnected" class="mb-8 flex items-center gap-6">
                <div class="flex items-center gap-3">
                    <button
                        @click="toggleAutoSync"
                        :class="[
                            'relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none',
                            integrationStatus.auto_sync_enabled ? 'bg-green-600' : 'bg-gray-200 dark:bg-gray-600'
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
                        Inventory will not push to Shopify
                    </span>
                </div>
                <button
                    @click="showDisconnectModal = true"
                    class="px-4 py-2 border border-red-300 text-red-600 hover:bg-red-50 dark:border-red-700 dark:text-red-400 dark:hover:bg-red-900/20 rounded-lg text-sm"
                >
                    Disconnect Store
                </button>
            </div>

            <!-- Action Cards Grid -->
            <div v-if="isConnected" class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <Link
                    v-for="card in cards"
                    :key="card.route"
                    :href="route(card.route)"
                    class="block p-6 bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 hover:border-blue-300 dark:hover:border-blue-600 hover:shadow-md transition-all"
                >
                    <div class="flex items-start justify-between">
                        <div class="flex items-center gap-3">
                            <div class="flex-shrink-0 w-10 h-10 rounded-lg bg-green-50 dark:bg-green-900/20 flex items-center justify-center">
                                <i :class="card.icon" class="text-green-600 dark:text-green-400"></i>
                            </div>
                            <div>
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">{{ card.title }}</h3>
                            </div>
                        </div>
                        <span
                            v-if="card.badge"
                            class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium"
                            :class="card.badgeClass"
                        >
                            {{ card.badge }}
                        </span>
                    </div>
                    <p class="mt-3 text-sm text-gray-600 dark:text-gray-400">{{ card.description }}</p>
                </Link>

                <!-- Import Products Card (action) -->
                <div class="block w-full text-left p-6 bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700">
                    <div class="flex items-center gap-3">
                        <div class="flex-shrink-0 w-10 h-10 rounded-lg bg-green-50 dark:bg-green-900/20 flex items-center justify-center">
                            <i class="fas fa-download text-green-600 dark:text-green-400"></i>
                        </div>
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                                {{ importingProducts ? 'Importing...' : 'Import Products' }}
                            </h3>
                        </div>
                    </div>
                    <p class="mt-3 text-sm text-gray-600 dark:text-gray-400">
                        Pull existing products from Shopify into the app for matching and inventory sync.
                    </p>

                    <div v-if="locations.length" class="mt-4">
                        <label for="shopify_import_location" class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Labeling work-order location
                        </label>
                        <select
                            id="shopify_import_location"
                            v-model="importLocationId"
                            :disabled="importingProducts"
                            class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 text-sm focus:ring-blue-500 focus:border-blue-500 disabled:opacity-50"
                        >
                            <option :value="null">No work order (skip labeling)</option>
                            <option v-for="loc in locations" :key="loc.id" :value="loc.id">{{ loc.name }}</option>
                        </select>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            Newly created products will be added to a labeling work order.
                        </p>
                    </div>

                    <button
                        @click="startImport"
                        :disabled="importingProducts"
                        class="mt-4 inline-flex items-center gap-2 px-4 py-2 bg-green-600 hover:bg-green-700 disabled:opacity-50 text-white text-sm font-medium rounded-lg"
                    >
                        <i v-if="importingProducts" class="fas fa-spinner fa-spin"></i>
                        {{ importingProducts ? 'Importing...' : 'Start Import' }}
                    </button>
                </div>
            </div>

            <!-- Quick Stats -->
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

        <!-- Disconnect Modal -->
        <Teleport to="body">
            <div v-if="showDisconnectModal" class="fixed inset-0 z-50 flex items-center justify-center">
                <div class="fixed inset-0 bg-black/50" @click="showDisconnectModal = false"></div>
                <div class="relative bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-md w-full mx-4 p-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">Disconnect Shopify Store?</h3>
                    <p class="text-sm text-gray-600 dark:text-gray-400 mb-6">
                        This will deactivate the connection. Existing listings will remain on Shopify but will no longer sync.
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
