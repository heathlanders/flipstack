<script setup>
import { ref, computed, onMounted, onUnmounted, provide } from 'vue';
import { usePage, router } from '@inertiajs/vue3';

const props = defineProps({
    position: { 
        type: String, 
        default: 'bottom-right',
        validator: (value) => ['top-right', 'bottom-right', 'top-left', 'bottom-left', 'top-center', 'bottom-center'].includes(value)
    },
    maxToasts: { 
        type: Number, 
        default: 5 
    },
    defaultDuration: {
        type: Number,
        default: 5000
    },
    enableSounds: {
        type: Boolean,
        default: false
    }
});

const page = usePage();
const toasts = ref([]);
const activityFeed = ref([]);

// Position classes
const positionClasses = computed(() => ({
    'top-right': 'top-4 right-4',
    'bottom-right': 'bottom-4 right-4',
    'top-left': 'top-4 left-4',
    'bottom-left': 'bottom-4 left-4',
    'top-center': 'top-4 left-1/2 -translate-x-1/2',
    'bottom-center': 'bottom-4 left-1/2 -translate-x-1/2',
}[props.position]));

// Toast type configs
const toastConfig = (type) => ({
    success: { 
        bg: 'bg-green-500', 
        icon: 'check-circle',
        sound: 'success'
    },
    error: { 
        bg: 'bg-red-500', 
        icon: 'x-circle',
        sound: 'error'
    },
    warning: { 
        bg: 'bg-yellow-500', 
        icon: 'exclamation',
        sound: 'warning'
    },
    info: { 
        bg: 'bg-blue-500', 
        icon: 'information-circle',
        sound: 'info'
    },
    stock_alert: {
        bg: 'bg-orange-500',
        icon: 'cube',
        sound: 'alert'
    }
}[type] || { bg: 'bg-gray-500', icon: 'bell', sound: null });

// Add toast notification
const addToast = (toast) => {
    const id = `toast-${Date.now()}-${Math.random().toString(36).substr(2, 9)}`;
    const newToast = {
        id,
        type: 'info',
        title: '',
        message: '',
        persistent: false,
        duration: props.defaultDuration,
        actions: [],
        ...toast,
        timestamp: new Date(),
        progress: 100,
    };
    
    toasts.value.unshift(newToast);
    
    // Limit max toasts
    if (toasts.value.length > props.maxToasts) {
        toasts.value.pop();
    }
    
    // Play sound if enabled
    if (props.enableSounds && toastConfig(newToast.type).sound) {
        playSound(toastConfig(newToast.type).sound);
    }
    
    // Auto-dismiss after duration (unless persistent)
    if (!newToast.persistent) {
        startToastTimer(id, newToast.duration);
    }
    
    return id;
};

// Start timer for toast auto-dismiss
const startToastTimer = (id, duration) => {
    const startTime = Date.now();
    const updateProgress = () => {
        const toast = toasts.value.find(t => t.id === id);
        if (!toast) return;
        
        const elapsed = Date.now() - startTime;
        toast.progress = Math.max(0, 100 - (elapsed / duration) * 100);
        
        if (toast.progress > 0) {
            requestAnimationFrame(updateProgress);
        } else {
            dismissToast(id);
        }
    };
    
    requestAnimationFrame(updateProgress);
};

// Dismiss toast
const dismissToast = (id) => {
    const index = toasts.value.findIndex(t => t.id === id);
    if (index > -1) {
        toasts.value.splice(index, 1);
    }
};

// Pause toast timer on hover
const pauseToast = (id) => {
    const toast = toasts.value.find(t => t.id === id);
    if (toast) {
        toast.paused = true;
    }
};

// Resume toast timer
const resumeToast = (id) => {
    const toast = toasts.value.find(t => t.id === id);
    if (toast && !toast.persistent) {
        toast.paused = false;
        const remainingDuration = (toast.progress / 100) * (toast.duration || props.defaultDuration);
        startToastTimer(id, remainingDuration);
    }
};

// Add to activity feed
const addActivity = (activity) => {
    const newActivity = {
        id: `activity-${Date.now()}-${Math.random().toString(36).substr(2, 9)}`,
        type: 'default',
        message: '',
        ...activity,
        timestamp: new Date(),
    };
    
    activityFeed.value.unshift(newActivity);
    
    // Limit feed size
    if (activityFeed.value.length > 50) {
        activityFeed.value.pop();
    }
    
    return newActivity.id;
};

// Clear all toasts
const clearAllToasts = () => {
    toasts.value = [];
};

// Clear activity feed
const clearActivityFeed = () => {
    activityFeed.value = [];
};

// Play notification sound
const playSound = (soundType) => {
    // Implementation depends on your sound files
    // const audio = new Audio(`/sounds/${soundType}.mp3`);
    // audio.volume = 0.3;
    // audio.play().catch(() => {});
};

// Handle toast action click
const handleAction = (toast, action) => {
    if (action.onClick) {
        action.onClick(toast);
    }
    if (action.route) {
        router.visit(action.route);
    }
    if (!action.keepOpen) {
        dismissToast(toast.id);
    }
};

// Laravel Echo listeners
let orgChannel = null;
let userChannel = null;

onMounted(() => {
    if (typeof window.Echo !== 'undefined') {
        const user = page.props.auth?.user;
        const orgId = user?.organization_id ?? user?.current_organization_id;
        
        // Organization channel
        if (orgId) {
            orgChannel = window.Echo.private(`organization.${orgId}`)
                .listen('.stock.updated', (event) => {
                    addActivity({
                        type: 'stock_update',
                        message: `${event.product_name} stock ${event.change > 0 ? 'increased' : 'decreased'} by ${Math.abs(event.change)}`,
                        product_id: event.product_id,
                        data: event,
                    });
                })
                .listen('.stock.alert', (event) => {
                    const isOutOfStock = event.alert_type === 'out_of_stock';
                    addToast({
                        type: isOutOfStock ? 'error' : 'warning',
                        title: isOutOfStock ? 'Out of Stock' : 'Low Stock Alert',
                        message: `${event.product_name} has ${event.stock_quantity} units remaining`,
                        product_id: event.product_id,
                        duration: isOutOfStock ? 8000 : 5000,
                        actions: [
                            {
                                label: 'View Product',
                                route: route('inventory.products.show', event.product_id),
                            }
                        ],
                    });
                    
                    addActivity({
                        type: 'stock_alert',
                        message: `${isOutOfStock ? 'Out of stock' : 'Low stock'}: ${event.product_name}`,
                        product_id: event.product_id,
                        severity: isOutOfStock ? 'critical' : 'warning',
                        data: event,
                    });
                })
                .listen('.movement.completed', (event) => {
                    addActivity({
                        type: 'movement',
                        message: `${event.user_name || 'System'} ${event.movement_type} ${event.quantity} units of ${event.product_name}`,
                        movement_id: event.movement_id,
                        product_id: event.product_id,
                        data: event,
                    });
                });
        }
        
        // User channel for bulk operations
        if (user?.id) {
            userChannel = window.Echo.private(`user.${user.id}`)
                .listen('.bulk.completed', (event) => {
                    const isSuccess = event.failed_count === 0;
                    addToast({
                        type: isSuccess ? 'success' : 'warning',
                        title: 'Bulk Operation Complete',
                        message: `${event.success_count}/${event.total_items} items processed successfully`,
                        persistent: !isSuccess,
                        duration: 8000,
                        data: event,
                        actions: event.failed_count > 0 ? [
                            {
                                label: 'View Errors',
                                onClick: () => {
                                    console.log('Errors:', event.errors);
                                },
                            }
                        ] : [],
                    });
                    
                    addActivity({
                        type: 'bulk_operation',
                        message: `Bulk ${event.operation_type}: ${event.success_count}/${event.total_items} succeeded`,
                        success_count: event.success_count,
                        failed_count: event.failed_count,
                        data: event,
                    });
                });
        }
    }
});

onUnmounted(() => {
    const user = page.props.auth?.user;
    const orgId = user?.organization_id ?? user?.current_organization_id;
    
    if (orgChannel) {
        orgChannel.stopListening('.stock.updated');
        orgChannel.stopListening('.stock.alert');
        orgChannel.stopListening('.movement.completed');
        if (orgId) {
            window.Echo.leave(`organization.${orgId}`);
        }
    }
    
    if (userChannel) {
        userChannel.stopListening('.bulk.completed');
        if (user?.id) {
            window.Echo.leave(`user.${user.id}`);
        }
    }
});

// Expose methods for external use
defineExpose({ 
    addToast, 
    addActivity, 
    dismissToast, 
    clearAllToasts,
    clearActivityFeed,
    toasts,
    activityFeed
});

// Provide to child components
provide('inventoryNotifications', {
    addToast,
    addActivity,
    dismissToast,
});
</script>

<template>
    <!-- Toast container -->
    <Teleport to="body">
        <div 
            :class="[
                'fixed z-50 flex flex-col gap-2 w-full max-w-sm pointer-events-none',
                positionClasses
            ]"
        >
            <TransitionGroup name="toast">
                <div
                    v-for="toast in toasts"
                    :key="toast.id"
                    class="pointer-events-auto"
                    @mouseenter="pauseToast(toast.id)"
                    @mouseleave="resumeToast(toast.id)"
                >
                    <div 
                        :class="[
                            'rounded-lg shadow-lg overflow-hidden backdrop-blur-sm',
                            toastConfig(toast.type).bg
                        ]"
                    >
                        <div class="p-4 flex items-start gap-3">
                            <!-- Icon -->
                            <div class="flex-shrink-0">
                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path 
                                        v-if="toastConfig(toast.type).icon === 'check-circle'"
                                        stroke-linecap="round" 
                                        stroke-linejoin="round" 
                                        stroke-width="2" 
                                        d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" 
                                    />
                                    <path 
                                        v-else-if="toastConfig(toast.type).icon === 'x-circle'"
                                        stroke-linecap="round" 
                                        stroke-linejoin="round" 
                                        stroke-width="2" 
                                        d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" 
                                    />
                                    <path 
                                        v-else-if="toastConfig(toast.type).icon === 'exclamation'"
                                        stroke-linecap="round" 
                                        stroke-linejoin="round" 
                                        stroke-width="2" 
                                        d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" 
                                    />
                                    <path 
                                        v-else-if="toastConfig(toast.type).icon === 'cube'"
                                        stroke-linecap="round" 
                                        stroke-linejoin="round" 
                                        stroke-width="2" 
                                        d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" 
                                    />
                                    <path 
                                        v-else
                                        stroke-linecap="round" 
                                        stroke-linejoin="round" 
                                        stroke-width="2" 
                                        d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" 
                                    />
                                </svg>
                            </div>
                            
                            <!-- Content -->
                            <div class="flex-1 text-white min-w-0">
                                <p v-if="toast.title" class="font-medium">{{ toast.title }}</p>
                                <p class="text-sm opacity-90" :class="{ 'mt-0.5': toast.title }">
                                    {{ toast.message }}
                                </p>
                                
                                <!-- Actions -->
                                <div v-if="toast.actions?.length" class="flex gap-2 mt-2">
                                    <button
                                        v-for="(action, idx) in toast.actions"
                                        :key="idx"
                                        @click="handleAction(toast, action)"
                                        class="text-sm font-medium text-white/90 hover:text-white underline"
                                    >
                                        {{ action.label }}
                                    </button>
                                </div>
                            </div>
                            
                            <!-- Close button -->
                            <button 
                                @click="dismissToast(toast.id)"
                                class="flex-shrink-0 text-white opacity-75 hover:opacity-100 transition-opacity"
                            >
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                        
                        <!-- Progress bar for auto-dismiss -->
                        <div v-if="!toast.persistent" class="h-1 bg-white/20">
                            <div 
                                class="h-full bg-white/40 transition-all duration-100 ease-linear"
                                :style="{ width: `${toast.progress}%` }"
                            ></div>
                        </div>
                    </div>
                </div>
            </TransitionGroup>
        </div>
    </Teleport>
    
    <!-- Slot for activity feed component or other content -->
    <slot 
        :toasts="toasts" 
        :activity-feed="activityFeed"
        :add-toast="addToast"
        :add-activity="addActivity"
        :dismiss-toast="dismissToast"
    />
</template>

<style scoped>
.toast-enter-active,
.toast-leave-active {
    transition: all 0.3s ease;
}

.toast-enter-from {
    opacity: 0;
    transform: translateX(100%);
}

.toast-leave-to {
    opacity: 0;
    transform: translateX(100%);
}

/* Adjust for left-side positions */
[class*="left-4"] .toast-enter-from,
[class*="left-4"] .toast-leave-to {
    transform: translateX(-100%);
}

/* Adjust for center positions */
[class*="-translate-x-1/2"] .toast-enter-from,
[class*="-translate-x-1/2"] .toast-leave-to {
    transform: translateY(-20px) translateX(-50%);
}
</style>
