<template>
    <AppLayout :title="'Order ' + order.marketplace_order_id">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
            <!-- Header -->
            <div class="flex items-center justify-between mb-6">
                <div>
                    <Link :href="route('fulfillment.orders.index')" class="text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 flex items-center gap-1 mb-1">
                        <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>
                        Back to Orders
                    </Link>
                    <h1 class="text-xl font-bold text-gray-900">
                        Order
                        <a v-if="marketplaceOrderUrl" :href="marketplaceOrderUrl" target="_blank" rel="noopener" class="text-indigo-600 hover:text-indigo-800">{{ order.marketplace_order_id }}</a>
                        <span v-else>{{ order.marketplace_order_id }}</span>
                    </h1>
                    <p class="text-sm text-gray-500 mt-0.5">
                        <Link :href="route('fulfillment.orders.index', { marketplace: order.marketplace })" class="capitalize hover:text-gray-700 hover:underline">{{ order.marketplace }}</Link>
                        | Created {{ formatDateTime(order.created_at) }}
                    </p>
                </div>
                <div class="flex items-center gap-3">
                    <OrderStatusBadge :status="order.status" />
                    <div class="flex gap-2">
                        <Link v-if="['assigned', 'picking'].includes(order.status)"
                            :href="route('fulfillment.pick.index')"
                            class="px-3 py-1.5 text-sm bg-indigo-600 text-white rounded-md hover:bg-indigo-700">
                            Go to Pick Queue
                        </Link>
                        <Link v-else-if="['picked', 'packing'].includes(order.status)"
                            :href="route('fulfillment.pack.index')"
                            class="px-3 py-1.5 text-sm bg-purple-600 text-white rounded-md hover:bg-purple-700">
                            Go to Pack Queue
                        </Link>
                        <Link v-else-if="order.status === 'packed'"
                            :href="route('fulfillment.ship.index')"
                            class="px-3 py-1.5 text-sm bg-sky-600 text-white rounded-md hover:bg-sky-700">
                            Go to Ship Queue
                        </Link>
                        <button v-if="!['shipped','delivered','cancelled'].includes(order.status)" @click="openManualShip" class="px-3 py-1.5 text-sm bg-emerald-600 text-white rounded-md hover:bg-emerald-700">Enter Tracking</button>
                        <button v-if="order.status !== 'on_hold' && order.status !== 'cancelled'" @click="showHoldModal = true" class="px-3 py-1.5 text-sm border rounded-md hover:bg-gray-50">Hold</button>
                        <button v-if="order.status === 'on_hold'" @click="releaseOrder" class="px-3 py-1.5 text-sm bg-green-600 text-white rounded-md hover:bg-green-700">Release</button>
                        <button v-if="!['shipped','delivered','cancelled'].includes(order.status)" @click="showCancelModal = true" class="px-3 py-1.5 text-sm text-red-600 border border-red-300 rounded-md hover:bg-red-50">Cancel</button>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Main Content -->
                <div class="lg:col-span-2 space-y-6">
                    <!-- Order Items -->
                    <div class="bg-white rounded-lg border">
                        <div class="px-4 py-3 border-b">
                            <h3 class="text-sm font-medium text-gray-900">Items ({{ order.items.length }})</h3>
                        </div>
                        <div class="divide-y">
                            <div v-for="item in order.items" :key="item.id" class="px-4 py-3 flex items-center justify-between">
                                <div class="flex items-center space-x-3">
                                    <component :is="item.product_id ? Link : 'div'" v-bind="item.product_id ? { href: route('inventory.products.show', item.product_id) } : {}" class="shrink-0">
                                        <img v-if="item.image_url" :src="item.image_url" class="h-10 w-10 rounded object-cover" />
                                        <div v-else class="h-10 w-10 rounded bg-gray-100 flex items-center justify-center">
                                            <svg class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" /></svg>
                                        </div>
                                    </component>
                                    <div>
                                        <Link v-if="item.product_id" :href="route('inventory.products.show', item.product_id)" class="text-sm font-medium text-gray-900 hover:text-indigo-700 hover:underline">{{ item.title }}</Link>
                                        <p v-else class="text-sm font-medium text-gray-900">{{ item.title }}</p>
                                        <p class="text-xs text-gray-500">
                                            <Link v-if="item.product_id && item.sku" :href="route('inventory.products.show', item.product_id)" class="hover:text-indigo-700 hover:underline">SKU: {{ item.sku }}</Link>
                                            <span v-else-if="item.sku">SKU: {{ item.sku }}</span>
                                            <span v-if="item.condition" class="ml-2">{{ item.condition }}</span>
                                        </p>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <p class="text-sm font-medium text-gray-900">x{{ item.quantity }} @ ${{ Number(item.unit_price).toFixed(2) }}</p>
                                    <OrderStatusBadge :status="item.pick_status" />
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Shipments -->
                    <div v-if="order.shipments.length > 0" class="bg-white rounded-lg border">
                        <div class="px-4 py-3 border-b">
                            <h3 class="text-sm font-medium text-gray-900">Shipments</h3>
                        </div>
                        <div class="divide-y">
                            <div v-for="shipment in order.shipments" :key="shipment.id" class="px-4 py-3">
                                <div class="flex justify-between items-start">
                                    <div class="min-w-0">
                                        <p class="text-sm font-medium text-gray-900">{{ shipment.shipment_number }}</p>
                                        <p class="text-xs text-gray-500">{{ shipment.carrier }} {{ shipment.service }}</p>
                                        <p v-if="shipment.tracking_number" class="text-xs mt-1">
                                            Tracking:
                                            <a v-if="shipment.tracking_link" :href="shipment.tracking_link" target="_blank" rel="noopener" class="text-indigo-600 hover:text-indigo-800 hover:underline">{{ shipment.tracking_number }}</a>
                                            <span v-else class="text-indigo-600">{{ shipment.tracking_number }}</span>
                                        </p>
                                        <p v-if="shipment.estimated_delivery_date" class="text-xs text-gray-500 mt-1">ETA: {{ formatDate(shipment.estimated_delivery_date) }}</p>
                                        <p v-if="shipment.actual_delivery_date" class="text-xs text-emerald-600 mt-1">Delivered: {{ formatDate(shipment.actual_delivery_date) }}</p>
                                    </div>
                                    <div class="text-right shrink-0 ml-3">
                                        <OrderStatusBadge :status="shipment.status" />
                                        <p v-if="shipment.label_cost" class="text-xs text-gray-500 mt-1">${{ Number(shipment.label_cost).toFixed(2) }}</p>
                                        <button v-if="shipment.tracking_number" @click="refreshTracking(shipment.id)" :disabled="refreshingShipmentId === shipment.id" class="mt-2 text-xs text-indigo-600 hover:text-indigo-800 hover:underline disabled:opacity-50">
                                            {{ refreshingShipmentId === shipment.id ? 'Refreshing...' : 'Refresh tracking' }}
                                        </button>
                                    </div>
                                </div>
                                <div v-if="shipment.label_url" class="mt-2">
                                    <a :href="shipment.label_url" target="_blank" class="text-xs text-indigo-600 hover:text-indigo-800">Download Label</a>
                                </div>
                                <div v-if="shipment.tracking_events && shipment.tracking_events.length > 0" class="mt-3 border-t pt-3">
                                    <p class="text-xs font-medium text-gray-700 mb-2">Tracking History
                                        <span v-if="shipment.tracking_provider" class="text-gray-400">via {{ shipment.tracking_provider }}</span>
                                    </p>
                                    <ol class="relative border-l border-gray-200 ml-2 space-y-2">
                                        <li v-for="(ev, idx) in shipment.tracking_events" :key="ev.id" class="ml-3">
                                            <span class="absolute -left-1.5 mt-1 h-3 w-3 rounded-full" :class="idx === 0 ? 'bg-indigo-600' : 'bg-gray-300'"></span>
                                            <p class="text-xs text-gray-900">{{ ev.description || prettyStatus(ev.status) }}</p>
                                            <p class="text-[11px] text-gray-500">
                                                {{ formatDateTime(ev.occurred_at) }}
                                                <span v-if="ev.city || ev.state">— {{ [ev.city, ev.state].filter(Boolean).join(', ') }}</span>
                                            </p>
                                        </li>
                                    </ol>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Audit Timeline -->
                    <div class="bg-white rounded-lg border">
                        <div class="px-4 py-3 border-b">
                            <h3 class="text-sm font-medium text-gray-900">Activity Timeline</h3>
                        </div>
                        <div class="px-4 py-4">
                            <OrderTimeline :timeline="timeline" />
                        </div>
                    </div>
                </div>

                <!-- Sidebar -->
                <div class="space-y-6">
                    <!-- Assignment -->
                    <div class="bg-white rounded-lg border p-4">
                        <h4 class="text-sm font-medium text-gray-900 mb-3">Assignment</h4>
                        <div class="space-y-2 text-sm">
                            <div class="flex justify-between">
                                <span class="text-gray-500">Location:</span>
                                <Link v-if="order.location?.id" :href="route('inventory.locations.show', order.location.id)" class="font-medium text-indigo-600 hover:text-indigo-800 hover:underline">{{ order.location.name }}</Link>
                                <span v-else class="font-medium">Unassigned</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-500">Picker:</span>
                                <Link v-if="order.assigned_picker?.id" :href="route('analytics.hub.users.show', order.assigned_picker.id)" class="text-indigo-600 hover:text-indigo-800 hover:underline">{{ order.assigned_picker.name }}</Link>
                                <span v-else>-</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-500">Packer:</span>
                                <Link v-if="order.assigned_packer?.id" :href="route('analytics.hub.users.show', order.assigned_packer.id)" class="text-indigo-600 hover:text-indigo-800 hover:underline">{{ order.assigned_packer.name }}</Link>
                                <span v-else>-</span>
                            </div>
                        </div>
                        <div v-if="order.status === 'pending'" class="mt-3">
                            <select v-model="assignLocation" class="w-full rounded-md border-gray-300 text-sm mb-2">
                                <option value="">Select location...</option>
                                <option v-for="loc in locations" :key="loc.id" :value="loc.id">{{ loc.name }}</option>
                            </select>
                            <button @click="assignOrder" :disabled="!assignLocation" class="w-full px-3 py-1.5 bg-indigo-600 text-white text-sm rounded-md hover:bg-indigo-700 disabled:opacity-50">Assign</button>
                        </div>
                    </div>

                    <!-- Shipping Address -->
                    <div class="bg-white rounded-lg border p-4">
                        <h4 class="text-sm font-medium text-gray-900 mb-2">Ship To</h4>
                        <a :href="mapsUrl" target="_blank" rel="noopener" class="text-sm text-gray-700 space-y-0.5 block hover:text-indigo-700">
                            <p class="font-medium">{{ order.shipping_address_name }}</p>
                            <p>{{ order.shipping_address_line1 }}</p>
                            <p v-if="order.shipping_address_line2">{{ order.shipping_address_line2 }}</p>
                            <p>{{ order.shipping_city }}, {{ order.shipping_state }} {{ order.shipping_postal_code }}</p>
                            <p v-if="order.shipping_country !== 'US'">{{ order.shipping_country }}</p>
                        </a>
                    </div>

                    <!-- Financials -->
                    <div class="bg-white rounded-lg border p-4">
                        <h4 class="text-sm font-medium text-gray-900 mb-2">Financials</h4>
                        <div class="text-sm space-y-1">
                            <div class="flex justify-between"><span class="text-gray-500">Subtotal:</span><span>${{ Number(order.subtotal).toFixed(2) }}</span></div>
                            <div class="flex justify-between"><span class="text-gray-500">Shipping:</span><span>${{ Number(order.shipping_cost).toFixed(2) }}</span></div>
                            <div class="flex justify-between"><span class="text-gray-500">Tax:</span><span>${{ Number(order.tax_amount).toFixed(2) }}</span></div>
                            <div class="flex justify-between"><span class="text-gray-500">Fees:</span><span class="text-red-600">-${{ Number(order.marketplace_fees).toFixed(2) }}</span></div>
                            <div class="flex justify-between border-t pt-1 font-medium"><span>Total:</span><span>${{ Number(order.total_amount).toFixed(2) }}</span></div>
                        </div>
                    </div>

                    <!-- Buyer Info -->
                    <div class="bg-white rounded-lg border p-4">
                        <h4 class="text-sm font-medium text-gray-900 mb-2">Buyer</h4>
                        <div class="text-sm text-gray-700 space-y-1">
                            <p>
                                <Link v-if="order.customer?.id" :href="route('customers.show', order.customer.id)" class="text-indigo-600 hover:text-indigo-800 hover:underline">{{ order.buyer_name }}</Link>
                                <span v-else>{{ order.buyer_name }}</span>
                                ({{ order.buyer_username }})
                            </p>
                            <p v-if="order.buyer_email"><a :href="`mailto:${order.buyer_email}`" class="text-indigo-600 hover:text-indigo-800 hover:underline">{{ order.buyer_email }}</a></p>
                            <p v-if="order.buyer_phone"><a :href="`tel:${order.buyer_phone}`" class="text-indigo-600 hover:text-indigo-800 hover:underline">{{ order.buyer_phone }}</a></p>
                        </div>
                    </div>

                    <!-- Notes -->
                    <div v-if="order.order_notes || order.internal_notes" class="bg-white rounded-lg border p-4">
                        <h4 class="text-sm font-medium text-gray-900 mb-2">Notes</h4>
                        <p v-if="order.order_notes" class="text-sm text-gray-700 mb-2">{{ order.order_notes }}</p>
                        <p v-if="order.internal_notes" class="text-sm text-amber-700 bg-amber-50 p-2 rounded">{{ order.internal_notes }}</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Hold Modal -->
        <div v-if="showHoldModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
            <div class="bg-white rounded-lg p-6 w-full max-w-md">
                <h3 class="text-lg font-medium mb-3">Hold Order</h3>
                <textarea v-model="holdReason" rows="3" class="w-full rounded-md border-gray-300 text-sm" placeholder="Reason for hold..." />
                <div class="flex justify-end gap-2 mt-4">
                    <button @click="showHoldModal = false" class="px-4 py-2 text-sm text-gray-600">Cancel</button>
                    <button @click="holdOrder" :disabled="!holdReason" class="px-4 py-2 text-sm bg-amber-600 text-white rounded-md hover:bg-amber-700 disabled:opacity-50">Hold Order</button>
                </div>
            </div>
        </div>

        <!-- Manual Ship Modal -->
        <div v-if="showManualShipModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
            <div class="bg-white rounded-lg p-6 w-full max-w-md">
                <h3 class="text-lg font-medium mb-1">Enter Tracking Manually</h3>
                <p class="text-xs text-gray-500 mb-4">Use this when you shipped outside the system or already have a label/tracking from another source.</p>
                <div class="space-y-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Carrier <span class="text-red-500">*</span></label>
                        <select v-model="manualShipForm.carrier" class="w-full rounded-md border-gray-300 text-sm">
                            <option value="">Select carrier...</option>
                            <option value="USPS">USPS</option>
                            <option value="UPS">UPS</option>
                            <option value="FedEx">FedEx</option>
                            <option value="DHL">DHL</option>
                            <option value="OnTrac">OnTrac</option>
                            <option value="LaserShip">LaserShip</option>
                            <option value="Canada Post">Canada Post</option>
                            <option value="Royal Mail">Royal Mail</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Tracking Number <span class="text-red-500">*</span></label>
                        <input v-model="manualShipForm.tracking_number" type="text" class="w-full rounded-md border-gray-300 text-sm" placeholder="9400 1000 0000 0000 0000 00" />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Service (optional)</label>
                        <input v-model="manualShipForm.service" type="text" class="w-full rounded-md border-gray-300 text-sm" placeholder="Priority Mail, Ground, etc." />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Tracking URL (optional)</label>
                        <input v-model="manualShipForm.tracking_url" type="url" class="w-full rounded-md border-gray-300 text-sm" placeholder="https://tools.usps.com/..." />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Shipped At (optional)</label>
                        <input v-model="manualShipForm.shipped_at" type="datetime-local" class="w-full rounded-md border-gray-300 text-sm" />
                    </div>
                    <div v-if="!order.location_id">
                        <label class="block text-xs font-medium text-gray-700 mb-1">Location <span class="text-red-500">*</span></label>
                        <select v-model="manualShipForm.location_id" class="w-full rounded-md border-gray-300 text-sm">
                            <option value="">Select location...</option>
                            <option v-for="loc in locations" :key="loc.id" :value="loc.id">{{ loc.name }}</option>
                        </select>
                        <p class="text-xs text-gray-500 mt-1">Order is unassigned. Pick a ship-from location.</p>
                    </div>
                </div>
                <div class="flex justify-end gap-2 mt-4">
                    <button @click="showManualShipModal = false" :disabled="manualShipSubmitting" class="px-4 py-2 text-sm text-gray-600">Cancel</button>
                    <button @click="submitManualShip" :disabled="!manualShipValid || manualShipSubmitting" class="px-4 py-2 text-sm bg-emerald-600 text-white rounded-md hover:bg-emerald-700 disabled:opacity-50">
                        {{ manualShipSubmitting ? 'Saving...' : 'Save Tracking & Ship' }}
                    </button>
                </div>
            </div>
        </div>

        <!-- Cancel Modal -->
        <div v-if="showCancelModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
            <div class="bg-white rounded-lg p-6 w-full max-w-md">
                <h3 class="text-lg font-medium mb-3">Cancel Order</h3>
                <textarea v-model="cancelReason" rows="3" class="w-full rounded-md border-gray-300 text-sm" placeholder="Reason for cancellation..." />
                <div class="flex justify-end gap-2 mt-4">
                    <button @click="showCancelModal = false" class="px-4 py-2 text-sm text-gray-600">Cancel</button>
                    <button @click="cancelOrder" :disabled="!cancelReason" class="px-4 py-2 text-sm bg-red-600 text-white rounded-md hover:bg-red-700 disabled:opacity-50">Cancel Order</button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { ref, computed } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import OrderStatusBadge from '@/Components/Fulfillment/OrderStatusBadge.vue';
import OrderTimeline from '@/Components/Fulfillment/OrderTimeline.vue';

const props = defineProps({
    order: Object,
    timeline: Array,
    locations: Array,
    availableWorkers: Array,
});

const assignLocation = ref('');
const showHoldModal = ref(false);
const showCancelModal = ref(false);
const showManualShipModal = ref(false);
const refreshingShipmentId = ref(null);
const manualShipSubmitting = ref(false);
const holdReason = ref('');
const cancelReason = ref('');
const manualShipForm = ref({
    carrier: '',
    tracking_number: '',
    service: '',
    tracking_url: '',
    shipped_at: '',
    location_id: '',
});

const marketplaceOrderUrl = computed(() => {
    if (!props.order?.marketplace_order_id) return null;
    if (props.order.marketplace === 'ebay') {
        return `https://www.ebay.com/sh/ord/details?orderid=${encodeURIComponent(props.order.marketplace_order_id)}`;
    }
    return null;
});

const mapsUrl = computed(() => {
    const parts = [
        props.order.shipping_address_line1,
        props.order.shipping_city,
        props.order.shipping_state,
        props.order.shipping_postal_code,
        props.order.shipping_country,
    ].filter(Boolean).join(', ');
    return `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(parts)}`;
});

const manualShipValid = computed(() => {
    if (!manualShipForm.value.carrier || !manualShipForm.value.tracking_number) return false;
    if (!props.order.location_id && !manualShipForm.value.location_id) return false;
    return true;
});

function openManualShip() {
    manualShipForm.value = {
        carrier: '',
        tracking_number: '',
        service: '',
        tracking_url: '',
        shipped_at: '',
        location_id: '',
    };
    showManualShipModal.value = true;
}

function submitManualShip() {
    manualShipSubmitting.value = true;
    const payload = Object.fromEntries(
        Object.entries(manualShipForm.value).filter(([, v]) => v !== '' && v !== null)
    );
    router.post(route('fulfillment.orders.manual-ship', props.order.id), payload, {
        preserveScroll: true,
        onSuccess: () => { showManualShipModal.value = false; },
        onFinish: () => { manualShipSubmitting.value = false; },
    });
}

function assignOrder() {
    router.post(route('fulfillment.orders.assign', props.order.id), { location_id: assignLocation.value });
}

function holdOrder() {
    router.post(route('fulfillment.orders.hold', props.order.id), { reason: holdReason.value }, { onSuccess: () => { showHoldModal.value = false; holdReason.value = ''; } });
}

function releaseOrder() {
    router.post(route('fulfillment.orders.release', props.order.id));
}

function cancelOrder() {
    router.post(route('fulfillment.orders.cancel', props.order.id), { reason: cancelReason.value }, { onSuccess: () => { showCancelModal.value = false; cancelReason.value = ''; } });
}

function formatDateTime(d) {
    return new Date(d).toLocaleString('en-US', { month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' });
}

function formatDate(d) {
    return new Date(d).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
}

function prettyStatus(status) {
    return (status || '').replace(/_/g, ' ');
}

function refreshTracking(shipmentId) {
    refreshingShipmentId.value = shipmentId;
    router.post(route('fulfillment.shipments.refresh-tracking', shipmentId), {}, {
        preserveScroll: true,
        onFinish: () => { refreshingShipmentId.value = null; },
    });
}
</script>
