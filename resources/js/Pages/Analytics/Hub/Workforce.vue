<script setup>
import { ref, computed } from 'vue';
import { router, Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { route } from 'ziggy-js';
import AnalyticsSidebar from '@/Components/Analytics/AnalyticsSidebar.vue';
import ComparisonDatePicker from '@/Components/Analytics/ComparisonDatePicker.vue';
import KPICard from '@/Components/Analytics/KPICard.vue';
import TrendLineChart from '@/Components/Analytics/TrendLineChart.vue';
import BarChart from '@/Components/Analytics/BarChart.vue';
import LeaderboardTable from '@/Components/Analytics/LeaderboardTable.vue';

const props = defineProps({
    teamOverview: { type: Object, default: () => ({}) },
    leaderboard: { type: Array, default: () => [] },
    individualPerformance: { type: Object, default: () => ({}) },
    productivityTrend: { type: Array, default: () => [] },
    errorRates: { type: Array, default: () => [] },
    teamComparison: { type: Array, default: () => [] },
    weekComparison: { type: Object, default: () => ({ metrics: {} }) },
    monthComparison: { type: Object, default: () => ({ metrics: {} }) },
    attributionOverview: { type: Object, default: null },
    returnsByPacker: { type: Array, default: () => [] },
    cancellationByHandler: { type: Array, default: () => [] },
    refundsByLister: { type: Array, default: () => [] },
    laborCostOverview: { type: Object, default: null },
    laborCostByUser: { type: Array, default: () => [] },
    teamMembers: { type: Array, default: () => [] },
    auditProductivity: { type: Object, default: null },
    isManager: { type: Boolean, default: false },
    selectedUserId: { type: [String, Number], default: null },
    filters: { type: Object, default: () => ({}) },
});

const periodRange = ref('week');
const activeComparison = computed(() => periodRange.value === 'month' ? props.monthComparison : props.weekComparison);

const periodMetrics = computed(() => {
    const m = activeComparison.value?.metrics || {};
    return [
        { key: 'items_picked', label: 'Items Picked' },
        { key: 'items_packed', label: 'Items Packed' },
        { key: 'shipments_created', label: 'Shipments' },
        { key: 'returns_processed', label: 'Returns' },
        { key: 'total_errors', label: 'Errors', lowerIsBetter: true },
        { key: 'active_users', label: 'Active Users' },
    ].map(meta => ({ ...meta, ...(m[meta.key] || { current: 0, previous: 0, change_pct: 0 }) }));
});

function leaderboardLabel(key) {
    return {
        products_listed: 'Products listed (top 10)',
        pos_created: 'POs created (top 10)',
        messages_answered: 'Messages answered (top 10)',
        listings_published_ebay: 'eBay listings published (top 10)',
    }[key] || key;
}

function periodTrendColor(metric) {
    if (metric.change_pct === 0) return 'text-gray-500 dark:text-gray-400';
    const positive = metric.change_pct > 0;
    if (metric.lowerIsBetter) return positive ? 'text-red-600 dark:text-red-400' : 'text-emerald-600 dark:text-emerald-400';
    return positive ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400';
}

import { formatCurrency, formatPercent, formatNumber, formatDecimal } from '@/Composables/useAnalyticsFormatters';

const selectedUser = ref(props.selectedUserId);

function applyFilters(filters) {
    router.get(route('analytics.hub.workforce'), filters, { preserveState: true, preserveScroll: true });
}

function selectUser(userId) {
    selectedUser.value = userId;
    router.get(route('analytics.hub.workforce'), {
        ...props.filters,
        user_id: userId,
    }, { preserveState: true, preserveScroll: true });
}

// Productivity Trend chart data
const productivityTrendLabels = computed(() => props.productivityTrend.map(d => d.date));
const productivityTrendDatasets = computed(() => [
    { label: 'Items Processed', data: props.productivityTrend.map(d => d.items_processed), color: '#6366f1' },
]);

// Error Rates chart data
const errorRateLabels = computed(() => props.errorRates.map(e => e.user_name));
const errorRateDatasets = computed(() => [
    { label: 'Error Rate %', data: props.errorRates.map(e => e.error_rate), color: '#ef4444' },
]);

// Team Comparison chart data
const teamComparisonLabels = computed(() => props.teamComparison.map(t => t.team_name));
const teamComparisonDatasets = computed(() => [
    { label: 'Items Processed', data: props.teamComparison.map(t => t.items_processed), color: '#6366f1' },
    { label: 'Accuracy %', data: props.teamComparison.map(t => t.accuracy), color: '#10b981' },
]);

const perf = computed(() => props.individualPerformance || {});
</script>

<template>
    <Head title="Workforce Analytics" />
    <AppLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200">Workforce Analytics</h2>
                <ComparisonDatePicker :filters="filters" @change="applyFilters" />
            </div>
        </template>
        <div class="py-6">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex gap-6">
                    <AnalyticsSidebar currentPage="workforce" />
                    <div class="flex-1 space-y-6">
                        <!-- Export bar (managers only) -->
                        <div v-if="isManager" class="flex justify-end gap-2 text-xs">
                            <span class="text-gray-500 dark:text-gray-400 self-center">Export:</span>
                            <a :href="route('analytics.reports.export', { view: 'workforce_individual', format: 'csv', start_date: filters.start_date, end_date: filters.end_date })" class="px-2 py-1 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded hover:bg-gray-50 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-300">CSV</a>
                            <a :href="route('analytics.reports.export', { view: 'workforce_individual', format: 'xlsx', start_date: filters.start_date, end_date: filters.end_date })" class="px-2 py-1 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded hover:bg-gray-50 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-300">XLSX</a>
                            <a :href="route('analytics.reports.export', { view: 'workforce_individual', format: 'pdf', start_date: filters.start_date, end_date: filters.end_date })" class="px-2 py-1 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded hover:bg-gray-50 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-300">PDF</a>
                            <Link :href="route('analytics.reports.index')" class="px-2 py-1 bg-indigo-600 text-white rounded hover:bg-indigo-700">Manage Reports</Link>
                        </div>

                        <!-- Period-over-period strip -->
                        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-5">
                            <div class="flex items-center justify-between mb-3">
                                <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wider">
                                    {{ periodRange === 'month' ? 'Last 30 Days vs Prior 30' : 'Last 7 Days vs Prior 7' }}
                                </h3>
                                <div class="inline-flex rounded-md border border-gray-200 dark:border-gray-700 overflow-hidden text-xs">
                                    <button
                                        @click="periodRange = 'week'"
                                        class="px-3 py-1.5 transition-colors"
                                        :class="periodRange === 'week' ? 'bg-indigo-600 text-white' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700/50'"
                                    >Week</button>
                                    <button
                                        @click="periodRange = 'month'"
                                        class="px-3 py-1.5 transition-colors"
                                        :class="periodRange === 'month' ? 'bg-indigo-600 text-white' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700/50'"
                                    >Month</button>
                                </div>
                            </div>
                            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3">
                                <div v-for="m in periodMetrics" :key="m.key" class="bg-gray-50 dark:bg-gray-700/30 rounded-lg p-3">
                                    <div class="text-xs text-gray-500 dark:text-gray-400 truncate">{{ m.label }}</div>
                                    <div class="mt-1 text-xl font-semibold text-gray-900 dark:text-white">{{ formatNumber(m.current) }}</div>
                                    <div class="mt-1 flex items-center text-xs" :class="periodTrendColor(m)">
                                        <svg v-if="m.change_pct !== 0" class="w-3 h-3 mr-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="m.change_pct > 0 ? 'M5 10l7-7m0 0l7 7m-7-7v18' : 'M19 14l-7 7m0 0l-7-7m7 7V3'" />
                                        </svg>
                                        {{ m.change_pct > 0 ? '+' : '' }}{{ formatDecimal(m.change_pct) }}%
                                        <span class="ml-1 text-gray-400 dark:text-gray-500">vs {{ formatNumber(m.previous) }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Team Overview KPIs -->
                        <div class="grid grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 gap-4">
                            <KPICard
                                title="Total Items Processed"
                                :value="formatNumber(teamOverview.total_items_processed)"
                                icon="cube"
                                color="blue"
                            />
                            <KPICard
                                title="Avg Pick Rate"
                                :value="`${formatNumber(teamOverview.avg_pick_rate)} items/hr`"
                                icon="chart-bar"
                                color="indigo"
                            />
                            <KPICard
                                title="Avg Pack Rate"
                                :value="`${formatNumber(teamOverview.avg_pack_rate)} items/hr`"
                                icon="chart-bar"
                                color="emerald"
                            />
                            <KPICard
                                title="Error Rate"
                                :value="formatPercent(teamOverview.error_rate)"
                                icon="exclamation-triangle"
                                color="amber"
                                :alertLevel="(teamOverview.error_rate || 0) > 2 ? 'warning' : 'normal'"
                            />
                            <KPICard
                                title="Active Workers"
                                :value="formatNumber(teamOverview.active_workers)"
                                icon="chart-bar"
                                color="violet"
                            />
                        </div>

                        <!-- Leaderboard (managers only) -->
                        <div v-if="isManager && leaderboard.length" class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Picking Leaderboard</h3>
                            <LeaderboardTable :data="leaderboard" />
                        </div>

                        <!-- Team Members (always visible to managers) -->
                        <div v-if="isManager" class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden">
                            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
                                <h3 class="text-lg font-medium text-gray-900 dark:text-white">Team Members</h3>
                                <span class="text-xs text-gray-500 dark:text-gray-400">{{ teamMembers.length }} users · click to drill into per-user analytics</span>
                            </div>
                            <div v-if="teamMembers.length" class="overflow-x-auto">
                                <table class="min-w-full text-sm">
                                    <thead class="bg-gray-50 dark:bg-gray-700/50">
                                        <tr>
                                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">User</th>
                                            <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Items Picked</th>
                                            <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Items Packed</th>
                                            <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Shipments</th>
                                            <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Returns</th>
                                            <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Days Active</th>
                                            <th class="px-4 py-2"></th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700/50">
                                        <tr v-for="m in teamMembers" :key="m.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/30">
                                            <td class="px-4 py-2">
                                                <div class="text-gray-900 dark:text-white font-medium">{{ m.name }}</div>
                                                <div class="text-xs text-gray-500 dark:text-gray-400">{{ m.email }}</div>
                                            </td>
                                            <td class="px-4 py-2 text-right text-gray-700 dark:text-gray-300">{{ formatNumber(m.items_picked) }}</td>
                                            <td class="px-4 py-2 text-right text-gray-700 dark:text-gray-300">{{ formatNumber(m.items_packed) }}</td>
                                            <td class="px-4 py-2 text-right text-gray-700 dark:text-gray-300">{{ formatNumber(m.shipments_created) }}</td>
                                            <td class="px-4 py-2 text-right text-gray-700 dark:text-gray-300">{{ formatNumber(m.returns_processed) }}</td>
                                            <td class="px-4 py-2 text-right text-gray-700 dark:text-gray-300">{{ formatNumber(m.days_active) }}</td>
                                            <td class="px-4 py-2 text-right">
                                                <Link :href="route('analytics.hub.users.show', m.id)" class="inline-flex items-center text-xs text-indigo-600 dark:text-indigo-400 hover:underline">
                                                    Drill in
                                                    <svg class="ml-1 w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" /></svg>
                                                </Link>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <div v-else class="px-6 py-12 text-center text-sm text-gray-500 dark:text-gray-400">
                                No users in this organization.
                            </div>
                        </div>

                        <!-- Labor Cost (managers only) -->
                        <div v-if="isManager && laborCostOverview" class="bg-white dark:bg-gray-800 rounded-lg shadow">
                            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
                                <div>
                                    <h3 class="text-lg font-medium text-gray-900 dark:text-white">Labor Cost Analytics</h3>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ laborCostOverview.users_with_rates }} users with rates set · {{ formatCurrency(laborCostOverview.total_labor_cost) }} total in period</p>
                                </div>
                                <Link :href="route('settings.labor-rates.index')" class="text-xs text-indigo-600 dark:text-indigo-400 hover:underline">Manage rates →</Link>
                            </div>
                            <div class="grid grid-cols-2 md:grid-cols-4 divide-x divide-gray-200 dark:divide-gray-700 border-b border-gray-200 dark:border-gray-700">
                                <div class="p-4 text-center">
                                    <div class="text-xs text-gray-500 dark:text-gray-400">Avg per Picked Unit</div>
                                    <div class="mt-1 text-xl font-semibold text-gray-900 dark:text-white">{{ formatCurrency(laborCostOverview.avg_cost_per_picked_unit) }}</div>
                                </div>
                                <div class="p-4 text-center">
                                    <div class="text-xs text-gray-500 dark:text-gray-400">Avg per Listed Item</div>
                                    <div class="mt-1 text-xl font-semibold text-gray-900 dark:text-white">{{ formatCurrency(laborCostOverview.avg_cost_per_listed_item) }}</div>
                                </div>
                                <div class="p-4 text-center">
                                    <div class="text-xs text-gray-500 dark:text-gray-400">Avg per Ticket</div>
                                    <div class="mt-1 text-xl font-semibold text-gray-900 dark:text-white">{{ formatCurrency(laborCostOverview.avg_cost_per_ticket) }}</div>
                                </div>
                                <div class="p-4 text-center">
                                    <div class="text-xs text-gray-500 dark:text-gray-400">Avg Cost-to-Serve</div>
                                    <div class="mt-1 text-xl font-semibold text-gray-900 dark:text-white">{{ formatCurrency(laborCostOverview.avg_cost_to_serve_per_order) }}</div>
                                </div>
                            </div>
                            <div v-if="laborCostByUser.length" class="overflow-x-auto">
                                <table class="min-w-full text-sm">
                                    <thead class="bg-gray-50 dark:bg-gray-700/50">
                                        <tr>
                                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">User</th>
                                            <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Rate/hr</th>
                                            <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Hours</th>
                                            <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Total Cost</th>
                                            <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">$/Pick</th>
                                            <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">$/Listing</th>
                                            <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">$/Order</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700/50">
                                        <tr v-for="row in laborCostByUser" :key="row.user_id" class="hover:bg-gray-50 dark:hover:bg-gray-700/30">
                                            <td class="px-4 py-2">
                                                <Link :href="route('analytics.hub.users.show', row.user_id)" class="text-blue-600 dark:text-blue-400 hover:underline">{{ row.user_name }}</Link>
                                            </td>
                                            <td class="px-4 py-2 text-right text-gray-900 dark:text-white">{{ formatCurrency(row.hourly_rate) }}</td>
                                            <td class="px-4 py-2 text-right text-gray-700 dark:text-gray-300">{{ formatDecimal(row.active_hours) }}</td>
                                            <td class="px-4 py-2 text-right font-semibold text-gray-900 dark:text-white">{{ formatCurrency(row.total_labor_cost) }}</td>
                                            <td class="px-4 py-2 text-right text-gray-700 dark:text-gray-300">{{ formatCurrency(row.cost_per_picked_unit) }}</td>
                                            <td class="px-4 py-2 text-right text-gray-700 dark:text-gray-300">{{ formatCurrency(row.cost_per_listed_item) }}</td>
                                            <td class="px-4 py-2 text-right text-gray-700 dark:text-gray-300">{{ formatCurrency(row.cost_to_serve_per_order) }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <div v-else class="px-6 py-12 text-center text-sm text-gray-500 dark:text-gray-400">
                                No labor rates set yet. <Link :href="route('settings.labor-rates.index')" class="text-indigo-600 dark:text-indigo-400 hover:underline">Set rates →</Link>
                            </div>
                        </div>

                        <!-- Error Attribution (managers only) -->
                        <div v-if="isManager && attributionOverview" class="bg-white dark:bg-gray-800 rounded-lg shadow">
                            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                                <h3 class="text-lg font-medium text-gray-900 dark:text-white">Error Attribution</h3>
                                <div class="mt-2 grid grid-cols-2 md:grid-cols-4 gap-3 text-xs">
                                    <div>
                                        <div class="text-gray-500 dark:text-gray-400">Total Orders</div>
                                        <div class="text-base font-semibold text-gray-900 dark:text-white">{{ formatNumber(attributionOverview.total_orders) }}</div>
                                    </div>
                                    <div>
                                        <div class="text-gray-500 dark:text-gray-400">Cancel Rate</div>
                                        <div class="text-base font-semibold" :class="attributionOverview.cancel_rate > 5 ? 'text-red-600 dark:text-red-400' : 'text-gray-900 dark:text-white'">{{ formatDecimal(attributionOverview.cancel_rate) }}%</div>
                                    </div>
                                    <div>
                                        <div class="text-gray-500 dark:text-gray-400">Return Rate</div>
                                        <div class="text-base font-semibold" :class="attributionOverview.return_rate > 5 ? 'text-amber-600 dark:text-amber-400' : 'text-gray-900 dark:text-white'">{{ formatDecimal(attributionOverview.return_rate) }}%</div>
                                    </div>
                                    <div>
                                        <div class="text-gray-500 dark:text-gray-400">Refunds Issued</div>
                                        <div class="text-base font-semibold text-gray-900 dark:text-white">{{ formatCurrency(attributionOverview.refund_total) }}</div>
                                    </div>
                                </div>
                            </div>
                            <div class="grid grid-cols-1 lg:grid-cols-3 divide-y lg:divide-y-0 lg:divide-x divide-gray-200 dark:divide-gray-700">
                                <!-- Returns by Packer -->
                                <div class="p-5">
                                    <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wider mb-3">Returns by Packer</h4>
                                    <div v-if="returnsByPacker.length" class="space-y-2 text-sm">
                                        <div v-for="row in returnsByPacker.slice(0, 8)" :key="row.user_id" class="flex items-center justify-between">
                                            <Link :href="route('analytics.hub.users.show', row.user_id)" class="text-blue-600 dark:text-blue-400 hover:underline truncate">{{ row.user_name }}</Link>
                                            <div class="text-right ml-2">
                                                <div class="text-gray-900 dark:text-white font-medium">{{ formatDecimal(row.return_rate) }}%</div>
                                                <div class="text-xs text-gray-500 dark:text-gray-400">{{ row.returns_filed }}/{{ row.orders_packed }}</div>
                                            </div>
                                        </div>
                                    </div>
                                    <p v-else class="text-xs text-gray-500 dark:text-gray-400">No packed orders in period.</p>
                                </div>
                                <!-- Cancellations by Handler -->
                                <div class="p-5">
                                    <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wider mb-3">Cancellations by Handler</h4>
                                    <div v-if="cancellationByHandler.length" class="space-y-2 text-sm">
                                        <div v-for="row in cancellationByHandler.slice(0, 8)" :key="row.user_id" class="flex items-center justify-between">
                                            <Link :href="route('analytics.hub.users.show', row.user_id)" class="text-blue-600 dark:text-blue-400 hover:underline truncate">{{ row.user_name }}</Link>
                                            <div class="text-right ml-2">
                                                <div class="text-xs">
                                                    <span class="text-gray-700 dark:text-gray-300">P {{ formatDecimal(row.picker_cancel_rate) }}%</span>
                                                    <span class="ml-2 text-gray-500 dark:text-gray-400">/ K {{ formatDecimal(row.packer_cancel_rate) }}%</span>
                                                </div>
                                                <div class="text-xs text-gray-500 dark:text-gray-400">{{ row.total_cancelled }} cancelled</div>
                                            </div>
                                        </div>
                                    </div>
                                    <p v-else class="text-xs text-gray-500 dark:text-gray-400">No handlers attributed in period.</p>
                                </div>
                                <!-- Refunds by Lister -->
                                <div class="p-5">
                                    <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wider mb-3">Refunds by Lister</h4>
                                    <div v-if="refundsByLister.length" class="space-y-2 text-sm">
                                        <div v-for="row in refundsByLister.slice(0, 8)" :key="row.user_id" class="flex items-center justify-between">
                                            <Link :href="route('analytics.hub.users.show', row.user_id)" class="text-blue-600 dark:text-blue-400 hover:underline truncate">{{ row.user_name }}</Link>
                                            <div class="text-right ml-2">
                                                <div class="text-gray-900 dark:text-white font-medium">{{ formatDecimal(row.refund_rate) }}%</div>
                                                <div class="text-xs text-gray-500 dark:text-gray-400">{{ formatCurrency(row.refund_total) }}</div>
                                            </div>
                                        </div>
                                    </div>
                                    <p v-else class="text-xs text-gray-500 dark:text-gray-400">No refunded items in period.</p>
                                </div>
                            </div>
                        </div>

                        <!-- Individual Performance -->
                        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                            <div class="flex items-center justify-between mb-4">
                                <h3 class="text-lg font-medium text-gray-900 dark:text-white">
                                    {{ isManager ? 'Individual Performance' : 'My Stats' }}
                                </h3>
                                <div v-if="isManager && leaderboard.length" class="flex items-center gap-2">
                                    <label class="text-sm text-gray-500 dark:text-gray-400">Worker:</label>
                                    <select
                                        :value="selectedUser"
                                        @change="selectUser($event.target.value)"
                                        class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-sm focus:ring-indigo-500 focus:border-indigo-500"
                                    >
                                        <option v-for="worker in leaderboard" :key="worker.user_id" :value="worker.user_id">
                                            {{ worker.user_name }}
                                        </option>
                                    </select>
                                    <Link
                                        v-if="selectedUser"
                                        :href="route('analytics.hub.users.show', selectedUser)"
                                        class="inline-flex items-center px-3 py-1.5 bg-indigo-600 text-white text-xs font-medium rounded-md hover:bg-indigo-700"
                                    >
                                        Drill into details
                                        <svg class="ml-1 w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" /></svg>
                                    </Link>
                                </div>
                            </div>
                            <div class="grid grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 gap-4">
                                <KPICard
                                    title="Orders Picked"
                                    :value="formatNumber(perf.orders_picked)"
                                    icon="cube"
                                    color="blue"
                                />
                                <KPICard
                                    title="Items Picked"
                                    :value="formatNumber(perf.items_picked)"
                                    icon="cube"
                                    color="indigo"
                                />
                                <KPICard
                                    title="Pick Accuracy"
                                    :value="formatPercent(perf.pick_accuracy)"
                                    icon="chart-bar"
                                    color="emerald"
                                    :alertLevel="(perf.pick_accuracy || 100) < 98 ? 'warning' : 'normal'"
                                />
                                <KPICard
                                    title="Orders Packed"
                                    :value="formatNumber(perf.orders_packed)"
                                    icon="archive"
                                    color="violet"
                                />
                                <KPICard
                                    title="Avg Pick Duration"
                                    :value="`${formatDecimal(perf.avg_pick_duration)} min`"
                                    icon="chart-bar"
                                    color="cyan"
                                />
                            </div>
                        </div>

                        <!-- Productivity Trend -->
                        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Productivity Trend</h3>
                            <TrendLineChart
                                :labels="productivityTrendLabels"
                                :datasets="productivityTrendDatasets"
                                :height="300"
                            />
                        </div>

                        <!-- Error Rates (managers only) -->
                        <div v-if="isManager && errorRates.length" class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Error Rates by Worker</h3>
                            <BarChart
                                :labels="errorRateLabels"
                                :datasets="errorRateDatasets"
                                :height="280"
                            />
                        </div>

                        <!-- Team Comparison (managers only) -->
                        <div v-if="isManager && teamComparison.length" class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Team Comparison</h3>
                            <BarChart
                                :labels="teamComparisonLabels"
                                :datasets="teamComparisonDatasets"
                                :height="300"
                            />
                        </div>
                    </div>

                    <!-- Audit-derived productivity (PR5: UserProductivityDaily) -->
                    <div v-if="isManager && auditProductivity" class="mt-6 space-y-6">
                        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-1">Audit-derived productivity</h3>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mb-4">
                                Per-user counts from `user_activity_logs` (listing throughput, PO throughput, message answers, marketplace publishes). Aggregated nightly from audit rows.
                            </p>

                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                    <thead class="bg-gray-50 dark:bg-gray-700/50">
                                        <tr>
                                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">User</th>
                                            <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Products listed</th>
                                            <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">POs created</th>
                                            <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">POs received</th>
                                            <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Msgs answered</th>
                                            <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Inv. moves</th>
                                            <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">eBay pub</th>
                                            <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Shopify pub</th>
                                            <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Amazon pub</th>
                                            <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Cust. touched</th>
                                            <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Pricing rules</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                        <tr v-for="row in auditProductivity.per_user_totals" :key="row.user_id">
                                            <td class="px-3 py-2 text-sm text-gray-700 dark:text-gray-200">{{ row.user?.name || 'Unknown' }}</td>
                                            <td class="px-3 py-2 text-right text-sm">{{ formatNumber(row.products_listed) }}</td>
                                            <td class="px-3 py-2 text-right text-sm">{{ formatNumber(row.pos_created) }}</td>
                                            <td class="px-3 py-2 text-right text-sm">{{ formatNumber(row.pos_received) }}</td>
                                            <td class="px-3 py-2 text-right text-sm">{{ formatNumber(row.messages_answered) }}</td>
                                            <td class="px-3 py-2 text-right text-sm">{{ formatNumber(row.inventory_moves) }}</td>
                                            <td class="px-3 py-2 text-right text-sm">{{ formatNumber(row.listings_ebay) }}</td>
                                            <td class="px-3 py-2 text-right text-sm">{{ formatNumber(row.listings_shopify) }}</td>
                                            <td class="px-3 py-2 text-right text-sm">{{ formatNumber(row.listings_amazon) }}</td>
                                            <td class="px-3 py-2 text-right text-sm">{{ formatNumber(row.customer_records_touched) }}</td>
                                            <td class="px-3 py-2 text-right text-sm">{{ formatNumber(row.pricing_rules_changed) }}</td>
                                        </tr>
                                        <tr v-if="!auditProductivity.per_user_totals?.length">
                                            <td colspan="11" class="px-3 py-6 text-center text-sm text-gray-500 dark:text-gray-400">
                                                No audit-derived data yet. The nightly aggregator runs at 02:15 UTC; backfill from existing audit rows by dispatching <code>AggregateAuditDerivedKPIs</code> for past dates.
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Leaderboards for top audit-derived KPIs -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div v-for="(board, key) in auditProductivity.leaderboards" :key="key" class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                                <h4 class="text-sm font-medium text-gray-700 dark:text-gray-200 mb-3">{{ leaderboardLabel(key) }}</h4>
                                <div v-if="board.length === 0" class="text-xs text-gray-400">No data.</div>
                                <ol v-else class="space-y-1">
                                    <li v-for="(entry, idx) in board" :key="entry.user_id" class="flex items-center justify-between text-sm">
                                        <span class="text-gray-700 dark:text-gray-300">
                                            <span class="inline-block w-6 text-gray-400">{{ idx + 1 }}.</span>
                                            {{ entry.user_name }}
                                        </span>
                                        <span class="font-mono text-gray-900 dark:text-gray-100">{{ formatNumber(entry.value) }}</span>
                                    </li>
                                </ol>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
