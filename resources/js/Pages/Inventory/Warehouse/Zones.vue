<script setup>
import { ref, reactive, watch } from 'vue'
import { router, Head, Link } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { route } from 'ziggy-js'

const props = defineProps({
    locations: { type: Array, default: () => [] },
    selectedLocationId: { type: String, default: '' },
    zones: { type: Array, default: () => [] },
    stats: { type: Object, default: () => ({}) },
    zoneTypes: { type: Object, default: () => ({}) },
})

const selectedLocation = ref(props.selectedLocationId)
const showCreateForm = ref(false)
const editingZone = ref(null)

const newZone = reactive({
    location_id: props.selectedLocationId,
    name: '',
    code: '',
    type: 'storage',
    sort_order: 0,
})

const generateBinsForm = reactive({
    zoneId: null,
    aisles: 1,
    racks: 1,
    shelves: 1,
})
const showGenerateBins = ref(false)

watch(selectedLocation, (val) => {
    router.get(route('inventory.warehouse.zones.index'), { location_id: val }, {
        preserveState: true,
        preserveScroll: true,
    })
})

function createZone() {
    newZone.location_id = selectedLocation.value
    router.post(route('inventory.warehouse.zones.store'), newZone, {
        preserveScroll: true,
        onSuccess: () => {
            showCreateForm.value = false
            Object.assign(newZone, { name: '', code: '', type: 'storage', sort_order: 0 })
        },
    })
}

function updateZone(zone) {
    router.put(route('inventory.warehouse.zones.update', zone.id), zone, {
        preserveScroll: true,
        onSuccess: () => { editingZone.value = null },
    })
}

function deleteZone(zone) {
    if (confirm(`Delete zone "${zone.name}"? It must have no bins.`)) {
        router.delete(route('inventory.warehouse.zones.destroy', zone.id), { preserveScroll: true })
    }
}

function openGenerateBins(zone) {
    generateBinsForm.zoneId = zone.id
    generateBinsForm.aisles = 1
    generateBinsForm.racks = 1
    generateBinsForm.shelves = 1
    showGenerateBins.value = true
}

function submitGenerateBins() {
    router.post(route('inventory.warehouse.zones.generate-bins', generateBinsForm.zoneId), {
        aisles: generateBinsForm.aisles,
        racks: generateBinsForm.racks,
        shelves: generateBinsForm.shelves,
    }, {
        preserveScroll: true,
        onSuccess: () => { showGenerateBins.value = false },
    })
}
</script>

<template>
    <Head title="Warehouse Zones" />

    <AppLayout>
        <div class="py-6">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <!-- Page header -->
                <div class="flex items-center justify-between flex-wrap gap-3 mb-6">
                    <div class="flex items-center gap-4 flex-wrap">
                        <h1 class="font-semibold text-2xl text-gray-800 dark:text-gray-200">Warehouse Layout</h1>
                        <select v-model="selectedLocation" class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                            <option v-for="loc in locations" :key="loc.id" :value="loc.id">{{ loc.name }}</option>
                        </select>
                    </div>
                    <div class="flex items-center gap-3">
                        <Link :href="route('inventory.warehouse.bins.index', { location_id: selectedLocation })" class="inline-flex items-center px-4 py-2 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md font-semibold text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest hover:bg-gray-50 dark:hover:bg-gray-600 transition">
                            View Bins
                        </Link>
                        <button @click="showCreateForm = true" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-500 transition">
                            New Zone
                        </button>
                    </div>
                </div>

                <!-- Stats -->
                <div class="grid grid-cols-3 gap-4 mb-6">
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
                        <div class="text-sm text-gray-500 dark:text-gray-400">Zones</div>
                        <div class="text-2xl font-bold text-gray-900 dark:text-white">{{ stats.total_zones }}</div>
                    </div>
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
                        <div class="text-sm text-gray-500 dark:text-gray-400">Total Bins</div>
                        <div class="text-2xl font-bold text-indigo-600">{{ stats.total_bins }}</div>
                    </div>
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
                        <div class="text-sm text-gray-500 dark:text-gray-400">Active Bins</div>
                        <div class="text-2xl font-bold text-emerald-600">{{ stats.active_bins }}</div>
                    </div>
                </div>

                <!-- Create Zone Form -->
                <div v-if="showCreateForm" class="bg-white dark:bg-gray-800 shadow rounded-lg p-6 mb-6 border-2 border-indigo-500">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">New Zone</h3>
                    <form @submit.prevent="createZone" class="grid grid-cols-4 gap-4">
                        <div>
                            <label class="block text-xs text-gray-500 dark:text-gray-400">Name</label>
                            <input v-model="newZone.name" type="text" required class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm" />
                        </div>
                        <div>
                            <label class="block text-xs text-gray-500 dark:text-gray-400">Code</label>
                            <input v-model="newZone.code" type="text" required maxlength="10" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm" placeholder="e.g., A" />
                        </div>
                        <div>
                            <label class="block text-xs text-gray-500 dark:text-gray-400">Type</label>
                            <select v-model="newZone.type" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm">
                                <option v-for="(label, value) in zoneTypes" :key="value" :value="value">{{ label }}</option>
                            </select>
                        </div>
                        <div class="flex items-end gap-2">
                            <button type="submit" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-500 transition">Create</button>
                            <button type="button" @click="showCreateForm = false" class="inline-flex items-center px-4 py-2 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md text-xs text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600 transition">Cancel</button>
                        </div>
                    </form>
                </div>

                <!-- Generate Bins Modal -->
                <div v-if="showGenerateBins" class="bg-white dark:bg-gray-800 shadow rounded-lg p-6 mb-6 border-2 border-emerald-500">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Generate Bins</h3>
                    <form @submit.prevent="submitGenerateBins" class="grid grid-cols-4 gap-4">
                        <div>
                            <label class="block text-xs text-gray-500 dark:text-gray-400">Aisles</label>
                            <input v-model.number="generateBinsForm.aisles" type="number" min="1" max="50" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm" />
                        </div>
                        <div>
                            <label class="block text-xs text-gray-500 dark:text-gray-400">Racks per Aisle</label>
                            <input v-model.number="generateBinsForm.racks" type="number" min="1" max="50" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm" />
                        </div>
                        <div>
                            <label class="block text-xs text-gray-500 dark:text-gray-400">Shelves per Rack</label>
                            <input v-model.number="generateBinsForm.shelves" type="number" min="1" max="20" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm" />
                        </div>
                        <div class="flex items-end gap-2">
                            <button type="submit" class="inline-flex items-center px-4 py-2 bg-emerald-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-emerald-500 transition">Generate</button>
                            <button type="button" @click="showGenerateBins = false" class="inline-flex items-center px-4 py-2 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md text-xs text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600 transition">Cancel</button>
                        </div>
                    </form>
                    <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                        This will create {{ generateBinsForm.aisles * generateBinsForm.racks * generateBinsForm.shelves }} bins.
                    </p>
                </div>

                <!-- Zones List -->
                <div class="space-y-4">
                    <div
                        v-for="zone in zones"
                        :key="zone.id"
                        @click="router.visit(route('inventory.warehouse.zones.show', zone.id))"
                        class="bg-white dark:bg-gray-800 shadow rounded-lg p-6 hover:ring-2 hover:ring-indigo-500 cursor-pointer transition"
                    >
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-4 flex-wrap">
                                <div class="text-2xl font-bold text-indigo-600 dark:text-indigo-400">{{ zone.code }}</div>
                                <div>
                                    <div class="font-medium text-gray-900 dark:text-gray-100">{{ zone.name }}</div>
                                    <div class="text-sm text-gray-500 dark:text-gray-400 capitalize">{{ zone.type }} | {{ zone.bins_count }} bins ({{ zone.active_bins_count }} active)</div>
                                </div>
                                <span v-if="!zone.is_active" class="text-xs px-2 py-0.5 rounded bg-red-100 text-red-700 dark:bg-red-900 dark:text-red-300">Inactive</span>
                                <span class="text-xs px-2 py-0.5 rounded-full bg-indigo-50 text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300 font-medium">
                                    {{ (zone.products_count ?? 0).toLocaleString() }} products
                                </span>
                                <span class="text-xs px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300 font-medium">
                                    {{ (zone.total_qty ?? 0).toLocaleString() }} units
                                </span>
                            </div>
                            <div class="flex items-center gap-2" @click.stop>
                                <button type="button" @click="openGenerateBins(zone)" class="text-sm text-emerald-600 dark:text-emerald-400 hover:underline">Generate Bins</button>
                                <button type="button" @click="deleteZone(zone)" class="text-sm text-red-600 dark:text-red-400 hover:underline">Delete</button>
                                <span class="text-gray-400 dark:text-gray-500 ml-2">&rsaquo;</span>
                            </div>
                        </div>
                    </div>
                    <div v-if="zones.length === 0" class="bg-white dark:bg-gray-800 shadow rounded-lg p-12 text-center text-gray-500 dark:text-gray-400">
                        No zones configured for this location. Create a zone to start organizing your warehouse.
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
