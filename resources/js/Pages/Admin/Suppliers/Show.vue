<template>
    <AppLayout title="Supplier Details">
        <template #header>
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-4">
                    <Link :href="route('admin.suppliers.index')" class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                        </svg>
                    </Link>
                    <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                        {{ supplier.company_name || supplier.name }}
                    </h2>
                    <span :class="statusBadgeClass" class="px-2 py-1 rounded-full text-xs font-medium">
                        {{ supplier.status }}
                    </span>
                </div>
                <div class="flex items-center space-x-3">
                    <button @click="showEditModal = true" class="px-4 py-2 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600">
                        Edit Supplier
                    </button>
                    <div class="relative" ref="actionsDropdown">
                        <button @click="showActionsMenu = !showActionsMenu" class="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-700">
                            Actions
                            <svg class="w-4 h-4 inline-block ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </button>
                        <div v-if="showActionsMenu" class="absolute right-0 mt-2 w-56 bg-white dark:bg-gray-800 rounded-lg shadow-lg border border-gray-200 dark:border-gray-700 z-10">
                            <div class="py-1">
                                <button v-if="canManageAdminConfig" @click="generateSettlement" class="w-full text-left px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700">
                                    Generate Settlement
                                </button>
                                <button v-if="canManageAdminConfig" @click="recalculatePerformance" class="w-full text-left px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700">
                                    Recalculate Performance
                                </button>
                                <button @click="sendNotification" class="w-full text-left px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700">
                                    Send Notification
                                </button>
                                <hr class="my-1 border-gray-200 dark:border-gray-700">
                                <button @click="exportData" class="w-full text-left px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700">
                                    Export Data
                                </button>
                                <button @click="viewActivityLog" class="w-full text-left px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700">
                                    View Activity Log
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <!-- Quick Stats -->
                <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-6 gap-4 mb-6">
                    <StatCard title="Total Sales" :value="formatCurrency(stats.total_sales)" icon="currency" :trend="stats.sales_trend" />
                    <StatCard title="Performance Score" :value="formatPercent(stats.performance_score)" icon="chart" :trend="stats.performance_trend" />
                    <StatCard title="Catalog Products" :value="stats.catalog_products" icon="list" />
                    <StatCard
                        title="Marketplace Listings"
                        :value="stats.marketplace_listings"
                        icon="list"
                    />
                    <StatCard title="Open PO Value" :value="formatCurrency(stats.open_po_value)" icon="currency" />
                    <StatCard title="Pending Payout" :value="formatCurrency(stats.pending_payout)" icon="wallet" />
                </div>
                <p v-if="marketplaceBreakdownLabel" class="text-xs text-gray-500 dark:text-gray-400 -mt-3 mb-6">
                    Marketplace listings breakdown: {{ marketplaceBreakdownLabel }}
                </p>

                <!-- Main Content Tabs -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700">
                    <!-- Tab Navigation -->
                    <div class="border-b border-gray-200 dark:border-gray-700">
                        <nav class="flex space-x-8 px-6" aria-label="Tabs">
                            <button 
                                v-for="tab in tabs" 
                                :key="tab.id"
                                @click="activeTab = tab.id"
                                :class="[
                                    activeTab === tab.id 
                                        ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400' 
                                        : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 hover:border-gray-300',
                                    'whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm'
                                ]"
                            >
                                {{ tab.name }}
                                <span v-if="tab.count" class="ml-2 px-2 py-0.5 rounded-full text-xs bg-gray-100 dark:bg-gray-700">
                                    {{ tab.count }}
                                </span>
                            </button>
                        </nav>
                    </div>

                    <!-- Tab Content -->
                    <div class="p-6">
                        <!-- Overview Tab -->
                        <div v-if="activeTab === 'overview'" class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                            <!-- Supplier Information -->
                            <div class="lg:col-span-2 space-y-6">
                                <ProcurementKpiCard :kpis="procurementKpis" />
                                <ListerCrossReference :data="listerCrossReference" />
                                <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-4">
                                    <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Business Information</h3>
                                    <dl class="grid grid-cols-2 gap-4">
                                        <div>
                                            <dt class="text-sm text-gray-500 dark:text-gray-400">Business Name</dt>
                                            <dd class="text-sm font-medium text-gray-900 dark:text-white">{{ supplier.company_name || supplier.name || 'N/A' }}</dd>
                                        </div>
                                        <div>
                                            <dt class="text-sm text-gray-500 dark:text-gray-400">Contact Name</dt>
                                            <dd class="text-sm font-medium text-gray-900 dark:text-white">{{ supplier.name }}</dd>
                                        </div>
                                        <div>
                                            <dt class="text-sm text-gray-500 dark:text-gray-400">Email</dt>
                                            <dd class="text-sm font-medium text-gray-900 dark:text-white">{{ supplier.email }}</dd>
                                        </div>
                                        <div>
                                            <dt class="text-sm text-gray-500 dark:text-gray-400">Phone</dt>
                                            <dd class="text-sm font-medium text-gray-900 dark:text-white">{{ supplier.phone || 'N/A' }}</dd>
                                        </div>
                                        <div>
                                            <dt class="text-sm text-gray-500 dark:text-gray-400">Joined</dt>
                                            <dd class="text-sm font-medium text-gray-900 dark:text-white">{{ formatDate(supplier.created_at) }}</dd>
                                        </div>
                                        <div>
                                            <dt class="text-sm text-gray-500 dark:text-gray-400">Last Active</dt>
                                            <dd class="text-sm font-medium text-gray-900 dark:text-white">{{ formatDate(supplier.last_activity) }}</dd>
                                        </div>
                                    </dl>
                                </div>

                                <!-- Current Configuration -->
                                <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-4">
                                    <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Current Configuration</h3>
                                    <dl class="grid grid-cols-2 gap-4">
                                        <div>
                                            <dt class="text-sm text-gray-500 dark:text-gray-400">Tier</dt>
                                            <dd class="flex items-center space-x-2">
                                                <span :class="tierBadgeClass" class="px-2 py-1 rounded-full text-xs font-medium">{{ supplier.tier?.name }}</span>
                                                <button v-if="canManageAdminConfig" @click="showChangeTierModal = true" class="text-indigo-600 hover:text-indigo-800 text-xs">Change</button>
                                            </dd>
                                        </div>
                                        <div>
                                            <dt class="text-sm text-gray-500 dark:text-gray-400">Commission Structure</dt>
                                            <dd class="flex items-center space-x-2">
                                                <span class="text-sm font-medium text-gray-900 dark:text-white">{{ supplier.commission?.structure_name }}</span>
                                                <button v-if="canManageAdminConfig" @click="showChangeCommissionModal = true" class="text-indigo-600 hover:text-indigo-800 text-xs">Change</button>
                                            </dd>
                                        </div>
                                        <div>
                                            <dt class="text-sm text-gray-500 dark:text-gray-400">Effective Rate</dt>
                                            <dd class="text-sm font-medium text-gray-900 dark:text-white">{{ formatPercent(supplier.commission?.custom_rate || supplier.commission?.base_percentage) }}</dd>
                                        </div>
                                        <div>
                                            <dt class="text-sm text-gray-500 dark:text-gray-400">Settlement Frequency</dt>
                                            <dd class="flex items-center space-x-2">
                                                <span class="text-sm font-medium text-gray-900 dark:text-white capitalize">{{ supplier.settlement?.schedule }}</span>
                                                <button v-if="canManageAdminConfig" @click="showChangeSettlementModal = true" class="text-indigo-600 hover:text-indigo-800 text-xs">Change</button>
                                            </dd>
                                        </div>
                                        <div>
                                            <dt class="text-sm text-gray-500 dark:text-gray-400">Next Settlement</dt>
                                            <dd class="text-sm font-medium text-gray-900 dark:text-white">{{ formatDate(supplier.settlement?.next_date) }}</dd>
                                        </div>
                                        <div>
                                            <dt class="text-sm text-gray-500 dark:text-gray-400">Payment Method</dt>
                                            <dd class="text-sm font-medium text-gray-900 dark:text-white">{{ supplier.portal?.payment_method || 'Not configured' }}</dd>
                                        </div>
                                    </dl>
                                </div>
                            </div>

                            <!-- Sidebar -->
                            <div class="space-y-6">
                                <!-- Performance Summary -->
                                <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-4">
                                    <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Performance Summary</h3>
                                    <div class="space-y-4">
                                        <div>
                                            <div class="flex justify-between text-sm mb-1">
                                                <span class="text-gray-500 dark:text-gray-400">Overall Score</span>
                                                <span class="font-medium text-gray-900 dark:text-white">{{ formatPercent(supplier.performance?.score) }}</span>
                                            </div>
                                            <div class="h-2 bg-gray-200 dark:bg-gray-600 rounded-full overflow-hidden">
                                                <div :style="{ width: (supplier.performance?.score || 0) + '%' }" class="h-full bg-indigo-500 rounded-full"></div>
                                            </div>
                                        </div>
                                        <div v-for="metric in performanceMetrics" :key="metric.name">
                                            <div class="flex justify-between text-sm mb-1">
                                                <span class="text-gray-500 dark:text-gray-400">{{ metric.label }}</span>
                                                <span class="font-medium text-gray-900 dark:text-white">{{ formatPercent(metric.value) }}</span>
                                            </div>
                                            <div class="h-1.5 bg-gray-200 dark:bg-gray-600 rounded-full overflow-hidden">
                                                <div :style="{ width: metric.value + '%' }" :class="metric.color" class="h-full rounded-full"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Recent Activity -->
                                <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-4">
                                    <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Recent Activity</h3>
                                    <ul class="space-y-3">
                                        <li v-for="activity in recentActivity" :key="activity.id" class="flex items-start space-x-3">
                                            <span :class="activityIconClass(activity.type)" class="flex-shrink-0 w-2 h-2 rounded-full mt-1.5"></span>
                                            <div class="flex-1 min-w-0">
                                                <p class="text-sm text-gray-900 dark:text-white">{{ activity.description }}</p>
                                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ formatRelativeTime(activity.created_at) }}</p>
                                            </div>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>

                        <!-- Settlements Tab -->
                        <div v-if="activeTab === 'settlements'">
                            <SettlementsTable :settlements="settlements || []" @view="viewSettlement" />
                        </div>

                        <!-- Products Tab -->
                        <div v-if="activeTab === 'products'">
                            <ProductsTable :supplier-id="supplier.id" @view="viewProduct" />
                        </div>

                        <!-- Purchase Orders Tab -->
                        <div v-if="activeTab === 'purchase_orders'">
                            <PurchaseOrdersTab :supplier-id="supplier.id" />
                        </div>

                        <!-- Inventory Batches Tab -->
                        <div v-if="activeTab === 'batches'">
                            <InventoryBatchesTab :supplier-id="supplier.id" />
                        </div>

                        <!-- Orders Tab -->
                        <div v-if="activeTab === 'orders'">
                            <OrdersTab :supplier-id="supplier.id" />
                        </div>

                        <!-- Submissions Tab -->
                        <div v-if="activeTab === 'submissions'">
                            <SubmissionsTab :supplier-id="supplier.id" />
                        </div>

                        <!-- Performance Tab -->
                        <div v-if="activeTab === 'performance'">
                            <PerformanceHistory :metrics="performanceHistory || []" :tier-history="tierHistory || []" />
                        </div>

                        <!-- Documents Tab -->
                        <div v-if="activeTab === 'documents'">
                            <SupplierDocumentsPanel :documents="supplier.documents || []" :supplier-id="supplier.id" />
                        </div>

                        <!-- Notes Tab -->
                        <div v-if="activeTab === 'notes'">
                            <NotesManager :notes="supplier.notes || []" :supplier-id="supplier.id" @add="addNote" @delete="deleteNote" />
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Change Tier Modal -->
        <Modal :show="showChangeTierModal" @close="showChangeTierModal = false">
            <div class="p-6">
                <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Change Supplier Tier</h3>
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Current Tier</label>
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ supplier.tier?.name }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">New Tier</label>
                        <select v-model="tierForm.tier_id" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700">
                            <option v-for="tier in availableTiers" :key="tier.id" :value="tier.id">
                                {{ tier.name }} ({{ formatPercent(tier.min_score) }} - {{ formatPercent(tier.max_score) }})
                            </option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Reason</label>
                        <textarea v-model="tierForm.reason" rows="3" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700" placeholder="Reason for tier change..."></textarea>
                    </div>
                    <div class="flex items-center">
                        <input type="checkbox" v-model="tierForm.notify_supplier" id="notify-tier" class="rounded border-gray-300 text-indigo-600">
                        <label for="notify-tier" class="ml-2 text-sm text-gray-700 dark:text-gray-300">Notify supplier of change</label>
                    </div>
                </div>
                <div class="flex justify-end space-x-3 mt-6">
                    <button @click="showChangeTierModal = false" class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-sm">Cancel</button>
                    <button @click="updateTier" class="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm">Update Tier</button>
                </div>
            </div>
        </Modal>

        <!-- Change Commission Modal -->
        <Modal :show="showChangeCommissionModal" @close="showChangeCommissionModal = false">
            <div class="p-6">
                <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Change Commission Structure</h3>
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Current Structure</label>
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ supplier.commission_structure?.name }} ({{ formatPercent(supplier.commission_structure?.base_rate) }})</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">New Structure</label>
                        <select v-model="commissionForm.commission_structure_id" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700">
                            <option v-for="structure in availableCommissionStructures" :key="structure.id" :value="structure.id">
                                {{ structure.name }} ({{ formatPercent(structure.base_rate) }})
                            </option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Custom Rate Override (optional)</label>
                        <div class="flex items-center space-x-2">
                            <input type="number" v-model="commissionForm.custom_rate" step="0.1" min="0" max="100" class="w-24 rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700" placeholder="0.0">
                            <span class="text-sm text-gray-500 dark:text-gray-400">%</span>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Effective Date</label>
                        <input type="date" v-model="commissionForm.effective_date" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700">
                    </div>
                </div>
                <div class="flex justify-end space-x-3 mt-6">
                    <button @click="showChangeCommissionModal = false" class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-sm">Cancel</button>
                    <button @click="updateCommission" class="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm">Update Commission</button>
                </div>
            </div>
        </Modal>

        <!-- Change Settlement Frequency Modal -->
        <Modal :show="showChangeSettlementModal" @close="showChangeSettlementModal = false">
            <div class="p-6">
                <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Change Settlement Frequency</h3>
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Current Frequency</label>
                        <p class="text-sm text-gray-500 dark:text-gray-400 capitalize">{{ supplier.settlement_frequency }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">New Frequency</label>
                        <select v-model="settlementForm.frequency" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700">
                            <option value="per_sale">Per Sale</option>
                            <option value="weekly">Weekly</option>
                            <option value="bi_weekly">Bi-Weekly</option>
                            <option value="monthly">Monthly</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Effective From</label>
                        <input type="date" v-model="settlementForm.effective_date" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700">
                    </div>
                </div>
                <div class="flex justify-end space-x-3 mt-6">
                    <button @click="showChangeSettlementModal = false" class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-sm">Cancel</button>
                    <button @click="updateSettlementFrequency" class="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm">Update</button>
                </div>
            </div>
        </Modal>
    </AppLayout>
</template>

<script setup>
import { ref, computed } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Modal from '@/Components/Modal.vue';
import StatCard from '@/Components/StatCard.vue';
import SettlementsTable from '@/Components/Admin/SettlementsTable.vue';
import ProductsTable from '@/Components/Admin/ProductsTable.vue';
import PerformanceHistory from '@/Components/Admin/PerformanceHistory.vue';
import SupplierDocumentsPanel from '@/Components/Admin/SupplierDocumentsPanel.vue';
import NotesManager from '@/Components/Admin/NotesManager.vue';
import PurchaseOrdersTab from '@/Components/Admin/PurchaseOrdersTab.vue';
import InventoryBatchesTab from '@/Components/Admin/InventoryBatchesTab.vue';
import OrdersTab from '@/Components/Admin/OrdersTab.vue';
import SubmissionsTab from '@/Components/Admin/SubmissionsTab.vue';
import ProcurementKpiCard from '@/Components/Admin/ProcurementKpiCard.vue';
import ListerCrossReference from '@/Components/Admin/ListerCrossReference.vue';

const props = defineProps({
    supplier: Object,
    stats: Object,
    counts: { type: Object, default: () => ({}) },
    procurementKpis: { type: Object, default: () => ({}) },
    listerCrossReference: { type: Object, default: () => ({ known_lister_codes: [], supplier_lister_codes: [], org_unlinked_lister_products: 0 }) },
    settlements: { type: Array, default: () => [] },
    performanceHistory: { type: Array, default: () => [] },
    tierHistory: { type: Array, default: () => [] },
    availableTiers: Array,
    availableCommissionStructures: Array,
    recentActivity: { type: Array, default: () => [] },
    canManageAdminConfig: { type: Boolean, default: false },
});

const marketplaceBreakdownLabel = (() => {
    const b = props.stats?.marketplace_breakdown;
    if (!b) return null;
    return `eBay ${b.ebay || 0} / Shopify ${b.shopify || 0} / Amazon ${b.amazon || 0}`;
})();

const activeTab = ref('overview');
const showActionsMenu = ref(false);
const showEditModal = ref(false);
const showChangeTierModal = ref(false);
const showChangeCommissionModal = ref(false);
const showChangeSettlementModal = ref(false);

const tabs = computed(() => [
    { id: 'overview', name: 'Overview' },
    { id: 'products', name: 'Products', count: props.counts.products || 0 },
    { id: 'purchase_orders', name: 'Purchase Orders', count: props.counts.purchase_orders || 0 },
    { id: 'batches', name: 'Batches', count: props.counts.batches || 0 },
    { id: 'orders', name: 'Orders', count: props.counts.orders || 0 },
    { id: 'submissions', name: 'Submissions', count: props.counts.submissions || 0 },
    { id: 'settlements', name: 'Settlements', count: props.counts.settlements || 0 },
    { id: 'performance', name: 'Performance', count: props.counts.performance || 0 },
    { id: 'documents', name: 'Documents', count: (props.supplier.documents || []).filter(d => d.uploaded).length },
    { id: 'notes', name: 'Notes', count: (props.supplier.notes || []).length },
]);

const tierForm = ref({
    tier_id: props.supplier.tier_id,
    reason: '',
    notify_supplier: true,
});

const commissionForm = ref({
    commission_structure_id: props.supplier.commission_structure_id,
    custom_rate: null,
    effective_date: new Date().toISOString().split('T')[0],
});

const settlementForm = ref({
    frequency: props.supplier.settlement_frequency,
    effective_date: new Date().toISOString().split('T')[0],
});

const statusBadgeClass = computed(() => {
    const classes = {
        active: 'bg-green-100 text-green-800 dark:bg-green-900/50 dark:text-green-300',
        pending: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/50 dark:text-yellow-300',
        suspended: 'bg-red-100 text-red-800 dark:bg-red-900/50 dark:text-red-300',
        inactive: 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300',
    };
    return classes[props.supplier.status] || classes.inactive;
});

const tierBadgeClass = computed(() => {
    const tierColors = {
        platinum: 'bg-slate-200 text-slate-800',
        gold: 'bg-yellow-100 text-yellow-800',
        silver: 'bg-gray-200 text-gray-800',
        bronze: 'bg-orange-100 text-orange-800',
        probation: 'bg-red-100 text-red-800',
    };
    const tierKey = props.supplier.tier?.slug || 'silver';
    return tierColors[tierKey] || 'bg-gray-100 text-gray-800';
});

const performanceMetrics = computed(() => [
    { name: 'sales', label: 'Sales Performance', value: props.supplier.sales_score || 0, color: 'bg-blue-500' },
    { name: 'quality', label: 'Quality Score', value: props.supplier.quality_score || 0, color: 'bg-green-500' },
    { name: 'fulfillment', label: 'Fulfillment Rate', value: props.supplier.fulfillment_score || 0, color: 'bg-purple-500' },
    { name: 'returns', label: 'Return Rate (Inverted)', value: 100 - (props.supplier.return_rate || 0), color: 'bg-orange-500' },
]);

function formatCurrency(value) {
    return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(value || 0);
}

function formatPercent(value) {
    if (value === null || value === undefined) return '0%';
    return `${parseFloat(value).toFixed(1)}%`;
}

function formatDate(date) {
    if (!date) return 'N/A';
    return new Date(date).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
}

function formatRelativeTime(date) {
    if (!date) return '';
    const now = new Date();
    const then = new Date(date);
    const diff = Math.floor((now - then) / 1000);
    
    if (diff < 60) return 'Just now';
    if (diff < 3600) return `${Math.floor(diff / 60)}m ago`;
    if (diff < 86400) return `${Math.floor(diff / 3600)}h ago`;
    if (diff < 604800) return `${Math.floor(diff / 86400)}d ago`;
    return formatDate(date);
}

function activityIconClass(type) {
    const classes = {
        sale: 'bg-green-500',
        settlement: 'bg-blue-500',
        tier_change: 'bg-purple-500',
        status_change: 'bg-yellow-500',
        submission: 'bg-gray-500',
    };
    return classes[type] || 'bg-gray-400';
}

function generateSettlement() {
    showActionsMenu.value = false;
    router.post(route('admin.suppliers.generate-settlement', props.supplier.id));
}

function recalculatePerformance() {
    showActionsMenu.value = false;
    router.post(route('admin.suppliers.recalculate-performance', props.supplier.id));
}

function sendNotification() {
    showActionsMenu.value = false;
    // Open notification modal
}

function exportData() {
    showActionsMenu.value = false;
    window.location.href = route('admin.suppliers.export', { supplier_id: props.supplier.id });
}

function viewActivityLog() {
    showActionsMenu.value = false;
    router.get(route('admin.suppliers.activity-log', props.supplier.id));
}

function updateTier() {
    router.patch(route('admin.suppliers.update-tier', props.supplier.id), tierForm.value, {
        onSuccess: () => {
            showChangeTierModal.value = false;
        },
    });
}

function updateCommission() {
    router.patch(route('admin.suppliers.update-commission', props.supplier.id), commissionForm.value, {
        onSuccess: () => {
            showChangeCommissionModal.value = false;
        },
    });
}

function updateSettlementFrequency() {
    router.patch(route('admin.suppliers.update-settlement-schedule', props.supplier.id), settlementForm.value, {
        onSuccess: () => {
            showChangeSettlementModal.value = false;
        },
    });
}

function viewSettlement(settlement) {
    router.get(route('admin.settlements.show', settlement.id));
}

function viewProduct(product) {
    router.get(route('admin.products.show', product.id));
}

function addNote(content) {
    router.post(route('admin.suppliers.notes.store', props.supplier.id), { content }, {
        preserveScroll: true,
    });
}

function deleteNote(noteId) {
    router.delete(route('admin.suppliers.notes.destroy', { supplier: props.supplier.id, note: noteId }), {
        preserveScroll: true,
    });
}
</script>
