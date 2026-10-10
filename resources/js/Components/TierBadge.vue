<script setup>
import { computed } from 'vue'

const props = defineProps({
    tier: { type: String, required: true },
    size: { type: String, default: 'md' }
})

const tierConfig = {
    platinum: { bg: 'bg-purple-100 dark:bg-purple-900/30', text: 'text-purple-800 dark:text-purple-300', label: 'Platinum' },
    gold: { bg: 'bg-yellow-100 dark:bg-yellow-900/30', text: 'text-yellow-800 dark:text-yellow-300', label: 'Gold' },
    silver: { bg: 'bg-gray-200 dark:bg-gray-700', text: 'text-gray-800 dark:text-gray-300', label: 'Silver' },
    bronze: { bg: 'bg-orange-100 dark:bg-orange-900/30', text: 'text-orange-800 dark:text-orange-300', label: 'Bronze' },
    probation: { bg: 'bg-red-100 dark:bg-red-900/30', text: 'text-red-800 dark:text-red-300', label: 'Probation' },
}

const config = computed(() => tierConfig[props.tier?.toLowerCase()] || tierConfig.bronze)
const sizeClasses = computed(() => ({ sm: 'px-2 py-0.5 text-xs', md: 'px-2.5 py-1 text-sm', lg: 'px-3 py-1.5 text-base' }[props.size]))
</script>

<template>
    <span class="inline-flex items-center font-medium rounded-full" :class="[config.bg, config.text, sizeClasses]">
        {{ config.label }}
    </span>
</template>
