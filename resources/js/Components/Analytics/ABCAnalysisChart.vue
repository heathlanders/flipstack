<script setup>
import { computed, ref } from 'vue';

const props = defineProps({
    data: {
        type: Object,
        default: () => ({ summary: {}, products: [] }),
    },
    metric: {
        type: String,
        default: 'revenue',
    },
});

const classColors = {
    A: {
        bg: '#10B981',
        gradient: 'from-emerald-500 to-emerald-600',
        light: 'bg-emerald-100 dark:bg-emerald-900/30',
        text: 'text-emerald-700 dark:text-emerald-400',
        border: 'border-emerald-200 dark:border-emerald-800',
    },
    B: {
        bg: '#F59E0B',
        gradient: 'from-amber-500 to-amber-600',
        light: 'bg-amber-100 dark:bg-amber-900/30',
        text: 'text-amber-700 dark:text-amber-400',
        border: 'border-amber-200 dark:border-amber-800',
    },
    C: {
        bg: '#6B7280',
        gradient: 'from-gray-500 to-gray-600',
        light: 'bg-gray-100 dark:bg-gray-800',
        text: 'text-gray-700 dark:text-gray-400',
        border: 'border-gray-200 dark:border-gray-700',
    },
};

const selectedClass = ref(null);

const chartData = computed(() => {
    if (!props.data?.summary) return [];
    
    return Object.entries(props.data.summary).map(([cls, data]) => ({
        class: cls,
        ...data,
        ...classColors[cls],
    }));
});

const totalProducts = computed(() => {
    return chartData.value.reduce((sum, item) => sum + (item.product_count || 0), 0);
});

const totalValue = computed(() => {
    return chartData.value.reduce((sum, item) => sum + (item.total_value || 0), 0);
});

const paretoChartData = computed(() => {
    if (!props.data?.products) return [];
    
    const sorted = [...props.data.products].sort((a, b) => b.value - a.value);
    let cumulative = 0;
    const total = sorted.reduce((sum, p) => sum + p.value, 0);
    
    return sorted.slice(0, 50).map((product, index) => {
        cumulative += product.value;
        return {
            ...product,
            cumulativePercentage: total > 0 ? (cumulative / total) * 100 : 0,
            index: index + 1,
        };
    });
});

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

function formatPercent(num) {
    return `${num.toFixed(1)}%`;
}

const metricLabels = {
    revenue: 'Revenue',
    sales_volume: 'Sales Volume',
    inventory_value: 'Inventory Value',
};
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
                    d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"
                />
            </svg>
            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">No ABC analysis data available</p>
        </div>

        <div v-else>
            <!-- Header with Metric -->
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">
                    Classification by {{ metricLabels[metric] || metric }}
                </h3>
                <div class="text-xs text-gray-400 dark:text-gray-500">
                    {{ formatNumber(totalProducts) }} products analyzed
                </div>
            </div>

            <!-- Summary Cards -->
            <div class="grid grid-cols-3 gap-4 mb-6">
                <div 
                    v-for="item in chartData" 
                    :key="item.class"
                    class="relative p-4 rounded-2xl border transition-all cursor-pointer hover:shadow-md"
                    :class="[
                        item.light,
                        item.border,
                        { 'ring-2 ring-offset-2 dark:ring-offset-gray-900': selectedClass === item.class }
                    ]"
                    :style="selectedClass === item.class ? { '--tw-ring-color': item.bg } : {}"
                    @mouseenter="selectedClass = item.class"
                    @mouseleave="selectedClass = null"
                >
                    <!-- Class Badge -->
                    <div 
                        class="absolute -top-3 left-4 w-8 h-8 rounded-full flex items-center justify-center text-white font-bold text-lg shadow-lg"
                        :class="`bg-gradient-to-br ${item.gradient}`"
                    >
                        {{ item.class }}
                    </div>

                    <div class="mt-4">
                        <p class="text-2xl font-bold text-gray-900 dark:text-white">
                            {{ formatPercent(item.value_percentage || 0) }}
                        </p>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                            of total {{ metricLabels[metric]?.toLowerCase() || 'value' }}
                        </p>
                    </div>

                    <div class="mt-4 pt-4 border-t" :class="item.border">
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-500 dark:text-gray-400">Products</span>
                            <span class="font-semibold" :class="item.text">
                                {{ formatNumber(item.product_count || 0) }}
                            </span>
                        </div>
                        <div class="flex justify-between text-sm mt-1">
                            <span class="text-gray-500 dark:text-gray-400">Value</span>
                            <span class="font-semibold" :class="item.text">
                                {{ formatCurrency(item.total_value || 0) }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Value Distribution Bar -->
            <div class="mb-6">
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400 mb-2">Value Distribution</p>
                <div class="flex h-4 rounded-full overflow-hidden bg-gray-200 dark:bg-gray-700">
                    <div 
                        v-for="item in chartData" 
                        :key="item.class"
                        class="relative group transition-all duration-300"
                        :class="`bg-gradient-to-r ${item.gradient}`"
                        :style="{ width: `${item.value_percentage || 0}%` }"
                    >
                        <div 
                            class="absolute inset-0 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity"
                        >
                            <span class="text-[10px] font-bold text-white">
                                {{ item.class }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Product Distribution Bar -->
            <div class="mb-6">
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400 mb-2">Product Distribution</p>
                <div class="flex h-4 rounded-full overflow-hidden bg-gray-200 dark:bg-gray-700">
                    <div 
                        v-for="item in chartData" 
                        :key="item.class"
                        class="relative group transition-all duration-300"
                        :class="`bg-gradient-to-r ${item.gradient}`"
                        :style="{ width: `${item.product_percentage || 0}%` }"
                    >
                        <div 
                            class="absolute inset-0 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity"
                        >
                            <span class="text-[10px] font-bold text-white">
                                {{ item.class }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Pareto Curve (simplified SVG) -->
            <div v-if="paretoChartData.length > 0" class="mt-6">
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400 mb-3">
                    Pareto Curve (Top 50 Products)
                </p>
                <div class="relative h-48 bg-gray-50 dark:bg-gray-800/50 rounded-xl p-4">
                    <svg class="w-full h-full" viewBox="0 0 400 160" preserveAspectRatio="none">
                        <!-- Grid lines -->
                        <line x1="0" y1="32" x2="400" y2="32" stroke="currentColor" class="text-gray-200 dark:text-gray-700" stroke-dasharray="4"/>
                        <line x1="0" y1="80" x2="400" y2="80" stroke="currentColor" class="text-gray-200 dark:text-gray-700" stroke-dasharray="4"/>
                        <line x1="0" y1="128" x2="400" y2="128" stroke="currentColor" class="text-gray-200 dark:text-gray-700" stroke-dasharray="4"/>
                        
                        <!-- 80% threshold line -->
                        <line x1="0" y1="32" x2="400" y2="32" stroke="#10B981" stroke-width="2" stroke-dasharray="8"/>
                        
                        <!-- Cumulative line -->
                        <polyline
                            :points="paretoChartData.map((p, i) => `${(i / paretoChartData.length) * 400},${160 - (p.cumulativePercentage / 100) * 160}`).join(' ')"
                            fill="none"
                            stroke="#6366F1"
                            stroke-width="2"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                        />
                        
                        <!-- Area fill -->
                        <polygon
                            :points="`0,160 ${paretoChartData.map((p, i) => `${(i / paretoChartData.length) * 400},${160 - (p.cumulativePercentage / 100) * 160}`).join(' ')} 400,160`"
                            fill="url(#gradient)"
                            opacity="0.3"
                        />
                        
                        <!-- Gradient definition -->
                        <defs>
                            <linearGradient id="gradient" x1="0%" y1="0%" x2="0%" y2="100%">
                                <stop offset="0%" style="stop-color:#6366F1;stop-opacity:0.5" />
                                <stop offset="100%" style="stop-color:#6366F1;stop-opacity:0" />
                            </linearGradient>
                        </defs>
                    </svg>
                    
                    <!-- Y-axis labels -->
                    <div class="absolute left-0 top-0 h-full flex flex-col justify-between text-[10px] text-gray-500 dark:text-gray-400 -ml-1">
                        <span>100%</span>
                        <span class="text-emerald-600">80%</span>
                        <span>50%</span>
                        <span>20%</span>
                        <span>0%</span>
                    </div>
                </div>
            </div>

            <!-- Recommendations -->
            <div 
                v-if="props.data?.recommendations?.length > 0"
                class="mt-6 space-y-2"
            >
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400 mb-2">Recommendations</p>
                <div 
                    v-for="(rec, index) in props.data.recommendations.slice(0, 3)" 
                    :key="index"
                    class="flex items-start gap-2 p-3 rounded-lg bg-blue-50 dark:bg-blue-900/20 border border-blue-100 dark:border-blue-800"
                >
                    <svg class="w-4 h-4 text-blue-600 dark:text-blue-400 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <p class="text-sm text-blue-700 dark:text-blue-300">{{ rec }}</p>
                </div>
            </div>
        </div>
    </div>
</template>
