<script setup>
import { ref, computed, onMounted } from 'vue';
import axios from 'axios';
import { route } from 'ziggy-js';

const props = defineProps({
    queueItem: Object,
    form: Object,
});

const emit = defineEmits(['next', 'prev', 'save-draft']);

const activePreviewTab = ref('ebay'); // 'ebay' | 'amazon' | 'newegg'
const showTips = ref(true);

// Valid conditions for the preliminary eBay category
const validEbayConditions = ref([]);
const conditionsLoaded = ref(false);
const conditionsLoading = ref(false);

const researchData = computed(() =>
    props.queueItem.enriched_data?.research_data ||
    props.queueItem.enriched_data?.product_data?.gemini_research ||
    null
);

const titleCharLimit = computed(() => {
    switch (activePreviewTab.value) {
        case 'amazon':
            return 200;
        case 'ebay':
        default:
            return 80;
    }
});

const titleCharCount = computed(() => props.form.listing_title.length);

const titleColorClass = computed(() => {
    const percent = (titleCharCount.value / titleCharLimit.value) * 100;
    if (percent < 80) return 'text-green-600 dark:text-green-400';
    if (percent < 90) return 'text-yellow-600 dark:text-yellow-400';
    return 'text-red-600 dark:text-red-400';
});

const addBulletPoint = () => {
    props.form.bullet_points.push('');
};

const removeBulletPoint = (index) => {
    props.form.bullet_points.splice(index, 1);
};

const addKeyword = (keyword) => {
    if (keyword && !props.form.keywords.includes(keyword)) {
        props.form.keywords.push(keyword);
    }
};

const removeKeyword = (index) => {
    props.form.keywords.splice(index, 1);
};

const addItemSpecific = () => {
    const key = prompt('Enter specification name:');
    if (key && !props.form.item_specifics[key]) {
        props.form.item_specifics[key] = '';
    }
};

const removeItemSpecific = (key) => {
    delete props.form.item_specifics[key];
};

const flattenSpecs = (obj) => {
    const result = {};
    for (const [key, value] of Object.entries(obj)) {
        if (value && typeof value === 'object' && !Array.isArray(value)) {
            Object.assign(result, flattenSpecs(value));
        } else if (value != null && value !== '') {
            result[key] = String(value);
        }
    }
    return result;
};

const buildListingDescription = () => {
    const rd = researchData.value || {};
    const sections = [];

    const productName = rd.product_identity?.title || props.form.name || '';
    if (productName) {
        sections.push(`<h3>${productName}</h3>`);
    }

    const description = rd.listing_optimization?.description_template || rd.product_details?.description || '';
    if (description) {
        sections.push(`<p>${description}</p>`);
    }

    const bullets = rd.listing_optimization?.bullet_points || rd.product_details?.key_features || [];
    if (bullets.length > 0) {
        const items = bullets.map(b => `  <li>${b}</li>`).join('\n');
        sections.push(`<h4>Key Features</h4>\n<ul>\n${items}\n</ul>`);
    }

    const specs = rd.product_details?.specifications || {};
    const ebaySpecs = rd.listing_optimization?.platform_specific_tips?.ebay?.item_specifics || {};
    const allSpecs = flattenSpecs({ ...specs, ...ebaySpecs });
    const specEntries = Object.entries(allSpecs).filter(([, v]) => v);
    if (specEntries.length > 0) {
        const rows = specEntries.map(([k, v]) => `  <tr><td><strong>${k}</strong></td><td>${v}</td></tr>`).join('\n');
        sections.push(`<h4>Specifications</h4>\n<table>\n${rows}\n</table>`);
    }

    const packageContents = rd.product_details?.package_contents || [];
    if (packageContents.length > 0) {
        const items = packageContents.map(item => `  <li>${item}</li>`).join('\n');
        sections.push(`<h4>What's In The Box</h4>\n<ul>\n${items}\n</ul>`);
    }

    const warranty = rd.product_details?.warranty || '';
    if (warranty) {
        sections.push(`<h4>Warranty</h4>\n<p>${warranty}</p>`);
    }

    return sections.join('\n\n') || '';
};

const useAISuggestions = () => {
    if (researchData.value?.listing_optimization) {
        const opt = researchData.value.listing_optimization;

        if (opt.suggested_title) {
            props.form.listing_title = opt.suggested_title;
        }

        if (opt.bullet_points && opt.bullet_points.length > 0) {
            props.form.bullet_points = [...opt.bullet_points];
        } else if (researchData.value.product_details?.key_features?.length > 0) {
            props.form.bullet_points = [...researchData.value.product_details.key_features];
        }

        if (opt.primary_keywords && opt.primary_keywords.length > 0) {
            props.form.keywords = [...opt.primary_keywords];
        }

        // Merge item specifics
        const specs = researchData.value.product_details?.specifications || {};
        const ebaySpecs = opt.platform_specific_tips?.ebay?.item_specifics || {};
        const merged = flattenSpecs({ ...props.form.item_specifics, ...specs, ...ebaySpecs });
        props.form.item_specifics = merged;
    }

    // Rebuild description
    const desc = buildListingDescription();
    if (desc) {
        props.form.listing_description = desc;
    }
};

const fetchConditions = async () => {
    if (!props.queueItem?.id) return;
    conditionsLoading.value = true;
    try {
        const { data } = await axios.get(route('intake-queue.conditions', props.queueItem.id), {
            params: {
                title: props.form.listing_title || props.form.name || '',
                brand: props.form.brand || '',
                model: props.form.model || '',
                category_name: props.form.category_name || '',
            },
        });
        const stored = data.condition_validation_error;
        if (stored?.valid_conditions?.length) {
            validEbayConditions.value = stored.valid_conditions;
        } else {
            validEbayConditions.value = data.valid_conditions ?? [];
        }
    } catch {
        // silent — degrade gracefully
    } finally {
        conditionsLoaded.value = true;
        conditionsLoading.value = false;
        // Auto-correct to best valid condition within same family
        if (validEbayConditions.value.length && !isConditionValid(props.form.condition)) {
            const families = {
                new: ['NEW', 'NEW_OTHER', 'NEW_WITH_DEFECTS'],
                refurbished: ['CERTIFIED_REFURBISHED', 'MANUFACTURER_REFURBISHED'],
                used: ['USED_EXCELLENT', 'USED_VERY_GOOD', 'USED_GOOD', 'USED_ACCEPTABLE'],
                parts: ['FOR_PARTS_OR_NOT_WORKING'],
            };
            const currentFamily = Object.entries(families)
                .find(([, vals]) => vals.includes(props.form.condition))?.[0] ?? null;
            const sameFamilyMatch = currentFamily
                ? validEbayConditions.value.find(c => families[currentFamily]?.includes(c))
                : null;
            props.form.condition = sameFamilyMatch ?? validEbayConditions.value[0];
        }
    }
};

onMounted(async () => {
    if (!props.form.listing_title && researchData.value) {
        useAISuggestions();
    }

    await fetchConditions();
});

const conditionOptions = [
    { value: 'NEW', label: 'New - Never used, original packaging' },
    { value: 'NEW_OTHER', label: 'New (Other) - New but not in original packaging' },
    { value: 'NEW_WITH_DEFECTS', label: 'New with Defects' },
    { value: 'MANUFACTURER_REFURBISHED', label: 'Manufacturer Refurbished' },
    { value: 'CERTIFIED_REFURBISHED', label: 'Certified Refurbished' },
    { value: 'USED_EXCELLENT', label: 'Used - Excellent' },
    { value: 'USED_VERY_GOOD', label: 'Used - Very Good' },
    { value: 'USED_GOOD', label: 'Used - Good' },
    { value: 'USED_ACCEPTABLE', label: 'Used - Acceptable' },
    { value: 'FOR_PARTS_OR_NOT_WORKING', label: 'For Parts or Not Working' },
];

const filteredConditionOptions = computed(() => {
    if (!conditionsLoaded.value || !validEbayConditions.value.length) {
        return conditionOptions;
    }
    return conditionOptions.filter(opt => validEbayConditions.value.includes(opt.value));
});

const isConditionValid = (value) => {
    return !validEbayConditions.value.length || validEbayConditions.value.includes(value);
};

const currentConditionWarning = computed(() => {
    if (!conditionsLoaded.value) return null;
    if (!validEbayConditions.value.length) {
        return 'Could not load valid conditions for this eBay category. Verify the Category field above is correct or check your eBay integration.';
    }
    // After filtering, the selected condition should always be valid — this is a safety net
    if (!isConditionValid(props.form.condition)) {
        const validLabels = validEbayConditions.value
            .map(v => conditionOptions.find(o => o.value === v)?.label ?? v)
            .join(', ');
        return `This eBay category only supports: ${validLabels}`;
    }
    return null;
});
</script>

<template>
    <div class="p-6">
        <div class="mb-6">
            <h2 class="text-2xl font-bold text-gray-900 dark:text-gray-100">
                Step 3: Listing Verification
            </h2>
            <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                Create optimized listing content with live preview
            </p>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-5 gap-6">
            <!-- LEFT: Live Preview (60% width) -->
            <div class="lg:col-span-3 space-y-4">
                <!-- Platform Tabs -->
                <div class="flex gap-2 border-b border-gray-200 dark:border-gray-700">
                    <button
                        @click="activePreviewTab = 'ebay'"
                        :class="{
                            'border-b-2 border-indigo-600 text-indigo-600 dark:text-indigo-400': activePreviewTab === 'ebay',
                            'text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300': activePreviewTab !== 'ebay',
                        }"
                        class="px-4 py-2 font-medium text-sm transition"
                    >
                        eBay Preview
                    </button>
                    <button
                        @click="activePreviewTab = 'amazon'"
                        :class="{
                            'border-b-2 border-indigo-600 text-indigo-600 dark:text-indigo-400': activePreviewTab === 'amazon',
                            'text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300': activePreviewTab !== 'amazon',
                        }"
                        class="px-4 py-2 font-medium text-sm transition"
                    >
                        Amazon Preview
                    </button>
                    <button
                        @click="activePreviewTab = 'newegg'"
                        :class="{
                            'border-b-2 border-indigo-600 text-indigo-600 dark:text-indigo-400': activePreviewTab === 'newegg',
                            'text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300': activePreviewTab !== 'newegg',
                        }"
                        class="px-4 py-2 font-medium text-sm transition"
                    >
                        Newegg Preview
                    </button>
                </div>

                <!-- eBay Preview -->
                <div v-if="activePreviewTab === 'ebay'" class="bg-white dark:bg-gray-800 rounded-lg border border-gray-300 dark:border-gray-600 overflow-hidden">
                    <!-- eBay Header -->
                    <div class="bg-gray-50 dark:bg-gray-900 p-4 border-b border-gray-200 dark:border-gray-700">
                        <div class="flex items-center gap-2 mb-2">
                            <div class="w-8 h-8 bg-blue-600 rounded flex items-center justify-center text-white text-xs font-bold">
                                eB
                            </div>
                            <span class="text-xs text-gray-500 dark:text-gray-400">eBay Listing Preview</span>
                        </div>
                        <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">
                            {{ form.listing_title || 'Untitled Listing' }}
                        </h1>
                    </div>

                    <!-- Photos Carousel -->
                    <div class="p-4 bg-gray-100 dark:bg-gray-900">
                        <div class="flex gap-2 overflow-x-auto">
                            <img
                                v-for="(photo, index) in form.selected_photos.slice(0, 12)"
                                :key="index"
                                :src="photo"
                                class="w-16 h-16 object-cover rounded border-2 border-gray-300 dark:border-gray-600"
                            />
                        </div>
                    </div>

                    <!-- Price & Actions -->
                    <div class="p-4 border-b border-gray-200 dark:border-gray-700">
                        <div class="flex items-center justify-between">
                            <div>
                                <div class="text-xs text-gray-500 dark:text-gray-400 mb-1">Price</div>
                                <div class="text-3xl font-bold text-gray-900 dark:text-gray-100">
                                    ${{ form.list_price || '0.00' }}
                                </div>
                            </div>
                            <button class="px-6 py-3 bg-blue-600 text-white rounded-full font-bold hover:bg-blue-700 transition">
                                Buy It Now
                            </button>
                        </div>
                    </div>

                    <!-- Item Specifics -->
                    <div class="p-4 border-b border-gray-200 dark:border-gray-700">
                        <h3 class="text-sm font-bold text-gray-900 dark:text-gray-100 mb-3">Item Specifics</h3>
                        <div class="grid grid-cols-2 gap-3 text-xs">
                            <div>
                                <span class="text-gray-500 dark:text-gray-400">Condition:</span>
                                <span class="ml-2 font-medium text-gray-900 dark:text-gray-100">
                                    {{ conditionOptions.find(c => c.value === form.condition)?.label || 'New' }}
                                </span>
                            </div>
                            <div>
                                <span class="text-gray-500 dark:text-gray-400">Quantity:</span>
                                <span class="ml-2 font-medium text-gray-900 dark:text-gray-100">{{ form.quantity }}</span>
                            </div>
                            <div
                                v-for="(value, key) in form.item_specifics"
                                :key="key"
                            >
                                <span class="text-gray-500 dark:text-gray-400">{{ key }}:</span>
                                <span class="ml-2 font-medium text-gray-900 dark:text-gray-100">{{ value }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Description -->
                    <div class="p-4">
                        <h3 class="text-sm font-bold text-gray-900 dark:text-gray-100 mb-3">Description</h3>
                        <div class="text-sm text-gray-700 dark:text-gray-300 prose dark:prose-invert max-w-none" v-html="form.listing_description || 'No description provided'"></div>
                        
                        <!-- Bullet Points in Description -->
                        <div v-if="form.bullet_points.length > 0" class="mt-4">
                            <h4 class="font-bold text-gray-900 dark:text-gray-100 mb-2">Key Features:</h4>
                            <ul class="list-disc list-inside space-y-1 text-sm text-gray-700 dark:text-gray-300">
                                <li v-for="(point, index) in form.bullet_points" :key="index">
                                    {{ point }}
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- Amazon Preview -->
                <div v-else-if="activePreviewTab === 'amazon'" class="bg-white dark:bg-gray-800 rounded-lg border border-gray-300 dark:border-gray-600 overflow-hidden">
                    <!-- Amazon Header -->
                    <div class="bg-gray-900 p-3 flex items-center gap-2">
                        <div class="text-orange-400 font-bold text-lg">amazon</div>
                        <div class="flex-1"></div>
                        <div class="text-xs text-gray-400">Preview Mode</div>
                    </div>

                    <div class="p-6">
                        <div class="grid grid-cols-2 gap-6">
                            <!-- Images -->
                            <div>
                                <img
                                    :src="form.selected_photos[0]"
                                    class="w-full h-80 object-contain bg-gray-100 dark:bg-gray-900 rounded"
                                />
                                <div class="flex gap-2 mt-2">
                                    <img
                                        v-for="(photo, index) in form.selected_photos.slice(1, 5)"
                                        :key="index"
                                        :src="photo"
                                        class="w-12 h-12 object-cover rounded border border-gray-300"
                                    />
                                </div>
                            </div>

                            <!-- Product Info -->
                            <div>
                                <h1 class="text-2xl font-medium text-gray-900 dark:text-gray-100 mb-2">
                                    {{ form.listing_title || 'Untitled Product' }}
                                </h1>
                                
                                <div class="flex items-center gap-2 mb-4">
                                    <div class="flex text-orange-400">
                                        <span v-for="n in 5" :key="n">★</span>
                                    </div>
                                    <span class="text-sm text-blue-600">999 ratings</span>
                                </div>

                                <div class="border-t border-gray-200 dark:border-gray-700 pt-4">
                                    <div class="text-sm text-gray-600 dark:text-gray-400 mb-1">Price:</div>
                                    <div class="text-3xl text-red-700 dark:text-red-500 font-medium">
                                        ${{ form.list_price || '0.00' }}
                                    </div>
                                </div>

                                <div class="mt-4">
                                    <h3 class="font-bold text-gray-900 dark:text-gray-100 mb-2">About this item</h3>
                                    <ul class="space-y-2 text-sm text-gray-700 dark:text-gray-300">
                                        <li
                                            v-for="(point, index) in form.bullet_points"
                                            :key="index"
                                            class="flex items-start"
                                        >
                                            <span class="mr-2">•</span>
                                            <span>{{ point }}</span>
                                        </li>
                                    </ul>
                                </div>

                                <button class="w-full mt-6 bg-yellow-400 hover:bg-yellow-500 text-gray-900 py-2 rounded-full font-medium">
                                    Add to Cart
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Newegg Preview -->
                <div v-else-if="activePreviewTab === 'newegg'" class="bg-white dark:bg-gray-800 rounded-lg border border-gray-300 dark:border-gray-600 overflow-hidden">
                    <!-- Newegg Header -->
                    <div class="bg-orange-500 p-3 flex items-center">
                        <div class="text-white font-bold text-xl">Newegg</div>
                        <div class="flex-1"></div>
                        <div class="text-xs text-white opacity-75">Preview Mode</div>
                    </div>

                    <div class="p-6">
                        <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100 mb-4">
                            {{ form.listing_title || 'Untitled Product' }}
                        </h1>

                        <div class="grid grid-cols-2 gap-6">
                            <div>
                                <img
                                    :src="form.selected_photos[0]"
                                    class="w-full h-64 object-contain bg-gray-100 dark:bg-gray-900 rounded"
                                />
                            </div>

                            <div>
                                <div class="mb-4">
                                    <div class="text-2xl font-bold text-red-600">
                                        ${{ form.list_price || '0.00' }}
                                    </div>
                                </div>

                                <div class="bg-gray-50 dark:bg-gray-900 rounded p-4 mb-4">
                                    <h3 class="font-bold text-sm mb-2">Specifications</h3>
                                    <div class="space-y-1 text-xs">
                                        <div
                                            v-for="(value, key) in form.item_specifics"
                                            :key="key"
                                            class="flex justify-between"
                                        >
                                            <span class="text-gray-600 dark:text-gray-400">{{ key }}:</span>
                                            <span class="font-medium">{{ value }}</span>
                                        </div>
                                    </div>
                                </div>

                                <button class="w-full bg-orange-500 hover:bg-orange-600 text-white py-3 rounded font-bold">
                                    ADD TO CART
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- RIGHT: Content Editor (40% width) -->
            <div class="lg:col-span-2 space-y-4">
                <!-- AI Suggestions Button -->
                <button
                    @click="useAISuggestions"
                    class="w-full bg-gradient-to-r from-purple-600 to-indigo-600 hover:from-purple-700 hover:to-indigo-700 text-white px-4 py-3 rounded-lg font-medium transition flex items-center justify-center"
                >
                    <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M11 3a1 1 0 10-2 0v1a1 1 0 102 0V3zM15.657 5.757a1 1 0 00-1.414-1.414l-.707.707a1 1 0 001.414 1.414l.707-.707zM18 10a1 1 0 01-1 1h-1a1 1 0 110-2h1a1 1 0 011 1zM5.05 6.464A1 1 0 106.464 5.05l-.707-.707a1 1 0 00-1.414 1.414l.707.707zM5 10a1 1 0 01-1 1H3a1 1 0 110-2h1a1 1 0 011 1zM8 16v-1h4v1a2 2 0 11-4 0zM12 14c.015-.34.208-.646.477-.859a4 4 0 10-4.954 0c.27.213.462.519.476.859h4.002z"/>
                    </svg>
                    Use AI Suggestions
                </button>

                <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-4 space-y-4">
                    <!-- Title -->
                    <div>
                        <label class="flex items-center justify-between text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            <span>Listing Title *</span>
                            <span :class="titleColorClass" class="text-xs font-mono">
                                {{ titleCharCount }} / {{ titleCharLimit }}
                            </span>
                        </label>
                        <input
                            v-model="form.listing_title"
                            type="text"
                            :maxlength="titleCharLimit"
                            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm dark:bg-gray-900 dark:text-gray-100"
                            placeholder="Enter product title..."
                        />
                        
                        <!-- Title Variations -->
                        <div v-if="researchData?.listing_optimization?.title_variations" class="mt-2">
                            <div class="text-xs text-gray-500 dark:text-gray-400 mb-1">Suggested Variations:</div>
                            <div class="space-y-1">
                                <button
                                    v-for="(variation, index) in researchData.listing_optimization.title_variations.slice(0, 2)"
                                    :key="index"
                                    @click="form.listing_title = variation"
                                    class="w-full text-left px-2 py-1 text-xs bg-gray-50 dark:bg-gray-900 hover:bg-gray-100 dark:hover:bg-gray-800 rounded border border-gray-200 dark:border-gray-700"
                                >
                                    {{ variation }}
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Category -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Category
                        </label>
                        <input
                            v-model="form.category_name"
                            type="text"
                            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm dark:bg-gray-900 dark:text-gray-100"
                            placeholder="Electronics > Cameras"
                        />
                    </div>

                    <!-- Condition -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Condition *
                        </label>
                        <div
                            v-if="currentConditionWarning"
                            class="mb-2 p-2 bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-300 dark:border-yellow-600 rounded text-sm text-yellow-800 dark:text-yellow-300 flex items-start justify-between gap-2"
                        >
                            <span>{{ currentConditionWarning }}</span>
                            <button
                                v-if="!validEbayConditions.length"
                                @click="fetchConditions"
                                :disabled="conditionsLoading"
                                class="shrink-0 text-xs font-medium text-yellow-700 dark:text-yellow-400 hover:text-yellow-900 dark:hover:text-yellow-200 underline disabled:opacity-50 disabled:no-underline"
                            >
                                {{ conditionsLoading ? 'Loading...' : 'Retry' }}
                            </button>
                        </div>
                        <select
                            v-model="form.condition"
                            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm dark:bg-gray-900 dark:text-gray-100"
                        >
                            <option
                                v-for="option in filteredConditionOptions"
                                :key="option.value"
                                :value="option.value"
                            >
                                {{ option.label }}
                            </option>
                        </select>
                        
                        <textarea
                            v-if="form.condition !== 'new'"
                            v-model="form.condition_notes"
                            rows="2"
                            class="mt-2 w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm dark:bg-gray-900 dark:text-gray-100"
                            placeholder="Describe item condition..."
                        ></textarea>
                    </div>

                    <!-- Description -->
                    <div>
                        <label class="flex items-center justify-between text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            <span>Description</span>
                            <button
                                @click="form.listing_description = buildListingDescription()"
                                class="text-xs text-indigo-600 dark:text-indigo-400 hover:text-indigo-800 dark:hover:text-indigo-300"
                            >
                                Rebuild Description
                            </button>
                        </label>
                        <textarea
                            v-model="form.listing_description"
                            rows="6"
                            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm dark:bg-gray-900 dark:text-gray-100 font-mono"
                            placeholder="Detailed product description..."
                        ></textarea>
                    </div>

                    <!-- Bullet Points -->
                    <div>
                        <label class="flex items-center justify-between text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            <span>Key Features</span>
                            <button
                                @click="addBulletPoint"
                                class="text-xs text-indigo-600 dark:text-indigo-400 hover:text-indigo-800 dark:hover:text-indigo-300"
                            >
                                + Add Feature
                            </button>
                        </label>
                        <div class="space-y-2 max-h-64 overflow-y-auto">
                            <div
                                v-for="(point, index) in form.bullet_points"
                                :key="index"
                                class="flex gap-2"
                            >
                                <span class="text-gray-400 mt-2">•</span>
                                <input
                                    v-model="form.bullet_points[index]"
                                    type="text"
                                    class="flex-1 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm dark:bg-gray-900 dark:text-gray-100"
                                    placeholder="Feature description..."
                                />
                                <button
                                    @click="removeBulletPoint(index)"
                                    class="px-2 text-red-600 dark:text-red-400 hover:text-red-800 dark:hover:text-red-300"
                                >
                                    ×
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Keywords -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Keywords / Tags
                        </label>
                        <div class="flex flex-wrap gap-2 mb-2">
                            <span
                                v-for="(keyword, index) in form.keywords"
                                :key="index"
                                class="inline-flex items-center px-2 py-1 bg-indigo-100 text-indigo-800 dark:bg-indigo-900 dark:text-indigo-200 rounded text-xs"
                            >
                                {{ keyword }}
                                <button
                                    @click="removeKeyword(index)"
                                    class="ml-1 text-indigo-600 dark:text-indigo-400 hover:text-indigo-800 dark:hover:text-indigo-200"
                                >
                                    ×
                                </button>
                            </span>
                        </div>
                        
                        <!-- Suggested Keywords -->
                        <div v-if="researchData?.listing_optimization?.secondary_keywords" class="mb-2">
                            <div class="text-xs text-gray-500 dark:text-gray-400 mb-1">Suggested:</div>
                            <div class="flex flex-wrap gap-1">
                                <button
                                    v-for="(keyword, index) in researchData.listing_optimization.secondary_keywords.slice(0, 5)"
                                    :key="index"
                                    @click="addKeyword(keyword)"
                                    class="px-2 py-1 bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300 rounded text-xs hover:bg-gray-200 dark:hover:bg-gray-700"
                                >
                                    + {{ keyword }}
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Item Specifics -->
                    <div>
                        <label class="flex items-center justify-between text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            <span>Item Specifics</span>
                            <button
                                @click="addItemSpecific"
                                class="text-xs text-indigo-600 dark:text-indigo-400 hover:text-indigo-800 dark:hover:text-indigo-300"
                            >
                                + Add Specific
                            </button>
                        </label>
                        <div class="space-y-2 max-h-48 overflow-y-auto">
                            <div
                                v-for="(value, key) in form.item_specifics"
                                :key="key"
                                class="grid grid-cols-2 gap-2"
                            >
                                <input
                                    :value="key"
                                    type="text"
                                    disabled
                                    class="px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm bg-gray-50 dark:bg-gray-900 dark:text-gray-400"
                                />
                                <div class="flex gap-2">
                                    <input
                                        v-model="form.item_specifics[key]"
                                        type="text"
                                        class="flex-1 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm dark:bg-gray-900 dark:text-gray-100"
                                    />
                                    <button
                                        @click="removeItemSpecific(key)"
                                        class="px-2 text-red-600 dark:text-red-400 hover:text-red-800 dark:hover:text-red-300"
                                    >
                                        ×
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Platform Tips -->
                <div v-if="showTips" class="bg-yellow-50 dark:bg-yellow-900/20 rounded-lg border border-yellow-200 dark:border-yellow-800 p-4">
                    <div class="flex items-start justify-between mb-2">
                        <h4 class="text-sm font-semibold text-yellow-900 dark:text-yellow-300 flex items-center">
                            <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                            </svg>
                            {{ activePreviewTab.charAt(0).toUpperCase() + activePreviewTab.slice(1) }} Tips
                        </h4>
                        <button
                            @click="showTips = false"
                            class="text-yellow-600 dark:text-yellow-400 hover:text-yellow-800 dark:hover:text-yellow-200"
                        >
                            ×
                        </button>
                    </div>
                    
                    <ul class="text-xs text-yellow-900 dark:text-yellow-300 space-y-1">
                        <li v-if="activePreviewTab === 'ebay'">
                            • Use all 12 photo slots for best results
                        </li>
                        <li v-if="activePreviewTab === 'ebay'">
                            • Include brand and model in title for SEO
                        </li>
                        <li v-if="activePreviewTab === 'amazon'">
                            • Bullet points are critical for Amazon conversions
                        </li>
                        <li v-if="activePreviewTab === 'amazon'">
                            • Use up to 200 characters in title
                        </li>
                        <li>
                            • Add detailed item specifics for better search visibility
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="mt-8 flex justify-between items-center pt-6 border-t border-gray-200 dark:border-gray-700">
            <button
                @click="$emit('prev')"
                class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition flex items-center"
            >
                <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M9.707 16.707a1 1 0 01-1.414 0l-6-6a1 1 0 010-1.414l6-6a1 1 0 011.414 1.414L5.414 9H17a1 1 0 110 2H5.414l4.293 4.293a1 1 0 010 1.414z" clip-rule="evenodd"/>
                </svg>
                Back to Pricing
            </button>

            <div class="flex gap-3">
                <button
                    @click="$emit('save-draft')"
                    class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition"
                >
                    Save Draft
                </button>

                <button
                    @click="$emit('next')"
                    :disabled="!form.listing_title || !form.listing_description"
                    :class="{
                        'opacity-50 cursor-not-allowed': !form.listing_title || !form.listing_description,
                    }"
                    class="px-6 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-md font-medium transition flex items-center"
                >
                    Next: Review & Publish
                    <svg class="w-5 h-5 ml-2" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10.293 3.293a1 1 0 011.414 0l6 6a1 1 0 010 1.414l-6 6a1 1 0 01-1.414-1.414L14.586 11H3a1 1 0 110-2h11.586l-4.293-4.293a1 1 0 010-1.414z" clip-rule="evenodd"/>
                    </svg>
                </button>
            </div>
        </div>
    </div>
</template>
