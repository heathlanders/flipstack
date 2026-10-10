<script setup>
import { computed } from 'vue';
import { router, Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { route } from 'ziggy-js';
import AnalyticsSidebar from '@/Components/Analytics/AnalyticsSidebar.vue';
import ComparisonDatePicker from '@/Components/Analytics/ComparisonDatePicker.vue';
import KPICard from '@/Components/Analytics/KPICard.vue';
import { formatCurrency, formatNumber, formatPercent, formatDecimal } from '@/Composables/useAnalyticsFormatters';

const props = defineProps({
    overview: { type: Object, default: () => ({}) },
    userSummary: { type: Array, default: () => [] },
    commissionAttribution: { type: Array, default: () => [] },
    tierBreakdown: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
});

function applyFilters(filters) {
    router.get(route('analytics.hub.compensation'), filters, { preserveState: true, preserveScroll: true });
}

const totalAttribution = computed(() =>
    props.commissionAttribution.reduce((sum, r) => sum + (r.total_final_commission || 0), 0)
);
</script>

<template>
    <Head title="Compensation Analytics" />
    <AppLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200">Compensation Analytics</h2>
                <ComparisonDatePicker :filters="filters" @change="applyFilters" />
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex gap-6">
                    <AnalyticsSidebar currentPage="compensation" />

                    <div class="flex-1 space-y-6">
                        <!-- Overview KPIs -->
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                            <KPICard title="Total Payout" :value="formatCurrency(overview.total_payout)" icon="currency-dollar" color="emerald" />
                            <KPICard title="Paid Out" :value="formatCurrency(overview.paid_payout)" icon="trending-up" color="blue" />
                            <KPICard
                                title="Pending"
                                :value="formatCurrency(overview.pending_payout)"
                                icon="exclamation-triangle"
                                color="amber"
                                :alertLevel="overview.pending_payout > 0 ? 'warning' : 'normal'"
                            />
                            <KPICard title="Recipients" :value="formatNumber(overview.recipient_count)" icon="cube" color="indigo" />
                        </div>

                        <!-- Per-User Settlement Summary (KPI #21) -->
                        <div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden">
                            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
                                <h3 class="text-lg font-medium text-gray-900 dark:text-white">Settlement-Ready Per-User Reports</h3>
                                <span class="text-xs text-gray-500 dark:text-gray-400">{{ userSummary.length }} recipients</span>
                            </div>
                            <div v-if="userSummary.length" class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                                    <thead class="bg-gray-50 dark:bg-gray-700/50">
                                        <tr>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">User / Supplier</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Tier</th>
                                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Items</th>
                                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Gross</th>
                                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Commission</th>
                                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Bonuses</th>
                                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Fees</th>
                                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Net Payout</th>
                                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700/50">
                                        <tr v-for="row in userSummary" :key="`${row.user_id}-${row.supplier_id}`" class="hover:bg-gray-50 dark:hover:bg-gray-700/30">
                                            <td class="px-4 py-3">
                                                <Link
                                                    v-if="row.user_id"
                                                    :href="route('analytics.hub.users.show', row.user_id)"
                                                    class="text-sm font-medium text-blue-600 dark:text-blue-400 hover:underline"
                                                >
                                                    {{ row.user_name }}
                                                </Link>
                                                <span v-else class="text-sm text-gray-500 dark:text-gray-400">{{ row.user_name }}</span>
                                                <div class="text-xs text-gray-500 dark:text-gray-400">{{ row.supplier_name }}</div>
                                            </td>
                                            <td class="px-4 py-3">
                                                <span
                                                    v-if="row.tier_name"
                                                    class="px-2 py-0.5 text-xs font-medium rounded"
                                                    :style="row.tier_color ? { backgroundColor: row.tier_color + '33', color: row.tier_color } : {}"
                                                    :class="!row.tier_color ? 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300' : ''"
                                                >
                                                    {{ row.tier_name }}
                                                </span>
                                                <span v-else class="text-xs text-gray-400">—</span>
                                            </td>
                                            <td class="px-4 py-3 text-right text-gray-900 dark:text-white">{{ formatNumber(row.total_items_sold) }}</td>
                                            <td class="px-4 py-3 text-right text-gray-900 dark:text-white">{{ formatCurrency(row.gross_sales) }}</td>
                                            <td class="px-4 py-3 text-right text-gray-700 dark:text-gray-300">{{ formatCurrency(row.commission) }}</td>
                                            <td class="px-4 py-3 text-right text-emerald-600 dark:text-emerald-400">{{ formatCurrency(row.bonuses) }}</td>
                                            <td class="px-4 py-3 text-right text-red-600 dark:text-red-400">-{{ formatCurrency(row.fees) }}</td>
                                            <td class="px-4 py-3 text-right font-semibold text-gray-900 dark:text-white">{{ formatCurrency(row.net_payout) }}</td>
                                            <td class="px-4 py-3 text-center">
                                                <span v-if="row.paid_settlements" class="px-2 py-0.5 text-xs font-medium rounded bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300">
                                                    {{ row.paid_settlements }} paid
                                                </span>
                                                <span v-if="row.pending_settlements" class="px-2 py-0.5 text-xs font-medium rounded bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300 ml-1">
                                                    {{ row.pending_settlements }} pending
                                                </span>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <div v-else class="px-6 py-12 text-center text-sm text-gray-500 dark:text-gray-400">
                                No settlements in this period.
                            </div>
                        </div>

                        <!-- Commission Attribution + Tier Breakdown -->
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                            <!-- KPI #22 -->
                            <div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden">
                                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                                    <h3 class="text-lg font-medium text-gray-900 dark:text-white">Commission Attribution by Structure</h3>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ formatCurrency(totalAttribution) }} total final commission attributed</p>
                                </div>
                                <div v-if="commissionAttribution.length" class="divide-y divide-gray-100 dark:divide-gray-700/50">
                                    <div v-for="row in commissionAttribution" :key="row.structure_id || 'none'" class="px-6 py-3">
                                        <div class="flex items-start justify-between">
                                            <div class="min-w-0 flex-1">
                                                <div class="text-sm font-medium text-gray-900 dark:text-white truncate">{{ row.structure_name }}</div>
                                                <div class="text-xs text-gray-500 dark:text-gray-400">
                                                    <span v-if="row.structure_type" class="capitalize">{{ row.structure_type.replace('_', ' ') }}</span>
                                                    <span v-if="row.base_percentage !== null" class="ml-2">{{ formatDecimal(row.base_percentage) }}% base</span>
                                                    <span v-if="row.flat_fee_amount" class="ml-2">+ {{ formatCurrency(row.flat_fee_amount) }} flat</span>
                                                </div>
                                            </div>
                                            <div class="text-right ml-4 flex-shrink-0">
                                                <div class="text-sm font-semibold text-gray-900 dark:text-white">{{ formatCurrency(row.total_final_commission) }}</div>
                                                <div class="text-xs text-gray-500 dark:text-gray-400">{{ formatNumber(row.item_count) }} items</div>
                                            </div>
                                        </div>
                                        <div v-if="totalAttribution > 0" class="mt-2 h-1.5 bg-gray-100 dark:bg-gray-700 rounded-full overflow-hidden">
                                            <div
                                                class="h-1.5 bg-indigo-500"
                                                :style="{ width: ((row.total_final_commission / totalAttribution) * 100) + '%' }"
                                            ></div>
                                        </div>
                                    </div>
                                </div>
                                <div v-else class="px-6 py-12 text-center text-sm text-gray-500 dark:text-gray-400">
                                    No commission data in this period.
                                </div>
                            </div>

                            <!-- KPI #23 -->
                            <div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden">
                                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                                    <h3 class="text-lg font-medium text-gray-900 dark:text-white">Tier-Based Bonus Breakdown</h3>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">Bonus + tier adjustments by tier</p>
                                </div>
                                <div v-if="tierBreakdown.length" class="overflow-x-auto">
                                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                                        <thead class="bg-gray-50 dark:bg-gray-700/50">
                                            <tr>
                                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Tier</th>
                                                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Suppliers</th>
                                                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Adjustments</th>
                                                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Bonuses</th>
                                                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Total Comm</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700/50">
                                            <tr v-for="row in tierBreakdown" :key="row.tier_id || 'none'">
                                                <td class="px-4 py-2">
                                                    <div class="flex items-center gap-2">
                                                        <span
                                                            v-if="row.color_code"
                                                            class="w-2 h-2 rounded-full"
                                                            :style="{ backgroundColor: row.color_code }"
                                                        ></span>
                                                        <span class="text-sm font-medium text-gray-900 dark:text-white">{{ row.tier_name }}</span>
                                                    </div>
                                                    <div v-if="row.tier_commission_adjustment !== null" class="text-xs text-gray-500 dark:text-gray-400">
                                                        {{ row.tier_commission_adjustment > 0 ? '+' : '' }}{{ formatDecimal(row.tier_commission_adjustment) }}% tier
                                                    </div>
                                                </td>
                                                <td class="px-4 py-2 text-right text-gray-700 dark:text-gray-300">{{ formatNumber(row.supplier_count) }}</td>
                                                <td class="px-4 py-2 text-right text-gray-900 dark:text-white">{{ formatCurrency(row.total_tier_adjustments) }}</td>
                                                <td class="px-4 py-2 text-right text-emerald-600 dark:text-emerald-400">{{ formatCurrency(row.total_bonuses) }}</td>
                                                <td class="px-4 py-2 text-right font-semibold text-gray-900 dark:text-white">{{ formatCurrency(row.total_final_commission) }}</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                                <div v-else class="px-6 py-12 text-center text-sm text-gray-500 dark:text-gray-400">
                                    No tier data in this period.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
