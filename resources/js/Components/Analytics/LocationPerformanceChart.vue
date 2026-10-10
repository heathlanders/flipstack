<script setup>
import { computed, ref } from 'vue';

const props = defineProps({
    data: {
        type: Array,
        default: () => [],
    },
});

const sortBy = ref('total_value');
const sortOrder = ref('desc');
const selectedLocation = ref(null);

const sortOptions = [
    { value: 'total_value', label: 'Total Value' },
    { value: 'total_units', label: 'Total Units' },
    { value: 'product_count', label: 'Product Count' },
    { value: 'movement_count', label: 'Activity' },
];

const sortedData = computed(() => {
    if (!props.data || props.data.length === 0) return [];
    
    return [...props.data].sort((a, b) => {
        const aVal = a[sortBy.value] || 0;
        const bVal = b[sortBy.value] || 0;
        return sortOrder.value === 'desc' ? bVal - aVal : aVal - bVal;
    });
});

const maxValues = computed(() => {
    if (!props.data || props.data.length === 0) return { value: 1, units: 1, products: 1 };
    
    return {
        value: Math.max(...props.data.map(l => l.total_value || 0)),
        units: Math.max(...props.data.map(l => l.total_units || 0)),
        products: Math.max(...props.data.map(l => l.product_count || 0)),
        movements: Math.max(...props.data.map(l => l.movement_count || 0)),
    };
});

const totals = computed(() => {
    if (!props.data) return { value: 0, units: 0, products: 0, locations: 0 };
    
    return {
        value: props.data.reduce((sum, l) => sum + (l.total_value || 0), 0),
        units: props.data.reduce((sum, l) => sum + (l.total_units || 0), 0),
        products: props.data.reduce((sum, l) => sum + (l.product_count || 0), 0),
        locations: props.data.length,
    };
});

const locationColors = [
    { bg: '#6366F1', gradient: 'from-indigo-500 to-indigo-600' },
    { bg: '#8B5CF6', gradient: 'from-violet-500 to-violet-600' },
    { bg: '#EC4899', gradient: 'from-pink-500 to-pink-600' },
    { bg: '#F59E0B', gradient: 'from-amber-500 to-amber-600' },
    { bg: '#10B981', gradient: 'from-emerald-500 to-emerald-600' },
    { bg: '#06B6D4', gradient: 'from-cyan-500 to-cyan-600' },
    { bg: '#3B82F6', gradient: 'from-blue-500 to-blue-600' },
    { bg: '#EF4444', gradient: 'from-red-500 to-red-600' },
];

function getLocationColor(index) {
    return locationColors[index % locationColors.length];
}

function formatCurrency(amount) {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
    }).format(amount);
}

function formatNumber(num) {
    return new Intl.NumberFormat('en-US').format(num);
}

function getValuePercentage(value) {
    return totals.value.value > 0 ? ((value / totals.value.value) * 100).toFixed(1) : 0;
}

function toggleSort(field) {
    if (sortBy.value === field) {
        sortOrder.value = sortOrder.value === 'desc' ? 'asc' : 'desc';
    } else {
        sortBy.value = field;
        sortOrder.value = 'desc';
    }
}
</script>

<template>
    <div>
        <!-- Empty State -->
        <div 
            v-if="!sortedData || sortedData.length === 0"
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
                    d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"
                />
                <path 
                    stroke-linecap="round" 
                    stroke-linejoin="round" 
                    stroke-width="2" 
                    d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"
                />
            </svg>
            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">No location data available</p>
        </div>

        <div v-else>
            <!-- Header with Totals -->
            <div class="grid grid-cols-4 gap-4 mb-6">
                <div class="text-center p-3 rounded-xl bg-gray-50 dark:bg-gray-800/50">
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">
                        {{ totals.locations }}
                    </p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Locations</p>
                </div>
                <div class="text-center p-3 rounded-xl bg-gray-50 dark:bg-gray-800/50">
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">
                        {{ formatCurrency(totals.value) }}
                    </p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Total Value</p>
                </div>
                <div class="text-center p-3 rounded-xl bg-gray-50 dark:bg-gray-800/50">
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">
                        {{ formatNumber(totals.units) }}
                    </p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Total Units</p>
                </div>
                <div class="text-center p-3 rounded-xl bg-gray-50 dark:bg-gray-800/50">
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">
                        {{ formatNumber(totals.products) }}
                    </p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Total SKUs</p>
                </div>
            </div>

            <!-- Sort Controls -->
            <div class="flex items-center gap-2 mb-4">
                <span class="text-xs text-gray-500 dark:text-gray-400">Sort by:</span>
                <div class="flex items-center gap-1 bg-gray-100 dark:bg-gray-800 rounded-lg p-1">
                    <button
                        v-for="option in sortOptions"
                        :key="option.value"
                        class="px-3 py-1.5 text-xs font-medium rounded-md transition-all flex items-center gap-1"
                        :class="sortBy === option.value 
                            ? 'bg-white dark:bg-gray-700 text-gray-900 dark:text-white shadow-sm' 
                            : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white'"
                        @click="toggleSort(option.value)"
                    >
                        {{ option.label }}
                        <svg 
                            v-if="sortBy === option.value"
                            class="w-3 h-3 transition-transform"
                            :class="{ 'rotate-180': sortOrder === 'asc' }"
                            fill="none" 
                            stroke="currentColor" 
                            viewBox="0 0 24 24"
                        >
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>
                </div>
            </div>

            <!-- Location List with Bars -->
            <div class="space-y-3">
                <div 
                    v-for="(location, index) in sortedData" 
                    :key="location.location_id || index"
                    class="p-4 rounded-2xl border transition-all cursor-pointer hover:shadow-md"
                    :class="selectedLocation === location.location_id 
                        ? 'border-indigo-300 dark:border-indigo-700 bg-indigo-50/50 dark:bg-indigo-900/20' 
                        : 'border-gray-100 dark:border-gray-800 hover:border-gray-200 dark:hover:border-gray-700'"
                    @click="selectedLocation = selectedLocation === location.location_id ? null : location.location_id"
                >
                    <!-- Location Header -->
                    <div class="flex items-center justify-between mb-3">
                        <div class="flex items-center gap-3">
                            <div 
                                class="w-10 h-10 rounded-xl flex items-center justify-center text-white font-bold"
                                :class="`bg-gradient-to-br ${getLocationColor(index).gradient}`"
                            >
                                {{ index + 1 }}
                            </div>
                            <div>
                                <h4 class="font-semibold text-gray-900 dark:text-white">
                                    {{ location.location_name || 'Unknown Location' }}
                                </h4>
                                <p class="text-xs text-gray-500 dark:text-gray-400">
                                    {{ getValuePercentage(location.total_value) }}% of total value
                                </p>
                            </div>
                        </div>
                        <div class="text-right">
                            <p class="text-lg font-bold text-gray-900 dark:text-white">
                                {{ formatCurrency(location.total_value || 0) }}
                            </p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                {{ formatNumber(location.total_units || 0) }} units
                            </p>
                        </div>
                    </div>

                    <!-- Progress Bars -->
                    <div class="space-y-2">
                        <!-- Value Bar -->
                        <div class="flex items-center gap-3">
                            <span class="text-xs text-gray-500 dark:text-gray-400 w-16">Value</span>
                            <div class="flex-1 h-2 bg-gray-200 dark:bg-gray-700 rounded-full overflow-hidden">
                                <div 
                                    class="h-full rounded-full transition-all duration-500"
                                    :class="`bg-gradient-to-r ${getLocationColor(index).gradient}`"
                                    :style="{ width: `${maxValues.value > 0 ? ((location.total_value || 0) / maxValues.value) * 100 : 0}%` }"
                                ></div>
                            </div>
                        </div>

                        <!-- Units Bar -->
                        <div class="flex items-center gap-3">
                            <span class="text-xs text-gray-500 dark:text-gray-400 w-16">Units</span>
                            <div class="flex-1 h-2 bg-gray-200 dark:bg-gray-700 rounded-full overflow-hidden">
                                <div 
                                    class="h-full rounded-full transition-all duration-500 bg-emerald-500"
                                    :style="{ width: `${maxValues.units > 0 ? ((location.total_units || 0) / maxValues.units) * 100 : 0}%` }"
                                ></div>
                            </div>
                        </div>

                        <!-- Products Bar -->
                        <div class="flex items-center gap-3">
                            <span class="text-xs text-gray-500 dark:text-gray-400 w-16">SKUs</span>
                            <div class="flex-1 h-2 bg-gray-200 dark:bg-gray-700 rounded-full overflow-hidden">
                                <div 
                                    class="h-full rounded-full transition-all duration-500 bg-amber-500"
                                    :style="{ width: `${maxValues.products > 0 ? ((location.product_count || 0) / maxValues.products) * 100 : 0}%` }"
                                ></div>
                            </div>
                        </div>
                    </div>

                    <!-- Expanded Details -->
                    <div 
                        v-if="selectedLocation === location.location_id"
                        class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700 grid grid-cols-3 gap-4"
                    >
                        <div>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Products</p>
                            <p class="text-lg font-semibold text-gray-900 dark:text-white">
                                {{ formatNumber(location.product_count || 0) }}
                            </p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Recent Activity</p>
                            <p class="text-lg font-semibold text-gray-900 dark:text-white">
                                {{ formatNumber(location.movement_count || 0) }}
                            </p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Avg Value/Unit</p>
                            <p class="text-lg font-semibold text-gray-900 dark:text-white">
                                {{ formatCurrency(location.total_units > 0 ? location.total_value / location.total_units : 0) }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Value Distribution Chart -->
            <div class="mt-6">
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400 mb-3">
                    Value Distribution by Location
                </p>
                <div class="flex h-6 rounded-full overflow-hidden">
                    <div 
                        v-for="(location, index) in sortedData" 
                        :key="location.location_id || index"
                        class="relative group transition-all"
                        :class="`bg-gradient-to-r ${getLocationColor(index).gradient}`"
                        :style="{ width: `${getValuePercentage(location.total_value)}%` }"
                    >
                        <!-- Tooltip -->
                        <div 
                            class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none z-10"
                        >
                            <div class="bg-gray-900 text-white text-xs rounded-lg px-3 py-2 whitespace-nowrap shadow-lg">
                                <p class="font-semibold">{{ location.location_name }}</p>
                                <p>{{ formatCurrency(location.total_value) }}</p>
                            </div>
                            <div class="absolute top-full left-1/2 -translate-x-1/2 -mt-1">
                                <div class="border-4 border-transparent border-t-gray-900"></div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Legend -->
                <div class="flex flex-wrap gap-3 mt-3">
                    <div 
                        v-for="(location, index) in sortedData.slice(0, 5)" 
                        :key="location.location_id || index"
                        class="flex items-center gap-2"
                    >
                        <span 
                            class="w-3 h-3 rounded-full"
                            :style="{ backgroundColor: getLocationColor(index).bg }"
                        ></span>
                        <span class="text-xs text-gray-600 dark:text-gray-400">
                            {{ location.location_name }}
                        </span>
                    </div>
                    <span 
                        v-if="sortedData.length > 5"
                        class="text-xs text-gray-500 dark:text-gray-400"
                    >
                        +{{ sortedData.length - 5 }} more
                    </span>
                </div>
            </div>
        </div>
    </div>
</template>
