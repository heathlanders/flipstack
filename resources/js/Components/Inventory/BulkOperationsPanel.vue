<script setup>
import { ref, reactive, computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

const props = defineProps({
    selectedProducts: { type: Array, default: () => [] },
    filterOptions: { type: Object, default: () => ({}) },
    permissions: { type: Object, default: () => ({}) },
});

const emit = defineEmits(['clear-selection', 'operation-started', 'refresh']);
const page = usePage();
const showModal = ref(false);
const isLoading = ref(false);
const operation = ref('');
const operationData = reactive({ status: 'active', category_id: null, brand_id: null, adjustment_type: 'fixed', adjustment_value: 0, stock_adjustment: 0, stock_type: 'add' });
const previewData = ref(null);
const error = ref('');

const operations = [
    { id: 'update_status', label: 'Change Status' },
    { id: 'update_category', label: 'Change Category' },
    { id: 'update_brand', label: 'Change Brand' },
    { id: 'update_prices', label: 'Update Prices' },
    { id: 'adjust_stock', label: 'Adjust Stock' },
    { id: 'delete', label: 'Delete', requiresPermission: 'can_delete' },
].filter(op => !op.requiresPermission || props.permissions[op.requiresPermission]);

const selectOperation = (op) => { operation.value = op.id; showModal.value = true; previewData.value = null; error.value = ''; };
const cancel = () => { showModal.value = false; operation.value = ''; previewData.value = null; };

const buildData = () => {
    switch (operation.value) {
        case 'update_status': return { status: operationData.status };
        case 'update_category': return { category_id: operationData.category_id };
        case 'update_brand': return { brand_id: operationData.brand_id };
        case 'update_prices': return { adjustment_type: operationData.adjustment_type, adjustment_value: operationData.adjustment_value };
        case 'adjust_stock': return { adjustment: operationData.stock_adjustment, type: operationData.stock_type };
        case 'delete': return { confirm: true };
        default: return {};
    }
};

const preview = async () => {
    isLoading.value = true; error.value = '';
    try {
        const res = await fetch(route('inventory.bulk.preview'), {
            method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content, 'Accept': 'application/json' },
            body: JSON.stringify({ product_ids: props.selectedProducts, operation: operation.value, data: buildData() })
        });
        const data = await res.json();
        if (data.success) previewData.value = data.preview;
        else error.value = data.message || 'Preview failed';
    } catch (e) { error.value = 'Failed to generate preview'; }
    isLoading.value = false;
};

const execute = async () => {
    isLoading.value = true; error.value = '';
    try {
        const res = await fetch(route('inventory.bulk.execute'), {
            method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content, 'Accept': 'application/json' },
            body: JSON.stringify({ product_ids: props.selectedProducts, operation: operation.value, data: buildData() })
        });
        const data = await res.json();
        if (data.success) { emit('operation-started', data); cancel(); emit('clear-selection'); emit('refresh'); }
        else error.value = data.message || 'Operation failed';
    } catch (e) { error.value = 'Failed to execute operation'; }
    isLoading.value = false;
};
</script>

<template>
<Transition enter-active-class="transition duration-300" enter-from-class="translate-y-full opacity-0" enter-to-class="translate-y-0 opacity-100" leave-active-class="transition duration-300" leave-from-class="translate-y-0 opacity-100" leave-to-class="translate-y-full opacity-0">
    <div v-if="selectedProducts.length > 0" class="fixed bottom-6 left-1/2 -translate-x-1/2 z-50 bg-white dark:bg-gray-800 rounded-lg shadow-xl border border-gray-200 dark:border-gray-700 p-4">
        <div class="flex items-center gap-4">
            <div class="flex items-center gap-2">
                <span class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-indigo-100 dark:bg-indigo-900 text-indigo-700 font-semibold text-sm">{{ selectedProducts.length }}</span>
                <span class="text-sm text-gray-600 dark:text-gray-300">selected</span>
            </div>
            <div class="h-6 w-px bg-gray-300"></div>
            <div class="flex items-center gap-2">
                <button v-for="op in operations" :key="op.id" @click="selectOperation(op)" class="px-3 py-1.5 text-sm font-medium rounded-md transition-colors" :class="op.id === 'delete' ? 'text-red-600 hover:bg-red-50' : 'text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700'">{{ op.label }}</button>
            </div>
            <div class="h-6 w-px bg-gray-300"></div>
            <button @click="emit('clear-selection')" class="text-sm text-gray-500 hover:text-gray-700">Clear</button>
        </div>
    </div>
</Transition>

<Teleport to="body">
    <div v-if="showModal" class="fixed inset-0 z-50 overflow-y-auto">
        <div class="flex min-h-screen items-center justify-center p-4">
            <div class="fixed inset-0 bg-black/50" @click="cancel"></div>
            <div class="relative bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-lg w-full p-6">
                <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">{{ operations.find(o => o.id === operation)?.label }}</h3>
                <p class="text-sm text-gray-500 mb-4">Affects {{ selectedProducts.length }} products</p>
                <div v-if="error" class="mb-4 p-3 bg-red-50 rounded-md"><p class="text-sm text-red-600">{{ error }}</p></div>

                <div class="space-y-4 mb-6">
                    <div v-if="operation === 'update_status'">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">New Status</label>
                        <select v-model="operationData.status" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                            <option v-for="s in filterOptions.statuses" :key="s.value" :value="s.value">{{ s.label }}</option>
                        </select>
                    </div>
                    <div v-if="operation === 'update_category'">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Category</label>
                        <select v-model="operationData.category_id" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                            <option value="">Select...</option>
                            <option v-for="c in filterOptions.categories" :key="c.id" :value="c.id">{{ c.name }}</option>
                        </select>
                    </div>
                    <div v-if="operation === 'update_brand'">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Brand</label>
                        <select v-model="operationData.brand_id" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                            <option value="">Select...</option>
                            <option v-for="b in filterOptions.brands" :key="b.id" :value="b.id">{{ b.name }}</option>
                        </select>
                    </div>
                    <div v-if="operation === 'update_prices'" class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Type</label>
                            <select v-model="operationData.adjustment_type" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                                <option value="fixed">Fixed price</option><option value="increase_percent">Increase %</option><option value="decrease_percent">Decrease %</option><option value="increase_amount">Increase $</option><option value="decrease_amount">Decrease $</option>
                            </select>
                        </div>
                        <div><label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Value</label><input type="number" v-model.number="operationData.adjustment_value" step="0.01" min="0" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white"/></div>
                    </div>
                    <div v-if="operation === 'adjust_stock'" class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Type</label>
                            <select v-model="operationData.stock_type" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                                <option value="add">Add</option><option value="subtract">Subtract</option><option value="set">Set to</option>
                            </select>
                        </div>
                        <div><label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Quantity</label><input type="number" v-model.number="operationData.stock_adjustment" min="0" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white"/></div>
                    </div>
                    <div v-if="operation === 'delete'" class="p-4 bg-red-50 rounded-md"><p class="text-sm text-red-600"><strong>Warning:</strong> This cannot be undone.</p></div>
                </div>

                <div v-if="previewData" class="mb-6 p-4 bg-gray-50 dark:bg-gray-700/50 rounded-md max-h-48 overflow-y-auto">
                    <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Preview</h4>
                    <div v-for="p in previewData.affected_products" :key="p.id" class="text-xs text-gray-600 dark:text-gray-400 py-1 border-b last:border-0">
                        <span class="font-medium">{{ p.sku }}</span>: {{ p.name }}
                        <div v-for="(c, f) in p.changes" :key="f" class="ml-4">{{ f }}: {{ typeof c === 'object' && c.from !== undefined ? `${c.from} → ${c.to}` : c }}</div>
                    </div>
                </div>

                <div class="flex justify-end gap-3">
                    <button @click="cancel" class="px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 rounded-md">Cancel</button>
                    <button v-if="!previewData" @click="preview" :disabled="isLoading" class="px-4 py-2 text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 rounded-md disabled:opacity-50">{{ isLoading ? 'Loading...' : 'Preview' }}</button>
                    <button v-else @click="execute" :disabled="isLoading" :class="['px-4 py-2 text-sm font-medium text-white rounded-md disabled:opacity-50', operation === 'delete' ? 'bg-red-600 hover:bg-red-700' : 'bg-indigo-600 hover:bg-indigo-700']">{{ isLoading ? 'Processing...' : 'Execute' }}</button>
                </div>
            </div>
        </div>
    </div>
</Teleport>
</template>
