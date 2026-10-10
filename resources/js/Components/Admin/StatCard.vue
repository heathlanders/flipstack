<script setup>
defineProps({
    title: String,
    value: [String, Number],
    icon: String,
    trend: String,
    trendUp: Boolean,
    color: {
        type: String,
        default: 'blue'
    }
})

const colorClasses = {
    blue: 'bg-blue-50 text-blue-600 dark:bg-blue-900/20 dark:text-blue-400',
    green: 'bg-green-50 text-green-600 dark:bg-green-900/20 dark:text-green-400',
    yellow: 'bg-yellow-50 text-yellow-600 dark:bg-yellow-900/20 dark:text-yellow-400',
    red: 'bg-red-50 text-red-600 dark:bg-red-900/20 dark:text-red-400',
    purple: 'bg-purple-50 text-purple-600 dark:bg-purple-900/20 dark:text-purple-400',
}
</script>

<template>
    <div class="bg-white dark:bg-slate-800 rounded-lg shadow p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ title }}</p>
                <p class="text-2xl font-semibold text-gray-900 dark:text-white mt-1">{{ value }}</p>
                <p v-if="trend" class="text-sm mt-1" :class="trendUp ? 'text-green-600' : 'text-red-600'">
                    <span v-if="trendUp">↑</span>
                    <span v-else>↓</span>
                    {{ trend }}
                </p>
            </div>
            <div v-if="icon" class="p-3 rounded-full" :class="colorClasses[color]">
                <component :is="icon" class="w-6 h-6" />
            </div>
        </div>
    </div>
</template>
