<script setup>
import { ref } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    product: Object,
    relatedProducts: Array,
});

// Selected image
const selectedImage = ref(props.product.images?.[0] || {
    url: props.product.featured_image || 'https://via.placeholder.com/600',
    alt_text: props.product.name
});

const selectImage = (image) => {
    selectedImage.value = image;
};
</script>

<template>
    <AppLayout :title="product.name">
        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <!-- Breadcrumb -->
                <nav class="mb-8 text-sm">
                    <ol class="flex items-center gap-2 text-gray-600 dark:text-gray-400">
                        <li><a href="/shop" class="hover:text-indigo-600 dark:hover:text-indigo-400">Shop</a></li>
                        <li>/</li>
                        <li v-if="product.category">
                            <a href="#" class="hover:text-indigo-600 dark:hover:text-indigo-400">
                                {{ product.category.name }}
                            </a>
                        </li>
                        <li>/</li>
                        <li class="text-gray-900 dark:text-white">{{ product.name }}</li>
                    </ol>
                </nav>

                <!-- Product Detail -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-12">
                    <!-- Image Gallery -->
                    <div>
                        <!-- Main Image -->
                        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg overflow-hidden mb-4">
                            <img
                                :src="selectedImage.url"
                                :alt="selectedImage.alt_text || product.name"
                                class="w-full aspect-square object-cover"
                            />
                        </div>

                        <!-- Thumbnail Gallery -->
                        <div v-if="product.images && product.images.length > 1" class="grid grid-cols-4 gap-2">
                            <button
                                v-for="image in product.images"
                                :key="image.id"
                                @click="selectImage(image)"
                                :class="[
                                    'bg-white dark:bg-gray-800 rounded-lg overflow-hidden border-2 transition-colors',
                                    selectedImage.id === image.id
                                        ? 'border-indigo-600'
                                        : 'border-transparent hover:border-gray-300 dark:hover:border-gray-600'
                                ]"
                            >
                                <img
                                    :src="image.thumbnail_url || image.url"
                                    :alt="image.alt_text"
                                    class="w-full aspect-square object-cover"
                                />
                            </button>
                        </div>
                    </div>

                    <!-- Product Info -->
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-8">
                        <!-- Brand & Category -->
                        <div class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400 mb-2">
                            <span v-if="product.brand">{{ product.brand.name }}</span>
                            <span v-if="product.brand && product.category">•</span>
                            <span v-if="product.category">{{ product.category.name }}</span>
                        </div>

                        <!-- Product Name -->
                        <h1 class="text-3xl font-bold text-gray-900 dark:text-white mb-4">
                            {{ product.name }}
                        </h1>

                        <!-- Short Description -->
                        <p class="text-lg text-gray-600 dark:text-gray-400 mb-6">
                            {{ product.short_description }}
                        </p>

                        <!-- Price -->
                        <div class="mb-6">
                            <div class="flex items-baseline gap-3">
                                <span class="text-4xl font-bold text-gray-900 dark:text-white">
                                    {{ product.formatted_price }}
                                </span>
                                <span v-if="product.is_on_sale" class="text-xl text-gray-500 dark:text-gray-400 line-through">
                                    ${{ product.msrp }}
                                </span>
                            </div>
                            <div v-if="product.discount_percentage" class="mt-2">
                                <span class="inline-block bg-red-100 dark:bg-red-900 text-red-800 dark:text-red-200 px-3 py-1 rounded-full text-sm font-semibold">
                                    Save {{ product.discount_percentage }}%
                                </span>
                            </div>
                        </div>

                        <!-- Stock Status -->
                        <div class="mb-6">
                            <span
                                v-if="product.is_in_stock"
                                class="inline-flex items-center gap-2 text-green-600 dark:text-green-400"
                            >
                                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                </svg>
                                In Stock
                                <span v-if="product.stock_quantity && product.manage_stock" class="text-gray-600 dark:text-gray-400">
                                    ({{ product.stock_quantity }} available)
                                </span>
                            </span>
                            <span v-else class="inline-flex items-center gap-2 text-red-600 dark:text-red-400">
                                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                                </svg>
                                Out of Stock
                            </span>
                        </div>

                        <!-- SKU & Barcode -->
                        <div class="border-t border-gray-200 dark:border-gray-700 pt-6 mb-6">
                            <dl class="grid grid-cols-2 gap-4 text-sm">
                                <div>
                                    <dt class="text-gray-600 dark:text-gray-400">SKU</dt>
                                    <dd class="font-medium text-gray-900 dark:text-white">{{ product.sku }}</dd>
                                </div>
                                <div v-if="product.barcode">
                                    <dt class="text-gray-600 dark:text-gray-400">Barcode</dt>
                                    <dd class="font-medium text-gray-900 dark:text-white">{{ product.barcode }}</dd>
                                </div>
                            </dl>
                        </div>

                        <!-- Back to Shop Button -->
                        <div class="border-t border-gray-200 dark:border-gray-700 pt-6">
                            <a
                                href="/shop"
                                class="inline-flex items-center gap-2 text-indigo-600 hover:text-indigo-700 dark:text-indigo-400 dark:hover:text-indigo-300"
                            >
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                                </svg>
                                Back to Shop
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Product Description -->
                <div v-if="product.description" class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-8 mb-12">
                    <h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-4">Description</h2>
                    <div class="prose dark:prose-invert max-w-none">
                        <p class="text-gray-600 dark:text-gray-400 whitespace-pre-line">{{ product.description }}</p>
                    </div>
                </div>

                <!-- Product Specifications -->
                <div v-if="product.weight || product.length || product.width || product.height" class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-8 mb-12">
                    <h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-4">Specifications</h2>
                    <dl class="grid grid-cols-2 md:grid-cols-4 gap-4">
                        <div v-if="product.weight">
                            <dt class="text-sm text-gray-600 dark:text-gray-400">Weight</dt>
                            <dd class="text-lg font-medium text-gray-900 dark:text-white">{{ product.weight }} lbs</dd>
                        </div>
                        <div v-if="product.length">
                            <dt class="text-sm text-gray-600 dark:text-gray-400">Length</dt>
                            <dd class="text-lg font-medium text-gray-900 dark:text-white">{{ product.length }}"</dd>
                        </div>
                        <div v-if="product.width">
                            <dt class="text-sm text-gray-600 dark:text-gray-400">Width</dt>
                            <dd class="text-lg font-medium text-gray-900 dark:text-white">{{ product.width }}"</dd>
                        </div>
                        <div v-if="product.height">
                            <dt class="text-sm text-gray-600 dark:text-gray-400">Height</dt>
                            <dd class="text-lg font-medium text-gray-900 dark:text-white">{{ product.height }}"</dd>
                        </div>
                    </dl>
                </div>

                <!-- Related Products -->
                <div v-if="relatedProducts && relatedProducts.length > 0">
                    <h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-6">Related Products</h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                        <div
                            v-for="relatedProduct in relatedProducts"
                            :key="relatedProduct.id"
                            class="bg-white dark:bg-gray-800 rounded-lg shadow hover:shadow-lg transition-shadow"
                        >
                            <a :href="`/shop/${relatedProduct.slug}`" class="block">
                                <!-- Image -->
                                <div class="aspect-square bg-gray-100 dark:bg-gray-700 rounded-t-lg overflow-hidden">
                                    <img
                                        :src="relatedProduct.primary_image_url || 'https://via.placeholder.com/400'"
                                        :alt="relatedProduct.name"
                                        class="w-full h-full object-cover"
                                    />
                                </div>

                                <!-- Content -->
                                <div class="p-4">
                                    <h3 class="font-semibold text-gray-900 dark:text-white mb-2 line-clamp-2">
                                        {{ relatedProduct.name }}
                                    </h3>

                                    <!-- Price -->
                                    <div class="flex items-center gap-2">
                                        <span class="text-lg font-bold text-gray-900 dark:text-white">
                                            {{ relatedProduct.formatted_price }}
                                        </span>
                                        <span v-if="relatedProduct.is_on_sale" class="text-sm text-gray-500 dark:text-gray-400 line-through">
                                            ${{ relatedProduct.msrp }}
                                        </span>
                                    </div>
                                </div>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>