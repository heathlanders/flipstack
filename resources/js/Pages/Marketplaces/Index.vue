<script setup>
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    stats: {
        type: Object,
        default: () => ({})
    }
});

const totals = computed(() => props.stats?.totals ?? {});
const platforms = computed(() => props.stats?.platforms ?? {});
const syncHealth = computed(() => props.stats?.sync_health ?? {});
const recentErrors = computed(() => props.stats?.recent_errors ?? []);
const connections = computed(() => props.stats?.connections ?? []);

const successRateColor = computed(() => {
    const rate = syncHealth.value.success_rate;
    if (rate === null || rate === undefined) return 'text-gray-500 dark:text-gray-400';
    if (rate >= 95) return 'text-emerald-600 dark:text-emerald-400';
    if (rate >= 80) return 'text-amber-600 dark:text-amber-400';
    return 'text-red-600 dark:text-red-400';
});

const platformCards = computed(() => {
    const cards = [];
    if (platforms.value.ebay) {
        cards.push({
            key: 'ebay',
            name: 'eBay',
            route: 'marketplaces.ebay.index',
            icon: 'fas fa-shopping-cart',
            color: 'blue',
            ...platforms.value.ebay,
        });
    }
    if (platforms.value.shopify) {
        cards.push({
            key: 'shopify',
            name: 'Shopify',
            route: 'marketplaces.shopify.index',
            icon: 'fas fa-shopping-bag',
            color: 'green',
            ...platforms.value.shopify,
        });
    }
    if (platforms.value.amazon) {
        cards.push({
            key: 'amazon',
            name: 'Amazon',
            route: 'marketplaces.amazon.index',
            icon: 'fab fa-amazon',
            color: 'amber',
            ...platforms.value.amazon,
        });
    }
    return cards;
});
</script>

<template>
    <AppLayout title="Marketplace Hub">
        <Head title="Marketplace Hub" />

        <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
            <!-- Header -->
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Marketplace Hub</h1>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                        Cross-platform overview of all marketplace integrations
                    </p>
                </div>
                <div class="flex items-center gap-3">
                    <Link
                        :href="route('marketplaces.sync-rules.index')"
                        class="inline-flex items-center px-4 py-2 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md font-semibold text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150"
                    >
                        Sync Rules
                    </Link>
                    <Link
                        :href="route('marketplaces.hub.listings')"
                        class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150"
                    >
                        View All Listings
                    </Link>
                </div>
            </div>

            <!-- Connections Status -->
            <div class="mb-6 flex items-center gap-3">
                <div
                    v-for="conn in connections"
                    :key="conn.platform"
                    class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full text-sm font-medium"
                    :class="conn.connected
                        ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-400'
                        : 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400'"
                >
                    <span class="w-2 h-2 rounded-full" :class="conn.connected ? 'bg-emerald-500' : 'bg-gray-400'"></span>
                    <span class="capitalize">{{ conn.platform }}</span>
                    <span v-if="conn.username" class="text-xs opacity-75">{{ conn.username }}</span>
                </div>
                <span v-if="connections.length === 0" class="text-sm text-gray-500 dark:text-gray-400">
                    No marketplaces connected
                </span>
            </div>

            <!-- Summary Stats -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
                <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-4">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Total Listings</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ totals.total_listings ?? 0 }}</p>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-4">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Active Listings</p>
                    <p class="text-2xl font-bold text-emerald-600 dark:text-emerald-400">{{ totals.active_listings ?? 0 }}</p>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-4">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Errors</p>
                    <p class="text-2xl font-bold" :class="(totals.total_errors ?? 0) > 0 ? 'text-red-600 dark:text-red-400' : 'text-gray-900 dark:text-white'">{{ totals.total_errors ?? 0 }}</p>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-4">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Platforms Connected</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ totals.platforms_connected ?? 0 }}</p>
                </div>
            </div>

            <!-- Platform Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                <Link
                    v-for="platform in platformCards"
                    :key="platform.key"
                    :href="route(platform.route)"
                    class="block bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 hover:border-blue-300 dark:hover:border-blue-600 hover:shadow-md transition-all p-6"
                >
                    <div class="flex items-center justify-between mb-4">
                        <div class="flex items-center gap-3">
                            <div
                                class="flex-shrink-0 w-10 h-10 rounded-lg flex items-center justify-center"
                                :class="platform.color === 'blue' ? 'bg-blue-50 dark:bg-blue-900/20' : 'bg-green-50 dark:bg-green-900/20'"
                            >
                                <i :class="platform.icon" :class2="platform.color === 'blue' ? 'text-blue-600 dark:text-blue-400' : 'text-green-600 dark:text-green-400'" class="text-lg" :style="{ color: platform.color === 'blue' ? '#2563eb' : '#16a34a' }"></i>
                            </div>
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">{{ platform.name }}</h3>
                        </div>
                        <span
                            v-if="platform.errors > 0"
                            class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400"
                        >
                            {{ platform.errors }} errors
                        </span>
                    </div>

                    <div class="grid grid-cols-3 gap-4">
                        <div>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Active</p>
                            <p class="text-lg font-semibold text-emerald-600 dark:text-emerald-400">{{ platform.active }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Pending</p>
                            <p class="text-lg font-semibold text-amber-600 dark:text-amber-400">{{ platform.pending }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Total</p>
                            <p class="text-lg font-semibold text-gray-900 dark:text-white">{{ platform.total }}</p>
                        </div>
                    </div>

                    <div v-if="platform.total_listed_value > 0" class="mt-3 pt-3 border-t border-gray-100 dark:border-gray-700">
                        <p class="text-xs text-gray-500 dark:text-gray-400">Total Listed Value</p>
                        <p class="text-sm font-medium text-gray-900 dark:text-white">${{ Number(platform.total_listed_value).toLocaleString('en-US', { minimumFractionDigits: 2 }) }}</p>
                    </div>
                </Link>

                <!-- Empty state when no platforms -->
                <div v-if="platformCards.length === 0" class="md:col-span-2 bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-8 text-center">
                    <i class="fas fa-store text-4xl text-gray-300 dark:text-gray-600 mb-3"></i>
                    <p class="text-gray-500 dark:text-gray-400">No marketplace listings yet.</p>
                    <p class="text-sm text-gray-400 dark:text-gray-500 mt-1">Connect a marketplace from the sidebar to get started.</p>
                </div>
            </div>

            <!-- Sync Health -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Sync Health Card -->
                <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Sync Health (Last 24h)</h3>

                    <div v-if="syncHealth.last_24h_operations > 0" class="space-y-4">
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-gray-500 dark:text-gray-400">Success Rate</span>
                            <span class="text-2xl font-bold" :class="successRateColor">
                                {{ syncHealth.success_rate }}%
                            </span>
                        </div>

                        <!-- Progress bar -->
                        <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                            <div
                                class="h-2 rounded-full transition-all"
                                :class="syncHealth.success_rate >= 95 ? 'bg-emerald-500' : syncHealth.success_rate >= 80 ? 'bg-amber-500' : 'bg-red-500'"
                                :style="{ width: (syncHealth.success_rate ?? 0) + '%' }"
                            ></div>
                        </div>

                        <div class="grid grid-cols-3 gap-4 pt-2">
                            <div>
                                <p class="text-xs text-gray-500 dark:text-gray-400">Operations</p>
                                <p class="text-lg font-semibold text-gray-900 dark:text-white">{{ syncHealth.last_24h_operations }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500 dark:text-gray-400">Successful</p>
                                <p class="text-lg font-semibold text-emerald-600 dark:text-emerald-400">{{ syncHealth.last_24h_success }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500 dark:text-gray-400">Failed</p>
                                <p class="text-lg font-semibold" :class="syncHealth.last_24h_failed > 0 ? 'text-red-600 dark:text-red-400' : 'text-gray-900 dark:text-white'">{{ syncHealth.last_24h_failed }}</p>
                            </div>
                        </div>
                    </div>

                    <div v-else class="text-center py-4">
                        <p class="text-sm text-gray-500 dark:text-gray-400">No sync operations in the last 24 hours</p>
                    </div>
                </div>

                <!-- Recent Errors -->
                <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Recent Errors</h3>

                    <div v-if="recentErrors.length > 0" class="space-y-3 max-h-64 overflow-y-auto">
                        <div
                            v-for="error in recentErrors"
                            :key="error.id"
                            class="p-3 bg-red-50 dark:bg-red-900/10 border border-red-100 dark:border-red-900/30 rounded-lg"
                        >
                            <div class="flex items-center justify-between mb-1">
                                <div class="flex items-center gap-2">
                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium capitalize"
                                        :class="error.platform === 'ebay'
                                            ? 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400'
                                            : 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400'"
                                    >
                                        {{ error.platform }}
                                    </span>
                                    <span class="text-xs text-gray-500 dark:text-gray-400">{{ error.operation_label }}</span>
                                </div>
                                <span class="text-xs text-gray-400 dark:text-gray-500">{{ error.created_at_human }}</span>
                            </div>
                            <p class="text-sm text-red-700 dark:text-red-400 line-clamp-2">{{ error.error_message }}</p>
                            <p v-if="error.product_name" class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                {{ error.product_name }} ({{ error.product_sku }})
                            </p>
                        </div>
                    </div>

                    <div v-else class="text-center py-4">
                        <p class="text-sm text-emerald-600 dark:text-emerald-400">No recent errors</p>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
