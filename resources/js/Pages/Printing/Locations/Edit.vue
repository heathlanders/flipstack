<template>
  <AppLayout :title="`Edit Location: ${location.location_code}`">
    <template #header>
      <div class="flex items-center space-x-4">
        <Link :href="route('printing.locations.show', location.id)" 
              class="text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300">
          <i class="fas fa-arrow-left"></i>
        </Link>
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
          Edit Location: {{ location.location_code }}
        </h2>
      </div>
    </template>

    <div class="py-12">
      <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
        <form @submit.prevent="updateLocation" class="space-y-6">
          
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
                         v-model="form.location_code" 
                         type="text" 
                         required 
                         :class="{ 'border-red-300 dark:border-red-600': errors.location_code }"
                         class="block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-gray-100 sm:text-sm uppercase"
                         @input="generateCode"
                         @blur="validateLocationCode">
                  <p v-if="errors.location_code" class="mt-1 text-sm text-red-600 dark:text-red-400">
                    {{ errors.location_code }}
                  </p>
                  <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                    Unique identifier for this location (e.g., A1-B2-C3)
                  </p>
                </div>
                
                <!-- Description -->
                <div>
                  <label for="description" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                    Description
                  </label>
                  <input id="description" 
                         v-model="form.description" 
                         type="text" 
                         class="block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-gray-100 sm:text-sm">
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
                         v-model="form.aisle" 
                         type="text" 
                         class="block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-gray-100 sm:text-sm"
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
                         v-model="form.shelf" 
                         type="text" 
                         class="block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-gray-100 sm:text-sm"
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
                         v-model="form.bin" 
                         type="text" 
                         class="block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-gray-100 sm:text-sm"
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
                       v-model="form.is_active" 
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

          <!-- Usage Information -->
          <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg">
            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
              <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                Usage Information
              </h3>
            </div>
            <div class="p-6">
              <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="text-center">
                  <div class="text-2xl font-bold text-blue-600 dark:text-blue-400">
                    {{ location.products_count || 0 }}
                  </div>
                  <div class="text-sm text-gray-500 dark:text-gray-400">Products Assigned</div>
                </div>
                <div class="text-center">
                  <div class="text-2xl font-bold text-green-600 dark:text-green-400">
                    {{ location.labels_printed || 0 }}
                  </div>
                  <div class="text-sm text-gray-500 dark:text-gray-400">Labels Printed</div>
                </div>
                <div class="text-center">
                  <div class="text-2xl font-bold text-purple-600 dark:text-purple-400">
                    {{ formatDate(location.created_at) }}
                  </div>
                  <div class="text-sm text-gray-500 dark:text-gray-400">Date Created</div>
                </div>
              </div>
              
              <!-- Warning if products are assigned -->
              <div v-if="location.products_count > 0 && !form.is_active" 
                   class="mt-6 bg-yellow-50 dark:bg-yellow-900 border border-yellow-200 dark:border-yellow-700 rounded-md p-4">
                <div class="flex">
                  <div class="flex-shrink-0">
                    <i class="fas fa-exclamation-triangle text-yellow-400"></i>
                  </div>
                  <div class="ml-3">
                    <h4 class="text-sm font-medium text-yellow-800 dark:text-yellow-200">
                      Warning: Products Assigned
                    </h4>
                    <div class="mt-1 text-sm text-yellow-700 dark:text-yellow-300">
                      This location has {{ location.products_count }} product(s) assigned. 
                      Deactivating it may affect inventory management and searches.
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Actions -->
          <div class="flex justify-end space-x-4">
            <Link :href="route('printing.locations.show', location.id)" 
                  class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
              Cancel
            </Link>
            <button type="submit" 
                    :disabled="saving"
                    class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 active:bg-blue-900 focus:outline-none focus:border-blue-900 focus:ring ring-blue-300 disabled:opacity-25 transition ease-in-out duration-150">
              <i v-if="saving" class="fas fa-spinner fa-spin mr-2"></i>
              <i v-else class="fas fa-save mr-2"></i>
              {{ saving ? 'Updating...' : 'Update Location' }}
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
  name: 'LocationEdit',
  components: {
    AppLayout,
    Link
  },
  props: {
    location: {
      type: Object,
      required: true
    }
  },
  data() {
    return {
      saving: false,
      autoGenerateCode: false,
      errors: {},
      form: {
        location_code: this.location.location_code,
        description: this.location.description || '',
        aisle: this.location.aisle || '',
        shelf: this.location.shelf || '',
        bin: this.location.bin || '',
        is_active: this.location.is_active
      }
    }
  },
  methods: {
    formatDate(date) {
      return new Date(date).toLocaleDateString();
    },
    
    generateCode() {
      // Manual typing overrides auto-generation temporarily
      this.autoGenerateCode = false;
    },
    
    updateLocationCode() {
      if (this.autoGenerateCode) {
        const parts = [
          this.form.aisle,
          this.form.shelf,
          this.form.bin
        ].filter(Boolean);
        
        this.form.location_code = parts.join('-').toUpperCase();
      }
    },
    
    async validateLocationCode() {
      if (!this.form.location_code || this.form.location_code === this.location.location_code) {
        this.errors.location_code = null;
        return;
      }
      
      try {
        const response = await axios.post('/printing/locations/validate-code', {
          location_code: this.form.location_code,
          exclude_id: this.location.id
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
    
    async updateLocation() {
      this.saving = true;
      this.errors = {};
      
      try {
        await axios.patch(`/printing/locations/${this.location.id}`, this.form);
        this.$toast.success('Location updated successfully');
        this.$inertia.visit(route('printing.locations.show', this.location.id));
      } catch (error) {
        if (error.response?.data?.errors) {
          this.errors = error.response.data.errors;
        } else {
          this.$toast.error('Failed to update location: ' + (error.response?.data?.message || error.message));
        }
      } finally {
        this.saving = false;
      }
    }
  },
  watch: {
    'form.aisle'() {
      this.updateLocationCode();
    },
    'form.shelf'() {
      this.updateLocationCode();
    },
    'form.bin'() {
      this.updateLocationCode();
    },
    'form.location_code'() {
      if (this.form.location_code && this.form.location_code !== this.location.location_code) {
        this.validateLocationCode();
      }
    }
  }
}
</script>
