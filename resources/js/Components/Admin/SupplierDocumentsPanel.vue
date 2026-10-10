<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';

const props = defineProps({
    documents: { type: Array, default: () => [] },
    supplierId: { type: [String, Number], required: true },
});

const rejectingType = ref(null);
const rejectionReason = ref('');
const isSaving = ref(false);

const statusBadge = (status) => {
    const map = {
        verified: 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300',
        pending: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/40 dark:text-yellow-300',
        rejected: 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300',
        missing: 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300',
    };
    return map[status] || map.missing;
};

const statusLabel = (status) => ({
    verified: 'Verified',
    pending: 'Pending review',
    rejected: 'Rejected',
    missing: 'Not uploaded',
}[status] || status);

const formatDate = (iso) => iso ? new Date(iso).toLocaleString() : '-';

const downloadUrl = (type) => `/admin/suppliers/${props.supplierId}/documents/${type}`;

const verify = (type) => {
    isSaving.value = true;
    router.patch(route('admin.suppliers.documents.verify', { supplier: props.supplierId, type }), {}, {
        preserveScroll: true,
        onFinish: () => { isSaving.value = false; },
    });
};

const startReject = (type) => {
    rejectingType.value = type;
    rejectionReason.value = '';
};

const confirmReject = () => {
    if (!rejectionReason.value.trim()) return;
    isSaving.value = true;
    router.patch(
        route('admin.suppliers.documents.reject', { supplier: props.supplierId, type: rejectingType.value }),
        { reason: rejectionReason.value },
        {
            preserveScroll: true,
            onSuccess: () => { rejectingType.value = null; rejectionReason.value = ''; },
            onFinish: () => { isSaving.value = false; },
        }
    );
};

const cancelReject = () => {
    rejectingType.value = null;
    rejectionReason.value = '';
};
</script>

<template>
    <div class="space-y-3">
        <div class="flex justify-between items-center">
            <h4 class="text-sm font-medium text-gray-900 dark:text-white">Onboarding Documents</h4>
        </div>

        <div class="space-y-2">
            <div
                v-for="doc in documents"
                :key="doc.type"
                class="p-4 bg-gray-50 dark:bg-slate-700 rounded-lg"
            >
                <div class="flex items-center justify-between gap-3">
                    <div class="min-w-0">
                        <div class="flex items-center gap-2">
                            <p class="text-sm font-medium text-gray-900 dark:text-white">{{ doc.label }}</p>
                            <span :class="['inline-flex px-2 py-0.5 rounded-full text-xs font-medium', statusBadge(doc.status)]">
                                {{ statusLabel(doc.status) }}
                            </span>
                        </div>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1 truncate">
                            <template v-if="doc.uploaded">
                                {{ doc.original_name }} &middot; uploaded {{ formatDate(doc.uploaded_at) }}
                            </template>
                            <template v-else>
                                Supplier has not uploaded this document yet.
                            </template>
                        </p>
                        <p v-if="doc.status === 'rejected' && doc.rejection_reason" class="text-xs text-red-600 dark:text-red-300 mt-1">
                            Rejection reason: {{ doc.rejection_reason }}
                        </p>
                        <p v-if="doc.reviewed_at" class="text-xs text-gray-400 dark:text-gray-500 mt-1">
                            Reviewed by {{ doc.reviewed_by || 'admin' }} on {{ formatDate(doc.reviewed_at) }}
                        </p>
                    </div>

                    <div class="flex items-center gap-2 flex-shrink-0">
                        <a
                            v-if="doc.uploaded"
                            :href="downloadUrl(doc.type)"
                            class="px-3 py-1 text-sm bg-gray-200 dark:bg-gray-600 text-gray-700 dark:text-gray-200 rounded-md hover:bg-gray-300"
                        >
                            Download
                        </a>
                        <button
                            v-if="doc.uploaded && doc.status !== 'verified'"
                            @click="verify(doc.type)"
                            :disabled="isSaving"
                            class="px-3 py-1 text-sm bg-green-600 text-white rounded-md hover:bg-green-700 disabled:opacity-50"
                        >
                            Verify
                        </button>
                        <button
                            v-if="doc.uploaded && doc.status !== 'rejected'"
                            @click="startReject(doc.type)"
                            :disabled="isSaving"
                            class="px-3 py-1 text-sm bg-red-600 text-white rounded-md hover:bg-red-700 disabled:opacity-50"
                        >
                            Reject
                        </button>
                    </div>
                </div>

                <div v-if="rejectingType === doc.type" class="mt-3 pt-3 border-t border-gray-200 dark:border-gray-600">
                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Rejection reason
                    </label>
                    <textarea
                        v-model="rejectionReason"
                        rows="2"
                        class="w-full text-sm rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                        placeholder="Explain what's wrong so the supplier can re-upload"
                    />
                    <div class="mt-2 flex justify-end gap-2">
                        <button
                            @click="cancelReject"
                            class="px-3 py-1 text-sm text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600 rounded-md"
                        >
                            Cancel
                        </button>
                        <button
                            @click="confirmReject"
                            :disabled="isSaving || !rejectionReason.trim()"
                            class="px-3 py-1 text-sm bg-red-600 text-white rounded-md hover:bg-red-700 disabled:opacity-50"
                        >
                            Confirm rejection
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
