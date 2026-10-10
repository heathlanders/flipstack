<script setup>
import { reactive, watch } from 'vue'
import { router, Head, Link } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { route } from 'ziggy-js'

const props = defineProps({
    bins: { type: Object, required: true },
    locations: { type: Array, default: () => [] },
    zones: { type: Array, default: () => [] },
    selectedLocationId: { type: String, default: '' },
    filters: { type: Object, default: () => ({}) },
})

const currentFilters = reactive({
    search: props.filters.search || '',
    zone_id: props.filters.zone_id || '',
    location_id: props.selectedLocationId || '',
    with_stock: props.filters.with_stock ? '1' : '',
    sort: props.filters.sort || 'code',
    dir: props.filters.dir || 'asc',
})

function applyFilters() {
    router.get(route('inventory.warehouse.bins.index'), currentFilters, {
        preserveState: true,
        preserveScroll: true,
    })
}

function sortBy(col) {
    if (currentFilters.sort === col) {
        currentFilters.dir = currentFilters.dir === 'asc' ? 'desc' : 'asc'
    } else {
        currentFilters.sort = col
        currentFilters.dir = 'asc'
    }
    applyFilters()
}

function sortIcon(col) {
    if (currentFilters.sort !== col) return ''
    return currentFilters.dir === 'asc' ? ' ↑' : ' ↓'
}

let searchTimeout = null
watch(() => currentFilters.search, () => {
    clearTimeout(searchTimeout)
    searchTimeout = setTimeout(applyFilters, 300)
})
watch(() => currentFilters.zone_id, applyFilters)
watch(() => currentFilters.location_id, applyFilters)
watch(() => currentFilters.with_stock, applyFilters)
</script>

<template>
    <Head title="Warehouse Bins" />

    <AppLayout>
        <div class="py-6">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <!-- Breadcrumb -->
                <nav class="text-sm text-gray-500 dark:text-gray-400 mb-3 flex items-center gap-2">
                    <Link :href="route('inventory.warehouse.zones.index', { location_id: selectedLocationId })" class="hover:text-indigo-600 dark:hover:text-indigo-400">
                        Warehouse
                    </Link>
                    <span>/</span>
                    <Link :href="route('inventory.warehouse.zones.index', { location_id: selectedLocationId })" class="hover:text-indigo-600 dark:hover:text-indigo-400">
                        Zones
                    </Link>
                    <span>/</span>
                    <span class="text-gray-900 dark:text-gray-200 font-medium">All bins</span>
                </nav>

                <!-- Page header -->
                <div class="flex items-center justify-between flex-wrap gap-3 mb-6">
                    <h1 class="font-semibold text-2xl text-gray-800 dark:text-gray-200">Warehouse Bins</h1>
                    <Link :href="route('inventory.warehouse.zones.index', { location_id: selectedLocationId })" class="text-sm text-gray-500 dark:text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-400">
                        &larr; Back to zones
                    </Link>
                </div>

                <!-- Filters -->
                <div class="flex gap-4 mb-6">
                    <select v-model="currentFilters.location_id" class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option v-for="loc in locations" :key="loc.id" :value="loc.id">{{ loc.name }}</option>
                    </select>
                    <select v-model="currentFilters.zone_id" class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">All Zones</option>
                        <option v-for="zone in zones" :key="zone.id" :value="zone.id">{{ zone.code }} - {{ zone.name }}</option>
                    </select>
                    <input
                        v-model="currentFilters.search"
                        type="text"
                        placeholder="Search bins..."
                        class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    />
                    <label class="inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                        <input
                            type="checkbox"
                            :checked="currentFilters.with_stock === '1'"
                            @change="currentFilters.with_stock = $event.target.checked ? '1' : ''"
                            class="rounded border-gray-300 dark:border-gray-600 text-indigo-600 focus:ring-indigo-500"
                        />
                        With stock only
                    </label>
                </div>

                <!-- Bins Table -->
                <div class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th @click="sortBy('code')" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider cursor-pointer select-none hover:text-indigo-600 dark:hover:text-indigo-400">Bin Code{{ sortIcon('code') }}</th>
                                <th @click="sortBy('zone')" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider cursor-pointer select-none hover:text-indigo-600 dark:hover:text-indigo-400">Zone{{ sortIcon('zone') }}</th>
                                <th @click="sortBy('barcode')" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider cursor-pointer select-none hover:text-indigo-600 dark:hover:text-indigo-400">Barcode{{ sortIcon('barcode') }}</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Location</th>
                                <th @click="sortBy('inventory_levels_count')" class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider cursor-pointer select-none hover:text-indigo-600 dark:hover:text-indigo-400">Products{{ sortIcon('inventory_levels_count') }}</th>
                                <th @click="sortBy('total_qty')" class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider cursor-pointer select-none hover:text-indigo-600 dark:hover:text-indigo-400">Qty On Hand{{ sortIcon('total_qty') }}</th>
                                <th @click="sortBy('max_capacity')" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider cursor-pointer select-none hover:text-indigo-600 dark:hover:text-indigo-400">Capacity{{ sortIcon('max_capacity') }}</th>
                                <th @click="sortBy('is_active')" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider cursor-pointer select-none hover:text-indigo-600 dark:hover:text-indigo-400">Status{{ sortIcon('is_active') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            <tr
                                v-for="bin in bins.data"
                                :key="bin.id"
                                @click="router.visit(route('inventory.warehouse.bins.show', bin.id))"
                                class="hover:bg-gray-50 dark:hover:bg-gray-700/50 cursor-pointer"
                            >
                                <td class="px-6 py-4 text-sm font-mono font-medium text-indigo-600 dark:text-indigo-400">{{ bin.code }}</td>
                                <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-400">
                                    <span class="font-medium">{{ bin.zone?.code }}</span> - {{ bin.zone?.name }}
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400 font-mono">{{ bin.barcode }}</td>
                                <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                    {{ [bin.aisle ? `Aisle ${bin.aisle}` : null, bin.rack ? `Rack ${bin.rack}` : null, bin.shelf ? `Shelf ${bin.shelf}` : null].filter(Boolean).join(', ') || '-' }}
                                </td>
                                <td class="px-6 py-4 text-sm text-right text-gray-900 dark:text-gray-100">{{ bin.inventory_levels_count ?? 0 }}</td>
                                <td class="px-6 py-4 text-sm text-right text-gray-900 dark:text-gray-100 font-medium">{{ Number(bin.total_qty ?? 0).toLocaleString() }}</td>
                                <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-400">{{ bin.max_capacity ?? '-' }}</td>
                                <td class="px-6 py-4">
                                    <span :class="bin.is_active ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900 dark:text-emerald-300' : 'bg-red-100 text-red-700 dark:bg-red-900 dark:text-red-300'"
                                        class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium">
                                        {{ bin.is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                            </tr>
                            <tr v-if="bins.data.length === 0">
                                <td colspan="8" class="px-6 py-12 text-center text-gray-500 dark:text-gray-400">
                                    No bins found. Generate bins from the Zones page.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div v-if="bins.last_page > 1" class="mt-4 flex justify-center">
                    <nav class="flex gap-1">
                        <Link v-for="link in bins.links" :key="link.label" :href="link.url || '#'" v-html="link.label"
                            class="px-3 py-1 rounded text-sm"
                            :class="link.active ? 'bg-indigo-600 text-white' : 'bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-600'" />
                    </nav>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
