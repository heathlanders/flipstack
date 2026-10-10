import { ref, computed, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import { debounce } from 'lodash';

export function useProductFilters(initialFilters = {}, routeName = 'inventory.products.index') {
    // State
    const filters = ref({
        search: initialFilters.search || '',
        category_id: initialFilters.category_id || null,
        brand_id: initialFilters.brand_id || null,
        status: initialFilters.status || null,
        stock_status: initialFilters.stock_status || null,
        condition: initialFilters.condition || null,
        location_id: initialFilters.location_id || null,
        min_price: initialFilters.min_price || null,
        max_price: initialFilters.max_price || null,
        created_from: initialFilters.created_from || null,
        created_to: initialFilters.created_to || null,
        sort_by: initialFilters.sort_by || 'created_at',
        sort_order: initialFilters.sort_order || 'desc',
        per_page: initialFilters.per_page || 20,
        view_mode: initialFilters.view_mode || 'grid',
    });

    const isLoading = ref(false);

    // Computed
    const hasActiveFilters = computed(() => {
        return !!(
            filters.value.category_id ||
            filters.value.brand_id ||
            filters.value.status ||
            filters.value.stock_status ||
            filters.value.condition ||
            filters.value.location_id ||
            filters.value.min_price ||
            filters.value.max_price ||
            filters.value.created_from ||
            filters.value.created_to
        );
    });

    const activeFilterCount = computed(() => {
        let count = 0;
        if (filters.value.category_id) count++;
        if (filters.value.brand_id) count++;
        if (filters.value.status) count++;
        if (filters.value.stock_status) count++;
        if (filters.value.condition) count++;
        if (filters.value.location_id) count++;
        if (filters.value.min_price || filters.value.max_price) count++;
        if (filters.value.created_from || filters.value.created_to) count++;
        return count;
    });

    const cleanFilters = computed(() => {
        return Object.fromEntries(
            Object.entries(filters.value).filter(([_, v]) => v !== null && v !== '')
        );
    });

    // Methods
    const applyFilters = debounce(() => {
        isLoading.value = true;

        router.get(route(routeName), cleanFilters.value, {
            preserveState: true,
            preserveScroll: true,
            onFinish: () => {
                isLoading.value = false;
            },
        });
    }, 300);

    const updateFilter = (key, value) => {
        filters.value[key] = value;
        applyFilters();
    };

    const setFilters = (newFilters) => {
        filters.value = { ...filters.value, ...newFilters };
        applyFilters();
    };

    const clearFilters = () => {
        filters.value = {
            search: '',
            category_id: null,
            brand_id: null,
            status: null,
            stock_status: null,
            condition: null,
            location_id: null,
            min_price: null,
            max_price: null,
            created_from: null,
            created_to: null,
            sort_by: 'created_at',
            sort_order: 'desc',
            per_page: 20,
            view_mode: filters.value.view_mode,
        };
        applyFilters();
    };

    const toggleSort = (field) => {
        if (filters.value.sort_by === field) {
            filters.value.sort_order = filters.value.sort_order === 'asc' ? 'desc' : 'asc';
        } else {
            filters.value.sort_by = field;
            filters.value.sort_order = 'desc';
        }
        applyFilters();
    };

    const setViewMode = (mode) => {
        filters.value.view_mode = mode;
        applyFilters();
    };

    const setPerPage = (value) => {
        filters.value.per_page = value;
        applyFilters();
    };

    return {
        filters,
        isLoading,
        hasActiveFilters,
        activeFilterCount,
        cleanFilters,
        applyFilters,
        updateFilter,
        setFilters,
        clearFilters,
        toggleSort,
        setViewMode,
        setPerPage,
    };
}
