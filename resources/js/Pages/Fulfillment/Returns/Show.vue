<template>
    <AppLayout :title="'Return ' + returnRequest.rma_number">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
            <!-- Header -->
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h1 class="text-xl font-bold text-gray-900">{{ returnRequest.rma_number }}</h1>
                    <p class="text-sm text-gray-500">Return for order {{ returnRequest.order?.marketplace_order_id }}</p>
                </div>
                <OrderStatusBadge :status="returnRequest.status" />
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <div class="lg:col-span-2 space-y-6">
                    <!-- Return Details -->
                    <div class="bg-white rounded-lg border p-4">
                        <h3 class="text-sm font-medium text-gray-900 mb-3">Return Details</h3>
                        <dl class="grid grid-cols-2 gap-3 text-sm">
                            <div><dt class="text-gray-500">Reason:</dt><dd class="font-medium">{{ returnRequest.reason }}</dd></div>
                            <div><dt class="text-gray-500">Source:</dt><dd>{{ returnRequest.source }}</dd></div>
                            <div><dt class="text-gray-500">Quantity:</dt><dd>{{ returnRequest.quantity }}</dd></div>
                            <div><dt class="text-gray-500">Restockable:</dt><dd>{{ returnRequest.restockable ? 'Yes' : 'No' }}</dd></div>
                            <div v-if="returnRequest.refund_amount"><dt class="text-gray-500">Refund Amount:</dt><dd class="font-medium">${{ Number(returnRequest.refund_amount).toFixed(2) }}</dd></div>
                            <div v-if="returnRequest.return_tracking_number"><dt class="text-gray-500">Return Tracking:</dt><dd class="font-mono">{{ returnRequest.return_tracking_number }}</dd></div>
                        </dl>
                        <p v-if="returnRequest.reason_details" class="mt-3 text-sm text-gray-600 bg-gray-50 p-3 rounded">{{ returnRequest.reason_details }}</p>
                    </div>

                    <!-- Inspection (if received) -->
                    <div v-if="returnRequest.status === 'received'" class="bg-white rounded-lg border p-4">
                        <h3 class="text-sm font-medium text-gray-900 mb-3">Inspection Form</h3>
                        <div class="space-y-3">
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Condition on Return</label>
                                <select v-model="inspectionForm.condition" class="w-full rounded-md border-gray-300 text-sm">
                                    <option value="">Select condition...</option>
                                    <option value="like_new">Like New</option>
                                    <option value="good">Good</option>
                                    <option value="fair">Fair</option>
                                    <option value="poor">Poor</option>
                                    <option value="damaged">Damaged</option>
                                    <option value="defective">Defective</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Inspection Notes</label>
                                <textarea v-model="inspectionForm.notes" rows="3" class="w-full rounded-md border-gray-300 text-sm" placeholder="Condition details..." />
                            </div>
                            <div class="flex items-center gap-2">
                                <input type="checkbox" v-model="inspectionForm.restockable" class="rounded border-gray-300" />
                                <label class="text-sm text-gray-700">Item can be restocked</label>
                            </div>
                            <button @click="submitInspection" :disabled="!inspectionForm.condition" class="px-4 py-2 bg-indigo-600 text-white text-sm rounded-md hover:bg-indigo-700 disabled:opacity-50">
                                Submit Inspection
                            </button>
                        </div>
                    </div>

                    <!-- Inspection Results -->
                    <div v-if="returnRequest.inspection_notes" class="bg-white rounded-lg border p-4">
                        <h3 class="text-sm font-medium text-gray-900 mb-2">Inspection Results</h3>
                        <p class="text-sm"><span class="text-gray-500">Condition:</span> {{ returnRequest.condition_on_return }}</p>
                        <p class="text-sm mt-1"><span class="text-gray-500">Notes:</span> {{ returnRequest.inspection_notes }}</p>
                        <p class="text-sm mt-1"><span class="text-gray-500">Inspector:</span> {{ returnRequest.inspected_by_user?.name }} at {{ formatDateTime(returnRequest.inspected_at) }}</p>
                    </div>
                </div>

                <!-- Sidebar Actions -->
                <div class="space-y-4">
                    <div class="bg-white rounded-lg border p-4">
                        <h4 class="text-sm font-medium text-gray-900 mb-3">Actions</h4>
                        <div class="space-y-2">
                            <button v-if="returnRequest.status === 'requested'" @click="approve" class="w-full px-3 py-2 text-sm bg-green-600 text-white rounded-md hover:bg-green-700">Approve Return</button>
                            <button v-if="['approved','shipped_back'].includes(returnRequest.status)" @click="receive" class="w-full px-3 py-2 text-sm bg-purple-600 text-white rounded-md hover:bg-purple-700">Mark Received</button>
                            <button v-if="returnRequest.status === 'inspected'" @click="refund" class="w-full px-3 py-2 text-sm bg-green-600 text-white rounded-md hover:bg-green-700">Process Refund</button>
                            <button v-if="returnRequest.status === 'requested'" @click="showDenyModal = true" class="w-full px-3 py-2 text-sm text-red-600 border border-red-300 rounded-md hover:bg-red-50">Deny Return</button>
                            <div v-if="returnRequest.restockable && returnRequest.status === 'inspected'" class="border-t pt-2 mt-2">
                                <label class="block text-xs font-medium text-gray-600 mb-1">Restock Location</label>
                                <select v-model="restockLocation" class="w-full rounded-md border-gray-300 text-sm mb-2">
                                    <option value="">Select location...</option>
                                    <option v-for="loc in locations" :key="loc.id" :value="loc.id">{{ loc.name }}</option>
                                </select>
                                <button @click="restock" :disabled="!restockLocation" class="w-full px-3 py-1.5 text-xs bg-teal-600 text-white rounded hover:bg-teal-700 disabled:opacity-50">Restock Item</button>
                            </div>
                        </div>
                    </div>

                    <!-- Timestamps -->
                    <div class="bg-white rounded-lg border p-4">
                        <h4 class="text-sm font-medium text-gray-900 mb-2">Timeline</h4>
                        <div class="text-xs text-gray-500 space-y-1">
                            <p>Created: {{ formatDateTime(returnRequest.created_at) }}</p>
                            <p v-if="returnRequest.received_at">Received: {{ formatDateTime(returnRequest.received_at) }}</p>
                            <p v-if="returnRequest.inspected_at">Inspected: {{ formatDateTime(returnRequest.inspected_at) }}</p>
                            <p v-if="returnRequest.refunded_at">Refunded: {{ formatDateTime(returnRequest.refunded_at) }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Deny Modal -->
        <div v-if="showDenyModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
            <div class="bg-white rounded-lg p-6 w-full max-w-md">
                <h3 class="text-lg font-medium mb-3">Deny Return</h3>
                <textarea v-model="denyReason" rows="3" class="w-full rounded-md border-gray-300 text-sm" placeholder="Reason for denial..." />
                <div class="flex justify-end gap-2 mt-4">
                    <button @click="showDenyModal = false" class="px-4 py-2 text-sm text-gray-600">Cancel</button>
                    <button @click="deny" :disabled="!denyReason" class="px-4 py-2 text-sm bg-red-600 text-white rounded-md disabled:opacity-50">Deny</button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { ref, reactive } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import OrderStatusBadge from '@/Components/Fulfillment/OrderStatusBadge.vue';

const props = defineProps({
    returnRequest: Object,
    locations: Array,
});

const showDenyModal = ref(false);
const denyReason = ref('');
const restockLocation = ref('');
const inspectionForm = reactive({ condition: '', notes: '', restockable: props.returnRequest.restockable });

function approve() { router.post(route('fulfillment.returns.approve', props.returnRequest.id)); }
function receive() { router.post(route('fulfillment.returns.receive', props.returnRequest.id)); }
function refund() { router.post(route('fulfillment.returns.refund', props.returnRequest.id)); }
function deny() { router.post(route('fulfillment.returns.deny', props.returnRequest.id), { reason: denyReason.value }, { onSuccess: () => { showDenyModal.value = false; } }); }
function restock() { router.post(route('fulfillment.returns.restock', props.returnRequest.id), { location_id: restockLocation.value }); }
function submitInspection() { router.post(route('fulfillment.returns.inspect', props.returnRequest.id), inspectionForm); }
function formatDateTime(d) { return d ? new Date(d).toLocaleString('en-US', { month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' }) : '-'; }
</script>
