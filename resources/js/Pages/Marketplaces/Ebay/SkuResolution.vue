<script setup>
import { ref, computed } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    products: Object,
    prefixCounts: Object,
    suppliers: Array,
    totalUnresolved: Number,
    filters: Object,
});

const search = ref(props.filters?.search ?? '');
const activePrefix = ref(props.filters?.prefix ?? '');

// Inline edit state: keyed by product ID
const editData = ref({});

const initEdit = (product) => {
    if (!editData.value[product.id]) {
        // Extract first word as likely owner prefix
        const parts = (product.sku || '').split(/\s+/);
        const likelyOwner = parts[0] && /^[A-Z][A-Z0-9]{1,5}$/.test(parts[0]) ? parts[0] : '';
        editData.value[product.id] = {
            owner: likelyOwner,
            internal_sku: '',
            location: '',
            saving: false,
        };
    }
};

const saveResolution = (product) => {
    const data = editData.value[product.id];
    if (!data) return;
    data.saving = true;

    router.post(route('marketplaces.ebay.sku-resolution.resolve', product.id), {
        owner: data.owner,
        internal_sku: data.internal_sku,
        location: data.location,
    }, {
        preserveScroll: true,
        onFinish: () => {
            data.saving = false;
        },
    });
};

// Bulk resolve
const bulkForm = useForm({
    prefix: '',
    owner: '',
});

const showBulkModal = ref(false);

const openBulkModal = (prefix) => {
    bulkForm.prefix = prefix;
    // Auto-suggest owner as the prefix itself
    bulkForm.owner = prefix;
    showBulkModal.value = true;
};

const submitBulkResolve = () => {
    bulkForm.post(route('marketplaces.ebay.sku-resolution.bulk-resolve'), {
        preserveScroll: true,
        onSuccess: () => {
            showBulkModal.value = false;
            bulkForm.reset();
        },
    });
};

const applyFilter = (prefix) => {
    activePrefix.value = prefix;
    router.get(route('marketplaces.ebay.sku-resolution'), {
        prefix: prefix || undefined,
        search: search.value || undefined,
    }, { preserveState: true, preserveScroll: true });
};

const applySearch = () => {
    router.get(route('marketplaces.ebay.sku-resolution'), {
        prefix: activePrefix.value || undefined,
        search: search.value || undefined,
    }, { preserveState: true, preserveScroll: true });
};

const clearFilters = () => {
    search.value = '';
    activePrefix.value = '';
    router.get(route('marketplaces.ebay.sku-resolution'), {}, { preserveState: true });
};

const prefixEntries = computed(() => {
    return Object.entries(props.prefixCounts || {}).sort((a, b) => b[1] - a[1]);
});
</script>

<template>
    <AppLayout title="SKU Resolution">
        <Head title="SKU Resolution" />

        <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
            <!-- Header -->
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">SKU Resolution</h1>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        {{ totalUnresolved }} imported products need supplier/location assignment.
                    </p>
                </div>
                <Link :href="route('marketplaces.ebay.import')" class="text-sm text-blue-600 hover:text-blue-800 dark:text-blue-400">
                    Back to Import
                </Link>
            </div>

            <div class="flex gap-6">
                <!-- Sidebar: Prefix Filters -->
                <div class="w-64 flex-shrink-0">
                    <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-4">
                        <h3 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">Filter by Prefix</h3>
                        <button
                            @click="clearFilters"
                            class="w-full text-left px-3 py-1.5 text-sm rounded-md mb-1"
                            :class="!activePrefix ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-700 dark:text-blue-300 font-medium' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700'"
                        >
                            All ({{ totalUnresolved }})
                        </button>
                        <div v-for="[prefix, count] in prefixEntries" :key="prefix" class="flex items-center gap-1">
                            <button
                                @click="applyFilter(prefix)"
                                class="flex-1 text-left px-3 py-1.5 text-sm rounded-md truncate"
                                :class="activePrefix === prefix ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-700 dark:text-blue-300 font-medium' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700'"
                            >
                                {{ prefix || '(empty)' }} ({{ count }})
                            </button>
                            <button
                                @click="openBulkModal(prefix)"
                                class="px-2 py-1 text-xs text-gray-400 hover:text-blue-600 dark:hover:text-blue-400"
                                title="Bulk assign owner for this prefix"
                            >
                                Bulk
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Main Content -->
                <div class="flex-1 min-w-0">
                    <!-- Search -->
                    <div class="mb-4">
                        <div class="flex gap-2">
                            <input
                                v-model="search"
                                @keyup.enter="applySearch"
                                type="text"
                                placeholder="Search by SKU or product name..."
                                class="flex-1 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm"
                            />
                            <button @click="applySearch" class="px-4 py-2 bg-gray-100 dark:bg-gray-700 text-sm rounded-md hover:bg-gray-200 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-300">
                                Search
                            </button>
                        </div>
                    </div>

                    <!-- Products Table -->
                    <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 overflow-hidden">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-900">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Raw SKU</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Product</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Owner</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Internal SKU</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Location</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase w-20"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                <tr v-for="product in products.data" :key="product.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/50" @mouseenter="initEdit(product)">
                                    <td class="px-4 py-3 text-sm font-mono text-gray-900 dark:text-gray-100 max-w-xs truncate" :title="product.sku">
                                        {{ product.sku }}
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-400 max-w-xs truncate" :title="product.name">
                                        {{ product.name }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <template v-if="editData[product.id]">
                                            <input
                                                v-model="editData[product.id].owner"
                                                type="text"
                                                placeholder="e.g. ARC"
                                                class="w-24 text-sm rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white px-2 py-1"
                                                :list="'suppliers-' + product.id"
                                            />
                                            <datalist :id="'suppliers-' + product.id">
                                                <option v-for="s in suppliers" :key="s" :value="s" />
                                            </datalist>
                                        </template>
                                        <span v-else class="text-sm text-gray-400">--</span>
                                    </td>
                                    <td class="px-4 py-3">
                                        <input
                                            v-if="editData[product.id]"
                                            v-model="editData[product.id].internal_sku"
                                            type="text"
                                            placeholder="Optional"
                                            class="w-36 text-sm rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white px-2 py-1"
                                        />
                                        <span v-else class="text-sm text-gray-400">--</span>
                                    </td>
                                    <td class="px-4 py-3">
                                        <input
                                            v-if="editData[product.id]"
                                            v-model="editData[product.id].location"
                                            type="text"
                                            placeholder="e.g. Shelf 3B"
                                            class="w-36 text-sm rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white px-2 py-1"
                                        />
                                        <span v-else class="text-sm text-gray-400">--</span>
                                    </td>
                                    <td class="px-4 py-3">
                                        <button
                                            v-if="editData[product.id] && editData[product.id].owner"
                                            @click="saveResolution(product)"
                                            :disabled="editData[product.id]?.saving"
                                            class="px-3 py-1 text-xs font-medium text-white bg-green-600 hover:bg-green-700 disabled:opacity-50 rounded"
                                        >
                                            {{ editData[product.id]?.saving ? '...' : 'Save' }}
                                        </button>
                                    </td>
                                </tr>
                                <tr v-if="!products.data?.length">
                                    <td colspan="6" class="px-4 py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                                        No unresolved products found.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div v-if="products.links?.length > 3" class="mt-4 flex justify-center gap-1">
                        <template v-for="link in products.links" :key="link.label">
                            <Link
                                v-if="link.url"
                                :href="link.url"
                                class="px-3 py-1.5 text-sm rounded-md border"
                                :class="link.active ? 'bg-blue-600 text-white border-blue-600' : 'bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700'"
                                v-html="link.label"
                                preserve-scroll
                            />
                            <span
                                v-else
                                class="px-3 py-1.5 text-sm text-gray-400 dark:text-gray-500"
                                v-html="link.label"
                            />
                        </template>
                    </div>
                </div>
            </div>
        </div>

        <!-- Bulk Resolve Modal -->
        <div v-if="showBulkModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl p-6 w-full max-w-md mx-4">
                <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">
                    Bulk Assign Owner
                </h3>
                <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                    Assign a supplier/owner to all unresolved products with SKU prefix "{{ bulkForm.prefix }}".
                </p>

                <div class="space-y-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Prefix</label>
                        <input
                            v-model="bulkForm.prefix"
                            type="text"
                            class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm"
                            readonly
                        />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Owner / Supplier Code</label>
                        <input
                            v-model="bulkForm.owner"
                            type="text"
                            placeholder="e.g. HAWK"
                            class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm"
                        />
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <button
                        @click="showBulkModal = false"
                        class="px-4 py-2 text-sm text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-700 rounded-md hover:bg-gray-200 dark:hover:bg-gray-600"
                    >
                        Cancel
                    </button>
                    <button
                        @click="submitBulkResolve"
                        :disabled="bulkForm.processing || !bulkForm.owner"
                        class="px-4 py-2 text-sm text-white bg-blue-600 hover:bg-blue-700 disabled:opacity-50 rounded-md"
                    >
                        {{ bulkForm.processing ? 'Applying...' : 'Apply to All' }}
                    </button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
