<script setup>
import { computed, ref } from 'vue';

const props = defineProps({
    modelValue: {
        type: String,
        default: ''
    },
    placeholder: {
        type: String,
        default: 'Search...'
    },
    disabled: Boolean,
    loading: Boolean,
    clearable: {
        type: Boolean,
        default: true
    },
    size: {
        type: String,
        default: 'medium',
        validator: (value) => ['small', 'medium', 'large'].includes(value)
    }
});

const emit = defineEmits(['update:modelValue', 'clear', 'search']);

const inputRef = ref(null);

const value = computed({
    get() {
        return props.modelValue;
    },
    set(newValue) {
        emit('update:modelValue', newValue);
    }
});

const sizeClasses = computed(() => {
    const sizes = {
        small: 'px-3 py-1.5 text-sm',
        medium: 'px-4 py-2 text-sm',
        large: 'px-4 py-3 text-base'
    };
    return sizes[props.size] || sizes.medium;
});

const clearSearch = () => {
    value.value = '';
    emit('clear');
    inputRef.value?.focus();
};

const handleKeydown = (event) => {
    if (event.key === 'Enter') {
        emit('search', value.value);
    }
    if (event.key === 'Escape') {
        clearSearch();
    }
};

const focus = () => {
    inputRef.value?.focus();
};

defineExpose({
    focus,
    blur: () => inputRef.value?.blur()
});
</script>

<template>
    <div class="relative">
        <!-- Search Icon -->
        <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
            <i v-if="!loading" class="fas fa-search text-gray-400"></i>
            <div v-else class="animate-spin">
                <i class="fas fa-spinner text-gray-400"></i>
            </div>
        </div>

        <!-- Input Field -->
        <input
            ref="inputRef"
            v-model="value"
            type="text"
            :placeholder="placeholder"
            :disabled="disabled || loading"
            :class="[
                'w-full pl-10 pr-10 border border-gray-300 dark:border-gray-600 rounded-lg shadow-sm',
                'focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500',
                'dark:bg-gray-700 dark:text-white dark:placeholder-gray-400',
                'transition-colors duration-200',
                sizeClasses,
                disabled || loading ? 'bg-gray-100 dark:bg-gray-800 cursor-not-allowed' : 'bg-white dark:bg-gray-700'
            ]"
            @keydown="handleKeydown"
        />

        <!-- Clear Button -->
        <div 
            v-if="clearable && value && !loading" 
            class="absolute inset-y-0 right-0 flex items-center pr-3"
        >
            <button
                type="button"
                @click="clearSearch"
                class="p-1 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition-colors duration-200 rounded-full hover:bg-gray-100 dark:hover:bg-gray-600"
            >
                <i class="fas fa-times text-sm"></i>
            </button>
        </div>
    </div>
</template>