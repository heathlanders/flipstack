<script setup>
import { ref, computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    listings: {
        type: Object,
        required: true
    },
    filters: {
        type: Object,
        default: () => ({ search: '', status: '' })
    },
    statuses: {
        type: Object,
        default: () => ({})
    }
});

const search = ref(props.filters.search || '');
const statusFilter = ref(props.filters.status || '');
const processing = ref({});

const applyFilters = () => {
    router.get(route('marketplaces.ebay.listings'), {
        search: search.value || undefined,
        status: statusFilter.value || undefined,
    }, {
        preserveState: true,
        preserveScroll: true
    });
};

const clearFilters = () => {
    search.value = '';
    statusFilter.value = '';
    applyFilters();
};

const updateListing = (listing) => {
    processing.value[listing.id] = 'update';
    router.post(route('marketplaces.ebay.listings.update', listing.id), {
        update_type: 'both',
    }, {
        preserveScroll: true,
        onFinish: () => { delete processing.value[listing.id]; }
    });
};

const retryListing = (listing) => {
    processing.value[listing.id] = 'retry';
    router.post(route('marketplaces.ebay.listings.retry', listing.id), {}, {
        preserveScroll: true,
        onFinish: () => { delete processing.value[listing.id]; }
    });
};

const retryAllFailed = () => {
    processing.value['all'] = 'retry-all';
    router.post(route('marketplaces.ebay.retry.all'), {}, {
        preserveScroll: true,
        onFinish: () => { delete processing.value['all']; }
    });
};

const hasErrors = computed(() => {
    return props.listings.data.some(l => l.status === 'error');
});

const statusColor = (status) => {
    const colors = {
        published: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-400',
        pending: 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400',
        error: 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400',
        ended: 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400',
        inventory_created: 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400',
        offer_created: 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900/30 dark:text-indigo-400',
        out_of_stock: 'bg-orange-100 text-orange-800 dark:bg-orange-900/30 dark:text-orange-400',
    };
    return colors[status] || 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400';
};
</script>

<template>
    <AppLayout title="eBay Listings">
        <Head title="eBay Listings - Marketplaces" />

        <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
            <!-- Breadcrumb -->
            <nav class="flex mb-6" aria-label="Breadcrumb">
                <ol class="flex items-center space-x-2 text-sm text-gray-500 dark:text-gray-400">
                    <li><Link :href="route('marketplaces.ebay.index')" class="hover:text-gray-700 dark:hover:text-gray-200">eBay</Link></li>
                    <li><i class="fas fa-chevron-right text-xs mx-1"></i></li>
                    <li class="text-gray-900 dark:text-white font-medium">Listings</li>
                </ol>
            </nav>

            <div class="flex items-center justify-between mb-6">
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">eBay Listings</h1>
                <button
                    v-if="hasErrors"
                    @click="retryAllFailed"
                    :disabled="processing['all']"
                    class="px-4 py-2 bg-red-600 hover:bg-red-700 disabled:opacity-50 text-white text-sm font-medium rounded-lg"
                >
                    {{ processing['all'] ? 'Retrying...' : 'Retry All Failed' }}
                </button>
            </div>

            <!-- Filters -->
            <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-4 mb-6">
                <div class="flex flex-wrap items-center gap-3">
                    <input
                        v-model="search"
                        type="text"
                        placeholder="Search by SKU or product name..."
                        class="px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 text-sm w-64"
                        @keyup.enter="applyFilters"
                    />
                    <select
                        v-model="statusFilter"
                        class="px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 text-sm"
                        @change="applyFilters"
                    >
                        <option value="">All Statuses</option>
                        <option v-for="(label, value) in statuses" :key="value" :value="value">{{ label }}</option>
                    </select>
                    <button @click="applyFilters" class="px-3 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm rounded-lg">Filter</button>
                    <button @click="clearFilters" class="px-3 py-2 text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white text-sm">Clear</button>
                </div>
            </div>

            <!-- Listings Table -->
            <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-900/50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Product</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">SKU</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Status</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Price</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Qty</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">eBay ID</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Last Synced</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            <tr v-for="listing in listings.data" :key="listing.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-3">
                                        <img v-if="listing.product?.featured_image" :src="listing.product.featured_image" class="w-8 h-8 rounded object-cover" />
                                        <div class="w-8 h-8 rounded bg-gray-200 dark:bg-gray-600 flex items-center justify-center" v-else>
                                            <i class="fas fa-image text-gray-400 text-xs"></i>
                                        </div>
                                        <Link
                                            v-if="listing.product?.id"
                                            :href="route('inventory.products.show', listing.product.id)"
                                            class="text-sm text-gray-900 dark:text-white hover:text-indigo-600 dark:hover:text-indigo-400 truncate max-w-[200px]"
                                        >
                                            {{ listing.product?.name ?? 'Unknown' }}
                                        </Link>
                                        <span v-else class="text-sm text-gray-900 dark:text-white truncate max-w-[200px]">{{ listing.product?.name ?? 'Unknown' }}</span>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-400 font-mono">{{ listing.ebay_sku }}</td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex px-2 py-0.5 rounded text-xs font-medium" :class="statusColor(listing.status)">
                                        {{ listing.status_label }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-900 dark:text-white">${{ listing.listed_price ?? '-' }}</td>
                                <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-400">{{ listing.available_quantity ?? '-' }}</td>
                                <td class="px-4 py-3 text-sm">
                                    <a v-if="listing.ebay_listing_url" :href="listing.ebay_listing_url" target="_blank" class="text-blue-600 dark:text-blue-400 hover:underline">
                                        {{ listing.ebay_listing_id ?? 'View' }}
                                    </a>
                                    <span v-else class="text-gray-400">-</span>
                                </td>
                                <td class="px-4 py-3 text-xs text-gray-500 dark:text-gray-400">
                                    {{ listing.last_synced_at ? new Date(listing.last_synced_at).toLocaleDateString() : '-' }}
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-2">
                                        <button
                                            v-if="listing.status === 'published' || listing.status === 'out_of_stock'"
                                            @click="updateListing(listing)"
                                            :disabled="processing[listing.id]"
                                            class="px-2 py-1 text-xs font-medium text-blue-700 dark:text-blue-400 bg-blue-50 dark:bg-blue-900/20 rounded hover:bg-blue-100 dark:hover:bg-blue-900/40 disabled:opacity-50"
                                        >
                                            {{ processing[listing.id] === 'update' ? '...' : 'Update' }}
                                        </button>
                                        <button
                                            v-if="listing.status === 'error'"
                                            @click="retryListing(listing)"
                                            :disabled="processing[listing.id]"
                                            class="px-2 py-1 text-xs font-medium text-red-700 dark:text-red-400 bg-red-50 dark:bg-red-900/20 rounded hover:bg-red-100 dark:hover:bg-red-900/40 disabled:opacity-50"
                                        >
                                            {{ processing[listing.id] === 'retry' ? '...' : 'Retry' }}
                                        </button>
                                        <Link
                                            v-if="listing.product?.id && (listing.status === 'published' || listing.status === 'out_of_stock')"
                                            :href="route('marketplaces.cross-list.preview', { product_id: listing.product.id, source: 'ebay', target: 'shopify' })"
                                            class="px-2 py-1 text-xs font-medium text-green-700 dark:text-green-400 bg-green-50 dark:bg-green-900/20 rounded hover:bg-green-100 dark:hover:bg-green-900/40"
                                        >
                                            Cross-list
                                        </Link>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="listings.data.length === 0">
                                <td colspan="8" class="px-4 py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                                    No listings found.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div v-if="listings.last_page > 1" class="px-4 py-3 border-t border-gray-200 dark:border-gray-700 flex items-center justify-between">
                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        Showing {{ listings.from }} to {{ listings.to }} of {{ listings.total }}
                    </p>
                    <div class="flex gap-1">
                        <Link
                            v-for="link in listings.links"
                            :key="link.label"
                            :href="link.url"
                            :class="[
                                'px-3 py-1 rounded text-sm',
                                link.active ? 'bg-blue-600 text-white' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700',
                                !link.url ? 'opacity-50 pointer-events-none' : ''
                            ]"
                            v-html="link.label"
                            preserve-state
                        />
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
