<script setup>
import { computed } from 'vue';
import { Doughnut } from 'vue-chartjs';
import {
    Chart as ChartJS,
    ArcElement,
    Tooltip,
    Legend,
} from 'chart.js';

ChartJS.register(ArcElement, Tooltip, Legend);

const props = defineProps({
    labels: {
        type: Array,
        required: true,
    },
    data: {
        type: Array,
        required: true,
    },
    colors: {
        type: Array,
        default: () => ['#6366f1', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#06b6d4', '#ec4899', '#14b8a6'],
    },
    height: {
        type: Number,
        default: 250,
    },
    showLegend: {
        type: Boolean,
        default: true,
    },
    centerText: {
        type: String,
        default: null,
    },
});

const isDark = computed(() => {
    return document.documentElement.classList.contains('dark');
});

const chartData = computed(() => {
    return {
        labels: props.labels,
        datasets: [
            {
                data: props.data,
                backgroundColor: props.colors.slice(0, props.data.length),
                hoverBackgroundColor: props.colors.slice(0, props.data.length).map(c => c),
                borderColor: isDark.value ? '#1f2937' : '#ffffff',
                borderWidth: 2,
                hoverOffset: 4,
            },
        ],
    };
});

const chartOptions = computed(() => {
    const textColor = isDark.value ? '#9ca3af' : '#6b7280';

    return {
        responsive: true,
        maintainAspectRatio: false,
        cutout: '65%',
        plugins: {
            legend: {
                display: props.showLegend,
                position: 'bottom',
                labels: {
                    color: textColor,
                    usePointStyle: true,
                    pointStyle: 'circle',
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
                        const value = context.parsed;
                        const total = context.dataset.data.reduce((a, b) => a + b, 0);
                        const percentage = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                        return `${context.label}: ${value.toLocaleString()} (${percentage}%)`;
                    },
                },
            },
        },
    };
});

const centerTextPlugin = computed(() => {
    if (!props.centerText) return {};
    const dark = isDark.value;
    return {
        id: 'centerText',
        afterDraw(chart) {
            const { ctx, chartArea } = chart;
            const centerX = (chartArea.left + chartArea.right) / 2;
            const centerY = (chartArea.top + chartArea.bottom) / 2;
            ctx.save();
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            ctx.font = 'bold 18px system-ui, -apple-system, sans-serif';
            ctx.fillStyle = dark ? '#f3f4f6' : '#111827';
            ctx.fillText(props.centerText, centerX, centerY);
            ctx.restore();
        },
    };
});

const plugins = computed(() => {
    if (props.centerText) {
        return [centerTextPlugin.value];
    }
    return [];
});
</script>

<template>
    <div :style="{ height: height + 'px' }">
        <Doughnut
            :data="chartData"
            :options="chartOptions"
            :plugins="plugins"
        />
    </div>
</template>
