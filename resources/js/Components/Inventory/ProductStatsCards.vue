<script setup>
import { computed } from 'vue';

const props = defineProps({
    stats: {
        type: Object,
        default: () => ({
            total: 0,
            active: 0,
            in_stock: 0,
            low_stock: 0,
            out_of_stock: 0,
            total_value: 0,
        }),
    },
});

const formattedTotalValue = computed(() => {
    const value = props.stats.total_value || 0;
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
    }).format(value);
});

const cards = computed(() => [
    {
        label: 'Total Products',
        value: props.stats.total || 0,
        icon: 'cube',
        color: 'indigo',
    },
    {
        label: 'Active Products',
        value: props.stats.active || 0,
        icon: 'check-circle',
        color: 'green',
    },
    {
        label: 'Low Stock',
        value: props.stats.low_stock || 0,
        icon: 'exclamation',
        color: 'yellow',
        alert: (props.stats.low_stock || 0) > 0,
    },
    {
        label: 'Out of Stock',
        value: props.stats.out_of_stock || 0,
        icon: 'x-circle',
        color: 'red',
        alert: (props.stats.out_of_stock || 0) > 0,
    },
    {
        label: 'Inventory Value',
        value: formattedTotalValue.value,
        icon: 'currency-dollar',
        color: 'blue',
        isFormatted: true,
    },
]);

const getColorClasses = (color) => {
    const colors = {
        indigo: {
            bg: 'bg-indigo-50 dark:bg-indigo-900/20',
            icon: 'text-indigo-600 dark:text-indigo-400',
            text: 'text-indigo-900 dark:text-indigo-100',
        },
        green: {
            bg: 'bg-green-50 dark:bg-green-900/20',
            icon: 'text-green-600 dark:text-green-400',
            text: 'text-green-900 dark:text-green-100',
        },
        yellow: {
            bg: 'bg-yellow-50 dark:bg-yellow-900/20',
            icon: 'text-yellow-600 dark:text-yellow-400',
            text: 'text-yellow-900 dark:text-yellow-100',
        },
        red: {
            bg: 'bg-red-50 dark:bg-red-900/20',
            icon: 'text-red-600 dark:text-red-400',
            text: 'text-red-900 dark:text-red-100',
        },
        blue: {
            bg: 'bg-blue-50 dark:bg-blue-900/20',
            icon: 'text-blue-600 dark:text-blue-400',
            text: 'text-blue-900 dark:text-blue-100',
        },
    };
    return colors[color] || colors.indigo;
};
</script>

<template>
    <div class="grid grid-cols-2 gap-4 md:grid-cols-3 lg:grid-cols-5">
        <div
            v-for="card in cards"
            :key="card.label"
            class="relative p-4 overflow-hidden bg-white rounded-lg shadow-sm dark:bg-gray-800"
            :class="{ 'ring-2 ring-red-400 dark:ring-red-600': card.alert }"
        >
            <div class="flex items-start justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">
                        {{ card.label }}
                    </p>
                    <p class="mt-1 text-2xl font-semibold" :class="getColorClasses(card.color).text">
                        {{ card.isFormatted ? card.value : card.value.toLocaleString() }}
                    </p>
                </div>
                <div
                    class="flex items-center justify-center w-10 h-10 rounded-lg"
                    :class="getColorClasses(card.color).bg"
                >
                    <!-- Cube Icon -->
                    <svg v-if="card.icon === 'cube'" class="w-5 h-5" :class="getColorClasses(card.color).icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                    </svg>
                    
                    <!-- Check Circle Icon -->
                    <svg v-else-if="card.icon === 'check-circle'" class="w-5 h-5" :class="getColorClasses(card.color).icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    
                    <!-- Exclamation Icon -->
                    <svg v-else-if="card.icon === 'exclamation'" class="w-5 h-5" :class="getColorClasses(card.color).icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                    
                    <!-- X Circle Icon -->
                    <svg v-else-if="card.icon === 'x-circle'" class="w-5 h-5" :class="getColorClasses(card.color).icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    
                    <!-- Currency Dollar Icon -->
                    <svg v-else-if="card.icon === 'currency-dollar'" class="w-5 h-5" :class="getColorClasses(card.color).icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
            </div>
            
            <!-- Alert indicator -->
            <div v-if="card.alert" class="absolute top-0 right-0 w-3 h-3">
                <span class="absolute inline-flex w-full h-full bg-red-400 rounded-full opacity-75 animate-ping"></span>
                <span class="relative inline-flex w-3 h-3 bg-red-500 rounded-full"></span>
            </div>
        </div>
    </div>
</template>
