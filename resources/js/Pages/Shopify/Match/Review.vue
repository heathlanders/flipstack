<script setup>
import { ref, computed, watch } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    matches: { type: Object, default: () => ({ data: [] }) },
    summary: { type: Object, default: () => ({}) },
    bulkJob: { type: Object, default: null },
    integrationStatus: { type: Object, default: () => ({}) },
});

const activeTab = ref('high');
const selectedIds = ref([]);
const showSearchModal = ref(false);
const searchModalMatchId = ref(null);
const searchQuery = ref('');
const searchResults = ref([]);
const searchLoading = ref(false);
const polling = ref(null);

const isRunning = computed(() => {
    return props.bulkJob && ['pending', 'processing'].includes(props.bulkJob.status);
});

const highConfidenceMatches = computed(() => {
    return (props.matches.data || []).filter(m =>
        m.status === 'pending' && m.product_id && m.confidence >= 85
    );
});

const lowConfidenceMatches = computed(() => {
    return (props.matches.data || []).filter(m =>
        m.status === 'pending' && m.product_id && m.confidence < 85
    );
});

const unmatchedItems = computed(() => {
    return (props.matches.data || []).filter(m =>
        m.status === 'pending' && !m.product_id
    );
});

const linkedItems = computed(() => {
    return (props.matches.data || []).filter(m => m.status === 'linked');
});

const currentTabItems = computed(() => {
    switch (activeTab.value) {
        case 'high': return highConfidenceMatches.value;
        case 'low': return lowConfidenceMatches.value;
        case 'unmatched': return unmatchedItems.value;
        case 'linked': return linkedItems.value;
        default: return [];
    }
});

const allSelected = computed({
    get() {
        const selectableIds = currentTabItems.value
            .filter(m => m.product_id && m.status === 'pending')
            .map(m => m.id);
        return selectableIds.length > 0 && selectableIds.every(id => selectedIds.value.includes(id));
    },
    set(val) {
        const selectableIds = currentTabItems.value
            .filter(m => m.product_id && m.status === 'pending')
            .map(m => m.id);
        if (val) {
            selectedIds.value = [...new Set([...selectedIds.value, ...selectableIds])];
        } else {
            selectedIds.value = selectedIds.value.filter(id => !selectableIds.includes(id));
        }
    }
});

// Poll for job status
watch(isRunning, (running) => {
    if (running) {
        startPolling();
    } else {
        stopPolling();
    }
}, { immediate: true });

function startPolling() {
    if (polling.value) return;
    polling.value = setInterval(() => {
        router.reload({ only: ['matches', 'summary', 'bulkJob'] });
    }, 3000);
}

function stopPolling() {
    if (polling.value) {
        clearInterval(polling.value);
        polling.value = null;
    }
}

function toggleSelect(id) {
    const idx = selectedIds.value.indexOf(id);
    if (idx >= 0) {
        selectedIds.value.splice(idx, 1);
    } else {
        selectedIds.value.push(id);
    }
}

function confirmSelected() {
    if (selectedIds.value.length === 0) return;
    router.post(route('marketplaces.shopify.match.confirm'), {
        match_ids: selectedIds.value,
    }, {
        onSuccess: () => { selectedIds.value = []; }
    });
}

function confirmAll() {
    router.post(route('marketplaces.shopify.match.confirm-all'));
}

function rejectMatch(matchId) {
    router.post(route('marketplaces.shopify.match.reject', matchId));
}

function ignoreMatch(matchId) {
    router.post(route('marketplaces.shopify.match.ignore', matchId));
}

function openSearchModal(matchId) {
    searchModalMatchId.value = matchId;
    searchQuery.value = '';
    searchResults.value = [];
    showSearchModal.value = true;
}

function closeSearchModal() {
    showSearchModal.value = false;
    searchModalMatchId.value = null;
}

let searchTimeout = null;
function onSearchInput() {
    clearTimeout(searchTimeout);
    if (searchQuery.value.length < 2) {
        searchResults.value = [];
        return;
    }
    searchTimeout = setTimeout(async () => {
        searchLoading.value = true;
        try {
            const response = await fetch(route('marketplaces.shopify.match.search-products', { q: searchQuery.value }));
            const data = await response.json();
            searchResults.value = data.products || [];
        } catch (e) {
            searchResults.value = [];
        }
        searchLoading.value = false;
    }, 300);
}

function pairProduct(productId) {
    router.post(route('marketplaces.shopify.match.pair', searchModalMatchId.value), {
        product_id: productId,
    }, {
        onSuccess: () => { closeSearchModal(); }
    });
}

function startMatching() {
    router.post(route('marketplaces.shopify.match.start'));
}

function matchTypeBadge(type, confidence) {
    const labels = {
        sku: { text: 'SKU Match', class: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-400' },
        upc: { text: 'UPC Match', class: 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400' },
        ean: { text: 'EAN Match', class: 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400' },
        barcode: { text: 'Barcode Match', class: 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400' },
        title: { text: `Title ${confidence}%`, class: confidence >= 70 ? 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400' : 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400' },
        manual: { text: 'Manual', class: 'bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-400' },
    };
    return labels[type] || { text: 'Unknown', class: 'bg-gray-100 text-gray-800' };
}

function hasQtyDiscrepancy(match) {
    if (!match.product || match.source_quantity === null) return false;
    return match.source_quantity !== match.product.available_quantity;
}
</script>

<template>
    <AppLayout title="Link Shopify Products">
        <Head title="Link Shopify Products" />

        <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
            <!-- Header -->
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Link Shopify Products</h1>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                        Match existing Shopify listings to your imported products
                    </p>
                </div>
                <div class="flex items-center gap-3">
                    <Link :href="route('marketplaces.shopify.index')" class="px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-300">
                        Back to Shopify
                    </Link>
                    <button
                        @click="startMatching"
                        :disabled="isRunning"
                        class="px-4 py-2 bg-green-600 hover:bg-green-700 disabled:opacity-50 text-white font-medium rounded-lg text-sm"
                    >
                        {{ bulkJob ? 'Re-run Matching' : 'Start Matching' }}
                    </button>
                </div>
            </div>

            <!-- Progress Bar -->
            <div v-if="isRunning" class="mb-6 bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-6">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Matching in progress...</span>
                    <span class="text-sm text-gray-500 dark:text-gray-400">
                        {{ bulkJob.processed_items }} / {{ bulkJob.total_items || '...' }}
                    </span>
                </div>
                <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2.5">
                    <div
                        class="bg-green-600 h-2.5 rounded-full transition-all duration-500"
                        :style="{ width: (bulkJob.progress_percent || 0) + '%' }"
                    ></div>
                </div>
            </div>

            <!-- Summary Stats -->
            <div v-if="bulkJob && !isRunning" class="mb-6 grid grid-cols-2 md:grid-cols-6 gap-4">
                <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-4">
                    <p class="text-xs text-gray-500 dark:text-gray-400">Total</p>
                    <p class="text-xl font-bold text-gray-900 dark:text-white">{{ summary.total }}</p>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-4">
                    <p class="text-xs text-gray-500 dark:text-gray-400">High Confidence</p>
                    <p class="text-xl font-bold text-emerald-600 dark:text-emerald-400">{{ summary.matched_high }}</p>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-4">
                    <p class="text-xs text-gray-500 dark:text-gray-400">Needs Review</p>
                    <p class="text-xl font-bold text-amber-600 dark:text-amber-400">{{ summary.matched_low }}</p>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-4">
                    <p class="text-xs text-gray-500 dark:text-gray-400">Unmatched</p>
                    <p class="text-xl font-bold text-red-600 dark:text-red-400">{{ summary.unmatched }}</p>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-4">
                    <p class="text-xs text-gray-500 dark:text-gray-400">Linked</p>
                    <p class="text-xl font-bold text-blue-600 dark:text-blue-400">{{ summary.linked }}</p>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-4">
                    <p class="text-xs text-gray-500 dark:text-gray-400">Ignored</p>
                    <p class="text-xl font-bold text-gray-500 dark:text-gray-400">{{ summary.ignored }}</p>
                </div>
            </div>

            <!-- No results state -->
            <div v-if="!bulkJob" class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-12 text-center">
                <div class="w-16 h-16 mx-auto mb-4 rounded-full bg-green-50 dark:bg-green-900/20 flex items-center justify-center">
                    <svg class="w-8 h-8 text-green-600 dark:text-green-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" />
                    </svg>
                </div>
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">Link Shopify Products</h3>
                <p class="text-sm text-gray-600 dark:text-gray-400 mb-6 max-w-md mx-auto">
                    Match your Shopify listings to existing products by SKU, UPC/EAN, or title similarity. This prevents duplicate products and enables cross-marketplace inventory sync.
                </p>
                <button
                    @click="startMatching"
                    class="px-6 py-2.5 bg-green-600 hover:bg-green-700 text-white font-medium rounded-lg"
                >
                    Start Matching
                </button>
            </div>

            <!-- Matches Table -->
            <div v-if="bulkJob && !isRunning" class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700">
                <!-- Tabs -->
                <div class="border-b border-gray-200 dark:border-gray-700 px-4">
                    <nav class="flex gap-6 -mb-px">
                        <button
                            v-for="tab in [
                                { key: 'high', label: 'High Confidence', count: summary.matched_high },
                                { key: 'low', label: 'Needs Review', count: summary.matched_low },
                                { key: 'unmatched', label: 'Unmatched', count: summary.unmatched },
                                { key: 'linked', label: 'Linked', count: summary.linked },
                            ]"
                            :key="tab.key"
                            @click="activeTab = tab.key; selectedIds = []"
                            :class="[
                                'py-3 px-1 text-sm font-medium border-b-2 transition-colors',
                                activeTab === tab.key
                                    ? 'border-green-500 text-green-600 dark:text-green-400'
                                    : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300'
                            ]"
                        >
                            {{ tab.label }}
                            <span class="ml-1.5 px-1.5 py-0.5 text-xs rounded-full bg-gray-100 dark:bg-gray-700">{{ tab.count }}</span>
                        </button>
                    </nav>
                </div>

                <!-- Bulk Actions -->
                <div v-if="activeTab !== 'linked'" class="px-4 py-3 border-b border-gray-200 dark:border-gray-700 flex items-center gap-3">
                    <label v-if="activeTab !== 'unmatched'" class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400">
                        <input type="checkbox" v-model="allSelected" class="rounded border-gray-300 dark:border-gray-600" />
                        Select all
                    </label>
                    <button
                        v-if="selectedIds.length > 0"
                        @click="confirmSelected"
                        class="px-3 py-1.5 bg-green-600 hover:bg-green-700 text-white text-sm rounded-lg"
                    >
                        Confirm {{ selectedIds.length }} Selected
                    </button>
                    <button
                        v-if="activeTab === 'high' && summary.matched_high > 0"
                        @click="confirmAll"
                        class="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white text-sm rounded-lg"
                    >
                        Confirm All High Confidence
                    </button>
                </div>

                <!-- Match Rows -->
                <div class="divide-y divide-gray-200 dark:divide-gray-700">
                    <div
                        v-for="match in currentTabItems"
                        :key="match.id"
                        class="px-4 py-4"
                    >
                        <div class="flex items-start gap-4">
                            <!-- Checkbox -->
                            <div v-if="match.product_id && match.status === 'pending'" class="pt-1">
                                <input
                                    type="checkbox"
                                    :checked="selectedIds.includes(match.id)"
                                    @change="toggleSelect(match.id)"
                                    class="rounded border-gray-300 dark:border-gray-600"
                                />
                            </div>

                            <!-- Shopify Side -->
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2 mb-1">
                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400">Shopify</span>
                                    <span v-if="match.match_type" :class="['inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium', matchTypeBadge(match.match_type, match.confidence).class]">
                                        {{ matchTypeBadge(match.match_type, match.confidence).text }}
                                    </span>
                                    <span v-if="hasQtyDiscrepancy(match)" class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400">
                                        Qty mismatch
                                    </span>
                                </div>
                                <p class="text-sm font-medium text-gray-900 dark:text-white truncate">{{ match.source_title }}</p>
                                <div class="mt-1 flex items-center gap-4 text-xs text-gray-500 dark:text-gray-400">
                                    <span v-if="match.source_sku">SKU: {{ match.source_sku }}</span>
                                    <span v-if="match.source_barcode">Barcode: {{ match.source_barcode }}</span>
                                    <span v-if="match.source_price">${{ match.source_price }}</span>
                                    <span v-if="match.source_quantity !== null">Qty: {{ match.source_quantity }}</span>
                                </div>
                            </div>

                            <!-- Arrow -->
                            <div v-if="match.product_id" class="flex-shrink-0 pt-3">
                                <svg class="w-5 h-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6" />
                                </svg>
                            </div>

                            <!-- Product Side -->
                            <div v-if="match.product" class="flex-1 min-w-0">
                                <div class="flex items-center gap-2 mb-1">
                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400">Product</span>
                                </div>
                                <p class="text-sm font-medium text-gray-900 dark:text-white truncate">{{ match.product.name }}</p>
                                <div class="mt-1 flex items-center gap-4 text-xs text-gray-500 dark:text-gray-400">
                                    <span>SKU: {{ match.product.sku }}</span>
                                    <span v-if="match.product.upc">UPC: {{ match.product.upc }}</span>
                                    <span>${{ match.product.selling_price }}</span>
                                    <span>Avail: {{ match.product.available_quantity }}</span>
                                </div>
                            </div>

                            <!-- No match placeholder -->
                            <div v-if="!match.product_id" class="flex-1 min-w-0">
                                <p class="text-sm text-gray-400 dark:text-gray-500 italic">No match found</p>
                            </div>

                            <!-- Actions -->
                            <div class="flex-shrink-0 flex items-center gap-2">
                                <template v-if="match.status === 'pending'">
                                    <button
                                        v-if="match.product_id"
                                        @click="router.post(route('marketplaces.shopify.match.confirm'), { match_ids: [match.id] })"
                                        class="p-1.5 text-emerald-600 hover:bg-emerald-50 dark:hover:bg-emerald-900/20 rounded"
                                        title="Confirm match"
                                    >
                                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                                    </button>
                                    <button
                                        v-if="match.product_id"
                                        @click="rejectMatch(match.id)"
                                        class="p-1.5 text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20 rounded"
                                        title="Reject match"
                                    >
                                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                                    </button>
                                    <button
                                        @click="openSearchModal(match.id)"
                                        class="p-1.5 text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-900/20 rounded"
                                        :title="match.product_id ? 'Change match' : 'Link to product'"
                                    >
                                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
                                    </button>
                                    <button
                                        v-if="!match.product_id"
                                        @click="ignoreMatch(match.id)"
                                        class="p-1.5 text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700 rounded"
                                        title="Ignore"
                                    >
                                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" /></svg>
                                    </button>
                                </template>
                                <span v-else-if="match.status === 'linked'" class="text-xs text-emerald-600 dark:text-emerald-400 font-medium">Linked</span>
                                <span v-else-if="match.status === 'ignored'" class="text-xs text-gray-400 font-medium">Ignored</span>
                                <span v-else-if="match.status === 'rejected'" class="text-xs text-red-400 font-medium">Rejected</span>
                            </div>
                        </div>
                    </div>

                    <!-- Empty tab state -->
                    <div v-if="currentTabItems.length === 0" class="px-4 py-12 text-center text-sm text-gray-500 dark:text-gray-400">
                        No items in this category
                    </div>
                </div>

                <!-- Pagination -->
                <div v-if="matches.links && matches.links.length > 3" class="px-4 py-3 border-t border-gray-200 dark:border-gray-700 flex items-center justify-between">
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        Showing {{ matches.from }}-{{ matches.to }} of {{ matches.total }}
                    </p>
                    <div class="flex gap-1">
                        <Link
                            v-for="link in matches.links"
                            :key="link.label"
                            :href="link.url || '#'"
                            v-html="link.label"
                            :class="[
                                'px-3 py-1 text-sm rounded',
                                link.active ? 'bg-green-600 text-white' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700',
                                !link.url ? 'opacity-50 pointer-events-none' : ''
                            ]"
                        />
                    </div>
                </div>
            </div>
        </div>

        <!-- Search Modal -->
        <Teleport to="body">
            <div v-if="showSearchModal" class="fixed inset-0 z-50 flex items-center justify-center">
                <div class="fixed inset-0 bg-black/50" @click="closeSearchModal"></div>
                <div class="relative bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-lg w-full mx-4 max-h-[80vh] flex flex-col">
                    <div class="p-4 border-b border-gray-200 dark:border-gray-700">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Search Products</h3>
                        <input
                            v-model="searchQuery"
                            @input="onSearchInput"
                            type="text"
                            placeholder="Search by name, SKU, or UPC..."
                            class="mt-3 w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 text-sm"
                            autofocus
                        />
                    </div>
                    <div class="flex-1 overflow-y-auto p-4">
                        <div v-if="searchLoading" class="text-center py-8 text-sm text-gray-500">Searching...</div>
                        <div v-else-if="searchResults.length === 0 && searchQuery.length >= 2" class="text-center py-8 text-sm text-gray-500">No products found</div>
                        <div v-else class="space-y-2">
                            <button
                                v-for="product in searchResults"
                                :key="product.id"
                                @click="pairProduct(product.id)"
                                class="w-full text-left p-3 rounded-lg border border-gray-200 dark:border-gray-700 hover:border-green-300 dark:hover:border-green-600 hover:bg-green-50 dark:hover:bg-green-900/10 transition-colors"
                            >
                                <p class="text-sm font-medium text-gray-900 dark:text-white truncate">{{ product.name }}</p>
                                <div class="mt-1 flex items-center gap-4 text-xs text-gray-500 dark:text-gray-400">
                                    <span>SKU: {{ product.sku }}</span>
                                    <span v-if="product.upc">UPC: {{ product.upc }}</span>
                                    <span>${{ product.selling_price }}</span>
                                    <span>Avail: {{ product.available_quantity }}</span>
                                </div>
                            </button>
                        </div>
                    </div>
                    <div class="p-4 border-t border-gray-200 dark:border-gray-700">
                        <button
                            @click="closeSearchModal"
                            class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700"
                        >
                            Cancel
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>
    </AppLayout>
</template>
