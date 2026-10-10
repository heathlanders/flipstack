<script setup>
const props = defineProps({
    status: {
        type: String,
        required: true
    },
    size: {
        type: String,
        default: 'md',
        validator: (value) => ['xs', 'sm', 'md'].includes(value)
    }
});

const statusConfig = {
    pending: {
        label: 'Pending',
        class: 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400'
    },
    synced: {
        label: 'Synced',
        class: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-400'
    },
    success: {
        label: 'Success',
        class: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-400'
    },
    failed: {
        label: 'Failed',
        class: 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400'
    },
    error: {
        label: 'Error',
        class: 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400'
    },
    processing: {
        label: 'Processing',
        class: 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400'
    },
    never: {
        label: 'Never Synced',
        class: 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400'
    },
    disabled: {
        label: 'Disabled',
        class: 'bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-500'
    }
};

const sizeClasses = {
    xs: 'px-1.5 py-0.5 text-xs',
    sm: 'px-2 py-0.5 text-xs',
    md: 'px-2.5 py-1 text-sm'
};

const config = statusConfig[props.status] || {
    label: props.status,
    class: 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400'
};
</script>

<template>
    <span :class="[config.class, sizeClasses[size]]" class="inline-flex items-center gap-1 rounded-full font-medium">
        <!-- Animated dot for processing status -->
        <span v-if="status === 'processing'" class="relative flex h-2 w-2">
            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-blue-400 opacity-75"></span>
            <span class="relative inline-flex rounded-full h-2 w-2 bg-blue-500"></span>
        </span>
        {{ config.label }}
    </span>
</template>