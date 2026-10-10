<script setup>
import { computed, onMounted, onBeforeUnmount, ref } from 'vue';
import { router, Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { route } from 'ziggy-js';
import AnalyticsSidebar from '@/Components/Analytics/AnalyticsSidebar.vue';
import KPICard from '@/Components/Analytics/KPICard.vue';
import TrendLineChart from '@/Components/Analytics/TrendLineChart.vue';
import { formatNumber } from '@/Composables/useAnalyticsFormatters';

const props = defineProps({
    todaySummary: { type: Object, default: () => ({}) },
    ordersInFlight: { type: Array, default: () => [] },
    hourlyTrend: { type: Array, default: () => [] },
    activeUsers: { type: Array, default: () => [] },
    perUserToday: { type: Array, default: () => [] },
    recentActivity: { type: Array, default: () => [] },
    isManager: { type: Boolean, default: false },
    serverTime: { type: String, default: '' },
});

const REFRESH_MS = 15000;
const pollHandle = ref(null);
const tick = ref(0);
const lastUpdated = computed(() => props.serverTime ? new Date(props.serverTime) : null);

function reloadProps() {
    if (document.hidden) return;
    router.reload({
        only: [
            'todaySummary',
            'ordersInFlight',
            'hourlyTrend',
            'activeUsers',
            'perUserToday',
            'recentActivity',
            'serverTime',
        ],
        preserveScroll: true,
        preserveState: true,
    });
}

function startPolling() {
    if (pollHandle.value) return;
    pollHandle.value = setInterval(reloadProps, REFRESH_MS);
}

function stopPolling() {
    if (pollHandle.value) {
        clearInterval(pollHandle.value);
        pollHandle.value = null;
    }
}

function onVisibility() {
    if (document.hidden) {
        stopPolling();
    } else {
        reloadProps();
        startPolling();
    }
}

let tickHandle = null;

onMounted(() => {
    startPolling();
    document.addEventListener('visibilitychange', onVisibility);
    tickHandle = setInterval(() => { tick.value++; }, 1000);
});

onBeforeUnmount(() => {
    stopPolling();
    document.removeEventListener('visibilitychange', onVisibility);
    if (tickHandle) clearInterval(tickHandle);
});

const secondsAgo = computed(() => {
    void tick.value;
    if (!lastUpdated.value) return null;
    return Math.max(0, Math.floor((Date.now() - lastUpdated.value.getTime()) / 1000));
});

const updatedLabel = computed(() => {
    const s = secondsAgo.value;
    if (s === null) return '';
    if (s < 5) return 'Updated just now';
    if (s < 60) return `Updated ${s}s ago`;
    const m = Math.floor(s / 60);
    return `Updated ${m}m ago`;
});

const trendLabels = computed(() => props.hourlyTrend.map(b => b.hour));
const trendDatasets = computed(() => [
    { label: 'Items picked', data: props.hourlyTrend.map(b => b.items_picked), color: '#6366f1' },
    { label: 'Items packed', data: props.hourlyTrend.map(b => b.items_packed), color: '#10b981' },
    { label: 'Shipments', data: props.hourlyTrend.map(b => b.shipments_created), color: '#f59e0b' },
]);

function formatTime(iso) {
    if (!iso) return '';
    const d = new Date(iso);
    return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' });
}

function relativeTime(iso) {
    if (!iso) return '';
    void tick.value;
    const diff = Math.max(0, Math.floor((Date.now() - new Date(iso).getTime()) / 1000));
    if (diff < 60) return `${diff}s ago`;
    if (diff < 3600) return `${Math.floor(diff / 60)}m ago`;
    if (diff < 86400) return `${Math.floor(diff / 3600)}h ago`;
    return `${Math.floor(diff / 86400)}d ago`;
}

function actionBadgeClass(action) {
    if (action === 'shipment_created' || action === 'pack_completed' || action === 'pick_completed' || action === 'label_purchased') {
        return 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300';
    }
    if (action === 'pick_shorted' || action === 'label_voided' || action === 'order_cancelled' || action === 'return_received') {
        return 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300';
    }
    if (action === 'item_picked' || action === 'pick_started' || action === 'pack_started' || action === 'item_packed') {
        return 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300';
    }
    return 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300';
}
</script>

<template>
    <Head title="Live Ops" />
    <AppLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200">Live Ops</h2>
                    <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full bg-emerald-100 dark:bg-emerald-900/40 text-emerald-700 dark:text-emerald-300 text-xs font-medium">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                        LIVE
                    </span>
                </div>
                <div class="flex items-center gap-3 text-xs text-gray-500 dark:text-gray-400">
                    <span>{{ updatedLabel }}</span>
                    <button
                        type="button"
                        @click="reloadProps"
                        class="px-3 py-1.5 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600 font-medium"
                    >
                        Refresh
                    </button>
                </div>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex gap-6">
                    <AnalyticsSidebar currentPage="live" />

                    <div class="flex-1 space-y-6">
                        <!-- Today KPI strip -->
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                            <KPICard
                                title="Shipments today"
                                :value="formatNumber(todaySummary.shipments_created || 0)"
                                color="emerald"
                                icon="trending-up"
                                :subtitle="`${formatNumber(todaySummary.labels_purchased || 0)} labels purchased`"
                            />
                            <KPICard
                                title="Items picked"
                                :value="formatNumber(todaySummary.items_picked || 0)"
                                color="indigo"
                                icon="cube"
                                :subtitle="`${formatNumber(todaySummary.orders_picked || 0)} orders complete`"
                            />
                            <KPICard
                                title="Items packed"
                                :value="formatNumber(todaySummary.items_packed || 0)"
                                color="blue"
                                icon="archive"
                                :subtitle="`${formatNumber(todaySummary.orders_packed || 0)} orders packed`"
                            />
                            <KPICard
                                title="Errors today"
                                :value="formatNumber((todaySummary.pick_errors || 0) + (todaySummary.pack_errors || 0))"
                                color="red"
                                icon="exclamation-triangle"
                                :subtitle="`${formatNumber(todaySummary.pick_errors || 0)} pick / ${formatNumber(todaySummary.pack_errors || 0)} pack`"
                                :alert-level="((todaySummary.pick_errors || 0) + (todaySummary.pack_errors || 0)) > 0 ? 'warning' : 'normal'"
                            />
                        </div>

                        <!-- Orders in flight -->
                        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-5">
                            <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wider mb-4">
                                Orders in flight
                            </h3>
                            <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-8 gap-3">
                                <div
                                    v-for="bucket in ordersInFlight"
                                    :key="bucket.status"
                                    class="rounded-lg bg-gray-50 dark:bg-gray-700/50 p-3 text-center"
                                >
                                    <div class="text-2xl font-bold text-gray-900 dark:text-white">{{ formatNumber(bucket.count) }}</div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ bucket.label }}</div>
                                </div>
                            </div>
                        </div>

                        <!-- Hourly trend -->
                        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-5">
                            <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wider mb-4">
                                Today by hour
                            </h3>
                            <TrendLineChart :labels="trendLabels" :datasets="trendDatasets" :height="240" />
                        </div>

                        <!-- Active users (manager) -->
                        <div v-if="isManager && activeUsers.length" class="bg-white dark:bg-gray-800 rounded-lg shadow p-5">
                            <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wider mb-4">
                                Active in last 60 min
                            </h3>
                            <div class="space-y-2">
                                <div
                                    v-for="u in activeUsers"
                                    :key="u.user_id"
                                    class="flex items-center justify-between p-2 rounded hover:bg-gray-50 dark:hover:bg-gray-700/40"
                                >
                                    <div class="flex items-center gap-3">
                                        <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                                        <span class="font-medium text-gray-900 dark:text-white">{{ u.user_name }}</span>
                                    </div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400">
                                        {{ formatNumber(u.actions) }} actions · last {{ relativeTime(u.last_action_at) }}
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Per-user today (manager) -->
                        <div v-if="isManager && perUserToday.length" class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden">
                            <div class="px-5 py-4 border-b border-gray-200 dark:border-gray-700">
                                <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wider">
                                    Per-user today
                                </h3>
                            </div>
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                                    <thead class="bg-gray-50 dark:bg-gray-700/50">
                                        <tr>
                                            <th class="px-4 py-2 text-left font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider text-xs">User</th>
                                            <th class="px-4 py-2 text-right font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider text-xs">Items picked</th>
                                            <th class="px-4 py-2 text-right font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider text-xs">Orders picked</th>
                                            <th class="px-4 py-2 text-right font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider text-xs">Items packed</th>
                                            <th class="px-4 py-2 text-right font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider text-xs">Orders packed</th>
                                            <th class="px-4 py-2 text-right font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider text-xs">Shipments</th>
                                            <th class="px-4 py-2 text-right font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider text-xs">Errors</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                        <tr v-for="row in perUserToday" :key="row.user_id">
                                            <td class="px-4 py-2 text-gray-900 dark:text-white font-medium">{{ row.user_name }}</td>
                                            <td class="px-4 py-2 text-right text-gray-700 dark:text-gray-300">{{ formatNumber(row.items_picked) }}</td>
                                            <td class="px-4 py-2 text-right text-gray-700 dark:text-gray-300">{{ formatNumber(row.orders_picked) }}</td>
                                            <td class="px-4 py-2 text-right text-gray-700 dark:text-gray-300">{{ formatNumber(row.items_packed) }}</td>
                                            <td class="px-4 py-2 text-right text-gray-700 dark:text-gray-300">{{ formatNumber(row.orders_packed) }}</td>
                                            <td class="px-4 py-2 text-right text-gray-700 dark:text-gray-300">{{ formatNumber(row.shipments_created) }}</td>
                                            <td
                                                class="px-4 py-2 text-right"
                                                :class="(row.pick_errors + row.pack_errors) > 0 ? 'text-red-600 dark:text-red-400 font-semibold' : 'text-gray-400'"
                                            >
                                                {{ formatNumber(row.pick_errors + row.pack_errors) }}
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Recent activity (manager) -->
                        <div v-if="isManager" class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden">
                            <div class="px-5 py-4 border-b border-gray-200 dark:border-gray-700">
                                <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wider">
                                    Recent activity
                                </h3>
                            </div>
                            <div v-if="!recentActivity.length" class="p-8 text-center text-sm text-gray-500 dark:text-gray-400">
                                No recent activity yet today.
                            </div>
                            <ul v-else class="divide-y divide-gray-200 dark:divide-gray-700 max-h-[480px] overflow-y-auto">
                                <li
                                    v-for="entry in recentActivity"
                                    :key="entry.id"
                                    class="px-5 py-3 flex items-start gap-3 text-sm"
                                >
                                    <span
                                        class="px-2 py-0.5 rounded-full text-xs font-medium whitespace-nowrap"
                                        :class="actionBadgeClass(entry.action)"
                                    >
                                        {{ entry.action_label }}
                                    </span>
                                    <div class="flex-1 min-w-0">
                                        <div class="text-gray-900 dark:text-white">
                                            <span class="font-medium">{{ entry.user_name }}</span>
                                            <template v-if="entry.marketplace_order_id">
                                                ·
                                                <span class="text-gray-600 dark:text-gray-400">
                                                    {{ entry.marketplace }} #{{ entry.marketplace_order_id }}
                                                </span>
                                            </template>
                                        </div>
                                    </div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400 whitespace-nowrap">
                                        {{ relativeTime(entry.created_at) }}
                                        <span class="text-gray-300 dark:text-gray-600">·</span>
                                        {{ formatTime(entry.created_at) }}
                                    </div>
                                </li>
                            </ul>
                        </div>

                        <p v-if="!isManager" class="text-xs text-gray-500 dark:text-gray-400 text-center py-2">
                            Per-user details and recent activity are visible to managers and admins.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
