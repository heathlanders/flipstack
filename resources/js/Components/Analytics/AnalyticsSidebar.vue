<script setup>
import { ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import { route } from 'ziggy-js';

const props = defineProps({
    currentPage: {
        type: String,
        required: true,
    },
    healthScore: {
        type: Number,
        default: null,
    },
});

const mobileOpen = ref(false);

const navItems = [
    { id: 'hub', label: 'Hub Home', route: 'analytics.hub.index', icon: 'chart-bar' },
    { id: 'live', label: 'Live Ops', route: 'analytics.hub.live', icon: 'trending-up', live: true },
    { id: 'executive', label: 'Executive Overview', route: 'analytics.hub.executive', icon: 'chart-bar' },
    { id: 'fulfillment', label: 'Fulfillment Performance', route: 'analytics.hub.fulfillment', icon: 'truck' },
    { id: 'workforce', label: 'Workforce Productivity', route: 'analytics.hub.workforce', icon: 'users' },
    { id: 'inventory', label: 'Inventory Health', route: 'inventory.analytics.dashboard', icon: 'cube' },
    { id: 'procurement', label: 'Procurement & Suppliers', route: 'analytics.hub.procurement', icon: 'shopping-cart' },
    { id: 'marketplace', label: 'Marketplace Health', route: 'analytics.hub.marketplace', icon: 'globe' },
    { id: 'customers', label: 'Customer Analytics', route: 'analytics.hub.customers', icon: 'user-group' },
    { id: 'communication', label: 'Communication SLA', route: 'analytics.hub.communication', icon: 'chat' },
    { id: 'marketing', label: 'Marketing & Email', route: 'analytics.hub.marketing', icon: 'mail' },
    { id: 'pricing', label: 'Pricing & Repricing', route: 'analytics.hub.pricing', icon: 'tag' },
    { id: 'compensation', label: 'Compensation', route: 'analytics.hub.compensation', icon: 'tag' },
    { id: 'reports', label: 'Reports & Exports', route: 'analytics.reports.index', icon: 'mail' },
];

const icons = {
    'chart-bar': '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />',
    'truck': '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1M5 17a2 2 0 104 0m-4 0a2 2 0 114 0m6 0a2 2 0 104 0m-4 0a2 2 0 114 0" />',
    'users': '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />',
    'cube': '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />',
    'shopping-cart': '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 100 4 2 2 0 000-4z" />',
    'globe': '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />',
    'user-group': '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />',
    'chat': '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />',
    'mail': '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />',
    'tag': '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />',
    'trending-up': '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />',
};

function healthScoreColor(score) {
    if (score === null || score === undefined) return '';
    if (score < 40) return 'bg-red-500';
    if (score < 60) return 'bg-amber-500';
    if (score < 80) return 'bg-yellow-500';
    return 'bg-emerald-500';
}

function resolveRoute(routeName) {
    try {
        return route(routeName);
    } catch {
        return '#';
    }
}
</script>

<template>
    <div>
        <!-- Mobile toggle -->
        <button
            @click="mobileOpen = !mobileOpen"
            class="lg:hidden flex items-center gap-2 px-3 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg mb-3 w-full"
        >
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
            </svg>
            <span>Analytics Navigation</span>
            <svg
                class="w-4 h-4 ml-auto transition-transform"
                :class="{ 'rotate-180': mobileOpen }"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
            >
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
        </button>

        <!-- Navigation list -->
        <nav
            class="space-y-1"
            :class="{ 'hidden lg:block': !mobileOpen }"
        >
            <Link
                v-for="item in navItems"
                :key="item.id"
                :href="resolveRoute(item.route)"
                class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors"
                :class="currentPage === item.id
                    ? 'bg-indigo-600 text-white'
                    : 'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700/50'"
            >
                <svg
                    class="w-5 h-5 flex-shrink-0"
                    :class="currentPage === item.id ? 'text-white' : 'text-gray-400 dark:text-gray-500'"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                    v-html="icons[item.icon]"
                />
                <span class="flex-1 truncate">{{ item.label }}</span>

                <!-- Live indicator -->
                <span
                    v-if="item.live"
                    class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"
                    aria-hidden="true"
                ></span>

                <!-- Health score badge for Executive Overview -->
                <span
                    v-if="item.id === 'executive' && healthScore !== null"
                    class="w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold text-white"
                    :class="healthScoreColor(healthScore)"
                >
                    {{ healthScore }}
                </span>
            </Link>
        </nav>
    </div>
</template>
