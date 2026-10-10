<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    listings: Object,
    stats: Object,
});

const statusColors = {
    pending: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
    processing: 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
    published: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
    failed: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
    cancelled: 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300',
};

function formatDate(dateStr) {
    if (!dateStr) return '--';
    return new Date(dateStr).toLocaleString();
}

function cancelListing(id) {
    if (confirm('Are you sure you want to cancel this scheduled listing?')) {
        router.post(route('marketplaces.scheduled-listings.cancel', id));
    }
}

function deleteListing(id) {
    if (confirm('Are you sure you want to delete this scheduled listing?')) {
        router.delete(route('marketplaces.scheduled-listings.destroy', id));
    }
}
</script>

<template>
    <AppLayout title="Scheduled Listings">
        <Head title="Scheduled Listings" />

        <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
            <!-- Header -->
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">Scheduled Listings</h1>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Manage listings scheduled for future publishing across marketplaces.</p>
                </div>
                <Link
                    :href="route('marketplaces.scheduled-listings.create')"
                    class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 dark:bg-indigo-500 dark:hover:bg-indigo-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition"
                >
                    Schedule New Listing
                </Link>
            </div>

            <!-- Stats Cards -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-5">
                    <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Pending</div>
                    <div class="mt-1 text-3xl font-semibold text-yellow-600 dark:text-yellow-400">{{ stats?.pending ?? 0 }}</div>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-5">
                    <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Published</div>
                    <div class="mt-1 text-3xl font-semibold text-green-600 dark:text-green-400">{{ stats?.published ?? 0 }}</div>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-5">
                    <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Failed</div>
                    <div class="mt-1 text-3xl font-semibold text-red-600 dark:text-red-400">{{ stats?.failed ?? 0 }}</div>
                </div>
            </div>

            <!-- Table -->
            <div class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-700">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Product</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Marketplace</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Scheduled For</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Created By</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                        <tr v-for="listing in listings.data" :key="listing.id">
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                                {{ listing.product?.name ?? 'Unknown Product' }}
                                <div class="text-xs text-gray-500 dark:text-gray-400">{{ listing.product?.sku ?? '' }}</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100 capitalize">
                                {{ listing.marketplace }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                                {{ formatDate(listing.scheduled_at) }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span
                                    class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                                    :class="statusColors[listing.status] || 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300'"
                                >
                                    {{ listing.status }}
                                </span>
                                <div v-if="listing.error_message" class="mt-1 text-xs text-red-500 dark:text-red-400 truncate max-w-xs" :title="listing.error_message">
                                    {{ listing.error_message }}
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                                {{ listing.creator?.name ?? '--' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium space-x-2">
                                <button
                                    v-if="listing.status === 'pending'"
                                    @click="cancelListing(listing.id)"
                                    class="text-yellow-600 hover:text-yellow-900 dark:text-yellow-400 dark:hover:text-yellow-300"
                                >
                                    Cancel
                                </button>
                                <button
                                    @click="deleteListing(listing.id)"
                                    class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300"
                                >
                                    Delete
                                </button>
                            </td>
                        </tr>
                        <tr v-if="!listings.data || listings.data.length === 0">
                            <td colspan="6" class="px-6 py-12 text-center text-sm text-gray-500 dark:text-gray-400">
                                No scheduled listings found. Click "Schedule New Listing" to get started.
                            </td>
                        </tr>
                    </tbody>
                </table>

                <!-- Pagination -->
                <div v-if="listings.links && listings.links.length > 3" class="bg-white dark:bg-gray-800 px-4 py-3 border-t border-gray-200 dark:border-gray-700 sm:px-6">
                    <nav class="flex items-center justify-between">
                        <div class="text-sm text-gray-700 dark:text-gray-300">
                            Showing {{ listings.from }} to {{ listings.to }} of {{ listings.total }} results
                        </div>
                        <div class="flex space-x-1">
                            <Link
                                v-for="link in listings.links"
                                :key="link.label"
                                :href="link.url || '#'"
                                class="px-3 py-1 text-sm rounded"
                                :class="link.active
                                    ? 'bg-indigo-600 text-white dark:bg-indigo-500'
                                    : 'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700'"
                                v-html="link.label"
                            />
                        </div>
                    </nav>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
