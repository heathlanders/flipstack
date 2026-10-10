<script setup>
import { computed } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import MovementHistoryTable from '@/Components/Inventory/MovementHistoryTable.vue';

const props = defineProps({
    movements: Object,
    filters: Object,
    filterOptions: Object,
    permissions: Object,
});

const stats = computed(() => {
    const data = props.movements.data || [];
    return {
        total: props.movements.total || 0,
        today: data.filter(m => {
            const created = new Date(m.created_at);
            const today = new Date();
            return created.toDateString() === today.toDateString();
        }).length,
        purchases: data.filter(m => m.type === 'purchase').length,
        sales: data.filter(m => m.type === 'sale').length,
    };
});

const handleFilterChange = (filters) => {
    router.get(route('inventory.movements.index'), filters, {
        preserveState: true,
        preserveScroll: true,
    });
};

const handleSortChange = (sort) => {
    router.get(route('inventory.movements.index'), { ...props.filters, ...sort }, {
        preserveState: true,
        preserveScroll: true,
    });
};

const handlePageChange = (page) => {
    router.get(route('inventory.movements.index'), { ...props.filters, page }, {
        preserveState: true,
        preserveScroll: true,
    });
};

const goToCreate = () => {
    router.visit(route('inventory.movements.create'));
};
</script>

<template>
    <AppLayout title="Inventory Movements">
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200">
                        Inventory Movements
                    </h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                        Track and manage all stock movements
                    </p>
                </div>
                <button
                    v-if="permissions.can_create"
                    @click="goToCreate"
                    class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors flex items-center gap-2"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    New Movement
                </button>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <!-- Quick Stats -->
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                    <div class="bg-white dark:bg-gray-800 rounded-xl p-4 shadow-sm border border-gray-200 dark:border-gray-700">
                        <p class="text-sm text-gray-500 dark:text-gray-400">Total Movements</p>
                        <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ stats.total }}</p>
                    </div>
                    <div class="bg-white dark:bg-gray-800 rounded-xl p-4 shadow-sm border border-gray-200 dark:border-gray-700">
                        <p class="text-sm text-gray-500 dark:text-gray-400">Today</p>
                        <p class="text-2xl font-bold text-indigo-600 dark:text-indigo-400">{{ stats.today }}</p>
                    </div>
                    <div class="bg-white dark:bg-gray-800 rounded-xl p-4 shadow-sm border border-gray-200 dark:border-gray-700">
                        <p class="text-sm text-gray-500 dark:text-gray-400">Purchases</p>
                        <p class="text-2xl font-bold text-green-600 dark:text-green-400">{{ stats.purchases }}</p>
                    </div>
                    <div class="bg-white dark:bg-gray-800 rounded-xl p-4 shadow-sm border border-gray-200 dark:border-gray-700">
                        <p class="text-sm text-gray-500 dark:text-gray-400">Sales</p>
                        <p class="text-2xl font-bold text-blue-600 dark:text-blue-400">{{ stats.sales }}</p>
                    </div>
                </div>

                <!-- Movements Table -->
                <MovementHistoryTable
                    :movements="movements"
                    :filters="filters"
                    :filter-options="filterOptions"
                    :permissions="permissions"
                    @filter-change="handleFilterChange"
                    @sort-change="handleSortChange"
                    @page-change="handlePageChange"
                />
            </div>
        </div>
    </AppLayout>
</template>
