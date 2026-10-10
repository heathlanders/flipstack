<script setup>
import { computed, ref } from 'vue';

const props = defineProps({
    data: {
        type: Array,
        default: () => [],
    },
    interval: {
        type: String,
        default: 'day',
    },
});

const emit = defineEmits(['intervalChange']);

const intervals = [
    { value: 'hour', label: 'Hourly' },
    { value: 'day', label: 'Daily' },
    { value: 'week', label: 'Weekly' },
    { value: 'month', label: 'Monthly' },
];

const movementTypes = {
    purchase: { color: '#3B82F6', label: 'Purchases', icon: '📦' },
    sale: { color: '#10B981', label: 'Sales', icon: '💰' },
    return: { color: '#F59E0B', label: 'Returns', icon: '↩️' },
    transfer_in: { color: '#6366F1', label: 'Transfer In', icon: '📥' },
    transfer_out: { color: '#8B5CF6', label: 'Transfer Out', icon: '📤' },
    adjustment: { color: '#14B8A6', label: 'Adjustments', icon: '📝' },
    damaged: { color: '#EF4444', label: 'Damaged', icon: '⚠️' },
    lost: { color: '#DC2626', label: 'Lost', icon: '❌' },
    found: { color: '#22C55E', label: 'Found', icon: '✅' },
};

const selectedTypes = ref(['purchase', 'sale', 'return']);
const hoveredPoint = ref(null);

const chartData = computed(() => {
    if (!props.data || props.data.length === 0) return [];
    return props.data.map(item => ({
        ...item,
        formattedDate: formatDate(item.period),
    }));
});

const maxValue = computed(() => {
    if (!chartData.value.length) return 100;
    let max = 0;
    chartData.value.forEach(item => {
        selectedTypes.value.forEach(type => {
            // Access type directly from item (e.g., item.sale.quantity)
            const typeData = item[type];
            const value = typeData?.quantity || typeData?.count || 0;
            if (value > max) max = value;
        });
    });
    return max || 100;
});

const totalsByType = computed(() => {
    const totals = {};
    Object.keys(movementTypes).forEach(type => {
        totals[type] = chartData.value.reduce((sum, item) => {
            // Access type directly from item
            const typeData = item[type];
            return sum + (typeData?.quantity || typeData?.count || 0);
        }, 0);
    });
    return totals;
});

const chartWidth = 600;
const chartHeight = 200;
const padding = { top: 20, right: 20, bottom: 40, left: 50 };

const points = computed(() => {
    if (!chartData.value.length) return {};
    
    const result = {};
    const innerWidth = chartWidth - padding.left - padding.right;
    const innerHeight = chartHeight - padding.top - padding.bottom;
    const xStep = innerWidth / Math.max(chartData.value.length - 1, 1);
    
    selectedTypes.value.forEach(type => {
        result[type] = chartData.value.map((item, index) => {
            // Access type directly from item (e.g., item.sale.quantity)
            const typeData = item[type];
            const value = typeData?.quantity || typeData?.count || 0;
            const x = padding.left + (index * xStep);
            const y = padding.top + innerHeight - ((value / maxValue.value) * innerHeight);
            return { x, y, value, period: item.formattedDate };
        });
    });
    
    return result;
});

const pathData = computed(() => {
    const result = {};
    Object.entries(points.value).forEach(([type, pts]) => {
        if (pts.length === 0) {
            result[type] = '';
            return;
        }
        result[type] = pts.map((p, i) => `${i === 0 ? 'M' : 'L'} ${p.x} ${p.y}`).join(' ');
    });
    return result;
});

const areaData = computed(() => {
    const result = {};
    const innerHeight = chartHeight - padding.top - padding.bottom;
    const baseline = padding.top + innerHeight;
    
    Object.entries(points.value).forEach(([type, pts]) => {
        if (pts.length === 0) {
            result[type] = '';
            return;
        }
        const linePath = pts.map((p, i) => `${i === 0 ? 'M' : 'L'} ${p.x} ${p.y}`).join(' ');
        result[type] = `${linePath} L ${pts[pts.length - 1].x} ${baseline} L ${pts[0].x} ${baseline} Z`;
    });
    return result;
});

const yAxisTicks = computed(() => {
    const ticks = [];
    const steps = 5;
    for (let i = 0; i <= steps; i++) {
        ticks.push({
            value: Math.round((maxValue.value / steps) * (steps - i)),
            y: padding.top + ((chartHeight - padding.top - padding.bottom) / steps) * i,
        });
    }
    return ticks;
});

const xAxisTicks = computed(() => {
    if (!chartData.value.length) return [];
    const step = Math.ceil(chartData.value.length / 6);
    return chartData.value
        .filter((_, i) => i % step === 0)
        .map((item, i) => ({
            label: item.formattedDate,
            x: padding.left + (i * step * ((chartWidth - padding.left - padding.right) / Math.max(chartData.value.length - 1, 1))),
        }));
});

function formatDate(dateStr) {
    if (!dateStr) return '';
    const date = new Date(dateStr);
    switch (props.interval) {
        case 'hour':
            return date.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' });
        case 'day':
            return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
        case 'week':
            return `Week ${getWeekNumber(date)}`;
        case 'month':
            return date.toLocaleDateString('en-US', { month: 'short', year: '2-digit' });
        default:
            return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
    }
}

function getWeekNumber(date) {
    const d = new Date(Date.UTC(date.getFullYear(), date.getMonth(), date.getDate()));
    const dayNum = d.getUTCDay() || 7;
    d.setUTCDate(d.getUTCDate() + 4 - dayNum);
    const yearStart = new Date(Date.UTC(d.getUTCFullYear(), 0, 1));
    return Math.ceil((((d - yearStart) / 86400000) + 1) / 7);
}

function formatNumber(num) {
    return new Intl.NumberFormat('en-US').format(num);
}

function toggleType(type) {
    const index = selectedTypes.value.indexOf(type);
    if (index > -1) {
        if (selectedTypes.value.length > 1) {
            selectedTypes.value.splice(index, 1);
        }
    } else {
        selectedTypes.value.push(type);
    }
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
                    d="M7 12l3-3 3 3 4-4M8 21l4-4 4 4M3 4h18M4 4h16v12a1 1 0 01-1 1H5a1 1 0 01-1-1V4z"
                />
            </svg>
            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">No movement trend data available</p>
        </div>

        <div v-else>
            <!-- Header Controls -->
            <div class="flex items-center justify-between mb-4">
                <!-- Interval Selector -->
                <div class="flex items-center gap-1 bg-gray-100 dark:bg-gray-800 rounded-lg p-1">
                    <button
                        v-for="int in intervals"
                        :key="int.value"
                        class="px-3 py-1.5 text-xs font-medium rounded-md transition-all"
                        :class="interval === int.value 
                            ? 'bg-white dark:bg-gray-700 text-gray-900 dark:text-white shadow-sm' 
                            : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white'"
                        @click="emit('intervalChange', int.value)"
                    >
                        {{ int.label }}
                    </button>
                </div>

                <!-- Type Toggles -->
                <div class="flex items-center gap-2">
                    <button
                        v-for="(config, type) in movementTypes"
                        :key="type"
                        class="flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg text-xs font-medium transition-all border"
                        :class="selectedTypes.includes(type)
                            ? 'border-transparent shadow-sm'
                            : 'border-gray-200 dark:border-gray-700 opacity-50 hover:opacity-75'"
                        :style="selectedTypes.includes(type) ? { backgroundColor: config.color + '20', color: config.color } : {}"
                        @click="toggleType(type)"
                    >
                        <span 
                            class="w-2 h-2 rounded-full"
                            :style="{ backgroundColor: config.color }"
                        ></span>
                        {{ config.label }}
                    </button>
                </div>
            </div>

            <!-- Chart -->
            <div class="relative">
                <svg 
                    :viewBox="`0 0 ${chartWidth} ${chartHeight}`" 
                    class="w-full h-48"
                    preserveAspectRatio="xMidYMid meet"
                >
                    <!-- Grid lines -->
                    <g class="text-gray-200 dark:text-gray-700">
                        <line 
                            v-for="tick in yAxisTicks" 
                            :key="tick.value"
                            :x1="padding.left" 
                            :y1="tick.y" 
                            :x2="chartWidth - padding.right" 
                            :y2="tick.y"
                            stroke="currentColor"
                            stroke-dasharray="4"
                        />
                    </g>

                    <!-- Y-axis labels -->
                    <g class="text-gray-500 dark:text-gray-400">
                        <text 
                            v-for="tick in yAxisTicks" 
                            :key="tick.value"
                            :x="padding.left - 8" 
                            :y="tick.y + 4"
                            text-anchor="end"
                            font-size="10"
                            fill="currentColor"
                        >
                            {{ tick.value }}
                        </text>
                    </g>

                    <!-- X-axis labels -->
                    <g class="text-gray-500 dark:text-gray-400">
                        <text 
                            v-for="tick in xAxisTicks" 
                            :key="tick.label"
                            :x="tick.x" 
                            :y="chartHeight - 10"
                            text-anchor="middle"
                            font-size="10"
                            fill="currentColor"
                        >
                            {{ tick.label }}
                        </text>
                    </g>

                    <!-- Area fills -->
                    <g>
                        <path
                            v-for="type in selectedTypes"
                            :key="`area-${type}`"
                            :d="areaData[type]"
                            :fill="movementTypes[type].color"
                            opacity="0.1"
                        />
                    </g>

                    <!-- Lines -->
                    <g>
                        <path
                            v-for="type in selectedTypes"
                            :key="`line-${type}`"
                            :d="pathData[type]"
                            fill="none"
                            :stroke="movementTypes[type].color"
                            stroke-width="2"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                        />
                    </g>

                    <!-- Data points -->
                    <g>
                        <template v-for="type in selectedTypes" :key="`points-${type}`">
                            <circle
                                v-for="(point, index) in points[type]"
                                :key="`${type}-${index}`"
                                :cx="point.x"
                                :cy="point.y"
                                r="4"
                                :fill="movementTypes[type].color"
                                class="cursor-pointer transition-all hover:r-6"
                                @mouseenter="hoveredPoint = { type, ...point }"
                                @mouseleave="hoveredPoint = null"
                            />
                        </template>
                    </g>
                </svg>

                <!-- Tooltip -->
                <div 
                    v-if="hoveredPoint"
                    class="absolute bg-gray-900 text-white text-xs rounded-lg px-3 py-2 shadow-lg pointer-events-none z-10"
                    :style="{ 
                        left: `${(hoveredPoint.x / chartWidth) * 100}%`, 
                        top: `${(hoveredPoint.y / chartHeight) * 100 - 15}%`,
                        transform: 'translateX(-50%)'
                    }"
                >
                    <p class="font-semibold">{{ movementTypes[hoveredPoint.type].label }}</p>
                    <p>{{ hoveredPoint.period }}: {{ formatNumber(hoveredPoint.value) }}</p>
                </div>
            </div>

            <!-- Summary Stats -->
            <div class="grid grid-cols-5 gap-3 mt-4">
                <div 
                    v-for="(config, type) in movementTypes"
                    :key="type"
                    class="p-3 rounded-xl border transition-all"
                    :class="selectedTypes.includes(type) 
                        ? 'border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50' 
                        : 'border-gray-100 dark:border-gray-800 opacity-50'"
                >
                    <div class="flex items-center gap-2 mb-1">
                        <span 
                            class="w-2 h-2 rounded-full"
                            :style="{ backgroundColor: config.color }"
                        ></span>
                        <span class="text-xs text-gray-500 dark:text-gray-400">{{ config.label }}</span>
                    </div>
                    <p 
                        class="text-lg font-bold"
                        :style="{ color: config.color }"
                    >
                        {{ formatNumber(totalsByType[type]) }}
                    </p>
                </div>
            </div>
        </div>
    </div>
</template>
