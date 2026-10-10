<script setup>
import { computed } from 'vue';

const props = defineProps({
    products: {
        type: Array,
        default: () => [],
    },
});

function formatCurrency(amount) {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD',
    }).format(amount);
}

function formatNumber(num) {
    return new Intl.NumberFormat('en-US').format(num);
}

function getPerformanceColor(index) {
    if (index < 3) return 'text-emerald-600 dark:text-emerald-400';
    if (index < 6) return 'text-amber-600 dark:text-amber-400';
    return 'text-gray-600 dark:text-gray-400';
}

function getRankBadgeClass(index) {
    const classes = {
        0: 'bg-gradient-to-r from-yellow-400 to-amber-500 text-white',
        1: 'bg-gradient-to-r from-gray-300 to-gray-400 text-gray-800',
        2: 'bg-gradient-to-r from-amber-600 to-orange-700 text-white',
    };
    return classes[index] || 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-400';
}
</script>

<template>
    <div class="overflow-hidden">
        <!-- Empty State -->
        <div 
            v-if="!products || products.length === 0"
            class="text-center py-12"
        >
            <svg 
                class="mx-auto h-12 w-12 text-gray-400 dark:text-gray-500"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
            >
                <path 
                    stroke-linecap="round" 
                    stroke-linejoin="round" 
                    stroke-width="2" 
                    d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"
                />
            </svg>
            <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">No products found</h3>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Start adding products to see performance data.</p>
        </div>

        <!-- Table -->
        <div v-else class="overflow-x-auto">
            <table class="min-w-full">
                <thead>
                    <tr class="border-b border-gray-100 dark:border-gray-700">
                        <th class="py-3 px-4 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            Rank
                        </th>
                        <th class="py-3 px-4 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            Product
                        </th>
                        <th class="py-3 px-4 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            Category
                        </th>
                        <th class="py-3 px-4 text-right text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            Sales
                        </th>
                        <th class="py-3 px-4 text-right text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            Revenue
                        </th>
                        <th class="py-3 px-4 text-right text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            Profit
                        </th>
                        <th class="py-3 px-4 text-right text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            Stock
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50 dark:divide-gray-700/50">
                    <tr 
                        v-for="(product, index) in products" 
                        :key="product.id"
                        class="group hover:bg-gray-50 dark:hover:bg-gray-700/30 transition-colors"
                    >
                        <td class="py-4 px-4">
                            <span 
                                class="inline-flex items-center justify-center w-7 h-7 rounded-full text-xs font-bold"
                                :class="getRankBadgeClass(index)"
                            >
                                {{ index + 1 }}
                            </span>
                        </td>
                        <td class="py-4 px-4">
                            <div class="flex items-center gap-3">
                                <div class="flex-shrink-0 w-10 h-10 bg-gray-100 dark:bg-gray-700 rounded-lg flex items-center justify-center">
                                    <svg class="w-5 h-5 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                                    </svg>
                                </div>
                                <div class="min-w-0">
                                    <p class="text-sm font-medium text-gray-900 dark:text-white truncate max-w-[200px]">
                                        {{ product.name }}
                                    </p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">
                                        {{ product.sku }}
                                    </p>
                                </div>
                            </div>
                        </td>
                        <td class="py-4 px-4">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300">
                                {{ product.category }}
                            </span>
                        </td>
                        <td class="py-4 px-4 text-right">
                            <span 
                                class="text-sm font-semibold"
                                :class="getPerformanceColor(index)"
                            >
                                {{ formatNumber(product.total_sales) }}
                            </span>
                        </td>
                        <td class="py-4 px-4 text-right">
                            <span class="text-sm font-medium text-gray-900 dark:text-white">
                                {{ formatCurrency(product.revenue) }}
                            </span>
                        </td>
                        <td class="py-4 px-4 text-right">
                            <span 
                                class="text-sm font-medium"
                                :class="product.profit >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400'"
                            >
                                {{ formatCurrency(product.profit) }}
                            </span>
                        </td>
                        <td class="py-4 px-4 text-right">
                            <div class="flex items-center justify-end gap-2">
                                <span 
                                    class="text-sm text-gray-600 dark:text-gray-300"
                                >
                                    {{ formatNumber(product.stock_quantity) }}
                                </span>
                                <span 
                                    v-if="product.stock_quantity <= 5"
                                    class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400"
                                >
                                    Low
                                </span>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
