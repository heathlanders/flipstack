<template>
    <AppLayout title="Fulfillment Orders">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
            <!-- Stats -->
            <div class="grid grid-cols-2 md:grid-cols-6 gap-4 mb-6">
                <div class="bg-white rounded-lg border p-4">
                    <p class="text-xs font-medium text-gray-500 uppercase">Pending</p>
                    <p class="text-2xl font-bold text-gray-900">{{ stats.pending }}</p>
                </div>
                <Link :href="route('fulfillment.pick.index')" class="bg-white rounded-lg border p-4 hover:border-indigo-300 transition-colors">
                    <p class="text-xs font-medium text-gray-500 uppercase">Picking</p>
                    <p class="text-2xl font-bold text-indigo-600">{{ stats.picking }}</p>
                </Link>
                <Link :href="route('fulfillment.pack.index')" class="bg-white rounded-lg border p-4 hover:border-purple-300 transition-colors">
                    <p class="text-xs font-medium text-gray-500 uppercase">Packing</p>
                    <p class="text-2xl font-bold text-purple-600">{{ stats.packing }}</p>
                </Link>
                <Link :href="route('fulfillment.ship.index')" class="bg-white rounded-lg border p-4 hover:border-sky-300 transition-colors">
                    <p class="text-xs font-medium text-gray-500 uppercase">Shipped</p>
                    <p class="text-2xl font-bold text-sky-600">{{ stats.shipped }}</p>
                </Link>
                <div class="bg-white rounded-lg border p-4">
                    <p class="text-xs font-medium text-amber-600 uppercase">Overdue</p>
                    <p class="text-2xl font-bold text-amber-600">{{ stats.overdue }}</p>
                </div>
                <div class="bg-white rounded-lg border p-4">
                    <p class="text-xs font-medium text-gray-500 uppercase">On Hold</p>
                    <p class="text-2xl font-bold text-gray-600">{{ stats.on_hold }}</p>
                </div>
            </div>

            <!-- Filters -->
            <div class="bg-white rounded-lg border p-4 mb-6">
                <div class="flex flex-wrap gap-3 items-end">
                    <div class="flex-1 min-w-[200px]">
                        <label class="block text-xs font-medium text-gray-600 mb-1">Search</label>
                        <input v-model="filterForm.search" type="text" placeholder="Order ID, buyer..." class="w-full rounded-md border-gray-300 text-sm" @keyup.enter="applyFilters" />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Status</label>
                        <select v-model="filterForm.status" class="rounded-md border-gray-300 text-sm" @change="applyFilters">
                            <option value="">All</option>
                            <option value="pending">Pending</option>
                            <option value="assigned">Assigned</option>
                            <option value="picking">Picking</option>
                            <option value="picked">Picked</option>
                            <option value="packing">Packing</option>
                            <option value="packed">Packed</option>
                            <option value="shipped">Shipped</option>
                            <option value="delivered">Delivered</option>
                            <option value="on_hold">On Hold</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Priority</label>
                        <select v-model="filterForm.priority" class="rounded-md border-gray-300 text-sm" @change="applyFilters">
                            <option value="">All</option>
                            <option value="urgent">Urgent</option>
                            <option value="high">High</option>
                            <option value="standard">Standard</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Location</label>
                        <select v-model="filterForm.location_id" class="rounded-md border-gray-300 text-sm" @change="applyFilters">
                            <option value="">All Locations</option>
                            <option v-for="loc in locations" :key="loc.id" :value="loc.id">{{ loc.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Marketplace</label>
                        <select v-model="filterForm.marketplace" class="rounded-md border-gray-300 text-sm" @change="applyFilters">
                            <option value="">All</option>
                            <option value="ebay">eBay</option>
                            <option value="shopify">Shopify</option>
                            <option value="amazon">Amazon</option>
                        </select>
                    </div>
                    <button @click="applyFilters" class="px-4 py-2 bg-indigo-600 text-white text-sm rounded-md hover:bg-indigo-700">Filter</button>
                    <button @click="resetFilters" class="px-4 py-2 text-gray-600 text-sm hover:text-gray-900">Reset</button>
                </div>
            </div>

            <!-- Bulk Actions -->
            <div v-if="selectedOrders.length > 0" class="bg-indigo-50 rounded-lg border border-indigo-200 p-3 mb-4 flex items-center justify-between">
                <span class="text-sm text-indigo-700">{{ selectedOrders.length }} order(s) selected</span>
                <div class="flex gap-2">
                    <select v-model="bulkLocation" class="rounded-md border-indigo-300 text-sm">
                        <option value="">Assign to...</option>
                        <option v-for="loc in locations" :key="loc.id" :value="loc.id">{{ loc.name }}</option>
                    </select>
                    <button @click="bulkAssign" :disabled="!bulkLocation" class="px-3 py-1.5 bg-indigo-600 text-white text-xs rounded hover:bg-indigo-700 disabled:opacity-50">Assign</button>
                    <select v-model="bulkPriority" class="rounded-md border-indigo-300 text-sm">
                        <option value="">Priority...</option>
                        <option value="urgent">Urgent</option>
                        <option value="high">High</option>
                        <option value="standard">Standard</option>
                    </select>
                    <button @click="bulkUpdatePriority" :disabled="!bulkPriority" class="px-3 py-1.5 bg-amber-600 text-white text-xs rounded hover:bg-amber-700 disabled:opacity-50">Set Priority</button>
                </div>
            </div>

            <!-- Orders Table -->
            <div class="bg-white rounded-lg border overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 w-8">
                                <input type="checkbox" @change="toggleAll" :checked="allSelected" class="rounded border-gray-300" />
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Order</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Buyer</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Priority</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Location</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Items</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Total</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Ship By</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <tr v-for="order in orders.data" :key="order.id" :class="{'bg-amber-50': isOverdue(order), 'bg-red-50': order.priority === 'urgent'}">
                            <td class="px-4 py-3">
                                <input type="checkbox" :value="order.id" v-model="selectedOrders" class="rounded border-gray-300" />
                            </td>
                            <td class="px-4 py-3">
                                <Link :href="route('fulfillment.orders.show', order.id)" class="text-sm font-medium text-indigo-600 hover:text-indigo-900">
                                    {{ truncateOrderId(order.marketplace_order_id) }}
                                </Link>
                                <p class="text-xs text-gray-500">{{ order.marketplace }}</p>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-700">{{ order.buyer_name }}</td>
                            <td class="px-4 py-3">
                                <Link v-if="['assigned', 'picking'].includes(order.status)" :href="route('fulfillment.pick.index')">
                                    <OrderStatusBadge :status="order.status" />
                                </Link>
                                <Link v-else-if="['picked', 'packing'].includes(order.status)" :href="route('fulfillment.pack.index')">
                                    <OrderStatusBadge :status="order.status" />
                                </Link>
                                <Link v-else-if="order.status === 'packed'" :href="route('fulfillment.ship.index')">
                                    <OrderStatusBadge :status="order.status" />
                                </Link>
                                <OrderStatusBadge v-else :status="order.status" />
                            </td>
                            <td class="px-4 py-3">
                                <span :class="['text-xs font-medium', priorityColor(order.priority)]">{{ order.priority }}</span>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-600">{{ order.location?.name || '-' }}</td>
                            <td class="px-4 py-3 text-sm text-gray-700">{{ order.items_count }}</td>
                            <td class="px-4 py-3 text-sm font-medium text-gray-900">${{ Number(order.total_amount).toFixed(2) }}</td>
                            <td class="px-4 py-3 text-xs" :class="isOverdue(order) ? 'text-red-600 font-medium' : 'text-gray-500'">
                                {{ order.ship_by_date ? formatDate(order.ship_by_date) : '-' }}
                            </td>
                            <td class="px-4 py-3 text-right">
                                <Link :href="route('fulfillment.orders.show', order.id)" class="text-indigo-600 hover:text-indigo-900 text-sm">View</Link>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div v-if="orders.links" class="mt-4 flex justify-center">
                <nav class="flex gap-1">
                    <template v-for="link in orders.links" :key="link.label">
                        <a v-if="link.url" :href="link.url" :class="['px-3 py-1.5 text-sm rounded', link.active ? 'bg-indigo-600 text-white' : 'text-gray-600 hover:bg-gray-100']" v-html="link.label" />
                        <span v-else class="px-3 py-1.5 text-sm text-gray-400" v-html="link.label" />
                    </template>
                </nav>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { ref, computed } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import OrderStatusBadge from '@/Components/Fulfillment/OrderStatusBadge.vue';

const props = defineProps({
    orders: Object,
    stats: Object,
    locations: Array,
    filters: Object,
});

const filterForm = ref({ ...props.filters });
const selectedOrders = ref([]);
const bulkLocation = ref('');
const bulkPriority = ref('');

const allSelected = computed(() => props.orders.data.length > 0 && selectedOrders.value.length === props.orders.data.length);

function toggleAll(e) {
    selectedOrders.value = e.target.checked ? props.orders.data.map(o => o.id) : [];
}

function applyFilters() {
    router.get(route('fulfillment.orders.index'), Object.fromEntries(Object.entries(filterForm.value).filter(([, v]) => v)), { preserveState: true });
}

function resetFilters() {
    filterForm.value = {};
    router.get(route('fulfillment.orders.index'));
}

function bulkAssign() {
    router.post(route('fulfillment.orders.bulk-assign'), { order_ids: selectedOrders.value, location_id: bulkLocation.value }, { onSuccess: () => { selectedOrders.value = []; bulkLocation.value = ''; } });
}

function bulkUpdatePriority() {
    router.post(route('fulfillment.orders.bulk-priority'), { order_ids: selectedOrders.value, priority: bulkPriority.value }, { onSuccess: () => { selectedOrders.value = []; bulkPriority.value = ''; } });
}

function truncateOrderId(id) {
    return id && id.length > 20 ? id.substring(0, 20) + '...' : id;
}

function isOverdue(order) {
    return order.ship_by_date && new Date(order.ship_by_date) < new Date() && !['shipped', 'in_transit', 'delivered', 'cancelled'].includes(order.status);
}

function priorityColor(p) {
    return { urgent: 'text-red-600', high: 'text-amber-600', standard: 'text-gray-500' }[p] || 'text-gray-500';
}

function formatDate(d) {
    return new Date(d).toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
}
</script>
