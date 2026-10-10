<script setup>
import { ref, watch, onMounted, computed } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import CategoryDistributionChart from '@/Components/Analytics/CategoryDistributionChart.vue';
import AgingAnalysisChart from '@/Components/Analytics/AgingAnalysisChart.vue';
import LocationPerformanceChart from '@/Components/Analytics/LocationPerformanceChart.vue';

const props = defineProps({
    filters: {
        type: Object,
        default: () => ({}),
    },
});

const loading = ref(false);
const valuationData = ref(null);
const stockHealthData = ref(null);
const agingData = ref([]);
const categoryData = ref([]);
const brandData = ref([]);
const locationData = ref([]);
const exporting = ref(false);
const activeSection = ref('overview');

const sections = [
    { id: 'overview', label: 'Overview', icon: '📊' },
    { id: 'categories', label: 'By Category', icon: '📁' },
    { id: 'brands', label: 'By Brand', icon: '🏷️' },
    { id: 'locations', label: 'By Location', icon: '📍' },
    { id: 'aging', label: 'Aging Analysis', icon: '⏰' },
];

async function fetchAllData() {
    loading.value = true;
    try {
        const [valueRes, healthRes] = await Promise.all([
            fetch('/api/analytics/kpis', {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            }),
            fetch('/api/analytics/stock-health', {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            }),
        ]);
        
        if (valueRes.ok) {
            const kpis = await valueRes.json();
            valuationData.value = kpis;
            agingData.value = kpis.aging || [];
            categoryData.value = kpis.category_distribution || [];
            brandData.value = kpis.brand_distribution || [];
            locationData.value = kpis.location_performance || [];
        }
        
        if (healthRes.ok) {
            stockHealthData.value = await healthRes.json();
        }
    } catch (error) {
        console.error('Error fetching valuation data:', error);
    } finally {
        loading.value = false;
    }
}

async function exportData(format) {
    exporting.value = true;
    try {
        const response = await fetch(`/inventory/analytics/export?type=valuation&format=${format}`, {
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
        a.download = `inventory-valuation-${new Date().toISOString().split('T')[0]}.${format}`;
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

onMounted(() => {
    fetchAllData();
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
    return `${(num || 0).toFixed(1)}%`;
}

const totalCostValue = computed(() => valuationData.value?.inventory_value?.cost_value || 0);
const totalRetailValue = computed(() => valuationData.value?.inventory_value?.retail_value || 0);
const potentialProfit = computed(() => totalRetailValue.value - totalCostValue.value);
const profitMargin = computed(() => {
    if (!totalRetailValue.value) return 0;
    return ((potentialProfit.value / totalRetailValue.value) * 100);
});
const totalUnits = computed(() => valuationData.value?.inventory_value?.total_units || 0);
const totalSKUs = computed(() => valuationData.value?.total_skus || 0);
const avgValuePerUnit = computed(() => totalUnits.value ? totalCostValue.value / totalUnits.value : 0);
</script>

<template>
    <AppLayout title="Inventory Valuation">
        <Head title="Inventory Valuation Report" />

        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-2xl font-bold text-gray-900 dark:text-white">
                        Inventory Valuation Report
                    </h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Comprehensive view of inventory value across categories, brands, and locations
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

                    <button
                        @click="fetchAllData"
                        class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-xl hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors"
                        :disabled="loading"
                    >
                        <svg 
                            class="w-4 h-4" 
                            :class="{ 'animate-spin': loading }"
                            fill="none" 
                            stroke="currentColor" 
                            viewBox="0 0 24 24"
                        >
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                        </svg>
                        Refresh
                    </button>

                    <div class="relative group">
                        <button
                            class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-gradient-to-r from-violet-600 to-purple-600 rounded-xl hover:from-violet-700 hover:to-purple-700 transition-all shadow-lg shadow-violet-500/25"
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
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <!-- Loading State -->
                <div 
                    v-if="loading"
                    class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 p-12"
                >
                    <div class="flex flex-col items-center justify-center">
                        <div class="w-12 h-12 border-4 border-violet-200 border-t-violet-600 rounded-full animate-spin"></div>
                        <p class="mt-4 text-sm text-gray-500 dark:text-gray-400">Loading valuation data...</p>
                    </div>
                </div>

                <template v-else>
                    <!-- Section Navigation -->
                    <div class="flex items-center gap-2 mb-6 overflow-x-auto pb-2">
                        <button
                            v-for="section in sections"
                            :key="section.id"
                            class="flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-medium whitespace-nowrap transition-all"
                            :class="activeSection === section.id 
                                ? 'bg-violet-100 dark:bg-violet-900/30 text-violet-700 dark:text-violet-300' 
                                : 'bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700 border border-gray-200 dark:border-gray-700'"
                            @click="activeSection = section.id"
                        >
                            <span>{{ section.icon }}</span>
                            {{ section.label }}
                        </button>
                    </div>

                    <!-- Overview Section -->
                    <div v-if="activeSection === 'overview'" class="space-y-6">
                        <!-- Value Summary Cards -->
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                            <!-- Cost Value -->
                            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                                <div class="flex items-center justify-between mb-2">
                                    <span class="text-sm text-gray-500 dark:text-gray-400">Cost Value</span>
                                    <span class="w-10 h-10 rounded-xl bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center">
                                        <svg class="w-5 h-5 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                        </svg>
                                    </span>
                                </div>
                                <p class="text-3xl font-bold text-gray-900 dark:text-white">
                                    {{ formatCurrency(totalCostValue) }}
                                </p>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                    Total inventory at cost
                                </p>
                            </div>

                            <!-- Retail Value -->
                            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                                <div class="flex items-center justify-between mb-2">
                                    <span class="text-sm text-gray-500 dark:text-gray-400">Retail Value</span>
                                    <span class="w-10 h-10 rounded-xl bg-emerald-100 dark:bg-emerald-900/30 flex items-center justify-center">
                                        <svg class="w-5 h-5 text-emerald-600 dark:text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>
                                        </svg>
                                    </span>
                                </div>
                                <p class="text-3xl font-bold text-gray-900 dark:text-white">
                                    {{ formatCurrency(totalRetailValue) }}
                                </p>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                    Total inventory at retail
                                </p>
                            </div>

                            <!-- Potential Profit -->
                            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                                <div class="flex items-center justify-between mb-2">
                                    <span class="text-sm text-gray-500 dark:text-gray-400">Potential Profit</span>
                                    <span class="w-10 h-10 rounded-xl bg-violet-100 dark:bg-violet-900/30 flex items-center justify-center">
                                        <svg class="w-5 h-5 text-violet-600 dark:text-violet-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/>
                                        </svg>
                                    </span>
                                </div>
                                <p class="text-3xl font-bold text-emerald-600 dark:text-emerald-400">
                                    {{ formatCurrency(potentialProfit) }}
                                </p>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                    {{ formatPercent(profitMargin) }} margin
                                </p>
                            </div>

                            <!-- Inventory Units -->
                            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                                <div class="flex items-center justify-between mb-2">
                                    <span class="text-sm text-gray-500 dark:text-gray-400">Total Inventory</span>
                                    <span class="w-10 h-10 rounded-xl bg-amber-100 dark:bg-amber-900/30 flex items-center justify-center">
                                        <svg class="w-5 h-5 text-amber-600 dark:text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                                        </svg>
                                    </span>
                                </div>
                                <p class="text-3xl font-bold text-gray-900 dark:text-white">
                                    {{ formatNumber(totalUnits) }}
                                </p>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                    units across {{ formatNumber(totalSKUs) }} SKUs
                                </p>
                            </div>
                        </div>

                        <!-- Additional Metrics -->
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div class="bg-gradient-to-br from-blue-500 to-indigo-600 rounded-2xl p-6 text-white">
                                <p class="text-sm text-white/80">Avg. Value Per Unit</p>
                                <p class="text-2xl font-bold mt-1">{{ formatCurrency(avgValuePerUnit) }}</p>
                            </div>
                            <div class="bg-gradient-to-br from-emerald-500 to-teal-600 rounded-2xl p-6 text-white">
                                <p class="text-sm text-white/80">Avg. Value Per SKU</p>
                                <p class="text-2xl font-bold mt-1">{{ formatCurrency(totalSKUs ? totalCostValue / totalSKUs : 0) }}</p>
                            </div>
                            <div class="bg-gradient-to-br from-violet-500 to-purple-600 rounded-2xl p-6 text-white">
                                <p class="text-sm text-white/80">Avg. Units Per SKU</p>
                                <p class="text-2xl font-bold mt-1">{{ formatNumber(totalSKUs ? totalUnits / totalSKUs : 0) }}</p>
                            </div>
                        </div>

                        <!-- Stock Health Summary -->
                        <div 
                            v-if="stockHealthData" 
                            class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 p-6"
                        >
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Stock Health Overview</h3>
                            <div class="grid grid-cols-5 gap-4">
                                <div 
                                    v-for="(value, key) in stockHealthData"
                                    :key="key"
                                    class="text-center p-4 rounded-xl"
                                    :class="{
                                        'bg-emerald-50 dark:bg-emerald-900/20': key === 'healthy',
                                        'bg-amber-50 dark:bg-amber-900/20': key === 'low_stock',
                                        'bg-red-50 dark:bg-red-900/20': key === 'critical' || key === 'out_of_stock',
                                        'bg-blue-50 dark:bg-blue-900/20': key === 'overstocked',
                                    }"
                                >
                                    <p class="text-2xl font-bold" :class="{
                                        'text-emerald-600 dark:text-emerald-400': key === 'healthy',
                                        'text-amber-600 dark:text-amber-400': key === 'low_stock',
                                        'text-red-600 dark:text-red-400': key === 'critical' || key === 'out_of_stock',
                                        'text-blue-600 dark:text-blue-400': key === 'overstocked',
                                    }">
                                        {{ formatNumber(value?.count || value || 0) }}
                                    </p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1 capitalize">
                                        {{ key.replace('_', ' ') }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Categories Section -->
                    <div v-if="activeSection === 'categories'" class="space-y-6">
                        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-6">
                                Value by Category
                            </h3>
                            <CategoryDistributionChart :data="categoryData" />
                        </div>
                    </div>

                    <!-- Brands Section -->
                    <div v-if="activeSection === 'brands'" class="space-y-6">
                        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-6">
                                Value by Brand
                            </h3>
                            <div v-if="brandData.length > 0" class="space-y-3">
                                <div 
                                    v-for="(brand, index) in brandData.slice(0, 15)"
                                    :key="brand.brand_id || index"
                                    class="flex items-center gap-4"
                                >
                                    <span class="text-sm text-gray-500 dark:text-gray-400 w-6">{{ index + 1 }}</span>
                                    <div class="flex-1">
                                        <div class="flex items-center justify-between mb-1">
                                            <span class="text-sm font-medium text-gray-900 dark:text-white">
                                                {{ brand.brand_name || 'Unknown' }}
                                            </span>
                                            <span class="text-sm text-gray-500 dark:text-gray-400">
                                                {{ formatCurrency(brand.total_value || 0) }}
                                            </span>
                                        </div>
                                        <div class="w-full h-2 bg-gray-200 dark:bg-gray-700 rounded-full overflow-hidden">
                                            <div 
                                                class="h-full bg-gradient-to-r from-violet-500 to-purple-500 rounded-full transition-all"
                                                :style="{ width: `${brand.percentage || 0}%` }"
                                            ></div>
                                        </div>
                                    </div>
                                    <span class="text-xs text-gray-400 w-12 text-right">
                                        {{ formatPercent(brand.percentage || 0) }}
                                    </span>
                                </div>
                            </div>
                            <div v-else class="text-center py-8 text-gray-500 dark:text-gray-400">
                                No brand data available
                            </div>
                        </div>
                    </div>

                    <!-- Locations Section -->
                    <div v-if="activeSection === 'locations'" class="space-y-6">
                        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-6">
                                Value by Location
                            </h3>
                            <LocationPerformanceChart :data="locationData" />
                        </div>
                    </div>

                    <!-- Aging Section -->
                    <div v-if="activeSection === 'aging'" class="space-y-6">
                        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-6">
                                Inventory Aging Analysis
                            </h3>
                            <AgingAnalysisChart :data="agingData" />
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </AppLayout>
</template>
