<script setup>
import { ref, computed, reactive } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import {
    CheckCircleIcon,
    DocumentTextIcon,
    CurrencyDollarIcon,
    ComputerDesktopIcon,
    PaperAirplaneIcon,
    ArrowRightIcon,
    ArrowLeftIcon,
    ExclamationCircleIcon,
} from '@heroicons/vue/24/outline';
import { CheckIcon } from '@heroicons/vue/24/solid';

const props = defineProps({
    supplier: Object,
    onboarding: Object,
    commissionStructures: Array,
    tiers: Array,
});

const currentStep = ref(props.onboarding.current_step);
const isSubmitting = ref(false);
const errors = ref({});

// Step icons mapping
const stepIcons = {
    1: DocumentTextIcon,
    2: DocumentTextIcon,
    3: CurrencyDollarIcon,
    4: ComputerDesktopIcon,
    5: PaperAirplaneIcon,
};

// Form data for each step
const step1Form = reactive({
    business_name: props.supplier.company_name || '',
    business_type: 'individual',
    tax_id: '',
    address: '',
    city: '',
    state: '',
    zip_code: '',
    country: 'United States',
    website: '',
    product_categories: [],
    average_monthly_items: null,
    agree_to_terms: false,
});

const step2Form = reactive({
    documents: [],
    business_license: null,
    tax_documents: null,
    insurance: null,
    identity: null,
});

const step3Form = reactive({
    commission_structure_id: props.commissionStructures?.[0]?.id || '',
    settlement_preference: 'monthly',
    payment_method: 'bank_transfer',
    bank_details: {
        account_name: '',
        routing_number: '',
        account_number: '',
    },
    paypal_email: '',
});

const step4Form = reactive({
    notification_preferences: {
        email: true,
        sms: false,
        push: true,
    },
});

const step5Form = reactive({
    submission_type: 'acknowledge',
    product_data: {
        name: '',
        sku: '',
        cost_price: null,
        description: '',
    },
});

const businessTypes = [
    { value: 'individual', label: 'Individual / Sole Proprietor' },
    { value: 'llc', label: 'LLC' },
    { value: 'corporation', label: 'Corporation' },
    { value: 'partnership', label: 'Partnership' },
];

const productCategories = [
    'Electronics', 'Clothing', 'Home & Garden', 'Sports & Outdoors',
    'Toys & Games', 'Books & Media', 'Automotive', 'Collectibles', 'Other',
];

const settlementOptions = [
    { value: 'weekly', label: 'Weekly', description: 'Get paid every week' },
    { value: 'bi_weekly', label: 'Bi-Weekly', description: 'Get paid every two weeks' },
    { value: 'monthly', label: 'Monthly', description: 'Get paid once per month' },
];

const paymentMethods = [
    { value: 'bank_transfer', label: 'Bank Transfer (ACH)', description: 'Direct deposit to your bank account' },
    { value: 'paypal', label: 'PayPal', description: 'Payment via PayPal' },
    { value: 'check', label: 'Check', description: 'Physical check mailed to you' },
];

const currentStepData = computed(() => props.onboarding.steps.find(s => s.step === currentStep.value));

const isStepComplete = (step) => {
    const stepData = props.onboarding.steps.find(s => s.step === step);
    return stepData?.complete || false;
};

const step2UploadErrors = computed(() => {
    if (!errors.value || typeof errors.value !== 'object') return [];
    return Object.entries(errors.value)
        .filter(([key]) => key === 'documents' || key.startsWith('documents.'))
        .map(([, msg]) => msg);
});

const canProceed = computed(() => {
    switch (currentStep.value) {
        case 1:
            return step1Form.business_name && step1Form.business_type &&
                   step1Form.address && step1Form.city && step1Form.state &&
                   step1Form.zip_code && step1Form.agree_to_terms;
        case 2:
            // At least one document uploaded or skip
            return true;
        case 3:
            return step3Form.commission_structure_id && step3Form.settlement_preference && step3Form.payment_method;
        case 4:
            return true;
        case 5:
            if (step5Form.submission_type === 'sample_product') {
                return !!step5Form.product_data?.name?.trim();
            }
            return step5Form.submission_type === 'acknowledge';
        default:
            return false;
    }
});

const submitStep = async () => {
    isSubmitting.value = true;
    errors.value = {};

    try {
        let endpoint = '';
        let formData = {};

        switch (currentStep.value) {
            case 1:
                endpoint = route('supplier.onboarding.step1');
                formData = step1Form;
                break;
            case 2:
                endpoint = route('supplier.onboarding.step2');
                // Handle file uploads separately
                submitStep2();
                return;
            case 3:
                endpoint = route('supplier.onboarding.step3');
                formData = step3Form;
                break;
            case 4:
                endpoint = route('supplier.onboarding.step4');
                formData = step4Form;
                break;
            case 5:
                endpoint = route('supplier.onboarding.step5');
                formData = step5Form;
                break;
        }

        router.post(endpoint, formData, {
            onSuccess: (page) => {
                if (page.props.flash?.redirect) {
                    window.location.href = page.props.flash.redirect;
                } else {
                    currentStep.value++;
                }
            },
            onError: (errs) => {
                errors.value = errs;
            },
            onFinish: () => {
                isSubmitting.value = false;
            },
        });
    } catch (error) {
        console.error('Step submission error:', error);
        isSubmitting.value = false;
    }
};

const DOCUMENT_TYPES = ['business_license', 'tax_documents', 'insurance', 'identity'];

const selectedDocuments = () => DOCUMENT_TYPES.filter((type) => step2Form[type]);

const submitStep2 = () => {
    const selected = selectedDocuments();

    if (selected.length === 0) {
        skipDocumentation();
        return;
    }

    const formData = new FormData();
    selected.forEach((type, index) => {
        formData.append(`documents[${index}][type]`, type);
        formData.append(`documents[${index}][file]`, step2Form[type]);
    });

    router.post(route('supplier.onboarding.step2'), formData, {
        forceFormData: true,
        onSuccess: (page) => {
            const next = page.props.onboarding?.current_step;
            if (next) {
                currentStep.value = next;
            }
        },
        onError: (errs) => {
            errors.value = errs;
        },
        onFinish: () => {
            isSubmitting.value = false;
        },
    });
};

const skipDocumentation = () => {
    isSubmitting.value = true;
    errors.value = {};

    router.post(route('supplier.onboarding.skip-docs'), {}, {
        onSuccess: (page) => {
            const next = page.props.onboarding?.current_step;
            currentStep.value = next ?? currentStep.value + 1;
        },
        onError: (errs) => {
            errors.value = errs;
        },
        onFinish: () => {
            isSubmitting.value = false;
        },
    });
};

const handleFileUpload = (event, docType) => {
    const file = event.target.files[0];
    if (file) {
        step2Form[docType] = file;
    }
};

const goBack = () => {
    if (currentStep.value > 1) {
        currentStep.value--;
    }
};

const formatCurrency = (value) => {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD',
        minimumFractionDigits: 0,
    }).format(value || 0);
};

const formatPercent = (value) => {
    return `${(value || 0).toFixed(1)}%`;
};
</script>

<template>
    <Head title="Supplier Onboarding" />

    <AppLayout>
        <div class="min-h-screen bg-gray-50 dark:bg-gray-900 py-8">
            <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
                <!-- Header -->
                <div class="text-center mb-8">
                    <h1 class="text-3xl font-bold text-gray-900 dark:text-white">
                        Welcome to the Supplier Portal
                    </h1>
                    <p class="mt-2 text-gray-600 dark:text-gray-400">
                        Complete these steps to start selling with us
                    </p>
                </div>

                <!-- Progress Steps -->
                <nav aria-label="Progress" class="mb-8">
                    <ol class="flex items-center justify-between">
                        <li v-for="step in onboarding.steps" :key="step.step" class="relative flex-1">
                            <div class="flex items-center">
                                <div
                                    :class="[
                                        'relative z-10 flex items-center justify-center w-10 h-10 rounded-full border-2 transition-colors',
                                        step.complete
                                            ? 'bg-green-600 border-green-600'
                                            : step.current
                                                ? 'bg-indigo-600 border-indigo-600'
                                                : 'bg-white dark:bg-gray-800 border-gray-300 dark:border-gray-600'
                                    ]"
                                >
                                    <CheckIcon v-if="step.complete" class="w-5 h-5 text-white" />
                                    <span v-else-if="step.current" class="text-white font-medium">{{ step.step }}</span>
                                    <span v-else class="text-gray-500 dark:text-gray-400 font-medium">{{ step.step }}</span>
                                </div>
                                <div v-if="step.step < 5" class="flex-1 h-0.5 mx-2"
                                    :class="step.complete ? 'bg-green-600' : 'bg-gray-300 dark:bg-gray-600'"
                                ></div>
                            </div>
                            <span class="absolute -bottom-6 left-0 text-xs text-gray-500 dark:text-gray-400 whitespace-nowrap">
                                {{ step.name }}
                            </span>
                        </li>
                    </ol>
                </nav>

                <!-- Step Content -->
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 mt-12">
                    <!-- Step 1: Application -->
                    <div v-if="currentStep === 1" class="p-8">
                        <div class="flex items-center mb-6">
                            <DocumentTextIcon class="w-8 h-8 text-indigo-600 mr-3" />
                            <h2 class="text-xl font-semibold text-gray-900 dark:text-white">Business Information</h2>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                    Business Name *
                                </label>
                                <input
                                    v-model="step1Form.business_name"
                                    type="text"
                                    class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                    placeholder="Your business name"
                                />
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                    Business Type *
                                </label>
                                <select
                                    v-model="step1Form.business_type"
                                    class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                >
                                    <option v-for="type in businessTypes" :key="type.value" :value="type.value">
                                        {{ type.label }}
                                    </option>
                                </select>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                    Tax ID / EIN (optional)
                                </label>
                                <input
                                    v-model="step1Form.tax_id"
                                    type="text"
                                    class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                    placeholder="XX-XXXXXXX"
                                />
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                    Website (optional)
                                </label>
                                <input
                                    v-model="step1Form.website"
                                    type="url"
                                    class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                    placeholder="https://yourwebsite.com"
                                />
                            </div>

                            <div class="md:col-span-2">
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                    Business Address *
                                </label>
                                <input
                                    v-model="step1Form.address"
                                    type="text"
                                    class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                    placeholder="Street address"
                                />
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                    City *
                                </label>
                                <input
                                    v-model="step1Form.city"
                                    type="text"
                                    class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                />
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                    State *
                                </label>
                                <input
                                    v-model="step1Form.state"
                                    type="text"
                                    class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                />
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                    ZIP Code *
                                </label>
                                <input
                                    v-model="step1Form.zip_code"
                                    type="text"
                                    class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                />
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                    Country *
                                </label>
                                <input
                                    v-model="step1Form.country"
                                    type="text"
                                    class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                />
                            </div>

                            <div class="md:col-span-2">
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Product Categories (select all that apply)
                                </label>
                                <div class="flex flex-wrap gap-2">
                                    <label
                                        v-for="category in productCategories"
                                        :key="category"
                                        class="inline-flex items-center"
                                    >
                                        <input
                                            type="checkbox"
                                            :value="category"
                                            v-model="step1Form.product_categories"
                                            class="rounded border-gray-300 text-indigo-600"
                                        />
                                        <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">{{ category }}</span>
                                    </label>
                                </div>
                            </div>

                            <div class="md:col-span-2">
                                <label class="flex items-start space-x-3">
                                    <input
                                        type="checkbox"
                                        v-model="step1Form.agree_to_terms"
                                        class="mt-1 rounded border-gray-300 text-indigo-600"
                                    />
                                    <span class="text-sm text-gray-700 dark:text-gray-300">
                                        I agree to the <a href="#" class="text-indigo-600 hover:underline">Supplier Terms and Conditions</a>
                                        and <a href="#" class="text-indigo-600 hover:underline">Privacy Policy</a> *
                                    </span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Step 2: Documentation -->
                    <div v-if="currentStep === 2" class="p-8">
                        <div class="flex items-center mb-6">
                            <DocumentTextIcon class="w-8 h-8 text-indigo-600 mr-3" />
                            <h2 class="text-xl font-semibold text-gray-900 dark:text-white">Documentation</h2>
                        </div>

                        <p class="text-gray-600 dark:text-gray-400 mb-6">
                            Upload the required documents to verify your business. You can skip this step for now and complete it later.
                        </p>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-lg p-6 text-center">
                                <DocumentTextIcon class="w-10 h-10 text-gray-400 mx-auto mb-3" />
                                <p class="text-sm font-medium text-gray-700 dark:text-gray-300">Business License</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mb-3">PDF, JPG, or PNG (max 10MB)</p>
                                <input
                                    type="file"
                                    @change="(e) => handleFileUpload(e, 'business_license')"
                                    accept=".pdf,.jpg,.jpeg,.png"
                                    class="hidden"
                                    :id="'file-business'"
                                />
                                <label
                                    for="file-business"
                                    class="cursor-pointer inline-flex items-center px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700"
                                >
                                    Upload
                                </label>
                                <p v-if="step2Form.business_license" class="mt-2 text-xs text-green-600">
                                    {{ step2Form.business_license.name }}
                                </p>
                            </div>

                            <div class="border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-lg p-6 text-center">
                                <DocumentTextIcon class="w-10 h-10 text-gray-400 mx-auto mb-3" />
                                <p class="text-sm font-medium text-gray-700 dark:text-gray-300">Tax Documents (W-9)</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mb-3">PDF, JPG, or PNG (max 10MB)</p>
                                <input
                                    type="file"
                                    @change="(e) => handleFileUpload(e, 'tax_documents')"
                                    accept=".pdf,.jpg,.jpeg,.png"
                                    class="hidden"
                                    :id="'file-tax'"
                                />
                                <label
                                    for="file-tax"
                                    class="cursor-pointer inline-flex items-center px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700"
                                >
                                    Upload
                                </label>
                                <p v-if="step2Form.tax_documents" class="mt-2 text-xs text-green-600">
                                    {{ step2Form.tax_documents.name }}
                                </p>
                            </div>

                            <div class="border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-lg p-6 text-center">
                                <DocumentTextIcon class="w-10 h-10 text-gray-400 mx-auto mb-3" />
                                <p class="text-sm font-medium text-gray-700 dark:text-gray-300">Insurance Certificate</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mb-3">Optional</p>
                                <input
                                    type="file"
                                    @change="(e) => handleFileUpload(e, 'insurance')"
                                    accept=".pdf,.jpg,.jpeg,.png"
                                    class="hidden"
                                    :id="'file-insurance'"
                                />
                                <label
                                    for="file-insurance"
                                    class="cursor-pointer inline-flex items-center px-4 py-2 bg-gray-200 dark:bg-gray-600 text-gray-700 dark:text-gray-300 text-sm font-medium rounded-lg hover:bg-gray-300 dark:hover:bg-gray-500"
                                >
                                    Upload
                                </label>
                                <p v-if="step2Form.insurance" class="mt-2 text-xs text-green-600">
                                    {{ step2Form.insurance.name }}
                                </p>
                            </div>

                            <div class="border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-lg p-6 text-center">
                                <DocumentTextIcon class="w-10 h-10 text-gray-400 mx-auto mb-3" />
                                <p class="text-sm font-medium text-gray-700 dark:text-gray-300">ID Verification</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mb-3">Driver's license or passport</p>
                                <input
                                    type="file"
                                    @change="(e) => handleFileUpload(e, 'identity')"
                                    accept=".pdf,.jpg,.jpeg,.png"
                                    class="hidden"
                                    :id="'file-identity'"
                                />
                                <label
                                    for="file-identity"
                                    class="cursor-pointer inline-flex items-center px-4 py-2 bg-gray-200 dark:bg-gray-600 text-gray-700 dark:text-gray-300 text-sm font-medium rounded-lg hover:bg-gray-300 dark:hover:bg-gray-500"
                                >
                                    Upload
                                </label>
                                <p v-if="step2Form.identity" class="mt-2 text-xs text-green-600">
                                    {{ step2Form.identity.name }}
                                </p>
                            </div>
                        </div>

                        <div v-if="step2UploadErrors.length" class="mt-4 p-4 bg-red-50 dark:bg-red-900/20 rounded-lg">
                            <p class="text-sm font-medium text-red-700 dark:text-red-300 mb-1">Upload failed</p>
                            <ul class="text-sm text-red-700 dark:text-red-300 list-disc list-inside">
                                <li v-for="(msg, i) in step2UploadErrors" :key="i">{{ msg }}</li>
                            </ul>
                        </div>

                        <div class="mt-6 p-4 bg-yellow-50 dark:bg-yellow-900/20 rounded-lg">
                            <div class="flex">
                                <ExclamationCircleIcon class="w-5 h-5 text-yellow-600 mr-2 flex-shrink-0" />
                                <p class="text-sm text-yellow-700 dark:text-yellow-300">
                                    You can skip this step and upload documents later. However, your first settlement may be delayed until verification is complete.
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Step 3: Commission Setup -->
                    <div v-if="currentStep === 3" class="p-8">
                        <div class="flex items-center mb-6">
                            <CurrencyDollarIcon class="w-8 h-8 text-indigo-600 mr-3" />
                            <h2 class="text-xl font-semibold text-gray-900 dark:text-white">Commission & Payment Setup</h2>
                        </div>

                        <div class="space-y-8">
                            <!-- Commission Structure -->
                            <div>
                                <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Select Commission Structure</h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <label
                                        v-for="structure in commissionStructures"
                                        :key="structure.id"
                                        :class="[
                                            'relative flex items-start p-4 rounded-lg border-2 cursor-pointer transition-colors',
                                            step3Form.commission_structure_id === structure.id
                                                ? 'border-indigo-600 bg-indigo-50 dark:bg-indigo-900/20'
                                                : 'border-gray-200 dark:border-gray-600 hover:border-gray-300'
                                        ]"
                                    >
                                        <input
                                            type="radio"
                                            :value="structure.id"
                                            v-model="step3Form.commission_structure_id"
                                            class="sr-only"
                                        />
                                        <div class="flex-1">
                                            <p class="font-medium text-gray-900 dark:text-white">{{ structure.name }}</p>
                                            <p class="text-sm text-gray-500 dark:text-gray-400">
                                                {{ formatPercent(structure.base_percentage) }} base rate
                                            </p>
                                            <p v-if="structure.description" class="text-xs text-gray-400 mt-1">
                                                {{ structure.description }}
                                            </p>
                                        </div>
                                        <CheckCircleIcon
                                            v-if="step3Form.commission_structure_id === structure.id"
                                            class="w-5 h-5 text-indigo-600"
                                        />
                                    </label>
                                </div>
                            </div>

                            <!-- Settlement Preference -->
                            <div>
                                <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Settlement Frequency</h3>
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                    <label
                                        v-for="option in settlementOptions"
                                        :key="option.value"
                                        :class="[
                                            'relative flex flex-col p-4 rounded-lg border-2 cursor-pointer transition-colors',
                                            step3Form.settlement_preference === option.value
                                                ? 'border-indigo-600 bg-indigo-50 dark:bg-indigo-900/20'
                                                : 'border-gray-200 dark:border-gray-600 hover:border-gray-300'
                                        ]"
                                    >
                                        <input
                                            type="radio"
                                            :value="option.value"
                                            v-model="step3Form.settlement_preference"
                                            class="sr-only"
                                        />
                                        <p class="font-medium text-gray-900 dark:text-white">{{ option.label }}</p>
                                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ option.description }}</p>
                                    </label>
                                </div>
                            </div>

                            <!-- Payment Method -->
                            <div>
                                <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Payment Method</h3>
                                <div class="space-y-4">
                                    <label
                                        v-for="method in paymentMethods"
                                        :key="method.value"
                                        :class="[
                                            'relative flex items-start p-4 rounded-lg border-2 cursor-pointer transition-colors',
                                            step3Form.payment_method === method.value
                                                ? 'border-indigo-600 bg-indigo-50 dark:bg-indigo-900/20'
                                                : 'border-gray-200 dark:border-gray-600 hover:border-gray-300'
                                        ]"
                                    >
                                        <input
                                            type="radio"
                                            :value="method.value"
                                            v-model="step3Form.payment_method"
                                            class="sr-only"
                                        />
                                        <div class="flex-1">
                                            <p class="font-medium text-gray-900 dark:text-white">{{ method.label }}</p>
                                            <p class="text-sm text-gray-500 dark:text-gray-400">{{ method.description }}</p>
                                        </div>
                                    </label>

                                    <!-- Bank Details (if bank transfer selected) -->
                                    <div v-if="step3Form.payment_method === 'bank_transfer'" class="mt-4 p-4 bg-gray-50 dark:bg-gray-700/50 rounded-lg space-y-4">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                                Account Holder Name
                                            </label>
                                            <input
                                                v-model="step3Form.bank_details.account_name"
                                                type="text"
                                                class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                            />
                                        </div>
                                        <div class="grid grid-cols-2 gap-4">
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                                    Routing Number
                                                </label>
                                                <input
                                                    v-model="step3Form.bank_details.routing_number"
                                                    type="text"
                                                    class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                                />
                                            </div>
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                                    Account Number
                                                </label>
                                                <input
                                                    v-model="step3Form.bank_details.account_number"
                                                    type="text"
                                                    class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                                />
                                            </div>
                                        </div>
                                    </div>

                                    <!-- PayPal Email -->
                                    <div v-if="step3Form.payment_method === 'paypal'" class="mt-4 p-4 bg-gray-50 dark:bg-gray-700/50 rounded-lg">
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                            PayPal Email Address
                                        </label>
                                        <input
                                            v-model="step3Form.paypal_email"
                                            type="email"
                                            class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                            placeholder="your@email.com"
                                        />
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Step 4: Portal Access -->
                    <div v-if="currentStep === 4" class="p-8">
                        <div class="flex items-center mb-6">
                            <ComputerDesktopIcon class="w-8 h-8 text-indigo-600 mr-3" />
                            <h2 class="text-xl font-semibold text-gray-900 dark:text-white">Portal Setup</h2>
                        </div>

                        <div class="space-y-8">
                            <!-- Notification Preferences -->
                            <div>
                                <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Notification Preferences</h3>
                                <p class="text-gray-600 dark:text-gray-400 mb-4">
                                    Choose how you'd like to receive updates about your sales, settlements, and important announcements.
                                </p>
                                <div class="space-y-3">
                                    <label class="flex items-center">
                                        <input
                                            type="checkbox"
                                            v-model="step4Form.notification_preferences.email"
                                            class="rounded border-gray-300 text-indigo-600"
                                        />
                                        <span class="ml-3 text-gray-700 dark:text-gray-300">Email notifications</span>
                                    </label>
                                    <label class="flex items-center">
                                        <input
                                            type="checkbox"
                                            v-model="step4Form.notification_preferences.sms"
                                            class="rounded border-gray-300 text-indigo-600"
                                        />
                                        <span class="ml-3 text-gray-700 dark:text-gray-300">SMS notifications</span>
                                    </label>
                                    <label class="flex items-center">
                                        <input
                                            type="checkbox"
                                            v-model="step4Form.notification_preferences.push"
                                            class="rounded border-gray-300 text-indigo-600"
                                        />
                                        <span class="ml-3 text-gray-700 dark:text-gray-300">Push notifications</span>
                                    </label>
                                </div>
                            </div>

                            <!-- Quick orientation -->
                            <div class="bg-indigo-50 dark:bg-indigo-900/20 rounded-lg p-6">
                                <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">What you'll find in the portal</h3>
                                <div class="space-y-4">
                                    <div class="flex items-start">
                                        <div class="flex-shrink-0 w-8 h-8 rounded-full bg-indigo-100 dark:bg-indigo-800 flex items-center justify-center mr-3">
                                            <span class="text-indigo-600 dark:text-indigo-300 font-medium">1</span>
                                        </div>
                                        <div>
                                            <p class="font-medium text-gray-900 dark:text-white">Dashboard</p>
                                            <p class="text-sm text-gray-600 dark:text-gray-400">
                                                Sales, performance, and pending settlements at a glance.
                                            </p>
                                        </div>
                                    </div>
                                    <div class="flex items-start">
                                        <div class="flex-shrink-0 w-8 h-8 rounded-full bg-indigo-100 dark:bg-indigo-800 flex items-center justify-center mr-3">
                                            <span class="text-indigo-600 dark:text-indigo-300 font-medium">2</span>
                                        </div>
                                        <div>
                                            <p class="font-medium text-gray-900 dark:text-white">Submit Products</p>
                                            <p class="text-sm text-gray-600 dark:text-gray-400">
                                                Send products for listing through the product submission portal.
                                            </p>
                                        </div>
                                    </div>
                                    <div class="flex items-start">
                                        <div class="flex-shrink-0 w-8 h-8 rounded-full bg-indigo-100 dark:bg-indigo-800 flex items-center justify-center mr-3">
                                            <span class="text-indigo-600 dark:text-indigo-300 font-medium">3</span>
                                        </div>
                                        <div>
                                            <p class="font-medium text-gray-900 dark:text-white">Performance</p>
                                            <p class="text-sm text-gray-600 dark:text-gray-400">
                                                Track metrics to earn tier upgrades and better rates.
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Step 5: First Submission -->
                    <div v-if="currentStep === 5" class="p-8">
                        <div class="flex items-center mb-6">
                            <PaperAirplaneIcon class="w-8 h-8 text-indigo-600 mr-3" />
                            <h2 class="text-xl font-semibold text-gray-900 dark:text-white">Final Step</h2>
                        </div>

                        <p class="text-gray-600 dark:text-gray-400 mb-6 max-w-2xl">
                            You can submit your first product for review now, or finish onboarding and submit later from the supplier portal.
                        </p>

                        <div class="space-y-3">
                            <label
                                :class="[
                                    'block p-4 rounded-lg border-2 cursor-pointer transition-colors',
                                    step5Form.submission_type === 'acknowledge'
                                        ? 'border-indigo-600 bg-indigo-50 dark:bg-indigo-900/20'
                                        : 'border-gray-200 dark:border-gray-600 hover:border-gray-300'
                                ]"
                            >
                                <input
                                    type="radio"
                                    value="acknowledge"
                                    v-model="step5Form.submission_type"
                                    class="sr-only"
                                />
                                <div class="flex items-center">
                                    <CheckCircleIcon v-if="step5Form.submission_type === 'acknowledge'" class="w-5 h-5 text-indigo-600 mr-3" />
                                    <div v-else class="w-5 h-5 rounded-full border-2 border-gray-300 mr-3"></div>
                                    <div>
                                        <p class="font-medium text-gray-900 dark:text-white">Skip for now</p>
                                        <p class="text-sm text-gray-500 dark:text-gray-400">
                                            Finish onboarding and submit products later from the portal.
                                        </p>
                                    </div>
                                </div>
                            </label>

                            <label
                                :class="[
                                    'block p-4 rounded-lg border-2 cursor-pointer transition-colors',
                                    step5Form.submission_type === 'sample_product'
                                        ? 'border-indigo-600 bg-indigo-50 dark:bg-indigo-900/20'
                                        : 'border-gray-200 dark:border-gray-600 hover:border-gray-300'
                                ]"
                            >
                                <input
                                    type="radio"
                                    value="sample_product"
                                    v-model="step5Form.submission_type"
                                    class="sr-only"
                                />
                                <div class="flex items-center">
                                    <CheckCircleIcon v-if="step5Form.submission_type === 'sample_product'" class="w-5 h-5 text-indigo-600 mr-3" />
                                    <div v-else class="w-5 h-5 rounded-full border-2 border-gray-300 mr-3"></div>
                                    <div>
                                        <p class="font-medium text-gray-900 dark:text-white">Submit a sample product</p>
                                        <p class="text-sm text-gray-500 dark:text-gray-400">
                                            Send your first product to the review queue right now.
                                        </p>
                                    </div>
                                </div>
                            </label>
                        </div>

                        <div v-if="step5Form.submission_type === 'sample_product'" class="mt-6 space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                    Product name <span class="text-red-500">*</span>
                                </label>
                                <input
                                    type="text"
                                    v-model="step5Form.product_data.name"
                                    maxlength="255"
                                    class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                                    placeholder="e.g. Vintage Leather Wallet"
                                />
                                <p v-if="errors['product_data.name']" class="mt-1 text-xs text-red-600">{{ errors['product_data.name'] }}</p>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">SKU</label>
                                    <input
                                        type="text"
                                        v-model="step5Form.product_data.sku"
                                        maxlength="100"
                                        class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                                    />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Cost price</label>
                                    <input
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        v-model="step5Form.product_data.cost_price"
                                        class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                                    />
                                </div>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Description</label>
                                <textarea
                                    v-model="step5Form.product_data.description"
                                    rows="3"
                                    maxlength="5000"
                                    class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                                />
                            </div>

                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                You can add images and finer details after onboarding from the Submit Product page.
                            </p>
                        </div>
                    </div>

                    <!-- Navigation Buttons -->
                    <div class="px-8 py-4 bg-gray-50 dark:bg-gray-700/50 border-t border-gray-200 dark:border-gray-700 flex justify-between">
                        <button
                            v-if="currentStep > 1"
                            @click="goBack"
                            class="inline-flex items-center px-4 py-2 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-600 rounded-lg"
                        >
                            <ArrowLeftIcon class="w-4 h-4 mr-2" />
                            Back
                        </button>
                        <div v-else></div>

                        <div class="flex items-center gap-3">
                            <button
                                v-if="currentStep === 2"
                                @click="skipDocumentation"
                                :disabled="isSubmitting"
                                type="button"
                                class="inline-flex items-center px-4 py-2 text-sm text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-600 rounded-lg disabled:opacity-50"
                            >
                                Skip for now
                            </button>
                            <button
                                @click="submitStep"
                                :disabled="!canProceed || isSubmitting"
                                class="inline-flex items-center px-6 py-2 bg-indigo-600 text-white font-medium rounded-lg hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed"
                            >
                                {{ isSubmitting ? 'Saving...' : (currentStep === 5 ? 'Complete Setup' : 'Continue') }}
                                <ArrowRightIcon v-if="!isSubmitting && currentStep < 5" class="w-4 h-4 ml-2" />
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Progress Percentage -->
                <div class="mt-4 text-center">
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        {{ onboarding.completion_percentage }}% complete
                    </p>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
