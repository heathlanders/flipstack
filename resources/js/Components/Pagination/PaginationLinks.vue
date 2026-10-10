<script setup>
import { computed } from 'vue';
import { router } from '@inertiajs/vue3';

const props = defineProps({
    links: {
        type: Array,
        required: true
    }
});

const paginationLinks = computed(() => {
    return props.links.filter((link, index, array) => {
        // Remove first and last if they're Previous/Next without URLs
        if (index === 0 && link.label.includes('Previous') && !link.url) return false;
        if (index === array.length - 1 && link.label.includes('Next') && !link.url) return false;
        return true;
    });
});

const navigate = (url) => {
    if (url) {
        router.get(url, {}, {
            preserveState: true,
            preserveScroll: true,
            replace: false
        });
    }
};

const getPageNumber = (label) => {
    // Extract number from label, handling "Previous" and "Next"
    if (label.includes('Previous')) return '‹';
    if (label.includes('Next')) return '›';
    return label.replace(/&[^;]+;/g, ''); // Remove HTML entities
};
</script>

<template>
    <nav v-if="paginationLinks.length > 3" class="flex items-center justify-between">
        <!-- Mobile pagination info -->
        <div class="flex flex-1 justify-between sm:hidden">
            <button
                v-for="link in paginationLinks"
                :key="link.label"
                @click="navigate(link.url)"
                :disabled="!link.url"
                :class="[
                    'relative inline-flex items-center px-4 py-2 text-sm font-medium rounded-md transition-colors',
                    link.active
                        ? 'bg-blue-600 text-white'
                        : link.url
                        ? 'bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 border border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700'
                        : 'bg-gray-100 dark:bg-gray-700 text-gray-400 dark:text-gray-600 cursor-not-allowed'
                ]"
                v-if="link.label.includes('Previous') || link.label.includes('Next')"
                v-html="getPageNumber(link.label)"
            ></button>
        </div>

        <!-- Desktop pagination -->
        <div class="hidden sm:flex sm:flex-1 sm:items-center sm:justify-center">
            <div class="flex items-center space-x-1">
                <button
                    v-for="link in paginationLinks"
                    :key="link.label"
                    @click="navigate(link.url)"
                    :disabled="!link.url"
                    :class="[
                        'relative inline-flex items-center justify-center w-10 h-10 text-sm font-medium rounded-lg transition-all duration-200',
                        link.active
                            ? 'bg-blue-600 text-white shadow-sm'
                            : link.url
                            ? 'bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 border border-gray-300 dark:border-gray-600 hover:bg-blue-50 dark:hover:bg-gray-700 hover:border-blue-300 dark:hover:border-gray-500'
                            : 'bg-gray-100 dark:bg-gray-700 text-gray-400 dark:text-gray-600 cursor-not-allowed border border-gray-200 dark:border-gray-600'
                    ]"
                >
                    <span v-html="getPageNumber(link.label)"></span>
                </button>
            </div>
        </div>

        <!-- Page info -->
        <div class="hidden sm:block">
            <p class="text-sm text-gray-700 dark:text-gray-300">
                Showing page {{ paginationLinks.find(link => link.active)?.label || '1' }}
            </p>
        </div>
    </nav>
</template>