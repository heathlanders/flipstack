<script setup>
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    campaigns: Object,
    credentials: {
        type: Array,
        default: () => [],
    },
});

const showCreateForm = ref(false);
const form = ref({
    name: '',
    campaign_type: 'standard',
    integration_credential_id: '',
    budget_daily: '',
    budget_total: '',
    start_date: '',
    end_date: '',
});
const submitting = ref(false);

const formatCurrency = (value) => {
    if (!value) return '--';
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD',
    }).format(value);
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

const createCampaign = () => {
    submitting.value = true;
    router.post(route('marketplaces.ebay.promoted.store'), form.value, {
        onSuccess: () => {
            showCreateForm.value = false;
            form.value = {
                name: '',
                campaign_type: 'standard',
                integration_credential_id: '',
                budget_daily: '',
                budget_total: '',
                start_date: '',
                end_date: '',
            };
        },
        onFinish: () => {
            submitting.value = false;
        },
    });
};

const pauseCampaign = (campaign) => {
    router.post(route('marketplaces.ebay.promoted.pause', campaign.id));
};

const resumeCampaign = (campaign) => {
    router.post(route('marketplaces.ebay.promoted.resume', campaign.id));
};

const endCampaign = (campaign) => {
    if (confirm('Are you sure you want to end this campaign? This cannot be undone.')) {
        router.post(route('marketplaces.ebay.promoted.end', campaign.id));
    }
};
</script>

<template>
    <AppLayout title="Promoted Listings Campaigns">
        <Head title="Promoted Listings Campaigns" />

        <div class="py-6">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex items-center justify-between mb-6">
                    <div class="flex items-center space-x-4">
                        <Link
                            :href="route('marketplaces.ebay.promoted.dashboard')"
                            class="text-sm text-indigo-600 dark:text-indigo-400 hover:underline"
                        >
                            Back to Dashboard
                        </Link>
                        <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">
                            Campaigns
                        </h1>
                    </div>
                    <button
                        type="button"
                        class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150"
                        @click="showCreateForm = !showCreateForm"
                    >
                        {{ showCreateForm ? 'Cancel' : 'Create Campaign' }}
                    </button>
                </div>

                <!-- Create Campaign Form -->
                <div
                    v-if="showCreateForm"
                    class="bg-white dark:bg-gray-800 shadow rounded-lg mb-6 p-6"
                >
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">
                        Create New Campaign
                    </h3>
                    <form @submit.prevent="createCampaign" class="space-y-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                    Campaign Name
                                </label>
                                <input
                                    v-model="form.name"
                                    type="text"
                                    required
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white sm:text-sm"
                                    placeholder="Enter campaign name"
                                />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                    Campaign Type
                                </label>
                                <select
                                    v-model="form.campaign_type"
                                    required
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white sm:text-sm"
                                >
                                    <option value="standard">Standard</option>
                                    <option value="advanced">Advanced</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                    eBay Credential
                                </label>
                                <select
                                    v-model="form.integration_credential_id"
                                    required
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white sm:text-sm"
                                >
                                    <option value="">Select credential...</option>
                                    <option
                                        v-for="cred in credentials"
                                        :key="cred.id"
                                        :value="cred.id"
                                    >
                                        {{ cred.name }}
                                    </option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                    Daily Budget
                                </label>
                                <input
                                    v-model="form.budget_daily"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white sm:text-sm"
                                    placeholder="0.00"
                                />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                    Total Budget
                                </label>
                                <input
                                    v-model="form.budget_total"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white sm:text-sm"
                                    placeholder="0.00"
                                />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                    Start Date
                                </label>
                                <input
                                    v-model="form.start_date"
                                    type="date"
                                    required
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white sm:text-sm"
                                />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                    End Date
                                </label>
                                <input
                                    v-model="form.end_date"
                                    type="date"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white sm:text-sm"
                                />
                            </div>
                        </div>
                        <div class="flex justify-end">
                            <button
                                type="submit"
                                :disabled="submitting"
                                class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150 disabled:opacity-50"
                            >
                                {{ submitting ? 'Creating...' : 'Create Campaign' }}
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Campaigns Table -->
                <div class="bg-white dark:bg-gray-800 shadow rounded-lg">
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
                                        Budget (Daily)
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        Budget (Total)
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        Start Date
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        End Date
                                    </th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        Actions
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                <tr
                                    v-for="campaign in campaigns.data"
                                    :key="campaign.id"
                                    class="hover:bg-gray-50 dark:hover:bg-gray-700"
                                >
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <Link
                                            :href="route('marketplaces.ebay.promoted.show', campaign.id)"
                                            class="text-sm font-medium text-indigo-600 dark:text-indigo-400 hover:underline"
                                        >
                                            {{ campaign.name }}
                                        </Link>
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
                                        {{ formatCurrency(campaign.budget_daily) }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                        {{ formatCurrency(campaign.budget_total) }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                        {{ formatDate(campaign.start_date) }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                        {{ formatDate(campaign.end_date) }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium space-x-2">
                                        <button
                                            v-if="campaign.status === 'active'"
                                            type="button"
                                            class="text-yellow-600 dark:text-yellow-400 hover:text-yellow-900 dark:hover:text-yellow-300"
                                            @click="pauseCampaign(campaign)"
                                        >
                                            Pause
                                        </button>
                                        <button
                                            v-if="campaign.status === 'paused'"
                                            type="button"
                                            class="text-green-600 dark:text-green-400 hover:text-green-900 dark:hover:text-green-300"
                                            @click="resumeCampaign(campaign)"
                                        >
                                            Resume
                                        </button>
                                        <button
                                            v-if="campaign.status !== 'ended'"
                                            type="button"
                                            class="text-red-600 dark:text-red-400 hover:text-red-900 dark:hover:text-red-300"
                                            @click="endCampaign(campaign)"
                                        >
                                            End
                                        </button>
                                    </td>
                                </tr>
                                <tr v-if="!campaigns.data || campaigns.data.length === 0">
                                    <td colspan="8" class="px-6 py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                                        No campaigns found. Click "Create Campaign" to get started.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div
                        v-if="campaigns.links && campaigns.links.length > 3"
                        class="px-4 py-3 border-t border-gray-200 dark:border-gray-700 sm:px-6"
                    >
                        <nav class="flex items-center justify-between">
                            <div class="flex-1 flex justify-between sm:hidden">
                                <Link
                                    v-if="campaigns.prev_page_url"
                                    :href="campaigns.prev_page_url"
                                    class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 dark:bg-gray-800 dark:border-gray-600 dark:text-gray-300"
                                >
                                    Previous
                                </Link>
                                <Link
                                    v-if="campaigns.next_page_url"
                                    :href="campaigns.next_page_url"
                                    class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 dark:bg-gray-800 dark:border-gray-600 dark:text-gray-300"
                                >
                                    Next
                                </Link>
                            </div>
                            <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                                <div>
                                    <p class="text-sm text-gray-700 dark:text-gray-300">
                                        Showing {{ campaigns.from || 0 }} to {{ campaigns.to || 0 }} of {{ campaigns.total || 0 }} results
                                    </p>
                                </div>
                                <div>
                                    <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px">
                                        <Link
                                            v-for="link in campaigns.links"
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
                        </nav>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
