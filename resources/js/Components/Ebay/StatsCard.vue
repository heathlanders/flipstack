<script setup>
const props = defineProps({
    title: {
        type: String,
        required: true
    },
    value: {
        type: [Number, String],
        required: true
    },
    subtitle: {
        type: String,
        default: ''
    },
    icon: {
        type: String,
        default: 'default'
    },
    color: {
        type: String,
        default: 'gray',
        validator: (value) => ['emerald', 'amber', 'red', 'blue', 'indigo', 'gray'].includes(value)
    }
});

const colorClasses = {
    emerald: {
        bg: 'bg-emerald-100 dark:bg-emerald-900/30',
        icon: 'bg-emerald-500',
        text: 'text-emerald-600 dark:text-emerald-400'
    },
    amber: {
        bg: 'bg-amber-100 dark:bg-amber-900/30',
        icon: 'bg-amber-500',
        text: 'text-amber-600 dark:text-amber-400'
    },
    red: {
        bg: 'bg-red-100 dark:bg-red-900/30',
        icon: 'bg-red-500',
        text: 'text-red-600 dark:text-red-400'
    },
    blue: {
        bg: 'bg-blue-100 dark:bg-blue-900/30',
        icon: 'bg-blue-500',
        text: 'text-blue-600 dark:text-blue-400'
    },
    indigo: {
        bg: 'bg-indigo-100 dark:bg-indigo-900/30',
        icon: 'bg-indigo-500',
        text: 'text-indigo-600 dark:text-indigo-400'
    },
    gray: {
        bg: 'bg-gray-100 dark:bg-gray-800',
        icon: 'bg-gray-500',
        text: 'text-gray-600 dark:text-gray-400'
    }
};

const iconPaths = {
    listings: 'M4 6h16M4 10h16M4 14h16M4 18h16',
    pending: 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z',
    error: 'M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
    success: 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z',
    sync: 'M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15',
    products: 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4',
    default: 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z'
};
</script>

<template>
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
        <div class="p-5">
            <div class="flex items-center gap-4">
                <div :class="colorClasses[color].bg" class="p-3 rounded-lg">
                    <svg :class="['w-6 h-6 text-white', colorClasses[color].icon.replace('bg-', 'text-')]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="iconPaths[icon] || iconPaths.default" />
                    </svg>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">{{ title }}</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ value }}</p>
                    <p v-if="subtitle" class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{{ subtitle }}</p>
                </div>
            </div>
        </div>
    </div>
</template>