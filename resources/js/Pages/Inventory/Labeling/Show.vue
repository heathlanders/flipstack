<script setup>
import { ref } from 'vue'
import { router, Head, Link, useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { route } from 'ziggy-js'

const props = defineProps({
    workOrder: { type: Object, required: true },
    progress: { type: Object, default: () => ({}) },
    bins: { type: Array, default: () => [] },
})

const statusBadgeClasses = {
    open: 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300',
    in_progress: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300',
    completed: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300',
    partially_completed: 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-300',
}

const statusLabels = {
    open: 'Open',
    in_progress: 'In Progress',
    completed: 'Completed',
    partially_completed: 'Partially Completed',
}

const itemStatusBadgeClasses = {
    pending: 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300',
    label_printed: 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300',
    shelved: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300',
    skipped: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300',
}

const itemStatusLabels = {
    pending: 'Pending',
    label_printed: 'Labeled',
    shelved: 'Shelved',
    skipped: 'Skipped',
}

const sourceLabels = {
    po_receipt: 'PO Receipt',
    intake_queue: 'Intake Queue',
    csv_import: 'CSV Import',
    part_out: 'Part-Out',
    manual: 'Manual',
}

const formatDate = (date) => {
    if (!date) return '-'
    return new Date(date).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric', hour: '2-digit', minute: '2-digit' })
}

const progressPercent = () => {
    if (props.progress.total === 0) return 0
    return Math.round((props.progress.shelved / props.progress.total) * 100)
}

const isActionable = ['open', 'in_progress'].includes(props.workOrder.status)

// Bin assignment state per item
const binSelections = ref({})
const skipReasons = ref({})
const showSkipModal = ref(null)

function startWorkOrder() {
    router.post(route('inventory.labeling.start', props.workOrder.id), {}, { preserveScroll: true })
}

function markAllLabeled() {
    if (confirm('Mark all pending items as labeled?')) {
        router.post(route('inventory.labeling.print-all', props.workOrder.id), {}, { preserveScroll: true })
    }
}

function completeWorkOrder() {
    if (confirm('Complete this work order?')) {
        router.post(route('inventory.labeling.complete', props.workOrder.id), {}, { preserveScroll: true })
    }
}

function markItemLabeled(item) {
    router.post(route('inventory.labeling.items.mark-labeled', item.id), {}, { preserveScroll: true })
}

function assignBin(item) {
    const binId = binSelections.value[item.id]
    if (!binId) return
    router.post(route('inventory.labeling.items.assign-bin', item.id), { bin_id: binId }, { preserveScroll: true })
}

function skipItem(item) {
    const reason = skipReasons.value[item.id]
    if (!reason) return
    router.post(route('inventory.labeling.items.skip', item.id), { reason }, { preserveScroll: true })
    showSkipModal.value = null
}
</script>

<template>
    <Head :title="'Labeling WO: ' + workOrder.work_order_number" />

    <AppLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <Link :href="route('inventory.labeling.index')" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                        <i class="fas fa-arrow-left"></i>
                    </Link>
                    <div>
                        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200">{{ workOrder.work_order_number }}</h2>
                        <div class="flex items-center gap-2 mt-1">
                            <span :class="statusBadgeClasses[workOrder.status]" class="px-2 py-0.5 text-xs font-medium rounded-full">
                                {{ statusLabels[workOrder.status] }}
                            </span>
                            <span class="text-sm text-gray-500 dark:text-gray-400">{{ sourceLabels[workOrder.source_type] }}</span>
                            <span v-if="workOrder.location" class="text-sm text-gray-500 dark:text-gray-400">at {{ workOrder.location.name }}</span>
                        </div>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <button
                        v-if="workOrder.status === 'open'"
                        @click="startWorkOrder"
                        class="inline-flex items-center px-3 py-2 bg-blue-600 rounded-md text-xs font-semibold text-white uppercase hover:bg-blue-500 transition"
                    >
                        Start Work Order
                    </button>
                    <button
                        v-if="isActionable"
                        @click="markAllLabeled"
                        class="inline-flex items-center px-3 py-2 bg-indigo-600 rounded-md text-xs font-semibold text-white uppercase hover:bg-indigo-500 transition"
                    >
                        Mark All Labeled
                    </button>
                    <button
                        v-if="isActionable"
                        @click="completeWorkOrder"
                        class="inline-flex items-center px-3 py-2 bg-green-600 rounded-md text-xs font-semibold text-white uppercase hover:bg-green-500 transition"
                    >
                        Complete
                    </button>
                </div>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <!-- Progress -->
                <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-6">
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
                        <div class="text-sm text-gray-500 dark:text-gray-400">Total Items</div>
                        <div class="text-2xl font-bold text-gray-700 dark:text-gray-300">{{ progress.total }}</div>
                    </div>
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
                        <div class="text-sm text-gray-500 dark:text-gray-400">Pending</div>
                        <div class="text-2xl font-bold text-gray-500">{{ progress.pending }}</div>
                    </div>
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
                        <div class="text-sm text-gray-500 dark:text-gray-400">Labeled</div>
                        <div class="text-2xl font-bold text-blue-600">{{ progress.labeled }}</div>
                    </div>
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
                        <div class="text-sm text-gray-500 dark:text-gray-400">Shelved</div>
                        <div class="text-2xl font-bold text-green-600">{{ progress.shelved }}</div>
                    </div>
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
                        <div class="text-sm text-gray-500 dark:text-gray-400">Skipped</div>
                        <div class="text-2xl font-bold text-red-500">{{ progress.skipped }}</div>
                    </div>
                </div>

                <!-- Progress Bar -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4 mb-6">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Overall Progress</span>
                        <span class="text-sm text-gray-500 dark:text-gray-400">{{ progressPercent() }}%</span>
                    </div>
                    <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-3">
                        <div
                            class="bg-green-500 h-3 rounded-full transition-all"
                            :style="{ width: progressPercent() + '%' }"
                        ></div>
                    </div>
                </div>

                <!-- Info Row -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4 mb-6 grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                    <div>
                        <span class="text-gray-500 dark:text-gray-400">Assigned To</span>
                        <div class="text-gray-800 dark:text-gray-200 font-medium">{{ workOrder.assigned_to_user?.name || 'Unassigned' }}</div>
                    </div>
                    <div>
                        <span class="text-gray-500 dark:text-gray-400">Created By</span>
                        <div class="text-gray-800 dark:text-gray-200 font-medium">{{ workOrder.creator?.name || '-' }}</div>
                    </div>
                    <div>
                        <span class="text-gray-500 dark:text-gray-400">Started</span>
                        <div class="text-gray-800 dark:text-gray-200 font-medium">{{ formatDate(workOrder.started_at) }}</div>
                    </div>
                    <div>
                        <span class="text-gray-500 dark:text-gray-400">Completed</span>
                        <div class="text-gray-800 dark:text-gray-200 font-medium">{{ formatDate(workOrder.completed_at) }}</div>
                    </div>
                </div>

                <!-- Items Table -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden">
                    <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700">
                        <h3 class="text-sm font-medium text-gray-700 dark:text-gray-300">Items ({{ workOrder.items?.length || 0 }})</h3>
                    </div>
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-900">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Product</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">SKU</th>
                                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Qty</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Status</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Bin</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Timestamps</th>
                                <th v-if="isActionable" class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            <tr v-for="item in workOrder.items" :key="item.id" class="hover:bg-gray-50 dark:hover:bg-gray-750">
                                <td class="px-4 py-3">
                                    <div class="text-sm font-medium text-gray-800 dark:text-gray-200">{{ item.product?.name || 'Unknown' }}</div>
                                    <div v-if="item.product_variation" class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                        Variation: {{ item.product_variation.sku }}
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-400 font-mono">
                                    {{ item.product?.sku || '-' }}
                                </td>
                                <td class="px-4 py-3 text-sm text-center text-gray-700 dark:text-gray-300">
                                    {{ item.quantity }}
                                </td>
                                <td class="px-4 py-3">
                                    <span :class="itemStatusBadgeClasses[item.status]" class="px-2 py-0.5 text-xs font-medium rounded-full">
                                        {{ itemStatusLabels[item.status] }}
                                    </span>
                                    <div v-if="item.skip_reason" class="text-xs text-red-500 mt-0.5">{{ item.skip_reason }}</div>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                                    <template v-if="item.bin">
                                        <div class="font-mono text-xs">{{ item.bin.zone?.code }}-{{ item.bin.code || item.bin.id }}</div>
                                        <div v-if="item.bin_verified" class="text-xs text-green-600">Verified</div>
                                    </template>
                                    <template v-else-if="isActionable && (item.status === 'label_printed' || item.status === 'pending')">
                                        <div class="flex items-center gap-1">
                                            <select
                                                v-model="binSelections[item.id]"
                                                class="rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 text-xs py-1 w-40"
                                            >
                                                <option value="">Select bin...</option>
                                                <option
                                                    v-for="bin in bins"
                                                    :key="bin.id"
                                                    :value="bin.id"
                                                    :disabled="bin.at_capacity"
                                                >
                                                    {{ bin.code }} ({{ bin.capacity_display }}){{ bin.at_capacity ? ' - FULL' : '' }}
                                                </option>
                                            </select>
                                            <button
                                                v-if="binSelections[item.id]"
                                                @click="assignBin(item)"
                                                class="px-2 py-1 bg-green-600 text-white text-xs rounded hover:bg-green-500"
                                            >
                                                Shelve
                                            </button>
                                        </div>
                                    </template>
                                    <span v-else class="text-gray-400 text-xs">-</span>
                                </td>
                                <td class="px-4 py-3 text-xs text-gray-500 dark:text-gray-400">
                                    <div v-if="item.label_printed_at">Labeled: {{ formatDate(item.label_printed_at) }}</div>
                                    <div v-if="item.shelved_at">Shelved: {{ formatDate(item.shelved_at) }}</div>
                                </td>
                                <td v-if="isActionable" class="px-4 py-3 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <button
                                            v-if="item.status === 'pending'"
                                            @click="markItemLabeled(item)"
                                            class="px-2 py-1 bg-blue-600 text-white text-xs rounded hover:bg-blue-500"
                                        >
                                            Mark Labeled
                                        </button>
                                        <button
                                            v-if="item.status === 'pending' || item.status === 'label_printed'"
                                            @click="showSkipModal = item.id"
                                            class="px-2 py-1 bg-gray-500 text-white text-xs rounded hover:bg-gray-400"
                                        >
                                            Skip
                                        </button>
                                    </div>
                                    <!-- Inline skip reason -->
                                    <div v-if="showSkipModal === item.id" class="mt-2 flex items-center gap-1">
                                        <input
                                            v-model="skipReasons[item.id]"
                                            type="text"
                                            placeholder="Reason..."
                                            class="rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 text-xs py-1 w-32"
                                            @keyup.enter="skipItem(item)"
                                        />
                                        <button @click="skipItem(item)" class="px-2 py-1 bg-red-600 text-white text-xs rounded">Go</button>
                                        <button @click="showSkipModal = null" class="px-2 py-1 text-gray-500 text-xs">X</button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="!workOrder.items || workOrder.items.length === 0">
                                <td :colspan="isActionable ? 7 : 6" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">
                                    No items in this work order.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Notes -->
                <div v-if="workOrder.notes" class="bg-white dark:bg-gray-800 rounded-lg shadow p-4 mt-6">
                    <h3 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Notes</h3>
                    <p class="text-sm text-gray-600 dark:text-gray-400">{{ workOrder.notes }}</p>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
