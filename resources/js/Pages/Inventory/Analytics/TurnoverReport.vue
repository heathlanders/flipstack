<script setup>
import { ref, watch, onMounted, computed } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    filters: {
        type: Object,
        default: () => ({}),
    },
});

const loading = ref(false);
const period = ref(props.filters?.period || 365);
const turnoverData = ref(null);
const trendData = ref([]);
const trendInterval = ref('month');
const exporting = ref(false);

const periods = [
    { value: 30, label: '30 Days' },
    { value: 90, label: '90 Days' },
    { value: 180, label: '6 Months' },
    { value: 365, label: '1 Year' },
];

const trendIntervals = [
    { value: 'week', label: 'Weekly' },
    { value: 'month', label: 'Monthly' },
    { value: 'quarter', label: 'Quarterly' },
];

const classificationColors = {
    fast_moving: { bg: '#10B981', label: 'Fast Moving', icon: '🚀', description: '8+ turns/year' },
    normal: { bg: '#6366F1', label: 'Normal', icon: '📦', description: '4-8 turns/year' },
    slow_moving: { bg: '#F59E0B', label: 'Slow Moving', icon: '🐢', description: '1-4 turns/year' },
    dead_stock: { bg: '#EF4444', label: 'Dead Stock', icon: '💀', description: '<1 turn/year' },
};

async function fetchTurnoverAnalysis() {
    loading.value = true;
    try {
        const response = await fetch(`/api/analytics/turnover?period=${period.value}`, {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        });
        
        if (!response.ok) throw new Error('Failed to fetch analysis');
        
        turnoverData.value = await response.json();
    } catch (error) {
        console.error('Error fetching turnover analysis:', error);
    } finally {
        loading.value = false;
    }
}

async function fetchTrendData() {
    try {
        const response = await fetch(`/api/analytics/turnover-trend?interval=${trendInterval.value}`, {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        });
        
        if (!response.ok) throw new Error('Failed to fetch trend');
        
        trendData.value = await response.json();
    } catch (error) {
        console.error('Error fetching trend data:', error);
    }
}

async function exportData(format) {
    exporting.value = true;
    try {
        const response = await fetch(`/inventory/analytics/export?type=turnover&format=${format}&period=${period.value}`, {
            headers: {
                'Accept': format === 'json' ? 'application/json' : 'text/csv',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        });
        
        if (!response.ok) throw new Error('Export failed');
        
        const blob = await response.blob();
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `turnover-analysis-${period.value}days-${new Date().toISOString().split('T')[0]}.${format}`;
        document.body.appendChild(a);
        a.click();
        window.URL.revokeObjectURL(url);
        a.remove();
    } catch (error) {
        console.error('Export error:', error);
    } finally {
        exporting.value = false;
    }
}

watch(period, () => {
    fetchTurnoverAnalysis();
});

watch(trendInterval, () => {
    fetchTrendData();
});

onMounted(() => {
    fetchTurnoverAnalysis();
    fetchTrendData();
});

function formatCurrency(amount) {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
    }).format(amount);
}

function formatNumber(num, decimals = 0) {
    return new Intl.NumberFormat('en-US', {
        minimumFractionDigits: decimals,
        maximumFractionDigits: decimals,
    }).format(num);
}

function formatPercent(num) {
    return `${(num || 0).toFixed(1)}%`;
}

const overallMetrics = computed(() => {
    if (!turnoverData.value?.overall) return null;
    return turnoverData.value.overall;
});

const classificationData = computed(() => {
    if (!turnoverData.value?.classification) return [];
    return Object.entries(turnoverData.value.classification).map(([key, data]) => ({
        type: key,
        ...classificationColors[key],
        ...data,
    }));
});

const recommendations = computed(() => {
    return turnoverData.value?.recommendations || [];
});

// Simple SVG line chart for trend
const trendChartPath = computed(() => {
    if (!trendData.value || trendData.value.length === 0) return '';
    
    const width = 600;
    const height = 150;
    const padding = { top: 20, right: 20, bottom: 30, left: 50 };
    
    const values = trendData.value.map(d => d.turnover_ratio || 0);
    const maxVal = Math.max(...values, 1);
    const minVal = Math.min(...values, 0);
    const range = maxVal - minVal || 1;
    
    const innerWidth = width - padding.left - padding.right;
    const innerHeight = height - padding.top - padding.bottom;
    const xStep = innerWidth / Math.max(trendData.value.length - 1, 1);
    
    const points = trendData.value.map((d, i) => {
        const x = padding.left + (i * xStep);
        const y = padding.top + innerHeight - (((d.turnover_ratio || 0) - minVal) / range) * innerHeight;
        return `${x},${y}`;
    });
    
    return `M ${points.join(' L ')}`;
});
</script>

<template>
    <AppLayout title="Turnover Analysis">
        <Head title="Turnover Analysis" />

        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-2xl font-bold text-gray-900 dark:text-white">
                        Inventory Turnover Analysis
                    </h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Measure how efficiently inventory is being sold and replaced
                    </p>
                </div>
                
                <div class="flex items-center gap-3">
                    <button
                        @click="router.visit('/inventory/analytics')"
                        class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-xl hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                        </svg>
                        Dashboard
                    </button>

                    <div class="relative group">
                        <button
                            class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-gradient-to-r from-emerald-600 to-teal-600 rounded-xl hover:from-emerald-700 hover:to-teal-700 transition-all shadow-lg shadow-emerald-500/25"
                            :disabled="exporting"
                        >
                            <svg v-if="!exporting" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                            <svg v-else class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            Export
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </button>
                        <div class="absolute right-0 mt-2 w-48 bg-white dark:bg-gray-800 rounded-xl shadow-lg border border-gray-200 dark:border-gray-700 opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all z-10">
                            <button
                                @click="exportData('csv')"
                                class="w-full px-4 py-2.5 text-left text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 rounded-t-xl"
                            >
                                Export as CSV
                            </button>
                            <button
                                @click="exportData('json')"
                                class="w-full px-4 py-2.5 text-left text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 rounded-b-xl"
                            >
                                Export as JSON
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
                <!-- Period Selection -->
                <div class="flex items-center gap-4">
                    <span class="text-sm text-gray-500 dark:text-gray-400">Analysis Period:</span>
                    <div class="flex items-center gap-1 bg-gray-100 dark:bg-gray-800 rounded-lg p-1">
                        <button
                            v-for="p in periods"
                            :key="p.value"
                            class="px-4 py-2 text-sm font-medium rounded-lg transition-all"
                            :class="period === p.value 
                                ? 'bg-white dark:bg-gray-700 text-gray-900 dark:text-white shadow-sm' 
                                : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white'"
                            @click="period = p.value"
                        >
                            {{ p.label }}
                        </button>
                    </div>
                </div>

                <!-- Loading State -->
                <div 
                    v-if="loading"
                    class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 p-12"
                >
                    <div class="flex flex-col items-center justify-center">
                        <div class="w-12 h-12 border-4 border-emerald-200 border-t-emerald-600 rounded-full animate-spin"></div>
                        <p class="mt-4 text-sm text-gray-500 dark:text-gray-400">Calculating turnover metrics...</p>
                    </div>
                </div>

                <!-- Results -->
                <template v-else-if="overallMetrics">
                    <!-- Key Metrics -->
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                        <!-- Turnover Ratio -->
                        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-sm text-gray-500 dark:text-gray-400">Turnover Ratio</span>
                                <span class="text-2xl">🔄</span>
                            </div>
                            <p class="text-3xl font-bold text-gray-900 dark:text-white">
                                {{ formatNumber(overallMetrics.turnover_ratio || 0, 2) }}x
                            </p>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                times per year (annualized)
                            </p>
                            <div class="mt-3 pt-3 border-t border-gray-100 dark:border-gray-700">
                                <div class="flex items-center gap-2">
                                    <span 
                                        class="w-2 h-2 rounded-full"
                                        :class="(overallMetrics.turnover_ratio || 0) >= 4 ? 'bg-emerald-500' : (overallMetrics.turnover_ratio || 0) >= 2 ? 'bg-amber-500' : 'bg-red-500'"
                                    ></span>
                                    <span class="text-xs text-gray-500 dark:text-gray-400">
                                        {{ (overallMetrics.turnover_ratio || 0) >= 4 ? 'Good performance' : (overallMetrics.turnover_ratio || 0) >= 2 ? 'Average' : 'Needs improvement' }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- DSI -->
                        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-sm text-gray-500 dark:text-gray-400">Days Sales of Inventory</span>
                                <span class="text-2xl">📅</span>
                            </div>
                            <p class="text-3xl font-bold text-gray-900 dark:text-white">
                                {{ formatNumber(overallMetrics.days_sales_inventory || 0, 0) }}
                            </p>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                days to sell inventory
                            </p>
                            <div class="mt-3 pt-3 border-t border-gray-100 dark:border-gray-700">
                                <div class="flex items-center gap-2">
                                    <span 
                                        class="w-2 h-2 rounded-full"
                                        :class="(overallMetrics.days_sales_inventory || 0) <= 60 ? 'bg-emerald-500' : (overallMetrics.days_sales_inventory || 0) <= 90 ? 'bg-amber-500' : 'bg-red-500'"
                                    ></span>
                                    <span class="text-xs text-gray-500 dark:text-gray-400">
                                        Target: 30-60 days
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Sell-Through Rate -->
                        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-sm text-gray-500 dark:text-gray-400">Sell-Through Rate</span>
                                <span class="text-2xl">📈</span>
                            </div>
                            <p class="text-3xl font-bold text-gray-900 dark:text-white">
                                {{ formatPercent(overallMetrics.sell_through_rate || 0) }}
                            </p>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                of inventory sold in period
                            </p>
                            <div class="mt-3 pt-3 border-t border-gray-100 dark:border-gray-700">
                                <div class="w-full h-2 bg-gray-200 dark:bg-gray-700 rounded-full overflow-hidden">
                                    <div 
                                        class="h-full bg-gradient-to-r from-emerald-500 to-teal-500 rounded-full transition-all"
                                        :style="{ width: `${Math.min(overallMetrics.sell_through_rate || 0, 100)}%` }"
                                    ></div>
                                </div>
                            </div>
                        </div>

                        <!-- GMROI -->
                        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-sm text-gray-500 dark:text-gray-400">GMROI</span>
                                <span class="text-2xl">💰</span>
                            </div>
                            <p class="text-3xl font-bold text-gray-900 dark:text-white">
                                {{ formatPercent(overallMetrics.gmroi || 0) }}
                            </p>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                Gross Margin Return on Investment
                            </p>
                            <div class="mt-3 pt-3 border-t border-gray-100 dark:border-gray-700">
                                <div class="flex items-center gap-2">
                                    <span 
                                        class="w-2 h-2 rounded-full"
                                        :class="(overallMetrics.gmroi || 0) >= 200 ? 'bg-emerald-500' : (overallMetrics.gmroi || 0) >= 100 ? 'bg-amber-500' : 'bg-red-500'"
                                    ></span>
                                    <span class="text-xs text-gray-500 dark:text-gray-400">
                                        Target: >200%
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Classification Breakdown -->
                    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-6">
                            Inventory Classification by Turnover
                        </h3>

                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                            <div 
                                v-for="cls in classificationData"
                                :key="cls.type"
                                class="p-4 rounded-xl border-2 transition-all"
                                :style="{ borderColor: cls.bg + '40', backgroundColor: cls.bg + '10' }"
                            >
                                <div class="flex items-center gap-3 mb-3">
                                    <span class="text-2xl">{{ cls.icon }}</span>
                                    <div>
                                        <p class="font-semibold text-gray-900 dark:text-white">{{ cls.label }}</p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ cls.description }}</p>
                                    </div>
                                </div>
                                <div class="space-y-2">
                                    <div class="flex justify-between">
                                        <span class="text-sm text-gray-500 dark:text-gray-400">Products</span>
                                        <span class="font-semibold" :style="{ color: cls.bg }">
                                            {{ formatNumber(cls.product_count || 0) }}
                                        </span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-sm text-gray-500 dark:text-gray-400">Value</span>
                                        <span class="font-semibold" :style="{ color: cls.bg }">
                                            {{ formatCurrency(cls.total_value || 0) }}
                                        </span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-sm text-gray-500 dark:text-gray-400">% of Total</span>
                                        <span class="font-semibold" :style="{ color: cls.bg }">
                                            {{ formatPercent(cls.percentage || 0) }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Classification Bar -->
                        <div>
                            <p class="text-xs font-medium text-gray-500 dark:text-gray-400 mb-2">Distribution by Value</p>
                            <div class="flex h-4 rounded-full overflow-hidden">
                                <div 
                                    v-for="cls in classificationData"
                                    :key="cls.type"
                                    class="transition-all"
                                    :style="{ width: `${cls.percentage || 0}%`, backgroundColor: cls.bg }"
                                    :title="`${cls.label}: ${formatPercent(cls.percentage || 0)}`"
                                ></div>
                            </div>
                            <div class="flex flex-wrap gap-4 mt-3">
                                <div 
                                    v-for="cls in classificationData"
                                    :key="cls.type"
                                    class="flex items-center gap-2"
                                >
                                    <span class="w-3 h-3 rounded-full" :style="{ backgroundColor: cls.bg }"></span>
                                    <span class="text-xs text-gray-600 dark:text-gray-400">{{ cls.label }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Turnover Trend Chart -->
                    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                        <div class="flex items-center justify-between mb-6">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                                Turnover Trend
                            </h3>
                            <div class="flex items-center gap-1 bg-gray-100 dark:bg-gray-700 rounded-lg p-1">
                                <button
                                    v-for="int in trendIntervals"
                                    :key="int.value"
                                    class="px-3 py-1.5 text-xs font-medium rounded-md transition-all"
                                    :class="trendInterval === int.value 
                                        ? 'bg-white dark:bg-gray-600 text-gray-900 dark:text-white shadow-sm' 
                                        : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white'"
                                    @click="trendInterval = int.value"
                                >
                                    {{ int.label }}
                                </button>
                            </div>
                        </div>

                        <div v-if="trendData.length > 0" class="relative">
                            <svg viewBox="0 0 600 150" class="w-full h-40">
                                <!-- Grid -->
                                <line x1="50" y1="20" x2="580" y2="20" stroke="currentColor" class="text-gray-200 dark:text-gray-700" stroke-dasharray="4"/>
                                <line x1="50" y1="70" x2="580" y2="70" stroke="currentColor" class="text-gray-200 dark:text-gray-700" stroke-dasharray="4"/>
                                <line x1="50" y1="120" x2="580" y2="120" stroke="currentColor" class="text-gray-200 dark:text-gray-700" stroke-dasharray="4"/>
                                
                                <!-- Line -->
                                <path 
                                    :d="trendChartPath" 
                                    fill="none" 
                                    stroke="#10B981" 
                                    stroke-width="3"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                />
                            </svg>
                        </div>
                        <div v-else class="text-center py-8 text-gray-500 dark:text-gray-400">
                            No trend data available
                        </div>
                    </div>

                    <!-- Recommendations -->
                    <div 
                        v-if="recommendations.length > 0"
                        class="bg-gradient-to-br from-emerald-500 to-teal-600 rounded-2xl shadow-lg p-6 text-white"
                    >
                        <div class="flex items-center gap-3 mb-4">
                            <div class="w-10 h-10 rounded-xl bg-white/20 flex items-center justify-center">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/>
                                </svg>
                            </div>
                            <h3 class="text-lg font-bold">Recommendations</h3>
                        </div>
                        <div class="space-y-3">
                            <div 
                                v-for="(rec, index) in recommendations.slice(0, 5)"
                                :key="index"
                                class="flex items-start gap-3 p-3 rounded-xl bg-white/10"
                            >
                                <span 
                                    class="w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold flex-shrink-0"
                                    :class="{
                                        'bg-red-500': rec.priority === 'high',
                                        'bg-amber-500': rec.priority === 'medium',
                                        'bg-emerald-400': rec.priority === 'low',
                                    }"
                                >
                                    {{ index + 1 }}
                                </span>
                                <div>
                                    <p class="font-medium">{{ rec.title || rec }}</p>
                                    <p v-if="rec.description" class="text-sm text-white/80 mt-1">{{ rec.description }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </AppLayout>
</template>
