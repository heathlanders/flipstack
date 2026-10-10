<template>
    <AppLayout title="Ship Queue">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
            <!-- Stats -->
            <div class="grid grid-cols-4 gap-4 mb-6">
                <div class="bg-white rounded-lg border p-3">
                    <p class="text-xs text-gray-500">Ready to Ship</p>
                    <p class="text-xl font-bold text-sky-600">{{ stats.ready_to_ship }}</p>
                </div>
                <div class="bg-white rounded-lg border p-3">
                    <p class="text-xs text-gray-500">Shipped Today</p>
                    <p class="text-xl font-bold text-green-600">{{ stats.shipped_today }}</p>
                </div>
                <div class="bg-white rounded-lg border p-3">
                    <p class="text-xs text-gray-500">In Transit</p>
                    <p class="text-xl font-bold text-indigo-600">{{ stats.in_transit }}</p>
                </div>
                <div class="bg-white rounded-lg border p-3">
                    <p class="text-xs text-gray-500">Delivered Today</p>
                    <p class="text-xl font-bold text-emerald-600">{{ stats.delivered_today }}</p>
                </div>
            </div>

            <!-- Bulk Actions -->
            <div v-if="selectedShipments.length > 0" class="bg-sky-50 rounded-lg border border-sky-200 p-3 mb-4 flex items-center justify-between">
                <span class="text-sm text-sky-700">{{ selectedShipments.length }} shipment(s) selected</span>
                <div class="flex gap-2">
                    <button @click="bulkPrint" class="px-3 py-1.5 text-xs bg-gray-800 text-white rounded hover:bg-gray-900">Batch Print Labels</button>
                    <button @click="bulkShip" class="px-3 py-1.5 text-xs bg-sky-600 text-white rounded hover:bg-sky-700">Mark All Shipped</button>
                </div>
            </div>

            <!-- Shipments Table -->
            <div class="bg-white rounded-lg border overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 w-8">
                                <input type="checkbox" @change="toggleAll" :checked="allSelected" class="rounded border-gray-300" />
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Shipment</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Order</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Carrier / Service</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tracking</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Destination</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <tr v-for="shipment in shipments.data" :key="shipment.id">
                            <td class="px-4 py-3">
                                <input type="checkbox" :value="shipment.id" v-model="selectedShipments" class="rounded border-gray-300" />
                            </td>
                            <td class="px-4 py-3 text-sm font-medium text-gray-900">{{ shipment.shipment_number }}</td>
                            <td class="px-4 py-3">
                                <a :href="route('fulfillment.orders.show', shipment.order.id)" class="text-sm text-indigo-600 hover:text-indigo-800">
                                    {{ shipment.order.marketplace_order_id?.substring(0, 15) }}
                                </a>
                                <p class="text-xs text-gray-500">{{ shipment.order.buyer_name }}</p>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-700">
                                <p>{{ shipment.carrier }}</p>
                                <p class="text-xs text-gray-500">{{ shipment.service }}</p>
                            </td>
                            <td class="px-4 py-3 text-sm font-mono text-gray-700">{{ shipment.tracking_number }}</td>
                            <td class="px-4 py-3 text-sm text-gray-600">
                                {{ shipment.order.shipping_city }}, {{ shipment.order.shipping_state }}
                            </td>
                            <td class="px-4 py-3">
                                <OrderStatusBadge :status="shipment.status" />
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div class="flex gap-2 justify-end">
                                    <a v-if="shipment.label_url" :href="shipment.label_url" target="_blank" class="text-xs text-gray-600 hover:text-gray-900">Label</a>
                                    <button v-if="!shipment.tracking_number" @click="openManualShip(shipment)" class="text-xs text-emerald-600 hover:text-emerald-800 font-medium">Enter Tracking</button>
                                    <button v-if="shipment.tracking_number" @click="markShipped(shipment)" class="text-xs text-sky-600 hover:text-sky-800 font-medium">Ship</button>
                                    <button v-if="shipment.label_url" @click="voidLabel(shipment)" class="text-xs text-red-500 hover:text-red-700">Void</button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <p v-if="shipments.data.length === 0" class="text-center text-gray-500 py-8">No shipments ready to ship.</p>
            </div>
        </div>

        <!-- Manual Ship Modal -->
        <div v-if="manualShipOrderId" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
            <div class="bg-white rounded-lg p-6 w-full max-w-md">
                <h3 class="text-lg font-medium mb-1">Enter Tracking Manually</h3>
                <p class="text-xs text-gray-500 mb-4">Use this when the order shipped outside the system.</p>
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
                        <input v-model="manualShipForm.tracking_number" type="text" class="w-full rounded-md border-gray-300 text-sm" />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Service (optional)</label>
                        <input v-model="manualShipForm.service" type="text" class="w-full rounded-md border-gray-300 text-sm" placeholder="Priority Mail, Ground, etc." />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Tracking URL (optional)</label>
                        <input v-model="manualShipForm.tracking_url" type="url" class="w-full rounded-md border-gray-300 text-sm" />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Shipped At (optional)</label>
                        <input v-model="manualShipForm.shipped_at" type="datetime-local" class="w-full rounded-md border-gray-300 text-sm" />
                    </div>
                </div>
                <div class="flex justify-end gap-2 mt-4">
                    <button @click="manualShipOrderId = null" :disabled="manualShipSubmitting" class="px-4 py-2 text-sm text-gray-600">Cancel</button>
                    <button @click="submitManualShip" :disabled="!manualShipValid || manualShipSubmitting" class="px-4 py-2 text-sm bg-emerald-600 text-white rounded-md hover:bg-emerald-700 disabled:opacity-50">
                        {{ manualShipSubmitting ? 'Saving...' : 'Save Tracking & Ship' }}
                    </button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import OrderStatusBadge from '@/Components/Fulfillment/OrderStatusBadge.vue';

const props = defineProps({
    shipments: Object,
    stats: Object,
});

const selectedShipments = ref([]);
const allSelected = computed(() => props.shipments.data.length > 0 && selectedShipments.value.length === props.shipments.data.length);

const manualShipOrderId = ref(null);
const manualShipSubmitting = ref(false);
const manualShipForm = ref({
    carrier: '',
    tracking_number: '',
    service: '',
    tracking_url: '',
    shipped_at: '',
});

const manualShipValid = computed(() => !!manualShipForm.value.carrier && !!manualShipForm.value.tracking_number);

function openManualShip(shipment) {
    manualShipForm.value = { carrier: '', tracking_number: '', service: '', tracking_url: '', shipped_at: '' };
    manualShipOrderId.value = shipment.order.id;
}

function submitManualShip() {
    manualShipSubmitting.value = true;
    const payload = Object.fromEntries(
        Object.entries(manualShipForm.value).filter(([, v]) => v !== '' && v !== null)
    );
    router.post(route('fulfillment.orders.manual-ship', manualShipOrderId.value), payload, {
        preserveScroll: true,
        onSuccess: () => { manualShipOrderId.value = null; },
        onFinish: () => { manualShipSubmitting.value = false; },
    });
}

function toggleAll(e) {
    selectedShipments.value = e.target.checked ? props.shipments.data.map(s => s.id) : [];
}

function markShipped(shipment) {
    router.post(route('fulfillment.ship.confirm', shipment.id));
}

function voidLabel(shipment) {
    if (confirm('Void this label? This will refund the postage.')) {
        router.post(route('fulfillment.ship.void', shipment.id));
    }
}

function bulkShip() {
    router.post(route('fulfillment.ship.bulk'), { shipment_ids: selectedShipments.value }, { onSuccess: () => { selectedShipments.value = []; } });
}

function bulkPrint() {
    router.post(route('fulfillment.ship.batch-print'), { shipment_ids: selectedShipments.value });
}
</script>
