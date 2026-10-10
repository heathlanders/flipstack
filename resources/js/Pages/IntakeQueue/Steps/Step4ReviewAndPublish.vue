<script setup>
import { ref, computed, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import ShippingRateSelector from '@/Components/IntakeQueue/ShippingRateSelector.vue';
import EbayPolicySelector from '@/Components/IntakeQueue/EbayPolicySelector.vue';
import TextInput from '@/Components/TextInput.vue';
import InputLabel from '@/Components/InputLabel.vue';

const props = defineProps({
    queueItem: Object,
    form: Object,
    locations: { type: Array, default: () => [] },
    isPublishing: {
        type: Boolean,
        default: false,
    },
});

const resolvedLocationLabel = computed(() => {
    const loc = props.locations.find(l => l.id === props.form?.quick_location_id);
    if (!loc) return props.form?.location || props.queueItem?.quick_location_name || props.queueItem?.quick_location || '';
    const bin = loc.bins?.find(b => b.id === props.form?.quick_bin_id);
    return bin
        ? `${loc.name} → ${bin.zone_code}-${bin.code}`
        : loc.name;
});

const emit = defineEmits(['prev', 'publish', 'save-draft']);

const selectedPlatforms = ref(['ebay']);
const selectedShippingRate = ref(null);
const selectedEbayPolicies = ref({
    fulfillment: null,
    payment: null,
    return: null,
});
const publishingOptions = ref({
    ebay: {
        duration: 'GTC',
        format: 'fixed_price',
        promoted: false,
        auto_accept_returns: true,
    },
    amazon: {
        fulfillment: 'FBM',
        gift_wrap: false,
    },
});

// Editable package dimensions -- seeded from the form/queue item so the user can
// correct them in place (e.g. after a listing failure) and re-fetch shipping rates.
const editableDims = ref({
    weight_lbs: props.form?.weight_lbs ?? props.queueItem?.weight_lbs ?? null,
    length_inches: props.form?.length_inches ?? props.queueItem?.length_inches ?? null,
    width_inches: props.form?.width_inches ?? props.queueItem?.width_inches ?? null,
    height_inches: props.form?.height_inches ?? props.queueItem?.height_inches ?? null,
});
const savingDims = ref(false);

// Compute dimensions for shipping rate lookup (drives ShippingRateSelector live)
const packageDimensions = computed(() => ({ ...editableDims.value }));

const saveDimensions = () => {
    savingDims.value = true;
    // Mirror into the shared form so later steps stay consistent.
    if (props.form) {
        Object.assign(props.form, editableDims.value);
    }
    router.patch(route('intake-queue.update', props.queueItem.id), { ...editableDims.value }, {
        preserveScroll: true,
        preserveState: true,
        onFinish: () => { savingDims.value = false; },
    });
};

// Product data for policy rules evaluation
const productDataForPolicies = computed(() => ({
    price: props.form?.list_price,
    weight_lbs: packageDimensions.value.weight_lbs,
    shipping_class: props.queueItem?.suggested_shipping_class,
    condition: props.form?.condition,
    category: props.form?.category_name || props.queueItem?.product_category,
    estimated_shipping_cost: props.form?.shipping_cost,
}));

// Handle shipping rate selection
const handleShippingRateSelected = (rate) => {
    selectedShippingRate.value = rate;
    if (props.form && rate?.rate) {
        props.form.shipping_cost = rate.insurance_included ? rate.total_rate : rate.rate;
        props.form.additional_item_cost = rate.additional_item_cost ?? null;
        props.form.insurance_enabled = rate.insurance_enabled ?? false;
        props.form.confirmation_type = rate.confirmation_type ?? 'none';
    }
};

// Use local state if not controlled by parent
const localPublishing = ref(false);
const isCurrentlyPublishing = computed(() => props.isPublishing || localPublishing.value);

const profitSummary = computed(() => {
    if (!props.form.list_price || !props.form.cost_basis) return null;

    const fees = (props.form.list_price * props.form.platform_fees_percent) / 100;
    const shipping = props.form.shipping_cost || 0;
    const insuranceCost = selectedShippingRate.value?.insurance_enabled
        ? parseFloat(selectedShippingRate.value?.insurance_cost || 0)
        : 0;
    const totalShipping = shipping + insuranceCost;
    const profit = props.form.list_price - props.form.cost_basis - fees - totalShipping;
    const margin = (profit / props.form.list_price) * 100;

    return {
        profit: profit.toFixed(2),
        margin: margin.toFixed(1),
        fees: fees.toFixed(2),
        shipping: totalShipping.toFixed(2),
        insurance_cost: insuranceCost > 0 ? insuranceCost.toFixed(2) : null,
    };
});

const handlePublish = async () => {
    if (selectedPlatforms.value.length === 0) {
        alert('Please select at least one platform to publish to');
        return;
    }

    localPublishing.value = true;

    try {
        // Handle each selected platform
        for (const platform of selectedPlatforms.value) {
            if (platform === 'ebay') {
                // Call our eBay integration
                await handleEbayPublish();
            } else {
                // Handle other platforms (Amazon, etc.) - existing logic
                emit('publish', platform);
            }
        }

    } catch (error) {
        console.error('Publishing failed:', error);
        // Error will be displayed via flash/validation error components
    } finally {
        // Only reset local state if not controlled by parent
        if (!props.isPublishing) {
            localPublishing.value = false;
        }
    }
};

const handleEbayPublish = async () => {
    // Use the atomic publish-with-ebay endpoint that creates the Product
    // AND dispatches the eBay listing job in a single transaction
    emit('publish', 'ebay', {
        publish_to: 'ebay',
        ebay_auto_publish: true,
        ebay_options: {
            ...publishingOptions.value.ebay,
            policies: selectedEbayPolicies.value,
            shipping_rate: selectedShippingRate.value,
            additional_item_cost: selectedShippingRate.value?.additional_item_cost ?? null,
            insurance_enabled: selectedShippingRate.value?.insurance_enabled ?? false,
            confirmation_type: selectedShippingRate.value?.confirmation_type ?? 'none',
        },
    });
};

const togglePlatform = (platform) => {
    const index = selectedPlatforms.value.indexOf(platform);
    if (index > -1) {
        selectedPlatforms.value.splice(index, 1);
    } else {
        selectedPlatforms.value.push(platform);
    }
};
</script>

<template>
    <div class="p-6">
        <div class="mb-6">
            <h2 class="text-2xl font-bold text-gray-900 dark:text-gray-100">
                Step 4: Review & Publish
            </h2>
            <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                Final review before publishing your listing
            </p>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Left: Summary Cards -->
            <div class="space-y-4">
                <!-- Product Identity Card -->
                <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4 flex items-center">
                        <svg class="w-5 h-5 mr-2 text-indigo-600" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                        </svg>
                        Product Verified
                    </h3>
                    
                    <div class="space-y-3">
                        <div class="flex items-start gap-3">
                            <img
                                :src="form.selected_photos[0]"
                                class="w-20 h-20 object-cover rounded-lg border border-gray-300 dark:border-gray-600"
                            />
                            <div class="flex-1 min-w-0">
                                <div class="font-medium text-gray-900 dark:text-gray-100">
                                    {{ form.brand }} {{ form.model }}
                                </div>
                                <div class="text-sm text-gray-600 dark:text-gray-400 line-clamp-2">
                                    {{ form.name }}
                                </div>
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-2 gap-3 pt-3 border-t border-gray-200 dark:border-gray-700">
                            <div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">Quantity</div>
                                <div class="font-semibold text-gray-900 dark:text-gray-100">{{ form.quantity }}</div>
                            </div>
                            <div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">Location</div>
                                <div class="font-semibold text-gray-900 dark:text-gray-100">{{ resolvedLocationLabel || 'Not set' }}</div>
                            </div>
                            <div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">Condition</div>
                                <div class="font-semibold text-gray-900 dark:text-gray-100 capitalize">{{ form.condition.replace('_', ' ') }}</div>
                            </div>
                            <div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">UPC</div>
                                <div class="font-semibold text-gray-900 dark:text-gray-100 font-mono text-xs">{{ form.upc || 'N/A' }}</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Pricing Summary -->
                <div class="bg-gradient-to-br from-green-50 to-emerald-50 dark:from-gray-800 dark:to-gray-900 rounded-lg border-2 border-green-200 dark:border-green-800 p-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4 flex items-center">
                        <svg class="w-5 h-5 mr-2 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M8.433 7.418c.155-.103.346-.196.567-.267v1.698a2.305 2.305 0 01-.567-.267C8.07 8.34 8 8.114 8 8c0-.114.07-.34.433-.582zM11 12.849v-1.698c.22.071.412.164.567.267.364.243.433.468.433.582 0 .114-.07.34-.433.582a2.305 2.305 0 01-.567.267z"/>
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-13a1 1 0 10-2 0v.092a4.535 4.535 0 00-1.676.662C6.602 6.234 6 7.009 6 8c0 .99.602 1.765 1.324 2.246.48.32 1.054.545 1.676.662v1.941c-.391-.127-.68-.317-.843-.504a1 1 0 10-1.51 1.31c.562.649 1.413 1.076 2.353 1.253V15a1 1 0 102 0v-.092a4.535 4.535 0 001.676-.662C13.398 13.766 14 12.991 14 12c0-.99-.602-1.765-1.324-2.246A4.535 4.535 0 0011 9.092V7.151c.391.127.68.317.843.504a1 1 0 101.511-1.31c-.563-.649-1.413-1.076-2.354-1.253V5z" clip-rule="evenodd"/>
                        </svg>
                        Pricing Confirmed
                    </h3>
                    
                    <div class="space-y-3">
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-gray-600 dark:text-gray-400">List Price</span>
                            <span class="text-2xl font-bold text-gray-900 dark:text-gray-100">
                                ${{ form.list_price }}
                            </span>
                        </div>
                        
                        <div v-if="profitSummary" class="pt-3 border-t border-green-200 dark:border-green-700">
                            <div class="grid grid-cols-3 gap-3 text-center">
                                <div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400 mb-1">Cost</div>
                                    <div class="font-semibold text-gray-900 dark:text-gray-100">
                                        ${{ form.cost_basis }}
                                    </div>
                                </div>
                                <div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400 mb-1">Fees</div>
                                    <div class="font-semibold text-red-600 dark:text-red-400">
                                        -${{ profitSummary.fees }}
                                    </div>
                                </div>
                                <div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400 mb-1">Profit</div>
                                    <div class="font-bold text-green-600 dark:text-green-400">
                                        ${{ profitSummary.profit }}
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mt-3 text-center">
                                <div class="text-xs text-gray-500 dark:text-gray-400">Margin</div>
                                <div class="text-xl font-bold text-green-600 dark:text-green-400">
                                    {{ profitSummary.margin }}%
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Listing Content Summary -->
                <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4 flex items-center">
                        <svg class="w-5 h-5 mr-2 text-indigo-600" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z" clip-rule="evenodd"/>
                        </svg>
                        Listing Content
                    </h3>
                    
                    <div class="space-y-3">
                        <div>
                            <div class="text-xs text-gray-500 dark:text-gray-400 mb-1">Title ({{ form.listing_title.length }} chars)</div>
                            <div class="text-sm font-medium text-gray-900 dark:text-gray-100">
                                {{ form.listing_title }}
                            </div>
                        </div>
                        
                        <div>
                            <div class="text-xs text-gray-500 dark:text-gray-400 mb-1">Description</div>
                            <div class="text-sm text-gray-600 dark:text-gray-400 line-clamp-3">
                                {{ form.listing_description }}
                            </div>
                        </div>
                        
                        <div v-if="form.bullet_points.length > 0">
                            <div class="text-xs text-gray-500 dark:text-gray-400 mb-1">Key Features ({{ form.bullet_points.length }})</div>
                            <ul class="text-xs text-gray-600 dark:text-gray-400 list-disc list-inside space-y-1">
                                <li v-for="(point, index) in form.bullet_points.slice(0, 3)" :key="index" class="line-clamp-1">
                                    {{ point }}
                                </li>
                            </ul>
                        </div>
                        
                        <div v-if="form.keywords.length > 0" class="flex flex-wrap gap-1">
                            <span
                                v-for="(keyword, index) in form.keywords.slice(0, 5)"
                                :key="index"
                                class="px-2 py-1 bg-indigo-100 text-indigo-800 dark:bg-indigo-900 dark:text-indigo-200 rounded text-xs"
                            >
                                {{ keyword }}
                            </span>
                            <span v-if="form.keywords.length > 5" class="px-2 py-1 bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400 rounded text-xs">
                                +{{ form.keywords.length - 5 }} more
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right: Publishing Options -->
            <div class="space-y-4">
                <!-- Platform Selection -->
                <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">
                        Select Publishing Platforms
                    </h3>
                    
                    <div class="space-y-3">
                        <!-- eBay -->
                        <div
                            :class="{
                                'border-indigo-600 bg-indigo-50 dark:bg-indigo-900/20': selectedPlatforms.includes('ebay'),
                                'border-gray-200 dark:border-gray-700': !selectedPlatforms.includes('ebay'),
                            }"
                            class="border-2 rounded-lg p-4 hover:border-indigo-400 transition"
                        >
                            <div @click="togglePlatform('ebay')" class="flex items-center justify-between cursor-pointer">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 bg-blue-600 rounded flex items-center justify-center text-white font-bold">
                                        eB
                                    </div>
                                    <div>
                                        <div class="font-semibold text-gray-900 dark:text-gray-100">eBay</div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400">Auction & Fixed Price</div>
                                    </div>
                                </div>
                                <div
                                    :class="{
                                        'bg-indigo-600 border-indigo-600': selectedPlatforms.includes('ebay'),
                                        'bg-white dark:bg-gray-800 border-gray-300 dark:border-gray-600': !selectedPlatforms.includes('ebay'),
                                    }"
                                    class="w-5 h-5 rounded border-2 flex items-center justify-center"
                                >
                                    <svg v-if="selectedPlatforms.includes('ebay')" class="w-3 h-3 text-white" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                    </svg>
                                </div>
                            </div>
                            
                            <!-- eBay Options -->
                            <div v-if="selectedPlatforms.includes('ebay')" class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700 space-y-3">
                                <div>
                                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">
                                        Listing Format
                                    </label>
                                    <select
                                        v-model="publishingOptions.ebay.format"
                                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm dark:bg-gray-900 dark:text-gray-100"
                                    >
                                        <option value="fixed_price">Fixed Price</option>
                                        <option value="auction">Auction</option>
                                    </select>
                                </div>
                                
                                <div>
                                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">
                                        Duration
                                    </label>
                                    <select
                                        v-model="publishingOptions.ebay.duration"
                                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm dark:bg-gray-900 dark:text-gray-100"
                                    >
                                        <option value="GTC">Good 'Til Cancelled</option>
                                        <option value="7">7 Days</option>
                                        <option value="30">30 Days</option>
                                    </select>
                                </div>
                                
                                <div class="flex items-center gap-2">
                                    <input
                                        v-model="publishingOptions.ebay.promoted"
                                        type="checkbox"
                                        id="ebay-promoted"
                                        class="rounded border-gray-300 dark:border-gray-600 text-indigo-600 focus:ring-indigo-500"
                                    />
                                    <label for="ebay-promoted" class="text-xs text-gray-700 dark:text-gray-300">
                                        Use Promoted Listings (2% fee)
                                    </label>
                                </div>

                                <!-- Package Dimensions (editable) -->
                                <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                                    <div class="flex items-center justify-between mb-2">
                                        <h4 class="text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wide">
                                            Package Dimensions
                                        </h4>
                                        <button
                                            type="button"
                                            :disabled="savingDims"
                                            @click="saveDimensions"
                                            class="text-xs font-medium text-indigo-600 hover:text-indigo-500 disabled:opacity-50"
                                        >
                                            {{ savingDims ? 'Saving...' : 'Save dimensions' }}
                                        </button>
                                    </div>
                                    <div class="grid grid-cols-4 gap-2">
                                        <div>
                                            <InputLabel for="dim_weight" value="Weight (lbs)" class="text-xs" />
                                            <TextInput id="dim_weight" v-model.number="editableDims.weight_lbs" type="number" step="0.1" min="0" class="mt-1 block w-full text-sm" placeholder="0.0" />
                                        </div>
                                        <div>
                                            <InputLabel for="dim_length" value="Length (in)" class="text-xs" />
                                            <TextInput id="dim_length" v-model.number="editableDims.length_inches" type="number" step="0.1" min="0" class="mt-1 block w-full text-sm" placeholder="0.0" />
                                        </div>
                                        <div>
                                            <InputLabel for="dim_width" value="Width (in)" class="text-xs" />
                                            <TextInput id="dim_width" v-model.number="editableDims.width_inches" type="number" step="0.1" min="0" class="mt-1 block w-full text-sm" placeholder="0.0" />
                                        </div>
                                        <div>
                                            <InputLabel for="dim_height" value="Height (in)" class="text-xs" />
                                            <TextInput id="dim_height" v-model.number="editableDims.height_inches" type="number" step="0.1" min="0" class="mt-1 block w-full text-sm" placeholder="0.0" />
                                        </div>
                                    </div>
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                        Manually entered at intake (not estimated from the photo). Used for shipping rate calculation.
                                    </p>
                                </div>

                                <!-- Shipping Rate Selector -->
                                <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                                    <ShippingRateSelector
                                        :queue-item-id="queueItem.id"
                                        :dimensions="packageDimensions"
                                        :estimated-cost="form.shipping_cost || 0"
                                        :item-value="Number(form.list_price || 0)"
                                        v-model="selectedShippingRate"
                                        @rate-selected="handleShippingRateSelected"
                                    />
                                </div>

                                <!-- eBay Policy Selector -->
                                <div class="mt-4">
                                    <EbayPolicySelector
                                        :product-data="productDataForPolicies"
                                        v-model="selectedEbayPolicies"
                                    />
                                </div>
                            </div>
                        </div>

                        <!-- Amazon -->
                        <div
                            :class="{
                                'border-indigo-600 bg-indigo-50 dark:bg-indigo-900/20': selectedPlatforms.includes('amazon'),
                                'border-gray-200 dark:border-gray-700': !selectedPlatforms.includes('amazon'),
                            }"
                            class="border-2 rounded-lg p-4 hover:border-indigo-400 transition"
                        >
                            <div @click="togglePlatform('amazon')" class="flex items-center justify-between cursor-pointer">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 bg-orange-500 rounded flex items-center justify-center text-white font-bold text-xs">
                                        AMZ
                                    </div>
                                    <div>
                                        <div class="font-semibold text-gray-900 dark:text-gray-100">Amazon</div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400">Marketplace</div>
                                    </div>
                                </div>
                                <div
                                    :class="{
                                        'bg-indigo-600 border-indigo-600': selectedPlatforms.includes('amazon'),
                                        'bg-white dark:bg-gray-800 border-gray-300 dark:border-gray-600': !selectedPlatforms.includes('amazon'),
                                    }"
                                    class="w-5 h-5 rounded border-2 flex items-center justify-center"
                                >
                                    <svg v-if="selectedPlatforms.includes('amazon')" class="w-3 h-3 text-white" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                    </svg>
                                </div>
                            </div>
                            
                            <!-- Amazon Options -->
                            <div v-if="selectedPlatforms.includes('amazon')" class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                                <div>
                                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">
                                        Fulfillment Method
                                    </label>
                                    <select
                                        v-model="publishingOptions.amazon.fulfillment"
                                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm dark:bg-gray-900 dark:text-gray-100"
                                    >
                                        <option value="FBM">Fulfilled by Merchant (FBM)</option>
                                        <option value="FBA">Fulfilled by Amazon (FBA)</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Newegg -->
                        <div
                            :class="{
                                'border-indigo-600 bg-indigo-50 dark:bg-indigo-900/20': selectedPlatforms.includes('newegg'),
                                'border-gray-200 dark:border-gray-700': !selectedPlatforms.includes('newegg'),
                            }"
                            class="border-2 rounded-lg p-4 hover:border-indigo-400 transition opacity-50"
                        >
                            <div @click="togglePlatform('newegg')" class="flex items-center justify-between cursor-pointer">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 bg-orange-600 rounded flex items-center justify-center text-white font-bold text-xs">
                                        NE
                                    </div>
                                    <div>
                                        <div class="font-semibold text-gray-900 dark:text-gray-100">Newegg</div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400">Coming Soon</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Final Checklist -->
                <div class="bg-gradient-to-br from-blue-50 to-indigo-50 dark:from-gray-800 dark:to-gray-900 rounded-lg border-2 border-blue-200 dark:border-blue-800 p-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">
                        Pre-Publish Checklist
                    </h3>
                    
                    <div class="space-y-2">
                        <div class="flex items-start gap-2 text-sm">
                            <svg class="w-5 h-5 text-green-600 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                            </svg>
                            <span class="text-gray-700 dark:text-gray-300">Product identity confirmed</span>
                        </div>
                        <div class="flex items-start gap-2 text-sm">
                            <svg class="w-5 h-5 text-green-600 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                            </svg>
                            <span class="text-gray-700 dark:text-gray-300">Competitive pricing set</span>
                        </div>
                        <div class="flex items-start gap-2 text-sm">
                            <svg class="w-5 h-5 text-green-600 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                            </svg>
                            <span class="text-gray-700 dark:text-gray-300">Listing content optimized</span>
                        </div>
                        <div class="flex items-start gap-2 text-sm">
                            <svg class="w-5 h-5 text-green-600 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                            </svg>
                            <span class="text-gray-700 dark:text-gray-300">{{ form.selected_photos.length }} photos selected</span>
                        </div>
                    </div>
                </div>

                <!-- Publish Button -->
                <button
                    @click="handlePublish"
                    :disabled="selectedPlatforms.length === 0 || isCurrentlyPublishing"
                    :class="{
                        'opacity-50 cursor-not-allowed': selectedPlatforms.length === 0 || isCurrentlyPublishing,
                    }"
                    class="w-full bg-gradient-to-r from-green-600 to-emerald-600 hover:from-green-700 hover:to-emerald-700 text-white px-6 py-4 rounded-lg font-bold text-lg shadow-lg transition flex items-center justify-center"
                >
                    <svg v-if="!isCurrentlyPublishing" class="w-6 h-6 mr-2" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M10 12a2 2 0 100-4 2 2 0 000 4z"/>
                        <path fill-rule="evenodd" d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd"/>
                    </svg>
                    <svg v-else class="animate-spin w-6 h-6 mr-2" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    {{ isCurrentlyPublishing ? 'Publishing...' : `Publish to ${selectedPlatforms.length} Platform${selectedPlatforms.length !== 1 ? 's' : ''}` }}
                </button>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="mt-8 flex justify-between items-center pt-6 border-t border-gray-200 dark:border-gray-700">
            <button
                @click="$emit('prev')"
                :disabled="isCurrentlyPublishing"
                class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition flex items-center disabled:opacity-50"
            >
                <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M9.707 16.707a1 1 0 01-1.414 0l-6-6a1 1 0 010-1.414l6-6a1 1 0 011.414 1.414L5.414 9H17a1 1 0 110 2H5.414l4.293 4.293a1 1 0 010 1.414z" clip-rule="evenodd"/>
                </svg>
                Back to Listing
            </button>

            <button
                @click="$emit('save-draft')"
                :disabled="isCurrentlyPublishing"
                class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition disabled:opacity-50"
            >
                Save Draft
            </button>
        </div>
    </div>
</template>
