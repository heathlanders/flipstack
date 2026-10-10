<template>
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
        <!-- Header -->
        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-3">
                    <div 
                        :class="statusColorClass"
                        class="w-10 h-10 rounded-full flex items-center justify-center"
                    >
                        <component :is="statusIcon" class="w-5 h-5 text-white" />
                    </div>
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                            {{ settlement.settlement_number }}
                        </h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            {{ formatDateRange(settlement.period_start, settlement.period_end) }}
                        </p>
                    </div>
                </div>
                <span :class="statusBadgeClass" class="px-3 py-1 text-sm font-medium rounded-full">
                    {{ formatStatus(settlement.status) }}
                </span>
            </div>
        </div>

        <!-- Financial Summary -->
        <div class="px-6 py-4">
            <div class="grid grid-cols-2 gap-4 mb-4">
                <div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">Gross Sales</div>
                    <div class="text-lg font-semibold text-gray-900 dark:text-white">
                        {{ formatCurrency(settlement.gross_amount) }}
                    </div>
                </div>
                <div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">Items Sold</div>
                    <div class="text-lg font-semibold text-gray-900 dark:text-white">
                        {{ settlement.items_count || 0 }}
                    </div>
                </div>
            </div>

            <!-- Breakdown -->
            <div class="space-y-2 py-3 border-t border-gray-200 dark:border-gray-700">
                <div class="flex justify-between text-sm">
                    <span class="text-gray-500 dark:text-gray-400">Commission</span>
                    <span class="text-red-600">-{{ formatCurrency(settlement.commission_amount) }}</span>
                </div>
                <div v-if="settlement.fees_amount > 0" class="flex justify-between text-sm">
                    <span class="text-gray-500 dark:text-gray-400">Fees</span>
                    <span class="text-red-600">-{{ formatCurrency(settlement.fees_amount) }}</span>
                </div>
                <div v-if="settlement.adjustments_amount !== 0" class="flex justify-between text-sm">
                    <span class="text-gray-500 dark:text-gray-400">Adjustments</span>
                    <span :class="settlement.adjustments_amount > 0 ? 'text-green-600' : 'text-red-600'">
                        {{ settlement.adjustments_amount > 0 ? '+' : '' }}{{ formatCurrency(settlement.adjustments_amount) }}
                    </span>
                </div>
            </div>

            <!-- Net Amount -->
            <div class="flex justify-between items-center py-3 border-t border-gray-200 dark:border-gray-700">
                <span class="font-medium text-gray-900 dark:text-white">Net Payout</span>
                <span class="text-xl font-bold text-green-600">
                    {{ formatCurrency(settlement.net_amount) }}
                </span>
            </div>

            <!-- Payment Info (if paid) -->
            <div v-if="settlement.status === 'paid'" class="py-3 border-t border-gray-200 dark:border-gray-700">
                <div class="flex items-center space-x-2 text-sm text-gray-500 dark:text-gray-400">
                    <BanknotesIcon class="w-4 h-4" />
                    <span>Paid on {{ formatDate(settlement.paid_at) }}</span>
                    <span v-if="settlement.payment_reference" class="text-gray-400">
                        • Ref: {{ settlement.payment_reference }}
                    </span>
                </div>
            </div>

            <!-- Dispute Info (if disputed) -->
            <div v-if="settlement.dispute_status" class="py-3 border-t border-gray-200 dark:border-gray-700">
                <div class="flex items-center space-x-2 text-sm text-yellow-600 dark:text-yellow-400">
                    <ExclamationTriangleIcon class="w-4 h-4" />
                    <span>Dispute {{ settlement.dispute_status }}: {{ settlement.dispute_reason }}</span>
                </div>
            </div>
        </div>

        <!-- Actions -->
        <div class="px-6 py-4 bg-gray-50 dark:bg-gray-900 border-t border-gray-200 dark:border-gray-700">
            <div class="flex space-x-3">
                <Link 
                    :href="settlement.id ? route('supplier.settlements.show', settlement.id) : '#'"
                    class="flex-1 text-center px-4 py-2 text-sm font-medium text-indigo-600 dark:text-indigo-400 bg-white dark:bg-gray-800 border border-indigo-300 dark:border-indigo-600 rounded-lg hover:bg-indigo-50 dark:hover:bg-gray-700 transition-colors"
                >
                    View Details
                </Link>
                <button 
                    v-if="settlement.status === 'paid'"
                    @click="$emit('download', settlement)"
                    class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors"
                >
                    <ArrowDownTrayIcon class="w-4 h-4" />
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { 
    CheckCircleIcon, 
    ClockIcon, 
    XCircleIcon,
    ExclamationTriangleIcon,
    BanknotesIcon,
    ArrowDownTrayIcon,
    DocumentTextIcon
} from '@heroicons/vue/24/outline';

const props = defineProps({
    settlement: {
        type: Object,
        required: true
    }
});

defineEmits(['download']);

const statusColorClass = computed(() => {
    const colors = {
        'draft': 'bg-gray-400',
        'pending': 'bg-yellow-500',
        'processing': 'bg-blue-500',
        'approved': 'bg-green-500',
        'paid': 'bg-green-600',
        'disputed': 'bg-red-500',
        'cancelled': 'bg-gray-500'
    };
    return colors[props.settlement.status] || 'bg-gray-400';
});

const statusBadgeClass = computed(() => {
    const classes = {
        'draft': 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300',
        'pending': 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
        'processing': 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
        'approved': 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
        'paid': 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
        'disputed': 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
        'cancelled': 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300'
    };
    return classes[props.settlement.status] || classes['draft'];
});

const statusIcon = computed(() => {
    const icons = {
        'draft': DocumentTextIcon,
        'pending': ClockIcon,
        'processing': ClockIcon,
        'approved': CheckCircleIcon,
        'paid': CheckCircleIcon,
        'disputed': ExclamationTriangleIcon,
        'cancelled': XCircleIcon
    };
    return icons[props.settlement.status] || DocumentTextIcon;
});

function formatCurrency(value) {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD'
    }).format(value || 0);
}

function formatDate(date) {
    if (!date) return 'N/A';
    return new Date(date).toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric'
    });
}

function formatDateRange(start, end) {
    const startDate = new Date(start);
    const endDate = new Date(end);
    const options = { month: 'short', day: 'numeric' };
    
    if (startDate.getFullYear() !== endDate.getFullYear()) {
        return `${startDate.toLocaleDateString('en-US', { ...options, year: 'numeric' })} - ${endDate.toLocaleDateString('en-US', { ...options, year: 'numeric' })}`;
    }
    
    return `${startDate.toLocaleDateString('en-US', options)} - ${endDate.toLocaleDateString('en-US', { ...options, year: 'numeric' })}`;
}

function formatStatus(status) {
    if (!status) return 'Unknown';
    return status.charAt(0).toUpperCase() + status.slice(1);
}
</script>
