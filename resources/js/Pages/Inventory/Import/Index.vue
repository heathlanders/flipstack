<script setup>
import { ref, reactive } from 'vue';
import { Head, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import ImportMappingModal from '@/Components/Inventory/ImportMappingModal.vue';
import BulkProgressTracker from '@/Components/Inventory/BulkProgressTracker.vue';

const props = defineProps({ recentImports: Object, templateFields: Array });
const page = usePage();
const fileInput = ref(null);
const isUploading = ref(false);
const uploadedFile = ref(null);
const showMapping = ref(false);
const validationResult = ref(null);
const isProcessing = ref(false);
const activeJobId = ref(null);
const options = reactive({ updateExisting: false, skipErrors: true });
const error = ref('');

const handleFile = async (e) => {
    const file = e.target.files?.[0]; if (!file) return;
    isUploading.value = true; error.value = '';
    const formData = new FormData(); formData.append('file', file);
    try {
        const res = await fetch(route('inventory.import.upload'), { method: 'POST', headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content }, body: formData });
        const data = await res.json();
        if (data.success) { uploadedFile.value = data; showMapping.value = true; }
        else error.value = data.message || 'Upload failed';
    } catch (e) { error.value = 'Upload failed'; }
    isUploading.value = false;
};

const validate = async (mapping) => {
    showMapping.value = false;
    try {
        const res = await fetch(route('inventory.import.validate'), { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content }, body: JSON.stringify({ file_path: uploadedFile.value.file_path, column_mapping: mapping, ...options }) });
        validationResult.value = { ...(await res.json()), column_mapping: mapping };
    } catch (e) { error.value = 'Validation failed'; }
};

const startImport = async () => {
    isProcessing.value = true;
    try {
        const res = await fetch(route('inventory.import.process'), { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content }, body: JSON.stringify({ file_path: uploadedFile.value.file_path, column_mapping: validationResult.value.column_mapping, ...options }) });
        const data = await res.json();
        if (data.success) { activeJobId.value = data.job_id; uploadedFile.value = null; validationResult.value = null; }
        else error.value = data.message;
    } catch (e) { error.value = 'Import failed'; }
    isProcessing.value = false;
};

const reset = () => { uploadedFile.value = null; validationResult.value = null; error.value = ''; if (fileInput.value) fileInput.value.value = ''; };
</script>

<template>
<AppLayout title="Import Products">
    <Head title="Import Products" />
    <template #header><div class="flex justify-between"><h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200">Import Products</h2><a :href="route('inventory.import.template')" class="text-sm text-indigo-600">Download Template</a></div></template>
    <div class="py-12"><div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
        <div v-if="activeJobId" class="mb-8"><BulkProgressTracker :job-id="activeJobId" @completed="activeJobId = null" /></div>
        <div class="bg-white dark:bg-gray-800 shadow-xl sm:rounded-lg p-6 mb-8">
            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Upload File</h3>
            <div v-if="error" class="mb-4 p-3 bg-red-50 rounded-md"><p class="text-sm text-red-600">{{ error }}</p></div>
            <div v-if="!uploadedFile" class="border-2 border-dashed border-gray-300 rounded-lg p-8 text-center">
                <input ref="fileInput" type="file" accept=".csv,.xlsx,.xls" class="hidden" @change="handleFile" />
                <p class="text-gray-600 mb-4">{{ isUploading ? 'Uploading...' : 'Drag and drop or click to browse' }}</p>
                <button v-if="!isUploading" @click="fileInput?.click()" class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700">Select File</button>
            </div>
            <div v-else class="space-y-4">
                <div class="flex justify-between p-4 bg-gray-50 rounded-md">
                    <div><p class="font-medium">{{ uploadedFile.file_name }}</p><p class="text-sm text-gray-500">{{ uploadedFile.row_count }} rows</p></div>
                    <button @click="reset" class="text-sm text-red-600">Remove</button>
                </div>
                <label class="flex items-center gap-2"><input type="checkbox" v-model="options.updateExisting" class="rounded border-gray-300 text-indigo-600"><span class="text-sm text-gray-700">Update existing (match by SKU)</span></label>
                <label class="flex items-center gap-2"><input type="checkbox" v-model="options.skipErrors" class="rounded border-gray-300 text-indigo-600"><span class="text-sm text-gray-700">Skip errors and continue</span></label>
                <button @click="showMapping = true" class="text-sm text-indigo-600">Edit Column Mapping</button>
            </div>
        </div>
        <div v-if="validationResult" class="bg-white dark:bg-gray-800 shadow-xl sm:rounded-lg p-6 mb-8">
            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Validation Results</h3>
            <div class="grid grid-cols-4 gap-4 mb-6">
                <div class="text-center p-4 bg-gray-50 rounded-md"><div class="text-2xl font-bold">{{ validationResult.total_rows }}</div><div class="text-sm text-gray-500">Total</div></div>
                <div class="text-center p-4 bg-green-50 rounded-md"><div class="text-2xl font-bold text-green-600">{{ validationResult.valid_rows }}</div><div class="text-sm text-gray-500">Valid</div></div>
                <div class="text-center p-4 bg-blue-50 rounded-md"><div class="text-2xl font-bold text-blue-600">{{ validationResult.new_products }}</div><div class="text-sm text-gray-500">New</div></div>
                <div class="text-center p-4 bg-red-50 rounded-md"><div class="text-2xl font-bold text-red-600">{{ validationResult.error_rows }}</div><div class="text-sm text-gray-500">Errors</div></div>
            </div>
            <div class="flex justify-end gap-3">
                <button @click="reset" class="px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 rounded-md">Cancel</button>
                <button @click="startImport" :disabled="isProcessing || validationResult.valid_rows === 0" class="px-4 py-2 text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 rounded-md disabled:opacity-50">{{ isProcessing ? 'Starting...' : `Import ${validationResult.valid_rows} Products` }}</button>
            </div>
        </div>
    </div></div>
    <ImportMappingModal :show="showMapping" :file-columns="uploadedFile?.detected_columns || []" :suggested-mapping="uploadedFile?.suggested_mapping || {}" :template-fields="templateFields || []" :preview="uploadedFile?.preview || []" @close="showMapping = false" @confirm="validate" />
</AppLayout>
</template>
