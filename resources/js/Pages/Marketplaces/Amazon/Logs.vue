<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

defineProps({
    logs: Object,
    filters: Object,
});

const statusClass = (status) => {
    if (status === 'success') return 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-400';
    if (status === 'failed' || status === 'error') return 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400';
    return 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400';
};
</script>

<template>
    <AppLayout title="Amazon Sync Logs">
        <Head title="Amazon Sync Logs" />

        <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between mb-6">
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Amazon Sync Logs</h1>
                <Link :href="route('marketplaces.amazon.index')" class="text-sm text-yellow-600 hover:text-yellow-700">
                    &larr; Back to Amazon
                </Link>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-900/50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Time</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Operation</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Endpoint</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Error</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        <tr v-for="log in logs.data" :key="log.id">
                            <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">{{ log.created_at }}</td>
                            <td class="px-6 py-4 text-sm text-gray-900 dark:text-white">{{ log.operation }}</td>
                            <td class="px-6 py-4">
                                <span :class="['inline-flex px-2 py-0.5 rounded text-xs font-medium', statusClass(log.status)]">
                                    {{ log.status }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400 truncate max-w-xs">{{ log.endpoint }}</td>
                            <td class="px-6 py-4 text-sm text-red-600 dark:text-red-400 truncate max-w-xs">{{ log.error_message }}</td>
                        </tr>
                        <tr v-if="!logs.data?.length">
                            <td colspan="5" class="px-6 py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                                No sync activity
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>
