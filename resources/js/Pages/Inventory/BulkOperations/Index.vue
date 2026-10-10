<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import BulkProgressTracker from '@/Components/Inventory/BulkProgressTracker.vue';

const props = defineProps({ recentJobs: Object, filterOptions: Object, permissions: Object });
const activeJobs = (props.recentJobs?.data || []).filter(j => ['pending', 'processing'].includes(j.status));
const completedJobs = (props.recentJobs?.data || []).filter(j => !['pending', 'processing'].includes(j.status));
</script>

<template>
<AppLayout title="Bulk Operations">
    <Head title="Bulk Operations" />
    <template #header>
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200">Bulk Operations</h2>
            <div class="flex gap-3">
                <Link v-if="permissions?.can_import" :href="route('inventory.import.index')" class="px-4 py-2 bg-white border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50">Import</Link>
                <Link v-if="permissions?.can_export" :href="route('inventory.export.index')" class="px-4 py-2 bg-white border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50">Export</Link>
            </div>
        </div>
    </template>
    <div class="py-12"><div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div v-if="activeJobs.length > 0" class="mb-8">
            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Active Operations</h3>
            <div class="grid gap-4 md:grid-cols-2">
                <BulkProgressTracker v-for="job in activeJobs" :key="job.id" :job-id="job.id" :initial-data="job" />
            </div>
        </div>
        <div class="bg-white dark:bg-gray-800 shadow-xl sm:rounded-lg p-6 mb-8">
            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">How to Use</h3>
            <ol class="list-decimal list-inside space-y-2 text-sm text-gray-600 dark:text-gray-400">
                <li>Go to Products page</li><li>Select products using checkboxes</li><li>Use the floating action bar</li><li>Preview and execute operations</li>
            </ol>
        </div>
        <div class="bg-white dark:bg-gray-800 shadow-xl sm:rounded-lg">
            <div class="p-6 border-b border-gray-200 dark:border-gray-700 flex justify-between">
                <h3 class="text-lg font-medium text-gray-900 dark:text-white">Recent Operations</h3>
                <Link :href="route('inventory.bulk.jobs')" class="text-sm text-indigo-600">View All</Link>
            </div>
            <div v-if="completedJobs.length > 0">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-700/50"><tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Operation</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Progress</th>
                    </tr></thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        <tr v-for="job in completedJobs" :key="job.id">
                            <td class="px-6 py-4 text-sm text-gray-900 dark:text-white">{{ job.operation_type_label }}</td>
                            <td class="px-6 py-4"><span class="px-2 py-1 text-xs font-medium rounded-full" :class="{'text-green-600 bg-green-100': job.status === 'completed', 'text-red-600 bg-red-100': job.status === 'failed'}">{{ job.status_label }}</span></td>
                            <td class="px-6 py-4 text-sm text-gray-600">{{ job.processed_items }}/{{ job.total_items }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div v-else class="p-6 text-center text-gray-500">No recent operations.</div>
        </div>
    </div></div>
</AppLayout>
</template>
