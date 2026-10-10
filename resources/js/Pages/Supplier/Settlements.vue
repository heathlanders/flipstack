<template>
    <AppLayout title="Settlements">
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    Settlements
                </h2>
                <div class="flex items-center space-x-4">
                    <Link 
                        :href="route('supplier.settlements.pending')"
                        class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700"
                    >
                        <ClockIcon class="w-4 h-4 mr-2" />
                        Upcoming
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <!-- Summary Cards -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm text-gray-500 dark:text-gray-400">Pending Payout</p>
                                <p class="text-2xl font-bold text-yellow-600 dark:text-yellow-400">
                                    {{ formatCurrency(summary.pending_amount) }}
                                </p>
                            </div>
                            <ClockIcon class="w-10 h-10 text-yellow-400" />
                        </div>
                    </div>

                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm text-gray-500 dark:text-gray-400">Paid (YTD)</p>
                                <p class="text-2xl font-bold text-green-600 dark:text-green-400">
                                    {{ formatCurrency(summary.ytd_paid) }}
                                </p>
                            </div>
                            <BanknotesIcon class="w-10 h-10 text-green-400" />
                        </div>
                    </div>

                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm text-gray-500 dark:text-gray-400">Total Settlements</p>
                                <p class="text-2xl font-bold text-gray-900 dark:text-white">
                                    {{ summary.total_settlements }}
                                </p>
                            </div>
                            <DocumentTextIcon class="w-10 h-10 text-gray-400" />
                        </div>
                    </div>

                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm text-gray-500 dark:text-gray-400">Next Settlement</p>
                                <p class="text-lg font-bold text-gray-900 dark:text-white">
                                    {{ nextSettlementDate ? formatDate(nextSettlementDate) : 'N/A' }}
                                </p>
                            </div>
                            <CalendarIcon class="w-10 h-10 text-indigo-400" />
                        </div>
                    </div>
                </div>

                <!-- Filters -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-4 mb-6">
                    <div class="flex flex-wrap gap-4">
                        <select 
                            v-model="filters.status"
                            @change="applyFilters"
                            class="border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md shadow-sm text-sm"
                        >
                            <option value="">All Statuses</option>
                            <option value="pending">Pending</option>
                            <option value="processing">Processing</option>
                            <option value="approved">Approved</option>
                            <option value="paid">Paid</option>
                            <option value="disputed">Disputed</option>
                        </select>

                        <input 
                            v-model="filters.date_from"
                            type="date"
                            @change="applyFilters"
                            class="border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md shadow-sm text-sm"
                            placeholder="From Date"
                        />

                        <input 
                            v-model="filters.date_to"
                            type="date"
                            @change="applyFilters"
                            class="border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md shadow-sm text-sm"
                            placeholder="To Date"
                        />

                        <button 
                            v-if="hasFilters"
                            @click="clearFilters"
                            class="text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200"
                        >
                            Clear filters
                        </button>

                        <div class="ml-auto">
                            <button 
                                @click="exportSettlements"
                                class="inline-flex items-center px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600"
                            >
                                <ArrowDownTrayIcon class="w-4 h-4 mr-2" />
                                Export
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Settlements Table -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-900">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    Settlement
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    Period
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    Items
                                </th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    Gross
                                </th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    Commission
                                </th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    Net
                                </th>
                                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    Status
                                </th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                            <tr 
                                v-for="settlement in settlements.data" 
                                :key="settlement.id"
                                class="hover:bg-gray-50 dark:hover:bg-gray-700"
                            >
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <Link 
                                        :href="route('supplier.settlements.show', settlement.id)"
                                        class="text-sm font-medium text-indigo-600 dark:text-indigo-400 hover:underline"
                                    >
                                        {{ settlement.settlement_number }}
                                    </Link>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                    {{ formatDateRange(settlement.period_start, settlement.period_end) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                    {{ settlement.items_count }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right text-gray-900 dark:text-white">
                                    {{ formatCurrency(settlement.gross_amount) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right text-red-600 dark:text-red-400">
                                    -{{ formatCurrency(settlement.commission_amount) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right font-medium text-green-600 dark:text-green-400">
                                    {{ formatCurrency(settlement.net_amount) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <span :class="getStatusBadgeClass(settlement.status)" class="px-2 py-1 text-xs font-medium rounded-full">
                                        {{ formatStatus(settlement.status) }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm">
                                    <div class="flex items-center justify-end space-x-2">
                                        <Link 
                                            :href="route('supplier.settlements.show', settlement.id)"
                                            class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
                                            title="View Details"
                                        >
                                            <EyeIcon class="w-5 h-5" />
                                        </Link>
                                        <button 
                                            v-if="settlement.status === 'paid'"
                                            @click="downloadPdf(settlement)"
                                            class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
                                            title="Download PDF"
                                        >
                                            <ArrowDownTrayIcon class="w-5 h-5" />
                                        </button>
                                        <button 
                                            v-if="canDispute(settlement)"
                                            @click="openDisputeModal(settlement)"
                                            class="text-gray-400 hover:text-red-600 dark:hover:text-red-400"
                                            title="Dispute"
                                        >
                                            <ExclamationTriangleIcon class="w-5 h-5" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <!-- Empty State -->
                    <div v-if="settlements.data.length === 0" class="px-6 py-12 text-center">
                        <DocumentTextIcon class="w-12 h-12 text-gray-300 dark:text-gray-600 mx-auto mb-4" />
                        <p class="text-gray-500 dark:text-gray-400">No settlements found</p>
                    </div>

                    <!-- Pagination -->
                    <div v-if="settlements.data.length > 0" class="px-6 py-4 border-t border-gray-200 dark:border-gray-700">
                        <Pagination :links="settlements.links" />
                    </div>
                </div>

                <!-- Tax Documents Section -->
                <div class="mt-8 bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Tax Documents</h3>
                    
                    <div v-if="taxDocuments.length > 0" class="space-y-3">
                        <div 
                            v-for="doc in taxDocuments" 
                            :key="doc.year"
                            class="flex items-center justify-between p-4 bg-gray-50 dark:bg-gray-700 rounded-lg"
                        >
                            <div class="flex items-center space-x-3">
                                <DocumentTextIcon class="w-8 h-8 text-gray-400" />
                                <div>
                                    <p class="font-medium text-gray-900 dark:text-white">
                                        1099-K ({{ doc.year }})
                                    </p>
                                    <p class="text-sm text-gray-500 dark:text-gray-400">
                                        Total: {{ formatCurrency(doc.total_amount) }}
                                    </p>
                                </div>
                            </div>
                            <button 
                                @click="downloadTaxDocument(doc.year)"
                                class="px-4 py-2 text-sm font-medium text-indigo-600 dark:text-indigo-400 hover:bg-indigo-50 dark:hover:bg-gray-600 rounded-md"
                            >
                                Download
                            </button>
                        </div>
                    </div>

                    <div v-else class="text-center py-8">
                        <DocumentTextIcon class="w-12 h-12 text-gray-300 dark:text-gray-600 mx-auto mb-4" />
                        <p class="text-gray-500 dark:text-gray-400">No tax documents available</p>
                        <p class="text-sm text-gray-400 dark:text-gray-500 mt-1">
                            Tax documents are generated for years with over $600 in payments
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Dispute Modal -->
        <Modal :show="showDisputeModal" @close="closeDisputeModal">
            <div class="p-6">
                <h2 class="text-lg font-medium text-gray-900 dark:text-white mb-4">
                    Dispute Settlement
                </h2>
                <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">
                    You're disputing settlement <strong>{{ disputeSettlement?.settlement_number }}</strong>
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
                        @click="closeDisputeModal"
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

<script setup>
import { ref, reactive, computed } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Modal from '@/Components/Modal.vue';
import Pagination from '@/Components/Pagination.vue';
import {
    ClockIcon,
    BanknotesIcon,
    DocumentTextIcon,
    CalendarIcon,
    ArrowDownTrayIcon,
    EyeIcon,
    ExclamationTriangleIcon
} from '@heroicons/vue/24/outline';

const props = defineProps({
    settlements: { type: Object, default: () => ({ data: [], links: [] }) },
    summary: { type: Object, default: () => ({}) },
    filters: { type: Object, default: () => ({}) },
    taxDocuments: { type: Array, default: () => [] },
    nextSettlementDate: { type: String, default: null },
});

const filters = reactive({
    status: '',
    date_from: '',
    date_to: ''
});

const showDisputeModal = ref(false);
const disputeSettlement = ref(null);
const disputeForm = reactive({
    reason: '',
    details: ''
});

const hasFilters = computed(() => {
    return filters.status || filters.date_from || filters.date_to;
});

function formatCurrency(value) {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD'
    }).format(value || 0);
}

function formatDate(date) {
    if (!date) return '';
    return new Date(date).toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric'
    });
}

function formatDateRange(start, end) {
    const startDate = new Date(start);
    const endDate = new Date(end);
    const options = { month: 'short', day: 'numeric' };
    
    if (startDate.getFullYear() !== endDate.getFullYear()) {
        return `${startDate.toLocaleDateString('en-US', { ...options, year: '2-digit' })} - ${endDate.toLocaleDateString('en-US', { ...options, year: '2-digit' })}`;
    }
    return `${startDate.toLocaleDateString('en-US', options)} - ${endDate.toLocaleDateString('en-US', options)}`;
}

function formatStatus(status) {
    return status.charAt(0).toUpperCase() + status.slice(1);
}

function getStatusBadgeClass(status) {
    const classes = {
        'draft': 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300',
        'pending': 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
        'processing': 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
        'approved': 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
        'paid': 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
        'disputed': 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
        'cancelled': 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300'
    };
    return classes[status] || classes['draft'];
}

function canDispute(settlement) {
    return ['pending', 'approved'].includes(settlement.status) && !settlement.dispute_status;
}

function applyFilters() {
    router.get(route('supplier.settlements.index'), filters, {
        preserveState: true,
        preserveScroll: true
    });
}

function clearFilters() {
    filters.status = '';
    filters.date_from = '';
    filters.date_to = '';
    applyFilters();
}

function exportSettlements() {
    window.location.href = route('supplier.settlements.export', filters);
}

function downloadPdf(settlement) {
    window.location.href = route('supplier.settlements.download', settlement.id);
}

function downloadTaxDocument(year) {
    window.location.href = route('supplier.settlements.tax-document', year);
}

function openDisputeModal(settlement) {
    disputeSettlement.value = settlement;
    disputeForm.reason = '';
    disputeForm.details = '';
    showDisputeModal.value = true;
}

function closeDisputeModal() {
    showDisputeModal.value = false;
    disputeSettlement.value = null;
}

function submitDispute() {
    router.post(route('supplier.settlements.dispute', disputeSettlement.value.id), disputeForm, {
        onSuccess: () => {
            closeDisputeModal();
        }
    });
}
</script>
