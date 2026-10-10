<script setup>
import { computed, ref } from 'vue';

const props = defineProps({
    data: {
        type: Array,
        default: () => [],
    },
});

const colors = {
    '0-30 days': { bg: '#10B981', gradient: 'from-emerald-400 to-emerald-600', label: 'Fresh' },
    '31-60 days': { bg: '#22C55E', gradient: 'from-green-400 to-green-600', label: 'Good' },
    '61-90 days': { bg: '#F59E0B', gradient: 'from-amber-400 to-amber-600', label: 'Aging' },
    '91-180 days': { bg: '#F97316', gradient: 'from-orange-400 to-orange-600', label: 'Old' },
    '180+ days': { bg: '#EF4444', gradient: 'from-red-400 to-red-600', label: 'Critical' },
};

const chartData = computed(() => {
    if (!props.data || props.data.length === 0) return [];
    
    const totalValue = props.data.reduce((sum, item) => sum + item.total_value, 0);
    
    return props.data.map(item => ({
        ...item,
        ...colors[item.bracket],
        percentage: totalValue > 0 ? ((item.total_value / totalValue) * 100) : 0,
    }));
});

const totalValue = computed(() => {
    return props.data?.reduce((sum, item) => sum + item.total_value, 0) || 0;
});

const totalUnits = computed(() => {
    return props.data?.reduce((sum, item) => sum + item.total_units, 0) || 0;
});

const oldStockPercentage = computed(() => {
    if (!props.data || totalValue.value === 0) return 0;
    const oldStock = props.data
        .filter(item => item.bracket.includes('180') || item.bracket.includes('91'))
        .reduce((sum, item) => sum + item.total_value, 0);
    return ((oldStock / totalValue.value) * 100).toFixed(1);
});

const selectedBracket = ref(null);

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
                    d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"
                />
            </svg>
            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">No aging data available</p>
        </div>

        <div v-else>
            <!-- Stacked Bar Chart -->
            <div class="mb-6">
                <div class="flex h-8 rounded-lg overflow-hidden">
                    <div 
                        v-for="item in chartData" 
                        :key="item.bracket"
                        class="relative group cursor-pointer transition-all duration-200"
                        :class="[
                            `bg-gradient-to-b ${item.gradient}`,
                            { 'opacity-50': selectedBracket && selectedBracket !== item.bracket }
                        ]"
                        :style="{ width: `${item.percentage}%` }"
                        @mouseenter="selectedBracket = item.bracket"
                        @mouseleave="selectedBracket = null"
                    >
                        <div 
                            v-if="item.percentage > 10"
                            class="absolute inset-0 flex items-center justify-center"
                        >
                            <span class="text-xs font-semibold text-white drop-shadow-sm">
                                {{ item.percentage.toFixed(0) }}%
                            </span>
                        </div>
                        
                        <!-- Tooltip -->
                        <div 
                            class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none z-10"
                        >
                            <div class="bg-gray-900 text-white text-xs rounded-lg px-3 py-2 whitespace-nowrap shadow-lg">
                                <p class="font-semibold">{{ item.bracket }}</p>
                                <p>{{ formatCurrency(item.total_value) }}</p>
                            </div>
                            <div class="absolute top-full left-1/2 -translate-x-1/2 -mt-1">
                                <div class="border-4 border-transparent border-t-gray-900"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Legend Grid -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
                <div 
                    v-for="item in chartData" 
                    :key="item.bracket"
                    class="p-3 rounded-xl border transition-all cursor-pointer"
                    :class="[
                        selectedBracket === item.bracket 
                            ? 'border-gray-300 dark:border-gray-500 bg-gray-50 dark:bg-gray-700/50 shadow-sm' 
                            : 'border-gray-100 dark:border-gray-700 hover:border-gray-200 dark:hover:border-gray-600'
                    ]"
                    @mouseenter="selectedBracket = item.bracket"
                    @mouseleave="selectedBracket = null"
                >
                    <div class="flex items-center gap-2 mb-2">
                        <div 
                            class="w-3 h-3 rounded-full"
                            :style="{ backgroundColor: item.bg }"
                        ></div>
                        <span class="text-xs font-medium text-gray-500 dark:text-gray-400">
                            {{ item.bracket }}
                        </span>
                    </div>
                    <p class="text-lg font-bold text-gray-900 dark:text-white">
                        {{ formatCurrency(item.total_value) }}
                    </p>
                    <div class="flex items-center justify-between mt-1">
                        <span class="text-xs text-gray-500 dark:text-gray-400">
                            {{ formatNumber(item.total_units) }} units
                        </span>
                        <span 
                            class="text-xs font-medium px-1.5 py-0.5 rounded"
                            :class="item.bracket.includes('180') || item.bracket.includes('91') 
                                ? 'bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400'
                                : 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-400'"
                        >
                            {{ item.label }}
                        </span>
                    </div>
                </div>
            </div>

            <!-- Summary -->
            <div 
                v-if="parseFloat(oldStockPercentage) > 20"
                class="mt-4 p-4 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-xl"
            >
                <div class="flex items-start gap-3">
                    <svg class="w-5 h-5 text-amber-600 dark:text-amber-400 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                    <div>
                        <h4 class="text-sm font-semibold text-amber-800 dark:text-amber-200">
                            High Aging Inventory Alert
                        </h4>
                        <p class="mt-1 text-sm text-amber-700 dark:text-amber-300">
                            {{ oldStockPercentage }}% of your inventory value is over 90 days old. 
                            Consider running promotions or clearance sales to reduce aging stock.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
