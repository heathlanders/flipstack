<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { router, usePage } from '@inertiajs/vue3';

const props = defineProps({
    initialAlerts: { 
        type: Array, 
        default: () => [] 
    },
    maxVisible: { 
        type: Number, 
        default: 10 
    },
    showFilters: {
        type: Boolean,
        default: true
    },
    showActions: {
        type: Boolean,
        default: true
    },
    collapsible: {
        type: Boolean,
        default: false
    },
    defaultCollapsed: {
        type: Boolean,
        default: false
    }
});

const emit = defineEmits(['dismiss', 'view-product', 'refresh', 'dismiss-all']);

const page = usePage();
const alerts = ref([...props.initialAlerts]);
const isLoading = ref(false);
const filter = ref('all'); // all, low_stock, out_of_stock, overstocked
const isCollapsed = ref(props.defaultCollapsed);
const lastRefresh = ref(new Date());

// Filtered alerts
const filteredAlerts = computed(() => {
    if (filter.value === 'all') return alerts.value;
    return alerts.value.filter(a => a.alert_type === filter.value);
});

const visibleAlerts = computed(() => {
    return filteredAlerts.value.slice(0, props.maxVisible);
});

const hasMoreAlerts = computed(() => {
    return filteredAlerts.value.length > props.maxVisible;
});

const alertCounts = computed(() => ({
    all: alerts.value.length,
    low_stock: alerts.value.filter(a => a.alert_type === 'low_stock').length,
    out_of_stock: alerts.value.filter(a => a.alert_type === 'out_of_stock').length,
    overstocked: alerts.value.filter(a => a.alert_type === 'overstocked').length,
}));

const hasAlerts = computed(() => alerts.value.length > 0);
const hasCriticalAlerts = computed(() => alertCounts.value.out_of_stock > 0);

const getAlertConfig = (type) => ({
    'low_stock': { 
        icon: 'exclamation-triangle', 
        bg: 'bg-yellow-50 dark:bg-yellow-900/20', 
        border: 'border-yellow-200 dark:border-yellow-800',
        text: 'text-yellow-800 dark:text-yellow-200',
        iconBg: 'bg-yellow-100 dark:bg-yellow-900/50',
        label: 'Low Stock',
        priority: 2
    },
    'out_of_stock': { 
        icon: 'x-circle', 
        bg: 'bg-red-50 dark:bg-red-900/20', 
        border: 'border-red-200 dark:border-red-800',
        text: 'text-red-800 dark:text-red-200',
        iconBg: 'bg-red-100 dark:bg-red-900/50',
        label: 'Out of Stock',
        priority: 1
    },
    'overstocked': { 
        icon: 'arrow-up-circle', 
        bg: 'bg-blue-50 dark:bg-blue-900/20', 
        border: 'border-blue-200 dark:border-blue-800',
        text: 'text-blue-800 dark:text-blue-200',
        iconBg: 'bg-blue-100 dark:bg-blue-900/50',
        label: 'Overstocked',
        priority: 3
    },
}[type] || { 
    icon: 'info', 
    bg: 'bg-gray-50 dark:bg-gray-800', 
    border: 'border-gray-200 dark:border-gray-700', 
    text: 'text-gray-800 dark:text-gray-200',
    iconBg: 'bg-gray-100 dark:bg-gray-700',
    label: 'Alert',
    priority: 4
});

// Format relative time
const formatRelativeTime = (timestamp) => {
    if (!timestamp) return '';
    const date = new Date(timestamp);
    const now = new Date();
    const diff = Math.floor((now - date) / 1000);
    
    if (diff < 60) return 'just now';
    if (diff < 3600) return `${Math.floor(diff / 60)}m ago`;
    if (diff < 86400) return `${Math.floor(diff / 3600)}h ago`;
    return `${Math.floor(diff / 86400)}d ago`;
};

// Dismiss alert
const dismissAlert = async (alertId) => {
    const index = alerts.value.findIndex(a => a.id === alertId);
    if (index > -1) {
        const dismissed = alerts.value.splice(index, 1)[0];
        emit('dismiss', alertId, dismissed);
        
        // Persist dismissal
        try {
            const csrfToken = page.props.csrf_token || 
                document.querySelector('meta[name="csrf-token"]')?.content;
            
            await fetch(`/api/v1/inventory/alerts/${alertId}/dismiss`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json',
                },
            });
        } catch (error) {
            console.error('Failed to dismiss alert:', error);
            // Re-add alert if API call fails
            alerts.value.splice(index, 0, dismissed);
        }
    }
};

// Dismiss all filtered alerts
const dismissAllFiltered = async () => {
    if (!confirm(`Dismiss all ${filter.value === 'all' ? '' : filter.value.replace('_', ' ') + ' '}alerts?`)) {
        return;
    }
    
    const toDismiss = filter.value === 'all' 
        ? [...alerts.value] 
        : alerts.value.filter(a => a.alert_type === filter.value);
    
    // Remove from local state
    toDismiss.forEach(alert => {
        const index = alerts.value.findIndex(a => a.id === alert.id);
        if (index > -1) alerts.value.splice(index, 1);
    });
    
    emit('dismiss-all', toDismiss);
    
    // Persist
    try {
        const csrfToken = page.props.csrf_token || 
            document.querySelector('meta[name="csrf-token"]')?.content;
        
        await fetch('/api/v1/inventory/alerts/dismiss-all', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
            },
            body: JSON.stringify({ alert_type: filter.value === 'all' ? null : filter.value }),
        });
    } catch (error) {
        console.error('Failed to dismiss alerts:', error);
    }
};

// View product
const viewProduct = (productId) => {
    emit('view-product', productId);
    router.visit(route('inventory.products.show', productId));
};

// Refresh alerts
const refreshAlerts = async () => {
    isLoading.value = true;
    try {
        const response = await fetch('/api/v1/inventory/alerts', {
            headers: {
                'Accept': 'application/json',
            },
        });
        const data = await response.json();
        alerts.value = data.alerts || [];
        lastRefresh.value = new Date();
        emit('refresh', alerts.value);
    } catch (error) {
        console.error('Failed to refresh alerts:', error);
    } finally {
        isLoading.value = false;
    }
};

// Toggle collapse
const toggleCollapse = () => {
    isCollapsed.value = !isCollapsed.value;
};

// Laravel Echo listener
let echoChannel = null;

onMounted(() => {
    if (typeof window.Echo !== 'undefined') {
        const user = page.props.auth?.user;
        const orgId = user?.organization_id ?? user?.current_organization_id;
        
        if (orgId) {
            echoChannel = window.Echo.private(`organization.${orgId}`)
                .listen('.stock.alert', (event) => {
                    // Check if alert for this product already exists
                    const existingIndex = alerts.value.findIndex(a => a.product_id === event.product_id);
                    if (existingIndex > -1) {
                        // Update existing alert
                        alerts.value[existingIndex] = {
                            ...alerts.value[existingIndex],
                            stock_quantity: event.stock_quantity,
                            alert_type: event.alert_type,
                            timestamp: event.timestamp,
                            is_new: true,
                        };
                    } else {
                        // Add new alert to top
                        const newAlert = {
                            id: `alert-${event.product_id}`,
                            product_id: event.product_id,
                            product_name: event.product_name,
                            sku: event.sku,
                            stock_quantity: event.stock_quantity,
                            low_stock_threshold: event.low_stock_threshold,
                            alert_type: event.alert_type,
                            timestamp: event.timestamp,
                            is_new: true,
                        };
                        alerts.value.unshift(newAlert);
                    }
                    
                    // Remove "new" flag after animation
                    setTimeout(() => {
                        const alert = alerts.value.find(a => a.product_id === event.product_id);
                        if (alert) alert.is_new = false;
                    }, 3000);
                });
        }
    }
    
    // Initial load if no initial alerts provided
    if (props.initialAlerts.length === 0) {
        refreshAlerts();
    }
});

onUnmounted(() => {
    if (echoChannel) {
        echoChannel.stopListening('.stock.alert');
        const user = page.props.auth?.user;
        const orgId = user?.organization_id ?? user?.current_organization_id;
        if (orgId) {
            window.Echo.leave(`organization.${orgId}`);
        }
    }
});
</script>

<template>
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
        <!-- Header -->
        <div 
            class="px-4 py-3 border-b border-gray-200 dark:border-gray-700"
            :class="{ 'cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700/50': collapsible }"
            @click="collapsible && toggleCollapse()"
        >
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <!-- Collapse toggle -->
                    <button 
                        v-if="collapsible"
                        class="p-1 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
                        @click.stop="toggleCollapse"
                    >
                        <svg 
                            :class="['w-5 h-5 transition-transform', { '-rotate-90': isCollapsed }]" 
                            fill="none" 
                            stroke="currentColor" 
                            viewBox="0 0 24 24"
                        >
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>
                    
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white flex items-center gap-2">
                        Stock Alerts
                        <span 
                            v-if="hasCriticalAlerts"
                            class="flex h-2 w-2 relative"
                        >
                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75"></span>
                            <span class="relative inline-flex rounded-full h-2 w-2 bg-red-500"></span>
                        </span>
                    </h3>
                    
                    <!-- Alert count badge -->
                    <span 
                        v-if="hasAlerts"
                        class="px-2 py-0.5 text-xs font-medium rounded-full"
                        :class="hasCriticalAlerts 
                            ? 'bg-red-100 text-red-800 dark:bg-red-900/50 dark:text-red-200' 
                            : 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400'"
                    >
                        {{ alertCounts.all }}
                    </span>
                </div>
                
                <!-- Actions -->
                <div class="flex items-center gap-2">
                    <span class="text-xs text-gray-400">
                        {{ formatRelativeTime(lastRefresh) }}
                    </span>
                    <button 
                        @click.stop="refreshAlerts"
                        :disabled="isLoading"
                        class="p-1.5 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition-colors"
                        title="Refresh alerts"
                    >
                        <svg :class="['w-5 h-5', { 'animate-spin': isLoading }]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                        </svg>
                    </button>
                </div>
            </div>
            
            <!-- Filter tabs -->
            <div v-if="showFilters && !isCollapsed" class="flex flex-wrap gap-2 mt-3">
                <button
                    v-for="(count, key) in alertCounts"
                    :key="key"
                    @click.stop="filter = key"
                    :class="[
                        'px-3 py-1 text-sm font-medium rounded-full transition-colors',
                        filter === key 
                            ? 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/50 dark:text-indigo-200' 
                            : 'text-gray-600 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-700'
                    ]"
                >
                    {{ key === 'all' ? 'All' : key.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase()) }}
                    <span class="ml-1 text-xs opacity-75">({{ count }})</span>
                </button>
            </div>
        </div>
        
        <!-- Alerts list -->
        <div 
            v-show="!isCollapsed"
            class="divide-y divide-gray-100 dark:divide-gray-700 max-h-96 overflow-y-auto"
        >
            <TransitionGroup name="alert-list">
                <div
                    v-for="alert in visibleAlerts"
                    :key="alert.id"
                    :class="[
                        'p-4 transition-all duration-300',
                        getAlertConfig(alert.alert_type).bg,
                        { 'ring-2 ring-inset ring-indigo-500': alert.is_new }
                    ]"
                >
                    <div class="flex items-start justify-between gap-4">
                        <div class="flex items-start gap-3 min-w-0">
                            <!-- Alert icon -->
                            <div :class="['p-2 rounded-lg flex-shrink-0', getAlertConfig(alert.alert_type).iconBg]">
                                <svg 
                                    class="w-5 h-5" 
                                    :class="getAlertConfig(alert.alert_type).text" 
                                    fill="none" 
                                    stroke="currentColor" 
                                    viewBox="0 0 24 24"
                                >
                                    <path 
                                        v-if="alert.alert_type === 'out_of_stock'" 
                                        stroke-linecap="round" 
                                        stroke-linejoin="round" 
                                        stroke-width="2" 
                                        d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" 
                                    />
                                    <path 
                                        v-else-if="alert.alert_type === 'low_stock'" 
                                        stroke-linecap="round" 
                                        stroke-linejoin="round" 
                                        stroke-width="2" 
                                        d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" 
                                    />
                                    <path 
                                        v-else 
                                        stroke-linecap="round" 
                                        stroke-linejoin="round" 
                                        stroke-width="2" 
                                        d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" 
                                    />
                                </svg>
                            </div>
                            
                            <!-- Alert content -->
                            <div class="min-w-0">
                                <p class="font-medium text-gray-900 dark:text-white truncate">
                                    {{ alert.product_name }}
                                </p>
                                <p class="text-sm text-gray-500 dark:text-gray-400 truncate">
                                    SKU: {{ alert.sku }}
                                </p>
                                <p class="text-sm mt-1" :class="getAlertConfig(alert.alert_type).text">
                                    <span class="font-semibold">{{ alert.stock_quantity }}</span> in stock
                                    <span v-if="alert.alert_type === 'low_stock' && alert.low_stock_threshold" class="opacity-75">
                                        (threshold: {{ alert.low_stock_threshold }})
                                    </span>
                                </p>
                                <p v-if="alert.timestamp" class="text-xs text-gray-400 mt-1">
                                    {{ formatRelativeTime(alert.timestamp) }}
                                </p>
                            </div>
                        </div>
                        
                        <!-- Actions -->
                        <div v-if="showActions" class="flex items-center gap-2 flex-shrink-0">
                            <button
                                @click="viewProduct(alert.product_id)"
                                class="px-3 py-1.5 text-sm font-medium text-indigo-600 hover:text-indigo-800 dark:text-indigo-400 dark:hover:text-indigo-200 hover:bg-indigo-50 dark:hover:bg-indigo-900/30 rounded-lg transition-colors"
                            >
                                View
                            </button>
                            <button
                                @click="dismissAlert(alert.id)"
                                class="p-1.5 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition-colors"
                                title="Dismiss alert"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>
            </TransitionGroup>
            
            <!-- Empty state -->
            <div v-if="filteredAlerts.length === 0" class="p-8 text-center">
                <div class="mx-auto w-16 h-16 bg-green-100 dark:bg-green-900/30 rounded-full flex items-center justify-center mb-4">
                    <svg class="w-8 h-8 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <p class="text-gray-900 dark:text-white font-medium">All clear!</p>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                    {{ filter === 'all' ? 'No stock alerts at this time' : `No ${filter.replace('_', ' ')} alerts` }}
                </p>
            </div>
        </div>
        
        <!-- Footer -->
        <div 
            v-if="!isCollapsed && (hasMoreAlerts || filteredAlerts.length > 0)"
            class="px-4 py-3 border-t border-gray-200 dark:border-gray-700 flex items-center justify-between"
        >
            <button 
                v-if="hasMoreAlerts"
                class="text-sm text-indigo-600 hover:text-indigo-800 dark:text-indigo-400 dark:hover:text-indigo-200"
            >
                View all {{ filteredAlerts.length }} alerts
            </button>
            <span v-else></span>
            
            <button
                v-if="filteredAlerts.length > 0"
                @click="dismissAllFiltered"
                class="text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200"
            >
                Dismiss all
            </button>
        </div>
    </div>
</template>

<style scoped>
.alert-list-enter-active,
.alert-list-leave-active {
    transition: all 0.3s ease;
}

.alert-list-enter-from {
    opacity: 0;
    transform: translateX(-30px);
}

.alert-list-leave-to {
    opacity: 0;
    transform: translateX(30px);
}

.alert-list-move {
    transition: transform 0.3s ease;
}
</style>
