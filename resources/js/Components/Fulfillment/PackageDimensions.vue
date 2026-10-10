<template>
    <div class="bg-white rounded-lg border border-gray-200 p-4">
        <h4 class="text-sm font-medium text-gray-900 mb-3">Package Dimensions</h4>

        <div class="grid grid-cols-2 gap-3">
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Weight (oz)</label>
                <input
                    type="number"
                    step="0.1"
                    min="0.1"
                    v-model.number="form.weight_oz"
                    @change="emitUpdate"
                    class="w-full rounded-md border-gray-300 text-sm focus:ring-indigo-500 focus:border-indigo-500"
                    placeholder="16"
                />
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Length (in)</label>
                <input
                    type="number"
                    step="0.1"
                    min="0.1"
                    v-model.number="form.length"
                    @change="emitUpdate"
                    class="w-full rounded-md border-gray-300 text-sm focus:ring-indigo-500 focus:border-indigo-500"
                    placeholder="12"
                />
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Width (in)</label>
                <input
                    type="number"
                    step="0.1"
                    min="0.1"
                    v-model.number="form.width"
                    @change="emitUpdate"
                    class="w-full rounded-md border-gray-300 text-sm focus:ring-indigo-500 focus:border-indigo-500"
                    placeholder="9"
                />
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Height (in)</label>
                <input
                    type="number"
                    step="0.1"
                    min="0.1"
                    v-model.number="form.height"
                    @change="emitUpdate"
                    class="w-full rounded-md border-gray-300 text-sm focus:ring-indigo-500 focus:border-indigo-500"
                    placeholder="4"
                />
            </div>
        </div>

        <div v-if="presets.length > 0" class="mt-3">
            <label class="block text-xs font-medium text-gray-600 mb-1">Quick Presets</label>
            <div class="flex flex-wrap gap-1">
                <button
                    v-for="preset in presets"
                    :key="preset.name"
                    @click="applyPreset(preset)"
                    class="px-2 py-1 text-xs rounded border border-gray-200 hover:bg-gray-50 text-gray-700"
                >
                    {{ preset.name }}
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { reactive } from 'vue';

const props = defineProps({
    initialDimensions: { type: Object, default: () => ({}) },
});

const emit = defineEmits(['update']);

const form = reactive({
    weight_oz: props.initialDimensions.weight_oz || null,
    length: props.initialDimensions.length || null,
    width: props.initialDimensions.width || null,
    height: props.initialDimensions.height || null,
});

const presets = [
    { name: 'Padded Envelope', weight_oz: 8, length: 12.5, width: 9.5, height: 1 },
    { name: 'Small Box', weight_oz: 16, length: 10, width: 8, height: 4 },
    { name: 'Medium Box', weight_oz: 32, length: 14, width: 10, height: 6 },
    { name: 'Large Box', weight_oz: 48, length: 18, width: 14, height: 8 },
    { name: 'Flat Rate Padded', weight_oz: 16, length: 12.5, width: 9.5, height: 2 },
];

function applyPreset(preset) {
    form.weight_oz = preset.weight_oz;
    form.length = preset.length;
    form.width = preset.width;
    form.height = preset.height;
    emitUpdate();
}

function emitUpdate() {
    emit('update', { ...form });
}
</script>
