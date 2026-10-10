<script setup>
import { ref, watch, onMounted } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import ABCAnalysisChart from '@/Components/Analytics/ABCAnalysisChart.vue';
import TopPerformersTable from '@/Components/Analytics/TopPerformersTable.vue';

const props = defineProps({
    filters: {
        type: Object,
        default: () => ({}),
    },
});

const loading = ref(false);
const metric = ref(props.filters?.metric || 'revenue');
const analysisData = ref(null);
const classProducts = ref({ A: [], B: [], C: [] });
const activeTab = ref('A');
const exporting = ref(false);

const metrics = [
    { value: 'revenue', label: 'Revenue', description: 'Classify by sales revenue contribution' },
    { value: 'sales_volume', label: 'Sales Volume', description: 'Classify by units sold' },
    { value: 'inventory_value', label: 'Inventory Value', description: 'Classify by current stock value' },
];

async function fetchAnalysis() {
    loading.value = true;
    try {
        const response = await fetch(`/api/analytics/abc?metric=${metric.value}`, {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        });
        
        if (!response.ok) throw new Error('Failed to fetch analysis');
        
        const data = await response.json();
        analysisData.value = data;
        
        // Group products by class
        if (data?.products) {
            classProducts.value = {
                A: data.products.filter(p => p.class === 'A'),
                B: data.products.filter(p => p.class === 'B'),
                C: data.products.filter(p => p.class === 'C'),
            };
        }
    } catch (error) {
        console.error('Error fetching ABC analysis:', error);
    } finally {
        loading.value = false;
    }
}

async function exportData(format) {
    exporting.value = true;
    try {
        const response = await fetch(`/inventory/analytics/export?type=abc&format=${format}&metric=${metric.value}`, {
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
        a.download = `abc-analysis-${metric.value}-${new Date().toISOString().split('T')[0]}.${format}`;
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

watch(metric, () => {
    fetchAnalysis();
});

onMounted(() => {
    fetchAnalysis();
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
</script>

<template>
    <AppLayout title="ABC Analysis">
        <Head title="ABC Analysis" />

        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-2xl font-bold text-gray-900 dark:text-white">
                        ABC Analysis
                    </h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Inventory classification by value contribution using Pareto principle
                    </p>
                </div>
                
                <div class="flex items-center gap-3">
                    <!-- Back to Dashboard -->
                    <button
                        @click="router.visit('/inventory/analytics')"
                        class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-xl hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                        </svg>
                        Dashboard
                    </button>

                    <!-- Export Dropdown -->
                    <div class="relative group">
                        <button
                            class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-gradient-to-r from-indigo-600 to-purple-600 rounded-xl hover:from-indigo-700 hover:to-purple-700 transition-all shadow-lg shadow-indigo-500/25"
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
                <!-- Metric Selection -->
                <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-white mb-4">
                        Classification Metric
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <button
                            v-for="m in metrics"
                            :key="m.value"
                            class="p-4 rounded-xl border-2 text-left transition-all"
                            :class="metric === m.value 
                                ? 'border-indigo-500 bg-indigo-50 dark:bg-indigo-900/20' 
                                : 'border-gray-200 dark:border-gray-700 hover:border-gray-300 dark:hover:border-gray-600'"
                            @click="metric = m.value"
                        >
                            <div class="flex items-center gap-3">
                                <div 
                                    class="w-4 h-4 rounded-full border-2 flex items-center justify-center"
                                    :class="metric === m.value ? 'border-indigo-500' : 'border-gray-300 dark:border-gray-600'"
                                >
                                    <div 
                                        v-if="metric === m.value"
                                        class="w-2 h-2 rounded-full bg-indigo-500"
                                    ></div>
                                </div>
                                <div>
                                    <p class="font-semibold text-gray-900 dark:text-white">{{ m.label }}</p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{{ m.description }}</p>
                                </div>
                            </div>
                        </button>
                    </div>
                </div>

                <!-- Loading State -->
                <div 
                    v-if="loading"
                    class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 p-12"
                >
                    <div class="flex flex-col items-center justify-center">
                        <div class="w-12 h-12 border-4 border-indigo-200 border-t-indigo-600 rounded-full animate-spin"></div>
                        <p class="mt-4 text-sm text-gray-500 dark:text-gray-400">Analyzing inventory...</p>
                    </div>
                </div>

                <!-- Analysis Results -->
                <template v-else-if="analysisData">
                    <!-- ABC Chart -->
                    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-6">
                            Classification Overview
                        </h3>
                        <ABCAnalysisChart 
                            :data="analysisData" 
                            :metric="metric"
                        />
                    </div>

                    <!-- Class Tabs and Product Lists -->
                    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
                        <!-- Tab Headers -->
                        <div class="flex border-b border-gray-200 dark:border-gray-700">
                            <button
                                v-for="cls in ['A', 'B', 'C']"
                                :key="cls"
                                class="flex-1 px-6 py-4 text-center font-semibold transition-colors relative"
                                :class="activeTab === cls 
                                    ? 'text-gray-900 dark:text-white bg-gray-50 dark:bg-gray-700/50' 
                                    : 'text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300'"
                                @click="activeTab = cls"
                            >
                                <div class="flex items-center justify-center gap-2">
                                    <span 
                                        class="w-6 h-6 rounded-full text-xs font-bold flex items-center justify-center text-white"
                                        :class="{
                                            'bg-emerald-500': cls === 'A',
                                            'bg-amber-500': cls === 'B',
                                            'bg-gray-500': cls === 'C',
                                        }"
                                    >
                                        {{ cls }}
                                    </span>
                                    <span>Class {{ cls }}</span>
                                    <span class="text-xs text-gray-400 dark:text-gray-500">
                                        ({{ formatNumber(classProducts[cls]?.length || 0) }})
                                    </span>
                                </div>
                                <div 
                                    v-if="activeTab === cls"
                                    class="absolute bottom-0 left-0 right-0 h-0.5"
                                    :class="{
                                        'bg-emerald-500': cls === 'A',
                                        'bg-amber-500': cls === 'B',
                                        'bg-gray-500': cls === 'C',
                                    }"
                                ></div>
                            </button>
                        </div>

                        <!-- Tab Content -->
                        <div class="p-6">
                            <div v-if="classProducts[activeTab]?.length === 0" class="text-center py-8">
                                <p class="text-gray-500 dark:text-gray-400">No products in this class</p>
                            </div>
                            <div v-else class="overflow-x-auto">
                                <table class="w-full">
                                    <thead>
                                        <tr class="text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                            <th class="pb-3 pr-4">Rank</th>
                                            <th class="pb-3 pr-4">Product</th>
                                            <th class="pb-3 pr-4 text-right">Value</th>
                                            <th class="pb-3 pr-4 text-right">Cumulative %</th>
                                            <th class="pb-3 text-right">Contribution</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                        <tr 
                                            v-for="(product, index) in classProducts[activeTab].slice(0, 50)"
                                            :key="product.product_id"
                                            class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors"
                                        >
                                            <td class="py-3 pr-4">
                                                <span class="text-sm font-medium text-gray-900 dark:text-white">
                                                    #{{ product.rank || index + 1 }}
                                                </span>
                                            </td>
                                            <td class="py-3 pr-4">
                                                <div>
                                                    <p class="text-sm font-medium text-gray-900 dark:text-white">
                                                        {{ product.product_name || product.name || 'Unknown' }}
                                                    </p>
                                                    <p class="text-xs text-gray-500 dark:text-gray-400">
                                                        {{ product.sku || 'No SKU' }}
                                                    </p>
                                                </div>
                                            </td>
                                            <td class="py-3 pr-4 text-right">
                                                <span class="text-sm font-semibold text-gray-900 dark:text-white">
                                                    {{ formatCurrency(product.value || 0) }}
                                                </span>
                                            </td>
                                            <td class="py-3 pr-4 text-right">
                                                <div class="flex items-center justify-end gap-2">
                                                    <div class="w-20 h-1.5 bg-gray-200 dark:bg-gray-700 rounded-full overflow-hidden">
                                                        <div 
                                                            class="h-full rounded-full"
                                                            :class="{
                                                                'bg-emerald-500': activeTab === 'A',
                                                                'bg-amber-500': activeTab === 'B',
                                                                'bg-gray-500': activeTab === 'C',
                                                            }"
                                                            :style="{ width: `${product.cumulative_percentage || 0}%` }"
                                                        ></div>
                                                    </div>
                                                    <span class="text-xs text-gray-500 dark:text-gray-400">
                                                        {{ (product.cumulative_percentage || 0).toFixed(1) }}%
                                                    </span>
                                                </div>
                                            </td>
                                            <td class="py-3 text-right">
                                                <span 
                                                    class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium"
                                                    :class="{
                                                        'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400': activeTab === 'A',
                                                        'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400': activeTab === 'B',
                                                        'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-400': activeTab === 'C',
                                                    }"
                                                >
                                                    {{ (product.percentage || 0).toFixed(2) }}%
                                                </span>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                                
                                <div 
                                    v-if="classProducts[activeTab].length > 50"
                                    class="mt-4 text-center text-sm text-gray-500 dark:text-gray-400"
                                >
                                    Showing top 50 of {{ formatNumber(classProducts[activeTab].length) }} products. 
                                    <button @click="exportData('csv')" class="text-indigo-600 dark:text-indigo-400 hover:underline">
                                        Export all
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Insights Card -->
                    <div class="bg-gradient-to-br from-indigo-500 to-purple-600 rounded-2xl shadow-lg p-6 text-white">
                        <div class="flex items-start gap-4">
                            <div class="w-12 h-12 rounded-xl bg-white/20 flex items-center justify-center">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/>
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-lg font-bold mb-2">ABC Analysis Insights</h3>
                                <ul class="space-y-2 text-sm text-white/90">
                                    <li class="flex items-start gap-2">
                                        <span class="text-emerald-300">▸</span>
                                        <span><strong>Class A ({{ analysisData?.summary?.A?.product_count || 0 }} items):</strong> Your top performers contributing ~80% of value. Focus on never running out of stock.</span>
                                    </li>
                                    <li class="flex items-start gap-2">
                                        <span class="text-amber-300">▸</span>
                                        <span><strong>Class B ({{ analysisData?.summary?.B?.product_count || 0 }} items):</strong> Important items contributing ~15% of value. Review quarterly.</span>
                                    </li>
                                    <li class="flex items-start gap-2">
                                        <span class="text-gray-300">▸</span>
                                        <span><strong>Class C ({{ analysisData?.summary?.C?.product_count || 0 }} items):</strong> Low contributors at ~5% of value. Consider reducing variety or discontinuing slow movers.</span>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </AppLayout>
</template>
