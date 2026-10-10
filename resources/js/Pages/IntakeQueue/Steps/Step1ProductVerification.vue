<script setup>
import { ref, computed, onMounted } from 'vue';
import axios from 'axios';
import { router } from '@inertiajs/vue3';

const props = defineProps({
    queueItem: Object,
    form: Object,
    locations: { type: Array, default: () => [] },
});

const selectedLocationBins = computed(() => {
    if (!props.form.quick_location_id) return [];
    const loc = props.locations.find(l => l.id === props.form.quick_location_id);
    return loc?.bins ?? [];
});

const emit = defineEmits(['next', 'save-draft', 'reresearch']);

const selectedPhoto = ref(0);
const showFullResearch = ref(true);
const productMismatch = ref(false);
const isReresearching = ref(false);
const reresearchError = ref(null);
const correctionData = ref({
    brand: '',
    model: '',
    upc: '',
    notes: '',
});

// Populate form data from ACTUAL enriched_data structure
onMounted(() => {
    populateFormFromEnrichedData();
});

const populateFormFromEnrichedData = () => {
    const enrichedData = props.queueItem.enriched_data;
    
    if (!enrichedData) {
        return;
    }
    
    // ACTUAL STRUCTURE: Extract from research_data (not product_data.gemini_research)
    const researchData = enrichedData.research_data;
    
    if (researchData && researchData.product_identity) {
        const productIdentity = researchData.product_identity;
        
        // Populate form with research data
        props.form.name = productIdentity.title || props.form.name;
        props.form.brand = productIdentity.brand || props.form.brand;
        props.form.model = productIdentity.model || props.form.model;
        props.form.mpn = productIdentity.manufacturer_part_number || productIdentity.verified_complete_part_number || props.form.mpn;
        props.form.upc = productIdentity.upc || props.form.upc;
        props.form.ean = productIdentity.ean || props.form.ean;
        props.form.category = productIdentity.category || props.form.category;
        props.form.subcategory = productIdentity.subcategory || props.form.subcategory;
    }
    
    // Check for product_details in research_data
    if (researchData && researchData.product_details) {
        const productDetails = researchData.product_details;
        
        if (!props.form.description && productDetails.description) {
            props.form.description = productDetails.description;
        }
        
        if (productDetails.key_features) {
            props.form.key_features = productDetails.key_features;
        }
        
        if (productDetails.specifications) {
            props.form.specifications = productDetails.specifications;
        }
    }
    
    // Also check for UPC lookup data (if still present in old structure)
    const upcData = enrichedData.product_data?.upc_lookup;
    if (upcData) {
        if (!props.form.name) props.form.name = upcData.title;
        if (!props.form.brand) props.form.brand = upcData.brand;
        if (!props.form.upc) props.form.upc = upcData.upc;
    }
    
    // Fallback to direct enriched data fields
    if (!props.form.name && enrichedData.title) props.form.name = enrichedData.title;
    if (!props.form.brand && enrichedData.brand) props.form.brand = enrichedData.brand;
    if (!props.form.description && enrichedData.description) props.form.description = enrichedData.description;
    
    // Populate from queue item direct fields
    if (!props.form.brand && props.queueItem.brand) props.form.brand = props.queueItem.brand;
    if (!props.form.model && props.queueItem.model_number) props.form.model = props.queueItem.model_number;
    if (!props.form.mpn && props.queueItem.manufacturer_part_number) props.form.mpn = props.queueItem.manufacturer_part_number;
};

// Updated to work with ACTUAL data structure
const researchData = computed(() => 
    props.queueItem.enriched_data?.research_data || null
);

const upcData = computed(() => 
    props.queueItem.enriched_data?.product_data?.upc_lookup || null
);

const catalogImage = computed(() => {
    // Check UPC lookup images
    if (upcData.value?.images?.length > 0) {
        return upcData.value.images[0];
    }
    
    // Check research data images 
    const images = researchData.value?.images;
    if (images && images.length > 0) {
        return images[0];
    }
    
    return null;
});

// Data sources for ACTUAL structure
const dataSources = computed(() => {
    const sources = [];
    const enrichedData = props.queueItem.enriched_data;
    
    if (!enrichedData) return sources;
    
    // Main research source
    if (researchData.value) {
        sources.push({
            name: 'AI Product Research',
            type: 'research',
            confidence: enrichedData.confidence_breakdown?.final_confidence || 'High',
            description: 'Comprehensive product identification and research',
            stage: 'complete'
        });
    }
    
    // Sources used
    if (enrichedData.sources_used) {
        enrichedData.sources_used.forEach((source, index) => {
            sources.push({
                name: source.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase()),
                type: 'source',
                confidence: 'Various',
                description: `Data source: ${source}`,
                stage: 'data'
            });
        });
    }
    
    // eBay data source
    if (upcData.value?.source === 'ebay_production') {
        sources.push({
            name: 'eBay Product Catalog',
            type: 'ebay',
            url: upcData.value.product_url || null,
            epid: upcData.value.epid,
            confidence: 'High',
            description: 'Matched via eBay Product Database',
            stage: 'external'
        });
    }
    
    return sources;
});

// Confidence calculation from ACTUAL structure
const identificationConfidence = computed(() => {
    const enrichedData = props.queueItem.enriched_data;
    
    if (!enrichedData) return null;
    
    // Check confidence_breakdown
    const confidenceBreakdown = enrichedData.confidence_breakdown;
    if (confidenceBreakdown && confidenceBreakdown.final_confidence) {
        const confidence = confidenceBreakdown.final_confidence;
        
        // Convert text confidence to percentage
        if (typeof confidence === 'string') {
            if (confidence.toLowerCase() === 'high') return calculateConfidenceDisplay(90);
            if (confidence.toLowerCase() === 'medium') return calculateConfidenceDisplay(75);
            if (confidence.toLowerCase() === 'low') return calculateConfidenceDisplay(50);
        }
        
        // If it's a number
        if (typeof confidence === 'number') {
            return calculateConfidenceDisplay(confidence * 100);
        }
    }
    
    // Fallback to queue item confidence
    const score = props.queueItem.ai_confidence_score;
    if (score) {
        return calculateConfidenceDisplay(score * 100);
    }
    
    return calculateConfidenceDisplay(75); // Default medium confidence
});

const calculateConfidenceDisplay = (percentage) => {
    let level = 'unknown';
    let color = 'gray';
    
    if (percentage >= 80) {
        level = 'high';
        color = 'green';
    } else if (percentage >= 60) {
        level = 'medium'; 
        color = 'yellow';
    } else {
        level = 'low';
        color = 'red';
    }
    
    return { percentage: Math.round(percentage), level, color };
};

// Research summary for ACTUAL structure
const researchSummary = computed(() => {
    const enrichedData = props.queueItem.enriched_data;
    
    if (!enrichedData || !researchData.value) return null;
    
    return {
        research_completed: !!researchData.value,
        product_identified: !!researchData.value.product_identity,
        has_warnings: !!(enrichedData.warnings && enrichedData.warnings.length > 0),
        warnings: enrichedData.warnings || [],
        sources_count: enrichedData.sources_used?.length || 0,
        confidence_level: identificationConfidence.value?.level || 'unknown',
        supplier_context: !!enrichedData.supplier_context,
        shipping_data: !!enrichedData.shipping_data
    };
});

// ── Deep research evidence (proof the user can verify) ──────────────────
const stage1 = computed(() => researchData.value?.stage1_identification || null);

const identification = computed(() =>
    stage1.value?.identification_results
    || researchData.value?.identification_results
    || null
);

const mostLikelyProduct = computed(() => identification.value?.most_likely_product || null);

const idReasoning = computed(() =>
    researchData.value?.identification_reasoning
    || identification.value?.reasoning
    || null
);

const idConfidenceLabel = computed(() => {
    const c = researchData.value?.identification_confidence
        || identification.value?.identification_confidence
        || null;
    return c && c !== 'none' ? String(c).toLowerCase() : null;
});

const typeValidation = computed(() => {
    const tv = identification.value?.user_type_validation;
    if (!tv || tv.type_classification_matches !== false) return null;
    return tv; // only surface when AI disagrees with the user-expected type
});

const marketPricing = computed(() => researchData.value?.market_pricing || null);

const activeListings = computed(() => {
    const l = marketPricing.value?.ebay_active_listings;
    return Array.isArray(l) ? l.filter(x => x && x.title) : [];
});

const soldListings = computed(() => {
    const l = marketPricing.value?.ebay_sold_listings;
    return Array.isArray(l) ? l.filter(x => x && x.title) : [];
});

const priceStats = computed(() => {
    const mp = marketPricing.value;
    if (!mp) return null;
    const hasAny = mp.recommended_list_price || mp.average_price || mp.price_range || mp.sample_size;
    if (!hasAny) return null;
    return {
        recommended: mp.recommended_list_price ?? null,
        average: mp.average_price ?? null,
        median: mp.median_price ?? null,
        range: mp.price_range ?? null,
        lowest: mp.lowest_price ?? null,
        highest: mp.highest_price ?? null,
        sampleSize: mp.sample_size ?? (activeListings.value.length || null),
        confidence: mp.pricing_confidence ?? null,
    };
});

const identificationSources = computed(() => {
    const s = researchData.value?.identification_sources
        || stage1.value?.source_links
        || [];
    return Array.isArray(s) ? s.filter(x => x && (x.url || x.description)) : [];
});

const searchTerms = computed(() => {
    const strat = stage1.value?.search_strategy_for_stage2;
    const primary = strat?.primary_search_terms || [];
    const secondary = strat?.secondary_search_terms || [];
    return [...new Set([...primary, ...secondary].filter(Boolean))];
});

const searchMethodLabel = computed(() => {
    const m = researchData.value?.search_method || researchData.value?.data_source;
    if (!m) return null;
    return String(m).replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
});

// Is there enough deep evidence to justify a dedicated panel?
const hasEvidence = computed(() =>
    !!(mostLikelyProduct.value || idReasoning.value || activeListings.value.length
        || soldListings.value.length || identificationSources.value.length
        || searchTerms.value.length || priceStats.value)
);

const formatPrice = (value, currency = 'USD') => {
    const n = Number(value);
    if (!isFinite(n) || n <= 0) return null;
    try {
        return new Intl.NumberFormat('en-US', { style: 'currency', currency: currency || 'USD' }).format(n);
    } catch (e) {
        return `$${n.toFixed(2)}`;
    }
};

const confidenceBadgeClass = (level) => {
    const l = String(level || '').toLowerCase();
    if (l === 'high') return 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300';
    if (l === 'medium') return 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/40 dark:text-yellow-300';
    if (l === 'low') return 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300';
    return 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300';
};

const listingLink = (listing) => listing?.link || listing?.url || listing?.product_url || null;
const listingPrice = (listing) => formatPrice(listing?.total_price ?? listing?.price, listing?.currency);

const confirmProduct = (isCorrect) => {
    if (isCorrect) {
        props.form.product_confirmed = true;
        props.form.product_match_type = 'auto';
        productMismatch.value = false;
        emit('next');
    } else {
        productMismatch.value = true;
        props.form.product_confirmed = false;
    }
};

const submitCorrection = async () => {
    isReresearching.value = true;
    reresearchError.value = null;

    try {
        await axios.post(route('intake-queue.reresearch', props.queueItem.id), {
            brand: correctionData.value.brand,
            model: correctionData.value.model,
            upc: correctionData.value.upc,
            notes: correctionData.value.notes,
        });

        // Poll for completion
        pollForCompletion();
    } catch (error) {
        isReresearching.value = false;
        reresearchError.value = error.response?.data?.message || 'Failed to start re-research. Please try again.';
    }
};

const pollForCompletion = () => {
    const pollInterval = setInterval(() => {
        router.reload({
            only: ['queueItem'],
            preserveState: true,
            preserveScroll: true,
            onSuccess: (page) => {
                const item = page.props.queueItem;
                if (item.ai_analysis_status === 'completed' || item.ai_analysis_status === 'failed') {
                    clearInterval(pollInterval);
                    isReresearching.value = false;
                    productMismatch.value = false;

                    // Re-populate form from new enriched data
                    populateFormFromEnrichedData();

                    // Reset correction fields
                    correctionData.value = { brand: '', model: '', upc: '', notes: '' };
                }
            },
        });
    }, 3000);

    // Safety timeout after 2 minutes
    setTimeout(() => {
        clearInterval(pollInterval);
        if (isReresearching.value) {
            isReresearching.value = false;
            reresearchError.value = 'Research is taking longer than expected. The page will update when complete.';
        }
    }, 120000);
};

const manualOverride = () => {
    props.form.product_confirmed = true;
    props.form.product_match_type = 'manual_override';
    props.form.correction_notes = 'Manual override - user confirmed product identity';
    productMismatch.value = false;
    emit('next');
};
</script>

<template>
    <div class="p-6">
        <div class="mb-6">
            <h2 class="text-2xl font-bold text-gray-900 dark:text-gray-100">
                Step 1: Product & Photo Verification
            </h2>
            <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                Confirm our AI research has identified the correct product
            </p>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Left: Photos Comparison -->
            <div class="space-y-4">
                <!-- Captured Photos -->
                <div class="bg-gray-50 dark:bg-gray-900 rounded-lg p-4">
                    <h3 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">
                        Your Photos
                    </h3>
                    
                    <!-- Main Photo -->
                    <div class="mb-3" v-if="queueItem.photos && queueItem.photos.length > 0">
                        <img
                            :src="queueItem.photos[selectedPhoto]"
                            alt="Product photo"
                            class="w-full h-80 object-contain bg-white dark:bg-gray-800 rounded-lg shadow-sm"
                        />
                    </div>
                    <div v-else class="mb-3 h-80 bg-gray-200 dark:bg-gray-700 rounded-lg flex items-center justify-center">
                        <p class="text-gray-500 dark:text-gray-400">No photos available</p>
                    </div>

                    <!-- Thumbnails -->
                    <div v-if="queueItem.photos && queueItem.photos.length > 1" class="grid grid-cols-6 gap-2">
                        <button
                            v-for="(photo, index) in queueItem.photos"
                            :key="index"
                            @click="selectedPhoto = index"
                            :class="{
                                'ring-2 ring-indigo-500': selectedPhoto === index,
                            }"
                            class="aspect-square rounded-md overflow-hidden hover:opacity-75 transition"
                        >
                            <img
                                :src="photo"
                                alt="Thumbnail"
                                class="w-full h-full object-cover"
                            />
                        </button>
                    </div>
                </div>

                <!-- Catalog Image Comparison -->
                <div v-if="catalogImage" class="bg-blue-50 dark:bg-blue-900/20 rounded-lg p-4 border-2 border-blue-200 dark:border-blue-800">
                    <h3 class="text-sm font-medium text-blue-900 dark:text-blue-300 mb-3 flex items-center">
                        <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z"/>
                            <path fill-rule="evenodd" d="M4 5a2 2 0 012-2 3 3 0 003 3h2a3 3 0 003-3 2 2 0 012 2v11a2 2 0 01-2 2H6a2 2 0 01-2-2V5zm3 4a1 1 0 000 2h.01a1 1 0 100-2H7zm3 0a1 1 0 000 2h3a1 1 0 100-2h-3zm-3 4a1 1 0 100 2h.01a1 1 0 100-2H7zm3 0a1 1 0 100 2h3a1 1 0 100-2h-3z" clip-rule="evenodd"/>
                        </svg>
                        Reference Image
                    </h3>
                    <img
                        :src="catalogImage"
                        alt="Reference image"
                        class="w-full h-64 object-contain bg-white dark:bg-gray-800 rounded-lg shadow-sm"
                    />
                    <p class="mt-2 text-xs text-blue-700 dark:text-blue-400">
                        Does your item match this reference image?
                    </p>
                </div>

                <!-- AI Research Summary -->
                <div v-if="researchSummary" class="bg-gradient-to-br from-purple-50 to-indigo-50 dark:from-gray-800 dark:to-gray-900 rounded-lg p-4 border-2 border-purple-200 dark:border-purple-800">
                    <h3 class="text-sm font-medium text-purple-900 dark:text-purple-300 mb-3 flex items-center">
                        <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        AI Research Summary
                    </h3>
                    
                    <div class="space-y-2 text-xs">
                        <div class="flex justify-between">
                            <span class="font-medium text-purple-900 dark:text-purple-300">Research Status:</span>
                            <span :class="{
                                'text-green-600 dark:text-green-400': researchSummary.research_completed,
                                'text-red-600 dark:text-red-400': !researchSummary.research_completed
                            }">
                                {{ researchSummary.research_completed ? 'COMPLETE' : 'INCOMPLETE' }}
                            </span>
                        </div>
                        
                        <div class="flex justify-between">
                            <span class="font-medium text-purple-900 dark:text-purple-300">Product Identified:</span>
                            <span :class="{
                                'text-green-600 dark:text-green-400': researchSummary.product_identified,
                                'text-red-600 dark:text-red-400': !researchSummary.product_identified
                            }">
                                {{ researchSummary.product_identified ? 'YES' : 'NO' }}
                            </span>
                        </div>
                        
                        <div v-if="researchSummary.sources_count > 0" class="flex justify-between">
                            <span class="font-medium text-purple-900 dark:text-purple-300">Data Sources:</span>
                            <span class="text-purple-700 dark:text-purple-400">{{ researchSummary.sources_count }}</span>
                        </div>
                        
                        <div v-if="identificationConfidence" class="flex justify-between pt-2 border-t border-purple-200 dark:border-purple-700">
                            <span class="font-medium text-purple-900 dark:text-purple-300">Confidence:</span>
                            <span 
                                :class="{
                                    'text-green-600 dark:text-green-400': identificationConfidence.color === 'green',
                                    'text-yellow-600 dark:text-yellow-400': identificationConfidence.color === 'yellow',
                                    'text-red-600 dark:text-red-400': identificationConfidence.color === 'red'
                                }"
                                class="font-bold"
                            >
                                {{ identificationConfidence.percentage }}%
                            </span>
                        </div>
                        
                        <!-- Show warnings if any -->
                        <div v-if="researchSummary.has_warnings" class="mt-3 pt-2 border-t border-purple-200 dark:border-purple-700">
                            <div class="text-yellow-700 dark:text-yellow-300 font-medium mb-1">Warnings:</div>
                            <div 
                                v-for="warning in researchSummary.warnings" 
                                :key="warning"
                                class="text-yellow-600 dark:text-yellow-400 text-xs"
                            >
                                • {{ warning }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right: Product Identity -->
            <div class="space-y-4">
                <!-- Product Match Confirmation -->
                <div class="bg-gradient-to-br from-indigo-50 to-purple-50 dark:from-gray-800 dark:to-gray-900 rounded-lg p-6 border-2 border-indigo-200 dark:border-indigo-800">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">
                        Product Identification Results
                    </h3>
                    
                    <!-- Show identified product information -->
                    <div class="space-y-3 mb-6">
                        <div v-if="form.name">
                            <div class="text-xs text-gray-500 dark:text-gray-400">Product Name</div>
                            <div class="text-lg font-bold text-gray-900 dark:text-gray-100">
                                {{ form.name }}
                            </div>
                        </div>
                        
                        <div v-if="form.brand">
                            <div class="text-xs text-gray-500 dark:text-gray-400">Brand</div>
                            <div class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                                {{ form.brand }}
                            </div>
                        </div>
                        
                        <div v-if="form.model">
                            <div class="text-xs text-gray-500 dark:text-gray-400">Model</div>
                            <div class="text-md font-medium text-gray-900 dark:text-gray-100 font-mono">
                                {{ form.model }}
                            </div>
                        </div>
                        
                        <div v-if="form.mpn">
                            <div class="text-xs text-gray-500 dark:text-gray-400">Part Number</div>
                            <div class="text-sm font-medium text-gray-700 dark:text-gray-300 font-mono">
                                {{ form.mpn }}
                            </div>
                        </div>
                        
                        <div v-if="form.category">
                            <div class="text-xs text-gray-500 dark:text-gray-400">Category</div>
                            <div class="text-sm text-gray-700 dark:text-gray-300">
                                {{ form.category }}
                                <span v-if="form.subcategory"> > {{ form.subcategory }}</span>
                            </div>
                        </div>
                        
                        <div v-if="form.description">
                            <div class="text-xs text-gray-500 dark:text-gray-400">Description</div>
                            <div class="text-sm text-gray-700 dark:text-gray-300">
                                {{ form.description }}
                            </div>
                        </div>
                    </div>

                    <!-- Show message if no product data found -->
                    <div v-if="!form.name && !form.brand && !form.model" class="mb-6 p-4 bg-yellow-50 dark:bg-yellow-900/20 rounded-lg border border-yellow-200 dark:border-yellow-800">
                        <div class="text-sm text-yellow-800 dark:text-yellow-300">
                            <strong>No product identification found.</strong><br>
                            The AI research was unable to identify this product. You can proceed manually or add corrections below.
                        </div>
                    </div>

                    <!-- Confirmation Buttons -->
                    <div v-if="!productMismatch" class="border-t border-indigo-200 dark:border-indigo-700 pt-4">
                        <p class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">
                            Is this the correct product identification?
                        </p>
                        <div class="flex gap-3">
                            <button
                                @click="confirmProduct(true)"
                                class="flex-1 bg-green-600 hover:bg-green-700 text-white px-4 py-3 rounded-lg font-medium transition flex items-center justify-center"
                            >
                                <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                </svg>
                                Yes, Correct
                            </button>
                            <button
                                @click="confirmProduct(false)"
                                class="flex-1 bg-red-600 hover:bg-red-700 text-white px-4 py-3 rounded-lg font-medium transition flex items-center justify-center"
                            >
                                <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                                </svg>
                                No, Incorrect
                            </button>
                        </div>
                    </div>

                    <!-- Correction Modal -->
                    <div v-else class="border-t border-red-200 dark:border-red-700 pt-4">
                        <!-- Re-research loading state -->
                        <div v-if="isReresearching" class="text-center py-8">
                            <svg class="animate-spin h-10 w-10 text-indigo-600 mx-auto mb-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <p class="text-sm font-medium text-gray-900 dark:text-gray-100">
                                Re-running AI research with your corrections...
                            </p>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                This may take a moment. Results will appear automatically.
                            </p>
                        </div>

                        <template v-else>
                            <div class="bg-red-50 dark:bg-red-900/20 rounded-lg p-4 mb-4">
                                <h4 class="text-sm font-semibold text-red-900 dark:text-red-300 mb-2">
                                    Product Mismatch Detected
                                </h4>
                                <p class="text-xs text-red-700 dark:text-red-400">
                                    Please provide corrections or proceed manually
                                </p>
                            </div>

                            <!-- Error message -->
                            <div v-if="reresearchError" class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-3 mb-4">
                                <p class="text-xs text-red-700 dark:text-red-400">{{ reresearchError }}</p>
                            </div>

                            <div class="space-y-3 mb-4">
                                <div>
                                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">
                                        Correct Brand
                                    </label>
                                    <input
                                        v-model="correctionData.brand"
                                        type="text"
                                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm dark:bg-gray-800 dark:text-gray-100"
                                        placeholder="Enter correct brand"
                                    />
                                </div>

                                <div>
                                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">
                                        Correct Model
                                    </label>
                                    <input
                                        v-model="correctionData.model"
                                        type="text"
                                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm dark:bg-gray-800 dark:text-gray-100"
                                        placeholder="Enter correct model"
                                    />
                                </div>

                                <div>
                                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">
                                        Notes
                                    </label>
                                    <textarea
                                        v-model="correctionData.notes"
                                        rows="2"
                                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm dark:bg-gray-800 dark:text-gray-100"
                                        placeholder="What's different?"
                                    ></textarea>
                                </div>
                            </div>

                            <div class="flex gap-2">
                                <button
                                    @click="submitCorrection"
                                    class="flex-1 bg-blue-600 hover:bg-blue-700 text-white px-3 py-2 rounded-md text-sm font-medium"
                                >
                                    Submit & Re-research
                                </button>
                                <button
                                    @click="manualOverride"
                                    class="flex-1 bg-gray-600 hover:bg-gray-700 text-white px-3 py-2 rounded-md text-sm font-medium"
                                >
                                    Manual Override
                                </button>
                                <button
                                    @click="productMismatch = false"
                                    class="px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm"
                                >
                                    Cancel
                                </button>
                            </div>
                        </template>
                    </div>
                </div>

                <!-- Warehouse Verification -->
                <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-4">
                    <h3 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-4 flex items-center">
                        <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M4 4a2 2 0 00-2 2v8a2 2 0 002 2h12a2 2 0 002-2V8a2 2 0 00-2-2h-5L9 4H4zm7 5a1 1 0 10-2 0v1H8a1 1 0 100 2h1v1a1 1 0 102 0v-1h1a1 1 0 100-2h-1V9z" clip-rule="evenodd"/>
                        </svg>
                        Warehouse Verification
                    </h3>
                    
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Quantity on Hand
                            </label>
                            <input
                                v-model.number="form.quantity"
                                type="number"
                                min="0"
                                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md dark:bg-gray-900 dark:text-gray-100"
                            />
                        </div>
                        
                        <div>
                            <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Location
                            </label>
                            <select
                                v-model="form.quick_location_id"
                                @change="form.quick_bin_id = null"
                                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md dark:bg-gray-900 dark:text-gray-100"
                            >
                                <option :value="null">-- Select location --</option>
                                <option v-for="loc in locations" :key="loc.id" :value="loc.id">
                                    {{ loc.name }}<span v-if="loc.code"> ({{ loc.code }})</span>
                                </option>
                            </select>
                            <p v-if="!form.quick_location_id && form.location" class="text-xs text-amber-600 dark:text-amber-400 mt-1">
                                Legacy text: "{{ form.location }}" — pick a real location to make pick/pack/ship show it.
                            </p>
                        </div>
                        <div v-if="selectedLocationBins.length > 0">
                            <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Bin (optional)
                            </label>
                            <select
                                v-model="form.quick_bin_id"
                                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md dark:bg-gray-900 dark:text-gray-100"
                            >
                                <option :value="null">-- No specific bin --</option>
                                <option v-for="bin in selectedLocationBins" :key="bin.id" :value="bin.id">
                                    {{ bin.zone_code }}-{{ bin.code }}<span v-if="bin.label"> &mdash; {{ bin.label }}</span>
                                </option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ── Research & Evidence: prove the identification to the user ── -->
        <div v-if="hasEvidence" class="mt-8">
            <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 overflow-hidden">
                <button
                    @click="showFullResearch = !showFullResearch"
                    class="w-full flex items-center justify-between px-5 py-4 text-left hover:bg-gray-50 dark:hover:bg-gray-700/40 transition"
                >
                    <span class="flex items-center text-base font-semibold text-gray-900 dark:text-gray-100">
                        <svg class="w-5 h-5 mr-2 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        How we identified this product
                        <span class="ml-2 text-xs font-normal text-gray-500 dark:text-gray-400">
                            (the research behind this match — verify it yourself)
                        </span>
                    </span>
                    <svg
                        :class="{ 'rotate-180': showFullResearch }"
                        class="w-5 h-5 text-gray-400 transition-transform shrink-0"
                        fill="currentColor"
                        viewBox="0 0 20 20"
                    >
                        <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"/>
                    </svg>
                </button>

                <div v-show="showFullResearch" class="px-5 pb-6 space-y-6 border-t border-gray-100 dark:border-gray-700">
                    <!-- A. Identification verdict + reasoning -->
                    <div v-if="mostLikelyProduct || idReasoning" class="pt-5">
                        <div class="flex items-center justify-between mb-3">
                            <h4 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Identification Verdict</h4>
                            <span v-if="idConfidenceLabel" :class="confidenceBadgeClass(idConfidenceLabel)" class="px-2.5 py-0.5 rounded-full text-xs font-semibold uppercase tracking-wide">
                                {{ idConfidenceLabel }} confidence
                            </span>
                        </div>

                        <div v-if="mostLikelyProduct" class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-4">
                            <div v-if="mostLikelyProduct.product_name" class="col-span-2 md:col-span-4">
                                <div class="text-xs text-gray-500 dark:text-gray-400">Determined Product</div>
                                <div class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ mostLikelyProduct.product_name }}</div>
                            </div>
                            <div v-if="mostLikelyProduct.manufacturer">
                                <div class="text-xs text-gray-500 dark:text-gray-400">Manufacturer</div>
                                <div class="text-sm text-gray-800 dark:text-gray-200">{{ mostLikelyProduct.manufacturer }}</div>
                            </div>
                            <div v-if="mostLikelyProduct.complete_part_number">
                                <div class="text-xs text-gray-500 dark:text-gray-400">Part Number</div>
                                <div class="text-sm font-mono text-gray-800 dark:text-gray-200">{{ mostLikelyProduct.complete_part_number }}</div>
                            </div>
                            <div v-if="mostLikelyProduct.product_type">
                                <div class="text-xs text-gray-500 dark:text-gray-400">Type</div>
                                <div class="text-sm text-gray-800 dark:text-gray-200">{{ mostLikelyProduct.product_type }}</div>
                            </div>
                        </div>

                        <div v-if="idReasoning" class="bg-indigo-50 dark:bg-indigo-900/20 border border-indigo-100 dark:border-indigo-800 rounded-lg p-4">
                            <div class="text-xs font-medium text-indigo-900 dark:text-indigo-300 mb-1">Why the AI reached this conclusion</div>
                            <p class="text-sm text-indigo-800 dark:text-indigo-200 leading-relaxed whitespace-pre-line">{{ idReasoning }}</p>
                        </div>

                        <div v-if="typeValidation" class="mt-3 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-lg p-4">
                            <div class="text-xs font-semibold text-amber-900 dark:text-amber-300 mb-1">Heads up: product type may differ from what was expected</div>
                            <p class="text-sm text-amber-800 dark:text-amber-200">
                                You expected <strong>{{ typeValidation.user_expected_type }}</strong>,
                                but the AI determined <strong>{{ typeValidation.ai_determined_type }}</strong>.
                                <span v-if="typeValidation.type_reasoning"> {{ typeValidation.type_reasoning }}</span>
                            </p>
                        </div>
                    </div>

                    <!-- B. Marketplace evidence: actual live listings that grounded the ID -->
                    <div v-if="activeListings.length || soldListings.length" class="pt-1">
                        <div class="flex items-center justify-between mb-3">
                            <h4 class="text-sm font-semibold text-gray-900 dark:text-gray-100">
                                Marketplace Evidence
                            </h4>
                            <span class="text-xs text-gray-500 dark:text-gray-400">
                                Real listings used to confirm this product — open any to verify
                            </span>
                        </div>

                        <!-- Active listings -->
                        <div v-if="activeListings.length" class="mb-4">
                            <div class="text-xs font-medium text-gray-600 dark:text-gray-400 mb-2">
                                Live eBay listings ({{ activeListings.length }})
                            </div>
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                                <component
                                    :is="listingLink(listing) ? 'a' : 'div'"
                                    v-for="(listing, index) in activeListings"
                                    :key="'a' + index"
                                    :href="listingLink(listing) || undefined"
                                    :target="listingLink(listing) ? '_blank' : undefined"
                                    rel="noopener"
                                    class="group flex gap-3 p-3 bg-gray-50 dark:bg-gray-900 rounded-lg border border-gray-200 dark:border-gray-700 hover:border-indigo-300 dark:hover:border-indigo-600 hover:shadow-sm transition"
                                >
                                    <img
                                        v-if="listing.image"
                                        :src="listing.image"
                                        alt="Listing"
                                        class="w-16 h-16 object-contain bg-white dark:bg-gray-800 rounded shrink-0"
                                    />
                                    <div class="min-w-0 flex-1">
                                        <div class="text-xs text-gray-800 dark:text-gray-200 line-clamp-2 group-hover:text-indigo-600 dark:group-hover:text-indigo-400">
                                            {{ listing.title }}
                                        </div>
                                        <div class="mt-1 flex items-center flex-wrap gap-x-2 gap-y-0.5">
                                            <span v-if="listingPrice(listing)" class="text-sm font-semibold text-gray-900 dark:text-gray-100">
                                                {{ listingPrice(listing) }}
                                            </span>
                                            <span v-if="listing.condition && listing.condition !== 'Unknown'" class="text-xs text-gray-500 dark:text-gray-400">
                                                {{ listing.condition }}
                                            </span>
                                        </div>
                                        <div v-if="listing.upc || listing.ean" class="mt-0.5 text-xs font-mono text-gray-400 dark:text-gray-500 truncate">
                                            {{ listing.upc ? 'UPC ' + listing.upc : 'EAN ' + listing.ean }}
                                        </div>
                                        <div v-if="listingLink(listing)" class="mt-1 inline-flex items-center text-xs text-indigo-600 dark:text-indigo-400 font-medium">
                                            View on eBay
                                            <svg class="w-3 h-3 ml-0.5" fill="currentColor" viewBox="0 0 20 20">
                                                <path d="M11 3a1 1 0 100 2h2.586l-6.293 6.293a1 1 0 101.414 1.414L15 6.414V9a1 1 0 102 0V4a1 1 0 00-1-1h-5z"/>
                                            </svg>
                                        </div>
                                    </div>
                                </component>
                            </div>
                        </div>

                        <!-- Sold comps -->
                        <div v-if="soldListings.length">
                            <div class="text-xs font-medium text-gray-600 dark:text-gray-400 mb-2">
                                Recent sold comps ({{ soldListings.length }})
                            </div>
                            <div class="space-y-1.5">
                                <component
                                    :is="listingLink(listing) ? 'a' : 'div'"
                                    v-for="(listing, index) in soldListings"
                                    :key="'s' + index"
                                    :href="listingLink(listing) || undefined"
                                    :target="listingLink(listing) ? '_blank' : undefined"
                                    rel="noopener"
                                    class="flex items-center justify-between gap-3 px-3 py-2 bg-gray-50 dark:bg-gray-900 rounded border border-gray-200 dark:border-gray-700 hover:border-indigo-300 dark:hover:border-indigo-600 transition"
                                >
                                    <span class="text-xs text-gray-700 dark:text-gray-300 truncate flex-1">{{ listing.title }}</span>
                                    <span v-if="listing.sold_date" class="text-xs text-gray-400 dark:text-gray-500 shrink-0 hidden sm:inline">{{ listing.sold_date }}</span>
                                    <span v-if="listingPrice(listing)" class="text-xs font-semibold text-green-700 dark:text-green-400 shrink-0">{{ listingPrice(listing) }}</span>
                                </component>
                            </div>
                        </div>

                        <!-- Pricing rollup -->
                        <div v-if="priceStats" class="mt-4 grid grid-cols-2 sm:grid-cols-4 gap-3">
                            <div v-if="formatPrice(priceStats.recommended)" class="bg-green-50 dark:bg-green-900/20 rounded-lg p-3 border border-green-100 dark:border-green-800">
                                <div class="text-xs text-green-700 dark:text-green-400">Recommended</div>
                                <div class="text-base font-bold text-green-800 dark:text-green-300">{{ formatPrice(priceStats.recommended) }}</div>
                            </div>
                            <div v-if="priceStats.range" class="bg-gray-50 dark:bg-gray-900 rounded-lg p-3 border border-gray-200 dark:border-gray-700">
                                <div class="text-xs text-gray-500 dark:text-gray-400">Market Range</div>
                                <div class="text-sm font-semibold text-gray-800 dark:text-gray-200">{{ priceStats.range }}</div>
                            </div>
                            <div v-if="formatPrice(priceStats.average)" class="bg-gray-50 dark:bg-gray-900 rounded-lg p-3 border border-gray-200 dark:border-gray-700">
                                <div class="text-xs text-gray-500 dark:text-gray-400">Average</div>
                                <div class="text-sm font-semibold text-gray-800 dark:text-gray-200">{{ formatPrice(priceStats.average) }}</div>
                            </div>
                            <div v-if="priceStats.sampleSize" class="bg-gray-50 dark:bg-gray-900 rounded-lg p-3 border border-gray-200 dark:border-gray-700">
                                <div class="text-xs text-gray-500 dark:text-gray-400">Based on</div>
                                <div class="text-sm font-semibold text-gray-800 dark:text-gray-200">
                                    {{ priceStats.sampleSize }} listing{{ priceStats.sampleSize === 1 ? '' : 's' }}
                                    <span v-if="priceStats.confidence" class="text-xs font-normal text-gray-400">· {{ priceStats.confidence }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- C. Reference documentation -->
                    <div v-if="identificationSources.length" class="pt-1">
                        <h4 class="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-3">Reference Sources</h4>
                        <div class="space-y-2">
                            <component
                                :is="source.url ? 'a' : 'div'"
                                v-for="(source, index) in identificationSources"
                                :key="'src' + index"
                                :href="source.url || undefined"
                                :target="source.url ? '_blank' : undefined"
                                rel="noopener"
                                class="flex items-start gap-3 p-3 bg-gray-50 dark:bg-gray-900 rounded-lg border border-gray-200 dark:border-gray-700"
                                :class="source.url ? 'hover:border-indigo-300 dark:hover:border-indigo-600 transition' : ''"
                            >
                                <svg class="w-4 h-4 mt-0.5 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 010 5.656l-3 3a4 4 0 01-5.656-5.656l1.5-1.5m6.656-6.656l1.5-1.5a4 4 0 015.656 5.656l-3 3a4 4 0 01-5.656 0"/>
                                </svg>
                                <div class="min-w-0 flex-1">
                                    <div v-if="source.type" class="text-xs font-medium text-gray-800 dark:text-gray-200 capitalize">
                                        {{ String(source.type).replace(/_/g, ' ') }}
                                    </div>
                                    <div v-if="source.description" class="text-xs text-gray-600 dark:text-gray-400">{{ source.description }}</div>
                                    <div v-if="source.url" class="text-xs text-indigo-600 dark:text-indigo-400 truncate">{{ source.url }}</div>
                                </div>
                            </component>
                        </div>
                    </div>

                    <!-- D. Search transparency -->
                    <div v-if="searchTerms.length || searchMethodLabel || (researchSummary && researchSummary.sources_count)" class="pt-1">
                        <h4 class="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-3">How We Searched</h4>
                        <div class="space-y-3">
                            <div v-if="searchMethodLabel" class="text-xs text-gray-600 dark:text-gray-400">
                                Method: <span class="font-medium text-gray-800 dark:text-gray-200">{{ searchMethodLabel }}</span>
                            </div>
                            <div v-if="searchTerms.length">
                                <div class="text-xs text-gray-500 dark:text-gray-400 mb-1.5">Search terms used</div>
                                <div class="flex flex-wrap gap-1.5">
                                    <span
                                        v-for="(term, index) in searchTerms"
                                        :key="'t' + index"
                                        class="px-2 py-0.5 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded text-xs font-mono"
                                    >
                                        {{ term }}
                                    </span>
                                </div>
                            </div>
                            <div v-if="dataSources.length">
                                <div class="text-xs text-gray-500 dark:text-gray-400 mb-1.5">Data sources contributing to this record</div>
                                <div class="flex flex-wrap gap-1.5">
                                    <span
                                        v-for="(source, index) in dataSources"
                                        :key="'ds' + index"
                                        class="px-2 py-0.5 bg-indigo-50 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300 rounded text-xs font-medium"
                                    >
                                        {{ source.name }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="mt-8 flex justify-between items-center pt-6 border-t border-gray-200 dark:border-gray-700">
            <button
                @click="$emit('save-draft')"
                class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition"
            >
                Save Draft
            </button>

            <button
                @click="confirmProduct(true)"
                :disabled="form.quantity <= 0"
                :class="{
                    'opacity-50 cursor-not-allowed': form.quantity <= 0,
                }"
                class="px-6 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-md font-medium transition flex items-center"
            >
                Next: Verify Pricing
                <svg class="w-5 h-5 ml-2" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10.293 3.293a1 1 0 011.414 0l6 6a1 1 0 010 1.414l-6 6a1 1 0 01-1.414-1.414L14.586 11H3a1 1 0 110-2h11.586l-4.293-4.293a1 1 0 010-1.414z" clip-rule="evenodd"/>
                </svg>
            </button>
        </div>
    </div>
</template>