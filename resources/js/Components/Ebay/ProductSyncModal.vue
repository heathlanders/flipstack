<script setup>
import { ref, computed, watch } from 'vue';

const props = defineProps({
    show: {
        type: Boolean,
        default: false
    },
    product: {
        type: Object,
        required: true
    },
    processing: {
        type: Boolean,
        default: false
    }
});

const emit = defineEmits(['close', 'sync']);

const syncOptions = ref({
    update_price: true,
    update_quantity: true,
    update_title: false,
    update_description: false,
    update_images: false,
    force_update: false
});

const hasExistingListing = computed(() => {
    return props.product?.ebay_listing?.ebay_item_id != null;
});

const syncLabel = computed(() => {
    return hasExistingListing.value ? 'Update Listing' : 'Create Listing';
});

const handleSync = () => {
    emit('sync', props.product.id, syncOptions.value);
};

const handleClose = () => {
    emit('close');
};

// Reset options when modal opens
watch(() => props.show, (newVal) => {
    if (newVal) {
        syncOptions.value = {
            update_price: true,
            update_quantity: true,
            update_title: false,
            update_description: false,
            update_images: false,
            force_update: false
        };
    }
});
</script>

<template>
    <Teleport to="body">
        <div v-if="show" class="fixed inset-0 z-50 overflow-y-auto">
            <div class="flex items-center justify-center min-h-screen px-4">
                <!-- Backdrop -->
                <div class="fixed inset-0 bg-black/50 transition-opacity" @click="handleClose"></div>
                
                <!-- Modal -->
                <div class="relative bg-white dark:bg-gray-800 rounded-xl shadow-xl max-w-lg w-full">
                    <!-- Header -->
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                        <div class="flex items-center justify-between">
                            <div>
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                                    {{ syncLabel }}
                                </h3>
                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                    {{ product.name }}
                                </p>
                            </div>
                            <button @click="handleClose" class="p-1 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                    </div>

                    <!-- Body -->
                    <div class="px-6 py-4">
                        <!-- Product Info -->
                        <div class="flex items-center gap-4 p-4 bg-gray-50 dark:bg-gray-900/50 rounded-lg mb-4">
                            <div class="w-16 h-16 bg-gray-200 dark:bg-gray-700 rounded-lg overflow-hidden flex-shrink-0">
                                <img v-if="product.primary_image" :src="product.primary_image" :alt="product.name" class="w-full h-full object-cover" />
                                <div v-else class="w-full h-full flex items-center justify-center">
                                    <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                    </svg>
                                </div>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium text-gray-900 dark:text-gray-100 line-clamp-2">{{ product.name }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                    SKU: {{ product.sku }}
                                </p>
                                <div class="flex items-center gap-4 mt-2">
                                    <span class="text-sm font-semibold text-gray-900 dark:text-gray-100">${{ parseFloat(product.price || 0).toFixed(2) }}</span>
                                    <span class="text-xs text-gray-500 dark:text-gray-400">Qty: {{ product.quantity }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Sync Options -->
                        <div v-if="hasExistingListing" class="space-y-3">
                            <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300">Update Options</h4>
                            
                            <label class="flex items-center gap-3 cursor-pointer">
                                <input v-model="syncOptions.update_price" type="checkbox" class="rounded border-gray-300 dark:border-gray-600 text-indigo-600 focus:ring-indigo-500" />
                                <span class="text-sm text-gray-700 dark:text-gray-300">Update price</span>
                            </label>

                            <label class="flex items-center gap-3 cursor-pointer">
                                <input v-model="syncOptions.update_quantity" type="checkbox" class="rounded border-gray-300 dark:border-gray-600 text-indigo-600 focus:ring-indigo-500" />
                                <span class="text-sm text-gray-700 dark:text-gray-300">Update quantity</span>
                            </label>

                            <label class="flex items-center gap-3 cursor-pointer">
                                <input v-model="syncOptions.update_title" type="checkbox" class="rounded border-gray-300 dark:border-gray-600 text-indigo-600 focus:ring-indigo-500" />
                                <span class="text-sm text-gray-700 dark:text-gray-300">Update title</span>
                            </label>

                            <label class="flex items-center gap-3 cursor-pointer">
                                <input v-model="syncOptions.update_description" type="checkbox" class="rounded border-gray-300 dark:border-gray-600 text-indigo-600 focus:ring-indigo-500" />
                                <span class="text-sm text-gray-700 dark:text-gray-300">Update description</span>
                            </label>

                            <label class="flex items-center gap-3 cursor-pointer">
                                <input v-model="syncOptions.update_images" type="checkbox" class="rounded border-gray-300 dark:border-gray-600 text-indigo-600 focus:ring-indigo-500" />
                                <span class="text-sm text-gray-700 dark:text-gray-300">Update images</span>
                            </label>

                            <div class="pt-2 mt-2 border-t border-gray-200 dark:border-gray-700">
                                <label class="flex items-center gap-3 cursor-pointer">
                                    <input v-model="syncOptions.force_update" type="checkbox" class="rounded border-gray-300 dark:border-gray-600 text-amber-600 focus:ring-amber-500" />
                                    <div>
                                        <span class="text-sm text-gray-700 dark:text-gray-300">Force full update</span>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">Override all fields, ignoring above options</p>
                                    </div>
                                </label>
                            </div>
                        </div>

                        <div v-else class="p-4 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg">
                            <p class="text-sm text-blue-700 dark:text-blue-400">
                                This product doesn't have an eBay listing yet. A new listing will be created with all product details.
                            </p>
                        </div>

                        <!-- Existing Listing Info -->
                        <div v-if="hasExistingListing && product.ebay_listing" class="mt-4 p-3 bg-gray-50 dark:bg-gray-900/50 rounded-lg">
                            <div class="flex items-center justify-between text-xs">
                                <span class="text-gray-500 dark:text-gray-400">eBay Item ID:</span>
                                <span class="font-mono text-gray-700 dark:text-gray-300">{{ product.ebay_listing.ebay_item_id }}</span>
                            </div>
                            <div v-if="product.ebay_listing.last_synced_at" class="flex items-center justify-between text-xs mt-1">
                                <span class="text-gray-500 dark:text-gray-400">Last synced:</span>
                                <span class="text-gray-700 dark:text-gray-300">{{ new Date(product.ebay_listing.last_synced_at).toLocaleString() }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Footer -->
                    <div class="px-6 py-4 bg-gray-50 dark:bg-gray-900/50 border-t border-gray-200 dark:border-gray-700 flex justify-end gap-3">
                        <button
                            @click="handleClose"
                            class="px-4 py-2 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600 transition"
                        >
                            Cancel
                        </button>
                        <button
                            @click="handleSync"
                            :disabled="processing"
                            class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg transition disabled:opacity-50 flex items-center gap-2"
                        >
                            <svg v-if="processing" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            {{ processing ? 'Syncing...' : syncLabel }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </Teleport>
</template>