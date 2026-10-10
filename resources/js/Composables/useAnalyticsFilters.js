import { ref, reactive } from 'vue';
import { router } from '@inertiajs/vue3';

export function useAnalyticsFilters(routeName, initialFilters = {}) {
    const dateRange = reactive({
        start_date: initialFilters.start_date || new Date(new Date().setDate(new Date().getDate() - 29)).toISOString().split('T')[0],
        end_date: initialFilters.end_date || new Date().toISOString().split('T')[0],
    });

    const comparisonRange = reactive({
        start_date: initialFilters.compare_start_date || null,
        end_date: initialFilters.compare_end_date || null,
    });

    const isComparing = ref(!!initialFilters.compare_start_date);

    const presets = [
        { label: 'Today', days: 0 },
        { label: '7 Days', days: 6 },
        { label: '30 Days', days: 29 },
        { label: '90 Days', days: 89 },
        { label: 'YTD', days: null, ytd: true },
        { label: '1 Year', days: 364 },
    ];

    function applyPreset(preset) {
        const end = new Date();
        let start;
        if (preset.ytd) {
            start = new Date(end.getFullYear(), 0, 1);
        } else if (preset.days === 0) {
            start = new Date();
        } else {
            start = new Date();
            start.setDate(start.getDate() - preset.days);
        }
        dateRange.start_date = start.toISOString().split('T')[0];
        dateRange.end_date = end.toISOString().split('T')[0];

        if (isComparing.value) {
            autoSetComparison();
        }
    }

    function toggleComparison() {
        isComparing.value = !isComparing.value;
        if (isComparing.value) {
            autoSetComparison();
        } else {
            comparisonRange.start_date = null;
            comparisonRange.end_date = null;
        }
    }

    function autoSetComparison() {
        // Previous period of same length
        const start = new Date(dateRange.start_date + 'T00:00:00');
        const end = new Date(dateRange.end_date + 'T00:00:00');
        const diffDays = Math.round((end - start) / (1000 * 60 * 60 * 24));
        const compEnd = new Date(start);
        compEnd.setDate(compEnd.getDate() - 1);
        const compStart = new Date(compEnd);
        compStart.setDate(compStart.getDate() - diffDays);
        comparisonRange.start_date = compStart.toISOString().split('T')[0];
        comparisonRange.end_date = compEnd.toISOString().split('T')[0];
    }

    function apply() {
        const params = { ...dateRange };
        if (isComparing.value && comparisonRange.start_date) {
            params.compare_start_date = comparisonRange.start_date;
            params.compare_end_date = comparisonRange.end_date;
        }
        router.get(routeName, params, { preserveState: true, preserveScroll: true });
    }

    return {
        dateRange,
        comparisonRange,
        isComparing,
        presets,
        applyPreset,
        toggleComparison,
        autoSetComparison,
        apply,
    };
}
