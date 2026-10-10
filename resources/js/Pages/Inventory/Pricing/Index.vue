<script setup>
import { ref, watch, computed } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import TextInput from '@/Components/TextInput.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';

const props = defineProps({
    products: Object,
    filters: Object,
});

const search = ref(props.filters?.search || '');
const selectedIds = ref([]);
const selectAll = ref(false);

// Pricing rules
const strategy = ref('percentage');
const value = ref('');
const operation = ref('increase');
const priceField = ref('selling_price');
const rounding = ref('');
const minPrice = ref('');
const maxPrice = ref('');

// Preview state
const showPreview = ref(false);
const previews = ref([]);
const previewLoading = ref(false);
const applying = ref(false);

let searchTimeout = null;

watch(search, () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        router.get(route('inventory.pricing.index'), {
            search: search.value || undefined,
        }, { preserveState: true, preserveScroll: true });
    }, 300);
});

watch(selectAll, (val) => {
    selectedIds.value = val ? props.products.data.map(p => p.id) : [];
});

const toggleSelect = (id) => {
    const idx = selectedIds.value.indexOf(id);
    if (idx >= 0) {
        selectedIds.value.splice(idx, 1);
    } else {
        selectedIds.value.push(id);
    }
};

const hasSelection = computed(() => selectedIds.value.length > 0);

const rules = computed(() => ({
    strategy: strategy.value,
    value: parseFloat(value.value) || 0,
    operation: operation.value,
    price_field: priceField.value,
    rounding: rounding.value || null,
    min_price: parseFloat(minPrice.value) || null,
    max_price: parseFloat(maxPrice.value) || null,
}));

const fetchPreview = async () => {
    if (!hasSelection.value || !value.value) return;

    previewLoading.value = true;
    showPreview.value = true;

    try {
        const response = await fetch(route('inventory.pricing.preview'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content'),
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                product_ids: selectedIds.value,
                rules: rules.value,
            }),
        });
        const data = await response.json();
        previews.value = data.previews || [];
    } catch (e) {
        console.error('Preview failed:', e);
    } finally {
        previewLoading.value = false;
    }
};

const applyPrices = () => {
    applying.value = true;
    router.post(route('inventory.pricing.apply'), {
        product_ids: selectedIds.value,
        rules: rules.value,
    }, {
        onFinish: () => {
            applying.value = false;
            showPreview.value = false;
            selectedIds.value = [];
            selectAll.value = false;
        },
    });
};

const cancelPreview = () => {
    showPreview.value = false;
    previews.value = [];
};
</script>

<template>
    <AppLayout title="Pricing Management">
        <Head title="Pricing Management" />

        <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
            <!-- Header -->
            <div class="mb-6">
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Pricing Management</h1>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                    Adjust prices in bulk with preview before applying
                </p>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
                <!-- Pricing Controls (Sidebar) -->
                <div class="lg:col-span-1">
                    <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-4 sticky top-6 space-y-4">
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-white uppercase tracking-wider">Pricing Rules</h3>

                        <div>
                            <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Strategy</label>
                            <select v-model="strategy" class="block w-full text-sm rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="percentage">Percentage Adjust</option>
                                <option value="fixed">Set Fixed Price</option>
                                <option value="cost_plus_percentage">Cost + Percentage</option>
                                <option value="cost_plus_fixed">Cost + Fixed Amount</option>
                            </select>
                        </div>

                        <div v-if="strategy === 'percentage'">
                            <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Direction</label>
                            <select v-model="operation" class="block w-full text-sm rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="increase">Increase</option>
                                <option value="decrease">Decrease</option>
                            </select>
                        </div>

                        <div v-if="strategy === 'percentage'">
                            <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Base Price Field</label>
                            <select v-model="priceField" class="block w-full text-sm rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="selling_price">Selling Price</option>
                                <option value="cost_price">Cost Price</option>
                                <option value="msrp">MSRP</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">
                                {{ strategy === 'fixed' ? 'Price ($)' : strategy === 'percentage' ? 'Percentage (%)' : strategy === 'cost_plus_fixed' ? 'Markup ($)' : 'Markup (%)' }}
                            </label>
                            <TextInput v-model="value" type="number" step="0.01" min="0" class="w-full text-sm" placeholder="0.00" />
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Rounding</label>
                            <select v-model="rounding" class="block w-full text-sm rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">None</option>
                                <option value="nearest_99">$X.99</option>
                                <option value="nearest_95">$X.95</option>
                                <option value="nearest_dollar">Nearest Dollar</option>
                                <option value="up_dollar">Round Up</option>
                            </select>
                        </div>

                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Min $</label>
                                <TextInput v-model="minPrice" type="number" step="0.01" min="0" class="w-full text-sm" placeholder="--" />
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Max $</label>
                                <TextInput v-model="maxPrice" type="number" step="0.01" min="0" class="w-full text-sm" placeholder="--" />
                            </div>
                        </div>

                        <div class="pt-2 space-y-2">
                            <PrimaryButton
                                @click="fetchPreview"
                                :disabled="!hasSelection || !value || previewLoading"
                                class="w-full justify-center text-sm"
                            >
                                {{ previewLoading ? 'Loading...' : `Preview (${selectedIds.length})` }}
                            </PrimaryButton>
                        </div>
                    </div>
                </div>

                <!-- Products Table -->
                <div class="lg:col-span-3">
                    <!-- Search -->
                    <div class="mb-4">
                        <TextInput v-model="search" type="text" placeholder="Search products by name or SKU..." class="w-full" />
                    </div>

                    <!-- Preview Panel -->
                    <div v-if="showPreview" class="bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-lg p-4 mb-4">
                        <div class="flex items-center justify-between mb-3">
                            <h3 class="text-sm font-semibold text-amber-800 dark:text-amber-300">Price Preview</h3>
                            <div class="flex gap-2">
                                <SecondaryButton @click="cancelPreview" class="text-xs">Cancel</SecondaryButton>
                                <PrimaryButton @click="applyPrices" :disabled="applying || !previews.length" class="text-xs">
                                    {{ applying ? 'Applying...' : 'Apply Changes' }}
                                </PrimaryButton>
                            </div>
                        </div>

                        <div v-if="previewLoading" class="text-center py-4 text-sm text-gray-500">Loading preview...</div>

                        <table v-else-if="previews.length" class="min-w-full text-sm">
                            <thead>
                                <tr class="text-left text-xs text-amber-700 dark:text-amber-400">
                                    <th class="pb-2">Product</th>
                                    <th class="pb-2">Current</th>
                                    <th class="pb-2">New</th>
                                    <th class="pb-2">Change</th>
                                    <th class="pb-2">Margin</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-amber-100 dark:divide-amber-800/50">
                                <tr v-for="p in previews" :key="p.id">
                                    <td class="py-1.5">
                                        <span class="text-gray-900 dark:text-white">{{ p.name }}</span>
                                        <span class="text-gray-500 text-xs ml-1">({{ p.sku }})</span>
                                    </td>
                                    <td class="py-1.5 text-gray-600 dark:text-gray-400">${{ Number(p.current_price).toFixed(2) }}</td>
                                    <td class="py-1.5 font-medium" :class="p.new_price > p.current_price ? 'text-emerald-600' : p.new_price < p.current_price ? 'text-red-600' : 'text-gray-600'">
                                        ${{ Number(p.new_price).toFixed(2) }}
                                    </td>
                                    <td class="py-1.5 text-xs" :class="p.change > 0 ? 'text-emerald-600' : p.change < 0 ? 'text-red-600' : 'text-gray-400'">
                                        {{ p.change !== null ? (p.change > 0 ? '+' : '') + p.change + '%' : '--' }}
                                    </td>
                                    <td class="py-1.5 text-xs text-gray-500">{{ p.margin !== null ? p.margin + '%' : '--' }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Table -->
                    <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 overflow-hidden">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-900/50">
                                <tr>
                                    <th class="px-4 py-3 w-10">
                                        <input type="checkbox" v-model="selectAll" class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded" />
                                    </th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Product</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Cost</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Selling</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">MSRP</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Margin</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                <tr
                                    v-for="product in products.data"
                                    :key="product.id"
                                    class="hover:bg-gray-50 dark:hover:bg-gray-700/50"
                                    :class="{ 'bg-indigo-50 dark:bg-indigo-900/10': selectedIds.includes(product.id) }"
                                >
                                    <td class="px-4 py-3">
                                        <input
                                            type="checkbox"
                                            :checked="selectedIds.includes(product.id)"
                                            @change="toggleSelect(product.id)"
                                            class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded"
                                        />
                                    </td>
                                    <td class="px-4 py-3">
                                        <p class="text-sm font-medium text-gray-900 dark:text-white line-clamp-1">{{ product.name }}</p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ product.sku }}</p>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-400">
                                        {{ product.cost_price ? '$' + Number(product.cost_price).toFixed(2) : '--' }}
                                    </td>
                                    <td class="px-4 py-3 text-sm font-medium text-gray-900 dark:text-white">
                                        {{ product.selling_price ? '$' + Number(product.selling_price).toFixed(2) : '--' }}
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-400">
                                        {{ product.msrp ? '$' + Number(product.msrp).toFixed(2) : '--' }}
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-400">
                                        <template v-if="product.cost_price && product.selling_price && product.selling_price > 0">
                                            {{ Math.round(((product.selling_price - product.cost_price) / product.selling_price) * 100) }}%
                                        </template>
                                        <template v-else>--</template>
                                    </td>
                                </tr>
                            </tbody>
                        </table>

                        <div v-if="!products.data?.length" class="text-center py-12">
                            <p class="text-gray-500 dark:text-gray-400">No products found</p>
                        </div>

                        <!-- Pagination -->
                        <div v-if="products.last_page > 1" class="px-4 py-3 border-t border-gray-200 dark:border-gray-700 flex items-center justify-between">
                            <p class="text-sm text-gray-500 dark:text-gray-400">
                                {{ products.from }}-{{ products.to }} of {{ products.total }}
                            </p>
                            <div class="flex gap-2">
                                <a v-if="products.prev_page_url" :href="products.prev_page_url" class="px-3 py-1 text-sm border border-gray-300 dark:border-gray-600 rounded hover:bg-gray-50 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-300">Prev</a>
                                <a v-if="products.next_page_url" :href="products.next_page_url" class="px-3 py-1 text-sm border border-gray-300 dark:border-gray-600 rounded hover:bg-gray-50 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-300">Next</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
