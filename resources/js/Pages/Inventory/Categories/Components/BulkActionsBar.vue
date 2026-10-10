<template>
    <transition
        enter-active-class="transition ease-out duration-300"
        enter-from-class="transform opacity-0 translate-y-2"
        enter-to-class="transform opacity-100 translate-y-0"
        leave-active-class="transition ease-in duration-200"
        leave-from-class="transform opacity-100 translate-y-0"
        leave-to-class="transform opacity-0 translate-y-2"
    >
        <div class="bg-indigo-600 dark:bg-indigo-700 rounded-xl shadow-lg border border-indigo-500 dark:border-indigo-600">
            <div class="px-6 py-4">
                <div class="flex items-center justify-between">
                    <!-- Selection Info -->
                    <div class="flex items-center space-x-4">
                        <div class="flex items-center">
                            <div class="w-10 h-10 bg-indigo-500 dark:bg-indigo-600 rounded-lg flex items-center justify-center">
                                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>
                        </div>
                        <div>
                            <div class="text-white font-medium">
                                {{ selectedCount }} {{ selectedCount === 1 ? 'category' : 'categories' }} selected
                            </div>
                            <div class="text-indigo-200 text-sm">
                                Choose an action to apply to all selected items
                            </div>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="flex items-center space-x-3">
                        <!-- Activate -->
                        <button
                            @click="$emit('bulk-activate')"
                            class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-lg text-green-700 bg-green-100 hover:bg-green-200 dark:bg-green-900/30 dark:text-green-400 dark:hover:bg-green-900/50 transition-colors"
                            title="Activate selected categories"
                        >
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                            </svg>
                            Activate
                        </button>

                        <!-- Deactivate -->
                        <button
                            @click="$emit('bulk-deactivate')"
                            class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-lg text-amber-700 bg-amber-100 hover:bg-amber-200 dark:bg-amber-900/30 dark:text-amber-400 dark:hover:bg-amber-900/50 transition-colors"
                            title="Deactivate selected categories"
                        >
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.878 9.878L3 3m6.878 6.878L21 21" />
                            </svg>
                            Deactivate
                        </button>

                        <!-- Move -->
                        <div class="relative" ref="moveDropdownRef">
                            <button
                                @click="showMoveDropdown = !showMoveDropdown"
                                class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-lg text-blue-700 bg-blue-100 hover:bg-blue-200 dark:bg-blue-900/30 dark:text-blue-400 dark:hover:bg-blue-900/50 transition-colors"
                                title="Move selected categories"
                            >
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
                                </svg>
                                Move
                                <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                </svg>
                            </button>

                            <!-- Move Dropdown -->
                            <transition
                                enter-active-class="transition ease-out duration-100"
                                enter-from-class="transform opacity-0 scale-95"
                                enter-to-class="transform opacity-100 scale-100"
                                leave-active-class="transition ease-in duration-75"
                                leave-from-class="transform opacity-100 scale-100"
                                leave-to-class="transform opacity-0 scale-95"
                            >
                                <div
                                    v-show="showMoveDropdown"
                                    class="absolute bottom-full mb-2 right-0 w-64 bg-white dark:bg-gray-800 rounded-lg shadow-lg ring-1 ring-black ring-opacity-5 z-50 border border-gray-200 dark:border-gray-700"
                                >
                                    <div class="p-4">
                                        <h3 class="text-sm font-medium text-gray-900 dark:text-gray-100 mb-3">
                                            Move to Parent Category
                                        </h3>
                                        <div class="space-y-2">
                                            <button
                                                @click="handleBulkMove(null)"
                                                class="w-full text-left px-3 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition-colors"
                                            >
                                                <svg class="w-4 h-4 mr-2 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2H5a2 2 0 00-2-2z" />
                                                </svg>
                                                Root Level
                                            </button>
                                            <div
                                                v-for="parent in availableParents"
                                                :key="parent.id"
                                                class="w-full text-left px-3 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition-colors cursor-pointer"
                                                @click="handleBulkMove(parent.id)"
                                            >
                                                <svg class="w-4 h-4 mr-2 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                                                </svg>
                                                {{ parent.name }}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </transition>
                        </div>

                        <!-- Export -->
                        <button
                            @click="$emit('bulk-export')"
                            class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-lg text-purple-700 bg-purple-100 hover:bg-purple-200 dark:bg-purple-900/30 dark:text-purple-400 dark:hover:bg-purple-900/50 transition-colors"
                            title="Export selected categories"
                        >
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            Export
                        </button>

                        <!-- Delete -->
                        <button
                            @click="$emit('bulk-delete')"
                            class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-lg text-red-700 bg-red-100 hover:bg-red-200 dark:bg-red-900/30 dark:text-red-400 dark:hover:bg-red-900/50 transition-colors"
                            title="Delete selected categories"
                        >
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                            </svg>
                            Delete
                        </button>

                        <!-- Cancel Selection -->
                        <button
                            @click="$emit('clear-selection')"
                            class="inline-flex items-center px-4 py-2 border border-indigo-400 text-sm font-medium rounded-lg text-white hover:bg-indigo-500 dark:hover:bg-indigo-600 transition-colors"
                            title="Clear selection"
                        >
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                            Cancel
                        </button>
                    </div>
                </div>

                <!-- Progress Bar (when processing bulk actions) -->
                <div v-if="isProcessing" class="mt-4">
                    <div class="flex items-center justify-between text-sm text-indigo-200 mb-2">
                        <span>{{ processingMessage }}</span>
                        <span>{{ processedCount }}/{{ selectedCount }}</span>
                    </div>
                    <div class="w-full bg-indigo-500 rounded-full h-2">
                        <div 
                            class="bg-white rounded-full h-2 transition-all duration-300 ease-out"
                            :style="{ width: `${progressPercentage}%` }"
                        ></div>
                    </div>
                </div>
            </div>
        </div>
    </transition>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'

const props = defineProps({
    selectedCount: {
        type: Number,
        required: true
    },
    availableParents: {
        type: Array,
        default: () => []
    },
    isProcessing: {
        type: Boolean,
        default: false
    },
    processingMessage: {
        type: String,
        default: 'Processing...'
    },
    processedCount: {
        type: Number,
        default: 0
    }
})

const emit = defineEmits([
    'bulk-activate',
    'bulk-deactivate',
    'bulk-move',
    'bulk-export',
    'bulk-delete',
    'clear-selection'
])

// State
const showMoveDropdown = ref(false)
const moveDropdownRef = ref(null)

// Computed
const progressPercentage = computed(() => {
    if (props.selectedCount === 0) return 0
    return Math.round((props.processedCount / props.selectedCount) * 100)
})

// Methods
const handleBulkMove = (parentId) => {
    emit('bulk-move', parentId)
    showMoveDropdown.value = false
}

const closeMoveDropdown = (event) => {
    if (moveDropdownRef.value && !moveDropdownRef.value.contains(event.target)) {
        showMoveDropdown.value = false
    }
}

// Lifecycle
onMounted(() => {
    document.addEventListener('click', closeMoveDropdown)
})

onUnmounted(() => {
    document.removeEventListener('click', closeMoveDropdown)
})
</script>

<style scoped>
/* Custom button styles for bulk actions */
.bulk-action-btn {
    @apply inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-lg transition-all duration-200;
}

.bulk-action-btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.bulk-action-btn:active {
    transform: translateY(0);
}

/* Progress bar animation */
.progress-bar {
    animation: progressGlow 2s ease-in-out infinite alternate;
}

@keyframes progressGlow {
    from {
        box-shadow: 0 0 5px rgba(255, 255, 255, 0.5);
    }
    to {
        box-shadow: 0 0 20px rgba(255, 255, 255, 0.8);
    }
}

/* Smooth slide transitions */
.slide-enter-active,
.slide-leave-active {
    transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
}

.slide-enter-from {
    opacity: 0;
    transform: translateY(20px);
}

.slide-leave-to {
    opacity: 0;
    transform: translateY(-20px);
}

/* Custom dropdown animation */
.dropdown-enter-active {
    transition: all 0.15s ease-out;
}

.dropdown-leave-active {
    transition: all 0.1s ease-in;
}

.dropdown-enter-from {
    opacity: 0;
    transform: translateY(-10px) scale(0.95);
}

.dropdown-leave-to {
    opacity: 0;
    transform: translateY(-10px) scale(0.95);
}

/* Mobile responsiveness */
@media (max-width: 640px) {
    .bulk-actions-container {
        flex-direction: column;
        space-y: 3;
    }
    
    .actions-group {
        flex-wrap: wrap;
        gap: 0.5rem;
    }
    
    .bulk-action-btn {
        flex: 1;
        min-width: 120px;
    }
}

@media (max-width: 480px) {
    .bulk-action-btn {
        padding: 0.5rem 0.75rem;
        font-size: 0.875rem;
    }
    
    .bulk-action-btn svg {
        width: 1rem;
        height: 1rem;
    }
}
</style>
