<script setup>
import { computed } from 'vue';

const props = defineProps({
    stages: {
        type: Array,
        required: true,
        // [{ label, count, rate }]
    },
});

const maxCount = computed(() => {
    if (!props.stages.length) return 1;
    return Math.max(...props.stages.map(s => s.count));
});

function widthPercent(count) {
    if (maxCount.value === 0) return 0;
    return Math.max((count / maxCount.value) * 100, 8);
}

const defaultColors = [
    'bg-indigo-500',
    'bg-blue-500',
    'bg-cyan-500',
    'bg-teal-500',
    'bg-emerald-500',
    'bg-green-500',
    'bg-lime-500',
];

function barColor(index) {
    return defaultColors[index % defaultColors.length];
}
</script>

<template>
    <div>
        <!-- Empty state -->
        <div
            v-if="!stages || stages.length === 0"
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
                    d="M3 4h13M3 8h9m-9 4h6m4 0l4-4m0 0l4 4m-4-4v12"
                />
            </svg>
            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">No funnel data available</p>
        </div>

        <!-- Funnel bars -->
        <div v-else class="space-y-3">
            <div
                v-for="(stage, index) in stages"
                :key="stage.label"
                class="flex items-center gap-4"
            >
                <!-- Label -->
                <div class="w-24 flex-shrink-0 text-right">
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">
                        {{ stage.label }}
                    </span>
                </div>

                <!-- Bar container -->
                <div class="flex-1 relative">
                    <div
                        class="h-10 rounded-lg flex items-center justify-between px-3 transition-all duration-300"
                        :class="barColor(index)"
                        :style="{ width: widthPercent(stage.count) + '%', minWidth: '60px' }"
                    >
                        <span class="text-sm font-bold text-white">
                            {{ stage.count.toLocaleString() }}
                        </span>
                        <span
                            v-if="stage.rate !== undefined && stage.rate !== null"
                            class="text-xs font-medium text-white/80"
                        >
                            {{ stage.rate }}%
                        </span>
                    </div>
                </div>
            </div>

            <!-- Drop-off indicators between stages -->
            <div
                v-if="stages.length > 1"
                class="mt-4 pt-4 border-t border-gray-100 dark:border-gray-700"
            >
                <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-2">
                    Stage Conversion
                </p>
                <div class="flex flex-wrap gap-3">
                    <div
                        v-for="(stage, index) in stages.slice(1)"
                        :key="'conv-' + stage.label"
                        class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-gray-50 dark:bg-gray-800/50 text-xs"
                    >
                        <span class="text-gray-500 dark:text-gray-400">
                            {{ stages[index].label }}
                        </span>
                        <svg class="w-3 h-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                        </svg>
                        <span class="text-gray-500 dark:text-gray-400">
                            {{ stage.label }}
                        </span>
                        <span
                            class="font-bold"
                            :class="(stage.rate || 0) >= 50
                                ? 'text-emerald-600 dark:text-emerald-400'
                                : 'text-amber-600 dark:text-amber-400'"
                        >
                            {{ stage.rate !== undefined ? stage.rate + '%' : '--' }}
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
