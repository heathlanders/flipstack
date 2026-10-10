<script setup>
import { ref, reactive } from 'vue';
import { Head, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import BulkProgressTracker from '@/Components/Inventory/BulkProgressTracker.vue';

const props = defineProps({ recentExports: Object, availableFields: Array, filterOptions: Object });
const page = usePage();
const isGenerating = ref(false);
const activeJobId = ref(null);
const error = ref('');
const settings = reactive({ format: 'csv', fields: (props.availableFields || []).filter(f => f.default).map(f => f.field), filters: {} });

const toggleField = (f) => { const i = settings.fields.indexOf(f); i === -1 ? settings.fields.push(f) : settings.fields.splice(i, 1); };

const startExport = async () => {
    if (settings.fields.length === 0) { error.value = 'Select at least one field'; return; }
    isGenerating.value = true; error.value = '';
    try {
        const res = await fetch(route('inventory.export.generate'), { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content }, body: JSON.stringify(settings) });
        const data = await res.json();
        if (data.success) activeJobId.value = data.job_id;
        else error.value = data.message;
    } catch (e) { error.value = 'Export failed'; }
    isGenerating.value = false;
};
</script>

<template>
<AppLayout title="Export Products">
    <Head title="Export Products" />
    <template #header><h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200">Export Products</h2></template>
    <div class="py-12"><div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
        <div v-if="activeJobId" class="mb-8"><BulkProgressTracker :job-id="activeJobId" @completed="activeJobId = null" /></div>
        <div class="bg-white dark:bg-gray-800 shadow-xl sm:rounded-lg p-6 mb-8">
            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Export Settings</h3>
            <div v-if="error" class="mb-4 p-3 bg-red-50 rounded-md"><p class="text-sm text-red-600">{{ error }}</p></div>
            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 mb-2">Format</label>
                <div class="flex gap-4">
                    <label class="flex items-center gap-2"><input type="radio" v-model="settings.format" value="csv" class="text-indigo-600"><span class="text-sm">CSV</span></label>
                    <label class="flex items-center gap-2"><input type="radio" v-model="settings.format" value="xlsx" class="text-indigo-600"><span class="text-sm">XLSX</span></label>
                </div>
            </div>
            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 mb-2">Fields ({{ settings.fields.length }} selected)</label>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-2">
                    <label v-for="f in availableFields" :key="f.field" class="flex items-center gap-2 p-2 rounded hover:bg-gray-50 cursor-pointer">
                        <input type="checkbox" :checked="settings.fields.includes(f.field)" @change="toggleField(f.field)" class="rounded border-gray-300 text-indigo-600">
                        <span class="text-sm text-gray-700">{{ f.label }}</span>
                    </label>
                </div>
            </div>
            <div class="flex justify-end">
                <button @click="startExport" :disabled="isGenerating || settings.fields.length === 0" class="px-4 py-2 text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 rounded-md disabled:opacity-50">{{ isGenerating ? 'Starting...' : 'Start Export' }}</button>
            </div>
        </div>
        <div class="bg-white dark:bg-gray-800 shadow-xl sm:rounded-lg">
            <div class="p-6 border-b border-gray-200 dark:border-gray-700"><h3 class="text-lg font-medium text-gray-900 dark:text-white">Recent Exports</h3></div>
            <div v-if="recentExports?.data?.length > 0">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50"><tr><th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th><th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Products</th><th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Action</th></tr></thead>
                    <tbody class="divide-y divide-gray-200">
                        <tr v-for="job in recentExports.data" :key="job.id">
                            <td class="px-6 py-4"><span class="px-2 py-1 text-xs font-medium rounded-full" :class="{'text-green-600 bg-green-100': job.status === 'completed', 'text-red-600 bg-red-100': job.status === 'failed'}">{{ job.status_label }}</span></td>
                            <td class="px-6 py-4 text-sm text-gray-600">{{ job.total_items }}</td>
                            <td class="px-6 py-4"><a v-if="job.has_download" :href="route('inventory.export.download', { job: job.id })" class="text-sm text-indigo-600">Download</a></td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div v-else class="p-6 text-center text-gray-500">No recent exports.</div>
        </div>
    </div></div>
</AppLayout>
</template>
