<script setup>
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    submissions: Object,
    stats: Object,
    filters: Object,
});

const statusTabs = [
    { key: '', label: 'All' },
    { key: 'pending_review', label: 'Pending' },
    { key: 'approved', label: 'Approved' },
    { key: 'rejected', label: 'Rejected' },
];

const activeStatus = ref(props.filters?.status || '');

const filterByStatus = (status) => {
    activeStatus.value = status;
    router.get(route('admin.supplier-submissions.index'), { status: status || undefined }, {
        preserveState: true,
        preserveScroll: true,
    });
};

const statusBadgeClass = (status) => {
    switch (status) {
        case 'pending_review':
            return 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200';
        case 'approved':
            return 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200';
        case 'rejected':
            return 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200';
        default:
            return 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300';
    }
};

const statusLabel = (status) => {
    switch (status) {
        case 'pending_review': return 'Pending Review';
        case 'approved': return 'Approved';
        case 'rejected': return 'Rejected';
        default: return status;
    }
};

const formatDate = (dateString) => {
    if (!dateString) return '--';
    return new Date(dateString).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const formatCurrency = (value) => {
    if (value === null || value === undefined) return '--';
    return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(value);
};
</script>

<template>
    <AppLayout title="Supplier Submissions">
        <Head title="Supplier Submissions" />

        <div class="max-w-7xl mx-auto py-8 px-4 sm:px-6 lg:px-8">
            <div class="mb-6">
                <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">Supplier Product Submissions</h1>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                    Review and manage products submitted by suppliers.
                </p>
            </div>

            <!-- Stats cards -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-5">
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Pending Review</p>
                    <p class="mt-1 text-3xl font-bold text-yellow-600 dark:text-yellow-400">{{ stats?.pending || 0 }}</p>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-5">
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Approved</p>
                    <p class="mt-1 text-3xl font-bold text-green-600 dark:text-green-400">{{ stats?.approved || 0 }}</p>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-5">
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Rejected</p>
                    <p class="mt-1 text-3xl font-bold text-red-600 dark:text-red-400">{{ stats?.rejected || 0 }}</p>
                </div>
            </div>

            <!-- Status filter tabs -->
            <div class="mb-6 border-b border-gray-200 dark:border-gray-700">
                <nav class="-mb-px flex space-x-6">
                    <button
                        v-for="tab in statusTabs"
                        :key="tab.key"
                        :class="[
                            'py-3 px-1 border-b-2 text-sm font-medium',
                            activeStatus === tab.key
                                ? 'border-blue-500 text-blue-600 dark:text-blue-400'
                                : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300',
                        ]"
                        @click="filterByStatus(tab.key)"
                    >
                        {{ tab.label }}
                    </button>
                </nav>
            </div>

            <!-- Submissions table -->
            <div class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-900">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Supplier</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Product Name</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">SKU</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Cost Price</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Submitted At</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        <tr v-for="submission in submissions.data" :key="submission.id" class="hover:bg-gray-50 dark:hover:bg-gray-750">
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                                {{ submission.supplier?.name || submission.supplier?.company_name || '--' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-gray-100">
                                {{ submission.submitted_data?.name || '--' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                {{ submission.submitted_data?.sku || '--' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                {{ formatCurrency(submission.submitted_data?.cost_price) }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span :class="['inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium', statusBadgeClass(submission.status)]">
                                    {{ statusLabel(submission.status) }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                {{ formatDate(submission.created_at) }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm">
                                <Link
                                    :href="route('admin.supplier-submissions.review', submission.id)"
                                    class="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300 font-medium"
                                >
                                    Review
                                </Link>
                            </td>
                        </tr>
                        <tr v-if="!submissions.data || submissions.data.length === 0">
                            <td colspan="7" class="px-6 py-12 text-center text-sm text-gray-500 dark:text-gray-400">
                                No submissions found.
                            </td>
                        </tr>
                    </tbody>
                </table>

                <!-- Pagination -->
                <div v-if="submissions.links && submissions.links.length > 3" class="px-6 py-3 bg-gray-50 dark:bg-gray-900 border-t border-gray-200 dark:border-gray-700 flex justify-between items-center">
                    <p class="text-sm text-gray-700 dark:text-gray-300">
                        Showing {{ submissions.from }} to {{ submissions.to }} of {{ submissions.total }} results
                    </p>
                    <div class="flex space-x-1">
                        <Link
                            v-for="link in submissions.links"
                            :key="link.label"
                            :href="link.url || '#'"
                            :class="[
                                'px-3 py-1 text-sm rounded',
                                link.active
                                    ? 'bg-blue-600 text-white'
                                    : link.url
                                        ? 'text-gray-600 dark:text-gray-400 hover:bg-gray-200 dark:hover:bg-gray-700'
                                        : 'text-gray-400 dark:text-gray-600 cursor-not-allowed',
                            ]"
                            v-html="link.label"
                        />
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
