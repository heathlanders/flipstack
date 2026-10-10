<script setup>
import { ref, computed, onMounted } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import TierStatusCard from '@/Components/Supplier/TierStatusCard.vue';
import PerformanceChart from '@/Components/Supplier/PerformanceChart.vue';
import SettlementCard from '@/Components/Supplier/SettlementCard.vue';
import NotificationList from '@/Components/Supplier/NotificationList.vue';

const props = defineProps({
    supplier: Object,
    recentSettlements: Array,
    latestMetrics: Object,
    pendingAmount: Number,
    unreadNotifications: Array,
    ytdSummary: Object,
    monthlyTrend: Array,
    stats: Object,
});

const activeTab = ref('overview');

const formatCurrency = (value) => {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD',
    }).format(value || 0);
};

const formatPercent = (value) => {
    return `${(value || 0).toFixed(1)}%`;
};

const getChangeClass = (change) => {
    if (change > 0) return 'text-green-600 dark:text-green-400';
    if (change < 0) return 'text-red-600 dark:text-red-400';
    return 'text-gray-500';
};

const getChangeIcon = (change) => {
    if (change > 0) return '↑';
    if (change < 0) return '↓';
    return '→';
};

const markNotificationRead = (notificationId) => {
    router.post(route('supplier.notifications.read', notificationId), {}, {
        preserveScroll: true,
    });
};

const markAllRead = () => {
    router.post(route('supplier.notifications.read-all'), {}, {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Supplier Dashboard" />

    <AppLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <div>
                    <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                        Welcome back, {{ supplier.name }}
                    </h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                        {{ supplier.tier?.name || 'No Tier' }} Supplier
                    </p>
                </div>
                <div class="flex items-center space-x-4">
                    <span :class="[
                        'px-3 py-1 rounded-full text-sm font-medium',
                        supplier.status === 'active' ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200'
                    ]">
                        {{ supplier.status_label }}
                    </span>
                </div>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <!-- Quick Stats Cards -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                    <!-- This Month Sales -->
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">This Month Sales</p>
                                <p class="text-2xl font-bold text-gray-900 dark:text-white mt-1">
                                    {{ formatCurrency(stats.this_month?.sales) }}
                                </p>
                            </div>
                            <div :class="['text-sm font-medium', getChangeClass(stats.changes?.sales)]">
                                {{ getChangeIcon(stats.changes?.sales) }} {{ formatPercent(Math.abs(stats.changes?.sales || 0)) }}
                            </div>
                        </div>
                        <p class="text-xs text-gray-400 mt-2">vs last month</p>
                    </div>

                    <!-- Pending Payout -->
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Pending Payout</p>
                                <p class="text-2xl font-bold text-gray-900 dark:text-white mt-1">
                                    {{ formatCurrency(pendingAmount) }}
                                </p>
                            </div>
                            <svg class="w-8 h-8 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <p class="text-xs text-gray-400 mt-2">
                            Next settlement: {{ supplier.settlement?.next_date || 'TBD' }}
                        </p>
                    </div>

                    <!-- Performance Score -->
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Performance Score</p>
                                <p class="text-2xl font-bold text-gray-900 dark:text-white mt-1">
                                    {{ latestMetrics?.overall_score || '—' }}
                                </p>
                            </div>
                            <div class="w-12 h-12 rounded-full flex items-center justify-center"
                                 :class="{
                                     'bg-purple-100 text-purple-600': (latestMetrics?.overall_score || 0) >= 95,
                                     'bg-yellow-100 text-yellow-600': (latestMetrics?.overall_score || 0) >= 85 && (latestMetrics?.overall_score || 0) < 95,
                                     'bg-gray-100 text-gray-600': (latestMetrics?.overall_score || 0) >= 75 && (latestMetrics?.overall_score || 0) < 85,
                                     'bg-orange-100 text-orange-600': (latestMetrics?.overall_score || 0) >= 65 && (latestMetrics?.overall_score || 0) < 75,
                                     'bg-red-100 text-red-600': (latestMetrics?.overall_score || 0) < 65,
                                 }">
                                <span class="text-lg font-bold">{{ supplier.tier?.code?.[0] || '?' }}</span>
                            </div>
                        </div>
                        <p class="text-xs text-gray-400 mt-2">
                            {{ supplier.tier?.name }} tier
                        </p>
                    </div>

                    <!-- Products -->
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Active Listings</p>
                                <p class="text-2xl font-bold text-gray-900 dark:text-white mt-1">
                                    {{ stats.products?.listed || 0 }}
                                </p>
                            </div>
                            <svg class="w-8 h-8 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                            </svg>
                        </div>
                        <p class="text-xs text-gray-400 mt-2">
                            {{ stats.products?.sold_this_month || 0 }} sold this month
                        </p>
                    </div>
                </div>

                <!-- Main Content Grid -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <!-- Left Column - 2/3 width -->
                    <div class="lg:col-span-2 space-y-6">
                        <!-- Tier Status Card -->
                        <TierStatusCard
                            v-if="supplier.tier"
                            :tier="supplier.tier"
                            :performance-score="supplier.performance?.score || latestMetrics?.overall_score || 0"
                            :base-commission-rate="supplier.commission?.base_percentage || 20"
                            :tier-start-date="supplier.tier_change_date"
                        />

                        <!-- Monthly Trend Chart -->
                        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">
                                Earnings Trend
                            </h3>
                            <PerformanceChart :data="monthlyTrend" />
                        </div>

                        <!-- Recent Settlements -->
                        <div class="bg-white dark:bg-gray-800 rounded-lg shadow">
                            <div class="p-6 border-b border-gray-200 dark:border-gray-700">
                                <div class="flex justify-between items-center">
                                    <h3 class="text-lg font-medium text-gray-900 dark:text-white">
                                        Recent Settlements
                                    </h3>
                                    <Link :href="route('supplier.settlements.index')" 
                                          class="text-sm text-indigo-600 hover:text-indigo-500 dark:text-indigo-400">
                                        View All →
                                    </Link>
                                </div>
                            </div>
                            <div class="divide-y divide-gray-200 dark:divide-gray-700">
                                <SettlementCard 
                                    v-for="settlement in recentSettlements" 
                                    :key="settlement.id"
                                    :settlement="settlement"
                                />
                                <div v-if="!recentSettlements?.length" class="p-6 text-center text-gray-500 dark:text-gray-400">
                                    No settlements yet
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Right Column - 1/3 width -->
                    <div class="space-y-6">
                        <!-- YTD Summary -->
                        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">
                                Year to Date
                            </h3>
                            <dl class="space-y-4">
                                <div class="flex justify-between">
                                    <dt class="text-sm text-gray-500 dark:text-gray-400">Total Sales</dt>
                                    <dd class="text-sm font-medium text-gray-900 dark:text-white">
                                        {{ formatCurrency(ytdSummary?.total_sales) }}
                                    </dd>
                                </div>
                                <div class="flex justify-between">
                                    <dt class="text-sm text-gray-500 dark:text-gray-400">Total Payout</dt>
                                    <dd class="text-sm font-medium text-gray-900 dark:text-white">
                                        {{ formatCurrency(ytdSummary?.total_payout) }}
                                    </dd>
                                </div>
                                <div class="flex justify-between">
                                    <dt class="text-sm text-gray-500 dark:text-gray-400">Commission Paid</dt>
                                    <dd class="text-sm font-medium text-gray-900 dark:text-white">
                                        {{ formatCurrency(ytdSummary?.total_commission) }}
                                    </dd>
                                </div>
                                <div class="flex justify-between">
                                    <dt class="text-sm text-gray-500 dark:text-gray-400">Items Sold</dt>
                                    <dd class="text-sm font-medium text-gray-900 dark:text-white">
                                        {{ ytdSummary?.total_items || 0 }}
                                    </dd>
                                </div>
                                <div class="flex justify-between">
                                    <dt class="text-sm text-gray-500 dark:text-gray-400">Avg Commission Rate</dt>
                                    <dd class="text-sm font-medium text-gray-900 dark:text-white">
                                        {{ formatPercent(ytdSummary?.average_commission_rate) }}
                                    </dd>
                                </div>
                            </dl>
                        </div>

                        <!-- Notifications -->
                        <div class="bg-white dark:bg-gray-800 rounded-lg shadow">
                            <div class="p-6 border-b border-gray-200 dark:border-gray-700">
                                <div class="flex justify-between items-center">
                                    <h3 class="text-lg font-medium text-gray-900 dark:text-white">
                                        Notifications
                                        <span v-if="unreadNotifications?.length" 
                                              class="ml-2 px-2 py-0.5 text-xs bg-red-100 text-red-800 rounded-full">
                                            {{ unreadNotifications.length }}
                                        </span>
                                    </h3>
                                    <button v-if="unreadNotifications?.length"
                                            @click="markAllRead"
                                            class="text-sm text-indigo-600 hover:text-indigo-500 dark:text-indigo-400">
                                        Mark all read
                                    </button>
                                </div>
                            </div>
                            <NotificationList 
                                :notifications="unreadNotifications"
                                @mark-read="markNotificationRead"
                            />
                        </div>

                        <!-- Quick Links -->
                        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">
                                Quick Links
                            </h3>
                            <nav class="space-y-2">
                                <Link :href="route('supplier.performance.index')"
                                      class="flex items-center p-3 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                                    <svg class="w-5 h-5 text-gray-400 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                                    </svg>
                                    <span class="text-sm text-gray-700 dark:text-gray-300">View Performance</span>
                                </Link>
                                <Link :href="route('supplier.settlements.index')"
                                      class="flex items-center p-3 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                                    <svg class="w-5 h-5 text-gray-400 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
                                    </svg>
                                    <span class="text-sm text-gray-700 dark:text-gray-300">Settlement History</span>
                                </Link>
                                <Link :href="route('supplier.settlements.tax-documents')"
                                      class="flex items-center p-3 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                                    <svg class="w-5 h-5 text-gray-400 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                    </svg>
                                    <span class="text-sm text-gray-700 dark:text-gray-300">Tax Documents</span>
                                </Link>
                            </nav>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
