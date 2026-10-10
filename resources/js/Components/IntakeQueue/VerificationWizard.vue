<script setup>
import { ref, computed } from 'vue';
import { useForm, router, usePage } from '@inertiajs/vue3';
import Step1ProductVerification from '@/Pages/IntakeQueue/Steps/Step1ProductVerification.vue';
import Step2PriceVerification from '@/Pages/IntakeQueue/Steps/Step2PriceVerification.vue';
import Step3ListingVerification from '@/Pages/IntakeQueue/Steps/Step3ListingVerification.vue';
import Step4ReviewAndPublish from '@/Pages/IntakeQueue/Steps/Step4ReviewAndPublish.vue';

const props = defineProps({
    queueItem: Object,
    locations: { type: Array, default: () => [] },
});

const page = usePage();

// Track publishing state
const isPublishing = ref(false);

const initialStep = (() => {
    const hash = window.location.hash;
    const match = hash.match(/^#step-(\d+)$/);
    if (match) {
        const step = parseInt(match[1], 10);
        if (step >= 1 && step <= 4) return step;
    }
    return 1;
})();

const currentStep = ref(initialStep);
const totalSteps = 4;

const updateStepHash = (step) => {
    history.replaceState(null, '', `${window.location.pathname}${window.location.search}#step-${step}`);
};

// Form data persists across all steps
const form = useForm({
    // Product Identity
    product_confirmed: false,
    product_match_type: 'auto', // 'auto' | 'manual_override' | 'corrected'
    correction_notes: '',
    
    // Product Data
    name: '',
    brand: '',
    model: '',
    mpn: '',
    upc: '',
    ean: '',
    category_id: null,
    category_name: '',
    description: '',
    
    // Inventory
    quantity: props.queueItem.quick_quantity || 1,
    location: props.queueItem.quick_location || '',
    quick_location_id: props.queueItem.quick_location_id || null,
    quick_bin_id: props.queueItem.quick_bin_id || null,
    
    // Pricing
    cost_basis: null,
    list_price: null,
    shipping_cost: 0,
    platform_fees_percent: 13.5, // eBay default
    profit_margin: null,
    
    // Listing Content
    listing_title: '',
    listing_description: '',
    bullet_points: [],
    keywords: [],
    item_specifics: {},
    condition: 'new',
    condition_notes: '',
    
    // Platform Settings
    target_platforms: ['ebay'], // 'ebay', 'amazon', 'newegg'
    ebay_listing_format: 'fixed_price', // 'fixed_price' | 'auction'
    ebay_duration: 'GTC',
    ebay_promoted: false,
    
    // Photos
    selected_photos: [],
});

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

// Initialize form with available data
const initializeFormData = () => {
    const upcData = props.queueItem.enriched_data?.product_data?.upc_lookup || {};
    const researchData = props.queueItem.enriched_data?.research_data || {};
    const geminiData = props.queueItem.enriched_data?.product_data?.gemini_research || {};
    const mergedFields = props.queueItem.enriched_data?.merged_product_fields || {};

    // Product Identity (new path first, old path fallback)
    form.name = researchData.product_identity?.title || geminiData.product_identity?.title || upcData.title || mergedFields.name || '';
    form.brand = researchData.product_identity?.brand || geminiData.product_identity?.brand || upcData.brand || '';
    form.model = researchData.product_identity?.model || geminiData.product_identity?.model || upcData.model || '';
    form.mpn = researchData.product_identity?.manufacturer_part_number || geminiData.product_identity?.mpn || upcData.mpn || '';
    form.upc = upcData.upc || props.queueItem.barcode || '';
    form.ean = upcData.ean || '';
    form.category_name = researchData.product_identity?.category || geminiData.product_identity?.category || upcData.category_name || '';

    // Description
    form.description = researchData.product_details?.description || geminiData.product_details?.description || upcData.description || '';

    // Pricing
    form.list_price = researchData.market_pricing?.recommended_list_price || geminiData.market_pricing?.recommended_list_price || upcData.market_price_avg || null;

    // Listing Content
    form.listing_title = researchData.listing_optimization?.suggested_title || geminiData.listing_optimization?.suggested_title || form.name;
    form.bullet_points = researchData.listing_optimization?.bullet_points || researchData.product_details?.key_features || geminiData.listing_optimization?.bullet_points || geminiData.product_details?.key_features || [];
    form.keywords = researchData.listing_optimization?.primary_keywords || geminiData.listing_optimization?.primary_keywords || [];
    // Use queue item's captured condition first (already in eBay format), then fall back to AI research
    form.condition = props.queueItem.condition
        || researchData.product_details?.condition?.toLowerCase()
        || geminiData.product_details?.condition?.toLowerCase()
        || upcData.condition?.toLowerCase()
        || 'NEW';
    form.condition_notes = props.queueItem.condition_description || '';

    // Item Specifics - merge specifications and eBay-specific item specifics
    const specs = researchData.product_details?.specifications || geminiData.product_details?.specifications || upcData.item_specifics || {};
    const ebaySpecs = researchData.listing_optimization?.platform_specific_tips?.ebay?.item_specifics || geminiData.listing_optimization?.platform_specific_tips?.ebay?.item_specifics || {};
    form.item_specifics = flattenSpecs({ ...specs, ...ebaySpecs });

    // Build structured listing description
    form.listing_description = buildListingDescription(researchData, geminiData);

    // Photos - select all by default
    form.selected_photos = props.queueItem.photos || [];
};

const buildListingDescription = (researchData, geminiData) => {
    const rd = researchData || {};
    const gd = geminiData || {};
    const sections = [];

    // Product name header
    const productName = rd.product_identity?.title || gd.product_identity?.title || '';
    if (productName) {
        sections.push(`<h3>${productName}</h3>`);
    }

    // Description paragraph
    const description = rd.listing_optimization?.description_template || rd.product_details?.description || gd.product_details?.description || '';
    if (description) {
        sections.push(`<p>${description}</p>`);
    }

    // Key Features
    const bullets = rd.listing_optimization?.bullet_points || rd.product_details?.key_features || gd.listing_optimization?.bullet_points || gd.product_details?.key_features || [];
    if (bullets.length > 0) {
        const items = bullets.map(b => `  <li>${b}</li>`).join('\n');
        sections.push(`<h4>Key Features</h4>\n<ul>\n${items}\n</ul>`);
    }

    // Specifications table
    const specs = rd.product_details?.specifications || gd.product_details?.specifications || {};
    const ebaySpecs = rd.listing_optimization?.platform_specific_tips?.ebay?.item_specifics || gd.listing_optimization?.platform_specific_tips?.ebay?.item_specifics || {};
    const allSpecs = flattenSpecs({ ...specs, ...ebaySpecs });
    const specEntries = Object.entries(allSpecs).filter(([, v]) => v);
    if (specEntries.length > 0) {
        const rows = specEntries.map(([k, v]) => `  <tr><td><strong>${k}</strong></td><td>${v}</td></tr>`).join('\n');
        sections.push(`<h4>Specifications</h4>\n<table>\n${rows}\n</table>`);
    }

    // Package Contents
    const packageContents = rd.product_details?.package_contents || gd.product_details?.package_contents || [];
    if (packageContents.length > 0) {
        const items = packageContents.map(item => `  <li>${item}</li>`).join('\n');
        sections.push(`<h4>What's In The Box</h4>\n<ul>\n${items}\n</ul>`);
    }

    // Warranty
    const warranty = rd.product_details?.warranty || gd.product_details?.warranty || '';
    if (warranty) {
        sections.push(`<h4>Warranty</h4>\n<p>${warranty}</p>`);
    }

    return sections.join('\n\n') || '';
};

initializeFormData();

// Set the hash on mount to reflect the current step
updateStepHash(currentStep.value);

// Step validation
const canProceedToStep = (step) => {
    switch (step) {
        case 2:
            return form.product_confirmed && form.name && form.quantity > 0;
        case 3:
            return form.list_price > 0;
        case 4:
            return form.listing_title && form.listing_description;
        default:
            return true;
    }
};

const nextStep = () => {
    if (currentStep.value < totalSteps && canProceedToStep(currentStep.value + 1)) {
        currentStep.value++;
        updateStepHash(currentStep.value);
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }
};

const prevStep = () => {
    if (currentStep.value > 1) {
        currentStep.value--;
        updateStepHash(currentStep.value);
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }
};

const goToStep = (step) => {
    if (step <= currentStep.value || canProceedToStep(step)) {
        currentStep.value = step;
        updateStepHash(currentStep.value);
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }
};

const refreshData = () => {
    router.reload({ only: ['queueItem'], preserveState: true, preserveScroll: true });
};

const saveDraft = () => {
    form.post(route('intake-queue.save-draft', props.queueItem.id), {
        preserveScroll: true,
        onSuccess: () => {
            // Show success message
        },
    });
};

const publishListing = (platform = 'ebay', options = {}) => {
    isPublishing.value = true;

    // For eBay with auto-publish, use the atomic endpoint that creates Product + dispatches eBay job
    if (platform === 'ebay' && options.ebay_auto_publish) {
        form.transform((data) => ({
            ...data,
            publish_to: platform,
            ebay_auto_publish: true,
            ebay_options: options.ebay_options || {},
        })).post(route('intake-queue.publish-with-ebay', props.queueItem.id), {
            preserveScroll: true,
            onSuccess: () => {
                // Flash message will be displayed by the component
                console.log('eBay publish successful');
            },
            onError: (errors) => {
                console.error('eBay publish failed:', errors);
                // Errors will be displayed by the validation error component
            },
            onFinish: () => {
                isPublishing.value = false;
            },
        });
    } else {
        // Standard publish for other platforms or non-eBay
        form.transform((data) => ({
            ...data,
            publish_to: platform,
        })).post(route('intake-queue.publish', props.queueItem.id), {
            preserveScroll: true,
            onSuccess: () => {
                // Redirect to success page or next item
            },
            onFinish: () => {
                isPublishing.value = false;
            },
        });
    }
};

// Progress calculation
const progressPercent = computed(() => {
    return (currentStep.value / totalSteps) * 100;
});
</script>

<template>
    <div class="max-w-7xl mx-auto">
        <!-- Flash Messages -->
        <div v-if="page.props.flash?.success" class="mb-4 p-4 bg-green-50 dark:bg-green-900/20 border border-green-400 dark:border-green-600 text-green-700 dark:text-green-300 rounded-lg flex items-start">
            <svg class="w-5 h-5 mr-3 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
            </svg>
            <span>{{ page.props.flash.success }}</span>
        </div>
        <div v-if="page.props.flash?.error" class="mb-4 p-4 bg-red-50 dark:bg-red-900/20 border border-red-400 dark:border-red-600 text-red-700 dark:text-red-300 rounded-lg flex items-start">
            <svg class="w-5 h-5 mr-3 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
            </svg>
            <span>{{ page.props.flash.error }}</span>
        </div>

        <!-- Validation Errors -->
        <div v-if="Object.keys(page.props.errors || {}).length > 0" class="mb-4 p-4 bg-red-50 dark:bg-red-900/20 border border-red-400 dark:border-red-600 rounded-lg">
            <div class="flex items-start">
                <svg class="w-5 h-5 mr-3 mt-0.5 flex-shrink-0 text-red-600 dark:text-red-400" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                </svg>
                <div>
                    <h4 class="font-medium text-red-700 dark:text-red-300">Please fix the following errors:</h4>
                    <ul class="mt-2 list-disc list-inside text-red-600 dark:text-red-400 text-sm space-y-1">
                        <li v-for="(error, field) in page.props.errors" :key="field">{{ error }}</li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Progress Bar -->
        <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg mb-6 p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                    Listing Verification Wizard
                </h3>
                <span class="text-sm text-gray-500 dark:text-gray-400">
                    Step {{ currentStep }} of {{ totalSteps }}
                </span>
            </div>
            
            <!-- Progress Bar -->
            <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2 mb-4">
                <div
                    class="bg-indigo-600 h-2 rounded-full transition-all duration-300"
                    :style="{ width: progressPercent + '%' }"
                ></div>
            </div>
            
            <!-- Step Indicators -->
            <div class="flex items-center justify-between">
                <button
                    v-for="step in totalSteps"
                    :key="step"
                    @click="goToStep(step)"
                    :disabled="step > currentStep && !canProceedToStep(step)"
                    class="flex-1 group relative"
                >
                    <div class="flex items-center">
                        <div
                            :class="{
                                'bg-indigo-600 text-white': step === currentStep,
                                'bg-green-600 text-white': step < currentStep,
                                'bg-gray-300 dark:bg-gray-600 text-gray-600 dark:text-gray-400': step > currentStep,
                            }"
                            class="w-10 h-10 rounded-full flex items-center justify-center font-semibold text-sm transition-colors mx-auto"
                        >
                            <svg v-if="step < currentStep" class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                            </svg>
                            <span v-else>{{ step }}</span>
                        </div>
                        
                        <div
                            v-if="step < totalSteps"
                            :class="{
                                'bg-green-600': step < currentStep,
                                'bg-gray-300 dark:bg-gray-600': step >= currentStep,
                            }"
                            class="hidden sm:block flex-1 h-0.5 mx-2"
                        ></div>
                    </div>
                    
                    <div class="mt-2 text-xs font-medium text-center">
                        <span v-if="step === 1">Product</span>
                        <span v-else-if="step === 2">Pricing</span>
                        <span v-else-if="step === 3">Listing</span>
                        <span v-else>Review</span>
                    </div>
                </button>
            </div>
        </div>

        <!-- Step Content -->
        <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg">
            <Step1ProductVerification
                v-if="currentStep === 1"
                :queue-item="queueItem"
                :form="form"
                :locations="locations"
                @next="nextStep"
                @save-draft="saveDraft"
            />
            
            <Step2PriceVerification
                v-else-if="currentStep === 2"
                :queue-item="queueItem"
                :form="form"
                @next="nextStep"
                @prev="prevStep"
                @save-draft="saveDraft"
                @refresh-data="refreshData"
            />
            
            <Step3ListingVerification
                v-else-if="currentStep === 3"
                :queue-item="queueItem"
                :form="form"
                @next="nextStep"
                @prev="prevStep"
                @save-draft="saveDraft"
            />
            
            <Step4ReviewAndPublish
                v-else-if="currentStep === 4"
                :queue-item="queueItem"
                :form="form"
                :locations="locations"
                :is-publishing="isPublishing"
                @prev="prevStep"
                @publish="publishListing"
                @save-draft="saveDraft"
            />
        </div>

        <!-- Auto-save Indicator -->
        <div class="mt-4 text-center text-sm text-gray-500 dark:text-gray-400">
            <span class="inline-flex items-center">
                <svg class="animate-pulse w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                    <circle cx="10" cy="10" r="3" />
                </svg>
                Auto-saving drafts...
            </span>
        </div>
    </div>
</template>
