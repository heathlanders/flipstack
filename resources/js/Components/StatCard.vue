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
</script>

<template>
    <div class="bg-white dark:bg-slate-800 rounded-lg shadow p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ title }}</p>
                <p class="text-2xl font-semibold text-gray-900 dark:text-white mt-1">{{ value }}</p>
                <p v-if="trend" class="text-sm mt-1" :class="trendUp ? 'text-green-600' : 'text-red-600'">
                    <span v-if="trendUp">↑</span><span v-else>↓</span> {{ trend }}
                </p>
            </div>
        </div>
    </div>
</template>
