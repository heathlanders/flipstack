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
    horizontal: {
        type: Boolean,
        default: false,
    },
    stacked: {
        type: Boolean,
        default: false,
    },
    yAxisPrefix: {
        type: String,
        default: '',
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
            return {
                label: ds.label,
                data: ds.data,
                backgroundColor: hexToRgba(baseColor, 0.8),
                hoverBackgroundColor: baseColor,
                borderColor: baseColor,
                borderWidth: 0,
                borderRadius: 6,
                borderSkipped: false,
            };
        }),
    };
});

const chartOptions = computed(() => {
    const gridColor = isDark.value ? 'rgba(75, 85, 99, 0.3)' : 'rgba(229, 231, 235, 0.8)';
    const textColor = isDark.value ? '#9ca3af' : '#6b7280';

    const valueAxis = {
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
        stacked: props.stacked,
        beginAtZero: true,
    };

    const categoryAxis = {
        grid: {
            display: false,
        },
        ticks: {
            color: textColor,
            font: { size: 11 },
            autoSkip: true,
            maxTicksLimit: 20,
        },
        stacked: props.stacked,
    };

    return {
        indexAxis: props.horizontal ? 'y' : 'x',
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: props.datasets.length > 1,
                position: 'top',
                align: 'end',
                labels: {
                    color: textColor,
                    usePointStyle: true,
                    pointStyle: 'rectRounded',
                    padding: 16,
                    font: { size: 12 },
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
                        let value = context.parsed[props.horizontal ? 'x' : 'y'];
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
            x: props.horizontal ? valueAxis : categoryAxis,
            y: props.horizontal ? categoryAxis : valueAxis,
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
        <Bar
            :data="chartData"
            :options="chartOptions"
        />
    </div>
</template>
