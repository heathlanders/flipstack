<script setup>
const props = defineProps({
    label: String,
    value: [String, Number],
    trend: {
        type: Object,
        default: null, // { value: number, direction: 'up'|'down'|'neutral' }
    },
    icon: String,
    color: {
        type: String,
        default: 'blue',
    },
    loading: {
        type: Boolean,
        default: false,
    },
})

const formatTrend = (val) => {
    if (val === null || val === undefined) return ''
    const sign = val > 0 ? '+' : ''
    return `${sign}${Number(val).toFixed(1)}%`
}

const trendColor = (direction) => {
    if (direction === 'up') return 'text-emerald-600 dark:text-emerald-400'
    if (direction === 'down') return 'text-red-600 dark:text-red-400'
    return 'text-gray-500 dark:text-gray-400'
}
</script>

<template>
    <div
        class="bg-white dark:bg-gray-900 border border-gray-200/60 dark:border-gray-800 shadow-sm rounded-xl overflow-hidden"
    >
        <div class="flex">
            <!-- Content -->
            <div class="flex-1 p-5">
                <!-- Skeleton loading state -->
                <div v-if="loading" class="animate-pulse">
                    <div class="h-3 bg-gray-200 dark:bg-gray-700 rounded w-20 mb-3"></div>
                    <div class="h-7 bg-gray-200 dark:bg-gray-700 rounded w-24 mb-2"></div>
                    <div class="h-3 bg-gray-200 dark:bg-gray-700 rounded w-16"></div>
                </div>

                <div v-else>
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ label }}</p>
                    <div class="flex items-baseline mt-1 gap-2">
                        <p class="text-2xl font-bold text-gray-900 dark:text-white tabular-nums truncate">{{ value }}</p>
                        <span
                            v-if="trend"
                            :class="trendColor(trend.direction)"
                            class="text-xs font-medium inline-flex items-center whitespace-nowrap"
                        >
                            <svg v-if="trend.direction === 'up'" class="w-3 h-3 mr-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7" />
                            </svg>
                            <svg v-else-if="trend.direction === 'down'" class="w-3 h-3 mr-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                            </svg>
                            {{ formatTrend(trend.value) }}
                        </span>
                    </div>
                </div>
            </div>

            <!-- Icon block -->
            <div :class="`bg-${color}-50 dark:bg-${color}-900/20`" class="flex items-center justify-center w-16 flex-shrink-0">
                <svg
                    v-if="icon === 'dollar'"
                    class="w-7 h-7" :class="`text-${color}-600 dark:text-${color}-400`"
                    fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"
                >
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818l.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <svg
                    v-else-if="icon === 'cube'"
                    class="w-7 h-7" :class="`text-${color}-600 dark:text-${color}-400`"
                    fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"
                >
                    <path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                </svg>
                <svg
                    v-else-if="icon === 'exclamation'"
                    class="w-7 h-7" :class="`text-${color}-600 dark:text-${color}-400`"
                    fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"
                >
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                </svg>
                <svg
                    v-else-if="icon === 'inbox'"
                    class="w-7 h-7" :class="`text-${color}-600 dark:text-${color}-400`"
                    fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"
                >
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 13.5h3.86a2.25 2.25 0 012.012 1.244l.256.512a2.25 2.25 0 002.013 1.244h3.218a2.25 2.25 0 002.013-1.244l.256-.512a2.25 2.25 0 012.013-1.244h3.859m-19.5.338V18a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18v-4.162c0-.224-.034-.447-.1-.661L19.24 5.338a2.25 2.25 0 00-2.15-1.588H6.911a2.25 2.25 0 00-2.15 1.588L2.35 12.677a2.25 2.25 0 00-.1.661z" />
                </svg>
                <slot v-else name="icon" />
            </div>
        </div>
    </div>
</template>
