<script setup>
import { computed } from 'vue';

const props = defineProps({
    title: {
        type: String,
        required: true,
    },
    value: {
        type: [String, Number],
        required: true,
    },
    subtitle: {
        type: String,
        default: null,
    },
    change: {
        type: Number,
        default: null,
    },
    trend: {
        type: String,
        default: 'up',
        validator: (value) => ['up', 'down', 'neutral'].includes(value),
    },
    icon: {
        type: String,
        default: 'chart-bar',
    },
    color: {
        type: String,
        default: 'blue',
        validator: (value) => ['blue', 'emerald', 'amber', 'red', 'violet', 'slate', 'gray', 'indigo', 'cyan', 'pink'].includes(value),
    },
    alertLevel: {
        type: String,
        default: 'normal',
        validator: (value) => ['normal', 'warning', 'critical'].includes(value),
    },
});

const colorClasses = computed(() => {
    const colors = {
        blue: {
            bg: 'bg-blue-50 dark:bg-blue-900/20',
            icon: 'text-blue-600 dark:text-blue-400',
            border: 'border-blue-100 dark:border-blue-800',
            accent: 'bg-blue-500',
        },
        emerald: {
            bg: 'bg-emerald-50 dark:bg-emerald-900/20',
            icon: 'text-emerald-600 dark:text-emerald-400',
            border: 'border-emerald-100 dark:border-emerald-800',
            accent: 'bg-emerald-500',
        },
        amber: {
            bg: 'bg-amber-50 dark:bg-amber-900/20',
            icon: 'text-amber-600 dark:text-amber-400',
            border: 'border-amber-100 dark:border-amber-800',
            accent: 'bg-amber-500',
        },
        red: {
            bg: 'bg-red-50 dark:bg-red-900/20',
            icon: 'text-red-600 dark:text-red-400',
            border: 'border-red-100 dark:border-red-800',
            accent: 'bg-red-500',
        },
        violet: {
            bg: 'bg-violet-50 dark:bg-violet-900/20',
            icon: 'text-violet-600 dark:text-violet-400',
            border: 'border-violet-100 dark:border-violet-800',
            accent: 'bg-violet-500',
        },
        slate: {
            bg: 'bg-slate-50 dark:bg-slate-900/20',
            icon: 'text-slate-600 dark:text-slate-400',
            border: 'border-slate-100 dark:border-slate-800',
            accent: 'bg-slate-500',
        },
        gray: {
            bg: 'bg-gray-50 dark:bg-gray-900/20',
            icon: 'text-gray-600 dark:text-gray-400',
            border: 'border-gray-100 dark:border-gray-800',
            accent: 'bg-gray-500',
        },
        indigo: {
            bg: 'bg-indigo-50 dark:bg-indigo-900/20',
            icon: 'text-indigo-600 dark:text-indigo-400',
            border: 'border-indigo-100 dark:border-indigo-800',
            accent: 'bg-indigo-500',
        },
        cyan: {
            bg: 'bg-cyan-50 dark:bg-cyan-900/20',
            icon: 'text-cyan-600 dark:text-cyan-400',
            border: 'border-cyan-100 dark:border-cyan-800',
            accent: 'bg-cyan-500',
        },
        pink: {
            bg: 'bg-pink-50 dark:bg-pink-900/20',
            icon: 'text-pink-600 dark:text-pink-400',
            border: 'border-pink-100 dark:border-pink-800',
            accent: 'bg-pink-500',
        },
    };
    return colors[props.color] || colors.blue;
});

const alertClasses = computed(() => {
    if (props.alertLevel === 'critical') {
        return 'ring-2 ring-red-500/50 animate-pulse-subtle';
    }
    if (props.alertLevel === 'warning') {
        return 'ring-2 ring-amber-500/50';
    }
    return '';
});

const trendColor = computed(() => {
    if (props.trend === 'up') return 'text-emerald-600 dark:text-emerald-400';
    if (props.trend === 'down') return 'text-red-600 dark:text-red-400';
    return 'text-gray-500 dark:text-gray-400';
});

const icons = {
    'currency-dollar': `<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />`,
    'cube': `<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />`,
    'exclamation-triangle': `<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />`,
    'x-circle': `<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />`,
    'trending-up': `<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />`,
    'archive': `<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4" />`,
    'chart-bar': `<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />`,
};
</script>

<template>
    <div 
        class="relative overflow-hidden rounded-2xl bg-white dark:bg-gray-800 border transition-all duration-200 hover:shadow-lg"
        :class="[colorClasses.border, alertClasses]"
    >
        <!-- Accent bar -->
        <div 
            class="absolute top-0 left-0 right-0 h-1"
            :class="colorClasses.accent"
        ></div>

        <div class="p-5">
            <div class="flex items-start justify-between">
                <div class="flex-1 min-w-0">
                    <p class="text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400 truncate">
                        {{ title }}
                    </p>
                    <p class="mt-2 text-2xl font-bold text-gray-900 dark:text-white tracking-tight">
                        {{ value }}
                    </p>
                    <p v-if="subtitle" class="mt-1 text-xs text-gray-500 dark:text-gray-400 truncate">
                        {{ subtitle }}
                    </p>
                </div>
                <div 
                    class="flex-shrink-0 p-2.5 rounded-xl"
                    :class="colorClasses.bg"
                >
                    <svg 
                        class="w-5 h-5"
                        :class="colorClasses.icon"
                        fill="none" 
                        stroke="currentColor" 
                        viewBox="0 0 24 24"
                        v-html="icons[icon] || icons['chart-bar']"
                    />
                </div>
            </div>

            <!-- Change indicator -->
            <div v-if="change !== null" class="mt-3 flex items-center gap-1.5">
                <span 
                    class="inline-flex items-center text-xs font-medium"
                    :class="trendColor"
                >
                    <svg 
                        v-if="trend === 'up'"
                        class="w-3.5 h-3.5 mr-0.5"
                        fill="none" 
                        stroke="currentColor" 
                        viewBox="0 0 24 24"
                    >
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18" />
                    </svg>
                    <svg 
                        v-else-if="trend === 'down'"
                        class="w-3.5 h-3.5 mr-0.5"
                        fill="none" 
                        stroke="currentColor" 
                        viewBox="0 0 24 24"
                    >
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3" />
                    </svg>
                    {{ Math.abs(change) }}%
                </span>
                <span class="text-xs text-gray-400 dark:text-gray-500">vs last period</span>
            </div>
        </div>
    </div>
</template>

<style scoped>
@keyframes pulse-subtle {
    0%, 100% {
        opacity: 1;
    }
    50% {
        opacity: 0.85;
    }
}

.animate-pulse-subtle {
    animation: pulse-subtle 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
}
</style>
