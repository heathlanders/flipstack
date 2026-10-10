<script setup>
import { computed, ref } from 'vue';

const props = defineProps({
    data: {
        type: Array,
        default: () => [],
    },
});

const colors = [
    '#6366F1', // Indigo
    '#8B5CF6', // Violet
    '#EC4899', // Pink
    '#F43F5E', // Rose
    '#F97316', // Orange
    '#EAB308', // Yellow
    '#22C55E', // Green
    '#14B8A6', // Teal
    '#06B6D4', // Cyan
    '#3B82F6', // Blue
];

const chartData = computed(() => {
    if (!props.data || props.data.length === 0) return [];
    
    const totalValue = props.data.reduce((sum, item) => sum + item.total_value, 0);
    
    return props.data.slice(0, 10).map((item, index) => ({
        ...item,
        color: colors[index % colors.length],
        percentage: totalValue > 0 ? ((item.total_value / totalValue) * 100).toFixed(1) : 0,
    }));
});

const maxValue = computed(() => {
    if (chartData.value.length === 0) return 0;
    return Math.max(...chartData.value.map(item => item.total_value));
});

const hoveredItem = ref(null);

function formatCurrency(amount) {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
    }).format(amount);
}

function formatNumber(num) {
    return new Intl.NumberFormat('en-US').format(num);
}
</script>

<template>
    <div>
        <!-- Empty State -->
        <div 
            v-if="!chartData || chartData.length === 0"
            class="text-center py-8"
        >
            <svg 
                class="mx-auto h-10 w-10 text-gray-400 dark:text-gray-500"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
            >
                <path 
                    stroke-linecap="round" 
                    stroke-linejoin="round" 
                    stroke-width="2" 
                    d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"
                />
            </svg>
            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">No category data available</p>
        </div>

        <!-- Bar Chart -->
        <div v-else class="space-y-3">
            <div 
                v-for="item in chartData" 
                :key="item.name"
                class="group cursor-pointer"
                @mouseenter="hoveredItem = item"
                @mouseleave="hoveredItem = null"
            >
                <div class="flex items-center justify-between mb-1">
                    <div class="flex items-center gap-2 min-w-0 flex-1">
                        <div 
                            class="w-2.5 h-2.5 rounded-full flex-shrink-0"
                            :style="{ backgroundColor: item.color }"
                        ></div>
                        <span 
                            class="text-sm font-medium text-gray-700 dark:text-gray-300 truncate"
                            :class="{ 'text-gray-900 dark:text-white': hoveredItem?.name === item.name }"
                        >
                            {{ item.name }}
                        </span>
                    </div>
                    <div class="flex items-center gap-3 flex-shrink-0">
                        <span class="text-xs text-gray-500 dark:text-gray-400">
                            {{ item.product_count }} products
                        </span>
                        <span class="text-sm font-semibold text-gray-900 dark:text-white min-w-[80px] text-right">
                            {{ formatCurrency(item.total_value) }}
                        </span>
                    </div>
                </div>
                <div class="relative h-2 bg-gray-100 dark:bg-gray-700 rounded-full overflow-hidden">
                    <div 
                        class="absolute inset-y-0 left-0 rounded-full transition-all duration-300"
                        :style="{ 
                            width: `${(item.total_value / maxValue) * 100}%`,
                            backgroundColor: item.color,
                        }"
                        :class="{ 'opacity-70': hoveredItem && hoveredItem.name !== item.name }"
                    ></div>
                </div>
            </div>
        </div>

        <!-- Tooltip Card -->
        <Transition
            enter-active-class="transition ease-out duration-200"
            enter-from-class="opacity-0 translate-y-2"
            enter-to-class="opacity-100 translate-y-0"
            leave-active-class="transition ease-in duration-150"
            leave-from-class="opacity-100 translate-y-0"
            leave-to-class="opacity-0 translate-y-2"
        >
            <div 
                v-if="hoveredItem"
                class="mt-4 p-4 bg-gray-50 dark:bg-gray-700/50 rounded-xl border border-gray-100 dark:border-gray-600"
            >
                <div class="flex items-center gap-3 mb-3">
                    <div 
                        class="w-4 h-4 rounded-full"
                        :style="{ backgroundColor: hoveredItem.color }"
                    ></div>
                    <h4 class="text-sm font-semibold text-gray-900 dark:text-white">
                        {{ hoveredItem.name }}
                    </h4>
                </div>
                <div class="grid grid-cols-3 gap-4 text-center">
                    <div>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Products</p>
                        <p class="text-lg font-bold text-gray-900 dark:text-white">
                            {{ formatNumber(hoveredItem.product_count) }}
                        </p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Total Stock</p>
                        <p class="text-lg font-bold text-gray-900 dark:text-white">
                            {{ formatNumber(hoveredItem.total_stock) }}
                        </p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 dark:text-gray-400">% of Value</p>
                        <p class="text-lg font-bold text-gray-900 dark:text-white">
                            {{ hoveredItem.percentage }}%
                        </p>
                    </div>
                </div>
            </div>
        </Transition>
    </div>
</template>
