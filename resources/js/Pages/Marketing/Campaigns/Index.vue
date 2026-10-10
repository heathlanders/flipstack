<script setup>
import { ref } from 'vue'
import { router, Head, Link } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

const props = defineProps({
    campaigns: {
        type: Array,
        default: () => []
    },
    stats: {
        type: Object,
        default: () => ({})
    }
})

const deletingId = ref(null)

function toggleCampaign(campaign) {
    router.post(route('marketing.campaigns.toggle', campaign.id), {}, {
        preserveScroll: true
    })
}

function confirmDelete(campaign) {
    deletingId.value = campaign.id
}

function cancelDelete() {
    deletingId.value = null
}

function deleteCampaign(campaign) {
    router.delete(route('marketing.campaigns.destroy', campaign.id), {
        preserveScroll: true,
        onSuccess: () => {
            deletingId.value = null
        }
    })
}

function typeBadgeClass(type) {
    const classes = {
        post_purchase_followup: 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
        review_request: 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200',
        restock_alert: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
        win_back: 'bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-200',
    }
    return classes[type] || 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200'
}
</script>

<template>
    <AppLayout title="Email Campaigns">
        <Head title="Email Campaigns" />

        <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
            <!-- Header -->
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Email Campaigns</h1>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Automated email marketing for your customers</p>
                </div>
                <Link
                    :href="route('marketing.campaigns.create')"
                    class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition"
                >
                    Create Campaign
                </Link>
            </div>

            <!-- Stats Cards -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-5">
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Campaigns</p>
                    <p class="mt-1 text-2xl font-bold text-gray-900 dark:text-white">{{ stats.total_campaigns ?? 0 }}</p>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-5">
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Active</p>
                    <p class="mt-1 text-2xl font-bold text-green-600 dark:text-green-400">{{ stats.active_campaigns ?? 0 }}</p>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-5">
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Sent</p>
                    <p class="mt-1 text-2xl font-bold text-gray-900 dark:text-white">{{ stats.total_sent ?? 0 }}</p>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-5">
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Avg Open Rate</p>
                    <p class="mt-1 text-2xl font-bold text-indigo-600 dark:text-indigo-400">{{ stats.avg_open_rate ?? 0 }}%</p>
                </div>
            </div>

            <!-- Campaigns Table -->
            <div class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden">
                <table v-if="campaigns.length > 0" class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-700">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Name</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Type</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Trigger</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Sent</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Open Rate</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Click Rate</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                        <tr v-for="campaign in campaigns" :key="campaign.id" class="hover:bg-gray-50 dark:hover:bg-gray-750">
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">
                                {{ campaign.name }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span
                                    :class="typeBadgeClass(campaign.type)"
                                    class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                                >
                                    {{ campaign.type_label }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                {{ campaign.trigger_label }}
                                <span class="text-xs text-gray-400 dark:text-gray-500">({{ campaign.trigger_delay_hours }}h delay)</span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span
                                    :class="campaign.is_active
                                        ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200'
                                        : 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300'"
                                    class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                                >
                                    {{ campaign.is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                {{ campaign.sent_count }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                {{ campaign.open_rate }}%
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                {{ campaign.click_rate }}%
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium space-x-2">
                                <Link
                                    :href="route('marketing.campaigns.edit', campaign.id)"
                                    class="text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-300"
                                >
                                    Edit
                                </Link>
                                <button
                                    @click="toggleCampaign(campaign)"
                                    :class="campaign.is_active
                                        ? 'text-amber-600 hover:text-amber-900 dark:text-amber-400'
                                        : 'text-green-600 hover:text-green-900 dark:text-green-400'"
                                >
                                    {{ campaign.is_active ? 'Deactivate' : 'Activate' }}
                                </button>
                                <Link
                                    :href="route('marketing.campaigns.stats', campaign.id)"
                                    class="text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-300"
                                >
                                    Stats
                                </Link>
                                <button
                                    v-if="deletingId !== campaign.id"
                                    @click="confirmDelete(campaign)"
                                    class="text-red-600 hover:text-red-900 dark:text-red-400"
                                >
                                    Delete
                                </button>
                                <template v-else>
                                    <button
                                        @click="deleteCampaign(campaign)"
                                        class="text-red-700 font-bold hover:text-red-900 dark:text-red-300"
                                    >
                                        Confirm
                                    </button>
                                    <button
                                        @click="cancelDelete"
                                        class="text-gray-500 hover:text-gray-700 dark:text-gray-400"
                                    >
                                        Cancel
                                    </button>
                                </template>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <div v-else class="p-12 text-center">
                    <p class="text-gray-500 dark:text-gray-400 text-sm">No campaigns yet. Create your first email campaign to get started.</p>
                    <Link
                        :href="route('marketing.campaigns.create')"
                        class="mt-4 inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 transition"
                    >
                        Create Campaign
                    </Link>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
