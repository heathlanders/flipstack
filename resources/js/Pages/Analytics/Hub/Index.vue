<script setup>
import { computed } from 'vue';
import { router, Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { route } from 'ziggy-js';
import AnalyticsSidebar from '@/Components/Analytics/AnalyticsSidebar.vue';
import ComparisonDatePicker from '@/Components/Analytics/ComparisonDatePicker.vue';
import BusinessHealthGauge from '@/Components/Analytics/BusinessHealthGauge.vue';
import { toNum } from '@/Composables/useAnalyticsFormatters';

const props = defineProps({
    healthScore: { type: Object, default: () => ({ overall_score: 0, dimensions: {}, alerts: [] }) },
    filters: { type: Object, default: () => ({}) },
});

function applyFilters(filters) {
    router.get(route('analytics.hub.index'), filters, { preserveState: true, preserveScroll: true });
}

const overallHealthScore = computed(() => toNum(props.healthScore?.overall_score));
const healthDimensions = computed(() => props.healthScore?.dimensions || {});
const healthAlerts = computed(() => (props.healthScore?.alerts || []).map(a => ({
    severity: a.level,
    message: a.message,
})));

const reports = [
    {
        id: 'live',
        title: 'Live Ops',
        description: 'What is happening right now: shipments, picks, packs, errors. Auto-refreshes every 15 s.',
        route: 'analytics.hub.live',
        icon: 'trending-up',
        color: 'emerald',
        live: true,
    },
    {
        id: 'executive',
        title: 'Executive Overview',
        description: 'High-level revenue, profit, margin and order KPIs across all marketplaces.',
        route: 'analytics.hub.executive',
        icon: 'chart-bar',
        color: 'indigo',
    },
    {
        id: 'fulfillment',
        title: 'Fulfillment Performance',
        description: 'Order-to-ship time, on-time rate, pick accuracy, backlog age, returns.',
        route: 'analytics.hub.fulfillment',
        icon: 'truck',
        color: 'blue',
    },
    {
        id: 'workforce',
        title: 'Workforce Productivity',
        description: 'Per-user picks, packs, ships. Leaderboards, trends, error rates.',
        route: 'analytics.hub.workforce',
        icon: 'users',
        color: 'emerald',
    },
    {
        id: 'inventory',
        title: 'Inventory Health',
        description: 'Stock value, low/out-of-stock counts, ABC analysis, turnover, aging.',
        route: 'inventory.analytics.dashboard',
        icon: 'cube',
        color: 'amber',
    },
    {
        id: 'procurement',
        title: 'Procurement & Suppliers',
        description: 'Supplier lead time, on-time rate, cost trends, spend, ranking.',
        route: 'analytics.hub.procurement',
        icon: 'shopping-cart',
        color: 'cyan',
    },
    {
        id: 'marketplace',
        title: 'Marketplace Health',
        description: 'Sync health, listing health, response time, errors, fees, comparison.',
        route: 'analytics.hub.marketplace',
        icon: 'globe',
        color: 'violet',
    },
    {
        id: 'customers',
        title: 'Customer Analytics',
        description: 'LTV, repeat rate, AOV trend, acquisition, segments, top customers.',
        route: 'analytics.hub.customers',
        icon: 'user-group',
        color: 'pink',
    },
    {
        id: 'communication',
        title: 'Communication SLA',
        description: 'Response times, SLA compliance, message volume, unread backlog.',
        route: 'analytics.hub.communication',
        icon: 'chat',
        color: 'gray',
    },
    {
        id: 'marketing',
        title: 'Marketing & Email',
        description: 'Campaign performance, deliverability, open/click engagement trends.',
        route: 'analytics.hub.marketing',
        icon: 'mail',
        color: 'red',
    },
    {
        id: 'pricing',
        title: 'Pricing & Repricing',
        description: 'Repricing frequency, margin impact, competitive position, rule performance.',
        route: 'analytics.hub.pricing',
        icon: 'tag',
        color: 'slate',
    },
    {
        id: 'compensation',
        title: 'Compensation',
        description: 'Per-user settlement-ready reports, commission attribution, tier-based bonuses.',
        route: 'analytics.hub.compensation',
        icon: 'tag',
        color: 'emerald',
    },
];

function resolveRoute(name) {
    try {
        return route(name);
    } catch {
        return '#';
    }
}

const colorBg = {
    indigo: 'bg-indigo-50 dark:bg-indigo-900/20',
    blue: 'bg-blue-50 dark:bg-blue-900/20',
    emerald: 'bg-emerald-50 dark:bg-emerald-900/20',
    amber: 'bg-amber-50 dark:bg-amber-900/20',
    cyan: 'bg-cyan-50 dark:bg-cyan-900/20',
    violet: 'bg-violet-50 dark:bg-violet-900/20',
    pink: 'bg-pink-50 dark:bg-pink-900/20',
    gray: 'bg-gray-100 dark:bg-gray-800/50',
    red: 'bg-red-50 dark:bg-red-900/20',
    slate: 'bg-slate-50 dark:bg-slate-900/20',
};

const colorText = {
    indigo: 'text-indigo-600 dark:text-indigo-400',
    blue: 'text-blue-600 dark:text-blue-400',
    emerald: 'text-emerald-600 dark:text-emerald-400',
    amber: 'text-amber-600 dark:text-amber-400',
    cyan: 'text-cyan-600 dark:text-cyan-400',
    violet: 'text-violet-600 dark:text-violet-400',
    pink: 'text-pink-600 dark:text-pink-400',
    gray: 'text-gray-600 dark:text-gray-400',
    red: 'text-red-600 dark:text-red-400',
    slate: 'text-slate-600 dark:text-slate-400',
};

const icons = {
    'chart-bar': 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z',
    'truck': 'M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1M5 17a2 2 0 104 0m-4 0a2 2 0 114 0m6 0a2 2 0 104 0m-4 0a2 2 0 114 0',
    'users': 'M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z',
    'cube': 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4',
    'shopping-cart': 'M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 100 4 2 2 0 000-4z',
    'globe': 'M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
    'user-group': 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z',
    'chat': 'M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z',
    'mail': 'M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z',
    'tag': 'M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z',
    'trending-up': 'M13 7h8m0 0v8m0-8l-8 8-4-4-6 6',
};
</script>

<template>
    <Head title="Analytics Hub" />
    <AppLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200">Analytics Hub</h2>
                <ComparisonDatePicker :filters="filters" @change="applyFilters" />
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex gap-6">
                    <AnalyticsSidebar currentPage="hub" :healthScore="overallHealthScore" />

                    <div class="flex-1 space-y-6">
                        <!-- Health Summary -->
                        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                            <div class="flex items-start gap-6">
                                <div class="flex-shrink-0">
                                    <BusinessHealthGauge
                                        :score="overallHealthScore"
                                        :dimensions="healthDimensions"
                                        :alerts="healthAlerts"
                                        compact
                                    />
                                </div>
                                <div class="flex-1">
                                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">
                                        Welcome to your Analytics Hub
                                    </h3>
                                    <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                                        Each report below is its own dedicated page. Pick a report to drill into the data, or use the sidebar to jump between them.
                                    </p>
                                    <Link
                                        :href="route('analytics.hub.executive')"
                                        class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-md hover:bg-indigo-700"
                                    >
                                        Open Executive Overview
                                        <svg class="ml-2 w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                                        </svg>
                                    </Link>
                                </div>
                            </div>
                        </div>

                        <!-- Reports Grid -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                            <Link
                                v-for="report in reports"
                                :key="report.id"
                                :href="resolveRoute(report.route)"
                                class="group block bg-white dark:bg-gray-800 rounded-lg shadow-sm hover:shadow-lg border border-transparent hover:border-indigo-300 dark:hover:border-indigo-600 transition-all p-5"
                            >
                                <div class="flex items-start gap-3">
                                    <div
                                        class="flex-shrink-0 w-10 h-10 rounded-lg flex items-center justify-center"
                                        :class="colorBg[report.color]"
                                    >
                                        <svg
                                            class="w-5 h-5"
                                            :class="colorText[report.color]"
                                            fill="none"
                                            stroke="currentColor"
                                            viewBox="0 0 24 24"
                                        >
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="icons[report.icon]" />
                                        </svg>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center gap-2">
                                            <h4 class="text-base font-semibold text-gray-900 dark:text-white group-hover:text-indigo-600 dark:group-hover:text-indigo-400">
                                                {{ report.title }}
                                            </h4>
                                            <span
                                                v-if="report.live"
                                                class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded-full bg-emerald-100 dark:bg-emerald-900/40 text-emerald-700 dark:text-emerald-300 text-[10px] font-bold uppercase tracking-wider"
                                            >
                                                <span class="w-1 h-1 rounded-full bg-emerald-500 animate-pulse"></span>
                                                Live
                                            </span>
                                        </div>
                                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400 leading-relaxed">
                                            {{ report.description }}
                                        </p>
                                    </div>
                                </div>
                                <div class="mt-3 flex items-center text-xs font-medium text-indigo-600 dark:text-indigo-400 opacity-0 group-hover:opacity-100 transition-opacity">
                                    View report
                                    <svg class="ml-1 w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                                    </svg>
                                </div>
                            </Link>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
