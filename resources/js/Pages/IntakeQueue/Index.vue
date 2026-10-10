<script setup>
import { ref, computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    items: Object,
    stats: Object,
    filters: Object,
});

const selectedItems = ref([]);
const searchQuery = ref(props.filters.search || '');

const confidenceBadgeClass = (level) => {
    const classes = {
        high: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300',
        medium: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300',
        low: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300',
        unknown: 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300',
    };
    return classes[level] || classes.unknown;
};

const statusBadgeClass = (status) => {
    const classes = {
        pending: 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300',
        in_progress: 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-300',
        needs_review: 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-300',
        needs_minor_review: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300',
        needs_major_review: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300',
        ready: 'bg-teal-100 text-teal-800 dark:bg-teal-900 dark:text-teal-300',
        validated: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900 dark:text-emerald-300',
        completed: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300',
        listed_on_ebay: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300',
        archived: 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300',
        failed: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300',
        listing_failed: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300',
    };
    return classes[status] || classes.pending;
};

const applyFilters = (overrides = {}) => {
    router.get(route('intake-queue.index'), {
        status: props.filters.status,
        confidence: props.filters.confidence,
        search: searchQuery.value,
        sort: props.filters.sort,
        direction: props.filters.direction,
        ...overrides,
    }, {
        preserveState: true,
        preserveScroll: true,
    });
};

const filterByStatus = (status) => applyFilters({ status });

const filterByConfidence = (confidence) => applyFilters({ confidence });

const performSearch = () => applyFilters({ search: searchQuery.value });

// Clicking a column header sorts by it; clicking again toggles asc/desc.
const sortBy = (column) => {
    const direction = props.filters.sort === column && props.filters.direction === 'asc' ? 'desc' : 'asc';
    applyFilters({ sort: column, direction });
};

const retryItem = (itemId) => {
    router.post(route('intake-queue.retry', itemId), {}, {
        preserveScroll: true,
    });
};

const toggleAllItems = () => {
    if (selectedItems.value.length === props.items.data.length) {
        selectedItems.value = [];
    } else {
        selectedItems.value = props.items.data.map(item => item.id);
    }
};

const bulkApprove = () => {
    if (!confirm(`Approve ${selectedItems.value.length} high-confidence items?`)) {
        return;
    }

    router.post(route('intake-queue.bulk-approve'), {
        item_ids: selectedItems.value,
    }, {
        onSuccess: () => {
            selectedItems.value = [];
        },
    });
};

const highConfidenceCount = computed(() => {
    return props.items.data.filter(item => item.confidence_level === 'high').length;
});
</script>

<template>
    <AppLayout
        title="Product Intake Queue"
        page-title="Product Intake Queue"
    >

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <!-- Statistics Cards -->
                <div class="grid grid-cols-1 md:grid-cols-5 gap-4 mb-6">
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                        <div class="text-gray-500 dark:text-gray-400 text-sm">Total Items</div>
                        <div class="text-2xl font-bold text-gray-900 dark:text-gray-100">
                            {{ stats.total }}
                        </div>
                    </div>
                    
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                        <div class="text-gray-500 dark:text-gray-400 text-sm">Pending</div>
                        <div class="text-2xl font-bold text-blue-600 dark:text-blue-400">
                            {{ stats.pending }}
                        </div>
                    </div>
                    
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                        <div class="text-gray-500 dark:text-gray-400 text-sm">In Progress</div>
                        <div class="text-2xl font-bold text-purple-600 dark:text-purple-400">
                            {{ stats.in_progress }}
                        </div>
                    </div>
                    
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                        <div class="text-gray-500 dark:text-gray-400 text-sm">Completed Today</div>
                        <div class="text-2xl font-bold text-green-600 dark:text-green-400">
                            {{ stats.completed_today }}
                        </div>
                    </div>
                    
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                        <div class="text-gray-500 dark:text-gray-400 text-sm">High Confidence</div>
                        <div class="text-2xl font-bold text-green-600 dark:text-green-400">
                            {{ stats.high_confidence }}
                        </div>
                    </div>
                </div>

                <!-- Filters and Actions -->
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mb-6 p-6">
                    <div class="flex flex-col md:flex-row justify-between gap-4">
                        <!-- Search -->
                        <div class="flex-1">
                            <input
                                v-model="searchQuery"
                                type="text"
                                placeholder="Search by barcode or notes..."
                                class="w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm"
                                @keyup.enter="performSearch"
                            />
                        </div>

                        <!-- Status Filter -->
                        <div>
                            <select
                                :value="filters.status"
                                @change="filterByStatus($event.target.value)"
                                class="border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm"
                            >
                                <option value="all">All Status</option>
                                <option value="pending">Pending</option>
                                <option value="in_progress">In Progress</option>
                                <option value="needs_review">Needs Review</option>
                                <option value="needs_minor_review">Needs Minor Review</option>
                                <option value="needs_major_review">Needs Major Review</option>
                                <option value="ready">Ready</option>
                                <option value="validated">Validated</option>
                                <option value="completed">Completed</option>
                                <option value="archived">Archived</option>
                                <option value="failed">Failed</option>
                            </select>
                        </div>

                        <!-- Confidence Filter -->
                        <div>
                            <select
                                :value="filters.confidence"
                                @change="filterByConfidence($event.target.value)"
                                class="border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm"
                            >
                                <option value="all">All Confidence</option>
                                <option value="high">High (≥85%)</option>
                                <option value="medium">Medium (60-84%)</option>
                                <option value="low">Low (<60%)</option>
                            </select>
                        </div>
                        <!-- Add Product Button -->
                        <div>
                            <Link
                                :href="route('intake-queue.create')"
                                class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-md inline-flex items-center"
                            >
                                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                </svg>
                                Add Product
                            </Link>
                        </div>
                        <!-- Bulk Actions -->
                        <div v-if="selectedItems.length > 0">
                            <button
                                @click="bulkApprove"
                                class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-md"
                            >
                                Bulk Approve ({{ selectedItems.length }})
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Queue Items Table -->
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-900">
                            <tr>
                                <th class="px-6 py-3 text-left">
                                    <input
                                        type="checkbox"
                                        :checked="selectedItems.length === items.data.length"
                                        @change="toggleAllItems"
                                        class="rounded border-gray-300 dark:border-gray-700"
                                    />
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    Product
                                </th>
                                <th
                                    @click="sortBy('barcode')"
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider cursor-pointer select-none hover:text-gray-700 dark:hover:text-gray-200"
                                >
                                    <div class="flex items-center gap-1">
                                        Barcode
                                        <svg v-if="filters.sort === 'barcode'" class="w-3 h-3 transition-transform" :class="{ 'rotate-180': filters.direction === 'desc' }" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7" /></svg>
                                    </div>
                                </th>
                                <th
                                    @click="sortBy('status')"
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider cursor-pointer select-none hover:text-gray-700 dark:hover:text-gray-200"
                                >
                                    <div class="flex items-center gap-1">
                                        Status
                                        <svg v-if="filters.sort === 'status'" class="w-3 h-3 transition-transform" :class="{ 'rotate-180': filters.direction === 'desc' }" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7" /></svg>
                                    </div>
                                </th>
                                <th
                                    @click="sortBy('confidence')"
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider cursor-pointer select-none hover:text-gray-700 dark:hover:text-gray-200"
                                >
                                    <div class="flex items-center gap-1">
                                        Confidence
                                        <svg v-if="filters.sort === 'confidence'" class="w-3 h-3 transition-transform" :class="{ 'rotate-180': filters.direction === 'desc' }" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7" /></svg>
                                    </div>
                                </th>
                                <th
                                    @click="sortBy('captured')"
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider cursor-pointer select-none hover:text-gray-700 dark:hover:text-gray-200"
                                >
                                    <div class="flex items-center gap-1">
                                        Captured
                                        <svg v-if="filters.sort === 'captured'" class="w-3 h-3 transition-transform" :class="{ 'rotate-180': filters.direction === 'desc' }" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7" /></svg>
                                    </div>
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                            <tr
                                v-for="item in items.data"
                                :key="item.id"
                                class="hover:bg-gray-50 dark:hover:bg-gray-700"
                            >
                                <td class="px-6 py-4">
                                    <input
                                        v-model="selectedItems"
                                        type="checkbox"
                                        :value="item.id"
                                        class="rounded border-gray-300 dark:border-gray-700"
                                    />
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex items-center">
                                        <img
                                            v-if="item.primary_photo"
                                            :src="item.primary_photo"
                                            alt="Product"
                                            class="h-12 w-12 object-cover rounded mr-3"
                                        />
                                        <div>
                                            <div class="text-sm font-medium text-gray-900 dark:text-gray-100">
                                                {{ item.preview_name }}
                                            </div>
                                            <div v-if="item.quick_quantity" class="text-xs text-gray-500">
                                                Qty: {{ item.quick_quantity }}
                                                <span v-if="item.quick_location"> • {{ item.quick_location }}</span>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-900 dark:text-gray-100">
                                    {{ item.barcode || '—' }}
                                </td>
                                <td class="px-6 py-4">
                                    <span
                                        :class="statusBadgeClass(item.processing_status)"
                                        class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full"
                                    >
                                        {{ item.status_badge }}
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <span
                                        v-if="item.confidence_score"
                                        :class="confidenceBadgeClass(item.confidence_level)"
                                        class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full"
                                    >
                                        {{ Math.round(item.confidence_score * 100) }}%
                                    </span>
                                    <span v-else class="text-gray-400 text-sm">Processing...</span>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                    <div>{{ item.captured_by }}</div>
                                    <div class="text-xs">{{ new Date(item.captured_at).toLocaleDateString() }}</div>
                                </td>
                                <td class="px-6 py-4 text-sm space-x-2">
                                    <Link
                                        :href="route('intake-queue.show', item.id)"
                                        class="text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-300"
                                    >
                                        Process →
                                    </Link>
                                    <button
                                        v-if="item.processing_status === 'failed'"
                                        @click="retryItem(item.id)"
                                        class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300 font-medium"
                                    >
                                        Retry
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <!-- Pagination -->
                    <div v-if="items.links && items.links.length > 3" class="px-6 py-4 border-t border-gray-200 dark:border-gray-700">
                        <div class="flex justify-between">
                            <div class="text-sm text-gray-700 dark:text-gray-300">
                                Showing {{ items.from }} to {{ items.to }} of {{ items.total }} results
                            </div>
                            <div class="flex space-x-2">
                                <Link
                                    v-for="(link, index) in items.links"
                                    :key="index"
                                    :href="link.url || '#'"
                                    :class="{
                                        'bg-indigo-600 text-white': link.active,
                                        'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700': !link.active,
                                        'pointer-events-none opacity-50': !link.url,
                                    }"
                                    class="px-3 py-1 rounded"
                                    v-html="link.label"
                                />
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
