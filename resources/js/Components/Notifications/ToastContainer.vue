<script setup>
import { ref, computed, onMounted } from 'vue';

const props = defineProps({
    position: {
        type: String,
        default: 'top-right',
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
    gap: {
        type: Number,
        default: 12 // gap in pixels
    }
});

const toasts = ref([]);

// Position classes mapping
const positionClasses = computed(() => {
    const baseClasses = 'fixed z-[100] flex pointer-events-none';
    const positions = {
        'top-right': `${baseClasses} top-4 right-4 flex-col`,
        'bottom-right': `${baseClasses} bottom-4 right-4 flex-col-reverse`,
        'top-left': `${baseClasses} top-4 left-4 flex-col`,
        'bottom-left': `${baseClasses} bottom-4 left-4 flex-col-reverse`,
        'top-center': `${baseClasses} top-4 left-1/2 -translate-x-1/2 flex-col items-center`,
        'bottom-center': `${baseClasses} bottom-4 left-1/2 -translate-x-1/2 flex-col-reverse items-center`,
    };
    return positions[props.position];
});

// Toast type configurations
const typeConfig = {
    success: {
        bg: 'bg-green-50 dark:bg-green-900/50',
        border: 'border-green-200 dark:border-green-800',
        icon: 'bg-green-100 dark:bg-green-800 text-green-600 dark:text-green-300',
        title: 'text-green-800 dark:text-green-200',
        message: 'text-green-700 dark:text-green-300',
    },
    error: {
        bg: 'bg-red-50 dark:bg-red-900/50',
        border: 'border-red-200 dark:border-red-800',
        icon: 'bg-red-100 dark:bg-red-800 text-red-600 dark:text-red-300',
        title: 'text-red-800 dark:text-red-200',
        message: 'text-red-700 dark:text-red-300',
    },
    warning: {
        bg: 'bg-yellow-50 dark:bg-yellow-900/50',
        border: 'border-yellow-200 dark:border-yellow-800',
        icon: 'bg-yellow-100 dark:bg-yellow-800 text-yellow-600 dark:text-yellow-300',
        title: 'text-yellow-800 dark:text-yellow-200',
        message: 'text-yellow-700 dark:text-yellow-300',
    },
    info: {
        bg: 'bg-blue-50 dark:bg-blue-900/50',
        border: 'border-blue-200 dark:border-blue-800',
        icon: 'bg-blue-100 dark:bg-blue-800 text-blue-600 dark:text-blue-300',
        title: 'text-blue-800 dark:text-blue-200',
        message: 'text-blue-700 dark:text-blue-300',
    },
};

// Get config for a toast type
const getConfig = (type) => typeConfig[type] || typeConfig.info;

// Add a new toast
const add = (options) => {
    const id = `toast-${Date.now()}-${Math.random().toString(36).substr(2, 9)}`;
    const toast = {
        id,
        type: 'info',
        title: '',
        message: '',
        duration: props.defaultDuration,
        closable: true,
        progress: true,
        icon: true,
        ...options,
        createdAt: Date.now(),
        remaining: options.duration || props.defaultDuration,
    };

    toasts.value.push(toast);

    // Limit max toasts
    while (toasts.value.length > props.maxToasts) {
        toasts.value.shift();
    }

    // Start auto-dismiss timer
    if (toast.duration > 0) {
        startTimer(toast);
    }

    return id;
};

// Start the dismiss timer for a toast
const startTimer = (toast) => {
    const tick = () => {
        const t = toasts.value.find(t => t.id === toast.id);
        if (!t || t.paused) return;

        t.remaining -= 100;
        if (t.remaining <= 0) {
            remove(toast.id);
        } else {
            setTimeout(tick, 100);
        }
    };
    setTimeout(tick, 100);
};

// Pause timer on hover
const pause = (id) => {
    const toast = toasts.value.find(t => t.id === id);
    if (toast) toast.paused = true;
};

// Resume timer
const resume = (id) => {
    const toast = toasts.value.find(t => t.id === id);
    if (toast && toast.duration > 0) {
        toast.paused = false;
        startTimer(toast);
    }
};

// Remove a toast
const remove = (id) => {
    const index = toasts.value.findIndex(t => t.id === id);
    if (index > -1) {
        toasts.value.splice(index, 1);
    }
};

// Clear all toasts
const clear = () => {
    toasts.value = [];
};

// Shorthand methods
const success = (message, options = {}) => add({ type: 'success', message, ...options });
const error = (message, options = {}) => add({ type: 'error', message, ...options });
const warning = (message, options = {}) => add({ type: 'warning', message, ...options });
const info = (message, options = {}) => add({ type: 'info', message, ...options });

// Calculate progress percentage
const getProgress = (toast) => {
    if (!toast.duration) return 100;
    return (toast.remaining / toast.duration) * 100;
};

// Expose methods
defineExpose({
    add,
    remove,
    clear,
    success,
    error,
    warning,
    info,
    toasts,
});

// Make toast available globally
onMounted(() => {
    if (typeof window !== 'undefined') {
        window.$toast = {
            add,
            remove,
            clear,
            success,
            error,
            warning,
            info,
        };
    }
});
</script>

<template>
    <Teleport to="body">
        <div :class="positionClasses" :style="{ gap: `${gap}px` }">
            <TransitionGroup name="toast-slide">
                <div
                    v-for="toast in toasts"
                    :key="toast.id"
                    class="pointer-events-auto w-full max-w-sm"
                    @mouseenter="pause(toast.id)"
                    @mouseleave="resume(toast.id)"
                >
                    <div
                        :class="[
                            'relative rounded-lg border shadow-lg overflow-hidden',
                            getConfig(toast.type).bg,
                            getConfig(toast.type).border,
                        ]"
                    >
                        <div class="p-4">
                            <div class="flex items-start gap-3">
                                <!-- Icon -->
                                <div 
                                    v-if="toast.icon !== false"
                                    :class="['flex-shrink-0 p-1 rounded-full', getConfig(toast.type).icon]"
                                >
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path 
                                            v-if="toast.type === 'success'"
                                            stroke-linecap="round" 
                                            stroke-linejoin="round" 
                                            stroke-width="2" 
                                            d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" 
                                        />
                                        <path 
                                            v-else-if="toast.type === 'error'"
                                            stroke-linecap="round" 
                                            stroke-linejoin="round" 
                                            stroke-width="2" 
                                            d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" 
                                        />
                                        <path 
                                            v-else-if="toast.type === 'warning'"
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
                                            d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" 
                                        />
                                    </svg>
                                </div>

                                <!-- Content -->
                                <div class="flex-1 min-w-0">
                                    <p 
                                        v-if="toast.title"
                                        :class="['font-semibold', getConfig(toast.type).title]"
                                    >
                                        {{ toast.title }}
                                    </p>
                                    <p 
                                        :class="[
                                            'text-sm',
                                            getConfig(toast.type).message,
                                            { 'mt-1': toast.title }
                                        ]"
                                    >
                                        {{ toast.message }}
                                    </p>
                                </div>

                                <!-- Close button -->
                                <button
                                    v-if="toast.closable !== false"
                                    @click="remove(toast.id)"
                                    class="flex-shrink-0 p-1 rounded-lg text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors"
                                >
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>
                        </div>

                        <!-- Progress bar -->
                        <div 
                            v-if="toast.progress !== false && toast.duration > 0"
                            class="absolute bottom-0 left-0 right-0 h-1 bg-black/10 dark:bg-white/10"
                        >
                            <div
                                class="h-full bg-current opacity-30 transition-all duration-100 ease-linear"
                                :class="getConfig(toast.type).title"
                                :style="{ width: `${getProgress(toast)}%` }"
                            />
                        </div>
                    </div>
                </div>
            </TransitionGroup>
        </div>
    </Teleport>
</template>

<style scoped>
.toast-slide-enter-active,
.toast-slide-leave-active {
    transition: all 0.3s ease;
}

.toast-slide-enter-from {
    opacity: 0;
    transform: translateX(100%);
}

.toast-slide-leave-to {
    opacity: 0;
    transform: translateX(100%);
}

.toast-slide-move {
    transition: transform 0.3s ease;
}
</style>
