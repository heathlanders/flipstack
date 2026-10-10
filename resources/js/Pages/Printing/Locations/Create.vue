<template>
  <AppLayout title="Create Warehouse Location">
    <template #header>
      <div class="flex items-center space-x-4">
        <Link :href="route('printing.locations.index')" 
              class="text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300">
          <i class="fas fa-arrow-left"></i>
        </Link>
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
          Create Warehouse Location
        </h2>
      </div>
    </template>

    <div class="py-12">
      <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
        <form @submit.prevent="saveLocation" class="space-y-6">
          
          <!-- Location Details -->
          <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg">
            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
              <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                Location Information
              </h3>
            </div>
            <div class="p-6 space-y-6">
              <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Location Code -->
                <div>
                  <label for="location_code" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                    Location Code *
                  </label>
                  <input id="location_code" 
                         v-model="location.location_code" 
                         type="text" 
                         required 
                         :class="{ 'border-red-300 dark:border-red-600': errors.location_code }"
                         class="block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-gray-100 sm:text-sm uppercase"
                         placeholder="e.g., A1-B2-C3"
                         @input="generateCode"
                         @blur="validateLocationCode">
                  <p v-if="errors.location_code" class="mt-1 text-sm text-red-600 dark:text-red-400">
                    {{ errors.location_code }}
                  </p>
                  <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                    Unique identifier for this location (e.g., A1-B2-C3 for Aisle A1, Shelf B2, Bin C3)
                  </p>
                </div>
                
                <!-- Description -->
                <div>
                  <label for="description" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                    Description
                  </label>
                  <input id="description" 
                         v-model="location.description" 
                         type="text" 
                         class="block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-gray-100 sm:text-sm"
                         placeholder="e.g., Electronics section, top shelf">
                  <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                    Optional description to help identify this location
                  </p>
                </div>
              </div>
            </div>
          </div>

          <!-- Position Details -->
          <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg">
            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
              <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                Position Details
              </h3>
              <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Break down the physical location into components
              </p>
            </div>
            <div class="p-6 space-y-6">
              <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <!-- Aisle -->
                <div>
                  <label for="aisle" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                    Aisle
                  </label>
                  <input id="aisle" 
                         v-model="location.aisle" 
                         type="text" 
                         class="block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-gray-100 sm:text-sm"
                         placeholder="e.g., A1, 1, Main"
                         @input="updateLocationCode">
                  <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                    Aisle identifier
                  </p>
                </div>
                
                <!-- Shelf -->
                <div>
                  <label for="shelf" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                    Shelf
                  </label>
                  <input id="shelf" 
                         v-model="location.shelf" 
                         type="text" 
                         class="block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-gray-100 sm:text-sm"
                         placeholder="e.g., B2, Top, 1"
                         @input="updateLocationCode">
                  <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                    Shelf or rack identifier
                  </p>
                </div>
                
                <!-- Bin -->
                <div>
                  <label for="bin" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                    Bin
                  </label>
                  <input id="bin" 
                         v-model="location.bin" 
                         type="text" 
                         class="block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-gray-100 sm:text-sm"
                         placeholder="e.g., C3, Left, 001"
                         @input="updateLocationCode">
                  <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                    Bin or slot identifier
                  </p>
                </div>
              </div>
              
              <!-- Auto-generate toggle -->
              <div class="flex items-center">
                <input id="auto_generate" 
                       v-model="autoGenerateCode" 
                       type="checkbox" 
                       class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 dark:border-gray-600 rounded dark:bg-gray-700">
                <label for="auto_generate" class="ml-2 block text-sm text-gray-900 dark:text-gray-100">
                  Auto-generate location code from position details
                </label>
              </div>
              <p class="text-xs text-gray-500 dark:text-gray-400">
                When enabled, the location code will be automatically generated as Aisle-Shelf-Bin (e.g., A1-B2-C3)
              </p>
            </div>
          </div>

          <!-- Location Settings -->
          <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg">
            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
              <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                Settings
              </h3>
            </div>
            <div class="p-6 space-y-4">
              <div class="flex items-center">
                <input id="is_active" 
                       v-model="location.is_active" 
                       type="checkbox" 
                       class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 dark:border-gray-600 rounded dark:bg-gray-700">
                <label for="is_active" class="ml-2 block text-sm text-gray-900 dark:text-gray-100">
                  Active location
                </label>
              </div>
              <p class="text-xs text-gray-500 dark:text-gray-400">
                Only active locations can be assigned to products and will appear in selection lists
              </p>
            </div>
          </div>

          <!-- Bulk Creation -->
          <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg">
            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
              <div class="flex items-center justify-between">
                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                  Bulk Creation
                </h3>
                <button type="button" 
                        @click="showBulkCreation = !showBulkCreation"
                        class="text-sm text-blue-600 dark:text-blue-400 hover:underline">
                  {{ showBulkCreation ? 'Hide' : 'Show' }} Bulk Options
                </button>
              </div>
            </div>
            <div v-if="showBulkCreation" class="p-6 space-y-6">
              <p class="text-sm text-gray-600 dark:text-gray-400">
                Create multiple locations at once by specifying ranges for each position component.
              </p>
              
              <div class="flex items-center mb-4">
                <input id="enable_bulk" 
                       v-model="bulkCreation.enabled" 
                       type="checkbox" 
                       class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 dark:border-gray-600 rounded dark:bg-gray-700">
                <label for="enable_bulk" class="ml-2 block text-sm text-gray-900 dark:text-gray-100">
                  Enable bulk creation
                </label>
              </div>
              
              <div v-if="bulkCreation.enabled" class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <!-- Aisle Range -->
                <div>
                  <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                    Aisle Range
                  </label>
                  <div class="space-y-2">
                    <input v-model="bulkCreation.aisle_prefix" 
                           type="text" 
                           placeholder="Prefix (e.g., A)"
                           class="block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-gray-100 sm:text-sm">
                    <div class="grid grid-cols-2 gap-2">
                      <input v-model.number="bulkCreation.aisle_start" 
                             type="number" 
                             min="1"
                             placeholder="Start"
                             class="block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-gray-100 sm:text-sm">
                      <input v-model.number="bulkCreation.aisle_end" 
                             type="number" 
                             min="1"
                             placeholder="End"
                             class="block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-gray-100 sm:text-sm">
                    </div>
                  </div>
                </div>
                
                <!-- Shelf Range -->
                <div>
                  <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                    Shelf Range
                  </label>
                  <div class="space-y-2">
                    <input v-model="bulkCreation.shelf_prefix" 
                           type="text" 
                           placeholder="Prefix (e.g., S)"
                           class="block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-gray-100 sm:text-sm">
                    <div class="grid grid-cols-2 gap-2">
                      <input v-model.number="bulkCreation.shelf_start" 
                             type="number" 
                             min="1"
                             placeholder="Start"
                             class="block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-gray-100 sm:text-sm">
                      <input v-model.number="bulkCreation.shelf_end" 
                             type="number" 
                             min="1"
                             placeholder="End"
                             class="block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-gray-100 sm:text-sm">
                    </div>
                  </div>
                </div>
                
                <!-- Bin Range -->
                <div>
                  <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                    Bin Range
                  </label>
                  <div class="space-y-2">
                    <input v-model="bulkCreation.bin_prefix" 
                           type="text" 
                           placeholder="Prefix (e.g., B)"
                           class="block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-gray-100 sm:text-sm">
                    <div class="grid grid-cols-2 gap-2">
                      <input v-model.number="bulkCreation.bin_start" 
                             type="number" 
                             min="1"
                             placeholder="Start"
                             class="block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-gray-100 sm:text-sm">
                      <input v-model.number="bulkCreation.bin_end" 
                             type="number" 
                             min="1"
                             placeholder="End"
                             class="block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-gray-100 sm:text-sm">
                    </div>
                  </div>
                </div>
              </div>
              
              <div v-if="bulkCreation.enabled" class="bg-blue-50 dark:bg-blue-900 rounded-lg p-4">
                <h4 class="text-sm font-medium text-blue-900 dark:text-blue-100 mb-2">
                  Bulk Creation Preview
                </h4>
                <p class="text-sm text-blue-800 dark:text-blue-200">
                  This will create approximately <strong>{{ estimatedBulkCount }}</strong> locations.
                </p>
                <p v-if="estimatedBulkCount > 100" class="text-sm text-blue-800 dark:text-blue-200 mt-1">
                  <i class="fas fa-exclamation-triangle mr-1"></i>
                  Large bulk creation may take some time to process.
                </p>
              </div>
            </div>
          </div>

          <!-- Actions -->
          <div class="flex justify-end space-x-4">
            <Link :href="route('printing.locations.index')" 
                  class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
              Cancel
            </Link>
            <button type="submit" 
                    :disabled="saving"
                    class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 active:bg-blue-900 focus:outline-none focus:border-blue-900 focus:ring ring-blue-300 disabled:opacity-25 transition ease-in-out duration-150">
              <i v-if="saving" class="fas fa-spinner fa-spin mr-2"></i>
              <i v-else class="fas fa-save mr-2"></i>
              {{ saving ? 'Saving...' : (bulkCreation.enabled ? `Create ${estimatedBulkCount} Locations` : 'Create Location') }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </AppLayout>
</template>

<script>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Link } from '@inertiajs/vue3'

export default {
  name: 'LocationCreate',
  components: {
    AppLayout,
    Link
  },
  data() {
    return {
      saving: false,
      autoGenerateCode: true,
      showBulkCreation: false,
      errors: {},
      location: {
        location_code: '',
        description: '',
        aisle: '',
        shelf: '',
        bin: '',
        is_active: true
      },
      bulkCreation: {
        enabled: false,
        aisle_prefix: 'A',
        aisle_start: 1,
        aisle_end: 3,
        shelf_prefix: 'S',
        shelf_start: 1,
        shelf_end: 3,
        bin_prefix: 'B',
        bin_start: 1,
        bin_end: 3
      }
    }
  },
  computed: {
    estimatedBulkCount() {
      if (!this.bulkCreation.enabled) return 0;
      
      const aisles = Math.max(0, (this.bulkCreation.aisle_end || 0) - (this.bulkCreation.aisle_start || 0) + 1);
      const shelves = Math.max(0, (this.bulkCreation.shelf_end || 0) - (this.bulkCreation.shelf_start || 0) + 1);
      const bins = Math.max(0, (this.bulkCreation.bin_end || 0) - (this.bulkCreation.bin_start || 0) + 1);
      
      return aisles * shelves * bins;
    }
  },
  methods: {
    generateCode() {
      // Manual typing overrides auto-generation temporarily
      this.autoGenerateCode = false;
    },
    
    updateLocationCode() {
      if (this.autoGenerateCode) {
        const parts = [
          this.location.aisle,
          this.location.shelf,
          this.location.bin
        ].filter(Boolean);
        
        this.location.location_code = parts.join('-').toUpperCase();
      }
    },
    
    async validateLocationCode() {
      if (!this.location.location_code) {
        this.errors.location_code = null;
        return;
      }
      
      try {
        const response = await axios.post('/printing/locations/validate-code', {
          location_code: this.location.location_code
        });
        
        if (!response.data.available) {
          this.errors.location_code = 'This location code is already in use';
        } else {
          this.errors.location_code = null;
        }
      } catch (error) {
        // Validation endpoint error handling
        this.errors.location_code = null;
      }
    },
    
    async saveLocation() {
      this.saving = true;
      this.errors = {};
      
      try {
        const payload = this.bulkCreation.enabled ? {
          bulk_creation: this.bulkCreation,
          is_active: this.location.is_active
        } : this.location;
        
        const response = await axios.post('/printing/locations', payload);
        
        if (this.bulkCreation.enabled) {
          this.$toast.success(`Created ${response.data.created_count} locations successfully`);
        } else {
          this.$toast.success('Location created successfully');
        }
        
        this.$inertia.visit(route('printing.locations.index'));
      } catch (error) {
        if (error.response?.data?.errors) {
          this.errors = error.response.data.errors;
        } else {
          this.$toast.error('Failed to create location: ' + (error.response?.data?.message || error.message));
        }
      } finally {
        this.saving = false;
      }
    }
  },
  watch: {
    'location.aisle'() {
      this.updateLocationCode();
    },
    'location.shelf'() {
      this.updateLocationCode();
    },
    'location.bin'() {
      this.updateLocationCode();
    },
    'location.location_code'() {
      if (this.location.location_code) {
        this.validateLocationCode();
      }
    }
  }
}
</script>
