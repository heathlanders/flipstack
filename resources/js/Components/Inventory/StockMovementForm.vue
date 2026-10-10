<script setup>
import { ref, reactive, computed, watch, onMounted } from 'vue';
import { router } from '@inertiajs/vue3';
import debounce from 'lodash/debounce';

const props = defineProps({
    locations: {
        type: Array,
        default: () => [],
    },
    reasonCodes: {
        type: Array,
        default: () => [],
    },
    movementTypes: {
        type: Array,
        default: () => [],
    },
    permissions: {
        type: Object,
        default: () => ({}),
    },
    preselectedProduct: {
        type: Object,
        default: null,
    },
});

const emit = defineEmits(['success', 'error', 'cancel']);

// Form state
const form = reactive({
    type: 'adjustment',
    items: [],
    reason: null,
    notes: '',
    reference_type: null,
    reference_number: null,
});

const errors = ref({});
const isSubmitting = ref(false);
const productSearchQuery = ref('');
const productSearchResults = ref([]);
const isSearching = ref(false);
const showProductSearch = ref(false);
const activeItemIndex = ref(null);

// Movement types that decrease stock (need availability check)
const decreaseTypes = ['sale', 'transfer_out', 'damaged', 'lost'];

// Computed
const availableMovementTypes = computed(() => {
    return props.movementTypes.filter(type => {
        // Filter based on permissions if needed
        return true;
    });
});

const canSubmit = computed(() => {
    if (form.items.length === 0) return false;
    
    return form.items.every(item => {
        if (!item.product_id) return false;
        if (!item.location_id) return false;
        if (!item.quantity || item.quantity <= 0) return false;
        return true;
    });
});

// Initialize
onMounted(() => {
    if (props.preselectedProduct) {
        addItem(props.preselectedProduct);
    } else {
        addEmptyItem();
    }
});

// Methods
const addEmptyItem = () => {
    form.items.push({
        product_id: null,
        product: null,
        location_id: props.locations.length === 1 ? props.locations[0].id : null,
        quantity: 1,
        unit_cost: null,
    });
};

const addItem = (product) => {
    form.items.push({
        product_id: product.id,
        product: product,
        location_id: props.locations.length === 1 ? props.locations[0].id : null,
        quantity: 1,
        unit_cost: null,
    });
};

const removeItem = (index) => {
    form.items.splice(index, 1);
    if (form.items.length === 0) {
        addEmptyItem();
    }
};

const searchProducts = debounce(async (query) => {
    if (!query || query.length < 2) {
        productSearchResults.value = [];
        return;
    }

    isSearching.value = true;
    try {
        const response = await fetch(`/api/v1/products/search?q=${encodeURIComponent(query)}&limit=10`, {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });
        
        if (response.ok) {
            const data = await response.json();
            productSearchResults.value = data.products || data.data || [];
        }
    } catch (error) {
        console.error('Product search failed:', error);
    } finally {
        isSearching.value = false;
    }
}, 400);

const selectProduct = (product, index) => {
    form.items[index].product_id = product.id;
    form.items[index].product = product;
    productSearchQuery.value = '';
    productSearchResults.value = [];
    showProductSearch.value = false;
    activeItemIndex.value = null;
};

const openProductSearch = (index) => {
    activeItemIndex.value = index;
    showProductSearch.value = true;
    productSearchQuery.value = '';
    productSearchResults.value = [];
};

const closeProductSearch = () => {
    showProductSearch.value = false;
    activeItemIndex.value = null;
    productSearchQuery.value = '';
    productSearchResults.value = [];
};

const validateQuantity = (item) => {
    if (!item.product) return true;
    
    if (!item.product.manage_stock || item.product.allow_backorders) {
        return true;
    }
    
    // Only validate for decrease types
    if (!decreaseTypes.includes(form.type)) {
        return true;
    }
    
    const availableStock = item.product.stock_quantity ?? 0;
    return item.quantity <= availableStock;
};

const submit = async () => {
    if (!canSubmit.value || isSubmitting.value) return;
    
    isSubmitting.value = true;
    errors.value = {};

    try {
        const payload = {
            type: form.type,
            items: form.items.map(item => ({
                product_id: item.product_id,
                location_id: item.location_id,
                quantity: item.quantity,
                unit_cost: item.unit_cost,
            })),
            reason: form.reason,
            notes: form.notes,
            reference_type: form.reference_type,
            reference_number: form.reference_number,
        };

        router.post(route('inventory.movements.store'), payload, {
            onSuccess: () => {
                emit('success');
            },
            onError: (errs) => {
                errors.value = errs;
                emit('error', errs);
            },
            onFinish: () => {
                isSubmitting.value = false;
            },
        });
    } catch (error) {
        console.error('Submit failed:', error);
        isSubmitting.value = false;
    }
};

const cancel = () => {
    emit('cancel');
    router.visit(route('inventory.movements.index'));
};

// Type colors for badges
const typeColors = {
    purchase: 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400',
    sale: 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400',
    adjustment: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400',
    transfer_in: 'bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-400',
    transfer_out: 'bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-400',
    return: 'bg-teal-100 text-teal-800 dark:bg-teal-900/30 dark:text-teal-400',
    damaged: 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400',
    lost: 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400',
    found: 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400',
};
</script>

<template>
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700">
        <form @submit.prevent="submit">
            <!-- Header -->
            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                    Record Stock Movement
                </h3>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                    Create a new inventory movement record
                </p>
            </div>

            <!-- Body -->
            <div class="px-6 py-4 space-y-6">
                <!-- Movement Type -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        Movement Type
                    </label>
                    <div class="grid grid-cols-3 md:grid-cols-5 gap-2">
                        <button
                            v-for="type in availableMovementTypes"
                            :key="type.value"
                            type="button"
                            @click="form.type = type.value"
                            :class="[
                                'px-3 py-2 text-xs font-medium rounded-lg border transition-colors',
                                form.type === type.value
                                    ? typeColors[type.value] + ' border-current'
                                    : 'bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-300 border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-600'
                            ]"
                        >
                            {{ type.label }}
                        </button>
                    </div>
                </div>

                <!-- Items -->
                <div>
                    <div class="flex items-center justify-between mb-3">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Products
                        </label>
                        <button
                            type="button"
                            @click="addEmptyItem"
                            class="text-sm text-indigo-600 dark:text-indigo-400 hover:text-indigo-700 dark:hover:text-indigo-300 font-medium"
                        >
                            + Add Another Product
                        </button>
                    </div>

                    <div class="space-y-3">
                        <div
                            v-for="(item, index) in form.items"
                            :key="index"
                            class="p-4 bg-gray-50 dark:bg-gray-700/50 rounded-lg border border-gray-200 dark:border-gray-600"
                        >
                            <div class="flex items-start justify-between mb-3">
                                <span class="text-xs font-medium text-gray-500 dark:text-gray-400">
                                    Item {{ index + 1 }}
                                </span>
                                <button
                                    v-if="form.items.length > 1"
                                    type="button"
                                    @click="removeItem(index)"
                                    class="text-red-500 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300"
                                >
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                                <!-- Product Selection -->
                                <div class="md:col-span-2">
                                    <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">
                                        Product
                                    </label>
                                    <div
                                        v-if="item.product"
                                        class="flex items-center justify-between px-3 py-2 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg"
                                    >
                                        <div>
                                            <p class="font-medium text-gray-900 dark:text-white text-sm">
                                                {{ item.product.name }}
                                            </p>
                                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                                SKU: {{ item.product.sku }} • Stock: {{ item.product.stock_quantity ?? 0 }}
                                            </p>
                                        </div>
                                        <button
                                            type="button"
                                            @click="openProductSearch(index)"
                                            class="text-indigo-600 dark:text-indigo-400 hover:text-indigo-700 text-sm font-medium"
                                        >
                                            Change
                                        </button>
                                    </div>
                                    <button
                                        v-else
                                        type="button"
                                        @click="openProductSearch(index)"
                                        class="w-full px-3 py-2 text-left bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-500 dark:text-gray-400 hover:border-indigo-500 transition-colors"
                                    >
                                        Click to select a product...
                                    </button>
                                </div>

                                <!-- Location -->
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">
                                        Location
                                    </label>
                                    <select
                                        v-model="item.location_id"
                                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                                    >
                                        <option :value="null">Select...</option>
                                        <option
                                            v-for="location in locations"
                                            :key="location.id"
                                            :value="location.id"
                                        >
                                            {{ location.name }}
                                        </option>
                                    </select>
                                </div>

                                <!-- Quantity -->
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">
                                        Quantity
                                    </label>
                                    <input
                                        type="number"
                                        v-model.number="item.quantity"
                                        min="1"
                                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                                        :class="{ 'border-red-500': !validateQuantity(item) }"
                                    />
                                    <p v-if="!validateQuantity(item)" class="mt-1 text-xs text-red-500">
                                        Exceeds available stock
                                    </p>
                                </div>
                            </div>

                            <!-- Unit Cost (for purchases) -->
                            <div v-if="form.type === 'purchase'" class="mt-3">
                                <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">
                                    Unit Cost (optional)
                                </label>
                                <input
                                    type="number"
                                    v-model.number="item.unit_cost"
                                    min="0"
                                    step="0.01"
                                    placeholder="0.00"
                                    class="w-32 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                                />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Reason -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        Reason
                    </label>
                    <select
                        v-model="form.reason"
                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                    >
                        <option :value="null">Select a reason (optional)...</option>
                        <option
                            v-for="reason in reasonCodes"
                            :key="reason.value"
                            :value="reason.label"
                        >
                            {{ reason.label }}
                        </option>
                    </select>
                </div>

                <!-- Notes -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        Notes
                    </label>
                    <textarea
                        v-model="form.notes"
                        rows="3"
                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-indigo-500 focus:border-transparent resize-none"
                        placeholder="Add any additional notes about this movement..."
                    ></textarea>
                </div>

                <!-- Reference (optional) -->
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Reference Type
                        </label>
                        <select
                            v-model="form.reference_type"
                            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                        >
                            <option :value="null">None</option>
                            <option value="order">Order</option>
                            <option value="purchase_order">Purchase Order</option>
                            <option value="transfer">Transfer</option>
                            <option value="return">Return</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Reference Number
                        </label>
                        <input
                            type="text"
                            v-model="form.reference_number"
                            placeholder="e.g., PO-12345"
                            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                        />
                    </div>
                </div>

                <!-- Error Display -->
                <div v-if="errors.general" class="p-4 bg-red-50 dark:bg-red-900/30 border border-red-200 dark:border-red-800 rounded-lg">
                    <p class="text-sm text-red-600 dark:text-red-400">{{ errors.general }}</p>
                </div>
            </div>

            <!-- Footer -->
            <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700 flex items-center justify-end gap-3">
                <button
                    type="button"
                    @click="cancel"
                    class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors"
                >
                    Cancel
                </button>
                <button
                    type="submit"
                    :disabled="!canSubmit || isSubmitting"
                    class="px-4 py-2 text-sm font-medium text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors flex items-center gap-2"
                >
                    <svg v-if="isSubmitting" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                    </svg>
                    {{ isSubmitting ? 'Processing...' : 'Submit Movement' }}
                </button>
            </div>
        </form>

        <!-- Product Search Modal -->
        <Teleport to="body">
            <div
                v-if="showProductSearch"
                class="fixed inset-0 z-50 overflow-y-auto"
            >
                <div class="flex min-h-full items-center justify-center p-4">
                    <div
                        class="fixed inset-0 bg-black/50 transition-opacity"
                        @click="closeProductSearch"
                    ></div>
                    
                    <div class="relative w-full max-w-lg bg-white dark:bg-gray-800 rounded-xl shadow-xl">
                        <div class="p-4 border-b border-gray-200 dark:border-gray-700">
                            <h4 class="text-lg font-semibold text-gray-900 dark:text-white">
                                Select Product
                            </h4>
                        </div>
                        
                        <div class="p-4">
                            <input
                                type="text"
                                v-model="productSearchQuery"
                                @input="searchProducts(productSearchQuery)"
                                placeholder="Search by name or SKU..."
                                class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                                autofocus
                            />
                        </div>
                        
                        <div class="max-h-64 overflow-y-auto">
                            <div v-if="isSearching" class="p-4 text-center text-gray-500 dark:text-gray-400">
                                <svg class="animate-spin w-6 h-6 mx-auto" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                                </svg>
                                <p class="mt-2">Searching...</p>
                            </div>
                            
                            <div v-else-if="productSearchResults.length === 0 && productSearchQuery.length >= 2" class="p-4 text-center text-gray-500 dark:text-gray-400">
                                No products found
                            </div>
                            
                            <div v-else-if="productSearchQuery.length < 2" class="p-4 text-center text-gray-500 dark:text-gray-400">
                                Type at least 2 characters to search
                            </div>
                            
                            <button
                                v-for="product in productSearchResults"
                                :key="product.id"
                                type="button"
                                @click="selectProduct(product, activeItemIndex)"
                                class="w-full px-4 py-3 text-left hover:bg-gray-50 dark:hover:bg-gray-700 border-b border-gray-100 dark:border-gray-700 last:border-b-0"
                            >
                                <p class="font-medium text-gray-900 dark:text-white">
                                    {{ product.name }}
                                </p>
                                <p class="text-sm text-gray-500 dark:text-gray-400">
                                    SKU: {{ product.sku }} • Stock: {{ product.stock_quantity ?? 0 }}
                                </p>
                            </button>
                        </div>
                        
                        <div class="p-4 border-t border-gray-200 dark:border-gray-700">
                            <button
                                type="button"
                                @click="closeProductSearch"
                                class="w-full px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-700 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors"
                            >
                                Cancel
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </Teleport>
    </div>
</template>
