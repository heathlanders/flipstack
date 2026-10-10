<script setup>
const props = defineProps({ kpis: { type: Object, required: true } });

const formatCurrency = (a) => new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(Number(a) || 0);
const pct = (v) => (v === null || v === undefined ? '—' : `${Number(v).toFixed(1)}%`);
const days = (v) => (v === null || v === undefined ? '—' : `${Number(v).toFixed(1)}d`);
</script>

<template>
    <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-4">
        <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Procurement KPIs</h3>
        <dl class="grid grid-cols-2 gap-4">
            <div>
                <dt class="text-xs text-gray-500 dark:text-gray-400 uppercase">POs</dt>
                <dd class="text-lg font-semibold text-gray-900 dark:text-white">{{ kpis.po_count }}</dd>
            </div>
            <div>
                <dt class="text-xs text-gray-500 dark:text-gray-400 uppercase">Total Spend</dt>
                <dd class="text-lg font-semibold text-gray-900 dark:text-white">{{ formatCurrency(kpis.total_spend) }}</dd>
            </div>
            <div>
                <dt class="text-xs text-gray-500 dark:text-gray-400 uppercase">Avg Lead Time</dt>
                <dd class="text-lg font-semibold text-gray-900 dark:text-white">{{ days(kpis.avg_lead_time_days) }}</dd>
            </div>
            <div>
                <dt class="text-xs text-gray-500 dark:text-gray-400 uppercase">On-Time Rate</dt>
                <dd class="text-lg font-semibold text-gray-900 dark:text-white">{{ pct(kpis.on_time_rate) }}</dd>
            </div>
            <div>
                <dt class="text-xs text-gray-500 dark:text-gray-400 uppercase">Receiving Accuracy</dt>
                <dd class="text-lg font-semibold text-gray-900 dark:text-white">{{ pct(kpis.receiving_accuracy) }}</dd>
            </div>
            <div>
                <dt class="text-xs text-gray-500 dark:text-gray-400 uppercase">Payouts</dt>
                <dd class="text-lg font-semibold text-gray-900 dark:text-white">{{ formatCurrency(kpis.total_payouts) }}</dd>
            </div>
        </dl>
        <div v-if="kpis.items_under_received || kpis.items_over_received" class="mt-3 text-xs text-gray-500 dark:text-gray-400 border-t border-gray-200 dark:border-gray-700 pt-3">
            Discrepancies: <span class="text-amber-600">{{ kpis.items_under_received }} short</span> /
            <span class="text-orange-600">{{ kpis.items_over_received }} over</span>
        </div>
    </div>
</template>
