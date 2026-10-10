<script setup>
import { ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { DocumentTextIcon, ArrowUpTrayIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    supplier: { type: Object, required: true },
    documents: { type: Array, default: () => [] },
    document_types: { type: Object, default: () => ({}) },
});

const uploadingType = ref(null);

const uploadForm = useForm({
    type: '',
    file: null,
});

const statusBadge = (status) => ({
    verified: 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300',
    pending: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/40 dark:text-yellow-300',
    rejected: 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300',
    missing: 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300',
}[status] || 'bg-gray-100 text-gray-700');

const statusLabel = (status) => ({
    verified: 'Verified',
    pending: 'Pending review',
    rejected: 'Action required',
    missing: 'Not uploaded',
}[status] || status);

const formatDate = (iso) => iso ? new Date(iso).toLocaleString() : '-';

const startUpload = (type) => {
    uploadingType.value = type;
    uploadForm.type = type;
    uploadForm.file = null;
};

const cancelUpload = () => {
    uploadingType.value = null;
    uploadForm.reset();
};

const handleFile = (event) => {
    uploadForm.file = event.target.files[0] || null;
};

const submitUpload = () => {
    if (!uploadForm.file) return;
    uploadForm.post(route('supplier.documents.store'), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            uploadingType.value = null;
            uploadForm.reset();
        },
    });
};

const downloadUrl = (type) => route('supplier.documents.download', { type });
</script>

<template>
    <AppLayout title="Documents">
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                Onboarding Documents
            </h2>
        </template>

        <div class="py-8">
            <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6 mb-6">
                    <p class="text-sm text-gray-600 dark:text-gray-300">
                        Upload required onboarding documents below. Each document is reviewed by your account manager.
                        If a document is rejected, the reason will be shown and you can re-upload a corrected version.
                    </p>
                </div>

                <div class="space-y-3">
                    <div
                        v-for="doc in documents"
                        :key="doc.type"
                        class="bg-white dark:bg-gray-800 shadow rounded-lg p-5"
                    >
                        <div class="flex items-start justify-between gap-4">
                            <div class="flex items-start gap-3 min-w-0 flex-1">
                                <DocumentTextIcon class="w-8 h-8 text-gray-400 flex-shrink-0 mt-0.5" />
                                <div class="min-w-0">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <h3 class="text-sm font-medium text-gray-900 dark:text-white">{{ doc.label }}</h3>
                                        <span :class="['inline-flex px-2 py-0.5 rounded-full text-xs font-medium', statusBadge(doc.status)]">
                                            {{ statusLabel(doc.status) }}
                                        </span>
                                    </div>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1 truncate">
                                        <template v-if="doc.uploaded">
                                            {{ doc.original_name }} &middot; uploaded {{ formatDate(doc.uploaded_at) }}
                                        </template>
                                        <template v-else>
                                            Not yet uploaded
                                        </template>
                                    </p>
                                    <p v-if="doc.status === 'rejected' && doc.rejection_reason" class="text-xs text-red-600 dark:text-red-300 mt-1">
                                        Reviewer note: {{ doc.rejection_reason }}
                                    </p>
                                </div>
                            </div>

                            <div class="flex items-center gap-2 flex-shrink-0">
                                <a
                                    v-if="doc.uploaded"
                                    :href="downloadUrl(doc.type)"
                                    class="px-3 py-1 text-sm bg-gray-200 dark:bg-gray-600 text-gray-700 dark:text-gray-200 rounded-md hover:bg-gray-300"
                                >
                                    View
                                </a>
                                <button
                                    @click="startUpload(doc.type)"
                                    class="px-3 py-1 text-sm bg-indigo-600 text-white rounded-md hover:bg-indigo-700 inline-flex items-center"
                                >
                                    <ArrowUpTrayIcon class="w-4 h-4 mr-1" />
                                    {{ doc.uploaded ? 'Replace' : 'Upload' }}
                                </button>
                            </div>
                        </div>

                        <div v-if="uploadingType === doc.type" class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                            <input
                                type="file"
                                accept=".pdf,.jpg,.jpeg,.png"
                                @change="handleFile"
                                class="block w-full text-sm text-gray-700 dark:text-gray-300 file:mr-3 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-medium file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100"
                            />
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">PDF, JPG, or PNG. Max 10 MB.</p>
                            <p v-if="uploadForm.errors.file" class="mt-1 text-xs text-red-600 dark:text-red-300">
                                {{ uploadForm.errors.file }}
                            </p>
                            <div class="mt-3 flex justify-end gap-2">
                                <button
                                    @click="cancelUpload"
                                    class="px-3 py-1 text-sm text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-md"
                                >
                                    Cancel
                                </button>
                                <button
                                    @click="submitUpload"
                                    :disabled="!uploadForm.file || uploadForm.processing"
                                    class="px-3 py-1 text-sm bg-indigo-600 text-white rounded-md hover:bg-indigo-700 disabled:opacity-50"
                                >
                                    {{ uploadForm.processing ? 'Uploading...' : 'Submit' }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
