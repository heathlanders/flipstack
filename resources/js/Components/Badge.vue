<script setup>
import { computed } from 'vue'
const props = defineProps({
    color: { type: String, default: 'gray' },
    size: { type: String, default: 'md' }
})
const colorClasses = {
    gray: 'bg-gray-100 text-gray-800 dark:bg-gray-500/10 dark:text-gray-300',
    red: 'bg-red-100 text-red-800 dark:bg-red-500/10 dark:text-red-300',
    yellow: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-500/10 dark:text-yellow-300',
    green: 'bg-green-100 text-green-800 dark:bg-green-500/10 dark:text-green-300',
    blue: 'bg-blue-100 text-blue-800 dark:bg-blue-500/10 dark:text-blue-300',
    purple: 'bg-purple-100 text-purple-800 dark:bg-purple-500/10 dark:text-purple-300',
}
const sizeClasses = { sm: 'px-2 py-0.5 text-xs', md: 'px-2.5 py-1 text-sm', lg: 'px-3 py-1.5 text-base' }
</script>

<template>
    <span class="inline-flex items-center font-medium rounded-full" :class="[colorClasses[color], sizeClasses[size]]"><slot /></span>
</template>
