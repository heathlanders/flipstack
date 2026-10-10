<script setup>
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    products: Object,
    categories: Array,
    brands: Array,
    filters: Object,
});

// Local state
const viewMode = ref('grid'); // 'grid' or 'list'
const localFilters = ref({ ...props.filters });

// Apply filters
const applyFilters = () => {
    router.get('/shop', localFilters.value, {
        preserveState: true,
        preserveScroll: true,
    });
};

// Clear filters
const clearFilters = () => {
    router.get('/shop');
};

// Quick filter shortcuts
const filterByCategory = (categoryId) => {
    localFilters.value.category = categoryId;
    applyFilters();
};

const filterByBrand = (brandId) => {
    localFilters.value.brand = brandId;
    applyFilters();
};

const toggleOnSale = () => {
    localFilters.value.on_sale = !localFilters.value.on_sale;
    applyFilters();
};

// Computed
const hasActiveFilters = computed(() => {
    return localFilters.value.search ||
           localFilters.value.category ||
           localFilters.value.brand ||
           localFilters.value.min_price ||
           localFilters.value.max_price ||
           localFilters.value.type ||
           localFilters.value.on_sale;
});
</script>

<template>
    <AppLayout title="Shop">
        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <!-- Header -->
                <div class="flex justify-between items-center mb-8">
                    <div>
                        <h1 class="text-3xl font-bold text-gray-900 dark:text-white">Shop</h1>
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                            {{ products.total }} products found
                        </p>
                    </div>

                    <div class="flex items-center gap-4">
                        <!-- View Toggle -->
                        <div class="flex border border-gray-300 dark:border-gray-600 rounded-lg">
                            <button
                                @click="viewMode = 'grid'"
                                :class="[
                                    'px-3 py-2 text-sm font-medium rounded-l-lg',
                                    viewMode === 'grid'
                                        ? 'bg-indigo-600 text-white'
                                        : 'bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700'
                                ]"
                            >
                                Grid
                            </button>
                            <button
                                @click="viewMode = 'list'"
                                :class="[
                                    'px-3 py-2 text-sm font-medium rounded-r-lg',
                                    viewMode === 'list'
                                        ? 'bg-indigo-600 text-white'
                                        : 'bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700'
                                ]"
                            >
                                List
                            </button>
                        </div>

                        <!-- Sort -->
                        <select
                            v-model="localFilters.sort"
                            @change="applyFilters"
                            class="border border-gray-300 dark:border-gray-600 rounded-lg px-4 py-2 text-sm bg-white dark:bg-gray-800 text-gray-900 dark:text-white"
                        >
                            <option value="newest">Newest</option>
                            <option value="price_low">Price: Low to High</option>
                            <option value="price_high">Price: High to Low</option>
                            <option value="name">Name: A-Z</option>
                            <option value="popularity">Most Popular</option>
                            <option value="rating">Highest Rated</option>
                        </select>
                    </div>
                </div>

                <div class="flex gap-8">
                    <!-- Sidebar Filters -->
                    <div class="w-64 flex-shrink-0">
                        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6 sticky top-4">
                            <div class="flex justify-between items-center mb-4">
                                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Filters</h2>
                                <button
                                    v-if="hasActiveFilters"
                                    @click="clearFilters"
                                    class="text-sm text-indigo-600 hover:text-indigo-700 dark:text-indigo-400"
                                >
                                    Clear All
                                </button>
                            </div>

                            <!-- Search -->
                            <div class="mb-6">
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Search
                                </label>
                                <input
                                    v-model="localFilters.search"
                                    @keyup.enter="applyFilters"
                                    type="text"
                                    placeholder="Search products..."
                                    class="w-full border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 text-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-white"
                                />
                            </div>

                            <!-- Categories -->
                            <div class="mb-6">
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Category
                                </label>
                                <select
                                    v-model="localFilters.category"
                                    @change="applyFilters"
                                    class="w-full border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 text-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-white"
                                >
                                    <option value="">All Categories</option>
                                    <option v-for="category in categories" :key="category.id" :value="category.id">
                                        {{ category.name }}
                                    </option>
                                </select>
                            </div>

                            <!-- Brands -->
                            <div class="mb-6">
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Brand
                                </label>
                                <select
                                    v-model="localFilters.brand"
                                    @change="applyFilters"
                                    class="w-full border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 text-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-white"
                                >
                                    <option value="">All Brands</option>
                                    <option v-for="brand in brands" :key="brand.id" :value="brand.id">
                                        {{ brand.name }}
                                    </option>
                                </select>
                            </div>

                            <!-- Price Range -->
                            <div class="mb-6">
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Price Range
                                </label>
                                <div class="flex gap-2">
                                    <input
                                        v-model.number="localFilters.min_price"
                                        type="number"
                                        placeholder="Min"
                                        class="w-full border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 text-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-white"
                                    />
                                    <input
                                        v-model.number="localFilters.max_price"
                                        type="number"
                                        placeholder="Max"
                                        class="w-full border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 text-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-white"
                                    />
                                </div>
                                <button
                                    @click="applyFilters"
                                    class="mt-2 w-full bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 px-4 py-2 rounded-lg text-sm hover:bg-gray-200 dark:hover:bg-gray-600"
                                >
                                    Apply Price
                                </button>
                            </div>

                            <!-- On Sale -->
                            <div class="mb-6">
                                <label class="flex items-center">
                                    <input
                                        v-model="localFilters.on_sale"
                                        @change="applyFilters"
                                        type="checkbox"
                                        class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                    />
                                    <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">On Sale</span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Products Grid/List -->
                    <div class="flex-1">
                        <div v-if="products.data.length === 0" class="text-center py-12">
                            <p class="text-gray-500 dark:text-gray-400">No products found</p>
                        </div>

                        <!-- Grid View -->
                        <div v-else-if="viewMode === 'grid'" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                            <div
                                v-for="product in products.data"
                                :key="product.id"
                                class="bg-white dark:bg-gray-800 rounded-lg shadow hover:shadow-lg transition-shadow"
                            >
                                <a :href="`/shop/${product.slug}`" class="block">
                                    <!-- Image -->
                                    <div class="aspect-square bg-gray-100 dark:bg-gray-700 rounded-t-lg overflow-hidden">
                                        <img
                                            :src="product.primary_image_url || 'https://via.placeholder.com/400'"
                                            :alt="product.name"
                                            class="w-full h-full object-cover"
                                        />
                                    </div>

                                    <!-- Content -->
                                    <div class="p-4">
                                        <div class="text-xs text-gray-500 dark:text-gray-400 mb-1">
                                            {{ product.category?.name }} • {{ product.brand?.name }}
                                        </div>
                                        <h3 class="font-semibold text-gray-900 dark:text-white mb-2 line-clamp-2">
                                            {{ product.name }}
                                        </h3>
                                        <p class="text-sm text-gray-600 dark:text-gray-400 mb-3 line-clamp-2">
                                            {{ product.short_description }}
                                        </p>

                                        <!-- Price -->
                                        <div class="flex items-center gap-2">
                                            <span class="text-lg font-bold text-gray-900 dark:text-white">
                                                {{ product.formatted_price }}
                                            </span>
                                            <span v-if="product.is_on_sale" class="text-sm text-gray-500 dark:text-gray-400 line-through">
                                                ${{ product.msrp }}
                                            </span>
                                            <span v-if="product.discount_percentage" class="text-xs font-semibold text-red-600 dark:text-red-400">
                                                -{{ product.discount_percentage }}%
                                            </span>
                                        </div>

                                        <!-- Stock Status -->
                                        <div class="mt-2">
                                            <span
                                                v-if="product.is_in_stock"
                                                class="text-xs text-green-600 dark:text-green-400"
                                            >
                                                In Stock
                                            </span>
                                            <span v-else class="text-xs text-red-600 dark:text-red-400">
                                                Out of Stock
                                            </span>
                                        </div>
                                    </div>
                                </a>
                            </div>
                        </div>

                        <!-- List View -->
                        <div v-else class="space-y-4">
                            <div
                                v-for="product in products.data"
                                :key="product.id"
                                class="bg-white dark:bg-gray-800 rounded-lg shadow hover:shadow-lg transition-shadow"
                            >
                                <a :href="`/shop/${product.slug}`" class="flex gap-4 p-4">
                                    <!-- Image -->
                                    <div class="w-32 h-32 bg-gray-100 dark:bg-gray-700 rounded-lg overflow-hidden flex-shrink-0">
                                        <img
                                            :src="product.primary_image_url || 'https://via.placeholder.com/200'"
                                            :alt="product.name"
                                            class="w-full h-full object-cover"
                                        />
                                    </div>

                                    <!-- Content -->
                                    <div class="flex-1">
                                        <div class="text-xs text-gray-500 dark:text-gray-400 mb-1">
                                            {{ product.category?.name }} • {{ product.brand?.name }}
                                        </div>
                                        <h3 class="font-semibold text-lg text-gray-900 dark:text-white mb-2">
                                            {{ product.name }}
                                        </h3>
                                        <p class="text-sm text-gray-600 dark:text-gray-400 mb-3">
                                            {{ product.short_description }}
                                        </p>

                                        <div class="flex items-center justify-between">
                                            <!-- Price -->
                                            <div class="flex items-center gap-2">
                                                <span class="text-xl font-bold text-gray-900 dark:text-white">
                                                    {{ product.formatted_price }}
                                                </span>
                                                <span v-if="product.is_on_sale" class="text-sm text-gray-500 dark:text-gray-400 line-through">
                                                    ${{ product.msrp }}
                                                </span>
                                                <span v-if="product.discount_percentage" class="text-sm font-semibold text-red-600 dark:text-red-400">
                                                    -{{ product.discount_percentage }}%
                                                </span>
                                            </div>

                                            <!-- Stock -->
                                            <span
                                                v-if="product.is_in_stock"
                                                class="text-sm text-green-600 dark:text-green-400"
                                            >
                                                In Stock
                                            </span>
                                            <span v-else class="text-sm text-red-600 dark:text-red-400">
                                                Out of Stock
                                            </span>
                                        </div>
                                    </div>
                                </a>
                            </div>
                        </div>

                        <!-- Pagination -->
                        <div v-if="products.data.length > 0" class="mt-8 flex justify-center">
                            <nav class="flex gap-2">
                                <a
                                    v-for="link in products.links"
                                    :key="link.label"
                                    :href="link.url"
                                    :class="[
                                        'px-4 py-2 rounded-lg text-sm',
                                        link.active
                                            ? 'bg-indigo-600 text-white'
                                            : 'bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700',
                                        !link.url && 'opacity-50 cursor-not-allowed'
                                    ]"
                                    v-html="link.label"
                                />
                            </nav>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>