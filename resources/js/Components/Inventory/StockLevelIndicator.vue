<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';

const props = defineProps({
    product: {
        type: Object,
        required: true,
    },
    showDetails: {
        type: Boolean,
        default: false,
    },
    compact: {
        type: Boolean,
        default: false,
    },
    refreshInterval: {
        type: Number,
        default: 0,
    },
    permissions: {
        type: Object,
        default: () => ({}),
    },
});

const emit = defineEmits(['adjust', 'refresh']);

const isExpanded = ref(false);
const isLoading = ref(false);
const stockLevels = ref([]);
const refreshTimer = ref(null);

// Computed
const stockStatus = computed(() => {
    const stock = props.product.stock_quantity ?? 0;
    const threshold = props.product.low_stock_threshold ?? props.product.reorder_point ?? 0;
    
    if (!props.product.manage_stock) return 'untracked';
    if (stock <= 0) return 'out_of_stock';
    if (threshold > 0 && stock <= threshold) return 'low_stock';
    return 'in_stock';
});

const statusConfig = computed(() => {
    const configs = {
        in_stock: {
            label: 'In Stock',
            color: 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400',
            dotColor: 'bg-green-500',
        },
        low_stock: {
            label: 'Low Stock',
            color: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400',
            dotColor: 'bg-yellow-500',
        },
        out_of_stock: {
            label: 'Out of Stock',
            color: 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400',
            dotColor: 'bg-red-500',
        },
        untracked: {
            label: 'Not Tracked',
            color: 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400',
            dotColor: 'bg-gray-400',
        },
    };
    return configs[stockStatus.value] || configs.untracked;
});

const totalOnHand = computed(() => stockLevels.value.reduce((sum, level) => sum + (level.quantity_on_hand || 0), 0));
const totalReserved = computed(() => stockLevels.value.reduce((sum, level) => sum + (level.quantity_reserved || 0), 0));
const totalAvailable = computed(() => stockLevels.value.reduce((sum, level) => sum + (level.quantity_available || 0), 0));
const totalValue = computed(() => stockLevels.value.reduce((sum, level) => sum + parseFloat(level.total_cost || 0), 0));

// Methods
const fetchStockLevels = async () => {
    if (!props.product.id) return;
    
    isLoading.value = true;
    try {
        const response = await fetch(`/api/v1/inventory/stock-levels/${props.product.id}`, {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });
        
        if (response.ok) {
            const data = await response.json();
            stockLevels.value = data.levels || [];
        }
    } catch (error) {
        console.error('Failed to fetch stock levels:', error);
    } finally {
        isLoading.value = false;
    }
};

const toggleExpanded = () => {
    isExpanded.value = !isExpanded.value;
    if (isExpanded.value && stockLevels.value.length === 0) {
        fetchStockLevels();
    }
};

const handleAdjust = () => {
    emit('adjust', props.product);
};

// Lifecycle
onMounted(() => {
    if (props.showDetails) {
        fetchStockLevels();
    }
    
    if (props.refreshInterval > 0) {
        refreshTimer.value = setInterval(() => {
            if (isExpanded.value || props.showDetails) {
                fetchStockLevels();
            }
            emit('refresh');
        }, props.refreshInterval);
    }
});

onUnmounted(() => {
    if (refreshTimer.value) {
        clearInterval(refreshTimer.value);
    }
});
</script>

<template>
    <div class="inline-flex flex-col">
        <!-- Status Badge -->
        <div
            :class="[
                'inline-flex items-center gap-1.5 rounded-full font-medium',
                compact ? 'px-2 py-0.5 text-xs' : 'px-3 py-1 text-sm',
                statusConfig.color,
                showDetails ? 'cursor-pointer hover:opacity-80' : ''
            ]"
            @click="showDetails && toggleExpanded()"
        >
            <span :class="['w-1.5 h-1.5 rounded-full', statusConfig.dotColor]"></span>
            <span>{{ statusConfig.label }}</span>
            <span v-if="product.manage_stock && !compact" class="opacity-75">
                ({{ product.stock_quantity ?? 0 }})
            </span>
            <svg
                v-if="showDetails"
                :class="['w-3.5 h-3.5 transition-transform', isExpanded ? 'rotate-180' : '']"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
            >
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
        </div>

        <!-- Expanded Details -->
        <div
            v-if="showDetails && isExpanded"
            class="mt-2 p-3 bg-gray-50 dark:bg-gray-700/50 rounded-lg border border-gray-200 dark:border-gray-600 min-w-72"
        >
            <div v-if="isLoading" class="flex items-center justify-center py-4">
                <svg class="animate-spin w-5 h-5 text-indigo-500" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                </svg>
            </div>

            <template v-else>
                <!-- Summary -->
                <div class="grid grid-cols-4 gap-2 mb-3 text-center">
                    <div class="p-2 bg-white dark:bg-gray-800 rounded">
                        <p class="text-xs text-gray-500 dark:text-gray-400">On Hand</p>
                        <p class="text-lg font-semibold text-gray-900 dark:text-white">{{ totalOnHand }}</p>
                    </div>
                    <div class="p-2 bg-white dark:bg-gray-800 rounded">
                        <p class="text-xs text-gray-500 dark:text-gray-400">Reserved</p>
                        <p class="text-lg font-semibold text-yellow-600 dark:text-yellow-400">{{ totalReserved }}</p>
                    </div>
                    <div class="p-2 bg-white dark:bg-gray-800 rounded">
                        <p class="text-xs text-gray-500 dark:text-gray-400">Available</p>
                        <p class="text-lg font-semibold text-green-600 dark:text-green-400">{{ totalAvailable }}</p>
                    </div>
                    <div class="p-2 bg-white dark:bg-gray-800 rounded">
                        <p class="text-xs text-gray-500 dark:text-gray-400">Value</p>
                        <p class="text-lg font-semibold text-blue-600 dark:text-blue-400">${{ totalValue.toFixed(2) }}</p>
                    </div>
                </div>

                <!-- Location Breakdown -->
                <div v-if="stockLevels.length > 0" class="space-y-2">
                    <p class="text-xs font-medium text-gray-600 dark:text-gray-400 uppercase tracking-wider">
                        By Location
                    </p>
                    <div
                        v-for="level in stockLevels"
                        :key="level.id"
                        class="flex items-center justify-between py-1.5 px-2 bg-white dark:bg-gray-800 rounded text-sm"
                    >
                        <span class="text-gray-700 dark:text-gray-300">
                            {{ level.location?.name || 'Unknown' }}
                        </span>
                        <div class="flex items-center gap-3 text-xs">
                            <span class="text-gray-500">{{ level.quantity_on_hand }}</span>
                            <span class="text-yellow-600">-{{ level.quantity_reserved }}</span>
                            <span class="font-medium text-green-600">={{ level.quantity_available }}</span>
                        </div>
                    </div>
                </div>

                <div v-else class="text-center py-2 text-sm text-gray-500 dark:text-gray-400">
                    No location data available
                </div>

                <!-- Actions -->
                <div v-if="permissions.can_adjust" class="mt-3 pt-3 border-t border-gray-200 dark:border-gray-600">
                    <button
                        @click="handleAdjust"
                        class="w-full px-3 py-1.5 text-xs font-medium text-indigo-600 dark:text-indigo-400 bg-indigo-50 dark:bg-indigo-900/30 rounded hover:bg-indigo-100 dark:hover:bg-indigo-900/50 transition-colors"
                    >
                        Record Movement
                    </button>
                </div>
            </template>
        </div>
    </div>
</template>
