<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';

const props = defineProps({ jobId: { type: [Number, String], required: true }, initialData: { type: Object, default: null }, pollInterval: { type: Number, default: 2000 } });
const emit = defineEmits(['completed', 'failed', 'cancelled']);
const job = ref(props.initialData || { status: 'pending', total_items: 0, processed_items: 0, failed_items: 0, progress_percent: 0 });
let pollTimer = null;

const statusColor = computed(() => ({ completed: 'text-green-600 bg-green-100', failed: 'text-red-600 bg-red-100', cancelled: 'text-gray-600 bg-gray-100', processing: 'text-blue-600 bg-blue-100' }[job.value.status] || 'text-yellow-600 bg-yellow-100'));
const progressColor = computed(() => job.value.failed_items === job.value.total_items ? 'bg-red-500' : job.value.failed_items > 0 ? 'bg-yellow-500' : 'bg-indigo-600');
const isActive = computed(() => ['pending', 'processing'].includes(job.value.status));

const fetchProgress = async () => {
    try {
        const res = await fetch(route('api.bulk.progress', { job: props.jobId }), { headers: { Accept: 'application/json' } });
        if (res.ok) {
            const data = await res.json();
            job.value = { ...job.value, ...data };
            if (data.status === 'completed') { emit('completed', data); stopPolling(); }
            else if (data.status === 'failed') { emit('failed', data); stopPolling(); }
            else if (data.status === 'cancelled') { emit('cancelled', data); stopPolling(); }
        }
    } catch (e) { console.error('Failed to fetch progress'); }
};

const startPolling = () => { pollTimer = setInterval(fetchProgress, props.pollInterval); fetchProgress(); };
const stopPolling = () => { if (pollTimer) { clearInterval(pollTimer); pollTimer = null; } };

const cancel = async () => {
    try { await fetch(route('inventory.bulk.jobs.cancel', { job: props.jobId }), { method: 'POST', headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content } }); }
    catch (e) { console.error('Failed to cancel'); }
};

onMounted(() => { if (isActive.value) startPolling(); });
onUnmounted(() => stopPolling());
watch(() => props.jobId, () => { stopPolling(); if (isActive.value) startPolling(); });
</script>

<template>
<div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-4">
    <div class="flex items-center justify-between mb-3">
        <div class="flex items-center gap-2">
            <span class="px-2 py-1 text-xs font-medium rounded-full" :class="statusColor">{{ job.status?.toUpperCase() }}</span>
            <span class="text-sm text-gray-500">Job #{{ jobId }}</span>
        </div>
        <button v-if="job.is_cancellable" @click="cancel" class="text-sm text-red-600 hover:text-red-700">Cancel</button>
    </div>
    <div class="mb-3">
        <div class="flex justify-between text-sm text-gray-600 mb-1"><span>Progress</span><span>{{ job.processed_items }} / {{ job.total_items }}</span></div>
        <div class="w-full h-2 bg-gray-200 rounded-full overflow-hidden"><div class="h-full transition-all duration-300 rounded-full" :class="progressColor" :style="{ width: `${job.progress_percent || 0}%` }"></div></div>
    </div>
    <div class="grid grid-cols-3 gap-4 text-center text-sm">
        <div><div class="text-lg font-semibold text-gray-900 dark:text-white">{{ job.processed_items }}</div><div class="text-gray-500">Processed</div></div>
        <div><div class="text-lg font-semibold text-green-600">{{ job.processed_items - (job.failed_items || 0) }}</div><div class="text-gray-500">Succeeded</div></div>
        <div><div class="text-lg font-semibold text-red-600">{{ job.failed_items || 0 }}</div><div class="text-gray-500">Failed</div></div>
    </div>
</div>
</template>
