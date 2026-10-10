<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
const props = defineProps({ jobs: Object });
</script>
<template>
<AppLayout title="Bulk Operation Jobs">
    <Head title="Bulk Operation Jobs" />
    <template #header><h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200">Bulk Operation History</h2></template>
    <div class="py-12"><div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white dark:bg-gray-800 shadow-xl sm:rounded-lg">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-700/50"><tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">ID</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Operation</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Progress</th>
                </tr></thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    <tr v-for="job in jobs.data" :key="job.id">
                        <td class="px-6 py-4 text-sm text-gray-500">#{{ job.id }}</td>
                        <td class="px-6 py-4 text-sm text-gray-900 dark:text-white">{{ job.operation_type_label }}</td>
                        <td class="px-6 py-4"><span class="px-2 py-1 text-xs font-medium rounded-full" :class="{'text-green-600 bg-green-100': job.status === 'completed', 'text-red-600 bg-red-100': job.status === 'failed', 'text-blue-600 bg-blue-100': job.status === 'processing'}">{{ job.status_label }}</span></td>
                        <td class="px-6 py-4 text-sm text-gray-600">{{ job.processed_items }}/{{ job.total_items }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div></div>
</AppLayout>
</template>
