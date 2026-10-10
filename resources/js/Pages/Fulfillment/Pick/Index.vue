<template>
    <AppLayout title="Pick List">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
            <!-- Header -->
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h1 class="text-xl font-bold text-gray-900">Pick List</h1>
                    <p class="text-sm text-gray-500">{{ stats.total_orders }} orders, {{ stats.total_items }} items to pick</p>
                </div>
                <div class="flex items-center gap-3">
                    <select v-model="selectedLocation" @change="filterByLocation" class="rounded-md border-gray-300 text-sm">
                        <option value="">All Locations</option>
                        <option v-for="loc in locations" :key="loc.id" :value="loc.id">{{ loc.name }} ({{ loc.code }})</option>
                    </select>
                    <button @click="printPickList" :disabled="!selectedLocation" class="px-4 py-2 bg-gray-800 text-white text-sm rounded-md hover:bg-gray-900 disabled:opacity-50">
                        Print Pick List
                    </button>
                </div>
            </div>

            <!-- Stats -->
            <div class="grid grid-cols-4 gap-4 mb-6">
                <div class="bg-white rounded-lg border p-3">
                    <p class="text-xs text-gray-500">Total Orders</p>
                    <p class="text-xl font-bold">{{ stats.total_orders }}</p>
                </div>
                <div class="bg-white rounded-lg border p-3">
                    <p class="text-xs text-gray-500">Total Items</p>
                    <p class="text-xl font-bold">{{ stats.total_items }}</p>
                </div>
                <div class="bg-white rounded-lg border p-3">
                    <p class="text-xs text-red-500">Urgent</p>
                    <p class="text-xl font-bold text-red-600">{{ stats.urgent }}</p>
                </div>
                <div class="bg-white rounded-lg border p-3">
                    <p class="text-xs text-amber-500">Overdue</p>
                    <p class="text-xl font-bold text-amber-600">{{ stats.overdue }}</p>
                </div>
            </div>

            <!-- Orders -->
            <div class="space-y-4">
                <div v-for="order in orders" :key="order.id" class="bg-white rounded-lg border" :class="{'ring-2 ring-red-300': order.priority === 'urgent', 'ring-2 ring-amber-300': order.priority === 'high'}">
                    <div class="px-4 py-3 border-b flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <span v-if="order.priority === 'urgent'" class="text-xs font-bold text-red-600 bg-red-50 px-2 py-0.5 rounded">[URGENT]</span>
                            <span v-else-if="order.priority === 'high'" class="text-xs font-bold text-amber-600 bg-amber-50 px-2 py-0.5 rounded">[HIGH]</span>
                            <Link :href="route('fulfillment.orders.show', order.id)" class="text-sm font-medium text-indigo-600 hover:text-indigo-900">{{ order.marketplace_order_id }}</Link>
                            <OrderStatusBadge :status="order.status" />
                            <span class="text-xs text-gray-500">{{ order.location?.name }}</span>
                        </div>
                        <div class="flex gap-2">
                            <button v-if="order.status === 'assigned'" @click="startPicking(order)" class="px-3 py-1.5 text-xs bg-indigo-600 text-white rounded hover:bg-indigo-700">Start Picking</button>
                            <button v-if="order.status === 'picking' && allItemsProcessed(order)" @click="completePicking(order)" class="px-3 py-1.5 text-xs bg-green-600 text-white rounded hover:bg-green-700">Complete Pick</button>
                        </div>
                    </div>
                    <div class="divide-y">
                        <div v-for="item in order.items" :key="item.id" class="px-4 py-2 flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <div class="w-6 text-center">
                                    <svg v-if="item.pick_status === 'picked'" class="h-5 w-5 text-green-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" /></svg>
                                    <svg v-else-if="item.pick_status === 'short'" class="h-5 w-5 text-red-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" /></svg>
                                    <span v-else class="inline-block h-4 w-4 rounded border-2 border-gray-300"></span>
                                </div>
                                <div>
                                    <p class="text-sm text-gray-900">{{ item.title }}</p>
                                    <p class="text-xs text-gray-500">SKU: {{ item.sku || 'N/A' }} | Qty: {{ item.quantity }}</p>
                                    <p v-if="item.pick_bin" class="text-xs font-mono text-indigo-600 mt-0.5">
                                        Bin: {{ item.pick_bin.zone ? `${item.pick_bin.zone}-${item.pick_bin.code}` : item.pick_bin.code }}
                                        <span v-if="item.pick_bin.aisle || item.pick_bin.rack || item.pick_bin.shelf" class="text-gray-400 ml-1">
                                            ({{ [item.pick_bin.aisle, item.pick_bin.rack, item.pick_bin.shelf].filter(Boolean).join(' / ') }})
                                        </span>
                                    </p>
                                    <p v-else class="text-xs text-gray-400 mt-0.5">Bin: Unassigned</p>
                                </div>
                            </div>
                            <div v-if="item.pick_status === 'pending' && order.status === 'picking'" class="flex gap-1">
                                <button @click="pickItem(item)" class="px-2 py-1 text-xs bg-green-100 text-green-700 rounded hover:bg-green-200">Pick</button>
                                <button @click="showShortModal(item)" class="px-2 py-1 text-xs bg-red-100 text-red-700 rounded hover:bg-red-200">Short</button>
                            </div>
                        </div>
                    </div>
                </div>

                <p v-if="orders.length === 0" class="text-center text-gray-500 py-8">No orders to pick at this location.</p>
            </div>
        </div>

        <!-- Short Modal -->
        <div v-if="shortingItem" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
            <div class="bg-white rounded-lg p-6 w-full max-w-md">
                <h3 class="text-lg font-medium mb-3">Short Item</h3>
                <p class="text-sm text-gray-600 mb-3">{{ shortingItem.title }}</p>
                <textarea v-model="shortReason" rows="3" class="w-full rounded-md border-gray-300 text-sm" placeholder="Reason (out of stock, damaged, etc.)" />
                <div class="flex justify-end gap-2 mt-4">
                    <button @click="shortingItem = null" class="px-4 py-2 text-sm text-gray-600">Cancel</button>
                    <button @click="confirmShort" :disabled="!shortReason" class="px-4 py-2 text-sm bg-red-600 text-white rounded-md disabled:opacity-50">Mark Short</button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import OrderStatusBadge from '@/Components/Fulfillment/OrderStatusBadge.vue';

const props = defineProps({
    orders: Array,
    locations: Array,
    stats: Object,
    filters: Object,
});

const selectedLocation = ref(props.filters?.location_id || '');
const shortingItem = ref(null);
const shortReason = ref('');

function filterByLocation() {
    router.get(route('fulfillment.pick.index'), selectedLocation.value ? { location_id: selectedLocation.value } : {}, { preserveState: true });
}

function startPicking(order) {
    router.post(route('fulfillment.pick.start', order.id));
}

function pickItem(item) {
    router.post(route('fulfillment.pick.item', item.id));
}

function showShortModal(item) {
    shortingItem.value = item;
    shortReason.value = '';
}

function confirmShort() {
    router.post(route('fulfillment.pick.short', shortingItem.value.id), { reason: shortReason.value }, { onSuccess: () => { shortingItem.value = null; } });
}

function completePicking(order) {
    router.post(route('fulfillment.pick.complete', order.id));
}

function allItemsProcessed(order) {
    return order.items.every(i => i.pick_status !== 'pending');
}

function printPickList() {
    router.post(route('fulfillment.pick.print'), { location_id: selectedLocation.value });
}
</script>
