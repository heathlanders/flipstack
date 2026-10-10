<script setup>
import { computed } from 'vue';
import { Line } from 'vue-chartjs';
import {
    Chart as ChartJS,
    CategoryScale,
    LinearScale,
    PointElement,
    LineElement,
    Title,
    Tooltip,
    Legend,
    Filler,
} from 'chart.js';

ChartJS.register(CategoryScale, LinearScale, PointElement, LineElement, Title, Tooltip, Legend, Filler);

const props = defineProps({
    labels: {
        type: Array,
        required: true,
    },
    datasets: {
        type: Array,
        required: true,
    },
    height: {
        type: Number,
        default: 300,
    },
    yAxisPrefix: {
        type: String,
        default: '',
    },
    showLegend: {
        type: Boolean,
        default: true,
    },
});

const defaultColors = ['#6366f1', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#06b6d4'];

const isDark = computed(() => {
    return document.documentElement.classList.contains('dark');
});

const chartData = computed(() => {
    return {
        labels: props.labels,
        datasets: props.datasets.map((ds, index) => {
            const baseColor = ds.color || defaultColors[index % defaultColors.length];
            const isDashed = ds.dashed || false;

            return {
                label: ds.label,
                data: ds.data,
                borderColor: baseColor,
                backgroundColor: hexToRgba(baseColor, isDashed ? 0.05 : 0.1),
                borderWidth: 2,
                borderDash: isDashed ? [6, 4] : [],
                pointRadius: 3,
                pointHoverRadius: 5,
                pointBackgroundColor: baseColor,
                pointBorderColor: isDark.value ? '#1f2937' : '#ffffff',
                pointBorderWidth: 2,
                tension: 0.3,
                fill: true,
            };
        }),
    };
});

const chartOptions = computed(() => {
    const gridColor = isDark.value ? 'rgba(75, 85, 99, 0.3)' : 'rgba(229, 231, 235, 0.8)';
    const textColor = isDark.value ? '#9ca3af' : '#6b7280';

    return {
        responsive: true,
        maintainAspectRatio: false,
        interaction: {
            mode: 'index',
            intersect: false,
        },
        plugins: {
            legend: {
                display: props.showLegend,
                position: 'top',
                align: 'end',
                labels: {
                    color: textColor,
                    usePointStyle: true,
                    pointStyle: 'circle',
                    padding: 16,
                    font: {
                        size: 12,
                    },
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
                        let value = context.parsed.y;
                        if (value !== null && value !== undefined) {
                            const formatted = typeof value === 'number'
                                ? value.toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 2 })
                                : value;
                            return `${context.dataset.label}: ${props.yAxisPrefix}${formatted}`;
                        }
                        return context.dataset.label;
                    },
                },
            },
        },
        scales: {
            x: {
                grid: {
                    color: gridColor,
                    drawBorder: false,
                },
                ticks: {
                    color: textColor,
                    font: { size: 11 },
                    maxRotation: 0,
                    autoSkip: true,
                    maxTicksLimit: 12,
                },
            },
            y: {
                grid: {
                    color: gridColor,
                    drawBorder: false,
                },
                ticks: {
                    color: textColor,
                    font: { size: 11 },
                    callback: function (value) {
                        return props.yAxisPrefix + value.toLocaleString();
                    },
                },
                beginAtZero: true,
            },
        },
    };
});

function hexToRgba(hex, alpha) {
    const r = parseInt(hex.slice(1, 3), 16);
    const g = parseInt(hex.slice(3, 5), 16);
    const b = parseInt(hex.slice(5, 7), 16);
    return `rgba(${r}, ${g}, ${b}, ${alpha})`;
}
</script>

<template>
    <div :style="{ height: height + 'px' }">
        <Line
            :data="chartData"
            :options="chartOptions"
        />
    </div>
</template>
