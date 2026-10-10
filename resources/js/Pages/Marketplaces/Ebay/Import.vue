<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import SkuParserConfig from '@/Components/Marketplaces/SkuParserConfig.vue';

const props = defineProps({
    integrationStatus: {
        type: Object,
        default: () => ({})
    },
    activeImport: {
        type: Object,
        default: null
    },
    lastCompleted: {
        type: Object,
        default: null
    },
    credentialId: {
        type: [Number, String],
        default: null
    },
    skuParserConfig: {
        type: Object,
        default: null
    },
    skuParsers: {
        type: Array,
        default: () => []
    },
    locations: {
        type: Array,
        default: () => []
    }
});

const form = useForm({
    skip_existing_skus: true,
    update_existing_products: false,
    default_status: 'draft',
    preserve_price: false,
    preserve_description: false,
    location_id: props.locations?.[0]?.id ?? null,
});

const activeTab = ref('api'); // 'api' | 'csv'

const csvForm = useForm({
    csv: null,
    default_status: 'draft',
    location_id: props.locations?.[0]?.id ?? null,
});

const csvFileName = computed(() => csvForm.csv?.name ?? '');

const onCsvSelected = (e) => {
    const f = e.target.files?.[0];
    csvForm.csv = f ?? null;
};

const startCsvImport = () => {
    if (!csvForm.csv) return;
    importSubmitted.value = true;
    csvForm.post(route('marketplaces.ebay.import.csv'), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            startPolling();
            csvForm.reset('csv');
        },
        onError: () => {
            importSubmitted.value = false;
        },
    });
};

const importStatus = ref(props.activeImport);
const pollInterval = ref(null);
const showErrors = ref(false);
const showDebugInfo = ref(false);

// Elapsed time
const elapsedSeconds = ref(0);
const elapsedInterval = ref(null);
const importStartTime = ref(null);

// Debug state
const debugMode = ref(false);
const debugData = ref(null);
const debugLoading = ref(false);
const pollLog = ref([]);

const isConnected = computed(() => props.integrationStatus?.connected);
const isImporting = computed(() => {
    const status = importStatus.value?.status;
    return status === 'pending' || status === 'processing';
});

const isDone = computed(() => {
    const status = importStatus.value?.status;
    return status === 'completed' || status === 'failed' || status === 'cancelled';
});

const progressPercent = computed(() => {
    if (!importStatus.value || !importStatus.value.total_items) return 0;
    return Math.round((importStatus.value.processed_items / importStatus.value.total_items) * 100);
});

const isCsvSource = computed(() => importStatus.value?.operation_type === 'ebay_csv_import');

const statusPhaseText = computed(() => {
    if (!importStatus.value) return '';
    const s = importStatus.value;
    if (s.status === 'pending') {
        return isCsvSource.value ? 'Queued, waiting for worker...' : 'Connecting to eBay...';
    }
    if (s.status === 'processing') {
        if (!s.total_items) {
            return isCsvSource.value ? 'Reading CSV...' : 'Fetching listings...';
        }
        return `Processing items (${s.processed_items ?? 0} of ${s.total_items})...`;
    }
    return '';
});

const formattedElapsed = computed(() => {
    const s = elapsedSeconds.value;
    const min = Math.floor(s / 60);
    const sec = s % 60;
    return min > 0 ? `${min}m ${sec}s` : `${sec}s`;
});

const buttonText = computed(() => {
    if (form.processing) return 'Starting...';
    if (isImporting.value) return 'Import Running...';
    if (isDone.value) return 'Start New Import';
    return 'Start Import';
});

const buttonDisabled = computed(() => {
    return !isConnected.value || isImporting.value || form.processing || importSubmitted.value;
});

const hasErrors = computed(() => {
    return (importStatus.value?.errors && importStatus.value.errors.length > 0) ||
           (importStatus.value?.failed_items > 0);
});

const lastErrorMessage = computed(() => {
    const errors = importStatus.value?.errors;
    if (!errors || errors.length === 0) return null;
    const last = errors[errors.length - 1];
    return `${last.item_id ?? 'Unknown'}: ${last.message}`;
});

const importSubmitted = ref(false);

const startImport = () => {
    importSubmitted.value = true;
    form.post(route('marketplaces.ebay.import.start'), {
        preserveScroll: true,
        onSuccess: () => {
            startPolling();
        },
        onError: () => {
            importSubmitted.value = false;
        }
    });
};

const cancelImport = () => {
    router.post(route('marketplaces.ebay.import.cancel'), {}, {
        preserveScroll: true,
    });
};

const canResume = computed(() => {
    if (isImporting.value || importSubmitted.value) return false;
    const job = props.lastCompleted;
    if (!job || job.status !== 'failed') return false;
    const lastPage = job.parameters?.last_completed_page;
    const totalPages = job.parameters?.total_pages;
    return lastPage && totalPages && lastPage < totalPages;
});

const resumeInfo = computed(() => {
    const job = props.lastCompleted;
    if (!job) return null;
    return {
        page: (job.parameters?.last_completed_page ?? 0) + 1,
        totalPages: job.parameters?.total_pages ?? 0,
    };
});

const resumeImport = () => {
    importSubmitted.value = true;
    router.post(route('marketplaces.ebay.import.resume'), {}, {
        preserveScroll: true,
        onSuccess: () => {
            startPolling();
        },
        onError: () => {
            importSubmitted.value = false;
        }
    });
};

const startElapsedTimer = () => {
    if (elapsedInterval.value) return;
    if (!importStartTime.value) {
        importStartTime.value = Date.now();
    }
    elapsedInterval.value = setInterval(() => {
        elapsedSeconds.value = Math.round((Date.now() - importStartTime.value) / 1000);
    }, 1000);
};

const stopElapsedTimer = () => {
    if (elapsedInterval.value) {
        clearInterval(elapsedInterval.value);
        elapsedInterval.value = null;
    }
};

const pollStatus = async () => {
    const startTime = performance.now();
    const url = debugMode.value
        ? route('marketplaces.ebay.import.status') + '?debug=1'
        : route('marketplaces.ebay.import.status');

    try {
        const response = await fetch(url);
        const elapsed = Math.round(performance.now() - startTime);
        const data = await response.json();

        if (debugMode.value) {
            pollLog.value.push({
                time: new Date().toISOString(),
                status: response.status,
                elapsed_ms: elapsed,
                data_status: data.status,
                total_items: data.total_items,
                processed_items: data.processed_items,
                has_debug_log: !!(data.debug_log && data.debug_log.length),
            });
            if (pollLog.value.length > 50) {
                pollLog.value = pollLog.value.slice(-50);
            }
        }

        if (data.status !== 'none') {
            importStatus.value = data;
        }
        if (data.status === 'completed' || data.status === 'failed' || data.status === 'cancelled') {
            stopPolling();
            stopElapsedTimer();
            importSubmitted.value = false;
        }
    } catch (e) {
        if (debugMode.value) {
            const elapsed = Math.round(performance.now() - startTime);
            pollLog.value.push({
                time: new Date().toISOString(),
                status: 'error',
                elapsed_ms: elapsed,
                error: e.message,
            });
            if (pollLog.value.length > 50) {
                pollLog.value = pollLog.value.slice(-50);
            }
        }
    }
};

const startPolling = () => {
    if (pollInterval.value) return;
    pollInterval.value = setInterval(pollStatus, 3000);
    startElapsedTimer();
};

const stopPolling = () => {
    if (pollInterval.value) {
        clearInterval(pollInterval.value);
        pollInterval.value = null;
    }
};

// Debug functions
const toggleDebug = () => {
    debugMode.value = !debugMode.value;
    if (debugMode.value && !debugData.value) {
        fetchDebugData();
    }
};

const fetchDebugData = async () => {
    debugLoading.value = true;
    try {
        const response = await fetch(route('marketplaces.ebay.import.debug'));
        debugData.value = await response.json();
    } catch (e) {
        debugData.value = { error: e.message };
    } finally {
        debugLoading.value = false;
    }
};

const testConnectivity = async () => {
    debugLoading.value = true;
    try {
        const response = await fetch(route('marketplaces.ebay.import.debug'));
        debugData.value = await response.json();
    } catch (e) {
        debugData.value = { ...debugData.value, connectivity: { status: 'error', message: e.message } };
    } finally {
        debugLoading.value = false;
    }
};

const handleKeydown = (e) => {
    // e.key returns '>' when shift is held with '.', so check for both
    if (e.ctrlKey && e.shiftKey && (e.key === '.' || e.key === '>')) {
        e.preventDefault();
        toggleDebug();
    }
};

// Auto-expand errors when status is failed
watch(() => importStatus.value?.status, (newStatus) => {
    if (newStatus === 'failed') {
        showErrors.value = true;
    }
});

onMounted(() => {
    if (isImporting.value) {
        importStartTime.value = importStatus.value?.started_at
            ? new Date(importStatus.value.started_at).getTime()
            : Date.now();
        startPolling();
    }
    window.addEventListener('keydown', handleKeydown);
});

onUnmounted(() => {
    stopPolling();
    stopElapsedTimer();
    window.removeEventListener('keydown', handleKeydown);
});
</script>

<template>
    <AppLayout title="Import eBay Inventory">
        <Head title="Import eBay Inventory" />

        <div class="max-w-4xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
            <!-- Breadcrumb -->
            <nav class="flex mb-6" aria-label="Breadcrumb">
                <ol class="flex items-center space-x-2 text-sm text-gray-500 dark:text-gray-400">
                    <li><Link :href="route('marketplaces.ebay.index')" class="hover:text-gray-700 dark:hover:text-gray-200">Marketplaces</Link></li>
                    <li><i class="fas fa-chevron-right text-xs mx-1"></i></li>
                    <li><Link :href="route('marketplaces.ebay.index')" class="hover:text-gray-700 dark:hover:text-gray-200">eBay</Link></li>
                    <li><i class="fas fa-chevron-right text-xs mx-1"></i></li>
                    <li class="text-gray-900 dark:text-white font-medium">Import Inventory</li>
                </ol>
            </nav>

            <!-- Header -->
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white mb-2">Import Store Inventory</h1>
            <p class="text-sm text-gray-600 dark:text-gray-400 mb-6">
                Pull your active eBay store listings into the product catalog.
            </p>

            <!-- Connection Check -->
            <div v-if="!isConnected" class="mb-6 p-4 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg">
                <div class="flex items-center gap-2">
                    <i class="fas fa-exclamation-circle text-red-600 dark:text-red-400"></i>
                    <p class="text-sm text-red-700 dark:text-red-300">
                        Your eBay account is not connected. Please
                        <Link :href="route('marketplaces.ebay.setup')" class="underline font-medium">connect your account</Link>
                        before importing.
                    </p>
                </div>
            </div>

            <!-- SKU Parser Config -->
            <div v-if="credentialId && isConnected" class="mb-6">
                <SkuParserConfig
                    :credential-id="credentialId"
                    :initial-config="skuParserConfig"
                    :initial-parsers="skuParsers"
                />
            </div>

            <!-- Source Tabs -->
            <div class="mb-4 border-b border-gray-200 dark:border-gray-700">
                <nav class="flex gap-2" aria-label="Import source tabs">
                    <button
                        type="button"
                        @click="activeTab = 'api'"
                        :class="[
                            'px-4 py-2 text-sm font-medium border-b-2 transition-colors',
                            activeTab === 'api'
                                ? 'border-blue-600 text-blue-600 dark:text-blue-400 dark:border-blue-400'
                                : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200'
                        ]"
                    >
                        From eBay API
                    </button>
                    <button
                        type="button"
                        @click="activeTab = 'csv'"
                        :class="[
                            'px-4 py-2 text-sm font-medium border-b-2 transition-colors',
                            activeTab === 'csv'
                                ? 'border-blue-600 text-blue-600 dark:text-blue-400 dark:border-blue-400'
                                : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200'
                        ]"
                    >
                        From CSV upload
                    </button>
                </nav>
            </div>

            <!-- Import Form (always visible) -->
            <div v-if="activeTab === 'api'" class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-6 mb-6">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Import Options</h2>

                <div class="space-y-4">
                    <label class="flex items-center gap-3">
                        <input
                            type="checkbox"
                            v-model="form.skip_existing_skus"
                            :disabled="isImporting || form.update_existing_products"
                            class="rounded border-gray-300 dark:border-gray-600 text-blue-600 focus:ring-blue-500 disabled:opacity-50"
                        />
                        <span class="text-sm text-gray-700 dark:text-gray-300" :class="{ 'opacity-50': isImporting || form.update_existing_products }">Skip products with existing SKUs</span>
                    </label>

                    <label class="flex items-center gap-3">
                        <input
                            type="checkbox"
                            v-model="form.update_existing_products"
                            :disabled="isImporting"
                            class="rounded border-gray-300 dark:border-gray-600 text-blue-600 focus:ring-blue-500 disabled:opacity-50"
                        />
                        <span class="text-sm text-gray-700 dark:text-gray-300" :class="{ 'opacity-50': isImporting }">Update existing products from eBay data</span>
                    </label>

                    <!-- Sub-options for update existing products -->
                    <div v-if="form.update_existing_products" class="ml-8 space-y-3 p-3 bg-gray-50 dark:bg-gray-700/50 rounded-lg border border-gray-200 dark:border-gray-600">
                        <label class="flex items-center gap-3">
                            <input
                                type="checkbox"
                                v-model="form.preserve_price"
                                :disabled="isImporting"
                                class="rounded border-gray-300 dark:border-gray-600 text-blue-600 focus:ring-blue-500 disabled:opacity-50"
                            />
                            <span class="text-sm text-gray-700 dark:text-gray-300" :class="{ 'opacity-50': isImporting }">Keep local selling price</span>
                        </label>
                        <label class="flex items-center gap-3">
                            <input
                                type="checkbox"
                                v-model="form.preserve_description"
                                :disabled="isImporting"
                                class="rounded border-gray-300 dark:border-gray-600 text-blue-600 focus:ring-blue-500 disabled:opacity-50"
                            />
                            <span class="text-sm text-gray-700 dark:text-gray-300" :class="{ 'opacity-50': isImporting }">Keep local description</span>
                        </label>
                    </div>

                    <!-- Default status select -->
                    <div class="flex items-center gap-3">
                        <label for="default_status" class="text-sm text-gray-700 dark:text-gray-300" :class="{ 'opacity-50': isImporting }">Import products as:</label>
                        <select
                            id="default_status"
                            v-model="form.default_status"
                            :disabled="isImporting"
                            class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 text-sm focus:ring-blue-500 focus:border-blue-500 disabled:opacity-50"
                        >
                            <option value="draft">Draft</option>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>

                    <!-- Labeling location -->
                    <div v-if="locations.length" class="flex items-center gap-3">
                        <label for="location_id" class="text-sm text-gray-700 dark:text-gray-300" :class="{ 'opacity-50': isImporting }">Labeling work-order location:</label>
                        <select
                            id="location_id"
                            v-model="form.location_id"
                            :disabled="isImporting"
                            class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 text-sm focus:ring-blue-500 focus:border-blue-500 disabled:opacity-50"
                        >
                            <option :value="null">No work order (skip labeling)</option>
                            <option v-for="loc in locations" :key="loc.id" :value="loc.id">{{ loc.name }}</option>
                        </select>
                    </div>
                    <p v-if="locations.length" class="text-xs text-gray-500 dark:text-gray-400 -mt-2">
                        Newly created products will be added to a labeling work order targeting this location.
                    </p>

                    <div class="p-3 bg-blue-50 dark:bg-blue-900/20 rounded-lg text-sm text-blue-700 dark:text-blue-300">
                        <i class="fas fa-info-circle mr-1"></i>
                        New products will be imported with the selected status. Existing products are only updated when "Update existing products" is enabled.
                    </div>
                </div>

                <div class="mt-6">
                    <button
                        @click="startImport"
                        :disabled="buttonDisabled"
                        class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed text-white rounded-lg text-sm font-medium transition-colors"
                    >
                        <i v-if="form.processing || isImporting" class="fas fa-spinner fa-spin"></i>
                        <i v-else class="fas fa-download"></i>
                        {{ buttonText }}
                    </button>
                </div>

                <div v-if="form.errors.ebay" class="mt-3 text-sm text-red-600 dark:text-red-400">
                    {{ form.errors.ebay }}
                </div>
            </div>

            <!-- CSV Import Form -->
            <div v-if="activeTab === 'csv'" class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-6 mb-6">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">CSV Upload</h2>

                <div class="mb-4 p-3 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-lg text-sm text-amber-800 dark:text-amber-300">
                    <p class="font-medium mb-1">Stub-mode import (testing tool)</p>
                    <p>
                        Creates Product and EbayListing rows from CSV data only. Price, quantity, images, and condition are
                        placeholders. Use this to validate the SKU parser and warehouse hierarchy build on develop without
                        depending on a working eBay API. Not for production listings.
                    </p>
                </div>

                <div class="mb-4 text-sm text-gray-700 dark:text-gray-300">
                    <p class="mb-2">Upload an eBay Seller Hub "All active listings" CSV. Required columns:</p>
                    <ul class="list-disc list-inside text-xs text-gray-600 dark:text-gray-400 space-y-0.5">
                        <li><code class="font-mono">Item number</code></li>
                        <li><code class="font-mono">Title</code></li>
                        <li><code class="font-mono">Custom label (SKU)</code> &mdash; the SKU parser reads this column</li>
                    </ul>
                </div>

                <div class="space-y-4">
                    <label class="block">
                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">CSV file</span>
                        <input
                            type="file"
                            accept=".csv,text/csv"
                            @change="onCsvSelected"
                            :disabled="isImporting || csvForm.processing"
                            class="mt-1 block w-full text-sm text-gray-700 dark:text-gray-300 file:mr-3 file:py-2 file:px-3 file:rounded-md file:border-0 file:text-sm file:font-medium file:bg-blue-50 dark:file:bg-blue-900/30 file:text-blue-700 dark:file:text-blue-300 hover:file:bg-blue-100 disabled:opacity-50"
                        />
                        <span v-if="csvFileName" class="mt-1 block text-xs text-gray-500 dark:text-gray-400">{{ csvFileName }}</span>
                    </label>

                    <div class="flex items-center gap-3">
                        <label for="csv_default_status" class="text-sm text-gray-700 dark:text-gray-300">Import products as:</label>
                        <select
                            id="csv_default_status"
                            v-model="csvForm.default_status"
                            :disabled="isImporting || csvForm.processing"
                            class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 text-sm focus:ring-blue-500 focus:border-blue-500 disabled:opacity-50"
                        >
                            <option value="draft">Draft</option>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>

                    <div v-if="locations.length" class="flex items-center gap-3">
                        <label for="csv_location_id" class="text-sm text-gray-700 dark:text-gray-300">Labeling work-order location:</label>
                        <select
                            id="csv_location_id"
                            v-model="csvForm.location_id"
                            :disabled="isImporting || csvForm.processing"
                            class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 text-sm focus:ring-blue-500 focus:border-blue-500 disabled:opacity-50"
                        >
                            <option :value="null">No work order (skip labeling)</option>
                            <option v-for="loc in locations" :key="loc.id" :value="loc.id">{{ loc.name }}</option>
                        </select>
                    </div>
                </div>

                <div class="mt-6">
                    <button
                        @click="startCsvImport"
                        :disabled="!csvForm.csv || isImporting || csvForm.processing || importSubmitted"
                        class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed text-white rounded-lg text-sm font-medium transition-colors"
                    >
                        <i v-if="csvForm.processing || isImporting" class="fas fa-spinner fa-spin"></i>
                        <i v-else class="fas fa-upload"></i>
                        {{ csvForm.processing ? 'Uploading...' : (isImporting ? 'Import Running...' : 'Upload &amp; Start Import') }}
                    </button>
                </div>

                <div v-if="csvForm.errors.csv" class="mt-3 text-sm text-red-600 dark:text-red-400">
                    {{ csvForm.errors.csv }}
                </div>
                <div v-if="csvForm.errors.ebay" class="mt-1 text-sm text-red-600 dark:text-red-400">
                    {{ csvForm.errors.ebay }}
                </div>
            </div>

            <!-- Progress Section -->
            <div v-if="isImporting" class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-6 mb-6">
                <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center gap-3">
                        <i class="fas fa-spinner fa-spin text-blue-600 dark:text-blue-400"></i>
                        <div>
                            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Import in Progress</h2>
                            <p class="text-sm text-gray-500 dark:text-gray-400">{{ statusPhaseText }}</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-4">
                        <span class="text-sm text-gray-500 dark:text-gray-400 font-mono">{{ formattedElapsed }}</span>
                        <button
                            @click="cancelImport"
                            class="text-sm text-red-600 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300"
                        >
                            Cancel Import
                        </button>
                    </div>
                </div>

                <!-- Progress Bar -->
                <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-3 mb-3">
                    <div
                        class="bg-blue-600 h-3 rounded-full transition-all duration-500"
                        :style="{ width: progressPercent + '%' }"
                    ></div>
                </div>

                <div class="flex items-center justify-between text-sm text-gray-600 dark:text-gray-400">
                    <span>
                        Processed {{ importStatus?.processed_items ?? 0 }} of {{ importStatus?.total_items ?? '...' }} items
                    </span>
                    <span>{{ progressPercent }}%</span>
                </div>

                <div v-if="importStatus?.failed_items > 0" class="mt-2 flex items-center gap-2 text-sm">
                    <span class="inline-flex items-center gap-1 px-2 py-0.5 bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400 rounded-full">
                        <i class="fas fa-exclamation-triangle text-xs"></i>
                        {{ importStatus.failed_items }} failed
                    </span>
                    <span v-if="lastErrorMessage" class="text-xs text-red-500 dark:text-red-400 truncate max-w-md">
                        Last: {{ lastErrorMessage }}
                    </span>
                </div>
            </div>

            <!-- Results Section -->
            <div v-if="importStatus && isDone" class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-6 mb-6">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
                    Import {{ importStatus.status === 'completed' ? 'Complete' : (importStatus.status === 'cancelled' ? 'Cancelled' : 'Failed') }}
                </h2>

                <div class="grid grid-cols-4 gap-4 mb-4">
                    <div class="text-center p-3 bg-emerald-50 dark:bg-emerald-900/20 rounded-lg">
                        <p class="text-2xl font-bold text-emerald-600 dark:text-emerald-400">{{ importStatus.parameters?.imported ?? 0 }}</p>
                        <p class="text-xs text-gray-600 dark:text-gray-400">Imported</p>
                    </div>
                    <div class="text-center p-3 bg-blue-50 dark:bg-blue-900/20 rounded-lg">
                        <p class="text-2xl font-bold text-blue-600 dark:text-blue-400">{{ importStatus.parameters?.updated ?? 0 }}</p>
                        <p class="text-xs text-gray-600 dark:text-gray-400">Updated</p>
                    </div>
                    <div class="text-center p-3 bg-gray-50 dark:bg-gray-700/50 rounded-lg">
                        <p class="text-2xl font-bold text-gray-600 dark:text-gray-300">{{ importStatus.parameters?.skipped ?? 0 }}</p>
                        <p class="text-xs text-gray-600 dark:text-gray-400">Skipped</p>
                    </div>
                    <div class="text-center p-3 bg-red-50 dark:bg-red-900/20 rounded-lg">
                        <p class="text-2xl font-bold text-red-600 dark:text-red-400">{{ importStatus.failed_items ?? 0 }}</p>
                        <p class="text-xs text-gray-600 dark:text-gray-400">Failed</p>
                    </div>
                </div>

                <div class="flex items-center gap-4">
                    <Link
                        :href="route('inventory.products.index')"
                        class="text-sm text-blue-600 hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300"
                    >
                        View Products <i class="fas fa-arrow-right ml-1"></i>
                    </Link>
                    <Link
                        :href="route('marketplaces.ebay.sku-resolution')"
                        class="text-sm text-yellow-600 hover:text-yellow-700 dark:text-yellow-400 dark:hover:text-yellow-300"
                    >
                        Resolve Unmatched SKUs <i class="fas fa-arrow-right ml-1"></i>
                    </Link>
                </div>
            </div>

            <!-- Errors & Warnings Card -->
            <div v-if="isDone && hasErrors" class="bg-white dark:bg-gray-800 rounded-lg border border-red-200 dark:border-red-800 p-6 mb-6">
                <button
                    @click="showErrors = !showErrors"
                    class="w-full flex items-center justify-between"
                >
                    <div class="flex items-center gap-2">
                        <i class="fas fa-exclamation-triangle text-red-500"></i>
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Errors & Warnings</h2>
                        <span class="text-sm text-gray-500 dark:text-gray-400">({{ importStatus.errors?.length ?? importStatus.failed_items ?? 0 }})</span>
                    </div>
                    <i :class="showErrors ? 'fas fa-chevron-up' : 'fas fa-chevron-down'" class="text-gray-400"></i>
                </button>

                <div v-if="showErrors" class="mt-4 max-h-60 overflow-y-auto space-y-1">
                    <div
                        v-for="(error, idx) in importStatus.errors"
                        :key="idx"
                        class="text-xs text-red-600 dark:text-red-400 p-2 bg-red-50 dark:bg-red-900/10 rounded"
                    >
                        <span class="font-medium">{{ error.item_id ?? 'Unknown' }}:</span> {{ error.message }}
                    </div>
                    <div v-if="!importStatus.errors?.length && importStatus.failed_items > 0" class="text-sm text-gray-500 dark:text-gray-400">
                        {{ importStatus.failed_items }} items failed. Check the Debug Info section below for details.
                    </div>
                </div>
            </div>

            <!-- Debug Info (visible on main page) -->
            <div v-if="isDone && (importStatus?.debug_log?.length || debugData?.recent_sync_logs?.length)" class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-6 mb-6">
                <button
                    @click="showDebugInfo = !showDebugInfo"
                    class="w-full flex items-center justify-between"
                >
                    <div class="flex items-center gap-2">
                        <i class="fas fa-list-alt text-gray-400"></i>
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Debug Info</h2>
                    </div>
                    <i :class="showDebugInfo ? 'fas fa-chevron-up' : 'fas fa-chevron-down'" class="text-gray-400"></i>
                </button>

                <div v-if="showDebugInfo" class="mt-4 space-y-4">
                    <!-- Job Timeline -->
                    <div v-if="importStatus?.debug_log?.length">
                        <h3 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Job Timeline ({{ importStatus.debug_log.length }} events)</h3>
                        <div class="bg-gray-50 dark:bg-gray-900 rounded p-3 max-h-48 overflow-y-auto space-y-1 text-xs font-mono">
                            <div v-for="(entry, idx) in importStatus.debug_log" :key="idx"
                                 class="flex gap-2"
                                 :class="entry.event === 'exception' ? 'text-red-600 dark:text-red-400' : 'text-gray-600 dark:text-gray-400'">
                                <span class="text-gray-400 shrink-0">{{ entry.timestamp ? new Date(entry.timestamp).toLocaleTimeString() : '-' }}</span>
                                <span class="font-semibold shrink-0"
                                      :class="{
                                          'text-red-600 dark:text-red-400': entry.event === 'exception',
                                          'text-yellow-600 dark:text-yellow-400': entry.event === 'credential_check',
                                          'text-blue-600 dark:text-cyan-400': entry.event?.startsWith('service:'),
                                          'text-green-600 dark:text-green-400': entry.event === 'completed',
                                      }">{{ entry.event }}</span>
                                <span class="text-gray-500 truncate">
                                    <template v-if="entry.api_time_ms">{{ entry.api_time_ms }}ms</template>
                                    <template v-if="entry.total_items"> items={{ entry.total_items }}</template>
                                    <template v-if="entry.page"> p{{ entry.page }}</template>
                                    <template v-if="entry.items_in_page"> ({{ entry.items_in_page }} items)</template>
                                    <template v-if="entry.memory_mb"> mem={{ entry.memory_mb }}MB</template>
                                    <template v-if="entry.imported !== undefined"> imported={{ entry.imported }} skipped={{ entry.skipped }} failed={{ entry.failed }}</template>
                                    <template v-if="entry.message"> {{ entry.message }}</template>
                                    <template v-if="entry.error"> {{ entry.error }}</template>
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Recent Sync Log Errors -->
                    <div v-if="debugData?.recent_sync_logs?.length">
                        <h3 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Recent Sync Log Errors</h3>
                        <div class="bg-gray-50 dark:bg-gray-900 rounded p-3 max-h-40 overflow-y-auto space-y-1 text-xs font-mono">
                            <div v-for="log in debugData.recent_sync_logs.filter(l => l.status !== 'success')" :key="log.id"
                                 class="flex gap-2 text-gray-600 dark:text-gray-400">
                                <span class="text-gray-400 shrink-0">{{ new Date(log.created_at).toLocaleTimeString() }}</span>
                                <span class="text-red-600 dark:text-red-400">{{ log.status }}</span>
                                <span class="text-gray-500">{{ log.ebay_item_id }}</span>
                                <span v-if="log.error_message" class="text-red-500 truncate">{{ log.error_message }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Previous Import Info -->
            <div v-if="lastCompleted && !importStatus" class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-6">
                <h3 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Last Import</h3>
                <div class="text-sm text-gray-600 dark:text-gray-400">
                    <span class="font-medium">Status:</span> {{ lastCompleted.status }}
                    <span class="ml-4"><span class="font-medium">Imported:</span> {{ lastCompleted.parameters?.imported ?? 0 }}</span>
                    <span class="ml-4"><span class="font-medium">Date:</span> {{ lastCompleted.completed_at ? new Date(lastCompleted.completed_at).toLocaleString() : 'N/A' }}</span>
                </div>
                <div v-if="canResume" class="mt-3 flex items-center gap-3">
                    <button
                        @click="resumeImport"
                        :disabled="importSubmitted"
                        class="inline-flex items-center px-4 py-2 bg-yellow-600 hover:bg-yellow-700 disabled:opacity-50 text-white text-sm font-medium rounded-md transition-colors"
                    >
                        Resume from Page {{ resumeInfo?.page }} of {{ resumeInfo?.totalPages }}
                    </button>
                    <span class="text-xs text-gray-500 dark:text-gray-400">
                        Previous import failed with checkpoint data. Resume to continue where it left off.
                    </span>
                </div>
            </div>
        </div>

        <!-- Debug Toggle Button -->
        <button
            @click="toggleDebug"
            class="fixed bottom-4 right-4 w-8 h-8 rounded-full bg-gray-800 dark:bg-gray-700 text-gray-400 hover:text-white flex items-center justify-center opacity-30 hover:opacity-100 transition-opacity z-40"
            title="Toggle Debug Panel (Ctrl+Shift+.)"
        >
            <i class="fas fa-bug text-xs"></i>
        </button>

        <!-- Debug Panel -->
        <div
            v-if="debugMode"
            class="fixed bottom-0 left-0 right-0 bg-gray-900 text-gray-100 border-t border-gray-700 z-50 max-h-[60vh] overflow-y-auto shadow-2xl"
        >
            <!-- Debug Header -->
            <div class="sticky top-0 bg-gray-900 border-b border-gray-700 px-4 py-2 flex items-center justify-between">
                <span class="text-sm font-bold text-yellow-400">Import Debug Panel</span>
                <div class="flex items-center gap-2">
                    <button
                        @click="fetchDebugData"
                        :disabled="debugLoading"
                        class="px-2 py-1 text-xs bg-gray-700 hover:bg-gray-600 disabled:opacity-50 rounded"
                    >
                        Refresh
                    </button>
                    <button
                        @click="testConnectivity"
                        :disabled="debugLoading"
                        class="px-2 py-1 text-xs bg-blue-700 hover:bg-blue-600 disabled:opacity-50 rounded"
                    >
                        Test Connection
                    </button>
                    <button
                        @click="debugMode = false"
                        class="px-2 py-1 text-xs bg-red-700 hover:bg-red-600 rounded"
                    >
                        Close
                    </button>
                </div>
            </div>

            <div class="p-4 space-y-2 text-xs font-mono">
                <div v-if="debugLoading" class="text-gray-400">Loading debug data...</div>

                <!-- Integration Status (raw props) -->
                <details class="border border-gray-700 rounded">
                    <summary class="px-3 py-2 cursor-pointer hover:bg-gray-800 font-semibold text-green-400">Integration Status (props)</summary>
                    <pre class="px-3 py-2 overflow-x-auto text-gray-300 bg-gray-950">{{ JSON.stringify(integrationStatus, null, 2) }}</pre>
                </details>

                <!-- Environment & Config -->
                <details v-if="debugData?.environment" class="border border-gray-700 rounded">
                    <summary class="px-3 py-2 cursor-pointer hover:bg-gray-800 font-semibold text-green-400">Environment & Config</summary>
                    <pre class="px-3 py-2 overflow-x-auto text-gray-300 bg-gray-950">{{ JSON.stringify(debugData.environment, null, 2) }}</pre>
                </details>

                <!-- Credential / Token Info -->
                <details v-if="debugData?.credential" class="border border-gray-700 rounded">
                    <summary class="px-3 py-2 cursor-pointer hover:bg-gray-800 font-semibold"
                             :class="debugData.credential.token_is_expired ? 'text-red-400' : 'text-green-400'">
                        Credential / Token Info
                        <span v-if="debugData.credential.token_is_expired" class="ml-2 text-red-500">[EXPIRED]</span>
                        <span v-else-if="debugData.credential.token_needs_refresh" class="ml-2 text-yellow-500">[NEEDS REFRESH]</span>
                    </summary>
                    <pre class="px-3 py-2 overflow-x-auto text-gray-300 bg-gray-950">{{ JSON.stringify(debugData.credential, null, 2) }}</pre>
                </details>

                <!-- Connectivity Test -->
                <details v-if="debugData?.connectivity" class="border border-gray-700 rounded">
                    <summary class="px-3 py-2 cursor-pointer hover:bg-gray-800 font-semibold"
                             :class="debugData.connectivity.status === 'ok' ? 'text-green-400' : 'text-red-400'">
                        Connectivity Test
                        <span class="ml-2 inline-block w-2 h-2 rounded-full"
                              :class="debugData.connectivity.status === 'ok' ? 'bg-green-500' : 'bg-red-500'"></span>
                        <span class="ml-1 text-gray-400">{{ debugData.connectivity.response_time_ms ? `${debugData.connectivity.response_time_ms}ms` : '' }}</span>
                    </summary>
                    <pre class="px-3 py-2 overflow-x-auto text-gray-300 bg-gray-950">{{ JSON.stringify(debugData.connectivity, null, 2) }}</pre>
                </details>

                <!-- Queue Health -->
                <details v-if="debugData?.queue_health" class="border border-gray-700 rounded">
                    <summary class="px-3 py-2 cursor-pointer hover:bg-gray-800 font-semibold"
                             :class="debugData.queue_health.worker_likely_running ? 'text-green-400' : 'text-yellow-400'">
                        Queue Health
                        <span class="ml-2 inline-block w-2 h-2 rounded-full"
                              :class="debugData.queue_health.worker_likely_running ? 'bg-green-500' : 'bg-yellow-500'"></span>
                        <span v-if="debugData.queue_health.stuck_imports > 0" class="ml-2 text-red-500">[{{ debugData.queue_health.stuck_imports }} STUCK]</span>
                    </summary>
                    <pre class="px-3 py-2 overflow-x-auto text-gray-300 bg-gray-950">{{ JSON.stringify(debugData.queue_health, null, 2) }}</pre>
                </details>

                <!-- Job Debug Timeline -->
                <details v-if="importStatus?.debug_log && importStatus.debug_log.length" class="border border-gray-700 rounded">
                    <summary class="px-3 py-2 cursor-pointer hover:bg-gray-800 font-semibold text-cyan-400">
                        Job Debug Timeline ({{ importStatus.debug_log.length }} events)
                    </summary>
                    <div class="px-3 py-2 bg-gray-950 space-y-1 max-h-60 overflow-y-auto">
                        <div v-for="(entry, idx) in importStatus.debug_log" :key="idx"
                             class="flex gap-2 py-0.5"
                             :class="entry.event === 'exception' ? 'text-red-400' : 'text-gray-300'">
                            <span class="text-gray-500 shrink-0">{{ entry.timestamp ? new Date(entry.timestamp).toLocaleTimeString() : '-' }}</span>
                            <span class="font-semibold shrink-0"
                                  :class="{
                                      'text-red-400': entry.event === 'exception',
                                      'text-yellow-400': entry.event === 'credential_check',
                                      'text-cyan-400': entry.event?.startsWith('service:'),
                                      'text-green-400': entry.event === 'completed',
                                  }">{{ entry.event }}</span>
                            <span class="text-gray-400 truncate">
                                <template v-if="entry.api_time_ms">{{ entry.api_time_ms }}ms</template>
                                <template v-if="entry.total_items"> items={{ entry.total_items }}</template>
                                <template v-if="entry.page"> p{{ entry.page }}</template>
                                <template v-if="entry.items_in_page"> ({{ entry.items_in_page }} items)</template>
                                <template v-if="entry.memory_mb"> mem={{ entry.memory_mb }}MB</template>
                                <template v-if="entry.imported !== undefined"> imported={{ entry.imported }} skipped={{ entry.skipped }} failed={{ entry.failed }}</template>
                                <template v-if="entry.message"> {{ entry.message }}</template>
                                <template v-if="entry.error"> {{ entry.error }}</template>
                            </span>
                        </div>
                    </div>
                </details>

                <!-- Poll Log -->
                <details class="border border-gray-700 rounded">
                    <summary class="px-3 py-2 cursor-pointer hover:bg-gray-800 font-semibold text-purple-400">
                        Poll Log ({{ pollLog.length }} entries)
                    </summary>
                    <div class="px-3 py-2 bg-gray-950 space-y-0.5 max-h-40 overflow-y-auto">
                        <div v-if="pollLog.length === 0" class="text-gray-500">No polls yet. Start an import or wait for polling.</div>
                        <div v-for="(entry, idx) in pollLog" :key="idx"
                             class="flex gap-2"
                             :class="entry.status === 'error' ? 'text-red-400' : 'text-gray-300'">
                            <span class="text-gray-500 shrink-0">{{ new Date(entry.time).toLocaleTimeString() }}</span>
                            <span :class="entry.status === 200 ? 'text-green-400' : (entry.status === 'error' ? 'text-red-400' : 'text-yellow-400')">{{ entry.status }}</span>
                            <span class="text-gray-400">{{ entry.elapsed_ms }}ms</span>
                            <span class="text-gray-500">{{ entry.data_status || entry.error || '' }}</span>
                            <span v-if="entry.has_debug_log" class="text-cyan-500">[debug]</span>
                        </div>
                    </div>
                </details>

                <!-- Recent Sync Logs -->
                <details v-if="debugData?.recent_sync_logs?.length" class="border border-gray-700 rounded">
                    <summary class="px-3 py-2 cursor-pointer hover:bg-gray-800 font-semibold text-green-400">
                        Recent Sync Logs ({{ debugData.recent_sync_logs.length }})
                    </summary>
                    <div class="px-3 py-2 bg-gray-950 space-y-1 max-h-40 overflow-y-auto">
                        <div v-for="log in debugData.recent_sync_logs" :key="log.id"
                             class="flex gap-2 text-gray-300">
                            <span class="text-gray-500 shrink-0">{{ new Date(log.created_at).toLocaleTimeString() }}</span>
                            <span :class="log.status === 'success' ? 'text-green-400' : 'text-red-400'">{{ log.status }}</span>
                            <span class="text-gray-400">{{ log.ebay_item_id }}</span>
                            <span v-if="log.error_message" class="text-red-400 truncate">{{ log.error_message }}</span>
                            <span v-if="log.duration" class="text-gray-500">{{ log.duration }}ms</span>
                        </div>
                    </div>
                </details>

                <!-- Bulk Job History -->
                <details v-if="debugData?.job_history?.length" class="border border-gray-700 rounded">
                    <summary class="px-3 py-2 cursor-pointer hover:bg-gray-800 font-semibold text-green-400">
                        Bulk Job History ({{ debugData.job_history.length }})
                    </summary>
                    <div class="px-3 py-2 bg-gray-950 space-y-2 max-h-48 overflow-y-auto">
                        <div v-for="job in debugData.job_history" :key="job.id" class="border-b border-gray-800 pb-1">
                            <div class="flex gap-2 text-gray-300">
                                <span class="text-gray-500">{{ job.created_at ? new Date(job.created_at).toLocaleString() : '-' }}</span>
                                <span :class="{
                                    'text-green-400': job.status === 'completed',
                                    'text-red-400': job.status === 'failed',
                                    'text-yellow-400': job.status === 'processing',
                                    'text-gray-400': job.status === 'pending',
                                }">{{ job.status }}</span>
                                <span class="text-gray-400">{{ job.processed_items }}/{{ job.total_items }} ({{ job.failed_items }} failed)</span>
                            </div>
                        </div>
                    </div>
                </details>

                <!-- Failed Queue Jobs -->
                <details v-if="debugData?.failed_jobs?.length" class="border border-red-800 rounded bg-red-950/30">
                    <summary class="px-3 py-2 cursor-pointer hover:bg-red-900/30 font-semibold text-red-400">
                        Failed Queue Jobs ({{ debugData.failed_jobs.length }})
                    </summary>
                    <div class="px-3 py-2 space-y-2 max-h-48 overflow-y-auto">
                        <div v-for="job in debugData.failed_jobs" :key="job.id" class="border-b border-red-800 pb-2">
                            <div class="text-red-300 text-xs mb-1">Failed at: {{ job.failed_at }}</div>
                            <pre class="text-red-200 whitespace-pre-wrap text-xs leading-tight">{{ job.exception_preview }}</pre>
                        </div>
                    </div>
                </details>

                <!-- Raw Import Status -->
                <details class="border border-gray-700 rounded">
                    <summary class="px-3 py-2 cursor-pointer hover:bg-gray-800 font-semibold text-gray-400">Raw Import Status (live)</summary>
                    <pre class="px-3 py-2 overflow-x-auto text-gray-300 bg-gray-950 max-h-40 overflow-y-auto">{{ JSON.stringify(importStatus, null, 2) }}</pre>
                </details>
            </div>
        </div>
    </AppLayout>
</template>
