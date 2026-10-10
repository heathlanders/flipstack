<script setup>
import { computed, ref, onMounted } from 'vue';

const props = defineProps({
    data: {
        type: Object,
        default: () => ({}),
    },
});

const chartRef = ref(null);

const chartData = computed(() => {
    if (!props.data) return [];
    
    const categories = [
        { key: 'healthy', label: 'Healthy', color: '#10B981' },
        { key: 'low', label: 'Low Stock', color: '#F59E0B' },
        { key: 'critical', label: 'Critical', color: '#EF4444' },
        { key: 'out_of_stock', label: 'Out of Stock', color: '#6B7280' },
        { key: 'overstocked', label: 'Overstocked', color: '#3B82F6' },
    ];

    return categories.map(cat => ({
        ...cat,
        count: props.data[cat.key]?.count || 0,
        percentage: props.data[cat.key]?.percentage || 0,
    }));
});

const totalProducts = computed(() => {
    return chartData.value.reduce((sum, item) => sum + item.count, 0);
});

// Calculate donut chart segments
const segments = computed(() => {
    let cumulativePercentage = 0;
    const radius = 40;
    const circumference = 2 * Math.PI * radius;

    return chartData.value
        .filter(item => item.percentage > 0)
        .map(item => {
            const strokeDasharray = (item.percentage / 100) * circumference;
            const strokeDashoffset = -cumulativePercentage / 100 * circumference;
            cumulativePercentage += item.percentage;

            return {
                ...item,
                strokeDasharray: `${strokeDasharray} ${circumference - strokeDasharray}`,
                strokeDashoffset,
                radius,
            };
        });
});

const hoveredSegment = ref(null);

function handleSegmentHover(segment) {
    hoveredSegment.value = segment;
}

function handleSegmentLeave() {
    hoveredSegment.value = null;
}
</script>

<template>
    <div class="flex flex-col lg:flex-row items-center gap-6">
        <!-- Donut Chart -->
        <div class="relative flex-shrink-0">
            <svg 
                ref="chartRef"
                width="180" 
                height="180" 
                viewBox="0 0 100 100"
                class="transform -rotate-90"
            >
                <!-- Background circle -->
                <circle
                    cx="50"
                    cy="50"
                    r="40"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="12"
                    class="text-gray-100 dark:text-gray-700"
                />
                
                <!-- Data segments -->
                <circle
                    v-for="(segment, index) in segments"
                    :key="segment.key"
                    cx="50"
                    cy="50"
                    :r="segment.radius"
                    fill="none"
                    :stroke="segment.color"
                    stroke-width="12"
                    :stroke-dasharray="segment.strokeDasharray"
                    :stroke-dashoffset="segment.strokeDashoffset"
                    class="transition-all duration-300 cursor-pointer"
                    :class="{ 'opacity-70': hoveredSegment && hoveredSegment.key !== segment.key }"
                    @mouseenter="handleSegmentHover(segment)"
                    @mouseleave="handleSegmentLeave"
                />
            </svg>

            <!-- Center text -->
            <div class="absolute inset-0 flex flex-col items-center justify-center">
                <span class="text-3xl font-bold text-gray-900 dark:text-white">
                    {{ hoveredSegment?.count || totalProducts }}
                </span>
                <span class="text-xs text-gray-500 dark:text-gray-400">
                    {{ hoveredSegment?.label || 'Total' }}
                </span>
            </div>
        </div>

        <!-- Legend -->
        <div class="flex-1 grid grid-cols-1 gap-2 w-full">
            <div 
                v-for="item in chartData" 
                :key="item.key"
                class="flex items-center justify-between p-2 rounded-lg transition-colors cursor-pointer"
                :class="[
                    hoveredSegment?.key === item.key 
                        ? 'bg-gray-100 dark:bg-gray-700' 
                        : 'hover:bg-gray-50 dark:hover:bg-gray-700/50'
                ]"
                @mouseenter="handleSegmentHover(item)"
                @mouseleave="handleSegmentLeave"
            >
                <div class="flex items-center gap-2">
                    <div 
                        class="w-3 h-3 rounded-full flex-shrink-0"
                        :style="{ backgroundColor: item.color }"
                    ></div>
                    <span class="text-sm text-gray-700 dark:text-gray-300">{{ item.label }}</span>
                </div>
                <div class="flex items-center gap-2">
                    <span class="text-sm font-semibold text-gray-900 dark:text-white">{{ item.count }}</span>
                    <span class="text-xs text-gray-500 dark:text-gray-400">({{ item.percentage }}%)</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Health Score -->
    <div class="mt-6 pt-4 border-t border-gray-100 dark:border-gray-700">
        <div class="flex items-center justify-between">
            <span class="text-sm text-gray-600 dark:text-gray-400">Overall Stock Health</span>
            <div class="flex items-center gap-2">
                <div class="flex-1 w-32 h-2 bg-gray-200 dark:bg-gray-700 rounded-full overflow-hidden">
                    <div 
                        class="h-full bg-gradient-to-r from-emerald-500 to-green-400 rounded-full transition-all duration-500"
                        :style="{ width: `${chartData.find(d => d.key === 'healthy')?.percentage || 0}%` }"
                    ></div>
                </div>
                <span class="text-sm font-semibold text-emerald-600 dark:text-emerald-400">
                    {{ chartData.find(d => d.key === 'healthy')?.percentage || 0 }}%
                </span>
            </div>
        </div>
    </div>
</template>
