<script setup>
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    products: {
        type: Object,
        required: true
    },
    filters: {
        type: Object,
        default: () => ({ search: '' })
    },
    hasCredential: {
        type: Boolean,
        default: false
    },
    isConfigured: {
        type: Boolean,
        default: false
    }
});

const search = ref(props.filters.search || '');
const selectedProducts = ref([]);
const processing = ref({});

const applyFilters = () => {
    router.get(route('marketplaces.ebay.products'), {
        search: search.value || undefined,
    }, {
        preserveState: true,
        preserveScroll: true
    });
};

const clearFilters = () => {
    search.value = '';
    applyFilters();
};

const toggleSelection = (id) => {
    const index = selectedProducts.value.indexOf(id);
    if (index > -1) {
        selectedProducts.value.splice(index, 1);
    } else {
        selectedProducts.value.push(id);
    }
};

const toggleSelectAll = () => {
    if (selectedProducts.value.length === props.products.data.length) {
        selectedProducts.value = [];
    } else {
        selectedProducts.value = props.products.data.map(p => p.id);
    }
};

const syncProduct = (product) => {
    processing.value[product.id] = true;
    router.post(route('marketplaces.ebay.products.sync', product.id), {}, {
        preserveScroll: true,
        onFinish: () => { delete processing.value[product.id]; }
    });
};

const syncBulk = () => {
    processing.value['bulk'] = true;
    router.post(route('marketplaces.ebay.sync.bulk'), {
        product_ids: selectedProducts.value
    }, {
        preserveScroll: true,
        onFinish: () => {
            delete processing.value['bulk'];
            selectedProducts.value = [];
        }
    });
};
</script>

<template>
    <AppLayout title="eBay Products">
        <Head title="eBay Products - Marketplaces" />

        <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
            <!-- Breadcrumb -->
            <nav class="flex mb-6" aria-label="Breadcrumb">
                <ol class="flex items-center space-x-2 text-sm text-gray-500 dark:text-gray-400">
                    <li><Link :href="route('marketplaces.ebay.index')" class="hover:text-gray-700 dark:hover:text-gray-200">eBay</Link></li>
                    <li><i class="fas fa-chevron-right text-xs mx-1"></i></li>
                    <li class="text-gray-900 dark:text-white font-medium">Products</li>
                </ol>
            </nav>

            <div class="flex items-center justify-between mb-6">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Products Ready for eBay</h1>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Products that can be synced to your eBay store</p>
                </div>
                <button
                    v-if="selectedProducts.length > 0 && isConfigured"
                    @click="syncBulk"
                    :disabled="processing['bulk']"
                    class="px-4 py-2 bg-blue-600 hover:bg-blue-700 disabled:opacity-50 text-white text-sm font-medium rounded-lg"
                >
                    {{ processing['bulk'] ? 'Syncing...' : `Sync ${selectedProducts.length} Selected` }}
                </button>
            </div>

            <!-- Not configured warning -->
            <div v-if="!isConfigured" class="mb-6 p-4 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-lg">
                <p class="text-sm text-amber-800 dark:text-amber-300">
                    eBay integration is not fully configured.
                    <Link :href="route('marketplaces.ebay.setup')" class="font-medium underline">Complete setup</Link>
                    to start syncing products.
                </p>
            </div>

            <!-- Filters -->
            <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-4 mb-6">
                <div class="flex flex-wrap items-center gap-3">
                    <input
                        v-model="search"
                        type="text"
                        placeholder="Search by name or SKU..."
                        class="px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 text-sm w-64"
                        @keyup.enter="applyFilters"
                    />
                    <button @click="applyFilters" class="px-3 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm rounded-lg">Search</button>
                    <button @click="clearFilters" class="px-3 py-2 text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white text-sm">Clear</button>
                </div>
            </div>

            <!-- Products Table -->
            <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-900/50">
                            <tr>
                                <th class="w-12 px-4 py-3">
                                    <input
                                        type="checkbox"
                                        :checked="selectedProducts.length === products.data.length && products.data.length > 0"
                                        @change="toggleSelectAll"
                                        class="rounded border-gray-300 dark:border-gray-600 text-blue-600 focus:ring-blue-500"
                                    />
                                </th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Product</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">SKU</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Price</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Stock</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Condition</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            <tr v-for="product in products.data" :key="product.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                <td class="px-4 py-3">
                                    <input
                                        type="checkbox"
                                        :checked="selectedProducts.includes(product.id)"
                                        @change="toggleSelection(product.id)"
                                        class="rounded border-gray-300 dark:border-gray-600 text-blue-600 focus:ring-blue-500"
                                    />
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-3">
                                        <img v-if="product.featured_image" :src="product.featured_image" class="w-8 h-8 rounded object-cover" />
                                        <div class="w-8 h-8 rounded bg-gray-200 dark:bg-gray-600 flex items-center justify-center" v-else>
                                            <i class="fas fa-image text-gray-400 text-xs"></i>
                                        </div>
                                        <span class="text-sm text-gray-900 dark:text-white truncate max-w-[200px]">{{ product.name }}</span>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-400 font-mono">{{ product.sku }}</td>
                                <td class="px-4 py-3 text-sm text-gray-900 dark:text-white">${{ product.selling_price ?? '-' }}</td>
                                <td class="px-4 py-3 text-sm" :class="product.stock_quantity > 0 ? 'text-gray-600 dark:text-gray-400' : 'text-red-600 dark:text-red-400'">
                                    {{ product.stock_quantity ?? 0 }}
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-400">{{ product.condition ?? '-' }}</td>
                                <td class="px-4 py-3">
                                    <button
                                        v-if="isConfigured"
                                        @click="syncProduct(product)"
                                        :disabled="processing[product.id]"
                                        class="px-2 py-1 text-xs font-medium text-blue-700 dark:text-blue-400 bg-blue-50 dark:bg-blue-900/20 rounded hover:bg-blue-100 dark:hover:bg-blue-900/40 disabled:opacity-50"
                                    >
                                        {{ processing[product.id] ? 'Syncing...' : 'Sync to eBay' }}
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="products.data.length === 0">
                                <td colspan="7" class="px-4 py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                                    No products ready to sync. All products are either already on eBay or not ready.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div v-if="products.last_page > 1" class="px-4 py-3 border-t border-gray-200 dark:border-gray-700 flex items-center justify-between">
                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        Showing {{ products.from }} to {{ products.to }} of {{ products.total }}
                    </p>
                    <div class="flex gap-1">
                        <Link
                            v-for="link in products.links"
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
