<template>
    <AdminLayout title="Settlement Management">
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200">
                        Settlement Management
                    </h2>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                        Process, approve, and track supplier settlements
                    </p>
                </div>
                <div class="flex items-center gap-3">
                    <button
                        @click="showBulkGenerateModal = true"
                        class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors"
                    >
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                        </svg>
                        Generate Settlements
                    </button>
                    <button
                        @click="exportSettlements"
                        class="inline-flex items-center px-4 py-2 bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-200 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors"
                    >
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                        </svg>
                        Export
                    </button>
                </div>
            </div>
        </template>

        <!-- Summary Stats -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4 mb-6">
            <StatCard
                title="Pending Approval"
                :value="stats.pending_count"
                :subvalue="formatCurrency(stats.pending_amount)"
                icon="clock"
                color="yellow"
            />
            <StatCard
                title="Approved"
                :value="stats.approved_count"
                :subvalue="formatCurrency(stats.approved_amount)"
                icon="check"
                color="blue"
            />
            <StatCard
                title="Processing"
                :value="stats.processing_count"
                :subvalue="formatCurrency(stats.processing_amount)"
                icon="refresh"
                color="indigo"
            />
            <StatCard
                title="Paid This Month"
                :value="stats.paid_count"
                :subvalue="formatCurrency(stats.paid_amount)"
                icon="currency"
                color="green"
            />
            <StatCard
                title="Disputed"
                :value="stats.disputed_count"
                :subvalue="formatCurrency(stats.disputed_amount)"
                icon="exclamation"
                color="red"
            />
        </div>

        <!-- Filters and Search -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-4 mb-6">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Search</label>
                    <input
                        v-model="filters.search"
                        type="text"
                        placeholder="Settlement # or supplier..."
                        class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    />
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Status</label>
                    <select
                        v-model="filters.status"
                        class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    >
                        <option value="">All Statuses</option>
                        <option value="pending">Pending</option>
                        <option value="approved">Approved</option>
                        <option value="processing">Processing</option>
                        <option value="paid">Paid</option>
                        <option value="disputed">Disputed</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Tier</label>
                    <select
                        v-model="filters.tier"
                        class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    >
                        <option value="">All Tiers</option>
                        <option v-for="tier in tiers" :key="tier.id" :value="tier.id">
                            {{ tier.name }}
                        </option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Period</label>
                    <select
                        v-model="filters.period"
                        class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    >
                        <option value="">All Periods</option>
                        <option value="current">Current Month</option>
                        <option value="last">Last Month</option>
                        <option value="quarter">This Quarter</option>
                        <option value="year">This Year</option>
                    </select>
                </div>
                <div class="flex items-end">
                    <button
                        @click="resetFilters"
                        class="w-full px-4 py-2 text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200 border border-gray-300 dark:border-gray-600 rounded-md transition-colors"
                    >
                        Reset Filters
                    </button>
                </div>
            </div>
        </div>

        <!-- Bulk Actions Bar -->
        <div
            v-if="selectedSettlements.length > 0"
            class="bg-indigo-50 dark:bg-indigo-900/30 border border-indigo-200 dark:border-indigo-700 rounded-lg p-4 mb-6 flex items-center justify-between"
        >
            <span class="text-indigo-700 dark:text-indigo-300">
                {{ selectedSettlements.length }} settlement(s) selected
            </span>
            <div class="flex items-center gap-3">
                <button
                    @click="bulkApprove"
                    :disabled="!canBulkApprove"
                    class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                >
                    Approve Selected
                </button>
                <button
                    @click="bulkProcess"
                    :disabled="!canBulkProcess"
                    class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                >
                    Process Selected
                </button>
                <button
                    @click="bulkExport"
                    class="px-4 py-2 bg-gray-600 text-white rounded-md hover:bg-gray-700 transition-colors"
                >
                    Export Selected
                </button>
            </div>
        </div>

        <!-- Settlements Table -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-700">
                        <tr>
                            <th class="w-12 px-4 py-3">
                                <input
                                    type="checkbox"
                                    v-model="selectAll"
                                    @change="toggleSelectAll"
                                    class="rounded border-gray-300 dark:border-gray-600 text-indigo-600 focus:ring-indigo-500"
                                />
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider cursor-pointer" @click="sort('settlement_number')">
                                <div class="flex items-center gap-1">
                                    Settlement #
                                    <SortIcon :direction="getSortDirection('settlement_number')" />
                                </div>
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider cursor-pointer" @click="sort('supplier')">
                                <div class="flex items-center gap-1">
                                    Supplier
                                    <SortIcon :direction="getSortDirection('supplier')" />
                                </div>
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider cursor-pointer" @click="sort('period')">
                                <div class="flex items-center gap-1">
                                    Period
                                    <SortIcon :direction="getSortDirection('period')" />
                                </div>
                            </th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider cursor-pointer" @click="sort('gross_amount')">
                                <div class="flex items-center justify-end gap-1">
                                    Gross
                                    <SortIcon :direction="getSortDirection('gross_amount')" />
                                </div>
                            </th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider cursor-pointer" @click="sort('commission_amount')">
                                <div class="flex items-center justify-end gap-1">
                                    Commission
                                    <SortIcon :direction="getSortDirection('commission_amount')" />
                                </div>
                            </th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider cursor-pointer" @click="sort('net_amount')">
                                <div class="flex items-center justify-end gap-1">
                                    Net Amount
                                    <SortIcon :direction="getSortDirection('net_amount')" />
                                </div>
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                Status
                            </th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        <tr
                            v-for="settlement in settlements.data"
                            :key="settlement.id"
                            class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors"
                        >
                            <td class="px-4 py-4">
                                <input
                                    type="checkbox"
                                    :value="settlement.id"
                                    v-model="selectedSettlements"
                                    class="rounded border-gray-300 dark:border-gray-600 text-indigo-600 focus:ring-indigo-500"
                                />
                            </td>
                            <td class="px-4 py-4">
                                <Link
                                    :href="route('admin.settlements.show', settlement.id)"
                                    class="text-indigo-600 dark:text-indigo-400 hover:underline font-medium"
                                >
                                    {{ settlement.settlement_number }}
                                </Link>
                            </td>
                            <td class="px-4 py-4">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-full bg-gray-200 dark:bg-gray-600 flex items-center justify-center">
                                        <span class="text-sm font-medium text-gray-600 dark:text-gray-300">
                                            {{ settlement.supplier.name.charAt(0) }}
                                        </span>
                                    </div>
                                    <div>
                                        <Link
                                            :href="route('admin.suppliers.show', settlement.supplier.id)"
                                            class="text-gray-900 dark:text-gray-100 hover:text-indigo-600 dark:hover:text-indigo-400 font-medium"
                                        >
                                            {{ settlement.supplier.name }}
                                        </Link>
                                        <div class="text-xs text-gray-500 dark:text-gray-400">
                                            {{ settlement.supplier.tier?.name || 'No tier' }}
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-4 text-sm text-gray-600 dark:text-gray-400">
                                {{ formatPeriod(settlement.period_start, settlement.period_end) }}
                            </td>
                            <td class="px-4 py-4 text-sm text-right text-gray-900 dark:text-gray-100">
                                {{ formatCurrency(settlement.gross_amount) }}
                            </td>
                            <td class="px-4 py-4 text-sm text-right text-gray-900 dark:text-gray-100">
                                {{ formatCurrency(settlement.commission_amount) }}
                                <span class="text-xs text-gray-500">({{ settlement.commission_rate }}%)</span>
                            </td>
                            <td class="px-4 py-4 text-sm text-right font-semibold text-gray-900 dark:text-gray-100">
                                {{ formatCurrency(settlement.net_amount) }}
                            </td>
                            <td class="px-4 py-4">
                                <StatusBadge :status="settlement.status" />
                            </td>
                            <td class="px-4 py-4 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <button
                                        v-if="settlement.status === 'pending'"
                                        @click="approveSettlement(settlement)"
                                        class="p-1.5 text-green-600 hover:bg-green-50 dark:hover:bg-green-900/30 rounded transition-colors"
                                        title="Approve"
                                    >
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                        </svg>
                                    </button>
                                    <button
                                        v-if="settlement.status === 'approved'"
                                        @click="processSettlement(settlement)"
                                        class="p-1.5 text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-900/30 rounded transition-colors"
                                        title="Process Payment"
                                    >
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>
                                        </svg>
                                    </button>
                                    <button
                                        @click="downloadPdf(settlement)"
                                        class="p-1.5 text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700 rounded transition-colors"
                                        title="Download PDF"
                                    >
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                        </svg>
                                    </button>
                                    <div class="relative" v-click-outside="() => closeDropdown(settlement.id)">
                                        <button
                                            @click="toggleDropdown(settlement.id)"
                                            class="p-1.5 text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700 rounded transition-colors"
                                        >
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z"/>
                                            </svg>
                                        </button>
                                        <div
                                            v-if="openDropdown === settlement.id"
                                            class="absolute right-0 mt-2 w-48 bg-white dark:bg-gray-800 rounded-lg shadow-lg border border-gray-200 dark:border-gray-700 py-1 z-10"
                                        >
                                            <Link
                                                :href="route('admin.settlements.show', settlement.id)"
                                                class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700"
                                            >
                                                View Details
                                            </Link>
                                            <button
                                                v-if="settlement.status === 'pending'"
                                                @click="editSettlement(settlement)"
                                                class="w-full text-left px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700"
                                            >
                                                Edit Settlement
                                            </button>
                                            <button
                                                v-if="['pending', 'approved'].includes(settlement.status)"
                                                @click="addAdjustment(settlement)"
                                                class="w-full text-left px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700"
                                            >
                                                Add Adjustment
                                            </button>
                                            <hr class="my-1 border-gray-200 dark:border-gray-700">
                                            <button
                                                v-if="['pending', 'approved'].includes(settlement.status)"
                                                @click="cancelSettlement(settlement)"
                                                class="w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-red-50 dark:hover:bg-red-900/30"
                                            >
                                                Cancel Settlement
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div class="px-4 py-3 border-t border-gray-200 dark:border-gray-700">
                <Pagination :links="settlements.links" />
            </div>
        </div>

        <!-- Bulk Generate Modal -->
        <Modal :show="showBulkGenerateModal" @close="showBulkGenerateModal = false">
            <div class="p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">
                    Generate Settlements
                </h3>
                <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                    Generate settlement statements for suppliers based on their settlement frequency.
                </p>
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Period End Date
                        </label>
                        <input
                            v-model="generateForm.period_end"
                            type="date"
                            class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Supplier Filter
                        </label>
                        <select
                            v-model="generateForm.supplier_filter"
                            class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        >
                            <option value="all">All Active Suppliers</option>
                            <option value="weekly">Weekly Settlement Suppliers</option>
                            <option value="biweekly">Bi-weekly Settlement Suppliers</option>
                            <option value="monthly">Monthly Settlement Suppliers</option>
                        </select>
                    </div>
                    <div>
                        <label class="flex items-center gap-2">
                            <input
                                v-model="generateForm.auto_approve"
                                type="checkbox"
                                class="rounded border-gray-300 dark:border-gray-600 text-indigo-600 focus:ring-indigo-500"
                            />
                            <span class="text-sm text-gray-700 dark:text-gray-300">
                                Auto-approve settlements under {{ formatCurrency(500) }}
                            </span>
                        </label>
                    </div>
                </div>
                <div class="mt-6 flex justify-end gap-3">
                    <button
                        @click="showBulkGenerateModal = false"
                        class="px-4 py-2 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-md transition-colors"
                    >
                        Cancel
                    </button>
                    <button
                        @click="generateSettlements"
                        :disabled="generateForm.processing"
                        class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 disabled:opacity-50 transition-colors"
                    >
                        Generate Settlements
                    </button>
                </div>
            </div>
        </Modal>

        <!-- Adjustment Modal -->
        <Modal :show="showAdjustmentModal" @close="showAdjustmentModal = false">
            <div class="p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">
                    Add Adjustment to {{ selectedSettlement?.settlement_number }}
                </h3>
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Adjustment Type
                        </label>
                        <select
                            v-model="adjustmentForm.type"
                            class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        >
                            <option value="bonus">Bonus</option>
                            <option value="penalty">Penalty</option>
                            <option value="correction">Correction</option>
                            <option value="refund">Refund</option>
                            <option value="fee">Fee</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Amount
                        </label>
                        <div class="relative">
                            <span class="absolute left-3 top-2 text-gray-500">$</span>
                            <input
                                v-model="adjustmentForm.amount"
                                type="number"
                                step="0.01"
                                class="w-full pl-8 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            />
                        </div>
                        <p class="mt-1 text-xs text-gray-500">
                            Positive for credit, negative for debit
                        </p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Reason
                        </label>
                        <textarea
                            v-model="adjustmentForm.reason"
                            rows="3"
                            class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            placeholder="Explain the reason for this adjustment..."
                        ></textarea>
                    </div>
                </div>
                <div class="mt-6 flex justify-end gap-3">
                    <button
                        @click="showAdjustmentModal = false"
                        class="px-4 py-2 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-md transition-colors"
                    >
                        Cancel
                    </button>
                    <button
                        @click="submitAdjustment"
                        :disabled="adjustmentForm.processing"
                        class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 disabled:opacity-50 transition-colors"
                    >
                        Add Adjustment
                    </button>
                </div>
            </div>
        </Modal>
    </AdminLayout>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import StatCard from '@/Components/Admin/StatCard.vue';
import Modal from '@/Components/Admin/Modal.vue';
import Pagination from '@/Components/Pagination.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import SortIcon from '@/Components/SortIcon.vue';
import { debounce } from 'lodash';

const props = defineProps({
    settlements: Object,
    stats: Object,
    tiers: Array,
    filters: Object,
});

const filters = ref({
    search: props.filters?.search || '',
    status: props.filters?.status || '',
    tier: props.filters?.tier || '',
    period: props.filters?.period || '',
    sort: props.filters?.sort || 'created_at',
    direction: props.filters?.direction || 'desc',
});

const selectedSettlements = ref([]);
const selectAll = ref(false);
const openDropdown = ref(null);
const showBulkGenerateModal = ref(false);
const showAdjustmentModal = ref(false);
const selectedSettlement = ref(null);

const generateForm = useForm({
    period_end: new Date().toISOString().split('T')[0],
    supplier_filter: 'all',
    auto_approve: true,
});

const adjustmentForm = useForm({
    type: 'correction',
    amount: 0,
    reason: '',
});

const canBulkApprove = computed(() => {
    return selectedSettlements.value.some(id => {
        const settlement = props.settlements.data.find(s => s.id === id);
        return settlement?.status === 'pending';
    });
});

const canBulkProcess = computed(() => {
    return selectedSettlements.value.some(id => {
        const settlement = props.settlements.data.find(s => s.id === id);
        return settlement?.status === 'approved';
    });
});

const applyFilters = debounce(() => {
    router.get(route('admin.settlements.index'), {
        ...filters.value,
    }, {
        preserveState: true,
        preserveScroll: true,
    });
}, 300);

watch(filters, applyFilters, { deep: true });

function formatCurrency(amount) {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD',
    }).format(amount);
}

function formatPeriod(start, end) {
    const startDate = new Date(start);
    const endDate = new Date(end);
    return `${startDate.toLocaleDateString('en-US', { month: 'short', day: 'numeric' })} - ${endDate.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })}`;
}

function sort(field) {
    if (filters.value.sort === field) {
        filters.value.direction = filters.value.direction === 'asc' ? 'desc' : 'asc';
    } else {
        filters.value.sort = field;
        filters.value.direction = 'asc';
    }
}

function getSortDirection(field) {
    if (filters.value.sort !== field) return null;
    return filters.value.direction;
}

function resetFilters() {
    filters.value = {
        search: '',
        status: '',
        tier: '',
        period: '',
        sort: 'created_at',
        direction: 'desc',
    };
}

function toggleSelectAll() {
    if (selectAll.value) {
        selectedSettlements.value = props.settlements.data.map(s => s.id);
    } else {
        selectedSettlements.value = [];
    }
}

function toggleDropdown(id) {
    openDropdown.value = openDropdown.value === id ? null : id;
}

function closeDropdown(id) {
    if (openDropdown.value === id) {
        openDropdown.value = null;
    }
}

function approveSettlement(settlement) {
    if (confirm(`Approve settlement ${settlement.settlement_number} for ${formatCurrency(settlement.net_amount)}?`)) {
        router.post(route('admin.settlements.approve', settlement.id), {}, {
            preserveScroll: true,
        });
    }
}

function processSettlement(settlement) {
    if (confirm(`Process payment for settlement ${settlement.settlement_number}?`)) {
        router.post(route('admin.settlements.process', settlement.id), {}, {
            preserveScroll: true,
        });
    }
}

function cancelSettlement(settlement) {
    if (confirm(`Cancel settlement ${settlement.settlement_number}? This action cannot be undone.`)) {
        router.post(route('admin.settlements.cancel', settlement.id), {}, {
            preserveScroll: true,
        });
    }
}

function downloadPdf(settlement) {
    window.open(route('admin.settlements.pdf', settlement.id), '_blank');
}

function addAdjustment(settlement) {
    selectedSettlement.value = settlement;
    adjustmentForm.reset();
    showAdjustmentModal.value = true;
    openDropdown.value = null;
}

function submitAdjustment() {
    adjustmentForm.post(route('admin.settlements.adjustment', selectedSettlement.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            showAdjustmentModal.value = false;
            selectedSettlement.value = null;
        },
    });
}

function generateSettlements() {
    generateForm.post(route('admin.settlements.generate'), {
        preserveScroll: true,
        onSuccess: () => {
            showBulkGenerateModal.value = false;
        },
    });
}

function bulkApprove() {
    const pendingIds = selectedSettlements.value.filter(id => {
        const settlement = props.settlements.data.find(s => s.id === id);
        return settlement?.status === 'pending';
    });

    if (confirm(`Approve ${pendingIds.length} settlement(s)?`)) {
        router.post(route('admin.settlements.bulk-approve'), {
            ids: pendingIds,
        }, {
            preserveScroll: true,
            onSuccess: () => {
                selectedSettlements.value = [];
                selectAll.value = false;
            },
        });
    }
}

function bulkProcess() {
    const approvedIds = selectedSettlements.value.filter(id => {
        const settlement = props.settlements.data.find(s => s.id === id);
        return settlement?.status === 'approved';
    });

    if (confirm(`Process payment for ${approvedIds.length} settlement(s)?`)) {
        router.post(route('admin.settlements.bulk-process'), {
            ids: approvedIds,
        }, {
            preserveScroll: true,
            onSuccess: () => {
                selectedSettlements.value = [];
                selectAll.value = false;
            },
        });
    }
}

function bulkExport() {
    window.open(route('admin.settlements.export', {
        ids: selectedSettlements.value,
    }), '_blank');
}

function exportSettlements() {
    window.open(route('admin.settlements.export', filters.value), '_blank');
}

function editSettlement(settlement) {
    router.get(route('admin.settlements.edit', settlement.id));
    openDropdown.value = null;
}

// Directive for click outside
const vClickOutside = {
    mounted(el, binding) {
        el._clickOutside = (event) => {
            if (!(el === event.target || el.contains(event.target))) {
                binding.value();
            }
        };
        document.addEventListener('click', el._clickOutside);
    },
    unmounted(el) {
        document.removeEventListener('click', el._clickOutside);
    },
};
</script>
