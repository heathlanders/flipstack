<template>
  <AppLayout title="Warehouse Locations">
    <template #header>
      <div class="flex justify-between items-center">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
          Warehouse Locations
        </h2>
        <div class="flex space-x-3">
          <button @click="generateDefaultLocations" 
                  :disabled="generatingDefaults"
                  class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50">
            <i v-if="generatingDefaults" class="fas fa-spinner fa-spin mr-2"></i>
            <i v-else class="fas fa-magic mr-2"></i>
            Generate Locations
          </button>
          <Link :href="route('printing.locations.create')" 
                class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 active:bg-blue-900 focus:outline-none focus:border-blue-900 focus:ring ring-blue-300 transition ease-in-out duration-150">
            <i class="fas fa-plus mr-2"></i>
            Add Location
          </Link>
        </div>
      </div>
    </template>

    <div class="py-12">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
        
        <!-- Search and Filters -->
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg">
          <div class="p-6 border-b border-gray-200 dark:border-gray-700">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
              <!-- Search -->
              <div class="md:col-span-2">
                <label for="search" class="sr-only">Search locations</label>
                <div class="relative">
                  <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <i class="fas fa-search text-gray-400"></i>
                  </div>
                  <input id="search" 
                         v-model="localFilters.search"
                         type="text" 
                         placeholder="Search locations..."
                         class="block w-full pl-10 pr-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md leading-5 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 placeholder-gray-500 dark:placeholder-gray-400 focus:outline-none focus:placeholder-gray-400 dark:focus:placeholder-gray-500 focus:ring-1 focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                </div>
              </div>
              
              <!-- Aisle Filter -->
              <div>
                <label for="aisle-filter" class="sr-only">Filter by aisle</label>
                <select id="aisle-filter" 
                        v-model="localFilters.aisle"
                        class="block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                  <option value="">All Aisles</option>
                  <option v-for="aisle in uniqueAisles" :key="aisle" :value="aisle">
                    Aisle {{ aisle }}
                  </option>
                </select>
              </div>
              
              <!-- Status Filter -->
              <div>
                <label for="status-filter" class="sr-only">Filter by status</label>
                <select id="status-filter" 
                        v-model="localFilters.status"
                        class="block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                  <option value="">All Status</option>
                  <option value="active">Active</option>
                  <option value="inactive">Inactive</option>
                </select>
              </div>
            </div>
          </div>
        </div>

        <!-- Location Stats -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
          <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg p-6">
            <div class="flex items-center">
              <div class="flex-1">
                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                  Total Locations
                </h3>
                <p class="text-3xl font-bold text-blue-600 dark:text-blue-400">
                  {{ stats.total || locationData.length }}
                </p>
              </div>
              <div class="ml-4">
                <i class="fas fa-map-marker-alt text-4xl text-blue-600 dark:text-blue-400"></i>
              </div>
            </div>
          </div>

          <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg p-6">
            <div class="flex items-center">
              <div class="flex-1">
                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                  Active Locations
                </h3>
                <p class="text-3xl font-bold text-green-600 dark:text-green-400">
                  {{ activeLocationsCount }}
                </p>
              </div>
              <div class="ml-4">
                <i class="fas fa-check-circle text-4xl text-green-600 dark:text-green-400"></i>
              </div>
            </div>
          </div>

          <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg p-6">
            <div class="flex items-center">
              <div class="flex-1">
                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                  Occupied
                </h3>
                <p class="text-3xl font-bold text-orange-600 dark:text-orange-400">
                  {{ occupiedLocationsCount }}
                </p>
              </div>
              <div class="ml-4">
                <i class="fas fa-box text-4xl text-orange-600 dark:text-orange-400"></i>
              </div>
            </div>
          </div>

          <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg p-6">
            <div class="flex items-center">
              <div class="flex-1">
                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                  Available
                </h3>
                <p class="text-3xl font-bold text-purple-600 dark:text-purple-400">
                  {{ availableLocationsCount }}
                </p>
              </div>
              <div class="ml-4">
                <i class="fas fa-inbox text-4xl text-purple-600 dark:text-purple-400"></i>
              </div>
            </div>
          </div>
        </div>

        <!-- Locations List -->
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg">
          <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
            <div class="flex justify-between items-center">
              <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                Locations ({{ filteredLocations.length }})
              </h3>
              <div class="flex items-center space-x-3">
                <span class="text-sm text-gray-500 dark:text-gray-400">View:</span>
                <button @click="viewMode = 'table'" 
                        :class="viewMode === 'table' ? 'text-blue-600 dark:text-blue-400' : 'text-gray-400'"
                        class="hover:text-blue-600 dark:hover:text-blue-400">
                  <i class="fas fa-table"></i>
                </button>
                <button @click="viewMode = 'grid'" 
                        :class="viewMode === 'grid' ? 'text-blue-600 dark:text-blue-400' : 'text-gray-400'"
                        class="hover:text-blue-600 dark:hover:text-blue-400">
                  <i class="fas fa-th"></i>
                </button>
              </div>
            </div>
          </div>

          <div v-if="filteredLocations.length === 0" class="p-6 text-center">
            <div class="text-gray-500 dark:text-gray-400">
              <i class="fas fa-map-marker-alt text-4xl mb-4"></i>
              <p class="text-lg font-medium">No locations found</p>
              <p class="text-sm">
                {{ locationData.length === 0 ? 'Create your first warehouse location to get started.' : 'Try adjusting your filters.' }}
              </p>
            </div>
          </div>

          <!-- Table View -->
          <div v-else-if="viewMode === 'table'" class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
              <thead class="bg-gray-50 dark:bg-gray-700">
                <tr>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                    Location Code
                  </th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                    Description
                  </th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                    Position
                  </th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                    Products
                  </th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                    Status
                  </th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                    Actions
                  </th>
                </tr>
              </thead>
              <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                <tr v-for="location in paginatedLocations" 
                    :key="location.id" 
                    class="hover:bg-gray-50 dark:hover:bg-gray-700">
                  <td class="px-6 py-4 whitespace-nowrap">
                    <div class="flex items-center">
                      <div class="text-sm font-medium text-gray-900 dark:text-gray-100">
                        {{ location.location_code }}
                      </div>
                    </div>
                  </td>
                  <td class="px-6 py-4">
                    <div class="text-sm text-gray-900 dark:text-gray-100">
                      {{ location.description || '-' }}
                    </div>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                    <div v-if="location.aisle || location.shelf || location.bin" class="space-y-1">
                      <div v-if="location.aisle">Aisle: {{ location.aisle }}</div>
                      <div v-if="location.shelf">Shelf: {{ location.shelf }}</div>
                      <div v-if="location.bin">Bin: {{ location.bin }}</div>
                    </div>
                    <div v-else>-</div>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                    {{ location.products_count || 0 }}
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap">
                    <span :class="location.is_active ? 
                          'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-800 dark:text-green-100' :
                          'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 dark:bg-red-800 dark:text-red-100'">
                      <i :class="location.is_active ? 'fas fa-circle' : 'fas fa-stop-circle'" class="mr-1"></i>
                      {{ location.is_active ? 'Active' : 'Inactive' }}
                    </span>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                    <div class="flex space-x-3">
                      <Link :href="route('printing.locations.show', location.id)" 
                            class="text-blue-600 dark:text-blue-400 hover:underline">
                        View
                      </Link>
                      <Link :href="route('printing.locations.edit', location.id)" 
                            class="text-green-600 dark:text-green-400 hover:underline">
                        Edit
                      </Link>
                      <button @click="printLocationLabel(location)" 
                              class="text-purple-600 dark:text-purple-400 hover:underline">
                        Print Label
                      </button>
                      <button @click="confirmDelete(location)" 
                              class="text-red-600 dark:text-red-400 hover:underline">
                        Delete
                      </button>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- Grid View -->
          <div v-else class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
              <div v-for="location in paginatedLocations" 
                   :key="location.id" 
                   class="border border-gray-200 dark:border-gray-700 rounded-lg p-4 hover:shadow-lg transition-shadow cursor-pointer"
                   @click="$inertia.visit(route('printing.locations.show', location.id))">
                
                <div class="flex justify-between items-start mb-3">
                  <div>
                    <h4 class="font-medium text-gray-900 dark:text-gray-100">
                      {{ location.location_code }}
                    </h4>
                    <p v-if="location.description" class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                      {{ location.description }}
                    </p>
                  </div>
                  <span :class="location.is_active ? 'text-green-500' : 'text-red-500'">
                    <i :class="location.is_active ? 'fas fa-circle' : 'fas fa-stop-circle'"></i>
                  </span>
                </div>
                
                <div v-if="location.aisle || location.shelf || location.bin" 
                     class="text-sm text-gray-500 dark:text-gray-400 mb-3">
                  <div v-if="location.aisle">Aisle: {{ location.aisle }}</div>
                  <div v-if="location.shelf">Shelf: {{ location.shelf }}</div>
                  <div v-if="location.bin">Bin: {{ location.bin }}</div>
                </div>
                
                <div class="flex justify-between items-center text-sm">
                  <span class="text-gray-900 dark:text-gray-100">
                    {{ location.products_count || 0 }} products
                  </span>
                  <div class="flex space-x-2" @click.stop="">
                    <button @click="printLocationLabel(location)" 
                            class="text-purple-600 dark:text-purple-400 hover:underline"
                            title="Print label">
                      <i class="fas fa-print"></i>
                    </button>
                    <Link :href="route('printing.locations.edit', location.id)" 
                          class="text-green-600 dark:text-green-400 hover:underline"
                          title="Edit">
                      <i class="fas fa-edit"></i>
                    </Link>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Pagination -->
          <div v-if="filteredLocations.length > itemsPerPage" 
               class="px-6 py-4 border-t border-gray-200 dark:border-gray-700">
            <div class="flex items-center justify-between">
              <div class="text-sm text-gray-700 dark:text-gray-300">
                Showing {{ (currentPage - 1) * itemsPerPage + 1 }} to 
                {{ Math.min(currentPage * itemsPerPage, filteredLocations.length) }} of 
                {{ filteredLocations.length }} results
              </div>
              <div class="flex space-x-2">
                <button @click="currentPage--" 
                        :disabled="currentPage === 1"
                        class="px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50">
                  Previous
                </button>
                <button @click="currentPage++" 
                        :disabled="currentPage >= totalPages"
                        class="px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50">
                  Next
                </button>
              </div>
            </div>
          </div>
        </div>

        <!-- Delete Confirmation Modal -->
        <div v-if="locationToDelete" 
             class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50"
             @click.self="locationToDelete = null">
          <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white dark:bg-gray-800">
            <div class="mt-3 text-center">
              <i class="fas fa-exclamation-triangle text-red-400 text-4xl mb-4"></i>
              <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-2">
                Delete Location
              </h3>
              <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">
                Are you sure you want to delete location "{{ locationToDelete.location_code }}"?
                {{ locationToDelete.products_count > 0 ? 
                   `This will affect ${locationToDelete.products_count} product(s).` : 
                   'This action cannot be undone.' }}
              </p>
              <div class="flex justify-center space-x-3">
                <button @click="locationToDelete = null" 
                        class="px-4 py-2 bg-gray-300 dark:bg-gray-600 text-gray-700 dark:text-gray-300 rounded-md hover:bg-gray-400 dark:hover:bg-gray-500 focus:outline-none focus:ring-2 focus:ring-gray-500">
                  Cancel
                </button>
                <button @click="deleteLocation" 
                        :disabled="deleting"
                        class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 disabled:opacity-50">
                  <i v-if="deleting" class="fas fa-spinner fa-spin mr-2"></i>
                  Delete
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Link } from '@inertiajs/vue3'

export default {
  name: 'LocationsIndex',
  components: {
    AppLayout,
    Link
  },
  props: {
    locations: {
      type: [Array, Object],
      default: () => ({ data: [] })
    },
    stats: {
      type: Object,
      default: () => ({ total: 0, active: 0, occupied: 0, empty: 0, aisles: 0 })
    },
    aisles: {
      type: Array,
      default: () => []
    },
    filters: {
      type: Object,
      default: () => ({})
    }
  },
  data() {
    return {
      viewMode: 'table',
      currentPage: 1,
      itemsPerPage: 20,
      generatingDefaults: false,
      deleting: false,
      locationToDelete: null,
      localFilters: {
        search: this.filters?.search || '',
        aisle: this.filters?.aisle || '',
        status: this.filters?.active_only === 'true' ? 'active' : ''
      }
    }
  },
  computed: {
    locationData() {
      return Array.isArray(this.locations) ? this.locations : (this.locations.data || []);
    },
    filteredLocations() {
      let filtered = [...this.locationData];

      if (this.localFilters.search) {
        const search = this.localFilters.search.toLowerCase();
        filtered = filtered.filter(location =>
          location.location_code.toLowerCase().includes(search) ||
          (location.description && location.description.toLowerCase().includes(search)) ||
          (location.aisle && location.aisle.toString().includes(search)) ||
          (location.shelf && location.shelf.toString().includes(search)) ||
          (location.bin && location.bin.toString().includes(search))
        );
      }

      if (this.localFilters.aisle) {
        filtered = filtered.filter(location => location.aisle === this.localFilters.aisle);
      }

      if (this.localFilters.status) {
        const isActive = this.localFilters.status === 'active';
        filtered = filtered.filter(location => location.is_active === isActive);
      }

      return filtered.sort((a, b) => a.location_code.localeCompare(b.location_code));
    },
    paginatedLocations() {
      const start = (this.currentPage - 1) * this.itemsPerPage;
      const end = start + this.itemsPerPage;
      return this.filteredLocations.slice(start, end);
    },
    totalPages() {
      return Math.ceil(this.filteredLocations.length / this.itemsPerPage);
    },
    uniqueAisles() {
      // Use props aisles if available, otherwise compute from data
      if (this.aisles && this.aisles.length > 0) {
        return this.aisles;
      }
      return [...new Set(this.locationData.map(l => l.aisle).filter(Boolean))].sort();
    },
    activeLocationsCount() {
      return this.stats.active || this.locationData.filter(l => l.is_active).length;
    },
    occupiedLocationsCount() {
      return this.stats.occupied || this.locationData.filter(l => l.products_count > 0).length;
    },
    availableLocationsCount() {
      return this.stats.empty || this.locationData.filter(l => l.is_active && l.products_count === 0).length;
    }
  },
  watch: {
    'localFilters.search'() {
      this.currentPage = 1;
    },
    'localFilters.aisle'() {
      this.currentPage = 1;
    },
    'localFilters.status'() {
      this.currentPage = 1;
    }
  },
  methods: {
    async generateDefaultLocations() {
      this.generatingDefaults = true;
      
      try {
        const response = await axios.post('/printing/locations/generate-defaults');
        this.$toast.success(`Generated ${response.data.created_count} default locations`);
        await this.$inertia.reload({ only: ['locations'] });
      } catch (error) {
        this.$toast.error(`Failed to generate locations: ${error.response?.data?.message || error.message}`);
      } finally {
        this.generatingDefaults = false;
      }
    },
    
    async printLocationLabel(location) {
      try {
        const response = await axios.post(`/printing/locations/${location.id}/print-label`);
        
        if (response.data.success) {
          this.$toast.success('Location label print job queued');
        } else {
          this.$toast.error('Failed to queue location label print');
        }
      } catch (error) {
        this.$toast.error(`Print failed: ${error.response?.data?.message || error.message}`);
      }
    },
    
    confirmDelete(location) {
      this.locationToDelete = location;
    },
    
    async deleteLocation() {
      if (!this.locationToDelete) return;
      
      this.deleting = true;
      
      try {
        await axios.delete(`/printing/locations/${this.locationToDelete.id}`);
        this.$toast.success(`Location ${this.locationToDelete.location_code} deleted successfully`);
        this.locationToDelete = null;
        await this.$inertia.reload({ only: ['locations'] });
      } catch (error) {
        this.$toast.error(`Failed to delete location: ${error.response?.data?.message || error.message}`);
      } finally {
        this.deleting = false;
      }
    }
  }
}
</script>
