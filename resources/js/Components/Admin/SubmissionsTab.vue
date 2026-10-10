<script setup>
import { onMounted } from 'vue';
import { router } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import { useSupplierTabFeed } from './useSupplierTabFeed';

const props = defineProps({ supplierId: { type: String, required: true } });

const { loading, page, result, load, goTo } = useSupplierTabFeed(
    'admin.suppliers.submissions',
    props.supplierId
);

const formatDate = (d) => (d ? new Date(d).toLocaleDateString('en-US') : '—');

const statusColors = {
    pending_review: 'text-amber-600',
    approved: 'text-green-600',
    rejected: 'text-red-600',
};

function openSubmission(id) {
    router.get(route('admin.supplier-submissions.review', id));
}

onMounted(load);
</script>

<template>
    <div>
        <div class="flex justify-between items-center mb-3">
            <h3 class="text-sm font-medium text-gray-700 dark:text-gray-300">Product Submissions</h3>
            <div class="text-xs text-gray-500" v-if="result.meta">{{ result.meta.total }} total</div>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-slate-700">
                    <tr>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Submitted</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Title</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Reviewed</th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-slate-800 divide-y divide-gray-200 dark:divide-gray-700">
                    <tr v-if="loading"><td colspan="4" class="px-4 py-6 text-center text-gray-500">Loading...</td></tr>
                    <tr v-else-if="!result.data.length"><td colspan="4" class="px-4 py-6 text-center text-gray-500">No submissions</td></tr>
                    <tr v-for="s in result.data" :key="s.id" @click="openSubmission(s.id)" class="hover:bg-gray-50 dark:hover:bg-slate-700 cursor-pointer">
                        <td class="px-4 py-2 text-sm text-gray-500">{{ formatDate(s.created_at) }}</td>
                        <td class="px-4 py-2 text-sm text-gray-900 dark:text-white">{{ s.submitted_data?.title || s.submitted_data?.name || '(no title)' }}</td>
                        <td class="px-4 py-2 text-sm capitalize" :class="statusColors[s.status] || 'text-gray-500'">{{ s.status.replace('_', ' ') }}</td>
                        <td class="px-4 py-2 text-sm text-gray-500">{{ formatDate(s.reviewed_at) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div v-if="result.meta && result.meta.last_page > 1" class="flex justify-end gap-2 mt-3">
            <button @click="goTo(page - 1)" :disabled="page <= 1" class="px-3 py-1 text-sm rounded border border-gray-300 disabled:opacity-50">Prev</button>
            <span class="text-xs text-gray-500">Page {{ result.meta.current_page }} of {{ result.meta.last_page }}</span>
            <button @click="goTo(page + 1)" :disabled="page >= result.meta.last_page" class="px-3 py-1 text-sm rounded border border-gray-300 disabled:opacity-50">Next</button>
        </div>
    </div>
</template>
