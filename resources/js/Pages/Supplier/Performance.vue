<template>
    <AppLayout title="Performance">
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    Performance Dashboard
                </h2>
                <div class="flex items-center space-x-4">
                    <select 
                        v-model="selectedPeriod"
                        class="text-sm border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md shadow-sm"
                        @change="loadPeriodData"
                    >
                        <option value="current">Current Period</option>
                        <option value="last_month">Last Month</option>
                        <option value="last_quarter">Last Quarter</option>
                        <option value="ytd">Year to Date</option>
                    </select>
                    <button 
                        @click="exportReport"
                        class="inline-flex items-center px-4 py-2 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md font-semibold text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest shadow-sm hover:bg-gray-50 dark:hover:bg-gray-600"
                    >
                        <ArrowDownTrayIcon class="w-4 h-4 mr-2" />
                        Export
                    </button>
                </div>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <!-- Score Overview Cards -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm text-gray-500 dark:text-gray-400">Overall Score</p>
                                <p :class="getScoreColor(metrics.overall_score)" class="text-3xl font-bold">
                                    {{ metrics.overall_score?.toFixed(1) || 0 }}%
                                </p>
                            </div>
                            <ChartBarIcon class="w-12 h-12 text-gray-300 dark:text-gray-600" />
                        </div>
                        <div class="mt-2 flex items-center text-sm">
                            <component 
                                :is="scoreChange >= 0 ? ArrowUpIcon : ArrowDownIcon" 
                                :class="scoreChange >= 0 ? 'text-green-500' : 'text-red-500'"
                                class="w-4 h-4 mr-1"
                            />
                            <span :class="scoreChange >= 0 ? 'text-green-600' : 'text-red-600'">
                                {{ Math.abs(scoreChange).toFixed(1) }}%
                            </span>
                            <span class="text-gray-500 dark:text-gray-400 ml-1">vs last period</span>
                        </div>
                    </div>

                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm text-gray-500 dark:text-gray-400">Current Tier</p>
                                <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ tier?.name || 'N/A' }}</p>
                            </div>
                            <TrophyIcon class="w-12 h-12 text-yellow-400" />
                        </div>
                        <div class="mt-2">
                            <span class="text-sm text-gray-500 dark:text-gray-400">
                                {{ pointsToNextTier > 0 ? `${pointsToNextTier.toFixed(1)} pts to ${nextTier?.name}` : 'Top tier!' }}
                            </span>
                        </div>
                    </div>

                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm text-gray-500 dark:text-gray-400">Items Sold</p>
                                <p class="text-3xl font-bold text-gray-900 dark:text-white">
                                    {{ metrics.items_sold || 0 }}
                                </p>
                            </div>
                            <ShoppingCartIcon class="w-12 h-12 text-gray-300 dark:text-gray-600" />
                        </div>
                        <div class="mt-2 flex items-center text-sm">
                            <span class="text-gray-500 dark:text-gray-400">
                                ${{ (metrics.total_sales || 0).toLocaleString() }} total
                            </span>
                        </div>
                    </div>

                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm text-gray-500 dark:text-gray-400">Acceptance Rate</p>
                                <p class="text-3xl font-bold" :class="getAcceptanceColor(metrics.acceptance_rate)">
                                    {{ metrics.acceptance_rate?.toFixed(1) || 0 }}%
                                </p>
                            </div>
                            <CheckCircleIcon class="w-12 h-12 text-gray-300 dark:text-gray-600" />
                        </div>
                        <div class="mt-2">
                            <span class="text-sm text-gray-500 dark:text-gray-400">
                                Target: 85%
                            </span>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                    <!-- Main Content -->
                    <div class="lg:col-span-2 space-y-8">
                        <!-- Performance Trend Chart -->
                        <PerformanceChart 
                            :data="trendData"
                            :datasets="[
                                { key: 'overall_score', label: 'Overall Score', color: '#6366f1' },
                                { key: 'acceptance_rate', label: 'Acceptance Rate', color: '#10b981' },
                                { key: 'return_rate', label: 'Return Rate', color: '#ef4444' }
                            ]"
                            title="Performance Trend"
                            value-format="percent"
                            @period-change="handlePeriodChange"
                        />

                        <!-- Detailed Metrics -->
                        <PerformanceMetrics
                            :metrics="metrics || {}"
                            :weights="scoreWeights || undefined"
                            :tier-thresholds="tierThresholds"
                        />
                    </div>

                    <!-- Sidebar -->
                    <div class="space-y-6">
                        <!-- Tier Progress -->
                        <TierStatusCard 
                            :tier="tier"
                            :next-tier="nextTier"
                            :performance-score="metrics?.overall_score || 0"
                            :base-commission-rate="baseCommissionRate"
                            :tier-start-date="tierStartDate"
                            @compare-tiers="showTierComparison = true"
                        />

                        <!-- Recommendations -->
                        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                            <h3 class="font-medium text-gray-900 dark:text-white mb-4">Improvement Tips</h3>
                            <ul class="space-y-3">
                                <li 
                                    v-for="(tip, index) in recommendations" 
                                    :key="index"
                                    class="flex items-start space-x-3"
                                >
                                    <LightBulbIcon class="w-5 h-5 text-yellow-500 flex-shrink-0 mt-0.5" />
                                    <span class="text-sm text-gray-600 dark:text-gray-400">{{ tip }}</span>
                                </li>
                            </ul>
                        </div>

                        <!-- Benchmarking -->
                        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                            <h3 class="font-medium text-gray-900 dark:text-white mb-4">How You Compare</h3>
                            <div class="space-y-4">
                                <div>
                                    <div class="flex justify-between text-sm mb-1">
                                        <span class="text-gray-600 dark:text-gray-400">Your Score</span>
                                        <span class="font-medium text-gray-900 dark:text-white">
                                            {{ metrics.overall_score?.toFixed(1) || 0 }}%
                                        </span>
                                    </div>
                                    <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                                        <div 
                                            class="bg-indigo-500 h-2 rounded-full"
                                            :style="{ width: (metrics.overall_score || 0) + '%' }"
                                        ></div>
                                    </div>
                                </div>
                                <div>
                                    <div class="flex justify-between text-sm mb-1">
                                        <span class="text-gray-600 dark:text-gray-400">Tier Average</span>
                                        <span class="font-medium text-gray-900 dark:text-white">
                                            {{ benchmark.tier_average?.toFixed(1) || 0 }}%
                                        </span>
                                    </div>
                                    <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                                        <div 
                                            class="bg-gray-400 h-2 rounded-full"
                                            :style="{ width: (benchmark.tier_average || 0) + '%' }"
                                        ></div>
                                    </div>
                                </div>
                                <div>
                                    <div class="flex justify-between text-sm mb-1">
                                        <span class="text-gray-600 dark:text-gray-400">Top Performers</span>
                                        <span class="font-medium text-gray-900 dark:text-white">
                                            {{ benchmark.top_performers?.toFixed(1) || 0 }}%
                                        </span>
                                    </div>
                                    <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                                        <div 
                                            class="bg-green-500 h-2 rounded-full"
                                            :style="{ width: (benchmark.top_performers || 0) + '%' }"
                                        ></div>
                                    </div>
                                </div>
                            </div>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-4">
                                * Based on anonymized data from {{ benchmark.sample_size || 0 }} suppliers
                            </p>
                        </div>

                        <!-- Tier History -->
                        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                            <h3 class="font-medium text-gray-900 dark:text-white mb-4">Tier History</h3>
                            <div class="space-y-3">
                                <div 
                                    v-for="(history, index) in tierHistory.slice(0, 5)" 
                                    :key="index"
                                    class="flex items-center justify-between text-sm"
                                >
                                    <div class="flex items-center space-x-2">
                                        <component 
                                            :is="history.direction === 'up' ? ArrowUpIcon : history.direction === 'down' ? ArrowDownIcon : MinusIcon"
                                            :class="history.direction === 'up' ? 'text-green-500' : history.direction === 'down' ? 'text-red-500' : 'text-gray-400'"
                                            class="w-4 h-4"
                                        />
                                        <span class="text-gray-600 dark:text-gray-400">{{ history.to_tier }}</span>
                                    </div>
                                    <span class="text-gray-500 dark:text-gray-500">
                                        {{ formatDate(history.changed_at) }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tier Comparison Modal -->
        <Modal :show="showTierComparison" @close="showTierComparison = false" max-width="4xl">
            <div class="p-6">
                <h2 class="text-lg font-medium text-gray-900 dark:text-white mb-6">Tier Comparison</h2>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead>
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Feature</th>
                                <th 
                                    v-for="t in allTiers" 
                                    :key="t.id"
                                    :class="t.id === tier?.id ? 'bg-indigo-50 dark:bg-indigo-900/20' : ''"
                                    class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase"
                                >
                                    {{ t.name }}
                                    <span v-if="t.id === tier?.id" class="block text-indigo-600 dark:text-indigo-400 text-xs normal-case">(Current)</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            <tr>
                                <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-400">Score Range</td>
                                <td 
                                    v-for="t in allTiers" 
                                    :key="'score-' + t.id"
                                    :class="t.id === tier?.id ? 'bg-indigo-50 dark:bg-indigo-900/20' : ''"
                                    class="px-4 py-3 text-sm text-center text-gray-900 dark:text-white"
                                >
                                    {{ t.min_score }}% - {{ t.max_score || 100 }}%
                                </td>
                            </tr>
                            <tr>
                                <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-400">Commission Adj.</td>
                                <td 
                                    v-for="t in allTiers" 
                                    :key="'comm-' + t.id"
                                    :class="t.id === tier?.id ? 'bg-indigo-50 dark:bg-indigo-900/20' : ''"
                                    class="px-4 py-3 text-sm text-center"
                                >
                                    <span :class="t.commission_adjustment <= 0 ? 'text-green-600' : 'text-red-600'">
                                        {{ t.commission_adjustment > 0 ? '+' : '' }}{{ t.commission_adjustment }}%
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-400">Settlement</td>
                                <td 
                                    v-for="t in allTiers" 
                                    :key="'settle-' + t.id"
                                    :class="t.id === tier?.id ? 'bg-indigo-50 dark:bg-indigo-900/20' : ''"
                                    class="px-4 py-3 text-sm text-center text-gray-900 dark:text-white capitalize"
                                >
                                    {{ t.settlement_frequency }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="mt-6 flex justify-end">
                    <button 
                        @click="showTierComparison = false"
                        class="px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-md hover:bg-gray-300 dark:hover:bg-gray-600"
                    >
                        Close
                    </button>
                </div>
            </div>
        </Modal>
    </AppLayout>
</template>

<script setup>
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Modal from '@/Components/Modal.vue';
import TierStatusCard from '@/Components/Supplier/TierStatusCard.vue';
import PerformanceChart from '@/Components/Supplier/PerformanceChart.vue';
import PerformanceMetrics from '@/Components/Supplier/PerformanceMetrics.vue';
import {
    ChartBarIcon,
    TrophyIcon,
    ShoppingCartIcon,
    CheckCircleIcon,
    ArrowUpIcon,
    ArrowDownIcon,
    MinusIcon,
    LightBulbIcon,
    ArrowDownTrayIcon
} from '@heroicons/vue/24/outline';

const props = defineProps({
    metrics: { type: Object, default: () => ({}) },
    tier: { type: Object, default: null },
    nextTier: { type: Object, default: null },
    allTiers: { type: Array, default: () => [] },
    trendData: { type: Array, default: () => [] },
    tierHistory: { type: Array, default: () => [] },
    benchmark: { type: Object, default: () => ({}) },
    recommendations: { type: Array, default: () => [] },
    scoreWeights: { type: Object, default: null },
    baseCommissionRate: { type: Number, default: 0 },
    tierStartDate: { type: String, default: null },
    supplier: { type: Object, default: null },
    historicalMetrics: { type: [Object, Array], default: null },
});

const selectedPeriod = ref('current');
const showTierComparison = ref(false);

const scoreChange = computed(() => {
    if (!props.trendData || props.trendData.length < 2) return 0;
    const current = props.metrics?.overall_score || 0;
    const previous = props.trendData[props.trendData.length - 2]?.overall_score || 0;
    return current - previous;
});

const pointsToNextTier = computed(() => {
    if (!props.nextTier) return 0;
    return props.nextTier.min_score - (props.metrics?.overall_score || 0);
});

const tierThresholds = computed(() => {
    return (props.allTiers || []).map(t => ({
        score: t.min_score,
        label: t.name
    }));
});

function getScoreColor(score) {
    if (!score) return 'text-gray-500';
    if (score >= 95) return 'text-purple-600 dark:text-purple-400';
    if (score >= 85) return 'text-yellow-600 dark:text-yellow-400';
    if (score >= 75) return 'text-gray-600 dark:text-gray-400';
    if (score >= 65) return 'text-orange-600 dark:text-orange-400';
    return 'text-red-600 dark:text-red-400';
}

function getAcceptanceColor(rate) {
    if (!rate) return 'text-gray-500';
    if (rate >= 90) return 'text-green-600 dark:text-green-400';
    if (rate >= 80) return 'text-yellow-600 dark:text-yellow-400';
    return 'text-red-600 dark:text-red-400';
}

function formatDate(date) {
    if (!date) return '';
    return new Date(date).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
}

function loadPeriodData() {
    router.get(route('supplier.performance.index'), { period: selectedPeriod.value }, {
        preserveState: true,
        preserveScroll: true
    });
}

function handlePeriodChange(days) {
    // Chart period changed
}

function exportReport() {
    window.location.href = route('supplier.performance.export', { period: selectedPeriod.value });
}
</script>
