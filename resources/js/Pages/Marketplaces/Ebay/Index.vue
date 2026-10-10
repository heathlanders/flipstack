<script setup>
import { computed } from 'vue';
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
    lastImport: {
        type: Object,
        default: null
    }
});

const isConnected = computed(() => props.integrationStatus?.connected);

const tokenStatusDisplay = computed(() => {
    if (!props.integrationStatus?.token_expires_at) return null;
    const expiresAt = new Date(props.integrationStatus.token_expires_at);
    const now = new Date();
    const hoursUntilExpiry = (expiresAt - now) / (1000 * 60 * 60);

    if (hoursUntilExpiry < 0) {
        return { label: 'Token Expired', class: 'text-red-600 dark:text-red-400' };
    }
    if (hoursUntilExpiry < 24) {
        return { label: 'Token Expiring Soon', class: 'text-amber-600 dark:text-amber-400' };
    }
    return { label: 'Valid until ' + expiresAt.toLocaleDateString(), class: 'text-emerald-600 dark:text-emerald-400' };
});

const cards = computed(() => [
    {
        title: 'Setup & Connection',
        description: 'Configure eBay credentials, policies, and connect your account.',
        icon: 'fas fa-cog',
        route: 'marketplaces.ebay.setup',
        badge: isConnected.value ? null : 'Not Connected',
        badgeClass: 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
    },
    {
        title: 'Import Inventory',
        description: 'Pull existing eBay store listings into the system.',
        icon: 'fas fa-download',
        route: 'marketplaces.ebay.import',
        badge: props.lastImport ? `Last: ${props.lastImport.parameters?.imported ?? 0} imported` : 'New',
        badgeClass: props.lastImport ? 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400' : 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400',
    },
    {
        title: 'Listings',
        description: 'View and manage synced eBay listings.',
        icon: 'fas fa-list',
        route: 'marketplaces.ebay.listings',
        badge: props.stats.total > 0 ? `${props.stats.published} active / ${props.stats.errors} errors` : null,
        badgeClass: props.stats.errors > 0 ? 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400' : 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
    },
    {
        title: 'Products',
        description: 'View products ready to sync to eBay.',
        icon: 'fas fa-box',
        route: 'marketplaces.ebay.products',
        badge: null,
        badgeClass: '',
    },
    {
        title: 'Sync Logs',
        description: 'View sync operation history and troubleshoot issues.',
        icon: 'fas fa-history',
        route: 'marketplaces.ebay.logs',
        badge: null,
        badgeClass: '',
    },
    {
        title: 'Business Policies',
        description: 'Manage shipping, payment, and return policies for eBay listings.',
        icon: 'fas fa-file-contract',
        route: 'marketplaces.ebay.policies.index',
        badge: null,
        badgeClass: '',
    },
]);
</script>

<template>
    <AppLayout title="eBay Marketplace">
        <Head title="eBay - Marketplaces" />

        <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
            <!-- Header -->
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">eBay</h1>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                        Manage your eBay marketplace integration
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

            <!-- Auto-Sync Toggle -->
            <div v-if="isConnected" class="mb-6 flex items-center gap-3">
                <button
                    @click="router.post(route('marketplaces.ebay.auto-sync.toggle'))"
                    :class="[
                        'relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none',
                        integrationStatus.auto_sync_enabled ? 'bg-blue-600' : 'bg-gray-200 dark:bg-gray-600'
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
                    Inventory will not push to eBay
                </span>
            </div>

            <!-- Token status -->
            <div v-if="tokenStatusDisplay" class="mb-6 text-sm" :class="tokenStatusDisplay.class">
                <i class="fas fa-key mr-1"></i> {{ tokenStatusDisplay.label }}
            </div>

            <!-- Environment info -->
            <div v-if="integrationStatus.environment" class="mb-6 text-sm text-gray-500 dark:text-gray-400">
                <span class="font-medium">Environment:</span> {{ integrationStatus.environment }}
                <span v-if="integrationStatus.account_username || integrationStatus.seller_username" class="ml-4">
                    <span class="font-medium">Seller:</span> {{ integrationStatus.account_username || integrationStatus.seller_username }}
                </span>
            </div>

            <!-- Action Cards Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <Link
                    v-for="card in cards"
                    :key="card.route"
                    :href="route(card.route)"
                    class="block p-6 bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 hover:border-blue-300 dark:hover:border-blue-600 hover:shadow-md transition-all"
                >
                    <div class="flex items-start justify-between">
                        <div class="flex items-center gap-3">
                            <div class="flex-shrink-0 w-10 h-10 rounded-lg bg-blue-50 dark:bg-blue-900/20 flex items-center justify-center">
                                <i :class="card.icon" class="text-blue-600 dark:text-blue-400"></i>
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
            </div>

            <!-- Quick Stats -->
            <div v-if="stats.total > 0" class="mt-8 grid grid-cols-2 md:grid-cols-4 gap-4">
                <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-4">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Total Listings</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ stats.total }}</p>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-4">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Published</p>
                    <p class="text-2xl font-bold text-emerald-600 dark:text-emerald-400">{{ stats.published }}</p>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-4">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Pending</p>
                    <p class="text-2xl font-bold text-amber-600 dark:text-amber-400">{{ stats.pending }}</p>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-4">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Errors</p>
                    <p class="text-2xl font-bold text-red-600 dark:text-red-400">{{ stats.errors }}</p>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
