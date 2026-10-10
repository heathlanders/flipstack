<script setup>
import { Head, Link } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

const props = defineProps({
    campaign: {
        type: Object,
        required: true
    },
    stats: {
        type: Object,
        default: () => ({})
    },
    recentSends: {
        type: Array,
        default: () => []
    }
})

function statusBadgeClass(status) {
    const classes = {
        pending: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
        sent: 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
        delivered: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
        opened: 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900 dark:text-indigo-200',
        clicked: 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200',
        bounced: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
        unsubscribed: 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300',
    }
    return classes[status] || 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300'
}
</script>

<template>
    <AppLayout title="Campaign Stats">
        <Head :title="`Stats - ${campaign.name}`" />

        <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
            <!-- Header -->
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ campaign.name }}</h1>
                    <div class="mt-1 flex items-center space-x-3">
                        <span class="text-sm text-gray-500 dark:text-gray-400">{{ campaign.type_label }}</span>
                        <span
                            :class="campaign.is_active
                                ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200'
                                : 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300'"
                            class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                        >
                            {{ campaign.is_active ? 'Active' : 'Inactive' }}
                        </span>
                    </div>
                </div>
                <Link
                    :href="route('marketing.campaigns.index')"
                    class="text-sm text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-200"
                >
                    Back to Campaigns
                </Link>
            </div>

            <!-- KPI Cards -->
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4 mb-6">
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Sent</p>
                    <p class="mt-1 text-xl font-bold text-gray-900 dark:text-white">{{ stats.total_sent ?? 0 }}</p>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Delivered</p>
                    <p class="mt-1 text-xl font-bold text-green-600 dark:text-green-400">{{ stats.delivered ?? 0 }}</p>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Opened</p>
                    <p class="mt-1 text-xl font-bold text-indigo-600 dark:text-indigo-400">{{ stats.opened ?? 0 }}</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ stats.open_rate ?? 0 }}% rate</p>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Clicked</p>
                    <p class="mt-1 text-xl font-bold text-purple-600 dark:text-purple-400">{{ stats.clicked ?? 0 }}</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ stats.click_rate ?? 0 }}% rate</p>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Bounced</p>
                    <p class="mt-1 text-xl font-bold text-red-600 dark:text-red-400">{{ stats.bounced ?? 0 }}</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ stats.bounce_rate ?? 0 }}% rate</p>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Unsubscribed</p>
                    <p class="mt-1 text-xl font-bold text-gray-600 dark:text-gray-400">{{ stats.unsubscribed ?? 0 }}</p>
                </div>
            </div>

            <!-- Recent Sends Table -->
            <div class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Recent Sends</h2>
                </div>
                <table v-if="recentSends.length > 0" class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-700">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Customer</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Order</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Sent At</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Opened At</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Clicked At</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                        <tr v-for="send in recentSends" :key="send.id" class="hover:bg-gray-50 dark:hover:bg-gray-750">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900 dark:text-white">{{ send.customer_name }}</div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">{{ send.customer_email }}</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                {{ send.order_number }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span
                                    :class="statusBadgeClass(send.status)"
                                    class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium capitalize"
                                >
                                    {{ send.status }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                {{ send.sent_at ?? '-' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                {{ send.opened_at ?? '-' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                {{ send.clicked_at ?? '-' }}
                            </td>
                        </tr>
                    </tbody>
                </table>
                <div v-else class="p-12 text-center">
                    <p class="text-gray-500 dark:text-gray-400 text-sm">No sends recorded yet for this campaign.</p>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
