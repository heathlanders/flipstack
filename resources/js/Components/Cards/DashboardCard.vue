<script setup>
import { computed } from 'vue';

const props = defineProps({
    title: {
        type: String,
        required: true
    },
    value: {
        type: [String, Number],
        required: true
    },
    subtitle: String,
    icon: String,
    color: {
        type: String,
        default: 'blue'
    },
    trend: {
        type: String,
        validator: (value) => ['up', 'down', 'neutral'].includes(value)
    },
    trendValue: String,
    clickable: Boolean
});

const emit = defineEmits(['click']);

// Color mapping for consistent theming
const colorClasses = computed(() => {
    const colorMap = {
        blue: {
            bg: 'bg-blue-500',
            icon: 'text-blue-600',
            iconBg: 'bg-blue-100 dark:bg-blue-900/30'
        },
        emerald: {
            bg: 'bg-emerald-500',
            icon: 'text-emerald-600',
            iconBg: 'bg-emerald-100 dark:bg-emerald-900/30'
        },
        purple: {
            bg: 'bg-purple-500',
            icon: 'text-purple-600',
            iconBg: 'bg-purple-100 dark:bg-purple-900/30'
        },
        orange: {
            bg: 'bg-orange-500',
            icon: 'text-orange-600',
            iconBg: 'bg-orange-100 dark:bg-orange-900/30'
        },
        red: {
            bg: 'bg-red-500',
            icon: 'text-red-600',
            iconBg: 'bg-red-100 dark:bg-red-900/30'
        },
        green: {
            bg: 'bg-green-500',
            icon: 'text-green-600',
            iconBg: 'bg-green-100 dark:bg-green-900/30'
        },
        yellow: {
            bg: 'bg-yellow-500',
            icon: 'text-yellow-600',
            iconBg: 'bg-yellow-100 dark:bg-yellow-900/30'
        },
        pink: {
            bg: 'bg-pink-500',
            icon: 'text-pink-600',
            iconBg: 'bg-pink-100 dark:bg-pink-900/30'
        },
        gray: {
            bg: 'bg-gray-500',
            icon: 'text-gray-600',
            iconBg: 'bg-gray-100 dark:bg-gray-900/30'
        }
    };
    
    return colorMap[props.color] || colorMap.blue;
});

const trendClasses = computed(() => {
    if (!props.trend) return '';
    
    switch (props.trend) {
        case 'up':
            return 'text-emerald-600 dark:text-emerald-400';
        case 'down':
            return 'text-red-600 dark:text-red-400';
        default:
            return 'text-gray-600 dark:text-gray-400';
    }
});

const trendIcon = computed(() => {
    if (!props.trend) return '';
    
    switch (props.trend) {
        case 'up':
            return 'fas fa-arrow-up';
        case 'down':
            return 'fas fa-arrow-down';
        default:
            return 'fas fa-minus';
    }
});

const handleClick = () => {
    if (props.clickable) {
        emit('click');
    }
};
</script>

<template>
    <div 
        :class="[
            'bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 p-6 transition-all duration-200',
            clickable ? 'cursor-pointer hover:shadow-md hover:scale-[1.02]' : ''
        ]"
        @click="handleClick"
    >
        <div class="flex items-center justify-between">
            <!-- Content -->
            <div class="flex-1">
                <!-- Title -->
                <h3 class="text-sm font-medium text-gray-600 dark:text-gray-400 uppercase tracking-wider">
                    {{ title }}
                </h3>
                
                <!-- Value -->
                <div class="mt-2 flex items-baseline space-x-2">
                    <span class="text-3xl font-bold text-gray-900 dark:text-white">
                        {{ value }}
                    </span>
                    
                    <!-- Trend indicator -->
                    <div v-if="trend && trendValue" :class="['flex items-center space-x-1 text-sm', trendClasses]">
                        <i :class="trendIcon"></i>
                        <span>{{ trendValue }}</span>
                    </div>
                </div>
                
                <!-- Subtitle -->
                <p v-if="subtitle" class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    {{ subtitle }}
                </p>
            </div>
            
            <!-- Icon -->
            <div v-if="icon" :class="['w-12 h-12 rounded-xl flex items-center justify-center flex-shrink-0', colorClasses.iconBg]">
                <i :class="[icon, 'text-xl', colorClasses.icon]"></i>
            </div>
        </div>
    </div>
</template>