<script setup>
defineProps({
    title: String,
    value: [String, Number],
    change: {
        type: Number,
        default: null
    },
    trend: {
        type: String,
        default: 'neutral' // 'up', 'down', 'neutral'
    },
    icon: String,
    color: {
        type: String,
        default: 'blue'
    },
    subtitle: {
        type: String,
        default: null
    },
    loading: {
        type: Boolean,
        default: false
    }
})

const formatChange = (change) => {
    if (change === null) return ''
    const sign = change > 0 ? '+' : ''
    return `${sign}${change.toFixed(1)}%`
}
</script>

<template>
    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 p-6 hover:shadow-md transition-all duration-200 group">
        <!-- Loading state -->
        <div v-if="loading" class="animate-pulse">
            <div class="flex items-center justify-between">
                <div>
                    <div class="h-4 bg-gray-200 rounded w-20 mb-2"></div>
                    <div class="h-8 bg-gray-200 rounded w-16"></div>
                </div>
                <div class="h-12 w-12 bg-gray-200 rounded-xl"></div>
            </div>
        </div>
        
        <!-- Content -->
        <div v-else class="flex items-center justify-between">
            <div class="flex-1">
                <p class="text-sm font-medium text-gray-600 dark:text-gray-400">{{ title }}</p>
                <div class="flex items-baseline mt-2">
                    <p class="text-3xl font-bold text-gray-900 dark:text-white">{{ value }}</p>
                    <div v-if="change !== null" class="ml-3 flex items-center">
                        <span 
                            :class="{
                                'text-emerald-600': trend === 'up',
                                'text-red-600': trend === 'down', 
                                'text-gray-500': trend === 'neutral'
                            }"
                            class="text-sm font-semibold flex items-center"
                        >
                            <i 
                                v-if="trend === 'up'" 
                                class="fas fa-arrow-up mr-1"
                            ></i>
                            <i 
                                v-else-if="trend === 'down'" 
                                class="fas fa-arrow-down mr-1"
                            ></i>
                            {{ formatChange(change) }}
                        </span>
                    </div>
                </div>
                <p v-if="subtitle" class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ subtitle }}</p>
            </div>
            
            <!-- Icon -->
            <div 
                :class="`bg-${color}-100 dark:bg-${color}-900/20`"
                class="rounded-xl p-3 group-hover:scale-110 transition-transform duration-200"
            >
                <i 
                    :class="[icon, `text-${color}-600`]" 
                    class="text-xl"
                ></i>
            </div>
        </div>
    </div>
</template>