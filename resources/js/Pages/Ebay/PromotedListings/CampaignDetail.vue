<script setup>
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import axios from 'axios';

const props = defineProps({
    campaign: Object,
    listings: Object,
    availableListings: {
        type: Array,
        default: () => [],
    },
});

const addForm = ref({
    ebay_listing_id: '',
    ad_rate_percentage: 2.0,
});
const addingListing = ref(false);
const syncingStats = ref(false);
const syncMessage = ref(null);

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
        removed: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
        ended: 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300',
    };
    return map[status] || map.ended;
};

const addListing = () => {
    addingListing.value = true;
    router.post(
        route('marketplaces.ebay.promoted.add-listing', props.campaign.id),
        addForm.value,
        {
            onSuccess: () => {
                addForm.value = {
                    ebay_listing_id: '',
                    ad_rate_percentage: 2.0,
                };
            },
            onFinish: () => {
                addingListing.value = false;
            },
        }
    );
};

const removeListing = (listing) => {
    if (confirm('Remove this listing from the campaign?')) {
        router.post(route('marketplaces.ebay.promoted.remove-listing', listing.id));
    }
};

const syncStats = async () => {
    syncingStats.value = true;
    syncMessage.value = null;

    try {
        const response = await axios.post(
            route('marketplaces.ebay.promoted.sync-stats', props.campaign.id)
        );
        syncMessage.value = {
            type: 'success',
            text: response.data.message,
        };
    } catch (error) {
        syncMessage.value = {
            type: 'error',
            text: error.response?.data?.message || 'Failed to dispatch sync job.',
        };
    } finally {
        syncingStats.value = false;
    }
};
</script>

<template>
    <AppLayout title="Campaign Detail">
        <Head :title="`Campaign: ${campaign.name}`" />

        <div class="py-6">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <!-- Breadcrumb -->
                <div class="flex items-center space-x-2 text-sm mb-6">
                    <Link
                        :href="route('marketplaces.ebay.promoted.dashboard')"
                        class="text-indigo-600 dark:text-indigo-400 hover:underline"
                    >
                        Dashboard
                    </Link>
                    <span class="text-gray-400">/</span>
                    <Link
                        :href="route('marketplaces.ebay.promoted.campaigns')"
                        class="text-indigo-600 dark:text-indigo-400 hover:underline"
                    >
                        Campaigns
                    </Link>
                    <span class="text-gray-400">/</span>
                    <span class="text-gray-500 dark:text-gray-400">{{ campaign.name }}</span>
                </div>

                <!-- Campaign Info Card -->
                <div class="bg-white dark:bg-gray-800 shadow rounded-lg mb-6 p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">
                            {{ campaign.name }}
                        </h1>
                        <div class="flex items-center space-x-3">
                            <span
                                :class="statusClasses(campaign.status)"
                                class="px-3 py-1 text-sm font-semibold rounded-full capitalize"
                            >
                                {{ campaign.status }}
                            </span>
                            <button
                                type="button"
                                class="inline-flex items-center px-3 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-600 transition"
                                :disabled="syncingStats"
                                @click="syncStats"
                            >
                                {{ syncingStats ? 'Syncing...' : 'Sync Stats' }}
                            </button>
                        </div>
                    </div>

                    <!-- Sync message -->
                    <div
                        v-if="syncMessage"
                        :class="syncMessage.type === 'success' ? 'bg-green-50 text-green-800 dark:bg-green-900 dark:text-green-200' : 'bg-red-50 text-red-800 dark:bg-red-900 dark:text-red-200'"
                        class="rounded-md p-3 mb-4 text-sm"
                    >
                        {{ syncMessage.text }}
                    </div>

                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                        <div>
                            <dt class="text-gray-500 dark:text-gray-400">Type</dt>
                            <dd class="mt-1 font-medium text-gray-900 dark:text-white capitalize">
                                {{ campaign.campaign_type }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-gray-500 dark:text-gray-400">Daily Budget</dt>
                            <dd class="mt-1 font-medium text-gray-900 dark:text-white">
                                {{ campaign.budget_daily ? formatCurrency(campaign.budget_daily) : '--' }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-gray-500 dark:text-gray-400">Total Budget</dt>
                            <dd class="mt-1 font-medium text-gray-900 dark:text-white">
                                {{ campaign.budget_total ? formatCurrency(campaign.budget_total) : '--' }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-gray-500 dark:text-gray-400">Dates</dt>
                            <dd class="mt-1 font-medium text-gray-900 dark:text-white">
                                {{ formatDate(campaign.start_date) }} - {{ formatDate(campaign.end_date) }}
                            </dd>
                        </div>
                    </div>
                </div>

                <!-- Add Listing Form -->
                <div
                    v-if="campaign.status !== 'ended'"
                    class="bg-white dark:bg-gray-800 shadow rounded-lg mb-6 p-6"
                >
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">
                        Add Listing to Campaign
                    </h3>
                    <form @submit.prevent="addListing" class="flex items-end space-x-4">
                        <div class="flex-1">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                eBay Listing
                            </label>
                            <select
                                v-model="addForm.ebay_listing_id"
                                required
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white sm:text-sm"
                            >
                                <option value="">Select a listing...</option>
                                <option
                                    v-for="listing in availableListings"
                                    :key="listing.id"
                                    :value="listing.id"
                                >
                                    {{ listing.ebay_sku || listing.ebay_listing_id }} - {{ formatCurrency(listing.listed_price) }}
                                </option>
                            </select>
                        </div>
                        <div class="w-40">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Ad Rate (%)
                            </label>
                            <input
                                v-model="addForm.ad_rate_percentage"
                                type="number"
                                step="0.01"
                                min="0"
                                max="100"
                                required
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white sm:text-sm"
                            />
                        </div>
                        <button
                            type="submit"
                            :disabled="addingListing"
                            class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150 disabled:opacity-50"
                        >
                            {{ addingListing ? 'Adding...' : 'Add Listing' }}
                        </button>
                    </form>
                </div>

                <!-- Listings Table -->
                <div class="bg-white dark:bg-gray-800 shadow rounded-lg">
                    <div class="px-4 py-5 sm:px-6 border-b border-gray-200 dark:border-gray-700">
                        <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white">
                            Campaign Listings
                        </h3>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-900">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        Product
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        eBay Listing
                                    </th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        Ad Rate
                                    </th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        Impressions
                                    </th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        Clicks
                                    </th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        Sales
                                    </th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        Ad Fees
                                    </th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        ROAS
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        Status
                                    </th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        Actions
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                <tr
                                    v-for="listing in listings.data"
                                    :key="listing.id"
                                    class="hover:bg-gray-50 dark:hover:bg-gray-700"
                                >
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                        {{ listing.product?.name || listing.product?.sku || '--' }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                        {{ listing.ebay_listing?.ebay_listing_id || listing.ebay_listing?.ebay_sku || '--' }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-right text-gray-900 dark:text-white">
                                        {{ listing.ad_rate_percentage }}%
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-right text-gray-500 dark:text-gray-400">
                                        {{ listing.impressions.toLocaleString() }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-right text-gray-500 dark:text-gray-400">
                                        {{ listing.clicks.toLocaleString() }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-right text-gray-500 dark:text-gray-400">
                                        {{ listing.sales_count }} ({{ formatCurrency(listing.sales_amount) }})
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-right text-gray-500 dark:text-gray-400">
                                        {{ formatCurrency(listing.ad_fees) }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-right text-gray-900 dark:text-white">
                                        {{ listing.roas }}x
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span
                                            :class="statusClasses(listing.status)"
                                            class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full capitalize"
                                        >
                                            {{ listing.status }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        <button
                                            v-if="listing.status !== 'removed'"
                                            type="button"
                                            class="text-red-600 dark:text-red-400 hover:text-red-900 dark:hover:text-red-300"
                                            @click="removeListing(listing)"
                                        >
                                            Remove
                                        </button>
                                    </td>
                                </tr>
                                <tr v-if="!listings.data || listings.data.length === 0">
                                    <td colspan="10" class="px-6 py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                                        No listings in this campaign. Use the form above to add listings.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div
                        v-if="listings.links && listings.links.length > 3"
                        class="px-4 py-3 border-t border-gray-200 dark:border-gray-700 sm:px-6"
                    >
                        <div class="flex items-center justify-between">
                            <p class="text-sm text-gray-700 dark:text-gray-300">
                                Showing {{ listings.from || 0 }} to {{ listings.to || 0 }} of {{ listings.total || 0 }} results
                            </p>
                            <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px">
                                <Link
                                    v-for="link in listings.links"
                                    :key="link.label"
                                    :href="link.url || '#'"
                                    :class="[
                                        link.active
                                            ? 'z-10 bg-indigo-50 border-indigo-500 text-indigo-600 dark:bg-indigo-900 dark:border-indigo-400 dark:text-indigo-300'
                                            : 'bg-white border-gray-300 text-gray-500 hover:bg-gray-50 dark:bg-gray-800 dark:border-gray-600 dark:text-gray-400',
                                        'relative inline-flex items-center px-4 py-2 border text-sm font-medium',
                                    ]"
                                    v-html="link.label"
                                />
                            </nav>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
