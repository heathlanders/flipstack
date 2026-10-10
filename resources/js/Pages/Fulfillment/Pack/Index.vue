<template>
    <AppLayout title="Pack Queue">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
            <!-- Stats -->
            <div class="grid grid-cols-4 gap-4 mb-6">
                <div class="bg-white rounded-lg border p-3">
                    <p class="text-xs text-gray-500">Awaiting Pack</p>
                    <p class="text-xl font-bold text-purple-600">{{ stats.awaiting_pack }}</p>
                </div>
                <div class="bg-white rounded-lg border p-3">
                    <p class="text-xs text-gray-500">Packing</p>
                    <p class="text-xl font-bold text-indigo-600">{{ stats.packing }}</p>
                </div>
                <div class="bg-white rounded-lg border p-3">
                    <p class="text-xs text-gray-500">Need Label</p>
                    <p class="text-xl font-bold text-amber-600">{{ stats.awaiting_label }}</p>
                </div>
                <div class="bg-white rounded-lg border p-3">
                    <p class="text-xs text-gray-500">Ready to Ship</p>
                    <p class="text-xl font-bold text-green-600">{{ stats.ready_to_ship }}</p>
                </div>
            </div>

            <!-- Orders -->
            <div class="space-y-4">
                <div v-for="order in orders.data" :key="order.id" class="bg-white rounded-lg border">
                    <div class="px-4 py-3 border-b flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <Link :href="route('fulfillment.orders.show', order.id)" class="text-sm font-medium text-indigo-600 hover:text-indigo-900">{{ order.marketplace_order_id }}</Link>
                            <OrderStatusBadge :status="order.status" />
                            <span class="text-xs text-gray-500">{{ order.items.length }} items</span>
                        </div>
                        <div class="flex gap-2">
                            <button v-if="order.status === 'picked'" @click="startPacking(order)" class="px-3 py-1.5 text-xs bg-purple-600 text-white rounded hover:bg-purple-700">Start Packing</button>
                            <button v-if="order.status === 'packing' || order.status === 'packed'" @click="selectOrder(order)" class="px-3 py-1.5 text-xs bg-teal-600 text-white rounded hover:bg-teal-700">Get Rates</button>
                            <button v-if="order.status === 'packing'" @click="printPackingSlip(order)" class="px-3 py-1.5 text-xs border border-gray-300 rounded hover:bg-gray-50">Print Slip</button>
                        </div>
                    </div>

                    <!-- Expanded rate shopping panel -->
                    <div v-if="activeOrder?.id === order.id" class="px-4 py-4 bg-gray-50 border-t">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <PackageDimensions :initial-dimensions="getOrderDimensions(order)" @update="updateDimensions" />
                            <div>
                                <RateSelector
                                    :rates="rates"
                                    :loading="loadingRates"
                                    @select="purchaseLabel"
                                />
                            </div>
                        </div>
                        <div class="mt-3 flex justify-between items-center">
                            <button @click="fetchRates(order)" :disabled="loadingRates" class="px-4 py-2 bg-indigo-600 text-white text-sm rounded-md hover:bg-indigo-700 disabled:opacity-50">
                                {{ loadingRates ? 'Fetching...' : 'Get Rates' }}
                            </button>
                            <button @click="activeOrder = null" class="text-sm text-gray-500 hover:text-gray-700">Close</button>
                        </div>
                    </div>

                    <!-- Items summary -->
                    <div class="px-4 py-2 text-xs text-gray-500 border-t bg-gray-50/50">
                        <span v-for="(item, idx) in order.items.slice(0, 3)" :key="item.id">
                            {{ item.product?.sku || item.sku || 'item' }} x{{ item.quantity }}{{ idx < Math.min(order.items.length, 3) - 1 ? ', ' : '' }}
                        </span>
                        <span v-if="order.items.length > 3"> +{{ order.items.length - 3 }} more</span>
                    </div>
                </div>

                <p v-if="orders.data.length === 0" class="text-center text-gray-500 py-8">No orders awaiting packing.</p>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import OrderStatusBadge from '@/Components/Fulfillment/OrderStatusBadge.vue';
import PackageDimensions from '@/Components/Fulfillment/PackageDimensions.vue';
import RateSelector from '@/Components/Fulfillment/RateSelector.vue';

const props = defineProps({
    orders: Object,
    stats: Object,
});

const activeOrder = ref(null);
const rates = ref([]);
const loadingRates = ref(false);
const dimensions = ref({});

function selectOrder(order) {
    activeOrder.value = order;
    rates.value = [];
}

function startPacking(order) {
    router.post(route('fulfillment.pack.start', order.id));
}

function getOrderDimensions(order) {
    const shipment = order.shipments?.[0];
    return {
        weight_oz: shipment?.package_weight_oz || order.location?.default_weight_oz || null,
        length: shipment?.package_length || order.location?.default_length || null,
        width: shipment?.package_width || order.location?.default_width || null,
        height: shipment?.package_height || order.location?.default_height || null,
    };
}

function updateDimensions(dims) {
    dimensions.value = dims;
}

function fetchRates(order) {
    loadingRates.value = true;
    router.get(route('fulfillment.pack.rates', order.id), dimensions.value, {
        preserveState: true,
        onSuccess: (page) => {
            rates.value = page.props.flash?.rates || [];
            loadingRates.value = false;
        },
        onError: () => { loadingRates.value = false; },
    });
}

function purchaseLabel(rateId) {
    if (!activeOrder.value) return;
    router.post(route('fulfillment.pack.purchase-label', activeOrder.value.id), {
        rate_id: rateId,
        ...dimensions.value,
    }, { onSuccess: () => { activeOrder.value = null; rates.value = []; } });
}

function printPackingSlip(order) {
    router.post(route('fulfillment.pack.packing-slip', order.id));
}
</script>
