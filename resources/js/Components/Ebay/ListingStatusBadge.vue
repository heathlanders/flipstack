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
    active: {
        label: 'Active',
        class: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-400',
        icon: 'M5 13l4 4L19 7'
    },
    ended: {
        label: 'Ended',
        class: 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400',
        icon: 'M6 18L18 6M6 6l12 12'
    },
    draft: {
        label: 'Draft',
        class: 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400',
        icon: 'M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z'
    },
    pending: {
        label: 'Pending',
        class: 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400',
        icon: 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z'
    },
    error: {
        label: 'Error',
        class: 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400',
        icon: 'M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z'
    },
    sold: {
        label: 'Sold',
        class: 'bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-400',
        icon: 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z'
    },
    out_of_stock: {
        label: 'Out of Stock',
        class: 'bg-orange-100 text-orange-800 dark:bg-orange-900/30 dark:text-orange-400',
        icon: 'M20 12H4'
    }
};

const sizeClasses = {
    xs: 'px-1.5 py-0.5 text-xs',
    sm: 'px-2 py-0.5 text-xs',
    md: 'px-2.5 py-1 text-sm'
};

const iconSizeClasses = {
    xs: 'w-3 h-3',
    sm: 'w-3.5 h-3.5',
    md: 'w-4 h-4'
};

const config = statusConfig[props.status] || {
    label: props.status,
    class: 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400',
    icon: 'M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z'
};
</script>

<template>
    <span :class="[config.class, sizeClasses[size]]" class="inline-flex items-center gap-1 rounded-full font-medium">
        <svg v-if="config.icon" :class="iconSizeClasses[size]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="config.icon" />
        </svg>
        {{ config.label }}
    </span>
</template>