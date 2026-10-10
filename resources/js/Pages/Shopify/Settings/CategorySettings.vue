<script setup>
import { ref, computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    integrationStatus: { type: Object, default: () => ({}) },
    settings: { type: Object, default: () => ({}) },
    mappings: { type: Array, default: () => [] },
    categories: { type: Array, default: () => [] },
});

const collections = ref([]);
const loadingCollections = ref(false);
const collectionsLoaded = ref(false);

const form = useForm({
    product_type_source: props.settings.product_type_source || 'internal_category',
    collection_mapping_enabled: props.settings.collection_mapping_enabled || false,
    mappings: props.mappings.map(m => ({
        category_id: m.category_id || '',
        shopify_collection_id: m.shopify_collection_id,
        shopify_collection_title: m.shopify_collection_title,
        collection_type: m.collection_type,
        is_default: m.is_default,
    })),
});

const productTypeSources = [
    {
        value: 'internal_category',
        label: 'Internal Category',
        description: 'Uses your internal category name as the Shopify product type.',
    },
    {
        value: 'ebay_category',
        label: 'eBay Category',
        description: 'Uses the eBay listing category name. Falls back to internal category if no eBay listing exists.',
    },
    {
        value: 'none',
        label: 'None',
        description: 'Leaves the product type empty on Shopify.',
    },
];

const fetchCollections = async (refresh = false) => {
    loadingCollections.value = true;
    try {
        const response = await fetch(route('marketplaces.shopify.collections', { refresh: refresh ? 1 : 0 }));
        const data = await response.json();
        collections.value = data.collections || [];
        collectionsLoaded.value = true;
    } catch (e) {
        // Silently handle
    } finally {
        loadingCollections.value = false;
    }
};

const addMapping = () => {
    form.mappings.push({
        category_id: '',
        shopify_collection_id: '',
        shopify_collection_title: '',
        collection_type: 'custom',
        is_default: false,
    });
};

const removeMapping = (index) => {
    form.mappings.splice(index, 1);
};

const onCollectionChange = (index) => {
    const mapping = form.mappings[index];
    const selected = collections.value.find(c => c.id === mapping.shopify_collection_id);
    if (selected) {
        mapping.shopify_collection_title = selected.title;
        mapping.collection_type = selected.type;
    }
};

const categoryName = (id) => {
    if (!id) return 'Default (all unmapped)';
    const cat = props.categories.find(c => c.id === id);
    return cat ? cat.name : 'Unknown';
};

const save = () => {
    form.post(route('marketplaces.shopify.category-settings.update'), {
        preserveScroll: true,
    });
};

// Load collections when mapping is enabled
if (form.collection_mapping_enabled) {
    fetchCollections();
}
</script>

<template>
    <AppLayout title="Category & Collection Settings">
        <Head title="Shopify - Category & Collections" />

        <div class="max-w-4xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
            <!-- Header -->
            <div class="mb-6">
                <div class="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400 mb-2">
                    <Link :href="route('marketplaces.shopify.index')" class="hover:text-gray-700 dark:hover:text-gray-200">Shopify</Link>
                    <span>/</span>
                    <span>Category & Collections</span>
                </div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Category & Collection Settings</h1>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                    Control how product types are set and assign products to Shopify collections during sync.
                </p>
            </div>

            <!-- Product Type Source -->
            <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-6 mb-6">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Product Type Source</h2>
                <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                    Choose what value is used for the "product_type" field when syncing products to Shopify.
                </p>

                <div class="space-y-3">
                    <label
                        v-for="source in productTypeSources"
                        :key="source.value"
                        class="flex items-start gap-3 p-3 rounded-lg border cursor-pointer transition-colors"
                        :class="form.product_type_source === source.value
                            ? 'border-green-500 bg-green-50 dark:bg-green-900/20 dark:border-green-600'
                            : 'border-gray-200 dark:border-gray-600 hover:border-gray-300 dark:hover:border-gray-500'"
                    >
                        <input
                            type="radio"
                            v-model="form.product_type_source"
                            :value="source.value"
                            class="mt-0.5 text-green-600 focus:ring-green-500"
                        />
                        <div>
                            <span class="block text-sm font-medium text-gray-900 dark:text-white">{{ source.label }}</span>
                            <span class="block text-sm text-gray-500 dark:text-gray-400">{{ source.description }}</span>
                        </div>
                    </label>
                </div>
            </div>

            <!-- Collection Assignment -->
            <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-6 mb-6">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Collection Assignment</h2>
                        <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                            Automatically assign products to Shopify collections based on their category when syncing.
                        </p>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input
                            type="checkbox"
                            v-model="form.collection_mapping_enabled"
                            class="sr-only peer"
                            @change="form.collection_mapping_enabled && !collectionsLoaded && fetchCollections()"
                        />
                        <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-green-300 dark:peer-focus:ring-green-800 rounded-full peer dark:bg-gray-600 peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:after:border-gray-500 peer-checked:bg-green-600"></div>
                    </label>
                </div>

                <div v-if="form.collection_mapping_enabled">
                    <!-- Toolbar -->
                    <div class="flex items-center justify-between mb-4">
                        <button
                            @click="addMapping"
                            type="button"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium text-green-700 dark:text-green-400 bg-green-50 dark:bg-green-900/20 rounded-lg hover:bg-green-100 dark:hover:bg-green-900/40"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
                            Add Mapping
                        </button>
                        <button
                            @click="fetchCollections(true)"
                            type="button"
                            :disabled="loadingCollections"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200 disabled:opacity-50"
                        >
                            <svg class="w-4 h-4" :class="{ 'animate-spin': loadingCollections }" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" /></svg>
                            Refresh Collections
                        </button>
                    </div>

                    <!-- Loading state -->
                    <div v-if="loadingCollections && !collectionsLoaded" class="text-center py-8 text-sm text-gray-500 dark:text-gray-400">
                        Loading Shopify collections...
                    </div>

                    <!-- Empty state -->
                    <div v-else-if="form.mappings.length === 0" class="text-center py-8 border-2 border-dashed border-gray-200 dark:border-gray-600 rounded-lg">
                        <p class="text-sm text-gray-500 dark:text-gray-400 mb-2">No mappings configured</p>
                        <p class="text-xs text-gray-400 dark:text-gray-500">Click "Add Mapping" to map a category to a Shopify collection.</p>
                    </div>

                    <!-- Mapping rows -->
                    <div v-else class="space-y-3">
                        <div
                            v-for="(mapping, index) in form.mappings"
                            :key="index"
                            class="flex items-start gap-3 p-3 bg-gray-50 dark:bg-gray-700/50 rounded-lg"
                        >
                            <!-- Category select -->
                            <div class="flex-1">
                                <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Category</label>
                                <select
                                    v-model="mapping.category_id"
                                    class="w-full text-sm px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100"
                                >
                                    <option value="">-- Default (all unmapped) --</option>
                                    <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
                                </select>
                            </div>

                            <!-- Arrow -->
                            <div class="pt-7 text-gray-400">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" /></svg>
                            </div>

                            <!-- Collection select -->
                            <div class="flex-1">
                                <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Shopify Collection</label>
                                <select
                                    v-model="mapping.shopify_collection_id"
                                    @change="onCollectionChange(index)"
                                    class="w-full text-sm px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100"
                                >
                                    <option value="">-- Select Collection --</option>
                                    <option v-for="col in collections" :key="col.id" :value="col.id">
                                        {{ col.title }} ({{ col.type }})
                                    </option>
                                    <!-- Show the saved value if collections not yet loaded -->
                                    <option
                                        v-if="mapping.shopify_collection_id && !collections.find(c => c.id === mapping.shopify_collection_id)"
                                        :value="mapping.shopify_collection_id"
                                    >
                                        {{ mapping.shopify_collection_title }} (saved)
                                    </option>
                                </select>
                            </div>

                            <!-- Default checkbox -->
                            <div class="pt-6">
                                <label class="inline-flex items-center gap-1.5 text-xs text-gray-600 dark:text-gray-400 cursor-pointer">
                                    <input
                                        type="checkbox"
                                        v-model="mapping.is_default"
                                        class="rounded border-gray-300 dark:border-gray-600 text-green-600 focus:ring-green-500"
                                        @change="mapping.is_default && (mapping.category_id = '')"
                                    />
                                    Default
                                </label>
                            </div>

                            <!-- Remove button -->
                            <div class="pt-6">
                                <button
                                    @click="removeMapping(index)"
                                    type="button"
                                    class="p-1.5 text-gray-400 hover:text-red-500 dark:hover:text-red-400"
                                >
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Save -->
            <div class="flex items-center justify-between">
                <Link
                    :href="route('marketplaces.shopify.index')"
                    class="text-sm text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200"
                >
                    Back to Shopify Settings
                </Link>
                <button
                    @click="save"
                    :disabled="form.processing"
                    class="px-6 py-2 bg-green-600 hover:bg-green-700 disabled:opacity-50 text-white font-medium rounded-lg text-sm"
                >
                    {{ form.processing ? 'Saving...' : 'Save Settings' }}
                </button>
            </div>

            <!-- Success message -->
            <div v-if="$page.props.flash?.success" class="mt-4 p-3 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg text-sm text-green-700 dark:text-green-400">
                {{ $page.props.flash.success }}
            </div>
        </div>
    </AppLayout>
</template>
