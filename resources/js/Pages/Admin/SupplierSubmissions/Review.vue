<script setup>
import { ref } from 'vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    submission: Object,
});

const showRejectForm = ref(false);

const rejectForm = useForm({
    review_notes: '',
});

const approveSubmission = () => {
    if (confirm('Are you sure you want to approve this submission? It will be added to the product intake queue.')) {
        router.post(route('admin.supplier-submissions.approve', props.submission.id));
    }
};

const rejectSubmission = () => {
    rejectForm.post(route('admin.supplier-submissions.reject', props.submission.id));
};

const formatCurrency = (value) => {
    if (value === null || value === undefined) return '--';
    return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(value);
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
</script>

<template>
    <AppLayout title="Review Submission">
        <Head title="Review Submission" />

        <div class="max-w-4xl mx-auto py-8 px-4 sm:px-6 lg:px-8">
            <!-- Back link -->
            <div class="mb-6">
                <Link
                    :href="route('admin.supplier-submissions.index')"
                    class="text-sm text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300"
                >
                    &larr; Back to Submissions
                </Link>
            </div>

            <div class="flex items-center justify-between mb-6">
                <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">Review Product Submission</h1>
                <span :class="['inline-flex items-center px-3 py-1 rounded-full text-sm font-medium', statusBadgeClass(submission.status)]">
                    {{ statusLabel(submission.status) }}
                </span>
            </div>

            <!-- Supplier Info -->
            <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6 mb-6">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">Supplier Information</h2>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Supplier Name</p>
                        <p class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ submission.supplier?.name || '--' }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Company</p>
                        <p class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ submission.supplier?.company_name || '--' }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Email</p>
                        <p class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ submission.supplier?.email || '--' }}</p>
                    </div>
                </div>
            </div>

            <!-- Submitted Product Data -->
            <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6 mb-6">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">Product Details</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Product Name</p>
                        <p class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ submission.submitted_data?.name || '--' }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">SKU</p>
                        <p class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ submission.submitted_data?.sku || '--' }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Barcode (UPC/EAN)</p>
                        <p class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ submission.submitted_data?.barcode || '--' }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Cost Price</p>
                        <p class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ formatCurrency(submission.submitted_data?.cost_price) }}</p>
                    </div>
                    <div class="md:col-span-2">
                        <p class="text-sm text-gray-500 dark:text-gray-400">Description</p>
                        <p class="text-sm text-gray-900 dark:text-gray-100 whitespace-pre-wrap">{{ submission.submitted_data?.description || '--' }}</p>
                    </div>
                </div>

                <!-- Images -->
                <div v-if="submission.submitted_data?.images && submission.submitted_data.images.length > 0" class="mt-6">
                    <p class="text-sm text-gray-500 dark:text-gray-400 mb-3">Product Images</p>
                    <div class="grid grid-cols-2 md:grid-cols-5 gap-3">
                        <div v-for="(image, index) in submission.submitted_data.images" :key="index">
                            <img
                                :src="'/storage/' + image"
                                :alt="'Product image ' + (index + 1)"
                                class="w-full h-32 object-cover rounded-lg border border-gray-200 dark:border-gray-600"
                            />
                        </div>
                    </div>
                </div>
            </div>

            <!-- Review History -->
            <div v-if="submission.reviewed_at" class="bg-white dark:bg-gray-800 shadow rounded-lg p-6 mb-6">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">Review History</h2>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Reviewed By</p>
                        <p class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ submission.reviewer?.name || '--' }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Reviewed At</p>
                        <p class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ formatDate(submission.reviewed_at) }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Decision</p>
                        <span :class="['inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium', statusBadgeClass(submission.status)]">
                            {{ statusLabel(submission.status) }}
                        </span>
                    </div>
                </div>
                <div v-if="submission.review_notes" class="mt-4">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Review Notes</p>
                    <p class="text-sm text-gray-900 dark:text-gray-100 mt-1 whitespace-pre-wrap">{{ submission.review_notes }}</p>
                </div>
            </div>

            <!-- Metadata -->
            <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6 mb-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                    <div>
                        <span class="text-gray-500 dark:text-gray-400">Submitted At:</span>
                        <span class="ml-2 text-gray-900 dark:text-gray-100">{{ formatDate(submission.created_at) }}</span>
                    </div>
                    <div>
                        <span class="text-gray-500 dark:text-gray-400">Submission ID:</span>
                        <span class="ml-2 text-gray-900 dark:text-gray-100 font-mono text-xs">{{ submission.id }}</span>
                    </div>
                </div>
            </div>

            <!-- Action buttons (only for pending submissions) -->
            <div v-if="submission.status === 'pending_review'" class="space-y-4">
                <div class="flex items-center space-x-4">
                    <button
                        type="button"
                        class="inline-flex items-center px-6 py-2.5 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500"
                        @click="approveSubmission"
                    >
                        Approve
                    </button>
                    <button
                        type="button"
                        class="inline-flex items-center px-6 py-2.5 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500"
                        @click="showRejectForm = !showRejectForm"
                    >
                        Reject
                    </button>
                </div>

                <!-- Reject form -->
                <div v-if="showRejectForm" class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-3">Rejection Notes</h3>
                    <form @submit.prevent="rejectSubmission">
                        <textarea
                            v-model="rejectForm.review_notes"
                            rows="4"
                            class="block w-full rounded-md border-gray-300 shadow-sm focus:border-red-500 focus:ring-red-500 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100 sm:text-sm"
                            placeholder="Provide a reason for rejection..."
                        ></textarea>
                        <p v-if="rejectForm.errors.review_notes" class="mt-1 text-sm text-red-600">{{ rejectForm.errors.review_notes }}</p>
                        <div class="mt-3 flex justify-end">
                            <button
                                type="button"
                                class="mr-3 px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 hover:text-gray-900 dark:hover:text-gray-100"
                                @click="showRejectForm = false"
                            >
                                Cancel
                            </button>
                            <button
                                type="submit"
                                :disabled="rejectForm.processing"
                                class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 disabled:opacity-50"
                            >
                                Confirm Rejection
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
