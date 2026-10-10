<script setup>
import { computed } from 'vue';
import { Bar } from 'vue-chartjs';
import {
    Chart as ChartJS,
    CategoryScale,
    LinearScale,
    BarElement,
    Title,
    Tooltip,
    Legend,
} from 'chart.js';

ChartJS.register(CategoryScale, LinearScale, BarElement, Title, Tooltip, Legend);

const props = defineProps({
    stages: {
        type: Array,
        required: true,
        // [{ name, avgHours, color }]
    },
});

const isDark = computed(() => {
    return document.documentElement.classList.contains('dark');
});

const chartData = computed(() => {
    return {
        labels: ['Fulfillment Pipeline'],
        datasets: props.stages.map((stage) => ({
            label: stage.name,
            data: [stage.avgHours],
            backgroundColor: stage.color,
            borderColor: stage.color,
            borderWidth: 0,
            borderRadius: 0,
            borderSkipped: false,
            barPercentage: 0.6,
            categoryPercentage: 0.8,
        })),
    };
});

const chartOptions = computed(() => {
    const gridColor = isDark.value ? 'rgba(75, 85, 99, 0.3)' : 'rgba(229, 231, 235, 0.8)';
    const textColor = isDark.value ? '#9ca3af' : '#6b7280';

    return {
        indexAxis: 'y',
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: true,
                position: 'bottom',
                labels: {
                    color: textColor,
                    usePointStyle: true,
                    pointStyle: 'rectRounded',
                    padding: 16,
                    font: { size: 11 },
                },
            },
            tooltip: {
                backgroundColor: isDark.value ? '#1f2937' : '#ffffff',
                titleColor: isDark.value ? '#f3f4f6' : '#111827',
                bodyColor: isDark.value ? '#d1d5db' : '#4b5563',
                borderColor: isDark.value ? '#374151' : '#e5e7eb',
                borderWidth: 1,
                padding: 12,
                cornerRadius: 8,
                callbacks: {
                    label: function (context) {
                        const hours = context.parsed.x;
                        if (hours < 1) {
                            return `${context.dataset.label}: ${Math.round(hours * 60)} min`;
                        }
                        return `${context.dataset.label}: ${hours.toFixed(1)} hrs`;
                    },
                },
            },
        },
        scales: {
            x: {
                stacked: true,
                grid: {
                    color: gridColor,
                    drawBorder: false,
                },
                ticks: {
                    color: textColor,
                    font: { size: 11 },
                    callback: function (value) {
                        return value + 'h';
                    },
                },
                title: {
                    display: true,
                    text: 'Average Hours',
                    color: textColor,
                    font: { size: 12 },
                },
            },
            y: {
                stacked: true,
                grid: {
                    display: false,
                },
                ticks: {
                    display: false,
                },
            },
        },
    };
});

const totalHours = computed(() => {
    return props.stages.reduce((sum, s) => sum + s.avgHours, 0);
});
</script>

<template>
    <div>
        <div class="mb-3 flex items-center justify-between">
            <span class="text-xs text-gray-500 dark:text-gray-400">
                Total avg time: {{ totalHours.toFixed(1) }} hours
            </span>
        </div>
        <div style="height: 100px;">
            <Bar
                :data="chartData"
                :options="chartOptions"
            />
        </div>

        <!-- Stage breakdown -->
        <div class="mt-4 grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-2">
            <div
                v-for="stage in stages"
                :key="stage.name"
                class="flex items-center gap-2 px-3 py-2 rounded-lg bg-gray-50 dark:bg-gray-800/50"
            >
                <span
                    class="w-2.5 h-2.5 rounded-sm flex-shrink-0"
                    :style="{ backgroundColor: stage.color }"
                ></span>
                <div class="min-w-0">
                    <p class="text-xs font-medium text-gray-700 dark:text-gray-300 truncate">{{ stage.name }}</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        {{ stage.avgHours < 1 ? Math.round(stage.avgHours * 60) + ' min' : stage.avgHours.toFixed(1) + ' hrs' }}
                    </p>
                </div>
            </div>
        </div>
    </div>
</template>
