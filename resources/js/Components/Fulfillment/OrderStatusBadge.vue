<template>
    <span :class="['inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium', colorClasses]">
        {{ label }}
    </span>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    status: { type: String, required: true },
});

const statusConfig = {
    pending: { label: 'Pending', classes: 'bg-gray-100 text-gray-800' },
    assigned: { label: 'Assigned', classes: 'bg-blue-100 text-blue-800' },
    picking: { label: 'Picking', classes: 'bg-indigo-100 text-indigo-800' },
    picked: { label: 'Picked', classes: 'bg-indigo-200 text-indigo-900' },
    packing: { label: 'Packing', classes: 'bg-purple-100 text-purple-800' },
    packed: { label: 'Packed', classes: 'bg-purple-200 text-purple-900' },
    shipped: { label: 'Shipped', classes: 'bg-sky-100 text-sky-800' },
    in_transit: { label: 'In Transit', classes: 'bg-sky-200 text-sky-900' },
    out_for_delivery: { label: 'Out for Delivery', classes: 'bg-emerald-100 text-emerald-800' },
    delivered: { label: 'Delivered', classes: 'bg-green-100 text-green-800' },
    cancelled: { label: 'Cancelled', classes: 'bg-red-100 text-red-800' },
    on_hold: { label: 'On Hold', classes: 'bg-amber-100 text-amber-800' },
    return_initiated: { label: 'Return', classes: 'bg-orange-100 text-orange-800' },
    returned: { label: 'Returned', classes: 'bg-orange-200 text-orange-900' },
    label_purchased: { label: 'Label Purchased', classes: 'bg-teal-100 text-teal-800' },
    failed: { label: 'Failed', classes: 'bg-red-200 text-red-900' },
    returned_to_sender: { label: 'RTS', classes: 'bg-red-100 text-red-700' },
    // Return statuses
    requested: { label: 'Requested', classes: 'bg-yellow-100 text-yellow-800' },
    approved: { label: 'Approved', classes: 'bg-blue-100 text-blue-800' },
    shipped_back: { label: 'Shipped Back', classes: 'bg-indigo-100 text-indigo-800' },
    received: { label: 'Received', classes: 'bg-purple-100 text-purple-800' },
    inspected: { label: 'Inspected', classes: 'bg-cyan-100 text-cyan-800' },
    refunded: { label: 'Refunded', classes: 'bg-green-100 text-green-800' },
    denied: { label: 'Denied', classes: 'bg-red-100 text-red-800' },
};

const label = computed(() => statusConfig[props.status]?.label || props.status.replace(/_/g, ' '));
const colorClasses = computed(() => statusConfig[props.status]?.classes || 'bg-gray-100 text-gray-800');
</script>
