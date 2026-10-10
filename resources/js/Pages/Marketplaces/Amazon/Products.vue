<script setup>
import { ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    products: Object,
    filters: Object,
    integrationStatus: Object,
});

const search = ref(props.filters?.search || '');

let searchTimer = null;
watch(search, (val) => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
        router.get(route('marketplaces.amazon.products'), { search: val }, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    }, 350);
});

const syncProduct = (productId) => {
    router.post(route('marketplaces.amazon.products.sync', productId), {}, {
        preserveScroll: true,
    });
};

const buyBoxColor = (product) => {
    if (!product.amazon_buy_box_price || !product.amazon_listed_price) {
        return 'text-gray-500 dark:text-gray-400';
    }
    const ours = Number(product.amazon_listed_price);
    const buyBox = Number(product.amazon_buy_box_price);
    if (ours <= buyBox) return 'text-emerald-600 dark:text-emerald-400 font-semibold';
    return 'text-red-600 dark:text-red-400 font-semibold';
};

const statusBadge = (status) => {
    if (!status) return { text: 'Not Listed', class: 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400' };
    const map = {
        active: { text: 'Active', class: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-400' },
        pending: { text: 'Pending', class: 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400' },
        created: { text: 'Created', class: 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400' },
        error: { text: 'Error', class: 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400' },
        inactive: { text: 'Inactive', class: 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400' },
        deleted: { text: 'Deleted', class: 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400' },
    };
    return map[status] || { text: status, class: 'bg-gray-100 text-gray-600' };
};
</script>

<template>
    <AppLayout title="Amazon Products">
        <Head title="Amazon Products" />

        <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Amazon Products</h1>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                        Products available to list on Amazon
                    </p>
                </div>
                <Link :href="route('marketplaces.amazon.index')" class="text-sm text-yellow-600 hover:text-yellow-700">
                    &larr; Back to Amazon
                </Link>
            </div>

            <div class="mb-4">
                <input
                    v-model="search"
                    type="text"
                    placeholder="Search products..."
                    class="w-full md:w-96 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700"
                />
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-900/50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Product</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">SKU</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">ASIN</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Channel</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Price</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Buy Box</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Status</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        <tr v-for="product in products.data" :key="product.id">
                            <td class="px-6 py-4 text-sm text-gray-900 dark:text-white">{{ product.name }}</td>
                            <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">{{ product.amazon_sku || product.sku }}</td>
                            <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">{{ product.amazon_asin || '—' }}</td>
                            <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">{{ product.amazon_fulfillment_channel || '—' }}</td>
                            <td class="px-6 py-4 text-sm text-right text-gray-900 dark:text-white">
                                {{ product.amazon_listed_price ? '$' + Number(product.amazon_listed_price).toFixed(2) : '—' }}
                            </td>
                            <td class="px-6 py-4 text-sm text-right">
                                <span v-if="product.amazon_buy_box_price" :class="buyBoxColor(product)">
                                    ${{ Number(product.amazon_buy_box_price).toFixed(2) }}
                                </span>
                                <span v-else class="text-gray-400">—</span>
                            </td>
                            <td class="px-6 py-4">
                                <span :class="['inline-flex px-2 py-0.5 rounded text-xs font-medium', statusBadge(product.amazon_status).class]">
                                    {{ statusBadge(product.amazon_status).text }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <button
                                    @click="syncProduct(product.id)"
                                    class="text-sm text-yellow-600 hover:text-yellow-700"
                                >
                                    {{ product.amazon_status ? 'Re-sync' : 'List' }}
                                </button>
                            </td>
                        </tr>
                        <tr v-if="!products.data?.length">
                            <td colspan="8" class="px-6 py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                                No products found
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>
