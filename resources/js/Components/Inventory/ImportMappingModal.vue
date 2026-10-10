<script setup>
import { ref, reactive, computed, watch } from 'vue';

const props = defineProps({ show: Boolean, fileColumns: Array, suggestedMapping: Object, templateFields: Array, preview: Array });
const emit = defineEmits(['close', 'confirm']);
const mapping = reactive({});

watch(() => props.suggestedMapping, (m) => { Object.keys(m || {}).forEach(k => mapping[k] = m[k]); }, { immediate: true });

const required = computed(() => props.templateFields?.filter(f => f.required).map(f => f.field) || []);
const missing = computed(() => required.value.filter(f => !mapping[f]));
const isValid = computed(() => missing.value.length === 0);

const confirm = () => { if (isValid.value) emit('confirm', { ...mapping }); };
</script>

<template>
<Teleport to="body">
    <div v-if="show" class="fixed inset-0 z-50 overflow-y-auto">
        <div class="flex min-h-screen items-center justify-center p-4">
            <div class="fixed inset-0 bg-black/50" @click="emit('close')"></div>
            <div class="relative bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-3xl w-full p-6 max-h-[90vh] overflow-y-auto">
                <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Map Import Columns</h3>
                <p class="text-sm text-gray-500 mb-6">Match your file columns to product fields.</p>
                <div v-if="missing.length > 0" class="mb-4 p-3 bg-yellow-50 rounded-md"><p class="text-sm text-yellow-700"><strong>Missing:</strong> {{ missing.join(', ') }}</p></div>
                <div class="space-y-4 mb-6">
                    <div v-for="f in templateFields" :key="f.field" class="grid grid-cols-2 gap-4 items-center">
                        <div><label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ f.field }}<span v-if="f.required" class="text-red-500">*</span></label><p class="text-xs text-gray-500">{{ f.description }}</p></div>
                        <select v-model="mapping[f.field]" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                            <option value="">-- Not mapped --</option>
                            <option v-for="col in fileColumns" :key="col" :value="col">{{ col }}</option>
                        </select>
                    </div>
                </div>
                <div class="flex justify-end gap-3">
                    <button @click="emit('close')" class="px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 rounded-md">Cancel</button>
                    <button @click="confirm" :disabled="!isValid" class="px-4 py-2 text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 rounded-md disabled:opacity-50">Continue</button>
                </div>
            </div>
        </div>
    </div>
</Teleport>
</template>
