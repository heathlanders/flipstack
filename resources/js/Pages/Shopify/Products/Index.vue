<script setup>
import { ref, computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    products: Object,
    filters: Object,
    integrationStatus: Object,
});

const search = ref(props.filters?.search || '');
const selectedProducts = ref([]);
const processing = ref({});

const isConnected = computed(() => props.integrationStatus?.connected);

const applyFilters = () => {
    router.get(route('marketplaces.shopify.products'), {
        search: search.value || undefined,
    }, {
        preserveState: true,
        preserveScroll: true,
    });
};

const clearFilters = () => {
    search.value = '';
    router.get(route('marketplaces.shopify.products'), {}, { preserveState: true });
};

const syncProduct = (productId) => {
    processing.value[productId] = true;
    router.post(route('marketplaces.shopify.products.sync', productId), {}, {
        preserveScroll: true,
        onFinish: () => {
            processing.value[productId] = false;
        }
    });
};

const bulkSync = () => {
    if (selectedProducts.value.length === 0) return;
    router.post(route('marketplaces.shopify.sync.bulk'), {
        product_ids: selectedProducts.value,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            selectedProducts.value = [];
        }
    });
};

const toggleSelectAll = (event) => {
    if (event.target.checked) {
        selectedProducts.value = props.products.data.map(p => p.id);
    } else {
        selectedProducts.value = [];
    }
};

const statusColor = (status) => {
    const colors = {
        active: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-400',
        draft: 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-400',
        pending: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400',
        error: 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400',
        created: 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400',
    };
    return colors[status] || 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-400';
};
</script>

<template>
    <AppLayout title="Shopify - Products">
        <Head title="Shopify Products" />

        <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Shopify Products</h1>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                        Products ready to sync to Shopify
                    </p>
                </div>
                <div class="flex items-center gap-3">
                    <Link :href="route('marketplaces.shopify.index')" class="text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">
                        Back to Shopify
                    </Link>
                    <button
                        v-if="selectedProducts.length > 0"
                        @click="bulkSync"
                        class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white text-sm font-medium rounded-lg"
                    >
                        Sync Selected ({{ selectedProducts.length }})
                    </button>
                </div>
            </div>

            <!-- Not connected warning -->
            <div v-if="!isConnected" class="mb-6 p-4 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-lg text-sm text-amber-700 dark:text-amber-400">
                Shopify is not connected. <Link :href="route('marketplaces.shopify.index')" class="underline font-medium">Connect your store</Link> first.
            </div>

            <!-- Search -->
            <div class="mb-6 flex items-center gap-4">
                <div class="flex-1">
                    <input
                        v-model="search"
                        @keyup.enter="applyFilters"
                        type="text"
                        placeholder="Search products..."
                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100"
                    />
                </div>
                <button @click="applyFilters" class="px-4 py-2 bg-gray-800 dark:bg-gray-600 text-white rounded-lg text-sm">Search</button>
                <button v-if="filters?.search" @click="clearFilters" class="px-4 py-2 border border-gray-300 dark:border-gray-600 text-gray-600 dark:text-gray-400 rounded-lg text-sm">Clear</button>
            </div>

            <!-- Products Table -->
            <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-900">
                        <tr>
                            <th class="px-4 py-3 w-10">
                                <input type="checkbox" @change="toggleSelectAll" class="rounded border-gray-300 dark:border-gray-600" />
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Product</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">SKU</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Price</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Stock</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Shopify Status</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        <tr v-for="product in products.data" :key="product.id" class="hover:bg-gray-50 dark:hover:bg-gray-750">
                            <td class="px-4 py-3">
                                <input type="checkbox" :value="product.id" v-model="selectedProducts" class="rounded border-gray-300 dark:border-gray-600" />
                            </td>
                            <td class="px-4 py-3">
                                <Link
                                    :href="route('inventory.products.show', product.id)"
                                    class="text-sm font-medium text-gray-900 dark:text-white hover:text-indigo-600 dark:hover:text-indigo-400 truncate max-w-xs block"
                                >
                                    {{ product.name }}
                                </Link>
                                <div v-if="product.brand" class="text-xs text-gray-500 dark:text-gray-400">{{ product.brand.name }}</div>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-400">{{ product.sku || '-' }}</td>
                            <td class="px-4 py-3 text-sm text-gray-900 dark:text-white">${{ parseFloat(product.selling_price).toFixed(2) }}</td>
                            <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-400">{{ product.stock_quantity }}</td>
                            <td class="px-4 py-3">
                                <span
                                    v-if="product.shopify_status"
                                    class="inline-flex px-2 py-0.5 rounded text-xs font-medium"
                                    :class="statusColor(product.shopify_status)"
                                >
                                    {{ product.shopify_status }}
                                </span>
                                <span v-else class="text-xs text-gray-400">Not synced</span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <button
                                    @click="syncProduct(product.id)"
                                    :disabled="processing[product.id] || !isConnected"
                                    class="px-3 py-1 text-sm bg-green-600 hover:bg-green-700 disabled:opacity-50 text-white rounded"
                                >
                                    {{ processing[product.id] ? 'Syncing...' : (product.shopify_status ? 'Re-sync' : 'Sync') }}
                                </button>
                            </td>
                        </tr>
                        <tr v-if="products.data.length === 0">
                            <td colspan="7" class="px-4 py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                                No products found
                            </td>
                        </tr>
                    </tbody>
                </table>

                <!-- Pagination -->
                <div v-if="products.links && products.links.length > 3" class="px-4 py-3 bg-gray-50 dark:bg-gray-900 border-t border-gray-200 dark:border-gray-700 flex justify-between items-center">
                    <span class="text-sm text-gray-500 dark:text-gray-400">
                        Showing {{ products.from }} to {{ products.to }} of {{ products.total }}
                    </span>
                    <div class="flex gap-1">
                        <Link
                            v-for="link in products.links"
                            :key="link.label"
                            :href="link.url || '#'"
                            v-html="link.label"
                            class="px-3 py-1 text-sm rounded border"
                            :class="link.active ? 'bg-green-600 text-white border-green-600' : 'border-gray-300 dark:border-gray-600 text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700'"
                        />
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
