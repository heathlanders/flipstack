<script setup>
import { ref, computed, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    sourceData: {
        type: Object,
        default: () => ({})
    },
    targetData: {
        type: Object,
        default: () => ({})
    },
    product: {
        type: Object,
        default: () => ({})
    },
    sourceMarketplace: {
        type: String,
        default: ''
    },
    targetMarketplace: {
        type: String,
        default: ''
    },
    credentials: {
        type: Array,
        default: () => []
    }
});

// Editable target fields
const editableTitle = ref(props.targetData.title || props.targetData.name || '');
const editablePrice = ref(parseFloat(props.targetData.price || props.targetData.listed_price || props.product.selling_price || 0));
const editableDescription = ref(props.targetData.body_html || props.targetData.description || '');
const selectedCredentialId = ref(props.credentials.length > 0 ? props.credentials[0].id : null);

// Price adjustment
const priceAdjustmentType = ref('none');
const priceAdjustmentValue = ref(0);
const sourcePrice = computed(() => parseFloat(props.sourceData.listed_price || props.sourceData.price || props.sourceData.selling_price || 0));

const processing = ref(false);

watch([priceAdjustmentType, priceAdjustmentValue], () => {
    const base = sourcePrice.value;
    const adj = parseFloat(priceAdjustmentValue.value) || 0;

    switch (priceAdjustmentType.value) {
        case 'percent_increase':
            editablePrice.value = parseFloat((base * (1 + adj / 100)).toFixed(2));
            break;
        case 'percent_decrease':
            editablePrice.value = parseFloat((base * (1 - adj / 100)).toFixed(2));
            break;
        case 'fixed_increase':
            editablePrice.value = parseFloat((base + adj).toFixed(2));
            break;
        case 'fixed_decrease':
            editablePrice.value = parseFloat(Math.max(0.01, base - adj).toFixed(2));
            break;
        case 'none':
        default:
            break;
    }
});

const sourceImages = computed(() => {
    const imgs = props.sourceData.images || props.sourceData.gallery_images || [];
    if (Array.isArray(imgs)) {
        return imgs.map(img => typeof img === 'string' ? img : (img.src || img.url || img.image_url || ''));
    }
    return [];
});

const targetImages = computed(() => {
    const imgs = props.targetData.images || [];
    if (Array.isArray(imgs)) {
        return imgs.map(img => typeof img === 'string' ? img : (img.src || img.url || img.image_url || ''));
    }
    return [];
});

const sourceLabel = computed(() => {
    return props.sourceMarketplace ? props.sourceMarketplace.charAt(0).toUpperCase() + props.sourceMarketplace.slice(1) : 'Source';
});

const targetLabel = computed(() => {
    return props.targetMarketplace ? props.targetMarketplace.charAt(0).toUpperCase() + props.targetMarketplace.slice(1) : 'Target';
});

const selectedCredential = computed(() => {
    return props.credentials.find(c => c.id === selectedCredentialId.value);
});

const profitEstimate = computed(() => {
    const cost = parseFloat(props.product.cost_price || 0);
    if (cost <= 0) return null;
    const profit = editablePrice.value - cost;
    const margin = ((profit / editablePrice.value) * 100).toFixed(1);
    return { profit: profit.toFixed(2), margin };
});

function executeCrossListing() {
    if (!selectedCredentialId.value) return;
    processing.value = true;

    const listingData = {
        product_id: props.product.id,
        title: editableTitle.value,
        price: editablePrice.value,
        description: editableDescription.value,
        sku: props.sourceData.sku || props.product.sku || '',
        condition: props.targetData.condition || props.sourceData.condition || 'NEW',
        condition_description: props.targetData.condition_description || props.sourceData.condition_description || '',
        images: targetImages.value,
        available_quantity: props.sourceData.available_quantity || props.sourceData.stock_quantity || 1,
        listed_quantity: props.sourceData.available_quantity || props.sourceData.stock_quantity || 1,
        inventory_quantity: props.sourceData.available_quantity || props.sourceData.stock_quantity || 0,
        currency: props.sourceData.currency || 'USD',
        weight: props.sourceData.weight || null,
    };

    // Add marketplace-specific fields
    if (props.targetMarketplace === 'shopify') {
        listingData.body_html = editableDescription.value;
        listingData.compare_at_price = props.targetData.compare_at_price || null;
    } else if (props.targetMarketplace === 'ebay') {
        listingData.listed_price = editablePrice.value;
        listingData.listing_format = props.targetData.listing_format || 'FIXED_PRICE';
    }

    router.post(route('marketplaces.cross-list.execute'), {
        listing_data: listingData,
        target: props.targetMarketplace,
        source: props.sourceMarketplace,
        credential_id: selectedCredentialId.value,
    }, {
        onFinish: () => {
            processing.value = false;
        },
    });
}
</script>

<template>
    <AppLayout title="Cross-List Product">
        <Head title="Cross-List Product" />

        <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
            <!-- Header -->
            <div class="mb-6">
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
                            Cross-List Product
                        </h1>
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                            Clone listing from {{ sourceLabel }} to {{ targetLabel }}
                        </p>
                    </div>
                    <Link
                        :href="route('marketplaces.hub.index')"
                        class="inline-flex items-center px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md shadow-sm hover:bg-gray-50 dark:bg-gray-700 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-600"
                    >
                        Cancel
                    </Link>
                </div>
            </div>

            <!-- Product Summary -->
            <div class="mb-6 bg-white dark:bg-gray-800 rounded-lg shadow p-4">
                <div class="flex items-center space-x-4">
                    <div v-if="product.featured_image" class="flex-shrink-0 w-16 h-16">
                        <img
                            :src="product.featured_image"
                            :alt="product.name"
                            class="w-16 h-16 object-cover rounded-lg"
                        />
                    </div>
                    <div v-else class="flex-shrink-0 w-16 h-16 bg-gray-200 dark:bg-gray-700 rounded-lg flex items-center justify-center">
                        <span class="text-gray-400 dark:text-gray-500 text-xs">No image</span>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-gray-900 dark:text-white truncate">
                            {{ product.name }}
                        </p>
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            SKU: {{ product.sku || 'N/A' }}
                        </p>
                    </div>
                    <div class="text-right">
                        <p class="text-sm font-medium text-gray-900 dark:text-white">
                            ${{ parseFloat(product.selling_price || 0).toFixed(2) }}
                        </p>
                        <p v-if="product.cost_price" class="text-xs text-gray-500 dark:text-gray-400">
                            Cost: ${{ parseFloat(product.cost_price).toFixed(2) }}
                        </p>
                    </div>
                </div>
            </div>

            <!-- Flash success message -->
            <div v-if="$page.props.flash?.success" class="mb-6 rounded-md bg-green-50 dark:bg-green-900/30 p-4">
                <p class="text-sm font-medium text-green-800 dark:text-green-300">
                    {{ $page.props.flash.success }}
                </p>
            </div>

            <!-- Two Column Layout -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

                <!-- Source Listing (Left) -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow">
                    <div class="px-4 py-5 sm:px-6 border-b border-gray-200 dark:border-gray-700">
                        <h2 class="text-lg font-medium text-gray-900 dark:text-white">
                            Source Listing ({{ sourceLabel }})
                        </h2>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            Current listing data on {{ sourceLabel }}
                        </p>
                    </div>
                    <div class="px-4 py-5 sm:px-6 space-y-4">
                        <!-- Title -->
                        <div>
                            <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Title</label>
                            <p class="mt-1 text-sm text-gray-900 dark:text-white">
                                {{ sourceData.title || sourceData.name || 'N/A' }}
                            </p>
                        </div>

                        <!-- Price -->
                        <div>
                            <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Price</label>
                            <p class="mt-1 text-sm font-semibold text-gray-900 dark:text-white">
                                ${{ parseFloat(sourceData.listed_price || sourceData.price || sourceData.selling_price || 0).toFixed(2) }}
                                <span class="text-xs font-normal text-gray-500 dark:text-gray-400">{{ sourceData.currency || 'USD' }}</span>
                            </p>
                        </div>

                        <!-- Condition -->
                        <div v-if="sourceData.condition">
                            <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Condition</label>
                            <p class="mt-1 text-sm text-gray-900 dark:text-white">{{ sourceData.condition }}</p>
                            <p v-if="sourceData.condition_description" class="text-xs text-gray-500 dark:text-gray-400">
                                {{ sourceData.condition_description }}
                            </p>
                        </div>

                        <!-- Category -->
                        <div v-if="sourceData.ebay_category_name || sourceData.category">
                            <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Category</label>
                            <p class="mt-1 text-sm text-gray-900 dark:text-white">
                                {{ sourceData.ebay_category_name || sourceData.category }}
                            </p>
                        </div>

                        <!-- Status -->
                        <div v-if="sourceData.status">
                            <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Status</label>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900/50 dark:text-blue-300">
                                {{ sourceData.status }}
                            </span>
                        </div>

                        <!-- Description Preview -->
                        <div>
                            <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Description</label>
                            <div class="mt-1 text-sm text-gray-700 dark:text-gray-300 max-h-40 overflow-y-auto prose prose-sm dark:prose-invert" v-html="sourceData.description || sourceData.body_html || 'No description'">
                            </div>
                        </div>

                        <!-- Images -->
                        <div v-if="sourceImages.length > 0">
                            <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Images ({{ sourceImages.length }})</label>
                            <div class="mt-2 grid grid-cols-4 gap-2">
                                <div v-for="(img, idx) in sourceImages.slice(0, 8)" :key="idx" class="aspect-square">
                                    <img :src="img" :alt="'Image ' + (idx + 1)" class="w-full h-full object-cover rounded-md" />
                                </div>
                            </div>
                        </div>

                        <!-- eBay-specific link -->
                        <div v-if="sourceData.ebay_listing_url">
                            <a :href="sourceData.ebay_listing_url" target="_blank" rel="noopener noreferrer"
                               class="text-sm text-indigo-600 hover:text-indigo-500 dark:text-indigo-400 dark:hover:text-indigo-300">
                                View on eBay
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Target Listing (Right) - Editable -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow">
                    <div class="px-4 py-5 sm:px-6 border-b border-gray-200 dark:border-gray-700">
                        <h2 class="text-lg font-medium text-gray-900 dark:text-white">
                            Target Listing ({{ targetLabel }})
                        </h2>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            Edit fields before cross-listing to {{ targetLabel }}
                        </p>
                    </div>
                    <div class="px-4 py-5 sm:px-6 space-y-4">
                        <!-- Credential Selector -->
                        <div>
                            <label for="credential" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                {{ targetLabel }} Account
                            </label>
                            <select
                                id="credential"
                                v-model="selectedCredentialId"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white sm:text-sm"
                            >
                                <option v-if="credentials.length === 0" :value="null" disabled>No {{ targetLabel }} accounts connected</option>
                                <option
                                    v-for="cred in credentials"
                                    :key="cred.id"
                                    :value="cred.id"
                                >
                                    {{ cred.name || cred.seller_username || ('Account #' + cred.id) }}
                                    <template v-if="cred.environment === 'sandbox'"> (Sandbox)</template>
                                </option>
                            </select>
                        </div>

                        <!-- Title -->
                        <div>
                            <label for="title" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Title</label>
                            <input
                                id="title"
                                v-model="editableTitle"
                                type="text"
                                maxlength="255"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white sm:text-sm"
                            />
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ editableTitle.length }} / 255 characters</p>
                        </div>

                        <!-- Price -->
                        <div>
                            <label for="price" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Price</label>
                            <div class="mt-1 relative rounded-md shadow-sm">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <span class="text-gray-500 dark:text-gray-400 sm:text-sm">$</span>
                                </div>
                                <input
                                    id="price"
                                    v-model.number="editablePrice"
                                    type="number"
                                    step="0.01"
                                    min="0.01"
                                    class="block w-full pl-7 pr-12 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white sm:text-sm"
                                />
                                <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                                    <span class="text-gray-500 dark:text-gray-400 sm:text-sm">{{ sourceData.currency || 'USD' }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Profit Estimate -->
                        <div v-if="profitEstimate" class="rounded-md bg-gray-50 dark:bg-gray-700/50 p-3">
                            <p class="text-sm text-gray-700 dark:text-gray-300">
                                Estimated profit:
                                <span :class="parseFloat(profitEstimate.profit) >= 0 ? 'text-green-600 dark:text-green-400 font-semibold' : 'text-red-600 dark:text-red-400 font-semibold'">
                                    ${{ profitEstimate.profit }}
                                </span>
                                <span class="text-gray-500 dark:text-gray-400 ml-1">({{ profitEstimate.margin }}% margin)</span>
                            </p>
                        </div>

                        <!-- Price Adjustment -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Price Adjustment</label>
                            <div class="mt-1 grid grid-cols-2 gap-2">
                                <select
                                    v-model="priceAdjustmentType"
                                    class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white sm:text-sm"
                                >
                                    <option value="none">No adjustment</option>
                                    <option value="percent_increase">Increase by %</option>
                                    <option value="percent_decrease">Decrease by %</option>
                                    <option value="fixed_increase">Increase by $</option>
                                    <option value="fixed_decrease">Decrease by $</option>
                                </select>
                                <input
                                    v-if="priceAdjustmentType !== 'none'"
                                    v-model.number="priceAdjustmentValue"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    placeholder="Amount"
                                    class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white sm:text-sm"
                                />
                            </div>
                        </div>

                        <!-- Description -->
                        <div>
                            <label for="description" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Description</label>
                            <textarea
                                id="description"
                                v-model="editableDescription"
                                rows="6"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white sm:text-sm"
                            ></textarea>
                        </div>

                        <!-- Target Images Preview -->
                        <div v-if="targetImages.length > 0">
                            <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Images ({{ targetImages.length }})</label>
                            <div class="mt-2 grid grid-cols-4 gap-2">
                                <div v-for="(img, idx) in targetImages.slice(0, 8)" :key="idx" class="aspect-square">
                                    <img :src="img" :alt="'Image ' + (idx + 1)" class="w-full h-full object-cover rounded-md" />
                                </div>
                            </div>
                        </div>

                        <!-- Condition Info -->
                        <div v-if="targetData.condition || targetData._source_condition">
                            <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Condition</label>
                            <p class="mt-1 text-sm text-gray-900 dark:text-white">
                                {{ targetData.condition || targetData._source_condition || 'N/A' }}
                            </p>
                        </div>

                        <!-- Shopify-specific: Product Type -->
                        <div v-if="targetMarketplace === 'shopify' && targetData.product_type">
                            <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Product Type</label>
                            <p class="mt-1 text-sm text-gray-900 dark:text-white">{{ targetData.product_type }}</p>
                        </div>

                        <!-- Shopify-specific: Tags -->
                        <div v-if="targetMarketplace === 'shopify' && targetData.tags">
                            <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Tags</label>
                            <p class="mt-1 text-sm text-gray-900 dark:text-white">{{ targetData.tags }}</p>
                        </div>

                        <!-- eBay-specific: Listing Format -->
                        <div v-if="targetMarketplace === 'ebay'">
                            <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Listing Format</label>
                            <p class="mt-1 text-sm text-gray-900 dark:text-white">{{ targetData.listing_format || 'Fixed Price' }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Action Bar -->
            <div class="mt-6 flex items-center justify-between bg-white dark:bg-gray-800 rounded-lg shadow px-6 py-4">
                <div class="text-sm text-gray-500 dark:text-gray-400">
                    <span v-if="selectedCredential">
                        Listing to: <span class="font-medium text-gray-900 dark:text-white">{{ selectedCredential.name || selectedCredential.seller_username }}</span>
                        on {{ targetLabel }}
                    </span>
                    <span v-else class="text-amber-600 dark:text-amber-400">
                        Select a {{ targetLabel }} account above to continue
                    </span>
                </div>
                <div class="flex items-center space-x-3">
                    <Link
                        :href="route('marketplaces.hub.index')"
                        class="inline-flex items-center px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md shadow-sm hover:bg-gray-50 dark:bg-gray-700 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-600"
                    >
                        Cancel
                    </Link>
                    <button
                        @click="executeCrossListing"
                        :disabled="processing || !selectedCredentialId || !editableTitle"
                        class="inline-flex items-center px-6 py-2 text-sm font-medium text-white bg-indigo-600 border border-transparent rounded-md shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed dark:focus:ring-offset-gray-800"
                    >
                        <svg v-if="processing" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        {{ processing ? 'Processing...' : 'Cross-List Now' }}
                    </button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
