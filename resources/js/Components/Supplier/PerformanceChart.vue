<template>
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-6">
        <div class="flex items-center justify-between mb-6">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                {{ title }}
            </h3>
            <div class="flex items-center space-x-2">
                <select 
                    v-model="selectedPeriod"
                    class="text-sm border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                >
                    <option value="7">Last 7 days</option>
                    <option value="30">Last 30 days</option>
                    <option value="90">Last 90 days</option>
                    <option value="365">Last 12 months</option>
                </select>
            </div>
        </div>

        <!-- Chart Container -->
        <div class="relative" :style="{ height: height + 'px' }">
            <canvas ref="chartCanvas"></canvas>
        </div>

        <!-- Legend -->
        <div v-if="showLegend" class="flex flex-wrap justify-center gap-4 mt-4">
            <div 
                v-for="(dataset, index) in visibleDatasets" 
                :key="index"
                class="flex items-center space-x-2 cursor-pointer"
                @click="toggleDataset(index)"
            >
                <span 
                    class="w-3 h-3 rounded-full"
                    :style="{ backgroundColor: dataset.borderColor }"
                    :class="{ 'opacity-30': !dataset.visible }"
                ></span>
                <span 
                    class="text-sm text-gray-600 dark:text-gray-400"
                    :class="{ 'line-through opacity-50': !dataset.visible }"
                >
                    {{ dataset.label }}
                </span>
            </div>
        </div>

        <!-- Stats Summary -->
        <div v-if="showStats" class="grid grid-cols-3 gap-4 mt-6 pt-4 border-t border-gray-200 dark:border-gray-700">
            <div class="text-center">
                <div class="text-2xl font-bold text-gray-900 dark:text-white">
                    {{ formatValue(stats.average) }}
                </div>
                <div class="text-sm text-gray-500 dark:text-gray-400">Average</div>
            </div>
            <div class="text-center">
                <div class="text-2xl font-bold text-green-600">
                    {{ formatValue(stats.max) }}
                </div>
                <div class="text-sm text-gray-500 dark:text-gray-400">Peak</div>
            </div>
            <div class="text-center">
                <div :class="stats.trend >= 0 ? 'text-green-600' : 'text-red-600'" class="text-2xl font-bold">
                    {{ stats.trend >= 0 ? '+' : '' }}{{ stats.trend.toFixed(1) }}%
                </div>
                <div class="text-sm text-gray-500 dark:text-gray-400">Trend</div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, watch, nextTick } from 'vue';
import Chart from 'chart.js/auto';

const props = defineProps({
    title: {
        type: String,
        default: 'Performance Trend'
    },
    data: {
        type: Array,
        required: true
    },
    datasets: {
        type: Array,
        default: () => [{
            key: 'value',
            label: 'Score',
            color: '#6366f1'
        }]
    },
    height: {
        type: Number,
        default: 300
    },
    showLegend: {
        type: Boolean,
        default: true
    },
    showStats: {
        type: Boolean,
        default: true
    },
    type: {
        type: String,
        default: 'line',
        validator: (value) => ['line', 'bar', 'area'].includes(value)
    },
    valueFormat: {
        type: String,
        default: 'number',
        validator: (value) => ['number', 'currency', 'percent'].includes(value)
    }
});

const emit = defineEmits(['period-change']);

const chartCanvas = ref(null);
const selectedPeriod = ref(30);
let chartInstance = null;

const visibleDatasets = ref(
    props.datasets.map(d => ({ ...d, visible: true }))
);

const filteredData = computed(() => {
    const now = new Date();
    const cutoff = new Date(now.getTime() - selectedPeriod.value * 24 * 60 * 60 * 1000);
    return props.data.filter(item => new Date(item.date) >= cutoff);
});

const stats = computed(() => {
    const values = filteredData.value.map(d => d[props.datasets[0].key] || 0);
    if (values.length === 0) return { average: 0, max: 0, trend: 0 };
    
    const sum = values.reduce((a, b) => a + b, 0);
    const average = sum / values.length;
    const max = Math.max(...values);
    
    // Calculate trend (comparing first half to second half)
    const midpoint = Math.floor(values.length / 2);
    const firstHalf = values.slice(0, midpoint);
    const secondHalf = values.slice(midpoint);
    
    const firstAvg = firstHalf.length > 0 ? firstHalf.reduce((a, b) => a + b, 0) / firstHalf.length : 0;
    const secondAvg = secondHalf.length > 0 ? secondHalf.reduce((a, b) => a + b, 0) / secondHalf.length : 0;
    
    const trend = firstAvg !== 0 ? ((secondAvg - firstAvg) / firstAvg) * 100 : 0;
    
    return { average, max, trend };
});

function formatValue(value) {
    if (props.valueFormat === 'currency') {
        return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(value);
    }
    if (props.valueFormat === 'percent') {
        return value.toFixed(1) + '%';
    }
    return value.toFixed(1);
}

function toggleDataset(index) {
    visibleDatasets.value[index].visible = !visibleDatasets.value[index].visible;
    if (chartInstance) {
        chartInstance.data.datasets[index].hidden = !visibleDatasets.value[index].visible;
        chartInstance.update();
    }
}

function createChart() {
    if (!chartCanvas.value) return;
    
    const ctx = chartCanvas.value.getContext('2d');
    
    const labels = filteredData.value.map(d => {
        const date = new Date(d.date);
        if (selectedPeriod.value <= 30) {
            return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
        }
        return date.toLocaleDateString('en-US', { month: 'short', year: '2-digit' });
    });
    
    const datasets = props.datasets.map((dataset, index) => {
        const isArea = props.type === 'area';
        return {
            label: dataset.label,
            data: filteredData.value.map(d => d[dataset.key] || 0),
            borderColor: dataset.color,
            backgroundColor: isArea 
                ? `${dataset.color}20` 
                : props.type === 'bar' 
                    ? dataset.color 
                    : 'transparent',
            fill: isArea,
            tension: 0.4,
            pointRadius: selectedPeriod.value <= 30 ? 3 : 0,
            pointHoverRadius: 5,
            hidden: !visibleDatasets.value[index].visible
        };
    });
    
    chartInstance = new Chart(ctx, {
        type: props.type === 'area' ? 'line' : props.type,
        data: { labels, datasets },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: {
                intersect: false,
                mode: 'index'
            },
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    backgroundColor: 'rgba(0, 0, 0, 0.8)',
                    titleColor: '#fff',
                    bodyColor: '#fff',
                    padding: 12,
                    displayColors: true,
                    callbacks: {
                        label: function(context) {
                            let label = context.dataset.label || '';
                            if (label) {
                                label += ': ';
                            }
                            if (props.valueFormat === 'currency') {
                                label += new Intl.NumberFormat('en-US', { 
                                    style: 'currency', 
                                    currency: 'USD' 
                                }).format(context.parsed.y);
                            } else if (props.valueFormat === 'percent') {
                                label += context.parsed.y.toFixed(1) + '%';
                            } else {
                                label += context.parsed.y.toFixed(1);
                            }
                            return label;
                        }
                    }
                }
            },
            scales: {
                x: {
                    grid: {
                        display: false
                    },
                    ticks: {
                        maxTicksLimit: 8,
                        color: '#9ca3af'
                    }
                },
                y: {
                    beginAtZero: props.valueFormat !== 'percent',
                    max: props.valueFormat === 'percent' ? 100 : undefined,
                    grid: {
                        color: 'rgba(156, 163, 175, 0.2)'
                    },
                    ticks: {
                        color: '#9ca3af',
                        callback: function(value) {
                            if (props.valueFormat === 'currency') {
                                return '$' + value.toLocaleString();
                            }
                            if (props.valueFormat === 'percent') {
                                return value + '%';
                            }
                            return value;
                        }
                    }
                }
            }
        }
    });
}

function updateChart() {
    if (chartInstance) {
        chartInstance.destroy();
    }
    createChart();
}

watch(selectedPeriod, () => {
    emit('period-change', selectedPeriod.value);
    nextTick(() => updateChart());
});

watch(() => props.data, () => {
    nextTick(() => updateChart());
}, { deep: true });

onMounted(() => {
    nextTick(() => createChart());
});

onUnmounted(() => {
    if (chartInstance) {
        chartInstance.destroy();
    }
});
</script>
