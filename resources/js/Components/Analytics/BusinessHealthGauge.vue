<script setup>
import { computed } from 'vue';

const props = defineProps({
    score: {
        type: Number,
        required: true,
        validator: (v) => v >= 0 && v <= 100,
    },
    dimensions: {
        type: Object,
        default: () => ({}),
    },
    alerts: {
        type: Array,
        default: () => [],
    },
    compact: {
        type: Boolean,
        default: false,
    },
});

const circumference = 2 * Math.PI * 50; // r=50

const dashOffset = computed(() => {
    const progress = props.score / 100;
    return circumference - (progress * circumference);
});

const gaugeColor = computed(() => {
    if (props.score < 40) return '#ef4444';
    if (props.score < 60) return '#f59e0b';
    if (props.score < 80) return '#eab308';
    return '#10b981';
});

const healthLevel = computed(() => {
    if (props.score < 40) return 'Critical';
    if (props.score < 60) return 'Needs Attention';
    if (props.score < 80) return 'Good';
    return 'Excellent';
});

function dimensionColor(value) {
    if (value < 40) return 'bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400';
    if (value < 60) return 'bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-400';
    if (value < 80) return 'bg-yellow-100 dark:bg-yellow-900/30 text-yellow-700 dark:text-yellow-400';
    return 'bg-emerald-100 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-400';
}

function alertBadgeClass(severity) {
    if (severity === 'critical') return 'bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400 border-red-200 dark:border-red-800';
    if (severity === 'warning') return 'bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-400 border-amber-200 dark:border-amber-800';
    return 'bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-400 border-blue-200 dark:border-blue-800';
}

const dimensionEntries = computed(() => {
    return Object.entries(props.dimensions).map(([key, value]) => ({
        name: key.charAt(0).toUpperCase() + key.slice(1).replace(/_/g, ' '),
        value,
    }));
});

const criticalAlerts = computed(() => props.alerts.filter(a => a.severity === 'critical'));
const warningAlerts = computed(() => props.alerts.filter(a => a.severity === 'warning'));
</script>

<template>
    <div class="flex flex-col items-center">
        <!-- Gauge SVG -->
        <div class="w-40 h-40">
            <svg viewBox="0 0 120 120" class="w-full h-full">
                <!-- Background circle -->
                <circle
                    cx="60"
                    cy="60"
                    r="50"
                    fill="none"
                    stroke="#e5e7eb"
                    class="dark:stroke-gray-700"
                    stroke-width="10"
                />
                <!-- Foreground arc -->
                <circle
                    cx="60"
                    cy="60"
                    r="50"
                    fill="none"
                    :stroke="gaugeColor"
                    stroke-width="10"
                    stroke-linecap="round"
                    :stroke-dasharray="circumference"
                    :stroke-dashoffset="dashOffset"
                    transform="rotate(-90 60 60)"
                    class="transition-all duration-700 ease-out"
                />
                <!-- Score text -->
                <text
                    x="60"
                    y="55"
                    text-anchor="middle"
                    dominant-baseline="middle"
                    class="text-3xl font-bold fill-gray-900 dark:fill-white"
                    style="font-size: 24px; font-weight: 700;"
                >
                    {{ score }}
                </text>
                <!-- Health level label -->
                <text
                    x="60"
                    y="72"
                    text-anchor="middle"
                    dominant-baseline="middle"
                    class="fill-gray-500 dark:fill-gray-400"
                    style="font-size: 9px;"
                >
                    {{ healthLevel }}
                </text>
            </svg>
        </div>

        <p class="mt-1 text-sm font-medium text-gray-700 dark:text-gray-300">Business Health</p>

        <!-- Dimension pills -->
        <div
            v-if="!compact && dimensionEntries.length > 0"
            class="mt-4 w-full grid grid-cols-2 sm:grid-cols-3 gap-2"
        >
            <div
                v-for="dim in dimensionEntries"
                :key="dim.name"
                class="flex items-center justify-between px-3 py-2 rounded-lg text-xs font-medium"
                :class="dimensionColor(dim.value)"
            >
                <span class="truncate mr-2">{{ dim.name }}</span>
                <span class="font-bold">{{ dim.value }}</span>
            </div>
        </div>

        <!-- Alerts -->
        <div
            v-if="!compact && alerts.length > 0"
            class="mt-4 w-full space-y-2"
        >
            <div
                v-for="(alert, index) in alerts"
                :key="index"
                class="flex items-start gap-2 px-3 py-2 rounded-lg border text-xs"
                :class="alertBadgeClass(alert.severity)"
            >
                <svg
                    v-if="alert.severity === 'critical'"
                    class="w-4 h-4 flex-shrink-0 mt-0.5"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                >
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
                <svg
                    v-else
                    class="w-4 h-4 flex-shrink-0 mt-0.5"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                >
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span>{{ alert.message }}</span>
            </div>
        </div>
    </div>
</template>
