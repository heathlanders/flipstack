<script setup>
const props = defineProps({
    direction: {
        type: String,
        default: null,
        validator: (value) => [null, 'asc', 'desc'].includes(value)
    }
})
</script>

<template>
    <span class="inline-flex flex-col ml-1">
        <svg 
            class="w-3 h-3 -mb-1" 
            :class="direction === 'asc' ? 'text-blue-600' : 'text-gray-400'"
            fill="currentColor" 
            viewBox="0 0 20 20"
        >
            <path d="M5 12l5-5 5 5H5z"/>
        </svg>
        <svg 
            class="w-3 h-3" 
            :class="direction === 'desc' ? 'text-blue-600' : 'text-gray-400'"
            fill="currentColor" 
            viewBox="0 0 20 20"
        >
            <path d="M5 8l5 5 5-5H5z"/>
        </svg>
    </span>
</template>
