<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    links: {
        type: Array,
        required: true,
    },
});
</script>

<template>
    <nav v-if="links.length > 3" class="flex items-center justify-between px-4 py-3 bg-white border border-gray-200 rounded-lg dark:bg-gray-800 dark:border-gray-700 sm:px-6">
        <div class="flex justify-between flex-1 sm:hidden">
            <Link
                v-if="links[0].url"
                :href="links[0].url"
                class="relative inline-flex items-center px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 dark:bg-gray-700 dark:text-gray-200 dark:border-gray-600"
            >
                Previous
            </Link>
            <span
                v-else
                class="relative inline-flex items-center px-4 py-2 text-sm font-medium text-gray-400 bg-white border border-gray-300 rounded-md cursor-not-allowed dark:bg-gray-700 dark:border-gray-600"
            >
                Previous
            </span>
            <Link
                v-if="links[links.length - 1].url"
                :href="links[links.length - 1].url"
                class="relative inline-flex items-center px-4 py-2 ml-3 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 dark:bg-gray-700 dark:text-gray-200 dark:border-gray-600"
            >
                Next
            </Link>
            <span
                v-else
                class="relative inline-flex items-center px-4 py-2 ml-3 text-sm font-medium text-gray-400 bg-white border border-gray-300 rounded-md cursor-not-allowed dark:bg-gray-700 dark:border-gray-600"
            >
                Next
            </span>
        </div>
        <div class="hidden sm:flex sm:flex-1 sm:items-center sm:justify-center">
            <div>
                <nav class="relative z-0 inline-flex -space-x-px rounded-md shadow-sm" aria-label="Pagination">
                    <template v-for="(link, key) in links" :key="key">
                        <!-- Previous Button -->
                        <template v-if="key === 0">
                            <Link
                                v-if="link.url"
                                :href="link.url"
                                class="relative inline-flex items-center px-2 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-l-md hover:bg-gray-50 dark:bg-gray-700 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-600"
                            >
                                <span class="sr-only">Previous</span>
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                                </svg>
                            </Link>
                            <span
                                v-else
                                class="relative inline-flex items-center px-2 py-2 text-sm font-medium text-gray-300 bg-white border border-gray-300 cursor-not-allowed rounded-l-md dark:bg-gray-700 dark:text-gray-500 dark:border-gray-600"
                            >
                                <span class="sr-only">Previous</span>
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                                </svg>
                            </span>
                        </template>

                        <!-- Next Button -->
                        <template v-else-if="key === links.length - 1">
                            <Link
                                v-if="link.url"
                                :href="link.url"
                                class="relative inline-flex items-center px-2 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-r-md hover:bg-gray-50 dark:bg-gray-700 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-600"
                            >
                                <span class="sr-only">Next</span>
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                </svg>
                            </Link>
                            <span
                                v-else
                                class="relative inline-flex items-center px-2 py-2 text-sm font-medium text-gray-300 bg-white border border-gray-300 cursor-not-allowed rounded-r-md dark:bg-gray-700 dark:text-gray-500 dark:border-gray-600"
                            >
                                <span class="sr-only">Next</span>
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                </svg>
                            </span>
                        </template>

                        <!-- Page Numbers -->
                        <template v-else>
                            <!-- Ellipsis -->
                            <span
                                v-if="link.label === '...'"
                                class="relative inline-flex items-center px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 dark:bg-gray-700 dark:text-gray-300 dark:border-gray-600"
                            >
                                ...
                            </span>
                            
                            <!-- Active Page -->
                            <span
                                v-else-if="link.active"
                                class="relative z-10 inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-indigo-600 border border-indigo-600"
                            >
                                {{ link.label }}
                            </span>
                            
                            <!-- Inactive Page Link -->
                            <Link
                                v-else-if="link.url"
                                :href="link.url"
                                class="relative inline-flex items-center px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 hover:bg-gray-50 dark:bg-gray-700 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-600"
                            >
                                {{ link.label }}
                            </Link>
                            
                            <!-- Disabled Page -->
                            <span
                                v-else
                                class="relative inline-flex items-center px-4 py-2 text-sm font-medium text-gray-400 bg-white border border-gray-300 cursor-not-allowed dark:bg-gray-700 dark:text-gray-500 dark:border-gray-600"
                            >
                                {{ link.label }}
                            </span>
                        </template>
                    </template>
                </nav>
            </div>
        </div>
    </nav>
</template>
