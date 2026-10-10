<script setup>
import { reactive, watch } from 'vue'
import { router, Head, Link } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { route } from 'ziggy-js'

const props = defineProps({
    zone: { type: Object, required: true },
    bins: { type: Object, required: true },
    stats: { type: Object, default: () => ({}) },
    filters: { type: Object, default: () => ({}) },
})

const currentFilters = reactive({
    search: props.filters.search || '',
    with_stock: props.filters.with_stock ? '1' : '',
    sort: props.filters.sort || 'code',
    dir: props.filters.dir || 'asc',
})

function applyFilters() {
    router.get(route('inventory.warehouse.zones.show', props.zone.id), currentFilters, {
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
watch(() => currentFilters.with_stock, applyFilters)

function fillPct(bin) {
    if (!bin.max_capacity || bin.max_capacity <= 0) return null
    return Math.min(100, Math.round((bin.current_count / bin.max_capacity) * 100))
}
</script>

<template>
    <Head :title="`Zone ${zone.code}`" />

    <AppLayout>
        <div class="py-6">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <!-- Breadcrumb -->
                <nav class="text-sm text-gray-500 dark:text-gray-400 mb-3 flex items-center gap-2">
                    <Link :href="route('inventory.warehouse.zones.index', { location_id: zone.location_id })" class="hover:text-indigo-600 dark:hover:text-indigo-400">
                        Warehouse
                    </Link>
                    <span>/</span>
                    <Link :href="route('inventory.warehouse.zones.index', { location_id: zone.location_id })" class="hover:text-indigo-600 dark:hover:text-indigo-400">
                        Zones
                    </Link>
                    <span>/</span>
                    <span class="text-gray-900 dark:text-gray-200 font-medium">{{ zone.code }}</span>
                </nav>

                <!-- Zone banner -->
                <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6 mb-6 border-l-4 border-indigo-500">
                    <div class="flex items-center gap-5 flex-wrap">
                        <div class="text-4xl font-bold font-mono text-indigo-600 dark:text-indigo-400">{{ zone.code }}</div>
                        <div class="flex-1 min-w-[200px]">
                            <div class="text-xl font-semibold text-gray-900 dark:text-gray-100">{{ zone.name }}</div>
                            <div class="text-sm text-gray-500 dark:text-gray-400 capitalize mt-1">
                                <span class="inline-block px-2 py-0.5 rounded bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 text-xs mr-2">{{ zone.type }}</span>
                                <span v-if="zone.location?.name">{{ zone.location.name }}</span>
                                <span v-if="!zone.is_active" class="ml-2 px-2 py-0.5 rounded bg-red-100 text-red-700 dark:bg-red-900 dark:text-red-300 text-xs">Inactive</span>
                            </div>
                        </div>
                        <Link :href="route('inventory.warehouse.zones.index', { location_id: zone.location_id })" class="text-sm text-gray-500 dark:text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-400">
                            &larr; All zones
                        </Link>
                    </div>
                </div>

                <!-- Stats -->
                <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-6">
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
                        <div class="text-xs text-gray-500 dark:text-gray-400">Total Bins</div>
                        <div class="text-2xl font-bold text-gray-900 dark:text-white">{{ stats.total_bins?.toLocaleString() }}</div>
                    </div>
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
                        <div class="text-xs text-gray-500 dark:text-gray-400">Active Bins</div>
                        <div class="text-2xl font-bold text-emerald-600">{{ stats.active_bins?.toLocaleString() }}</div>
                    </div>
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
                        <div class="text-xs text-gray-500 dark:text-gray-400">Empty Bins</div>
                        <div class="text-2xl font-bold text-gray-400">{{ stats.empty_bins?.toLocaleString() }}</div>
                    </div>
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
                        <div class="text-xs text-gray-500 dark:text-gray-400">Distinct Products</div>
                        <div class="text-2xl font-bold text-indigo-600">{{ stats.distinct_products?.toLocaleString() }}</div>
                    </div>
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
                        <div class="text-xs text-gray-500 dark:text-gray-400">Units On Hand</div>
                        <div class="text-2xl font-bold text-gray-900 dark:text-white">{{ stats.total_qty?.toLocaleString() }}</div>
                    </div>
                </div>

                <!-- Filters -->
                <div class="flex flex-wrap items-center gap-3 mb-4">
                    <input
                        v-model="currentFilters.search"
                        type="text"
                        placeholder="Search bin code, label, barcode..."
                        class="flex-1 min-w-[240px] rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
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

                <!-- Bins Grid -->
                <div class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th @click="sortBy('code')" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider cursor-pointer select-none hover:text-indigo-600 dark:hover:text-indigo-400">Bin{{ sortIcon('code') }}</th>
                                <th @click="sortBy('label')" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider cursor-pointer select-none hover:text-indigo-600 dark:hover:text-indigo-400">Label{{ sortIcon('label') }}</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Position</th>
                                <th @click="sortBy('inventory_levels_count')" class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider cursor-pointer select-none hover:text-indigo-600 dark:hover:text-indigo-400">Products{{ sortIcon('inventory_levels_count') }}</th>
                                <th @click="sortBy('total_qty')" class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider cursor-pointer select-none hover:text-indigo-600 dark:hover:text-indigo-400">Qty On Hand{{ sortIcon('total_qty') }}</th>
                                <th @click="sortBy('max_capacity')" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider cursor-pointer select-none hover:text-indigo-600 dark:hover:text-indigo-400">Capacity{{ sortIcon('max_capacity') }}</th>
                                <th @click="sortBy('barcode')" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider cursor-pointer select-none hover:text-indigo-600 dark:hover:text-indigo-400">Barcode{{ sortIcon('barcode') }}</th>
                                <th class="px-6 py-3"></th>
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
                                <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-400">{{ bin.label || '-' }}</td>
                                <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                    {{ [bin.aisle ? `A${bin.aisle}` : null, bin.rack ? `R${bin.rack}` : null, bin.shelf ? `S${bin.shelf}` : null].filter(Boolean).join(' / ') || '-' }}
                                </td>
                                <td class="px-6 py-4 text-sm text-right text-gray-900 dark:text-gray-100">{{ bin.inventory_levels_count ?? 0 }}</td>
                                <td class="px-6 py-4 text-sm text-right text-gray-900 dark:text-gray-100 font-medium">{{ Number(bin.total_qty ?? 0).toLocaleString() }}</td>
                                <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                    <div v-if="bin.max_capacity" class="flex items-center gap-2">
                                        <div class="w-20 h-2 bg-gray-200 dark:bg-gray-700 rounded-full overflow-hidden">
                                            <div class="h-full bg-indigo-500" :style="{ width: fillPct(bin) + '%' }"></div>
                                        </div>
                                        <span class="text-xs">{{ bin.current_count }}/{{ bin.max_capacity }}</span>
                                    </div>
                                    <span v-else class="text-xs">{{ bin.current_count }} / &infin;</span>
                                </td>
                                <td class="px-6 py-4 text-xs font-mono text-gray-500 dark:text-gray-400">{{ bin.barcode || '-' }}</td>
                                <td class="px-6 py-4 text-right text-gray-400">&rsaquo;</td>
                            </tr>
                            <tr v-if="bins.data.length === 0">
                                <td colspan="8" class="px-6 py-12 text-center text-gray-500 dark:text-gray-400">
                                    No bins match these filters.
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
