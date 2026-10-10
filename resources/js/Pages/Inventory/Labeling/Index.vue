<script setup>
import { reactive, watch } from 'vue'
import { router, Head, Link } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { route } from 'ziggy-js'

const props = defineProps({
    workOrders: { type: Object, required: true },
    stats: { type: Object, default: () => ({}) },
    locations: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
})

const currentFilters = reactive({ ...props.filters })

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
    partially_completed: 'Partial',
}

const sourceLabels = {
    po_receipt: 'PO Receipt',
    intake_queue: 'Intake',
    csv_import: 'CSV Import',
    part_out: 'Part-Out',
    manual: 'Manual',
}

const sourceBadgeClasses = {
    po_receipt: 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-300',
    intake_queue: 'bg-cyan-100 text-cyan-800 dark:bg-cyan-900 dark:text-cyan-300',
    csv_import: 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300',
    part_out: 'bg-pink-100 text-pink-800 dark:bg-pink-900 dark:text-pink-300',
    manual: 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900 dark:text-indigo-300',
}

const priorityLabels = {
    urgent: 'Urgent',
    high: 'High',
    standard: 'Standard',
}

const formatDate = (date) => {
    if (!date) return '-'
    return new Date(date).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })
}

const progressPercent = (wo) => {
    if (wo.total_items === 0) return 0
    return Math.round((wo.shelved_items / wo.total_items) * 100)
}

let searchTimeout = null
watch(() => currentFilters.search, () => {
    clearTimeout(searchTimeout)
    searchTimeout = setTimeout(() => applyFilters(), 300)
})

function applyFilters() {
    router.get(route('inventory.labeling.index'), currentFilters, { preserveState: true, preserveScroll: true })
}

function filterByStatus(status) {
    currentFilters.status = status
    applyFilters()
}

function filterByLocation(locationId) {
    currentFilters.location_id = locationId
    applyFilters()
}

function clearFilters() {
    currentFilters.search = ''
    currentFilters.status = ''
    currentFilters.location_id = ''
    applyFilters()
}
</script>

<template>
    <Head title="Labeling Work Orders" />

    <AppLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200">Labeling Work Orders</h2>
                <Link
                    :href="route('inventory.labeling.create')"
                    class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-500 transition"
                >
                    New Work Order
                </Link>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <!-- Stats -->
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4 cursor-pointer hover:ring-2 hover:ring-blue-400" @click="filterByStatus('open')">
                        <div class="text-sm text-gray-500 dark:text-gray-400">Open</div>
                        <div class="text-2xl font-bold text-blue-600">{{ stats.open ?? 0 }}</div>
                    </div>
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4 cursor-pointer hover:ring-2 hover:ring-yellow-400" @click="filterByStatus('in_progress')">
                        <div class="text-sm text-gray-500 dark:text-gray-400">In Progress</div>
                        <div class="text-2xl font-bold text-yellow-600">{{ stats.in_progress ?? 0 }}</div>
                    </div>
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
                        <div class="text-sm text-gray-500 dark:text-gray-400">Completed Today</div>
                        <div class="text-2xl font-bold text-green-600">{{ stats.completed_today ?? 0 }}</div>
                    </div>
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
                        <div class="text-sm text-gray-500 dark:text-gray-400">Items Pending Label</div>
                        <div class="text-2xl font-bold text-gray-700 dark:text-gray-300">{{ stats.items_pending_label ?? 0 }}</div>
                    </div>
                </div>

                <!-- Filters -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow mb-6 p-4">
                    <div class="flex flex-wrap items-center gap-3">
                        <input
                            v-model="currentFilters.search"
                            type="text"
                            placeholder="Search work orders..."
                            class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 text-sm px-3 py-2 w-64"
                        />
                        <select
                            v-model="currentFilters.status"
                            @change="applyFilters()"
                            class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 text-sm px-3 py-2"
                        >
                            <option value="">All Statuses</option>
                            <option value="open">Open</option>
                            <option value="in_progress">In Progress</option>
                            <option value="completed">Completed</option>
                            <option value="partially_completed">Partially Completed</option>
                        </select>
                        <select
                            v-model="currentFilters.location_id"
                            @change="applyFilters()"
                            class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 text-sm px-3 py-2"
                        >
                            <option value="">All Locations</option>
                            <option v-for="loc in locations" :key="loc.id" :value="loc.id">{{ loc.name }}</option>
                        </select>
                        <button
                            v-if="currentFilters.search || currentFilters.status || currentFilters.location_id"
                            @click="clearFilters"
                            class="text-sm text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 underline"
                        >
                            Clear
                        </button>
                    </div>
                </div>

                <!-- Table -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-900">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">WO #</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Source</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Location</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Progress</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Status</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Assigned</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Created</th>
                                <th class="px-4 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            <tr
                                v-for="wo in workOrders.data"
                                :key="wo.id"
                                class="hover:bg-gray-50 dark:hover:bg-gray-750"
                            >
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <Link :href="route('inventory.labeling.show', wo.id)" class="text-indigo-600 dark:text-indigo-400 hover:underline font-medium text-sm">
                                        {{ wo.work_order_number }}
                                    </Link>
                                    <div v-if="wo.priority !== 'standard'" class="text-xs mt-0.5">
                                        <span class="text-red-600 font-medium" v-if="wo.priority === 'urgent'">{{ priorityLabels[wo.priority] }}</span>
                                        <span class="text-orange-600 font-medium" v-else>{{ priorityLabels[wo.priority] }}</span>
                                    </div>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <span :class="sourceBadgeClasses[wo.source_type]" class="px-2 py-0.5 text-xs font-medium rounded-full">
                                        {{ sourceLabels[wo.source_type] || wo.source_type }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300">
                                    {{ wo.location?.name || '-' }}
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <div class="flex items-center gap-2">
                                        <div class="w-24 bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                                            <div
                                                class="bg-green-500 h-2 rounded-full transition-all"
                                                :style="{ width: progressPercent(wo) + '%' }"
                                            ></div>
                                        </div>
                                        <span class="text-xs text-gray-500 dark:text-gray-400">
                                            {{ wo.shelved_items }}/{{ wo.total_items }}
                                        </span>
                                    </div>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <span :class="statusBadgeClasses[wo.status]" class="px-2 py-0.5 text-xs font-medium rounded-full">
                                        {{ statusLabels[wo.status] || wo.status }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300">
                                    {{ wo.assigned_to_user?.name || '-' }}
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                    {{ formatDate(wo.created_at) }}
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-right">
                                    <Link :href="route('inventory.labeling.show', wo.id)" class="text-indigo-600 dark:text-indigo-400 hover:underline text-sm">
                                        View
                                    </Link>
                                </td>
                            </tr>
                            <tr v-if="workOrders.data.length === 0">
                                <td colspan="8" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">
                                    No labeling work orders found.
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <!-- Pagination -->
                    <div v-if="workOrders.links && workOrders.last_page > 1" class="px-4 py-3 border-t border-gray-200 dark:border-gray-700 flex justify-between items-center">
                        <span class="text-sm text-gray-500 dark:text-gray-400">
                            Showing {{ workOrders.from }}-{{ workOrders.to }} of {{ workOrders.total }}
                        </span>
                        <div class="flex gap-1">
                            <template v-for="link in workOrders.links" :key="link.label">
                                <Link
                                    v-if="link.url"
                                    :href="link.url"
                                    class="px-3 py-1 text-sm rounded border dark:border-gray-600"
                                    :class="link.active ? 'bg-indigo-600 text-white border-indigo-600' : 'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700'"
                                    v-html="link.label"
                                    preserve-state
                                    preserve-scroll
                                />
                                <span v-else class="px-3 py-1 text-sm text-gray-400 dark:text-gray-500" v-html="link.label" />
                            </template>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
