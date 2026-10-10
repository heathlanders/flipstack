<script setup>
import { computed } from 'vue';

const props = defineProps({
    modelValue: {
        type: String,
        default: ''
    },
    id: String,
    placeholder: String,
    rows: {
        type: Number,
        default: 4
    },
    required: Boolean,
    disabled: Boolean,
    readonly: Boolean
});

const emit = defineEmits(['update:modelValue']);

const value = computed({
    get() {
        return props.modelValue;
    },
    set(value) {
        emit('update:modelValue', value);
    }
});
</script>

<template>
    <textarea
        v-model="value"
        :id="id"
        :placeholder="placeholder"
        :rows="rows"
        :required="required"
        :disabled="disabled"
        :readonly="readonly"
        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white dark:placeholder-gray-400 resize-vertical"
    ></textarea>
</template>