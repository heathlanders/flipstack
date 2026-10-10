<script setup>
import { computed } from 'vue';
import { router, Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { route } from 'ziggy-js';
import AnalyticsSidebar from '@/Components/Analytics/AnalyticsSidebar.vue';
import ComparisonDatePicker from '@/Components/Analytics/ComparisonDatePicker.vue';
import KPICard from '@/Components/Analytics/KPICard.vue';
import TrendLineChart from '@/Components/Analytics/TrendLineChart.vue';
import BarChart from '@/Components/Analytics/BarChart.vue';
import { formatCurrency, formatNumber, formatPercent, formatDecimal } from '@/Composables/useAnalyticsFormatters';

const props = defineProps({
    user: { type: Object, required: true },
    kpis: { type: Object, default: () => ({}) },
    productivityTrend: { type: Array, default: () => [] },
    timeline: { type: Array, default: () => [] },
    heatmap: { type: Array, default: () => [] },
    concurrentUsers: { type: Array, default: () => [] },
    ranking: { type: Object, default: () => ({}) },
    teamDeltas: { type: Array, default: () => [] },
    errorTrend: { type: Array, default: () => [] },
    attainment: { type: Array, default: () => [] },
    laborCost: { type: Object, default: null },
    sessions: { type: Array, default: () => [] },
    sessionAggregates: { type: Object, default: () => ({}) },
    rankMetric: { type: String, default: 'items_picked' },
    isManager: { type: Boolean, default: false },
    filters: { type: Object, default: () => ({}) },
});

const rankMetricOptions = [
    { value: 'items_picked', label: 'Items Picked' },
    { value: 'items_packed', label: 'Items Packed' },
    { value: 'shipments_created', label: 'Shipments' },
    { value: 'returns_processed', label: 'Returns' },
    { value: 'pick_errors', label: 'Pick Errors (lower=better)' },
    { value: 'pack_errors', label: 'Pack Errors (lower=better)' },
];

function changeRankMetric(metric) {
    router.get(
        route('analytics.hub.users.show', props.user.id),
        { ...props.filters, rank_metric: metric },
        { preserveState: true, preserveScroll: true }
    );
}

const quartileLabels = { 1: 'Top Quartile', 2: 'Second Quartile', 3: 'Third Quartile', 4: 'Bottom Quartile' };
const quartileColors = {
    1: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300',
    2: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300',
    3: 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300',
    4: 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300',
};

const errorTrendLabels = computed(() => props.errorTrend.map(d => d.date));
const errorTrendDatasets = computed(() => [
    { label: 'Pick Errors', data: props.errorTrend.map(d => d.pick_errors), color: '#ef4444' },
    { label: 'Pack Errors', data: props.errorTrend.map(d => d.pack_errors), color: '#f59e0b' },
    { label: 'Error Rate %', data: props.errorTrend.map(d => d.error_rate), color: '#8b5cf6', dashed: true },
]);

function deltaColor(trend) {
    if (trend === 'up') return 'text-emerald-600 dark:text-emerald-400';
    if (trend === 'down') return 'text-red-600 dark:text-red-400';
    return 'text-gray-500 dark:text-gray-400';
}

function deltaIcon(trend) {
    if (trend === 'up') return 'M5 10l7-7m0 0l7 7m-7-7v18';
    if (trend === 'down') return 'M19 14l-7 7m0 0l-7-7m7 7V3';
    return 'M5 12h14';
}

const attainmentStatusColors = {
    excellent: 'text-emerald-600 dark:text-emerald-400',
    on_track: 'text-blue-600 dark:text-blue-400',
    at_risk: 'text-amber-600 dark:text-amber-400',
    critical: 'text-red-600 dark:text-red-400',
};
const attainmentStatusBars = {
    excellent: 'bg-emerald-500',
    on_track: 'bg-blue-500',
    at_risk: 'bg-amber-500',
    critical: 'bg-red-500',
};
function attainmentColor(status) { return attainmentStatusColors[status] || 'text-gray-500'; }
function attainmentBar(status) { return attainmentStatusBars[status] || 'bg-gray-400'; }

function applyFilters(filters) {
    router.get(route('analytics.hub.users.show', props.user.id), filters, { preserveState: true, preserveScroll: true });
}

const trendLabels = computed(() => props.productivityTrend.map(d => d.date));
const trendDatasets = computed(() => [
    { label: 'Items Picked', data: props.productivityTrend.map(d => d.items_picked), color: '#3b82f6' },
    { label: 'Items Packed', data: props.productivityTrend.map(d => d.items_packed), color: '#10b981' },
    { label: 'Shipments', data: props.productivityTrend.map(d => d.shipments_created), color: '#f59e0b' },
]);

// Heatmap grid: 7 days × 24 hours
const dayLabels = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
const hours = Array.from({ length: 24 }, (_, h) => h);

const heatmapMatrix = computed(() => {
    const grid = Array.from({ length: 7 }, () => Array(24).fill(0));
    for (const cell of props.heatmap) {
        if (cell.day_of_week >= 0 && cell.day_of_week < 7 && cell.hour_of_day >= 0 && cell.hour_of_day < 24) {
            grid[cell.day_of_week][cell.hour_of_day] = cell.event_count;
        }
    }
    return grid;
});

const heatmapMax = computed(() => Math.max(1, ...props.heatmap.map(c => c.event_count)));

function heatColor(value) {
    if (!value) return 'bg-gray-50 dark:bg-gray-800';
    const ratio = value / heatmapMax.value;
    if (ratio < 0.2) return 'bg-blue-100 dark:bg-blue-900/30';
    if (ratio < 0.4) return 'bg-blue-200 dark:bg-blue-800/50';
    if (ratio < 0.6) return 'bg-blue-300 dark:bg-blue-700/70';
    if (ratio < 0.8) return 'bg-blue-400 dark:bg-blue-600';
    return 'bg-blue-600 dark:bg-blue-500';
}

const concurrentLabels = computed(() => props.concurrentUsers.map(c => `${c.hour_of_day}:00`));
const concurrentDatasets = computed(() => [
    { label: 'Active Users', data: props.concurrentUsers.map(c => c.active_users), color: '#8b5cf6' },
]);

function formatHours(seconds) {
    return formatDecimal(seconds / 3600);
}

function formatRelative(timestamp) {
    if (!timestamp) return '';
    const diff = Date.now() - new Date(timestamp).getTime();
    const mins = Math.floor(diff / 60000);
    if (mins < 1) return 'just now';
    if (mins < 60) return `${mins}m ago`;
    const hrs = Math.floor(mins / 60);
    if (hrs < 24) return `${hrs}h ago`;
    return `${Math.floor(hrs / 24)}d ago`;
}

const actionColors = {
    pick_completed: 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300',
    pack_completed: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300',
    shipment_created: 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300',
    label_purchased: 'bg-cyan-100 text-cyan-800 dark:bg-cyan-900/30 dark:text-cyan-300',
    listing_created: 'bg-violet-100 text-violet-800 dark:bg-violet-900/30 dark:text-violet-300',
    return_received: 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300',
};

function actionBadge(action) {
    return actionColors[action] || 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300';
}
</script>

<template>
    <Head :title="`${user.name} — Analytics`" />
    <AppLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <Link :href="route('analytics.hub.workforce')" class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>
                    </Link>
                    <img v-if="user.avatar" :src="user.avatar" :alt="user.name" class="w-10 h-10 rounded-full" />
                    <div>
                        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200">{{ user.name }}</h2>
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ user.email }}</p>
                    </div>
                </div>
                <ComparisonDatePicker :filters="filters" @change="applyFilters" />
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex gap-6">
                    <AnalyticsSidebar currentPage="workforce" />

                    <div class="flex-1 space-y-6">
                        <!-- KPI Row -->
                        <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-6 gap-4">
                            <KPICard title="Items Picked" :value="formatNumber(kpis.items_picked)" icon="cube" color="blue" />
                            <KPICard title="Items Packed" :value="formatNumber(kpis.items_packed)" icon="archive" color="emerald" />
                            <KPICard title="Shipments" :value="formatNumber(kpis.shipments_created)" icon="trending-up" color="amber" />
                            <KPICard title="Returns" :value="formatNumber(kpis.returns_processed)" icon="x-circle" color="red" />
                            <KPICard
                                title="Error Rate"
                                :value="formatPercent(kpis.error_rate)"
                                :alertLevel="kpis.error_rate > 5 ? 'critical' : kpis.error_rate > 2 ? 'warning' : 'normal'"
                                icon="exclamation-triangle"
                                color="violet"
                            />
                            <KPICard title="Active Hours" :value="formatHours(kpis.total_active_seconds)" icon="chart-bar" color="cyan" />
                        </div>

                        <!-- Speed + Days -->
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-5">
                                <div class="text-xs uppercase tracking-wider text-gray-500 dark:text-gray-400">Avg Pick Time</div>
                                <div class="mt-2 text-2xl font-semibold text-gray-900 dark:text-white">{{ formatDecimal(kpis.avg_pick_seconds) }}s</div>
                            </div>
                            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-5">
                                <div class="text-xs uppercase tracking-wider text-gray-500 dark:text-gray-400">Avg Pack Time</div>
                                <div class="mt-2 text-2xl font-semibold text-gray-900 dark:text-white">{{ formatDecimal(kpis.avg_pack_seconds) }}s</div>
                            </div>
                            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-5">
                                <div class="text-xs uppercase tracking-wider text-gray-500 dark:text-gray-400">Days Active</div>
                                <div class="mt-2 text-2xl font-semibold text-gray-900 dark:text-white">{{ formatNumber(kpis.days_active) }}</div>
                            </div>
                        </div>

                        <!-- Labor Cost -->
                        <div v-if="laborCost" class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                            <div class="flex items-center justify-between mb-4">
                                <h3 class="text-lg font-medium text-gray-900 dark:text-white">Labor Cost</h3>
                                <Link :href="route('settings.labor-rates.index')" class="text-xs text-indigo-600 dark:text-indigo-400 hover:underline">Manage rates →</Link>
                            </div>
                            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3 text-center">
                                <div class="bg-gray-50 dark:bg-gray-700/30 rounded-lg p-3">
                                    <div class="text-xs text-gray-500 dark:text-gray-400">Hourly Rate</div>
                                    <div class="mt-1 text-lg font-semibold text-gray-900 dark:text-white">{{ formatCurrency(laborCost.hourly_rate) }}</div>
                                </div>
                                <div class="bg-gray-50 dark:bg-gray-700/30 rounded-lg p-3">
                                    <div class="text-xs text-gray-500 dark:text-gray-400">Active Hours</div>
                                    <div class="mt-1 text-lg font-semibold text-gray-900 dark:text-white">{{ formatDecimal(laborCost.active_hours) }}</div>
                                </div>
                                <div class="bg-gray-50 dark:bg-gray-700/30 rounded-lg p-3">
                                    <div class="text-xs text-gray-500 dark:text-gray-400">Total Labor</div>
                                    <div class="mt-1 text-lg font-semibold text-gray-900 dark:text-white">{{ formatCurrency(laborCost.total_labor_cost) }}</div>
                                </div>
                                <div class="bg-blue-50 dark:bg-blue-900/20 rounded-lg p-3">
                                    <div class="text-xs text-gray-500 dark:text-gray-400">Per Picked Unit</div>
                                    <div class="mt-1 text-lg font-semibold text-gray-900 dark:text-white">{{ formatCurrency(laborCost.cost_per_picked_unit) }}</div>
                                </div>
                                <div class="bg-emerald-50 dark:bg-emerald-900/20 rounded-lg p-3">
                                    <div class="text-xs text-gray-500 dark:text-gray-400">Per Listed Item</div>
                                    <div class="mt-1 text-lg font-semibold text-gray-900 dark:text-white">{{ formatCurrency(laborCost.cost_per_listed_item) }}</div>
                                </div>
                                <div class="bg-amber-50 dark:bg-amber-900/20 rounded-lg p-3">
                                    <div class="text-xs text-gray-500 dark:text-gray-400">Per Order Served</div>
                                    <div class="mt-1 text-lg font-semibold text-gray-900 dark:text-white">{{ formatCurrency(laborCost.cost_to_serve_per_order) }}</div>
                                </div>
                            </div>
                            <div class="mt-3 text-xs text-gray-500 dark:text-gray-400">
                                {{ formatNumber(laborCost.listings_created) }} listings · {{ formatNumber(laborCost.replies_sent) }} replies · {{ formatCurrency(laborCost.cost_per_ticket) }} per ticket
                            </div>
                        </div>

                        <!-- Goal Attainment -->
                        <div v-if="attainment.length" class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                            <div class="flex items-center justify-between mb-4">
                                <h3 class="text-lg font-medium text-gray-900 dark:text-white">Goal Attainment</h3>
                                <Link :href="route('settings.targets.index')" class="text-xs text-indigo-600 dark:text-indigo-400 hover:underline">Manage targets →</Link>
                            </div>
                            <div class="space-y-3">
                                <div v-for="row in attainment" :key="row.metric + row.period" class="border border-gray-100 dark:border-gray-700 rounded-lg p-3">
                                    <div class="flex items-center justify-between mb-1.5 text-sm">
                                        <div>
                                            <span class="font-medium text-gray-900 dark:text-white capitalize">{{ row.metric.replace(/_/g, ' ') }}</span>
                                            <span class="ml-2 text-xs text-gray-500 dark:text-gray-400 capitalize">{{ row.period }} ({{ row.scope_type }})</span>
                                        </div>
                                        <div class="text-right">
                                            <span class="text-sm font-semibold" :class="attainmentColor(row.status)">
                                                {{ formatDecimal(row.attainment_pct) }}%
                                            </span>
                                            <div class="text-xs text-gray-500 dark:text-gray-400">
                                                {{ formatNumber(row.actual) }} / {{ formatNumber(row.pro_rated_target) }}
                                                <span v-if="row.lower_is_better" class="text-gray-400">(lower=better)</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="h-1.5 bg-gray-100 dark:bg-gray-700 rounded-full overflow-hidden">
                                        <div
                                            class="h-1.5 transition-all"
                                            :class="attainmentBar(row.status)"
                                            :style="{ width: Math.min(100, row.attainment_pct) + '%' }"
                                        ></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Ranking + Quartile -->
                        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                            <div class="flex items-center justify-between mb-4">
                                <h3 class="text-lg font-medium text-gray-900 dark:text-white">Team Ranking</h3>
                                <div class="flex items-center gap-2">
                                    <label class="text-xs text-gray-500 dark:text-gray-400">Metric:</label>
                                    <select
                                        :value="rankMetric"
                                        @change="changeRankMetric($event.target.value)"
                                        class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-xs"
                                    >
                                        <option v-for="opt in rankMetricOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                                    </select>
                                </div>
                            </div>
                            <div v-if="ranking.total_users" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                                <div>
                                    <div class="text-xs uppercase tracking-wider text-gray-500 dark:text-gray-400">Rank</div>
                                    <div class="mt-2 text-3xl font-bold text-gray-900 dark:text-white">
                                        #{{ ranking.rank }}<span class="text-base font-normal text-gray-500 dark:text-gray-400 ml-1">/ {{ ranking.total_users }}</span>
                                    </div>
                                </div>
                                <div>
                                    <div class="text-xs uppercase tracking-wider text-gray-500 dark:text-gray-400">Percentile</div>
                                    <div class="mt-2 text-3xl font-bold text-gray-900 dark:text-white">{{ formatDecimal(ranking.percentile) }}<span class="text-base font-normal text-gray-500 dark:text-gray-400 ml-1">%</span></div>
                                </div>
                                <div>
                                    <div class="text-xs uppercase tracking-wider text-gray-500 dark:text-gray-400">Quartile</div>
                                    <div class="mt-2">
                                        <span class="px-3 py-1 text-sm font-semibold rounded-full" :class="quartileColors[ranking.quartile]">
                                            {{ quartileLabels[ranking.quartile] }}
                                        </span>
                                    </div>
                                </div>
                                <div>
                                    <div class="text-xs uppercase tracking-wider text-gray-500 dark:text-gray-400">vs Team Median</div>
                                    <div class="mt-2 text-2xl font-semibold text-gray-900 dark:text-white">
                                        {{ formatNumber(ranking.user_value) }}
                                        <span class="text-sm text-gray-500 dark:text-gray-400 ml-1">/ {{ formatNumber(ranking.team_median) }}</span>
                                    </div>
                                </div>
                            </div>
                            <p v-else class="text-sm text-gray-500 dark:text-gray-400">No team data to rank against in this period.</p>
                        </div>

                        <!-- Team vs Individual Deltas -->
                        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">vs Team Average</h3>
                            <div class="overflow-x-auto">
                                <table class="min-w-full text-sm">
                                    <thead class="border-b border-gray-200 dark:border-gray-700">
                                        <tr>
                                            <th class="text-left py-2 pr-4 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Metric</th>
                                            <th class="text-right py-2 px-4 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">User</th>
                                            <th class="text-right py-2 px-4 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Team Avg</th>
                                            <th class="text-right py-2 pl-4 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Δ vs Team</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700/50">
                                        <tr v-for="row in teamDeltas" :key="row.label">
                                            <td class="py-2 pr-4 text-gray-900 dark:text-white">{{ row.label }}</td>
                                            <td class="py-2 px-4 text-right text-gray-900 dark:text-white font-medium">{{ formatNumber(row.user_value) }}</td>
                                            <td class="py-2 px-4 text-right text-gray-600 dark:text-gray-300">{{ formatNumber(row.team_avg) }}</td>
                                            <td class="py-2 pl-4 text-right">
                                                <span class="inline-flex items-center" :class="deltaColor(row.trend)">
                                                    <svg v-if="row.trend !== 'flat'" class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="deltaIcon(row.trend)" />
                                                    </svg>
                                                    {{ row.delta > 0 ? '+' : '' }}{{ formatDecimal(row.delta) }}
                                                    <span class="ml-1 text-xs opacity-75">({{ row.delta_pct > 0 ? '+' : '' }}{{ formatDecimal(row.delta_pct) }}%)</span>
                                                </span>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Productivity Trend -->
                        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Productivity Trend</h3>
                            <TrendLineChart :labels="trendLabels" :datasets="trendDatasets" :height="280" />
                        </div>

                        <!-- Error Trend -->
                        <div v-if="errorTrend.length" class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Error Trend</h3>
                            <TrendLineChart :labels="errorTrendLabels" :datasets="errorTrendDatasets" :height="240" />
                        </div>

                        <!-- Heatmap -->
                        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                            <div class="flex items-center justify-between mb-4">
                                <h3 class="text-lg font-medium text-gray-900 dark:text-white">Activity Heatmap</h3>
                                <p class="text-xs text-gray-500 dark:text-gray-400">Day of week × hour of day. Darker = more events.</p>
                            </div>
                            <div class="overflow-x-auto">
                                <table class="text-xs">
                                    <thead>
                                        <tr>
                                            <th class="w-12"></th>
                                            <th v-for="h in hours" :key="h" class="w-7 text-center text-gray-500 dark:text-gray-400 font-normal">
                                                {{ h % 3 === 0 ? h : '' }}
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="(row, dayIdx) in heatmapMatrix" :key="dayIdx">
                                            <td class="pr-2 text-right text-gray-500 dark:text-gray-400">{{ dayLabels[dayIdx] }}</td>
                                            <td v-for="(value, hourIdx) in row" :key="hourIdx" class="p-0.5">
                                                <div
                                                    class="w-6 h-6 rounded"
                                                    :class="heatColor(value)"
                                                    :title="`${dayLabels[dayIdx]} ${hourIdx}:00 — ${value} events`"
                                                ></div>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Concurrent users (manager only) -->
                        <div v-if="isManager && concurrentUsers.length" class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Concurrent Active Users by Hour (team)</h3>
                            <BarChart :labels="concurrentLabels" :datasets="concurrentDatasets" :height="240" />
                        </div>

                        <!-- Sessions + Idle (KPI #25) -->
                        <div v-if="sessionAggregates.session_count" class="bg-white dark:bg-gray-800 rounded-lg shadow">
                            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                                <h3 class="text-lg font-medium text-gray-900 dark:text-white">Sessions & Idle Gaps</h3>
                                <p class="text-xs text-gray-500 dark:text-gray-400">Idle = consecutive events more than 5 min apart inside a session.</p>
                            </div>
                            <div class="grid grid-cols-2 md:grid-cols-5 divide-x divide-gray-200 dark:divide-gray-700 border-b border-gray-200 dark:border-gray-700">
                                <div class="p-4 text-center">
                                    <div class="text-xs text-gray-500 dark:text-gray-400">Sessions</div>
                                    <div class="mt-1 text-xl font-semibold text-gray-900 dark:text-white">{{ formatNumber(sessionAggregates.session_count) }}</div>
                                </div>
                                <div class="p-4 text-center">
                                    <div class="text-xs text-gray-500 dark:text-gray-400">Avg Length</div>
                                    <div class="mt-1 text-xl font-semibold text-gray-900 dark:text-white">{{ formatDecimal(sessionAggregates.avg_session_minutes) }}m</div>
                                </div>
                                <div class="p-4 text-center">
                                    <div class="text-xs text-gray-500 dark:text-gray-400">Avg Active</div>
                                    <div class="mt-1 text-xl font-semibold text-emerald-600 dark:text-emerald-400">{{ formatDecimal(sessionAggregates.avg_active_minutes) }}m</div>
                                </div>
                                <div class="p-4 text-center">
                                    <div class="text-xs text-gray-500 dark:text-gray-400">Avg Idle</div>
                                    <div class="mt-1 text-xl font-semibold text-amber-600 dark:text-amber-400">{{ formatDecimal(sessionAggregates.avg_idle_minutes) }}m</div>
                                </div>
                                <div class="p-4 text-center">
                                    <div class="text-xs text-gray-500 dark:text-gray-400">Longest</div>
                                    <div class="mt-1 text-xl font-semibold text-gray-900 dark:text-white">{{ formatDecimal(sessionAggregates.longest_session_minutes) }}m</div>
                                </div>
                            </div>
                            <div class="overflow-x-auto max-h-80 overflow-y-auto">
                                <table class="min-w-full text-xs">
                                    <thead class="bg-gray-50 dark:bg-gray-700/50 sticky top-0">
                                        <tr>
                                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Session</th>
                                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Started</th>
                                            <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Length</th>
                                            <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Active</th>
                                            <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Idle</th>
                                            <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Longest Gap</th>
                                            <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Events</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700/50">
                                        <tr v-for="s in sessions" :key="s.session_id" class="hover:bg-gray-50 dark:hover:bg-gray-700/30">
                                            <td class="px-4 py-2 font-mono text-gray-500 dark:text-gray-400 truncate max-w-[140px]">{{ s.session_id }}</td>
                                            <td class="px-4 py-2 text-gray-700 dark:text-gray-300">{{ s.started_at }}</td>
                                            <td class="px-4 py-2 text-right text-gray-900 dark:text-white">{{ formatDecimal(s.duration_minutes) }}m</td>
                                            <td class="px-4 py-2 text-right text-emerald-600 dark:text-emerald-400">{{ formatDecimal(s.active_minutes) }}m</td>
                                            <td class="px-4 py-2 text-right text-amber-600 dark:text-amber-400">{{ formatDecimal(s.total_idle_minutes) }}m</td>
                                            <td class="px-4 py-2 text-right" :class="s.longest_idle_minutes > 30 ? 'text-red-600 dark:text-red-400 font-semibold' : 'text-gray-700 dark:text-gray-300'">{{ formatDecimal(s.longest_idle_minutes) }}m</td>
                                            <td class="px-4 py-2 text-right text-gray-700 dark:text-gray-300">{{ formatNumber(s.event_count) }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Activity Timeline -->
                        <div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden">
                            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
                                <h3 class="text-lg font-medium text-gray-900 dark:text-white">Activity Timeline</h3>
                                <span class="text-xs text-gray-500 dark:text-gray-400">Latest {{ timeline.length }} events</span>
                            </div>
                            <div v-if="timeline.length" class="divide-y divide-gray-200 dark:divide-gray-700 max-h-[600px] overflow-y-auto">
                                <div v-for="event in timeline" :key="event.id" class="px-6 py-3 hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                    <div class="flex items-start justify-between">
                                        <div class="flex-1 min-w-0">
                                            <div class="flex items-center gap-2 mb-1">
                                                <span class="px-2 py-0.5 text-xs font-mono font-medium rounded" :class="actionBadge(event.action)">
                                                    {{ event.action }}
                                                </span>
                                                <span class="text-xs text-gray-500 dark:text-gray-400">{{ formatRelative(event.performed_at) }}</span>
                                            </div>
                                            <p class="text-sm text-gray-900 dark:text-white">{{ event.description || '—' }}</p>
                                        </div>
                                        <span class="ml-4 text-xs text-gray-400 font-mono flex-shrink-0">{{ event.performed_at }}</span>
                                    </div>
                                </div>
                            </div>
                            <div v-else class="px-6 py-12 text-center text-sm text-gray-500 dark:text-gray-400">
                                No activity in this date range.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
