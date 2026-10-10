<script setup>
import { ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import TextInput from '@/Components/TextInput.vue';

const props = defineProps({
    templates: Object,
    filters: Object,
});

const search = ref(props.filters?.search || '');
const platform = ref(props.filters?.platform || '');

let searchTimeout = null;

const applyFilters = () => {
    router.get(route('inventory.templates.index'), {
        search: search.value || undefined,
        platform: platform.value || undefined,
    }, { preserveState: true, preserveScroll: true });
};

watch(search, () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(applyFilters, 300);
});

watch(platform, applyFilters);

const deleteTemplate = (template) => {
    if (confirm(`Delete template "${template.name}"?`)) {
        router.delete(route('inventory.templates.destroy', template.id));
    }
};

const strategyLabel = (strategy) => {
    return {
        fixed_markup: 'Fixed Markup',
        percentage: 'Percentage Markup',
        competitive: 'Competitive',
    }[strategy] || '--';
};

const platformLabel = (platform) => {
    return { ebay: 'eBay', amazon: 'Amazon', all: 'All Platforms' }[platform] || platform;
};
</script>

<template>
    <AppLayout title="Listing Templates">
        <Head title="Listing Templates" />

        <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
            <!-- Header -->
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Listing Templates</h1>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                        Reusable templates for marketplace listings
                    </p>
                </div>
                <Link
                    :href="route('inventory.templates.create')"
                    class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150"
                >
                    Create Template
                </Link>
            </div>

            <!-- Filters -->
            <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-4 mb-6">
                <div class="flex flex-col sm:flex-row gap-4">
                    <div class="flex-1">
                        <TextInput v-model="search" type="text" placeholder="Search templates..." class="w-full" />
                    </div>
                    <div>
                        <select v-model="platform" class="block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600">
                            <option value="">All Platforms</option>
                            <option value="ebay">eBay</option>
                            <option value="amazon">Amazon</option>
                            <option value="all">All Platforms</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Templates Grid -->
            <div v-if="templates.data?.length" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <div
                    v-for="template in templates.data"
                    :key="template.id"
                    class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-6 hover:shadow-md transition-shadow"
                >
                    <div class="flex items-start justify-between mb-3">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">{{ template.name }}</h3>
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400 mt-1">
                                {{ platformLabel(template.platform) }}
                            </span>
                        </div>
                        <div class="flex items-center gap-1">
                            <Link
                                :href="route('inventory.templates.edit', template.id)"
                                class="p-1.5 text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-400"
                                title="Edit"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                            </Link>
                            <button
                                @click="deleteTemplate(template)"
                                class="p-1.5 text-gray-400 hover:text-red-600 dark:hover:text-red-400"
                                title="Delete"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                            </button>
                        </div>
                    </div>

                    <p v-if="template.description" class="text-sm text-gray-600 dark:text-gray-400 mb-4 line-clamp-2">
                        {{ template.description }}
                    </p>

                    <div class="space-y-2 text-sm">
                        <div class="flex justify-between">
                            <span class="text-gray-500 dark:text-gray-400">Pricing</span>
                            <span class="text-gray-900 dark:text-white">
                                <template v-if="template.pricing_strategy === 'percentage'">{{ template.markup_percentage }}% markup</template>
                                <template v-else-if="template.pricing_strategy === 'fixed_markup'">${{ Number(template.fixed_markup_amount).toFixed(2) }} markup</template>
                                <template v-else>{{ strategyLabel(template.pricing_strategy) }}</template>
                            </span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500 dark:text-gray-400">Times Used</span>
                            <span class="text-gray-900 dark:text-white">{{ template.times_used }}</span>
                        </div>
                        <div v-if="template.creator" class="flex justify-between">
                            <span class="text-gray-500 dark:text-gray-400">Created By</span>
                            <span class="text-gray-900 dark:text-white">{{ template.creator.name }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Empty State -->
            <div v-else class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-12 text-center">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">No templates</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Create a template to streamline your listing workflow.</p>
                <div class="mt-4">
                    <Link
                        :href="route('inventory.templates.create')"
                        class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700"
                    >
                        Create Template
                    </Link>
                </div>
            </div>

            <!-- Pagination -->
            <div v-if="templates.last_page > 1" class="mt-6 flex justify-center">
                <div class="flex gap-2">
                    <Link v-if="templates.prev_page_url" :href="templates.prev_page_url" class="px-3 py-1 text-sm border border-gray-300 dark:border-gray-600 rounded hover:bg-gray-50 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-300" preserve-state>Previous</Link>
                    <Link v-if="templates.next_page_url" :href="templates.next_page_url" class="px-3 py-1 text-sm border border-gray-300 dark:border-gray-600 rounded hover:bg-gray-50 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-300" preserve-state>Next</Link>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
