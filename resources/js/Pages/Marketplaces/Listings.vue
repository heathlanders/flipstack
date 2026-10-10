<script setup>
import { ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import TextInput from '@/Components/TextInput.vue';

const props = defineProps({
    listings: Object,
    filters: Object,
});

const search = ref(props.filters?.search || '');
const platform = ref(props.filters?.platform || '');

let searchTimeout = null;

const applyFilters = () => {
    router.get(route('marketplaces.hub.listings'), {
        search: search.value || undefined,
        platform: platform.value || undefined,
    }, {
        preserveState: true,
        preserveScroll: true,
    });
};

watch(search, () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(applyFilters, 300);
});

watch(platform, applyFilters);

const statusBadgeClass = (color) => {
    const map = {
        green: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-400',
        yellow: 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400',
        red: 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400',
        blue: 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400',
        gray: 'bg-gray-100 text-gray-800 dark:bg-gray-600 dark:text-gray-200',
    };
    return map[color] || map.gray;
};
</script>

<template>
    <AppLayout title="All Marketplace Listings">
        <Head title="All Listings - Marketplace Hub" />

        <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
            <!-- Header -->
            <div class="flex items-center justify-between mb-6">
                <div class="flex items-center">
                    <Link
                        :href="route('marketplaces.hub.index')"
                        class="mr-4 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
                    >
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                        </svg>
                    </Link>
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">All Marketplace Listings</h1>
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                            Products listed across all connected marketplaces
                        </p>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-4 mb-6">
                <div class="flex flex-col sm:flex-row gap-4">
                    <div class="flex-1">
                        <TextInput
                            v-model="search"
                            type="text"
                            placeholder="Search by product name or SKU..."
                            class="w-full"
                        />
                    </div>
                    <div>
                        <select
                            v-model="platform"
                            class="block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600"
                        >
                            <option value="">All Platforms</option>
                            <option value="ebay">eBay</option>
                            <option value="shopify">Shopify</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Listings Table -->
            <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-900/50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Product</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Price</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Stock</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">eBay</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Shopify</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            <tr
                                v-for="product in listings.data"
                                :key="product.id"
                                class="hover:bg-gray-50 dark:hover:bg-gray-700/50"
                            >
                                <td class="px-6 py-4">
                                    <Link :href="route('inventory.products.show', product.id)" class="group">
                                        <div class="flex items-center">
                                            <div class="flex-shrink-0 h-10 w-10 rounded bg-gray-100 dark:bg-gray-700 overflow-hidden">
                                                <img
                                                    v-if="product.featured_image"
                                                    :src="product.featured_image.startsWith('http') ? product.featured_image : '/storage/' + product.featured_image"
                                                    class="h-10 w-10 object-cover"
                                                    :alt="product.name"
                                                />
                                                <div v-else class="h-10 w-10 flex items-center justify-center text-gray-400">
                                                    <i class="fas fa-box text-sm"></i>
                                                </div>
                                            </div>
                                            <div class="ml-3">
                                                <p class="text-sm font-medium text-gray-900 dark:text-white group-hover:text-indigo-600 dark:group-hover:text-indigo-400 line-clamp-1">{{ product.name }}</p>
                                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ product.sku }}</p>
                                            </div>
                                        </div>
                                    </Link>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-900 dark:text-white">
                                    ${{ Number(product.selling_price).toFixed(2) }}
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-900 dark:text-white">
                                    {{ product.stock_quantity }}
                                </td>
                                <td class="px-6 py-4">
                                    <div v-if="product.ebay_listings?.length" class="space-y-1">
                                        <div v-for="listing in product.ebay_listings" :key="listing.id" class="flex items-center gap-2">
                                            <span
                                                class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium"
                                                :class="statusBadgeClass(listing.status_color)"
                                            >
                                                {{ listing.status_label }}
                                            </span>
                                            <span class="text-xs text-gray-500 dark:text-gray-400">${{ Number(listing.listed_price).toFixed(2) }}</span>
                                            <a
                                                v-if="listing.ebay_url"
                                                :href="listing.ebay_url"
                                                target="_blank"
                                                class="text-xs text-indigo-600 hover:text-indigo-500 dark:text-indigo-400"
                                            >
                                                <i class="fas fa-external-link-alt"></i>
                                            </a>
                                        </div>
                                    </div>
                                    <span v-else class="text-xs text-gray-400 dark:text-gray-500">--</span>
                                </td>
                                <td class="px-6 py-4">
                                    <div v-if="product.shopify_listings?.length" class="space-y-1">
                                        <div v-for="listing in product.shopify_listings" :key="listing.id" class="flex items-center gap-2">
                                            <span
                                                class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium"
                                                :class="statusBadgeClass(listing.status_color)"
                                            >
                                                {{ listing.status_label }}
                                            </span>
                                            <span class="text-xs text-gray-500 dark:text-gray-400">${{ Number(listing.listed_price).toFixed(2) }}</span>
                                        </div>
                                    </div>
                                    <span v-else class="text-xs text-gray-400 dark:text-gray-500">--</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Empty State -->
                <div v-if="!listings.data?.length" class="text-center py-12">
                    <i class="fas fa-store text-4xl text-gray-300 dark:text-gray-600 mb-3"></i>
                    <p class="text-gray-500 dark:text-gray-400">No marketplace listings found</p>
                </div>

                <!-- Pagination -->
                <div v-if="listings.last_page > 1" class="px-6 py-3 border-t border-gray-200 dark:border-gray-700 flex items-center justify-between">
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        Showing {{ listings.from }} to {{ listings.to }} of {{ listings.total }} results
                    </p>
                    <div class="flex gap-2">
                        <Link
                            v-if="listings.prev_page_url"
                            :href="listings.prev_page_url"
                            class="px-3 py-1 text-sm border border-gray-300 dark:border-gray-600 rounded hover:bg-gray-50 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-300"
                            preserve-state
                        >
                            Previous
                        </Link>
                        <Link
                            v-if="listings.next_page_url"
                            :href="listings.next_page_url"
                            class="px-3 py-1 text-sm border border-gray-300 dark:border-gray-600 rounded hover:bg-gray-50 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-300"
                            preserve-state
                        >
                            Next
                        </Link>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
