<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    stats: {
        type: Object,
        default: () => ({
            total_spend: 0,
            total_revenue: 0,
            overall_roas: 0,
            active_campaigns: 0,
        }),
    },
    recentCampaigns: {
        type: Array,
        default: () => [],
    },
});

const formatCurrency = (value) => {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD',
    }).format(value || 0);
};

const formatDate = (dateStr) => {
    if (!dateStr) return '--';
    return new Date(dateStr).toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    });
};

const statusClasses = (status) => {
    const map = {
        active: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
        paused: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
        ended: 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300',
    };
    return map[status] || map.ended;
};
</script>

<template>
    <AppLayout title="Promoted Listings Dashboard">
        <Head title="Promoted Listings Dashboard" />

        <div class="py-6">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex items-center justify-between mb-6">
                    <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">
                        Promoted Listings Dashboard
                    </h1>
                    <Link
                        :href="route('marketplaces.ebay.promoted.campaigns')"
                        class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150"
                    >
                        View All Campaigns
                    </Link>
                </div>

                <!-- KPI Cards -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow rounded-lg">
                        <div class="p-5">
                            <div class="flex items-center">
                                <div class="flex-1">
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">
                                        Total Ad Spend
                                    </dt>
                                    <dd class="mt-1 text-3xl font-semibold text-gray-900 dark:text-white">
                                        {{ formatCurrency(stats.total_spend) }}
                                    </dd>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow rounded-lg">
                        <div class="p-5">
                            <div class="flex items-center">
                                <div class="flex-1">
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">
                                        Total Ad Revenue
                                    </dt>
                                    <dd class="mt-1 text-3xl font-semibold text-gray-900 dark:text-white">
                                        {{ formatCurrency(stats.total_revenue) }}
                                    </dd>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow rounded-lg">
                        <div class="p-5">
                            <div class="flex items-center">
                                <div class="flex-1">
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">
                                        Overall ROAS
                                    </dt>
                                    <dd class="mt-1 text-3xl font-semibold text-gray-900 dark:text-white">
                                        {{ stats.overall_roas }}x
                                    </dd>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow rounded-lg">
                        <div class="p-5">
                            <div class="flex items-center">
                                <div class="flex-1">
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">
                                        Active Campaigns
                                    </dt>
                                    <dd class="mt-1 text-3xl font-semibold text-gray-900 dark:text-white">
                                        {{ stats.active_campaigns }}
                                    </dd>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Campaigns Table -->
                <div class="bg-white dark:bg-gray-800 shadow rounded-lg">
                    <div class="px-4 py-5 sm:px-6 border-b border-gray-200 dark:border-gray-700">
                        <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white">
                            Recent Campaigns
                        </h3>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-900">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        Name
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        Type
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        Status
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        Budget
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        Start Date
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        End Date
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                <tr
                                    v-for="campaign in recentCampaigns"
                                    :key="campaign.id"
                                    class="hover:bg-gray-50 dark:hover:bg-gray-700 cursor-pointer"
                                    @click="$inertia.visit(route('marketplaces.ebay.promoted.show', campaign.id))"
                                >
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">
                                        {{ campaign.name }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400 capitalize">
                                        {{ campaign.campaign_type }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span
                                            :class="statusClasses(campaign.status)"
                                            class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full capitalize"
                                        >
                                            {{ campaign.status }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                        <span v-if="campaign.budget_daily">{{ formatCurrency(campaign.budget_daily) }}/day</span>
                                        <span v-else-if="campaign.budget_total">{{ formatCurrency(campaign.budget_total) }} total</span>
                                        <span v-else>--</span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                        {{ formatDate(campaign.start_date) }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                        {{ formatDate(campaign.end_date) }}
                                    </td>
                                </tr>
                                <tr v-if="recentCampaigns.length === 0">
                                    <td colspan="6" class="px-6 py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                                        No campaigns found. Create your first promoted listings campaign.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
