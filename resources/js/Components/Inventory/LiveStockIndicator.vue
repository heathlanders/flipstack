<template>
    <div
        :class="containerClasses"
        @click="$emit('click', product)"
    >
        <!-- Stock quantity display -->
        <div class="flex items-center gap-2">
            <!-- Stock icon -->
            <div :class="iconClasses">
                <svg v-if="stockStatus === 'out_of_stock'" class="w-full h-full" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                </svg>
                <svg v-else-if="stockStatus === 'low_stock'" class="w-full h-full" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
                <svg v-else-if="stockStatus === 'overstocked'" class="w-full h-full" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                </svg>
                <svg v-else class="w-full h-full" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
            </div>

            <!-- Quantity text -->
            <div :class="quantityClasses">
                <span 
                    :class="[
                        'font-semibold transition-all duration-300',
                        showAnimation && isAnimating ? 'scale-110' : ''
                    ]"
                >
                    {{ displayQuantity }}
                </span>
                <span v-if="!compact" class="text-gray-500 dark:text-gray-400 ml-1">
                    {{ quantityLabel }}
                </span>
            </div>

            <!-- Change indicator -->
            <transition name="fade">
                <div 
                    v-if="showChangeIndicator && changeAmount !== 0"
                    :class="changeIndicatorClasses"
                >
                    <span v-if="changeAmount > 0">+{{ changeAmount }}</span>
                    <span v-else>{{ changeAmount }}</span>
                </div>
            </transition>
        </div>

        <!-- Status badge -->
        <div v-if="showStatusBadge && !compact" :class="statusBadgeClasses">
            {{ statusLabel }}
        </div>

        <!-- Threshold info -->
        <div v-if="showThreshold && !compact && reorderPoint > 0" class="text-xs text-gray-500 dark:text-gray-400 mt-1">
            Reorder at: {{ reorderPoint }}
        </div>

        <!-- Reserved indicator -->
        <div v-if="!compact && quantityReserved > 0" class="text-xs text-amber-600 dark:text-amber-400 mt-1">
            {{ quantityReserved }} reserved
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';

const props = defineProps({
    // Product object (should have id, inventory levels)
    product: {
        type: Object,
        required: true,
    },
    // Specific inventory level to display (optional, if not provided uses totals)
    inventoryLevel: {
        type: Object,
        default: null,
    },
    // Show animation on stock changes
    showAnimation: {
        type: Boolean,
        default: true,
    },
    // Size variant
    size: {
        type: String,
        default: 'md',
        validator: (value) => ['xs', 'sm', 'md', 'lg'].includes(value),
    },
    // Show status badge
    showStatusBadge: {
        type: Boolean,
        default: true,
    },
    // Show threshold info
    showThreshold: {
        type: Boolean,
        default: false,
    },
    // Compact mode (quantity only)
    compact: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['stock-changed', 'alert', 'click']);

// Reactive state
const currentQuantity = ref(0);
const quantityAvailable = ref(0);
const quantityReserved = ref(0);
const reorderPoint = ref(0);
const maxStockLevel = ref(null);
const isAnimating = ref(false);
const showChangeIndicator = ref(false);
const changeAmount = ref(0);
let channel = null;

// Initialize from props
const initializeFromProps = () => {
    if (props.inventoryLevel) {
        // Use specific inventory level
        currentQuantity.value = props.inventoryLevel.quantity_on_hand ?? 0;
        quantityAvailable.value = props.inventoryLevel.quantity_available ?? 0;
        quantityReserved.value = props.inventoryLevel.quantity_reserved ?? 0;
        reorderPoint.value = props.inventoryLevel.reorder_point ?? 0;
        maxStockLevel.value = props.inventoryLevel.max_stock_level;
    } else if (props.product) {
        // Use product totals or first inventory level
        const levels = props.product.inventory_levels || props.product.inventoryLevels || [];
        if (levels.length > 0) {
            // Sum all levels
            currentQuantity.value = levels.reduce((sum, l) => sum + (l.quantity_on_hand ?? 0), 0);
            quantityAvailable.value = levels.reduce((sum, l) => sum + (l.quantity_available ?? 0), 0);
            quantityReserved.value = levels.reduce((sum, l) => sum + (l.quantity_reserved ?? 0), 0);
            // Use lowest reorder point
            reorderPoint.value = Math.min(...levels.map(l => l.reorder_point ?? 0).filter(r => r > 0)) || 0;
        } else {
            // Fallback to product-level fields if they exist
            currentQuantity.value = props.product.quantity_on_hand ?? props.product.stock_quantity ?? 0;
            quantityAvailable.value = props.product.quantity_available ?? currentQuantity.value;
            quantityReserved.value = props.product.quantity_reserved ?? 0;
            reorderPoint.value = props.product.reorder_point ?? props.product.low_stock_threshold ?? 5;
            maxStockLevel.value = props.product.max_stock_level;
        }
    }
};

// Watch for prop changes
watch(() => [props.product, props.inventoryLevel], initializeFromProps, { deep: true });

// Computed properties
const displayQuantity = computed(() => {
    return props.compact ? currentQuantity.value : quantityAvailable.value;
});

const quantityLabel = computed(() => {
    return quantityAvailable.value === 1 ? 'available' : 'available';
});

const stockStatus = computed(() => {
    if (quantityAvailable.value <= 0) {
        return 'out_of_stock';
    }
    if (reorderPoint.value > 0 && quantityAvailable.value <= reorderPoint.value) {
        return 'low_stock';
    }
    if (maxStockLevel.value && currentQuantity.value > maxStockLevel.value) {
        return 'overstocked';
    }
    return 'in_stock';
});

const statusLabel = computed(() => {
    switch (stockStatus.value) {
        case 'out_of_stock':
            return 'Out of Stock';
        case 'low_stock':
            return 'Low Stock';
        case 'overstocked':
            return 'Overstocked';
        default:
            return 'In Stock';
    }
});

// Size-based classes
const sizeConfig = {
    xs: { container: 'text-xs', icon: 'w-3 h-3', quantity: 'text-xs' },
    sm: { container: 'text-sm', icon: 'w-4 h-4', quantity: 'text-sm' },
    md: { container: 'text-base', icon: 'w-5 h-5', quantity: 'text-base' },
    lg: { container: 'text-lg', icon: 'w-6 h-6', quantity: 'text-lg' },
};

const containerClasses = computed(() => [
    'inline-flex flex-col items-start p-2 rounded-lg cursor-pointer transition-all duration-200',
    'hover:bg-gray-50 dark:hover:bg-gray-800',
    sizeConfig[props.size].container,
]);

const iconClasses = computed(() => {
    const baseClasses = [sizeConfig[props.size].icon];
    
    switch (stockStatus.value) {
        case 'out_of_stock':
            return [...baseClasses, 'text-red-500'];
        case 'low_stock':
            return [...baseClasses, 'text-amber-500'];
        case 'overstocked':
            return [...baseClasses, 'text-blue-500'];
        default:
            return [...baseClasses, 'text-green-500'];
    }
});

const quantityClasses = computed(() => [
    sizeConfig[props.size].quantity,
    'flex items-baseline',
]);

const statusBadgeClasses = computed(() => {
    const baseClasses = 'text-xs px-2 py-0.5 rounded-full font-medium mt-1';
    
    switch (stockStatus.value) {
        case 'out_of_stock':
            return `${baseClasses} bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400`;
        case 'low_stock':
            return `${baseClasses} bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400`;
        case 'overstocked':
            return `${baseClasses} bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400`;
        default:
            return `${baseClasses} bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400`;
    }
});

const changeIndicatorClasses = computed(() => {
    const baseClasses = 'text-xs font-medium px-1.5 py-0.5 rounded-full animate-pulse';
    
    if (changeAmount.value > 0) {
        return `${baseClasses} bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400`;
    }
    return `${baseClasses} bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400`;
});

// Handle real-time stock update
const handleStockUpdate = (event) => {
    // Check if this update is for our product
    if (event.product_id !== props.product?.id) {
        return;
    }

    // If we're showing a specific location, check that too
    if (props.inventoryLevel && event.location_id !== props.inventoryLevel.location_id) {
        return;
    }

    const previousQuantity = currentQuantity.value;
    
    // Update quantities
    currentQuantity.value = event.new_quantity ?? event.quantity_on_hand ?? currentQuantity.value;
    quantityAvailable.value = event.quantity_available ?? (currentQuantity.value - quantityReserved.value);
    
    // Calculate change
    changeAmount.value = currentQuantity.value - previousQuantity;

    // Trigger animation
    if (props.showAnimation && changeAmount.value !== 0) {
        isAnimating.value = true;
        showChangeIndicator.value = true;
        
        setTimeout(() => {
            isAnimating.value = false;
        }, 300);
        
        setTimeout(() => {
            showChangeIndicator.value = false;
            changeAmount.value = 0;
        }, 3000);
    }

    // Emit event
    emit('stock-changed', {
        productId: props.product.id,
        previousQuantity,
        newQuantity: currentQuantity.value,
        change: changeAmount.value,
        event,
    });

    // Check for alert conditions
    if (event.is_low_stock || event.is_out_of_stock) {
        emit('alert', {
            type: event.is_out_of_stock ? 'out_of_stock' : 'low_stock',
            productId: props.product.id,
            quantity: currentQuantity.value,
        });
    }
};

// Setup real-time listener
onMounted(() => {
    initializeFromProps();

    // Subscribe to real-time updates if Echo is available
    if (window.Echo && props.product?.id) {
        channel = window.Echo.private(`product.${props.product.id}`);
        channel.listen('.stock.updated', handleStockUpdate);
    }
});

// Cleanup
onUnmounted(() => {
    if (channel) {
        channel.stopListening('.stock.updated');
    }
});
</script>

<style scoped>
.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.3s ease;
}

.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}
</style>
