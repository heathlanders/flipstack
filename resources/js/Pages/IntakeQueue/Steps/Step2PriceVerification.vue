<script setup>
import { ref, reactive, computed, onMounted, onUnmounted } from 'vue';
import axios from 'axios';

const props = defineProps({
    queueItem: Object,
    form: Object,
});

const emit = defineEmits(['next', 'prev', 'save-draft', 'refresh-data']);

const soldSort = reactive({ column: 'sold_date', direction: 'desc' });
const competitorSort = reactive({ column: 'price', direction: 'asc' });

function toggleSort(sortState, column) {
    if (sortState.column === column) {
        sortState.direction = sortState.direction === 'asc' ? 'desc' : 'asc';
    } else {
        sortState.column = column;
        sortState.direction = 'asc';
    }
}

function sortIndicator(sortState, column) {
    if (sortState.column !== column) return '';
    return sortState.direction === 'asc' ? ' \u2191' : ' \u2193';
}

const researchData = computed(() =>
    props.queueItem.enriched_data?.research_data ||
    props.queueItem.enriched_data?.product_data?.gemini_research ||
    null
);

const marketPricing = computed(() =>
    researchData.value?.market_pricing || null
);

function sortValue(item, column) {
    const val = item[column];
    if (column === 'price' || column === 'shipping_cost') {
        return parseFloat(val) || 0;
    }
    if (column === 'sold_date') {
        return val ? new Date(val).getTime() : 0;
    }
    if (column === 'seller_feedback') {
        return val || '';
    }
    return (val || '').toString().toLowerCase();
}

const sortedSoldListings = computed(() => {
    const listings = marketPricing.value?.ebay_sold_listings || [];
    const sorted = [...listings];
    const { column, direction } = soldSort;
    sorted.sort((a, b) => {
        const aVal = sortValue(a, column);
        const bVal = sortValue(b, column);
        if (aVal < bVal) return direction === 'asc' ? -1 : 1;
        if (aVal > bVal) return direction === 'asc' ? 1 : -1;
        return 0;
    });
    return sorted;
});

const sortedActiveListings = computed(() => {
    const listings = marketPricing.value?.ebay_active_listings || [];
    const sorted = [...listings];
    const { column, direction } = competitorSort;
    sorted.sort((a, b) => {
        const aVal = sortValue(a, column);
        const bVal = sortValue(b, column);
        if (aVal < bVal) return direction === 'asc' ? -1 : 1;
        if (aVal > bVal) return direction === 'asc' ? 1 : -1;
        return 0;
    });
    return sorted;
});

const soldSummary = computed(() =>
    marketPricing.value?.ebay_sold_summary || {}
);

const profitMargin = computed(() => {
    if (!props.form.list_price || !props.form.cost_basis) return null;

    const fees = (props.form.list_price * props.form.platform_fees_percent) / 100;
    const shipping = props.form.shipping_cost || 0;
    const profit = props.form.list_price - props.form.cost_basis - fees - shipping;
    const margin = (profit / props.form.list_price) * 100;

    return {
        profit: profit.toFixed(2),
        margin: margin.toFixed(1),
        fees: fees.toFixed(2),
    };
});

const profitClass = computed(() => {
    if (!profitMargin.value) return '';
    const margin = parseFloat(profitMargin.value.margin);

    if (margin >= 30) return 'text-green-600 dark:text-green-400';
    if (margin >= 15) return 'text-yellow-600 dark:text-yellow-400';
    return 'text-red-600 dark:text-red-400';
});

const adjustPrice = (amount) => {
    const current = props.form.list_price || 0;
    props.form.list_price = Math.max(0, current + amount);
};

const useRecommendedPrice = () => {
    if (marketPricing.value?.recommended_list_price) {
        props.form.list_price = parseFloat(marketPricing.value.recommended_list_price);
    }
};

const formatCurrency = (value) => {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD',
    }).format(value);
};

const formatDate = (dateString) => {
    if (!dateString) return 'N/A';
    return new Date(dateString).toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    });
};

const formatShipping = (value) => {
    if (value === null || value === undefined || value === '') return 'N/A';
    const num = parseFloat(value);
    if (num === 0) return 'Free';
    return formatCurrency(num);
};

// eBay Research Extension Integration
const isWaitingForExtension = ref(false);
const extensionPollingTimer = ref(null);
const extensionTimeoutTimer = ref(null);
const extensionStatusMessage = ref('');

const productKeywords = computed(() => {
    const rd = props.queueItem.enriched_data?.research_data || {};
    const parts = [
        rd.product_identity?.brand || props.form.brand || '',
        rd.product_identity?.model || props.form.model || '',
        rd.product_identity?.title || props.form.name || '',
    ].filter(Boolean);

    const words = parts.join(' ').split(/\s+/);
    const seen = new Set();
    const unique = [];
    for (const w of words) {
        const lower = w.toLowerCase();
        if (!seen.has(lower)) {
            seen.add(lower);
            unique.push(w);
        }
    }
    return unique.join(' ').substring(0, 200);
});

const hasExtensionData = computed(() => {
    const mp = marketPricing.value;
    return mp?.source === 'chrome_extension';
});

const launchEbayResearch = async () => {
    try {
        const response = await axios.post(route('organization.extension-token.generate'));
        const token = response.data.token;
        const keywords = encodeURIComponent(productKeywords.value);
        const url = `https://www.ebay.com/sh/research?keywords=${keywords}&dayRange=365&tabName=SOLD#flipstack-queueitem=${props.queueItem.id}&token=${encodeURIComponent(token)}`;

        window.dispatchEvent(new CustomEvent('flipstack-extension-context', {
            detail: {
                token,
                queueItemId: props.queueItem.id,
                flipstackUrl: window.location.origin,
            },
        }));

        window.open(url, '_blank');
        isWaitingForExtension.value = true;
        extensionStatusMessage.value = 'Waiting for extension data...';
        startPolling();
    } catch (error) {
        console.error('Failed to generate extension token:', error);
    }
};

const startPolling = () => {
    extensionPollingTimer.value = setInterval(() => {
        checkForExtensionData();
    }, 3000);

    extensionTimeoutTimer.value = setTimeout(() => {
        stopPolling();
        extensionStatusMessage.value = 'Timed out waiting for extension data. You can try again.';
    }, 5 * 60 * 1000);
};

const stopPolling = () => {
    if (extensionPollingTimer.value) {
        clearInterval(extensionPollingTimer.value);
        extensionPollingTimer.value = null;
    }
    if (extensionTimeoutTimer.value) {
        clearTimeout(extensionTimeoutTimer.value);
        extensionTimeoutTimer.value = null;
    }
    isWaitingForExtension.value = false;
};

const checkForExtensionData = async () => {
    try {
        const response = await axios.get(route('intake-queue.extension-status', props.queueItem.id));
        if (response.data.has_extension_data) {
            stopPolling();
            extensionStatusMessage.value = 'Extension data received! Refreshing...';
            emit('refresh-data');
        }
    } catch (error) {
        console.error('Extension poll failed:', error);
    }
};

onMounted(() => {
    if (typeof window.Echo !== 'undefined') {
        window.Echo.private(`queue-item.${props.queueItem.id}`)
            .listen('.extension-research.received', () => {
                stopPolling();
                extensionStatusMessage.value = 'Extension data received! Refreshing...';
                emit('refresh-data');
            });
    }
});

onUnmounted(() => {
    stopPolling();
    if (typeof window.Echo !== 'undefined') {
        window.Echo.leave(`queue-item.${props.queueItem.id}`);
    }
});
</script>

<template>
    <div class="p-6">
        <div class="mb-6">
            <h2 class="text-2xl font-bold text-gray-900 dark:text-gray-100">
                Step 2: Price Verification
            </h2>
            <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                Set competitive, profitable pricing using market intelligence
            </p>
        </div>

        <!-- eBay Research Extension Banner -->
        <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4 mb-6">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <svg class="w-6 h-6 text-blue-600 dark:text-blue-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                    <div>
                        <div class="text-sm font-semibold text-blue-900 dark:text-blue-200">
                            {{ hasExtensionData ? 'Extension data loaded' : 'Research sold prices on eBay' }}
                        </div>
                        <div v-if="isWaitingForExtension" class="text-xs text-blue-700 dark:text-blue-300 mt-1 flex items-center gap-2">
                            <svg class="animate-spin w-3 h-3" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                            </svg>
                            {{ extensionStatusMessage }}
                        </div>
                        <div v-else-if="hasExtensionData" class="text-xs text-green-700 dark:text-green-400 mt-1">
                            Real sold listing data from eBay Seller Hub Research
                        </div>
                        <div v-else class="text-xs text-blue-700 dark:text-blue-300 mt-1">
                            Opens eBay Seller Hub Research. Refine your search, then click the Extract button to send data back.
                        </div>
                    </div>
                </div>
                <button
                    v-if="!isWaitingForExtension"
                    @click="launchEbayResearch"
                    class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-md transition flex-shrink-0"
                >
                    {{ hasExtensionData ? 'Re-research on eBay' : 'Research on eBay' }}
                </button>
                <button
                    v-else
                    @click="stopPolling"
                    class="px-4 py-2 bg-gray-400 hover:bg-gray-500 text-white text-sm font-medium rounded-md transition flex-shrink-0"
                >
                    Cancel
                </button>
            </div>
        </div>

        <!-- Pricing Dashboard -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
            <!-- Recommended Price Card -->
            <div class="lg:col-span-1 bg-gradient-to-br from-indigo-500 to-purple-600 rounded-lg p-6 text-white">
                <div class="text-sm opacity-90 mb-2">AI Recommended Price</div>
                <div class="text-4xl font-bold mb-4">
                    {{ formatCurrency(marketPricing?.recommended_list_price || 0) }}
                </div>
                <button
                    @click="useRecommendedPrice"
                    class="w-full bg-white text-indigo-600 px-4 py-2 rounded-md font-medium hover:bg-gray-100 transition"
                >
                    Use This Price
                </button>
            </div>

            <!-- Profit Calculator -->
            <div class="lg:col-span-2 bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">
                    Profit Calculator
                </h3>

                <div class="grid grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Cost Basis *
                        </label>
                        <div class="relative">
                            <span class="absolute left-3 top-2 text-gray-500">$</span>
                            <input
                                v-model.number="form.cost_basis"
                                type="number"
                                step="0.01"
                                min="0"
                                class="w-full pl-7 pr-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md dark:bg-gray-900 dark:text-gray-100"
                                placeholder="0.00"
                            />
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">
                            List Price *
                        </label>
                        <div class="relative">
                            <span class="absolute left-3 top-2 text-gray-500">$</span>
                            <input
                                v-model.number="form.list_price"
                                type="number"
                                step="0.01"
                                min="0"
                                class="w-full pl-7 pr-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md dark:bg-gray-900 dark:text-gray-100"
                                placeholder="0.00"
                            />
                        </div>
                    </div>
                </div>

                <!-- Quick Adjustment Buttons -->
                <div class="flex gap-2 mb-4">
                    <button
                        @click="adjustPrice(5)"
                        class="flex-1 px-3 py-1 bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300 rounded text-sm font-medium hover:bg-green-200 dark:hover:bg-green-800 transition"
                    >
                        +$5
                    </button>
                    <button
                        @click="adjustPrice(10)"
                        class="flex-1 px-3 py-1 bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300 rounded text-sm font-medium hover:bg-green-200 dark:hover:bg-green-800 transition"
                    >
                        +$10
                    </button>
                    <button
                        @click="adjustPrice(-5)"
                        class="flex-1 px-3 py-1 bg-red-100 text-red-700 dark:bg-red-900 dark:text-red-300 rounded text-sm font-medium hover:bg-red-200 dark:hover:bg-red-800 transition"
                    >
                        -$5
                    </button>
                    <button
                        @click="adjustPrice(-10)"
                        class="flex-1 px-3 py-1 bg-red-100 text-red-700 dark:bg-red-900 dark:text-red-300 rounded text-sm font-medium hover:bg-red-200 dark:hover:bg-red-800 transition"
                    >
                        -$10
                    </button>
                </div>

                <!-- Profit Breakdown -->
                <div v-if="profitMargin" class="bg-gray-50 dark:bg-gray-900 rounded-lg p-4">
                    <div class="grid grid-cols-3 gap-4 text-center">
                        <div>
                            <div class="text-xs text-gray-500 dark:text-gray-400 mb-1">Platform Fees ({{ form.platform_fees_percent }}%)</div>
                            <div class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                                -{{ formatCurrency(profitMargin.fees) }}
                            </div>
                        </div>
                        <div>
                            <div class="text-xs text-gray-500 dark:text-gray-400 mb-1">Net Profit</div>
                            <div :class="profitClass" class="text-lg font-bold">
                                {{ formatCurrency(profitMargin.profit) }}
                            </div>
                        </div>
                        <div>
                            <div class="text-xs text-gray-500 dark:text-gray-400 mb-1">Margin</div>
                            <div :class="profitClass" class="text-lg font-bold">
                                {{ profitMargin.margin }}%
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- A. Comparable Sold Items Table -->
        <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-6 mb-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                    Comparable Sold Items on eBay (Past Year)
                </h3>
                <a
                    v-if="soldSummary.search_url"
                    :href="soldSummary.search_url"
                    target="_blank"
                    class="text-xs text-indigo-600 hover:text-indigo-800 dark:text-indigo-400"
                >
                    View All on eBay →
                </a>
            </div>

            <!-- Summary Stats -->
            <div v-if="soldSummary && (soldSummary.average_sold || soldSummary.total_sold)" class="grid grid-cols-4 gap-3 mb-4">
                <div class="bg-green-50 dark:bg-green-900/20 rounded-lg p-3 text-center">
                    <div class="text-xs text-gray-600 dark:text-gray-400 mb-1">Average</div>
                    <div class="text-lg font-bold text-green-600 dark:text-green-400">
                        ${{ soldSummary.average_sold || 0 }}
                    </div>
                </div>
                <div class="bg-blue-50 dark:bg-blue-900/20 rounded-lg p-3 text-center">
                    <div class="text-xs text-gray-600 dark:text-gray-400 mb-1">Median</div>
                    <div class="text-lg font-bold text-blue-600 dark:text-blue-400">
                        ${{ soldSummary.median_sold || 0 }}
                    </div>
                </div>
                <div class="bg-purple-50 dark:bg-purple-900/20 rounded-lg p-3 text-center">
                    <div class="text-xs text-gray-600 dark:text-gray-400 mb-1">High / Low</div>
                    <div class="text-sm font-bold text-purple-600 dark:text-purple-400">
                        ${{ soldSummary.highest_sold || 0 }}
                        <br>
                        <span class="text-xs">/ ${{ soldSummary.lowest_sold || 0 }}</span>
                    </div>
                </div>
                <div class="bg-gray-50 dark:bg-gray-900 rounded-lg p-3 text-center">
                    <div class="text-xs text-gray-600 dark:text-gray-400 mb-1">Total Sold</div>
                    <div class="text-lg font-bold text-gray-900 dark:text-gray-100">
                        {{ soldSummary.total_sold || 0 }}
                    </div>
                </div>
            </div>

            <!-- Sold Listings Table -->
            <div v-if="sortedSoldListings.length > 0" class="max-h-[500px] overflow-y-auto border border-gray-200 dark:border-gray-700 rounded-lg">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 dark:bg-gray-900 sticky top-0">
                        <tr>
                            <th
                                @click="toggleSort(soldSort, 'title')"
                                class="text-left px-4 py-3 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider cursor-pointer hover:text-gray-700 dark:hover:text-gray-200 select-none"
                            >
                                Title{{ sortIndicator(soldSort, 'title') }}
                            </th>
                            <th
                                @click="toggleSort(soldSort, 'condition')"
                                class="text-left px-4 py-3 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider cursor-pointer hover:text-gray-700 dark:hover:text-gray-200 select-none whitespace-nowrap"
                            >
                                Condition{{ sortIndicator(soldSort, 'condition') }}
                            </th>
                            <th
                                @click="toggleSort(soldSort, 'sold_date')"
                                class="text-left px-4 py-3 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider cursor-pointer hover:text-gray-700 dark:hover:text-gray-200 select-none whitespace-nowrap"
                            >
                                Sold Date{{ sortIndicator(soldSort, 'sold_date') }}
                            </th>
                            <th
                                @click="toggleSort(soldSort, 'price')"
                                class="text-right px-4 py-3 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider cursor-pointer hover:text-gray-700 dark:hover:text-gray-200 select-none whitespace-nowrap"
                            >
                                Price{{ sortIndicator(soldSort, 'price') }}
                            </th>
                            <th
                                @click="toggleSort(soldSort, 'shipping_cost')"
                                class="text-right px-4 py-3 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider cursor-pointer hover:text-gray-700 dark:hover:text-gray-200 select-none whitespace-nowrap"
                            >
                                Shipping{{ sortIndicator(soldSort, 'shipping_cost') }}
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        <tr
                            v-for="(listing, index) in sortedSoldListings"
                            :key="index"
                            class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition"
                        >
                            <td class="px-4 py-3 max-w-xs">
                                <a
                                    v-if="listing.listing_url"
                                    :href="listing.listing_url"
                                    target="_blank"
                                    class="text-gray-900 dark:text-gray-100 hover:text-indigo-600 dark:hover:text-indigo-400 line-clamp-2"
                                >
                                    {{ listing.title }}
                                </a>
                                <span v-else class="text-gray-900 dark:text-gray-100 line-clamp-2">
                                    {{ listing.title }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-400 whitespace-nowrap">
                                {{ listing.condition || 'N/A' }}
                            </td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-400 whitespace-nowrap">
                                {{ formatDate(listing.sold_date) }}
                            </td>
                            <td class="px-4 py-3 text-right font-semibold text-gray-900 dark:text-gray-100 whitespace-nowrap">
                                {{ formatCurrency(listing.price) }}
                            </td>
                            <td class="px-4 py-3 text-right text-gray-600 dark:text-gray-400 whitespace-nowrap">
                                {{ formatShipping(listing.shipping_cost) }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div v-else class="text-sm text-gray-500 dark:text-gray-400 text-center py-8">
                No sold listings data available
            </div>
        </div>

        <!-- B. Competitor Listings Table -->
        <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-6 mb-6">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">
                Competitor Listings
            </h3>

            <div v-if="sortedActiveListings.length > 0" class="max-h-[500px] overflow-y-auto border border-gray-200 dark:border-gray-700 rounded-lg">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 dark:bg-gray-900 sticky top-0">
                        <tr>
                            <th
                                @click="toggleSort(competitorSort, 'title')"
                                class="text-left px-4 py-3 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider cursor-pointer hover:text-gray-700 dark:hover:text-gray-200 select-none"
                            >
                                Title{{ sortIndicator(competitorSort, 'title') }}
                            </th>
                            <th class="text-left px-4 py-3 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider whitespace-nowrap">
                                Source
                            </th>
                            <th
                                @click="toggleSort(competitorSort, 'condition')"
                                class="text-left px-4 py-3 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider cursor-pointer hover:text-gray-700 dark:hover:text-gray-200 select-none whitespace-nowrap"
                            >
                                Condition{{ sortIndicator(competitorSort, 'condition') }}
                            </th>
                            <th
                                @click="toggleSort(competitorSort, 'price')"
                                class="text-right px-4 py-3 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider cursor-pointer hover:text-gray-700 dark:hover:text-gray-200 select-none whitespace-nowrap"
                            >
                                Price{{ sortIndicator(competitorSort, 'price') }}
                            </th>
                            <th
                                @click="toggleSort(competitorSort, 'seller_feedback')"
                                class="text-left px-4 py-3 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider cursor-pointer hover:text-gray-700 dark:hover:text-gray-200 select-none whitespace-nowrap"
                            >
                                Seller Info{{ sortIndicator(competitorSort, 'seller_feedback') }}
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        <tr
                            v-for="(listing, index) in sortedActiveListings"
                            :key="index"
                            class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition"
                        >
                            <td class="px-4 py-3 max-w-xs">
                                <a
                                    v-if="listing.listing_url"
                                    :href="listing.listing_url"
                                    target="_blank"
                                    class="text-gray-900 dark:text-gray-100 hover:text-indigo-600 dark:hover:text-indigo-400 line-clamp-2"
                                >
                                    {{ listing.title }}
                                </a>
                                <span v-else class="text-gray-900 dark:text-gray-100 line-clamp-2">
                                    {{ listing.title }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-400 whitespace-nowrap">
                                eBay
                            </td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-400 whitespace-nowrap">
                                {{ listing.condition || 'N/A' }}
                            </td>
                            <td class="px-4 py-3 text-right font-semibold text-gray-900 dark:text-gray-100 whitespace-nowrap">
                                {{ formatCurrency(listing.price) }}
                            </td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-400 whitespace-nowrap">
                                {{ listing.seller_feedback || 'N/A' }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div v-else class="text-sm text-gray-500 dark:text-gray-400 text-center py-8">
                No competitor listings available
            </div>
        </div>

        <!-- C. AI Pricing Tips -->
        <div v-if="marketPricing?.pricing_psychology" class="bg-gradient-to-br from-yellow-50 to-orange-50 dark:from-gray-800 dark:to-gray-900 rounded-lg border-2 border-yellow-200 dark:border-yellow-800 p-6 mb-6">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4 flex items-center">
                <svg class="w-5 h-5 mr-2 text-yellow-600" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                </svg>
                AI Pricing Tips
            </h3>

            <div class="space-y-3 text-sm">
                <div v-if="marketPricing.pricing_psychology.charm_pricing_suggestion" class="flex items-start">
                    <svg class="w-4 h-4 mr-2 text-yellow-600 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                    </svg>
                    <div>
                        <div class="font-medium text-gray-900 dark:text-gray-100">Charm Pricing</div>
                        <div class="text-gray-600 dark:text-gray-400">
                            Consider pricing at <strong>${{ marketPricing.pricing_psychology.charm_pricing_suggestion }}</strong> for psychological appeal
                        </div>
                    </div>
                </div>

                <div v-if="marketPricing.pricing_psychology.free_shipping_threshold" class="flex items-start">
                    <svg class="w-4 h-4 mr-2 text-yellow-600 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M8 16.5a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0zM15 16.5a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0z"/>
                        <path d="M3 4a1 1 0 00-1 1v10a1 1 0 001 1h1.05a2.5 2.5 0 014.9 0H10a1 1 0 001-1V5a1 1 0 00-1-1H3zM14 7a1 1 0 00-1 1v6.05A2.5 2.5 0 0115.95 16H17a1 1 0 001-1v-5a1 1 0 00-.293-.707l-2-2A1 1 0 0015 7h-1z"/>
                    </svg>
                    <div>
                        <div class="font-medium text-gray-900 dark:text-gray-100">Free Shipping</div>
                        <div class="text-gray-600 dark:text-gray-400">
                            Consider free shipping above ${{ marketPricing.pricing_psychology.free_shipping_threshold }}
                        </div>
                    </div>
                </div>

                <div v-if="researchData?.listing_optimization?.platform_specific_tips?.ebay?.best_listing_format" class="flex items-start">
                    <svg class="w-4 h-4 mr-2 text-yellow-600 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z" clip-rule="evenodd"/>
                    </svg>
                    <div>
                        <div class="font-medium text-gray-900 dark:text-gray-100">Listing Format</div>
                        <div class="text-gray-600 dark:text-gray-400">
                            {{ researchData.listing_optimization.platform_specific_tips.ebay.best_listing_format }}
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="mt-8 flex justify-between items-center pt-6 border-t border-gray-200 dark:border-gray-700">
            <button
                @click="$emit('prev')"
                class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition flex items-center"
            >
                <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M9.707 16.707a1 1 0 01-1.414 0l-6-6a1 1 0 010-1.414l6-6a1 1 0 011.414 1.414L5.414 9H17a1 1 0 110 2H5.414l4.293 4.293a1 1 0 010 1.414z" clip-rule="evenodd"/>
                </svg>
                Back to Product
            </button>

            <div class="flex gap-3">
                <button
                    @click="$emit('save-draft')"
                    class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition"
                >
                    Save Draft
                </button>

                <button
                    @click="$emit('next')"
                    :disabled="!form.list_price || form.list_price <= 0"
                    :class="{
                        'opacity-50 cursor-not-allowed': !form.list_price || form.list_price <= 0,
                    }"
                    class="px-6 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-md font-medium transition flex items-center"
                >
                    Next: Create Listing
                    <svg class="w-5 h-5 ml-2" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10.293 3.293a1 1 0 011.414 0l6 6a1 1 0 010 1.414l-6 6a1 1 0 01-1.414-1.414L14.586 11H3a1 1 0 110-2h11.586l-4.293-4.293a1 1 0 010-1.414z" clip-rule="evenodd"/>
                    </svg>
                </button>
            </div>
        </div>
    </div>
</template>
