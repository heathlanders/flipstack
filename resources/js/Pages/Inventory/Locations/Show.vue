<template>
    <AppLayout :title="location.name">
        <template #header>
            <div class="flex justify-between items-center">
                <div class="flex items-center space-x-4">
                    <Link
                        :href="route('inventory.locations.index')"
                        class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200"
                    >
                        <i class="fas fa-arrow-left"></i>
                    </Link>
                    <div>
                        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                            {{ location.name }}
                        </h2>
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ location.code }}</p>
                    </div>
                </div>
                <div class="flex space-x-3">
                    <Link
                        :href="route('inventory.locations.edit', location.id)"
                        class="inline-flex items-center px-4 py-2 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md font-semibold text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest shadow-sm hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition ease-in-out duration-150"
                    >
                        <i class="fas fa-edit mr-2"></i>
                        Edit
                    </Link>
                    <button
                        type="button"
                        @click="confirmDelete"
                        class="inline-flex items-center px-4 py-2 bg-red-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-red-700 focus:bg-red-700 active:bg-red-900 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 transition ease-in-out duration-150"
                    >
                        <i class="fas fa-trash mr-2"></i>
                        Delete
                    </button>
                </div>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <!-- Main Info -->
                    <div class="lg:col-span-2 space-y-6">
                        <!-- Location Details -->
                        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm">
                            <div class="p-6">
                                <div class="flex items-center justify-between mb-6">
                                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Location Details</h3>
                                    <span
                                        class="px-3 py-1 text-sm font-medium rounded-full"
                                        :class="statusClass"
                                    >
                                        {{ location.is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </div>

                                <dl class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Name</dt>
                                        <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ location.name }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Code</dt>
                                        <dd class="mt-1 text-sm text-gray-900 dark:text-white font-mono">{{ location.code }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Type</dt>
                                        <dd class="mt-1 text-sm text-gray-900 dark:text-white capitalize">{{ formatType(location.type) }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Default Location</dt>
                                        <dd class="mt-1 text-sm text-gray-900 dark:text-white">
                                            <span v-if="location.is_default" class="text-blue-600 dark:text-blue-400">
                                                <i class="fas fa-star mr-1"></i> Yes
                                            </span>
                                            <span v-else>No</span>
                                        </dd>
                                    </div>
                                </dl>
                            </div>
                        </div>

                        <!-- Address Card -->
                        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm">
                            <div class="p-6">
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Address</h3>

                                <div v-if="hasAddress" class="space-y-2 text-sm text-gray-700 dark:text-gray-300">
                                    <p v-if="location.address_line1">{{ location.address_line1 }}</p>
                                    <p v-if="location.address_line2">{{ location.address_line2 }}</p>
                                    <p>
                                        <span v-if="location.city">{{ location.city }}</span>
                                        <span v-if="location.city && location.state">, </span>
                                        <span v-if="location.state">{{ location.state }}</span>
                                        <span v-if="location.postal_code"> {{ location.postal_code }}</span>
                                    </p>
                                    <p v-if="location.country">{{ location.country }}</p>
                                </div>
                                <p v-else class="text-sm text-gray-500 dark:text-gray-400">No address specified</p>
                            </div>
                        </div>

                        <!-- Contact Card -->
                        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm">
                            <div class="p-6">
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Contact Information</h3>

                                <div v-if="hasContact" class="space-y-3">
                                    <div v-if="location.contact_person" class="flex items-center text-sm">
                                        <i class="fas fa-user w-6 text-gray-400"></i>
                                        <span class="text-gray-900 dark:text-white">{{ location.contact_person }}</span>
                                    </div>
                                    <div v-if="location.email" class="flex items-center text-sm">
                                        <i class="fas fa-envelope w-6 text-gray-400"></i>
                                        <a :href="`mailto:${location.email}`" class="text-blue-600 dark:text-blue-400 hover:underline">
                                            {{ location.email }}
                                        </a>
                                    </div>
                                    <div v-if="location.phone" class="flex items-center text-sm">
                                        <i class="fas fa-phone w-6 text-gray-400"></i>
                                        <a :href="`tel:${location.phone}`" class="text-blue-600 dark:text-blue-400 hover:underline">
                                            {{ location.phone }}
                                        </a>
                                    </div>
                                </div>
                                <p v-else class="text-sm text-gray-500 dark:text-gray-400">No contact information specified</p>
                            </div>
                        </div>

                        <!-- Zones & Bins Panel -->
                        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm">
                            <div class="p-6">
                                <div class="flex items-center justify-between mb-4">
                                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Zones & Bins</h3>
                                    <Link
                                        :href="layoutUrl"
                                        class="text-sm text-blue-600 dark:text-blue-400 hover:underline"
                                    >
                                        View Warehouse Layout <i class="fas fa-arrow-right ml-1"></i>
                                    </Link>
                                </div>

                                <div class="flex flex-wrap gap-4 text-sm mb-4">
                                    <div class="flex items-baseline space-x-1">
                                        <span class="text-2xl font-semibold text-gray-900 dark:text-white">{{ bins_summary.zones_count }}</span>
                                        <span class="text-gray-500 dark:text-gray-400">zones</span>
                                    </div>
                                    <div class="flex items-baseline space-x-1">
                                        <span class="text-2xl font-semibold text-gray-900 dark:text-white">{{ bins_summary.bins_count }}</span>
                                        <span class="text-gray-500 dark:text-gray-400">bins</span>
                                    </div>
                                    <div class="flex items-baseline space-x-1">
                                        <span class="text-2xl font-semibold text-gray-900 dark:text-white">{{ bins_summary.occupied_bins }}</span>
                                        <span class="text-gray-500 dark:text-gray-400">occupied</span>
                                    </div>
                                </div>

                                <div v-if="bins_summary.zones?.length" class="overflow-x-auto">
                                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                        <thead>
                                            <tr>
                                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Zone</th>
                                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Code</th>
                                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Type</th>
                                                <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Bins</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                            <tr v-for="zone in bins_summary.zones" :key="zone.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                                <td class="px-3 py-2 text-sm text-gray-900 dark:text-white">
                                                    <Link
                                                        :href="route('inventory.warehouse.zones.show', zone.id)"
                                                        class="hover:text-blue-600 dark:hover:text-blue-400"
                                                    >
                                                        {{ zone.name }}
                                                        <span v-if="!zone.is_active" class="ml-2 text-xs text-gray-400">(inactive)</span>
                                                    </Link>
                                                </td>
                                                <td class="px-3 py-2 text-sm text-gray-700 dark:text-gray-300 font-mono">{{ zone.code }}</td>
                                                <td class="px-3 py-2 text-sm text-gray-700 dark:text-gray-300 capitalize">{{ zone.type }}</td>
                                                <td class="px-3 py-2 text-sm text-gray-900 dark:text-white text-right">{{ zone.bins_count }}</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                                <div v-else class="text-center py-6 text-sm text-gray-500 dark:text-gray-400">
                                    <p class="mb-3">No zones configured for this location.</p>
                                    <Link
                                        :href="layoutUrl"
                                        class="inline-flex items-center px-3 py-1.5 bg-blue-600 text-white text-xs font-semibold rounded-md hover:bg-blue-700"
                                    >
                                        Configure in Warehouse Layout
                                    </Link>
                                </div>
                            </div>
                        </div>

                        <!-- Top Products Panel -->
                        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm">
                            <div class="p-6">
                                <div class="flex items-center justify-between mb-4">
                                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Top Products at this Location</h3>
                                    <div class="flex items-center space-x-1 text-xs">
                                        <button
                                            type="button"
                                            @click="topSort = 'qty'"
                                            :class="topSort === 'qty'
                                                ? 'bg-blue-600 text-white'
                                                : 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300'"
                                            class="px-2 py-1 rounded-md font-medium"
                                        >
                                            Qty
                                        </button>
                                        <button
                                            type="button"
                                            @click="topSort = 'value'"
                                            :class="topSort === 'value'
                                                ? 'bg-blue-600 text-white'
                                                : 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300'"
                                            class="px-2 py-1 rounded-md font-medium"
                                        >
                                            Value
                                        </button>
                                    </div>
                                </div>

                                <div v-if="sortedTopProducts.length" class="overflow-x-auto">
                                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                        <thead>
                                            <tr>
                                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Product</th>
                                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Bin</th>
                                                <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">On Hand</th>
                                                <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Reserved</th>
                                                <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Value</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                            <tr v-for="row in sortedTopProducts" :key="row.product_id" class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                                <td class="px-3 py-2 text-sm">
                                                    <Link
                                                        :href="route('inventory.products.show', row.product_id)"
                                                        class="text-blue-600 dark:text-blue-400 hover:underline font-medium"
                                                    >
                                                        {{ row.product_name || '—' }}
                                                    </Link>
                                                    <div class="text-xs text-gray-500 dark:text-gray-400 font-mono">{{ row.product_sku }}</div>
                                                </td>
                                                <td class="px-3 py-2 text-sm text-gray-700 dark:text-gray-300">
                                                    <span v-if="row.bin_code">
                                                        <span v-if="row.zone_name" class="text-gray-500">{{ row.zone_name }} · </span>
                                                        <span class="font-mono">{{ row.bin_code }}</span>
                                                    </span>
                                                    <span v-else class="text-gray-400">—</span>
                                                </td>
                                                <td class="px-3 py-2 text-sm text-gray-900 dark:text-white text-right font-medium">{{ row.quantity_on_hand }}</td>
                                                <td class="px-3 py-2 text-sm text-gray-700 dark:text-gray-300 text-right">{{ row.quantity_reserved }}</td>
                                                <td class="px-3 py-2 text-sm text-gray-900 dark:text-white text-right">${{ formatCurrency(row.value) }}</td>
                                            </tr>
                                        </tbody>
                                    </table>

                                    <div class="mt-4 text-right">
                                        <Link
                                            :href="inventoryUrl"
                                            class="text-sm text-blue-600 dark:text-blue-400 hover:underline"
                                        >
                                            View All Inventory <i class="fas fa-arrow-right ml-1"></i>
                                        </Link>
                                    </div>
                                </div>
                                <p v-else class="text-center py-6 text-sm text-gray-500 dark:text-gray-400">
                                    No inventory at this location yet.
                                </p>
                            </div>
                        </div>

                        <!-- Recent Movements Panel -->
                        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm">
                            <div class="p-6">
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Recent Movements</h3>

                                <div v-if="recent_movements.length" class="overflow-x-auto">
                                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                        <thead>
                                            <tr>
                                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Date</th>
                                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Type</th>
                                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Product</th>
                                                <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Qty</th>
                                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">User</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                            <tr v-for="mv in recent_movements" :key="mv.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                                <td class="px-3 py-2 text-xs text-gray-700 dark:text-gray-300 whitespace-nowrap">{{ formatDateShort(mv.created_at) }}</td>
                                                <td class="px-3 py-2 text-xs">
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium" :class="movementChipClass(mv.type)">
                                                        {{ mv.type_label }}
                                                    </span>
                                                </td>
                                                <td class="px-3 py-2 text-sm">
                                                    <Link
                                                        v-if="mv.product_id"
                                                        :href="route('inventory.products.show', mv.product_id)"
                                                        class="text-blue-600 dark:text-blue-400 hover:underline"
                                                    >
                                                        {{ mv.product_name || '—' }}
                                                    </Link>
                                                    <span v-else class="text-gray-400">—</span>
                                                </td>
                                                <td
                                                    class="px-3 py-2 text-sm text-right font-medium"
                                                    :class="mv.quantity >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'"
                                                >
                                                    {{ mv.quantity > 0 ? '+' : '' }}{{ mv.quantity }}
                                                </td>
                                                <td class="px-3 py-2 text-xs text-gray-700 dark:text-gray-300">{{ mv.user_name || 'System' }}</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                                <p v-else class="text-center py-6 text-sm text-gray-500 dark:text-gray-400">
                                    No recent movements.
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Sidebar -->
                    <div class="space-y-6">
                        <!-- Inventory Stats Card -->
                        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm">
                            <div class="p-6">
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Inventory Overview</h3>

                                <div class="space-y-4">
                                    <div class="flex justify-between items-center">
                                        <span class="text-sm text-gray-500 dark:text-gray-400">Total Products</span>
                                        <span class="text-lg font-semibold text-gray-900 dark:text-white">
                                            {{ inventory_stats?.total_products || 0 }}
                                        </span>
                                    </div>
                                    <div class="flex justify-between items-center">
                                        <span class="text-sm text-gray-500 dark:text-gray-400">Total Value</span>
                                        <span class="text-lg font-semibold text-gray-900 dark:text-white">
                                            ${{ formatCurrency(inventory_stats?.total_value || 0) }}
                                        </span>
                                    </div>
                                    <div class="flex justify-between items-center">
                                        <span class="text-sm text-gray-500 dark:text-gray-400">Inventory Levels</span>
                                        <span class="text-lg font-semibold text-gray-900 dark:text-white">
                                            {{ inventory_stats?.inventory_levels_count || 0 }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Quick Actions Card -->
                        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm">
                            <div class="p-6">
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Quick Actions</h3>

                                <div class="space-y-1">
                                    <Link
                                        :href="layoutUrl"
                                        class="block w-full text-left px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-md transition-colors"
                                    >
                                        <i class="fas fa-th w-6"></i>
                                        Warehouse Layout
                                    </Link>
                                    <Link
                                        :href="inventoryUrl"
                                        class="block w-full text-left px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-md transition-colors"
                                    >
                                        <i class="fas fa-boxes w-6"></i>
                                        View Inventory at Location
                                    </Link>
                                    <Link
                                        :href="transferUrl"
                                        class="block w-full text-left px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-md transition-colors"
                                    >
                                        <i class="fas fa-exchange-alt w-6"></i>
                                        Transfer Inventory
                                    </Link>
                                    <Link
                                        :href="analyticsUrl"
                                        class="block w-full text-left px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-md transition-colors"
                                    >
                                        <i class="fas fa-chart-line w-6"></i>
                                        View Analytics
                                    </Link>
                                </div>
                            </div>
                        </div>

                        <!-- Timestamps Card -->
                        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm">
                            <div class="p-6">
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Record Info</h3>

                                <div class="space-y-2 text-sm">
                                    <div class="flex justify-between">
                                        <span class="text-gray-500 dark:text-gray-400">Created</span>
                                        <span class="text-gray-900 dark:text-white">{{ formatDate(location.created_at) }}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-500 dark:text-gray-400">Updated</span>
                                        <span class="text-gray-900 dark:text-white">{{ formatDate(location.updated_at) }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Delete Confirmation Modal -->
        <ConfirmationModal
            :show="showDeleteModal"
            title="Delete Location"
            :message="`Are you sure you want to delete '${location.name}'? This action cannot be undone.`"
            confirm-text="Delete"
            confirm-class="bg-red-600 hover:bg-red-700"
            @confirm="deleteLocation"
            @cancel="showDeleteModal = false"
        />
    </AppLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { router, Link } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import ConfirmationModal from '@/Components/ConfirmationModal.vue'

const props = defineProps({
    location: {
        type: Object,
        required: true
    },
    inventory_stats: {
        type: Object,
        default: () => ({})
    },
    bins_summary: {
        type: Object,
        default: () => ({ zones_count: 0, bins_count: 0, occupied_bins: 0, zones: [] })
    },
    top_products: {
        type: Array,
        default: () => []
    },
    recent_movements: {
        type: Array,
        default: () => []
    }
})

const showDeleteModal = ref(false)
const topSort = ref('qty')

const statusClass = computed(() => {
    return props.location.is_active
        ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400'
        : 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400'
})

const hasAddress = computed(() => {
    return props.location.address_line1 || props.location.city || props.location.state
})

const hasContact = computed(() => {
    return props.location.contact_person || props.location.email || props.location.phone
})

const sortedTopProducts = computed(() => {
    const list = [...(props.top_products || [])]
    if (topSort.value === 'value') {
        list.sort((a, b) => Number(b.value || 0) - Number(a.value || 0))
    } else {
        list.sort((a, b) => Number(b.quantity_on_hand || 0) - Number(a.quantity_on_hand || 0))
    }
    return list
})

const layoutUrl = computed(() => route('inventory.warehouse.zones.index', { location_id: props.location.id }))
const inventoryUrl = computed(() => route('inventory.products.index', { location_ids: props.location.id }))
const transferUrl = computed(() => route('inventory.operations.transfer.form'))
const analyticsUrl = computed(() => route('inventory.locations.analytics', props.location.id))

const formatType = (type) => {
    const typeNames = {
        warehouse: 'Warehouse',
        store: 'Store',
        supplier: 'Supplier Location',
        customer: 'Customer Location',
        virtual: 'Virtual Location'
    }
    return typeNames[type] || type
}

const formatCurrency = (value) => {
    return new Intl.NumberFormat('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    }).format(Number(value) || 0)
}

const formatDate = (date) => {
    if (!date) return 'N/A'
    return new Date(date).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    })
}

const formatDateShort = (date) => {
    if (!date) return ''
    return new Date(date).toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    })
}

const movementChipClass = (type) => {
    const map = {
        purchase: 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400',
        sale: 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400',
        adjustment: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400',
        transfer_in: 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900/30 dark:text-indigo-400',
        transfer_out: 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900/30 dark:text-indigo-400',
        return: 'bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-400',
        damaged: 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400',
        lost: 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400',
        found: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-400',
        partout_consume: 'bg-orange-100 text-orange-800 dark:bg-orange-900/30 dark:text-orange-400',
        partout_produce: 'bg-teal-100 text-teal-800 dark:bg-teal-900/30 dark:text-teal-400',
    }
    return map[type] || 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300'
}

const confirmDelete = () => {
    showDeleteModal.value = true
}

const deleteLocation = () => {
    router.delete(route('inventory.locations.destroy', props.location.id), {
        onSuccess: () => {
            showDeleteModal.value = false
        }
    })
}
</script>
