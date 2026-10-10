<script setup>
import { ref, computed, onMounted } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Modal from '@/Components/Modal.vue';
import Pagination from '@/Components/Pagination.vue';
import {
    ArrowLeftIcon,
    ArrowDownTrayIcon,
    ExclamationTriangleIcon,
    CheckCircleIcon,
    ClockIcon,
    BanknotesIcon,
    DocumentTextIcon,
    XCircleIcon,
    ArrowPathIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps({
    settlement: {
        type: Object,
        default: () => ({
            financials: {},
            period: {},
            payment: {},
            tax: {},
            items: {},
            processing: {},
            links: {},
        }),
    },
});

const showDisputeModal = ref(false);
const loadingItems = ref(false);
const items = ref([]);
const itemsPagination = ref(null);
const disputeForm = ref({
    reason: '',
    details: '',
});

const statusConfig = {
    draft: { color: 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300', icon: DocumentTextIcon },
    pending_review: { color: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200', icon: ClockIcon },
    approved: { color: 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200', icon: CheckCircleIcon },
    pending_payment: { color: 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900 dark:text-indigo-200', icon: ClockIcon },
    processing: { color: 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200', icon: ArrowPathIcon },
    paid: { color: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200', icon: BanknotesIcon },
    disputed: { color: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200', icon: ExclamationTriangleIcon },
    cancelled: { color: 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300', icon: XCircleIcon },
};

const currentStatusConfig = computed(() => {
    return statusConfig[props.settlement?.status] || statusConfig.draft;
});

const formatCurrency = (value) => {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD',
    }).format(value || 0);
};

const formatDate = (date) => {
    if (!date) return 'N/A';
    return new Date(date).toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    });
};

const formatDateTime = (date) => {
    if (!date) return 'N/A';
    return new Date(date).toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const loadItems = async (page = 1) => {
    loadingItems.value = true;
    try {
        const response = await fetch(route('supplier.settlements.items', props.settlement.id) + `?page=${page}`);
        const data = await response.json();
        items.value = data.items.data || [];
        itemsPagination.value = data.items;
    } catch (error) {
        console.error('Failed to load items:', error);
    } finally {
        loadingItems.value = false;
    }
};

const downloadPdf = () => {
    window.location.href = props.settlement?.links?.download_pdf;
};

const openDisputeModal = () => {
    disputeForm.value = { reason: '', details: '' };
    showDisputeModal.value = true;
};

const submitDispute = () => {
    router.post(route('supplier.settlements.dispute', props.settlement.id), {
        reason: `${disputeForm.value.reason}: ${disputeForm.value.details}`,
    }, {
        onSuccess: () => {
            showDisputeModal.value = false;
        },
    });
};

onMounted(() => {
    loadItems();
});
</script>

<template>
    <Head :title="`Settlement ${settlement.settlement_number}`" />

    <AppLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-4">
                    <Link
                        :href="route('supplier.settlements.index')"
                        class="p-2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700"
                    >
                        <ArrowLeftIcon class="w-5 h-5" />
                    </Link>
                    <div>
                        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                            Settlement {{ settlement.settlement_number }}
                        </h2>
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            {{ formatDate(settlement.period?.start) }} - {{ formatDate(settlement.period?.end) }}
                        </p>
                    </div>
                </div>
                <div class="flex items-center space-x-3">
                    <span :class="[currentStatusConfig.color, 'px-3 py-1 rounded-full text-sm font-medium inline-flex items-center']">
                        <component :is="currentStatusConfig.icon" class="w-4 h-4 mr-1.5" />
                        {{ settlement.status_label }}
                    </span>
                    <button
                        v-if="settlement.is_finalized"
                        @click="downloadPdf"
                        class="inline-flex items-center px-4 py-2 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md font-semibold text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest shadow-sm hover:bg-gray-50 dark:hover:bg-gray-600"
                    >
                        <ArrowDownTrayIcon class="w-4 h-4 mr-2" />
                        Download PDF
                    </button>
                    <button
                        v-if="settlement.can_dispute"
                        @click="openDisputeModal"
                        class="inline-flex items-center px-4 py-2 bg-red-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-red-700"
                    >
                        <ExclamationTriangleIcon class="w-4 h-4 mr-2" />
                        Dispute
                    </button>
                </div>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <!-- Financial Summary Cards -->
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-4">
                        <p class="text-sm text-gray-500 dark:text-gray-400">Gross Sales</p>
                        <p class="text-2xl font-bold text-gray-900 dark:text-white">
                            {{ formatCurrency(settlement.financials?.gross_sales) }}
                        </p>
                    </div>
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-4">
                        <p class="text-sm text-gray-500 dark:text-gray-400">Returns</p>
                        <p class="text-2xl font-bold text-red-600 dark:text-red-400">
                            -{{ formatCurrency(settlement.financials?.returns_amount) }}
                        </p>
                    </div>
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-4">
                        <p class="text-sm text-gray-500 dark:text-gray-400">Commission</p>
                        <p class="text-2xl font-bold text-orange-600 dark:text-orange-400">
                            -{{ formatCurrency(settlement.financials?.commission_amount) }}
                        </p>
                    </div>
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-4">
                        <p class="text-sm text-gray-500 dark:text-gray-400">Your Payout</p>
                        <p class="text-2xl font-bold text-green-600 dark:text-green-400">
                            {{ formatCurrency(settlement.financials?.net_amount) }}
                        </p>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <!-- Main Content - Financial Breakdown -->
                    <div class="lg:col-span-2 space-y-6">
                        <!-- Detailed Breakdown -->
                        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
                            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                                <h3 class="text-lg font-medium text-gray-900 dark:text-white">Financial Breakdown</h3>
                            </div>
                            <div class="p-6">
                                <dl class="space-y-4">
                                    <div class="flex justify-between py-2">
                                        <dt class="text-gray-600 dark:text-gray-400">Gross Sales</dt>
                                        <dd class="font-medium text-gray-900 dark:text-white">
                                            {{ formatCurrency(settlement.financials?.gross_sales) }}
                                        </dd>
                                    </div>
                                    <div class="flex justify-between py-2 border-t border-gray-100 dark:border-gray-700">
                                        <dt class="text-gray-600 dark:text-gray-400">Returns</dt>
                                        <dd class="font-medium text-red-600 dark:text-red-400">
                                            -{{ formatCurrency(settlement.financials?.returns_amount) }}
                                        </dd>
                                    </div>
                                    <div class="flex justify-between py-2 border-t border-gray-100 dark:border-gray-700 bg-gray-50 dark:bg-gray-700/50 -mx-6 px-6">
                                        <dt class="font-medium text-gray-900 dark:text-white">Net Sales</dt>
                                        <dd class="font-bold text-gray-900 dark:text-white">
                                            {{ formatCurrency(settlement.financials?.net_sales) }}
                                        </dd>
                                    </div>
                                    <div class="flex justify-between py-2">
                                        <dt class="text-gray-600 dark:text-gray-400">Commission</dt>
                                        <dd class="font-medium text-red-600 dark:text-red-400">
                                            -{{ formatCurrency(settlement.financials?.commission_amount) }}
                                        </dd>
                                    </div>
                                    <div v-if="settlement.financials?.fees_amount > 0" class="flex justify-between py-2 border-t border-gray-100 dark:border-gray-700">
                                        <dt class="text-gray-600 dark:text-gray-400">Fees</dt>
                                        <dd class="font-medium text-red-600 dark:text-red-400">
                                            -{{ formatCurrency(settlement.financials?.fees_amount) }}
                                        </dd>
                                    </div>
                                    <div v-if="settlement.financials?.adjustments_amount !== 0" class="flex justify-between py-2 border-t border-gray-100 dark:border-gray-700">
                                        <dt class="text-gray-600 dark:text-gray-400">Adjustments</dt>
                                        <dd :class="settlement.financials?.adjustments_amount > 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'" class="font-medium">
                                            {{ settlement.financials?.adjustments_amount > 0 ? '+' : '-' }}{{ formatCurrency(Math.abs(settlement.financials?.adjustments_amount)) }}
                                        </dd>
                                    </div>
                                    <div v-if="settlement.financials?.bonus_amount > 0" class="flex justify-between py-2 border-t border-gray-100 dark:border-gray-700">
                                        <dt class="text-gray-600 dark:text-gray-400">Bonus</dt>
                                        <dd class="font-medium text-green-600 dark:text-green-400">
                                            +{{ formatCurrency(settlement.financials?.bonus_amount) }}
                                        </dd>
                                    </div>
                                    <div v-if="settlement.tax?.withheld > 0" class="flex justify-between py-2 border-t border-gray-100 dark:border-gray-700">
                                        <dt class="text-gray-600 dark:text-gray-400">Tax Withheld</dt>
                                        <dd class="font-medium text-red-600 dark:text-red-400">
                                            -{{ formatCurrency(settlement.tax?.withheld) }}
                                        </dd>
                                    </div>
                                    <div class="flex justify-between py-3 border-t-2 border-gray-200 dark:border-gray-600 bg-green-50 dark:bg-green-900/20 -mx-6 px-6 rounded-b-lg">
                                        <dt class="font-bold text-gray-900 dark:text-white">Net Payout</dt>
                                        <dd class="font-bold text-xl text-green-600 dark:text-green-400">
                                            {{ formatCurrency(settlement.financials?.net_amount) }}
                                        </dd>
                                    </div>
                                </dl>
                            </div>
                        </div>

                        <!-- Items Table -->
                        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
                            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 flex justify-between items-center">
                                <h3 class="text-lg font-medium text-gray-900 dark:text-white">
                                    Items ({{ settlement.items?.count }})
                                </h3>
                                <span v-if="settlement.items?.returned_count > 0" class="text-sm text-red-600 dark:text-red-400">
                                    {{ settlement.items?.returned_count }} returned
                                </span>
                            </div>

                            <div v-if="loadingItems" class="p-12 text-center">
                                <ArrowPathIcon class="w-8 h-8 text-gray-400 animate-spin mx-auto" />
                                <p class="text-gray-500 dark:text-gray-400 mt-2">Loading items...</p>
                            </div>

                            <table v-else-if="items.length > 0" class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-50 dark:bg-gray-900">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                            Product
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                            Sale Date
                                        </th>
                                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                            Sale Price
                                        </th>
                                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                            Commission
                                        </th>
                                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                            Your Payout
                                        </th>
                                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                            Status
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                    <tr
                                        v-for="item in items"
                                        :key="item.id"
                                        :class="item.is_returned ? 'bg-red-50 dark:bg-red-900/10' : ''"
                                        class="hover:bg-gray-50 dark:hover:bg-gray-700"
                                    >
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm font-medium text-gray-900 dark:text-white">
                                                {{ item.product_name || 'Unknown Product' }}
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                            {{ formatDate(item.sale_date) }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-right text-gray-900 dark:text-white">
                                            {{ formatCurrency(item.sale_price) }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-right text-red-600 dark:text-red-400">
                                            -{{ formatCurrency(item.commission) }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-right font-medium text-green-600 dark:text-green-400">
                                            {{ formatCurrency(item.payout) }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-center">
                                            <span v-if="item.is_returned" class="px-2 py-1 text-xs font-medium rounded-full bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">
                                                Returned
                                            </span>
                                            <span v-else class="px-2 py-1 text-xs font-medium rounded-full bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                                                {{ item.status }}
                                            </span>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>

                            <div v-else class="p-12 text-center">
                                <DocumentTextIcon class="w-12 h-12 text-gray-300 dark:text-gray-600 mx-auto mb-4" />
                                <p class="text-gray-500 dark:text-gray-400">No items in this settlement</p>
                            </div>

                            <!-- Pagination -->
                            <div v-if="itemsPagination && itemsPagination.last_page > 1" class="px-6 py-4 border-t border-gray-200 dark:border-gray-700">
                                <div class="flex justify-between items-center">
                                    <span class="text-sm text-gray-500 dark:text-gray-400">
                                        Showing {{ itemsPagination.from }} to {{ itemsPagination.to }} of {{ itemsPagination.total }}
                                    </span>
                                    <div class="flex space-x-2">
                                        <button
                                            v-for="page in itemsPagination.last_page"
                                            :key="page"
                                            @click="loadItems(page)"
                                            :class="[
                                                'px-3 py-1 text-sm rounded',
                                                page === itemsPagination.current_page
                                                    ? 'bg-indigo-600 text-white'
                                                    : 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600'
                                            ]"
                                        >
                                            {{ page }}
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Sidebar -->
                    <div class="space-y-6">
                        <!-- Settlement Info -->
                        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Settlement Details</h3>
                            <dl class="space-y-4">
                                <div>
                                    <dt class="text-sm text-gray-500 dark:text-gray-400">Settlement Number</dt>
                                    <dd class="font-medium text-gray-900 dark:text-white">{{ settlement.settlement_number }}</dd>
                                </div>
                                <div>
                                    <dt class="text-sm text-gray-500 dark:text-gray-400">Period</dt>
                                    <dd class="font-medium text-gray-900 dark:text-white">
                                        {{ formatDate(settlement.period?.start) }} - {{ formatDate(settlement.period?.end) }}
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-sm text-gray-500 dark:text-gray-400">Items</dt>
                                    <dd class="font-medium text-gray-900 dark:text-white">{{ settlement.items?.count }}</dd>
                                </div>
                                <div>
                                    <dt class="text-sm text-gray-500 dark:text-gray-400">Created</dt>
                                    <dd class="font-medium text-gray-900 dark:text-white">{{ formatDateTime(settlement.created_at) }}</dd>
                                </div>
                            </dl>
                        </div>

                        <!-- Payment Info -->
                        <div v-if="settlement.payment" class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Payment Information</h3>
                            <dl class="space-y-4">
                                <div v-if="settlement.payment.method">
                                    <dt class="text-sm text-gray-500 dark:text-gray-400">Payment Method</dt>
                                    <dd class="font-medium text-gray-900 dark:text-white capitalize">{{ settlement.payment.method }}</dd>
                                </div>
                                <div v-if="settlement.payment.date">
                                    <dt class="text-sm text-gray-500 dark:text-gray-400">Payment Date</dt>
                                    <dd class="font-medium text-gray-900 dark:text-white">{{ formatDate(settlement.payment.date) }}</dd>
                                </div>
                                <div v-if="settlement.payment.due_date && !settlement.payment.date">
                                    <dt class="text-sm text-gray-500 dark:text-gray-400">Due Date</dt>
                                    <dd :class="settlement.payment.is_overdue ? 'text-red-600 dark:text-red-400' : 'text-gray-900 dark:text-white'" class="font-medium">
                                        {{ formatDate(settlement.payment.due_date) }}
                                        <span v-if="settlement.payment.is_overdue" class="text-xs ml-1">(Overdue)</span>
                                    </dd>
                                </div>
                                <div v-if="settlement.payment.reference">
                                    <dt class="text-sm text-gray-500 dark:text-gray-400">Reference</dt>
                                    <dd class="font-medium text-gray-900 dark:text-white font-mono text-sm">{{ settlement.payment.reference }}</dd>
                                </div>
                            </dl>
                        </div>

                        <!-- Processing Timeline -->
                        <div v-if="settlement.processing" class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Processing History</h3>
                            <div class="space-y-4">
                                <div v-if="settlement.processing?.reviewed_at" class="flex items-start space-x-3">
                                    <div class="flex-shrink-0 w-8 h-8 rounded-full bg-blue-100 dark:bg-blue-900 flex items-center justify-center">
                                        <CheckCircleIcon class="w-4 h-4 text-blue-600 dark:text-blue-400" />
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium text-gray-900 dark:text-white">Reviewed</p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">
                                            {{ settlement.processing?.reviewed_by }} - {{ formatDateTime(settlement.processing?.reviewed_at) }}
                                        </p>
                                    </div>
                                </div>
                                <div v-if="settlement.processing?.approved_at" class="flex items-start space-x-3">
                                    <div class="flex-shrink-0 w-8 h-8 rounded-full bg-green-100 dark:bg-green-900 flex items-center justify-center">
                                        <CheckCircleIcon class="w-4 h-4 text-green-600 dark:text-green-400" />
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium text-gray-900 dark:text-white">Approved</p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">
                                            {{ settlement.processing?.approved_by }} - {{ formatDateTime(settlement.processing?.approved_at) }}
                                        </p>
                                    </div>
                                </div>
                                <div v-if="settlement.processing?.processed_at" class="flex items-start space-x-3">
                                    <div class="flex-shrink-0 w-8 h-8 rounded-full bg-purple-100 dark:bg-purple-900 flex items-center justify-center">
                                        <BanknotesIcon class="w-4 h-4 text-purple-600 dark:text-purple-400" />
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium text-gray-900 dark:text-white">Processed</p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">
                                            {{ settlement.processing?.processed_by }} - {{ formatDateTime(settlement.processing?.processed_at) }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Dispute Info -->
                        <div v-if="settlement.dispute?.is_disputed" class="bg-red-50 dark:bg-red-900/20 rounded-lg shadow-sm border border-red-200 dark:border-red-800 p-6">
                            <h3 class="text-lg font-medium text-red-800 dark:text-red-200 mb-4">Dispute Information</h3>
                            <div class="space-y-4">
                                <div>
                                    <dt class="text-sm text-red-600 dark:text-red-400">Dispute Reason</dt>
                                    <dd class="font-medium text-red-900 dark:text-red-100">{{ settlement.dispute.reason }}</dd>
                                </div>
                                <div v-if="settlement.dispute.resolution">
                                    <dt class="text-sm text-red-600 dark:text-red-400">Resolution</dt>
                                    <dd class="font-medium text-red-900 dark:text-red-100">{{ settlement.dispute.resolution }}</dd>
                                </div>
                            </div>
                        </div>

                        <!-- Notes -->
                        <div v-if="settlement.notes" class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Notes</h3>
                            <p class="text-gray-600 dark:text-gray-400 text-sm whitespace-pre-wrap">{{ settlement.notes }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Dispute Modal -->
        <Modal :show="showDisputeModal" @close="showDisputeModal = false">
            <div class="p-6">
                <h2 class="text-lg font-medium text-gray-900 dark:text-white mb-2">
                    Dispute Settlement
                </h2>
                <p class="text-sm text-gray-500 dark:text-gray-400 mb-6">
                    Please provide details about why you're disputing settlement <strong>{{ settlement.settlement_number }}</strong>
                </p>

                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Reason for Dispute
                        </label>
                        <select
                            v-model="disputeForm.reason"
                            class="w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md shadow-sm"
                        >
                            <option value="">Select a reason...</option>
                            <option value="incorrect_amount">Incorrect Amount</option>
                            <option value="missing_items">Missing Items</option>
                            <option value="wrong_commission">Wrong Commission Rate</option>
                            <option value="incorrect_returns">Incorrect Return Deductions</option>
                            <option value="other">Other</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Details
                        </label>
                        <textarea
                            v-model="disputeForm.details"
                            rows="4"
                            class="w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md shadow-sm"
                            placeholder="Please provide specific details about your dispute..."
                        ></textarea>
                    </div>
                </div>

                <div class="mt-6 flex justify-end space-x-3">
                    <button
                        @click="showDisputeModal = false"
                        class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-700 rounded-md hover:bg-gray-200 dark:hover:bg-gray-600"
                    >
                        Cancel
                    </button>
                    <button
                        @click="submitDispute"
                        :disabled="!disputeForm.reason || !disputeForm.details"
                        class="px-4 py-2 text-sm font-medium text-white bg-red-600 rounded-md hover:bg-red-700 disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        Submit Dispute
                    </button>
                </div>
            </div>
        </Modal>
    </AppLayout>
</template>
