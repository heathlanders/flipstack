<script setup>
import { ref, reactive, computed, watch } from 'vue';

const props = defineProps({
    filters: {
        type: Object,
        default: () => ({
            start_date: null,
            end_date: null,
            compare_start_date: null,
            compare_end_date: null,
        }),
    },
});

const emit = defineEmits(['change']);

const localRange = reactive({
    start_date: props.filters.start_date || '',
    end_date: props.filters.end_date || '',
});

const compareRange = reactive({
    start_date: props.filters.compare_start_date || '',
    end_date: props.filters.compare_end_date || '',
});

const isComparing = ref(!!props.filters.compare_start_date);
const activePreset = ref(null);

const presets = [
    { label: 'Today', days: 0 },
    { label: '7d', days: 6 },
    { label: '30d', days: 29 },
    { label: '90d', days: 89 },
    { label: 'YTD', days: null, ytd: true },
    { label: '1Y', days: 364 },
    { label: 'All', days: null, allTime: true },
];

watch(() => props.filters, (newVal) => {
    localRange.start_date = newVal.start_date || '';
    localRange.end_date = newVal.end_date || '';
    compareRange.start_date = newVal.compare_start_date || '';
    compareRange.end_date = newVal.compare_end_date || '';
    isComparing.value = !!newVal.compare_start_date;
}, { deep: true });

function selectPreset(preset, index) {
    activePreset.value = index;
    const end = new Date();
    let start;
    if (preset.allTime) {
        // Anchor at a far-past date so backend whereBetween still hits.
        start = new Date('2000-01-01');
    } else if (preset.ytd) {
        start = new Date(end.getFullYear(), 0, 1);
    } else if (preset.days === 0) {
        start = new Date();
    } else {
        start = new Date();
        start.setDate(start.getDate() - preset.days);
    }
    localRange.start_date = start.toISOString().split('T')[0];
    localRange.end_date = end.toISOString().split('T')[0];

    if (isComparing.value) {
        autoSetPreviousPeriod();
    }

    // Auto-apply on preset click — feels snappier than requiring a separate Apply tap
    applyFilters();
}

function toggleCompare() {
    isComparing.value = !isComparing.value;
    if (isComparing.value) {
        autoSetPreviousPeriod();
    } else {
        compareRange.start_date = '';
        compareRange.end_date = '';
    }
}

function autoSetPreviousPeriod() {
    if (!localRange.start_date || !localRange.end_date) return;
    const start = new Date(localRange.start_date + 'T00:00:00');
    const end = new Date(localRange.end_date + 'T00:00:00');
    const diffDays = Math.round((end - start) / (1000 * 60 * 60 * 24));
    const compEnd = new Date(start);
    compEnd.setDate(compEnd.getDate() - 1);
    const compStart = new Date(compEnd);
    compStart.setDate(compStart.getDate() - diffDays);
    compareRange.start_date = compStart.toISOString().split('T')[0];
    compareRange.end_date = compEnd.toISOString().split('T')[0];
}

function autoSetSamePeriodLastYear() {
    if (!localRange.start_date || !localRange.end_date) return;
    const start = new Date(localRange.start_date + 'T00:00:00');
    const end = new Date(localRange.end_date + 'T00:00:00');
    const compStart = new Date(start);
    compStart.setFullYear(compStart.getFullYear() - 1);
    const compEnd = new Date(end);
    compEnd.setFullYear(compEnd.getFullYear() - 1);
    compareRange.start_date = compStart.toISOString().split('T')[0];
    compareRange.end_date = compEnd.toISOString().split('T')[0];
}

function applyFilters() {
    const result = {
        start_date: localRange.start_date,
        end_date: localRange.end_date,
    };
    if (isComparing.value && compareRange.start_date) {
        result.compare_start_date = compareRange.start_date;
        result.compare_end_date = compareRange.end_date;
    }
    emit('change', result);
}
</script>

<template>
    <div class="space-y-3">
        <!-- Primary row -->
        <div class="flex flex-wrap items-center gap-2">
            <!-- Preset buttons -->
            <div class="flex items-center gap-1">
                <button
                    v-for="(preset, index) in presets"
                    :key="preset.label"
                    @click="selectPreset(preset, index)"
                    class="px-2.5 py-1 text-xs font-medium rounded-md transition-colors"
                    :class="activePreset === index
                        ? 'bg-indigo-600 text-white'
                        : 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600'"
                >
                    {{ preset.label }}
                </button>
            </div>

            <!-- Date inputs -->
            <div class="flex items-center gap-2">
                <input
                    type="date"
                    v-model="localRange.start_date"
                    class="px-2.5 py-1 text-sm border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                />
                <span class="text-xs text-gray-500 dark:text-gray-400">to</span>
                <input
                    type="date"
                    v-model="localRange.end_date"
                    :min="localRange.start_date"
                    class="px-2.5 py-1 text-sm border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                />
            </div>

            <!-- Compare toggle -->
            <button
                @click="toggleCompare"
                class="px-3 py-1 text-xs font-medium rounded-md border transition-colors"
                :class="isComparing
                    ? 'bg-indigo-600 border-indigo-600 text-white'
                    : 'border-gray-300 dark:border-gray-600 text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700'"
            >
                Compare
            </button>

            <!-- Apply -->
            <button
                @click="applyFilters"
                class="px-4 py-1 text-xs font-medium text-white bg-indigo-600 rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-colors"
            >
                Apply
            </button>
        </div>

        <!-- Comparison row -->
        <div
            v-if="isComparing"
            class="flex flex-wrap items-center gap-2 pl-1 border-l-2 border-indigo-400 dark:border-indigo-500"
        >
            <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Compare:</span>
            <input
                type="date"
                v-model="compareRange.start_date"
                class="px-2.5 py-1 text-sm border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
            />
            <span class="text-xs text-gray-500 dark:text-gray-400">to</span>
            <input
                type="date"
                v-model="compareRange.end_date"
                :min="compareRange.start_date"
                class="px-2.5 py-1 text-sm border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
            />
            <div class="flex items-center gap-1">
                <button
                    @click="autoSetPreviousPeriod"
                    class="px-2 py-1 text-xs font-medium rounded-md bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors"
                >
                    Previous Period
                </button>
                <button
                    @click="autoSetSamePeriodLastYear"
                    class="px-2 py-1 text-xs font-medium rounded-md bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors"
                >
                    Same Period Last Year
                </button>
            </div>
        </div>
    </div>
</template>
