<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue'
import { router, usePage } from '@inertiajs/vue3'

const props = defineProps({
    modelValue: {
        type: Boolean,
        default: false
    },
    suppliers: {
        type: Array,
        default: () => []
    },
    locations: {
        type: Array,
        default: () => []
    }
})

const emit = defineEmits(['update:modelValue', 'item-scanned', 'session-end'])

const page = usePage()

// State
const isOpen = ref(props.modelValue)
const inputRef = ref(null)
const barcodeInput = ref('')
const isProcessing = ref(false)
const lastScannedItem = ref(null)
const error = ref(null)

// Session settings
const settings = ref({
    supplier_id: null,
    location_id: null,
    default_price: null
})

// Session stats
const sessionStats = ref({
    items_scanned: 0,
    added_to_queue: 0,
    errors: 0
})

// Barcode scanner detection
const barcodeBuffer = ref('')
const lastKeyTime = ref(0)
const BARCODE_SCAN_TIMEOUT = 50

// Audio feedback
let successSound = null
let errorSound = null

onMounted(() => {
    // Create audio elements for feedback
    try {
        successSound = new Audio('data:audio/wav;base64,UklGRnoGAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQoGAACBhYqFbF1fdJivrJBhNjVgodDbq2EcBj+a2teleB8BPprh3Z1wFQZJnuDZlmkQBE+g4NiRYwsCVJ/f1IxeCAFYn93QhloHAFug3M6CWAgAXZ/c0H5aCABdn93RfloJAF2f3dJ+WgoAXp/d031aCwBen93UfloMAF+f3dV9WwwAYJ/e1nxcDQBgn97We1wOAGGf3td7XQ8AYaHe2HleEABiod/ZeV4RAGOi39p4XxIAZKPg23hgEwBlpOHceGETAGal4t14YhQAZ6bi3nhjFQBoqOPfeGQWAGmp5OB4ZRcAaqrk4XhmGABrq+XieGcZAGyr5uN3aBsAbazm5HdpHABurefl')
        errorSound = new Audio('data:audio/wav;base64,UklGRnoGAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQoGAACBhYqFbF1fdJivrJBhNjVgodDbq2EcBj+a2teleB8BPprh3Z1wFQZJnuDZlmkQBE+g4NiRYwsCVJ/f1IxeCAFYn93QhloHAFug3M6CWAgAXZ/c0H5aCABdn93RfloJAF2f3dJ+WgoAXp/d031aCwBen93UfloMAF+f3dV9WwwAYJ/e1nxcDQBgn97We1wOAGGf3td7XQ8AYaHe2HleEABiod/ZeV4RAGOi39p4XxIAZKPg23hgEwBlpOHceGETAGal4t14YhQAZ6bi3nhjFQBoqOPfeGQWAGmp5OB4ZRcAaqrk4XhmGABrq+XieGcZAGyr5uN3aBsAbazm5HdpHABurefl')
    } catch (e) {
        console.warn('Could not initialize audio feedback:', e)
    }

    // Listen for global open event
    window.addEventListener('open-quick-intake', handleOpenEvent)
})

onUnmounted(() => {
    window.removeEventListener('open-quick-intake', handleOpenEvent)
})

const handleOpenEvent = () => {
    open()
}

// Watch external modelValue
watch(() => props.modelValue, (newValue) => {
    if (newValue !== isOpen.value) {
        if (newValue) {
            open()
        } else {
            close()
        }
    }
})

// Open panel
const open = () => {
    isOpen.value = true
    emit('update:modelValue', true)
    error.value = null

    // Focus input after animation
    setTimeout(() => {
        inputRef.value?.focus()
    }, 100)
}

// Close panel
const close = () => {
    isOpen.value = false
    emit('update:modelValue', false)
}

// Handle barcode input
const handleKeyDown = (event) => {
    const now = Date.now()

    // Check for barcode scanner input (rapid keystrokes)
    if (now - lastKeyTime.value < BARCODE_SCAN_TIMEOUT) {
        barcodeBuffer.value += event.key
    } else {
        barcodeBuffer.value = event.key
    }
    lastKeyTime.value = now
}

const handleKeyUp = (event) => {
    if (event.key === 'Enter') {
        // Check if this was a barcode scan or manual input
        if (barcodeBuffer.value.length > 5 || barcodeInput.value.trim()) {
            const barcode = barcodeBuffer.value.length > 5
                ? barcodeBuffer.value.replace('Enter', '').trim()
                : barcodeInput.value.trim()

            if (barcode) {
                processBarcode(barcode)
            }
        }
        barcodeBuffer.value = ''
    }
}

// Process scanned barcode
const processBarcode = async (barcode) => {
    if (isProcessing.value) return

    isProcessing.value = true
    error.value = null
    sessionStats.value.items_scanned++

    try {
        const response = await fetch('/api/intake-queue/quick-add', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
            },
            body: JSON.stringify({
                barcode: barcode,
                supplier_id: settings.value.supplier_id,
                location_id: settings.value.location_id,
                default_price: settings.value.default_price
            })
        })

        const data = await response.json()

        if (response.ok && data.success) {
            lastScannedItem.value = {
                barcode: barcode,
                product: data.product,
                status: 'success',
                message: data.message || 'Added to queue'
            }
            sessionStats.value.added_to_queue++
            playSound('success')
            emit('item-scanned', lastScannedItem.value)
        } else {
            lastScannedItem.value = {
                barcode: barcode,
                status: 'error',
                message: data.message || 'Failed to add item'
            }
            sessionStats.value.errors++
            playSound('error')
            error.value = data.message || 'Failed to process barcode'
        }
    } catch (e) {
        console.error('Failed to process barcode:', e)
        lastScannedItem.value = {
            barcode: barcode,
            status: 'error',
            message: 'Network error'
        }
        sessionStats.value.errors++
        playSound('error')
        error.value = 'Network error occurred'
    } finally {
        isProcessing.value = false
        barcodeInput.value = ''
        inputRef.value?.focus()
    }
}

// Play sound feedback
const playSound = (type) => {
    try {
        if (type === 'success' && successSound) {
            successSound.currentTime = 0
            successSound.play().catch(() => {})
        } else if (type === 'error' && errorSound) {
            errorSound.currentTime = 0
            errorSound.play().catch(() => {})
        }
    } catch (e) {
        // Ignore audio errors
    }
}

// End session
const endSession = () => {
    emit('session-end', sessionStats.value)
    sessionStats.value = {
        items_scanned: 0,
        added_to_queue: 0,
        errors: 0
    }
    lastScannedItem.value = null
    close()
}

// View intake queue
const viewQueue = () => {
    router.visit(route('intake-queue.index'))
    close()
}

// Expose methods
defineExpose({ open, close })
</script>

<template>
    <Teleport to="body">
        <!-- Backdrop -->
        <Transition
            enter-active-class="duration-300 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="duration-200 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="isOpen"
                class="fixed inset-0 bg-black/50 z-40"
                @click="close"
            ></div>
        </Transition>

        <!-- Panel -->
        <Transition
            enter-active-class="duration-300 ease-out"
            enter-from-class="translate-x-full"
            enter-to-class="translate-x-0"
            leave-active-class="duration-200 ease-in"
            leave-from-class="translate-x-0"
            leave-to-class="translate-x-full"
        >
            <div
                v-if="isOpen"
                class="fixed inset-y-0 right-0 w-full max-w-md bg-white dark:bg-slate-800 shadow-xl z-50 flex flex-col"
                @click.stop
            >
                <!-- Header -->
                <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 dark:border-slate-700">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Quick Intake Mode</h2>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Scan products to add to queue</p>
                    </div>
                    <button
                        @click="close"
                        class="p-2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition-colors"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <!-- Barcode Input -->
                <div class="px-6 py-4 border-b border-gray-200 dark:border-slate-700">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        Scan barcode or enter SKU
                    </label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                            <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z" />
                            </svg>
                        </div>
                        <input
                            ref="inputRef"
                            v-model="barcodeInput"
                            type="text"
                            placeholder="Waiting for barcode..."
                            @keydown="handleKeyDown"
                            @keyup="handleKeyUp"
                            :disabled="isProcessing"
                            class="w-full pl-10 pr-4 py-3 bg-gray-50 dark:bg-slate-700 border border-gray-200 dark:border-slate-600 rounded-xl text-gray-900 dark:text-white placeholder-gray-400 focus:ring-2 focus:ring-indigo-500 focus:border-transparent text-lg font-mono"
                        />
                        <div v-if="isProcessing" class="absolute inset-y-0 right-0 flex items-center pr-3">
                            <svg class="w-5 h-5 text-indigo-500 animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                        </div>
                    </div>
                </div>

                <!-- Last Scanned Item -->
                <div v-if="lastScannedItem" class="px-6 py-4 border-b border-gray-200 dark:border-slate-700">
                    <div class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-2">Last scanned</div>
                    <div
                        :class="[
                            'p-4 rounded-xl',
                            lastScannedItem.status === 'success'
                                ? 'bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-800'
                                : 'bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800'
                        ]"
                    >
                        <div class="flex items-start gap-3">
                            <div :class="[
                                'flex-shrink-0 w-10 h-10 rounded-lg flex items-center justify-center',
                                lastScannedItem.status === 'success'
                                    ? 'bg-emerald-100 dark:bg-emerald-900/50'
                                    : 'bg-red-100 dark:bg-red-900/50'
                            ]">
                                <svg v-if="lastScannedItem.status === 'success'" class="w-5 h-5 text-emerald-600 dark:text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                </svg>
                                <svg v-else class="w-5 h-5 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="font-mono text-sm text-gray-600 dark:text-gray-400">{{ lastScannedItem.barcode }}</div>
                                <div v-if="lastScannedItem.product" class="font-medium text-gray-900 dark:text-white mt-1">
                                    {{ lastScannedItem.product.name }}
                                </div>
                                <div :class="[
                                    'text-sm mt-1',
                                    lastScannedItem.status === 'success'
                                        ? 'text-emerald-700 dark:text-emerald-300'
                                        : 'text-red-700 dark:text-red-300'
                                ]">
                                    {{ lastScannedItem.message }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Error Message -->
                <div v-if="error && !lastScannedItem" class="px-6 py-4 bg-red-50 dark:bg-red-900/20 border-b border-red-200 dark:border-red-800">
                    <div class="flex items-center gap-2 text-red-700 dark:text-red-300">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                        <span>{{ error }}</span>
                    </div>
                </div>

                <!-- Quick Settings -->
                <div class="px-6 py-4 border-b border-gray-200 dark:border-slate-700 space-y-4">
                    <div class="text-sm font-medium text-gray-700 dark:text-gray-300">Quick Settings</div>

                    <!-- Supplier -->
                    <div>
                        <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Supplier</label>
                        <select
                            v-model="settings.supplier_id"
                            class="w-full px-3 py-2 bg-gray-50 dark:bg-slate-700 border border-gray-200 dark:border-slate-600 rounded-lg text-sm text-gray-900 dark:text-white focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                        >
                            <option :value="null">Select supplier...</option>
                            <option v-for="supplier in suppliers" :key="supplier.id" :value="supplier.id">
                                {{ supplier.name }}
                            </option>
                        </select>
                    </div>

                    <!-- Location -->
                    <div>
                        <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Location</label>
                        <select
                            v-model="settings.location_id"
                            class="w-full px-3 py-2 bg-gray-50 dark:bg-slate-700 border border-gray-200 dark:border-slate-600 rounded-lg text-sm text-gray-900 dark:text-white focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                        >
                            <option :value="null">Select location...</option>
                            <option v-for="location in locations" :key="location.id" :value="location.id">
                                {{ location.name }}
                            </option>
                        </select>
                    </div>

                    <!-- Default Price -->
                    <div>
                        <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Default Price</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                <span class="text-gray-400">$</span>
                            </div>
                            <input
                                v-model.number="settings.default_price"
                                type="number"
                                step="0.01"
                                placeholder="0.00"
                                class="w-full pl-8 pr-4 py-2 bg-gray-50 dark:bg-slate-700 border border-gray-200 dark:border-slate-600 rounded-lg text-sm text-gray-900 dark:text-white focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                            />
                        </div>
                    </div>
                </div>

                <!-- Session Stats -->
                <div class="px-6 py-4 flex-1">
                    <div class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">Session Stats</div>
                    <div class="grid grid-cols-3 gap-4">
                        <div class="text-center p-3 bg-gray-50 dark:bg-slate-700 rounded-xl">
                            <div class="text-2xl font-bold text-gray-900 dark:text-white">{{ sessionStats.items_scanned }}</div>
                            <div class="text-xs text-gray-500 dark:text-gray-400">Scanned</div>
                        </div>
                        <div class="text-center p-3 bg-emerald-50 dark:bg-emerald-900/20 rounded-xl">
                            <div class="text-2xl font-bold text-emerald-600 dark:text-emerald-400">{{ sessionStats.added_to_queue }}</div>
                            <div class="text-xs text-emerald-700 dark:text-emerald-300">Added</div>
                        </div>
                        <div class="text-center p-3 bg-red-50 dark:bg-red-900/20 rounded-xl">
                            <div class="text-2xl font-bold text-red-600 dark:text-red-400">{{ sessionStats.errors }}</div>
                            <div class="text-xs text-red-700 dark:text-red-300">Errors</div>
                        </div>
                    </div>
                </div>

                <!-- Footer Actions -->
                <div class="px-6 py-4 border-t border-gray-200 dark:border-slate-700 flex items-center gap-3">
                    <button
                        @click="viewQueue"
                        class="flex-1 px-4 py-2 bg-white dark:bg-slate-700 border border-gray-200 dark:border-slate-600 rounded-lg text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-slate-600 transition-colors"
                    >
                        View Queue
                    </button>
                    <button
                        @click="endSession"
                        class="flex-1 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-sm font-medium transition-colors"
                    >
                        End Session
                    </button>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
