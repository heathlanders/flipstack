<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';

const props = defineProps({
    initialRange: {
        type: Object,
        default: () => ({
            start_date: null,
            end_date: null,
        }),
    },
});

const emit = defineEmits(['change']);

const isOpen = ref(false);
const dropdownRef = ref(null);

const selectedRange = ref({
    start_date: props.initialRange?.start_date || null,
    end_date: props.initialRange?.end_date || null,
});

const presetRanges = [
    { label: 'Today', getValue: () => ({ start_date: formatDate(new Date()), end_date: formatDate(new Date()) }) },
    { label: 'Yesterday', getValue: () => {
        const d = new Date();
        d.setDate(d.getDate() - 1);
        return { start_date: formatDate(d), end_date: formatDate(d) };
    }},
    { label: 'Last 7 Days', getValue: () => {
        const end = new Date();
        const start = new Date();
        start.setDate(start.getDate() - 6);
        return { start_date: formatDate(start), end_date: formatDate(end) };
    }},
    { label: 'Last 30 Days', getValue: () => {
        const end = new Date();
        const start = new Date();
        start.setDate(start.getDate() - 29);
        return { start_date: formatDate(start), end_date: formatDate(end) };
    }},
    { label: 'This Month', getValue: () => {
        const now = new Date();
        const start = new Date(now.getFullYear(), now.getMonth(), 1);
        return { start_date: formatDate(start), end_date: formatDate(now) };
    }},
    { label: 'Last Month', getValue: () => {
        const now = new Date();
        const start = new Date(now.getFullYear(), now.getMonth() - 1, 1);
        const end = new Date(now.getFullYear(), now.getMonth(), 0);
        return { start_date: formatDate(start), end_date: formatDate(end) };
    }},
    { label: 'This Quarter', getValue: () => {
        const now = new Date();
        const quarter = Math.floor(now.getMonth() / 3);
        const start = new Date(now.getFullYear(), quarter * 3, 1);
        return { start_date: formatDate(start), end_date: formatDate(now) };
    }},
    { label: 'This Year', getValue: () => {
        const now = new Date();
        const start = new Date(now.getFullYear(), 0, 1);
        return { start_date: formatDate(start), end_date: formatDate(now) };
    }},
];

function formatDate(date) {
    return date.toISOString().split('T')[0];
}

function formatDisplayDate(dateString) {
    if (!dateString) return '';
    const date = new Date(dateString + 'T00:00:00');
    return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
}

const displayText = computed(() => {
    if (!selectedRange.value.start_date || !selectedRange.value.end_date) {
        return 'Select date range';
    }
    
    if (selectedRange.value.start_date === selectedRange.value.end_date) {
        return formatDisplayDate(selectedRange.value.start_date);
    }
    
    return `${formatDisplayDate(selectedRange.value.start_date)} - ${formatDisplayDate(selectedRange.value.end_date)}`;
});

function selectPreset(preset) {
    const range = preset.getValue();
    selectedRange.value = range;
    emit('change', range);
    isOpen.value = false;
}

function applyCustomRange() {
    if (selectedRange.value.start_date && selectedRange.value.end_date) {
        emit('change', { ...selectedRange.value });
        isOpen.value = false;
    }
}

function handleClickOutside(event) {
    if (dropdownRef.value && !dropdownRef.value.contains(event.target)) {
        isOpen.value = false;
    }
}

onMounted(() => {
    document.addEventListener('click', handleClickOutside);
});

onUnmounted(() => {
    document.removeEventListener('click', handleClickOutside);
});

watch(() => props.initialRange, (newRange) => {
    selectedRange.value = { ...newRange };
}, { deep: true });
</script>

<template>
    <div class="relative" ref="dropdownRef">
        <button
            @click="isOpen = !isOpen"
            class="inline-flex items-center px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 dark:bg-gray-800 dark:text-gray-200 dark:border-gray-600 dark:hover:bg-gray-700"
        >
            <svg class="w-4 h-4 mr-2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
            </svg>
            <span class="truncate max-w-[200px]">{{ displayText }}</span>
            <svg class="w-4 h-4 ml-2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
        </button>

        <!-- Dropdown -->
        <Transition
            enter-active-class="transition ease-out duration-100"
            enter-from-class="transform opacity-0 scale-95"
            enter-to-class="transform opacity-100 scale-100"
            leave-active-class="transition ease-in duration-75"
            leave-from-class="transform opacity-100 scale-100"
            leave-to-class="transform opacity-0 scale-95"
        >
            <div
                v-if="isOpen"
                class="absolute right-0 z-50 mt-2 w-80 origin-top-right rounded-xl bg-white dark:bg-gray-800 shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none"
            >
                <div class="p-4">
                    <!-- Preset Ranges -->
                    <div class="mb-4">
                        <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-2">
                            Quick Select
                        </p>
                        <div class="grid grid-cols-2 gap-2">
                            <button
                                v-for="preset in presetRanges"
                                :key="preset.label"
                                @click="selectPreset(preset)"
                                class="px-3 py-2 text-xs font-medium text-gray-700 dark:text-gray-300 bg-gray-50 dark:bg-gray-700/50 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors text-left"
                            >
                                {{ preset.label }}
                            </button>
                        </div>
                    </div>

                    <!-- Custom Range -->
                    <div class="border-t border-gray-100 dark:border-gray-700 pt-4">
                        <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-2">
                            Custom Range
                        </p>
                        <div class="space-y-3">
                            <div>
                                <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Start Date</label>
                                <input
                                    type="date"
                                    v-model="selectedRange.start_date"
                                    class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                />
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">End Date</label>
                                <input
                                    type="date"
                                    v-model="selectedRange.end_date"
                                    :min="selectedRange.start_date"
                                    class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                />
                            </div>
                            <button
                                @click="applyCustomRange"
                                :disabled="!selectedRange.start_date || !selectedRange.end_date"
                                class="w-full px-4 py-2 text-sm font-medium text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                            >
                                Apply Range
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </Transition>
    </div>
</template>
