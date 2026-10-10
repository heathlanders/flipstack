<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import KPICard from '@/Components/Analytics/KPICard.vue';
import StockHealthChart from '@/Components/Analytics/StockHealthChart.vue';
import MovementTrendsChart from '@/Components/Analytics/MovementTrendsChart.vue';
import TopPerformersTable from '@/Components/Analytics/TopPerformersTable.vue';
import DateRangePicker from '@/Components/Analytics/DateRangePicker.vue';
import CategoryDistributionChart from '@/Components/Analytics/CategoryDistributionChart.vue';
import AgingAnalysisChart from '@/Components/Analytics/AgingAnalysisChart.vue';

const props = defineProps({
    dashboardData: Object,
    dateRange: Object,
});

const isLoading = ref(false);
const selectedDateRange = ref({
    start_date: props.dateRange?.start_date || null,
    end_date: props.dateRange?.end_date || null,
});

// Computed KPI cards data
const kpiCards = computed(() => {
    const data = props.dashboardData;
    if (!data) return [];

    return [
        {
            title: 'Total Inventory Value',
            value: data.inventory_value?.cost_value?.formatted || '$0.00',
            subtitle: `Retail: ${data.inventory_value?.retail_value?.formatted || '$0.00'}`,
            change: data.inventory_value?.change_percentage || 0,
            trend: data.inventory_value?.trend || 'up',
            icon: 'currency-dollar',
            color: 'emerald',
        },
        {
            title: 'Total SKUs',
            value: formatNumber(data.total_skus || 0),
            subtitle: `Active: ${formatNumber(data.active_skus || 0)}`,
            change: null,
            icon: 'cube',
            color: 'blue',
        },
        {
            title: 'Low Stock Items',
            value: formatNumber(data.low_stock_count || 0),
            subtitle: 'Need attention',
            change: null,
            icon: 'exclamation-triangle',
            color: 'amber',
            alertLevel: data.low_stock_count > 10 ? 'warning' : 'normal',
        },
        {
            title: 'Out of Stock',
            value: formatNumber(data.out_of_stock_count || 0),
            subtitle: 'Immediate action required',
            change: null,
            icon: 'x-circle',
            color: 'red',
            alertLevel: data.out_of_stock_count > 0 ? 'critical' : 'normal',
        },
        {
            title: 'Profit Margin',
            value: `${data.inventory_value?.profit_margin || 0}%`,
            subtitle: `Potential: ${data.inventory_value?.potential_profit?.formatted || '$0.00'}`,
            change: null,
            icon: 'trending-up',
            color: 'violet',
        },
        {
            title: 'Total Units',
            value: formatNumber(data.inventory_value?.total_units || 0),
            subtitle: 'In stock',
            change: null,
            icon: 'archive',
            color: 'slate',
        },
    ];
});

// Movement summary data
const movementSummary = computed(() => {
    const summary = props.dashboardData?.movement_summary || {};
    return [
        { type: 'Purchases', count: summary.purchase?.count || 0, quantity: summary.purchase?.quantity || 0, color: '#3B82F6' },
        { type: 'Sales', count: summary.sale?.count || 0, quantity: summary.sale?.quantity || 0, color: '#10B981' },
        { type: 'Returns', count: summary.return?.count || 0, quantity: summary.return?.quantity || 0, color: '#F59E0B' },
        { type: 'Transfers', count: summary.transfer?.count || 0, quantity: summary.transfer?.quantity || 0, color: '#8B5CF6' },
        { type: 'Adjustments', count: summary.adjustment?.count || 0, quantity: summary.adjustment?.quantity || 0, color: '#14B8A6' },
        { type: 'Damaged', count: summary.damaged?.count || 0, quantity: summary.damaged?.quantity || 0, color: '#EF4444' },
    ];
});

function formatNumber(num) {
    if (num >= 1000000) {
        return (num / 1000000).toFixed(1) + 'M';
    }
    if (num >= 1000) {
        return (num / 1000).toFixed(1) + 'K';
    }
    return num.toLocaleString();
}

function handleDateRangeChange(range) {
    selectedDateRange.value = range;
    isLoading.value = true;
    
    router.get(route('inventory.analytics.dashboard'), {
        start_date: range.start_date,
        end_date: range.end_date,
    }, {
        preserveState: true,
        preserveScroll: true,
        onFinish: () => {
            isLoading.value = false;
        },
    });
}

function refreshData() {
    isLoading.value = true;
    router.reload({
        onFinish: () => {
            isLoading.value = false;
        },
    });
}

function navigateTo(page) {
    router.visit(route(`inventory.analytics.${page}`));
}

function exportData(format) {
    window.location.href = route('inventory.analytics.export', { 
        type: 'dashboard', 
        format: format 
    });
}
</script>

<template>
    <Head title="Inventory Analytics" />

    <AppLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-2xl font-bold tracking-tight text-gray-900 dark:text-white">
                        Inventory Analytics
                    </h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Real-time insights into your inventory performance
                    </p>
                </div>
                <div class="flex items-center gap-3">
                    <DateRangePicker
                        :initial-range="selectedDateRange"
                        @change="handleDateRangeChange"
                    />
                    <button
                        @click="refreshData"
                        :disabled="isLoading"
                        class="inline-flex items-center px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 dark:bg-gray-800 dark:text-gray-200 dark:border-gray-600 dark:hover:bg-gray-700 disabled:opacity-50"
                    >
                        <svg 
                            class="w-4 h-4 mr-2" 
                            :class="{ 'animate-spin': isLoading }"
                            fill="none" 
                            stroke="currentColor" 
                            viewBox="0 0 24 24"
                        >
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                        </svg>
                        Refresh
                    </button>
                    <div class="relative">
                        <button
                            class="inline-flex items-center px-3 py-2 text-sm font-medium text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                        >
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                            </svg>
                            Export
                        </button>
                    </div>
                </div>
            </div>
        </template>

        <div class="py-6">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <!-- Loading Overlay -->
                <div 
                    v-if="isLoading" 
                    class="fixed inset-0 z-50 flex items-center justify-center bg-white/50 dark:bg-gray-900/50 backdrop-blur-sm"
                >
                    <div class="flex flex-col items-center gap-4">
                        <div class="w-12 h-12 border-4 border-indigo-600 border-t-transparent rounded-full animate-spin"></div>
                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Loading analytics...</span>
                    </div>
                </div>

                <!-- KPI Cards Grid -->
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 mb-8">
                    <KPICard
                        v-for="(card, index) in kpiCards"
                        :key="index"
                        :title="card.title"
                        :value="card.value"
                        :subtitle="card.subtitle"
                        :change="card.change"
                        :trend="card.trend"
                        :icon="card.icon"
                        :color="card.color"
                        :alert-level="card.alertLevel"
                    />
                </div>

                <!-- Quick Navigation Cards -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-8">
                    <button
                        @click="navigateTo('abc')"
                        class="group relative overflow-hidden rounded-2xl bg-gradient-to-br from-violet-500 to-purple-600 p-6 text-left shadow-lg transition-all hover:shadow-xl hover:scale-[1.02]"
                    >
                        <div class="absolute inset-0 bg-white/10 opacity-0 group-hover:opacity-100 transition-opacity"></div>
                        <div class="relative">
                            <div class="flex items-center gap-3 mb-3">
                                <div class="p-2 bg-white/20 rounded-lg">
                                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                                    </svg>
                                </div>
                                <h3 class="text-lg font-semibold text-white">ABC Analysis</h3>
                            </div>
                            <p class="text-white/80 text-sm">Classify inventory by value contribution</p>
                        </div>
                    </button>

                    <button
                        @click="navigateTo('turnover')"
                        class="group relative overflow-hidden rounded-2xl bg-gradient-to-br from-emerald-500 to-teal-600 p-6 text-left shadow-lg transition-all hover:shadow-xl hover:scale-[1.02]"
                    >
                        <div class="absolute inset-0 bg-white/10 opacity-0 group-hover:opacity-100 transition-opacity"></div>
                        <div class="relative">
                            <div class="flex items-center gap-3 mb-3">
                                <div class="p-2 bg-white/20 rounded-lg">
                                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                    </svg>
                                </div>
                                <h3 class="text-lg font-semibold text-white">Turnover Analysis</h3>
                            </div>
                            <p class="text-white/80 text-sm">Track inventory efficiency metrics</p>
                        </div>
                    </button>

                    <button
                        @click="navigateTo('valuation')"
                        class="group relative overflow-hidden rounded-2xl bg-gradient-to-br from-amber-500 to-orange-600 p-6 text-left shadow-lg transition-all hover:shadow-xl hover:scale-[1.02]"
                    >
                        <div class="absolute inset-0 bg-white/10 opacity-0 group-hover:opacity-100 transition-opacity"></div>
                        <div class="relative">
                            <div class="flex items-center gap-3 mb-3">
                                <div class="p-2 bg-white/20 rounded-lg">
                                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                </div>
                                <h3 class="text-lg font-semibold text-white">Valuation Report</h3>
                            </div>
                            <p class="text-white/80 text-sm">Detailed inventory valuation breakdown</p>
                        </div>
                    </button>
                </div>

                <!-- Charts Row 1 -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                    <!-- Stock Health Chart -->
                    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 p-6">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Stock Health Distribution</h3>
                        <StockHealthChart :data="dashboardData?.stock_health" />
                    </div>

                    <!-- Movement Trends -->
                    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 p-6">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Movement Summary</h3>
                        <div class="space-y-4">
                            <div 
                                v-for="item in movementSummary" 
                                :key="item.type"
                                class="flex items-center justify-between p-3 rounded-lg bg-gray-50 dark:bg-gray-700/50"
                            >
                                <div class="flex items-center gap-3">
                                    <div 
                                        class="w-3 h-3 rounded-full"
                                        :style="{ backgroundColor: item.color }"
                                    ></div>
                                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ item.type }}</span>
                                </div>
                                <div class="flex items-center gap-4">
                                    <span class="text-sm text-gray-500 dark:text-gray-400">{{ item.count }} transactions</span>
                                    <span class="text-sm font-semibold text-gray-900 dark:text-white">{{ formatNumber(item.quantity) }} units</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Charts Row 2 -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                    <!-- Category Distribution -->
                    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 p-6">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Category Distribution</h3>
                        <CategoryDistributionChart :data="dashboardData?.category_distribution" />
                    </div>

                    <!-- Aging Analysis -->
                    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 p-6">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Inventory Aging</h3>
                        <AgingAnalysisChart :data="dashboardData?.aging_analysis" />
                    </div>
                </div>

                <!-- Top Performers Table -->
                <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Top Performing Products</h3>
                        <button 
                            class="text-sm text-indigo-600 hover:text-indigo-700 dark:text-indigo-400 dark:hover:text-indigo-300 font-medium"
                        >
                            View All →
                        </button>
                    </div>
                    <TopPerformersTable :products="dashboardData?.top_performers" />
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
/* Add smooth transitions */
.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.3s ease;
}

.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}
</style>
