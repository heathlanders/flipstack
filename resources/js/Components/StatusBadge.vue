<script setup>
import { computed } from 'vue'

const props = defineProps({
    status: {
        type: String,
        required: true
    },
    size: {
        type: String,
        default: 'md'
    }
})

const statusConfig = {
    // Settlement statuses
    pending: { bg: 'bg-yellow-100 dark:bg-yellow-900/30', text: 'text-yellow-800 dark:text-yellow-300', label: 'Pending' },
    approved: { bg: 'bg-blue-100 dark:bg-blue-900/30', text: 'text-blue-800 dark:text-blue-300', label: 'Approved' },
    processing: { bg: 'bg-indigo-100 dark:bg-indigo-900/30', text: 'text-indigo-800 dark:text-indigo-300', label: 'Processing' },
    completed: { bg: 'bg-green-100 dark:bg-green-900/30', text: 'text-green-800 dark:text-green-300', label: 'Completed' },
    paid: { bg: 'bg-green-100 dark:bg-green-900/30', text: 'text-green-800 dark:text-green-300', label: 'Paid' },
    failed: { bg: 'bg-red-100 dark:bg-red-900/30', text: 'text-red-800 dark:text-red-300', label: 'Failed' },
    cancelled: { bg: 'bg-gray-100 dark:bg-gray-900/30', text: 'text-gray-800 dark:text-gray-300', label: 'Cancelled' },
    disputed: { bg: 'bg-orange-100 dark:bg-orange-900/30', text: 'text-orange-800 dark:text-orange-300', label: 'Disputed' },
    
    // Supplier statuses
    active: { bg: 'bg-green-100 dark:bg-green-900/30', text: 'text-green-800 dark:text-green-300', label: 'Active' },
    inactive: { bg: 'bg-gray-100 dark:bg-gray-900/30', text: 'text-gray-800 dark:text-gray-300', label: 'Inactive' },
    suspended: { bg: 'bg-red-100 dark:bg-red-900/30', text: 'text-red-800 dark:text-red-300', label: 'Suspended' },
    pending_approval: { bg: 'bg-yellow-100 dark:bg-yellow-900/30', text: 'text-yellow-800 dark:text-yellow-300', label: 'Pending Approval' },
    
    // Generic
    draft: { bg: 'bg-gray-100 dark:bg-gray-900/30', text: 'text-gray-800 dark:text-gray-300', label: 'Draft' },
    published: { bg: 'bg-green-100 dark:bg-green-900/30', text: 'text-green-800 dark:text-green-300', label: 'Published' },
}

const config = computed(() => {
    return statusConfig[props.status] || { 
        bg: 'bg-gray-100 dark:bg-gray-900/30', 
        text: 'text-gray-800 dark:text-gray-300', 
        label: props.status 
    }
})

const sizeClasses = computed(() => {
    return {
        sm: 'px-2 py-0.5 text-xs',
        md: 'px-2.5 py-1 text-sm',
        lg: 'px-3 py-1.5 text-base',
    }[props.size]
})
</script>

<template>
    <span 
        class="inline-flex items-center font-medium rounded-full"
        :class="[config.bg, config.text, sizeClasses]"
    >
        {{ config.label }}
    </span>
</template>
