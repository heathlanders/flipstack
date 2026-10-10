<template>
    <AppLayout title="Returns">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
            <!-- Stats -->
            <div class="grid grid-cols-4 gap-4 mb-6">
                <div class="bg-white rounded-lg border p-3">
                    <p class="text-xs text-gray-500">Open Returns</p>
                    <p class="text-xl font-bold text-orange-600">{{ stats.open }}</p>
                </div>
                <div class="bg-white rounded-lg border p-3">
                    <p class="text-xs text-gray-500">Awaiting Receipt</p>
                    <p class="text-xl font-bold text-indigo-600">{{ stats.awaiting_receipt }}</p>
                </div>
                <div class="bg-white rounded-lg border p-3">
                    <p class="text-xs text-gray-500">Needs Inspection</p>
                    <p class="text-xl font-bold text-purple-600">{{ stats.awaiting_inspection }}</p>
                </div>
                <div class="bg-white rounded-lg border p-3">
                    <p class="text-xs text-gray-500">Refunded This Month</p>
                    <p class="text-xl font-bold text-green-600">${{ Number(stats.refunded_this_month || 0).toFixed(2) }}</p>
                </div>
            </div>

            <!-- Filters -->
            <div class="bg-white rounded-lg border p-4 mb-6 flex gap-3 items-end">
                <div class="flex-1">
                    <label class="block text-xs font-medium text-gray-600 mb-1">Search</label>
                    <input v-model="search" type="text" placeholder="RMA number, order ID..." class="w-full rounded-md border-gray-300 text-sm" @keyup.enter="applyFilter" />
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Status</label>
                    <select v-model="statusFilter" @change="applyFilter" class="rounded-md border-gray-300 text-sm">
                        <option value="">All</option>
                        <option value="requested">Requested</option>
                        <option value="approved">Approved</option>
                        <option value="shipped_back">Shipped Back</option>
                        <option value="received">Received</option>
                        <option value="inspected">Inspected</option>
                        <option value="refunded">Refunded</option>
                        <option value="denied">Denied</option>
                    </select>
                </div>
                <button @click="applyFilter" class="px-4 py-2 bg-indigo-600 text-white text-sm rounded-md hover:bg-indigo-700">Filter</button>
            </div>

            <!-- Returns Table -->
            <div class="bg-white rounded-lg border overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">RMA</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Order</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Reason</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Qty</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Refund</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Created</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <tr v-for="ret in returns.data" :key="ret.id">
                            <td class="px-4 py-3 text-sm font-mono font-medium text-gray-900">{{ ret.rma_number }}</td>
                            <td class="px-4 py-3">
                                <a v-if="ret.order" :href="route('fulfillment.orders.show', ret.order.id)" class="text-sm text-indigo-600 hover:text-indigo-800">
                                    {{ ret.order.marketplace_order_id?.substring(0, 15) }}
                                </a>
                                <p class="text-xs text-gray-500">{{ ret.order?.buyer_name }}</p>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-700">{{ ret.reason }}</td>
                            <td class="px-4 py-3"><OrderStatusBadge :status="ret.status" /></td>
                            <td class="px-4 py-3 text-sm text-gray-700">{{ ret.quantity }}</td>
                            <td class="px-4 py-3 text-sm font-medium">{{ ret.refund_amount ? '$' + Number(ret.refund_amount).toFixed(2) : '-' }}</td>
                            <td class="px-4 py-3 text-xs text-gray-500">{{ formatDate(ret.created_at) }}</td>
                            <td class="px-4 py-3 text-right">
                                <a :href="route('fulfillment.returns.show', ret.id)" class="text-sm text-indigo-600 hover:text-indigo-800">View</a>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <p v-if="returns.data.length === 0" class="text-center text-gray-500 py-8">No returns found.</p>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import OrderStatusBadge from '@/Components/Fulfillment/OrderStatusBadge.vue';

const props = defineProps({
    returns: Object,
    stats: Object,
    filters: Object,
});

const search = ref(props.filters?.search || '');
const statusFilter = ref(props.filters?.status || '');

function applyFilter() {
    const params = {};
    if (search.value) params.search = search.value;
    if (statusFilter.value) params.status = statusFilter.value;
    router.get(route('fulfillment.returns.index'), params, { preserveState: true });
}

function formatDate(d) {
    return new Date(d).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
}
</script>
