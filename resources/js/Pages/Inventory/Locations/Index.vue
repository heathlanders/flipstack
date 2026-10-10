<template>
    <AppLayout
        title="Inventory Locations"
        page-title="Inventory Locations"
        :page-actions="pageActions"
    >

        <div class="py-6">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <!-- Stats Cards -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                    <StatCard
                        title="Total Locations"
                        :value="stats.total"
                        icon="fas fa-map-marker-alt"
                        color="blue"
                    />
                    <StatCard
                        title="Active Locations"
                        :value="stats.active"
                        icon="fas fa-check-circle"
                        color="green"
                    />
                    <StatCard
                        title="Warehouses"
                        :value="stats.warehouses"
                        icon="fas fa-warehouse"
                        color="purple"
                    />
                    <StatCard
                        title="Stores"
                        :value="stats.stores"
                        icon="fas fa-store"
                        color="orange"
                    />
                </div>

                <!-- Filters -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm mb-6 p-4">
                    <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Search</label>
                            <input
                                v-model="localFilters.search"
                                type="text"
                                placeholder="Search by name, code, or city..."
                                class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                @keyup.enter="applyFilters"
                            />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Type</label>
                            <select
                                v-model="localFilters.type"
                                class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                @change="applyFilters"
                            >
                                <option value="">All Types</option>
                                <option value="warehouse">Warehouse</option>
                                <option value="store">Store</option>
                                <option value="supplier">Supplier</option>
                                <option value="customer">Customer</option>
                                <option value="virtual">Virtual</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Status</label>
                            <select
                                v-model="localFilters.status"
                                class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                @change="applyFilters"
                            >
                                <option value="">All Status</option>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Sort By</label>
                            <select
                                v-model="localFilters.sort"
                                class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                @change="applyFilters"
                            >
                                <option value="name">Name A-Z</option>
                                <option value="name_desc">Name Z-A</option>
                                <option value="code">Code</option>
                                <option value="type">Type</option>
                                <option value="city">City</option>
                                <option value="created_at_desc">Newest</option>
                            </select>
                        </div>
                    </div>
                    <div class="mt-3 flex justify-end">
                        <button
                            type="button"
                            @click="clearFilters"
                            class="text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200"
                        >
                            Clear Filters
                        </button>
                    </div>
                </div>

                <!-- Locations Grid -->
                <div v-if="locationData.length === 0" class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-12 text-center">
                    <i class="fas fa-map-marker-alt text-4xl text-gray-400 mb-4"></i>
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-2">No Locations Found</h3>
                    <p class="text-gray-500 dark:text-gray-400 mb-4">
                        Get started by creating your first inventory location.
                    </p>
                    <PrimaryButton type="button" @click="openCreateModal">
                        <i class="fas fa-plus mr-2"></i>
                        Add Location
                    </PrimaryButton>
                </div>

                <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <LocationCard
                        v-for="location in locationData"
                        :key="location.id"
                        :location="location"
                        @edit="openEditModal"
                        @view="viewLocation"
                        @delete="confirmDelete"
                    />
                </div>

                <!-- Pagination -->
                <div v-if="locations.links && locations.links.length > 3" class="mt-6">
                    <nav class="flex justify-center">
                        <ul class="flex space-x-1">
                            <li v-for="(link, index) in locations.links" :key="index">
                                <Link
                                    v-if="link.url"
                                    :href="link.url"
                                    class="px-3 py-2 rounded-md text-sm"
                                    :class="{
                                        'bg-blue-600 text-white': link.active,
                                        'bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700': !link.active
                                    }"
                                    v-html="link.label"
                                />
                                <span
                                    v-else
                                    class="px-3 py-2 rounded-md text-sm text-gray-400"
                                    v-html="link.label"
                                />
                            </li>
                        </ul>
                    </nav>
                </div>
            </div>
        </div>

        <!-- Create/Edit Modal -->
        <LocationModal
            :show="showModal"
            :location="selectedLocation"
            :types="locationTypes"
            :mode="modalMode"
            @close="closeModal"
            @saved="handleSaved"
        />

        <!-- Delete Confirmation Modal -->
        <ConfirmationModal
            :show="showDeleteModal"
            title="Delete Location"
            :message="`Are you sure you want to delete '${locationToDelete?.name}'? This action cannot be undone.`"
            confirm-text="Delete"
            confirm-class="bg-red-600 hover:bg-red-700"
            @confirm="deleteLocation"
            @cancel="showDeleteModal = false"
        />
    </AppLayout>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import { router, Link } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import StatCard from '@/Components/StatCard.vue'
import PrimaryButton from '@/Components/PrimaryButton.vue'
import LocationCard from './Components/LocationCard.vue'
import LocationModal from './Components/LocationModal.vue'
import ConfirmationModal from '@/Components/ConfirmationModal.vue'

const props = defineProps({
    locations: {
        type: Object,
        default: () => ({ data: [], links: [] })
    },
    stats: {
        type: Object,
        default: () => ({ total: 0, active: 0, warehouses: 0, stores: 0 })
    },
    types: {
        type: Array,
        default: () => []
    },
    filters: {
        type: Object,
        default: () => ({})
    }
})

const locationTypes = {
    warehouse: 'Warehouse',
    store: 'Store',
    supplier: 'Supplier Location',
    customer: 'Customer Location',
    virtual: 'Virtual Location'
}

const localFilters = ref({
    search: props.filters.search || '',
    type: props.filters.type || '',
    status: props.filters.status || '',
    sort: props.filters.sort || 'name'
})

const showModal = ref(false)
const modalMode = ref('create')
const selectedLocation = ref(null)
const showDeleteModal = ref(false)
const locationToDelete = ref(null)

const locationData = computed(() => {
    return Array.isArray(props.locations) ? props.locations : (props.locations.data || [])
})

const applyFilters = () => {
    const params = {}
    Object.keys(localFilters.value).forEach(key => {
        if (localFilters.value[key]) {
            params[key] = localFilters.value[key]
        }
    })

    router.get(route('inventory.locations.index'), params, {
        preserveState: true,
        preserveScroll: true
    })
}

const clearFilters = () => {
    localFilters.value = {
        search: '',
        type: '',
        status: '',
        sort: 'name'
    }
    router.get(route('inventory.locations.index'), {}, {
        preserveState: true,
        preserveScroll: true
    })
}

const openCreateModal = () => {
    selectedLocation.value = null
    modalMode.value = 'create'
    showModal.value = true
}

const openEditModal = (location) => {
    selectedLocation.value = { ...location }
    modalMode.value = 'edit'
    showModal.value = true
}

const closeModal = () => {
    showModal.value = false
    selectedLocation.value = null
}

const handleSaved = () => {
    closeModal()
}

const viewLocation = (location) => {
    router.visit(route('inventory.locations.show', location.id))
}

const confirmDelete = (location) => {
    locationToDelete.value = location
    showDeleteModal.value = true
}

const deleteLocation = () => {
    if (!locationToDelete.value) return

    router.delete(route('inventory.locations.destroy', locationToDelete.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            showDeleteModal.value = false
            locationToDelete.value = null
        }
    })
}

watch(() => props.filters, (newFilters) => {
    localFilters.value = {
        search: newFilters.search || '',
        type: newFilters.type || '',
        status: newFilters.status || '',
        sort: newFilters.sort || 'name'
    }
}, { deep: true })

// Page actions for the toolbar
const pageActions = computed(() => [
    {
        label: 'Add Location',
        icon: 'fas fa-plus',
        onClick: openCreateModal,
        variant: 'primary'
    }
])
</script>
