<template>
    <div class="flow-root">
        <ul role="list" class="-mb-8">
            <li v-for="(event, index) in timeline" :key="event.id" class="relative pb-8">
                <span v-if="index !== timeline.length - 1" class="absolute left-4 top-4 -ml-px h-full w-0.5 bg-gray-200" aria-hidden="true" />
                <div class="relative flex space-x-3">
                    <div>
                        <span :class="['h-8 w-8 rounded-full flex items-center justify-center ring-8 ring-white', getActionColor(event.action)]">
                            <svg class="h-4 w-4 text-white" fill="currentColor" viewBox="0 0 20 20">
                                <path v-if="isCompletionAction(event.action)" fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                                <path v-else-if="isShippingAction(event.action)" fill-rule="evenodd" d="M3 4a1 1 0 011-1h12a1 1 0 011 1v2a1 1 0 01-1 1H4a1 1 0 01-1-1V4zm0 6a1 1 0 011-1h12a1 1 0 011 1v2a1 1 0 01-1 1H4a1 1 0 01-1-1v-2zm0 6a1 1 0 011-1h12a1 1 0 011 1v2a1 1 0 01-1 1H4a1 1 0 01-1-1v-2z" clip-rule="evenodd" />
                                <circle v-else cx="10" cy="10" r="4" />
                            </svg>
                        </span>
                    </div>
                    <div class="flex min-w-0 flex-1 justify-between space-x-4 pt-1.5">
                        <div>
                            <p class="text-sm text-gray-700">
                                {{ event.action_label }}
                                <span class="font-medium text-gray-900">{{ event.user }}</span>
                            </p>
                            <p v-if="event.metadata" class="mt-0.5 text-xs text-gray-500">
                                <template v-if="event.metadata.reason">Reason: {{ event.metadata.reason }}</template>
                                <template v-else-if="event.metadata.tracking_number">Tracking: {{ event.metadata.tracking_number }}</template>
                                <template v-else-if="event.metadata.carrier">{{ event.metadata.carrier }} {{ event.metadata.service }} - ${{ event.metadata.cost }}</template>
                                <template v-else-if="event.metadata.location_name">Location: {{ event.metadata.location_name }}</template>
                                <template v-else-if="event.metadata.quantity">Qty: {{ event.metadata.quantity }}</template>
                            </p>
                        </div>
                        <div class="whitespace-nowrap text-right text-xs text-gray-500">
                            {{ event.relative_time }}
                        </div>
                    </div>
                </div>
            </li>
        </ul>
        <p v-if="!timeline || timeline.length === 0" class="text-sm text-gray-500 text-center py-4">
            No activity recorded yet.
        </p>
    </div>
</template>

<script setup>
defineProps({
    timeline: { type: Array, default: () => [] },
});

function getActionColor(action) {
    if (action.includes('cancelled') || action.includes('voided') || action.includes('denied')) return 'bg-red-500';
    if (action.includes('delivered') || action.includes('completed') || action.includes('refunded')) return 'bg-green-500';
    if (action.includes('shipped') || action.includes('tracking')) return 'bg-sky-500';
    if (action.includes('label') || action.includes('rate')) return 'bg-teal-500';
    if (action.includes('pick')) return 'bg-indigo-500';
    if (action.includes('pack')) return 'bg-purple-500';
    if (action.includes('hold') || action.includes('short')) return 'bg-amber-500';
    if (action.includes('return')) return 'bg-orange-500';
    return 'bg-blue-500';
}

function isCompletionAction(action) {
    return action.includes('completed') || action.includes('delivered') || action.includes('refunded');
}

function isShippingAction(action) {
    return action.includes('shipped') || action.includes('tracking') || action.includes('label');
}
</script>
