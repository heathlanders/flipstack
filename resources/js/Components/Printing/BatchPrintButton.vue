<template>
  <div class="batch-print-actions">
    <!-- Batch Print Button -->
    <button v-if="selectedProducts.length > 0" 
            @click="showBatchSettings = true" 
            :disabled="printing"
            class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 disabled:opacity-50 disabled:cursor-not-allowed">
      <i class="fas fa-print mr-2"></i>
      {{ printing ? 'Printing...' : `Print ${selectedProducts.length} Labels` }}
    </button>

    <!-- Quick Batch Print -->
    <button v-if="selectedProducts.length > 0 && hasDefaultPrinter" 
            @click="quickBatchPrint"
            :disabled="printing"
            class="inline-flex items-center px-3 py-2 border border-gray-300 dark:border-gray-600 text-sm font-medium rounded-md text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 disabled:opacity-50 ml-2"
            title="Quick batch print with default settings">
      <i class="fas fa-bolt mr-1"></i>
      Quick Print All
    </button>

    <!-- Batch Settings Modal -->
    <div v-if="showBatchSettings" 
         class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50"
         @click.self="showBatchSettings = false">
      <div class="relative top-10 mx-auto p-5 border w-full max-w-2xl shadow-lg rounded-md bg-white dark:bg-gray-800">
        <div class="mt-3">
          <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">
              Batch Print Settings
            </h3>
            <button @click="showBatchSettings = false" 
                    class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">
              <i class="fas fa-times"></i>
            </button>
          </div>
          
          <!-- Selected Products Summary -->
          <div class="mb-6 p-4 bg-gray-50 dark:bg-gray-700 rounded-lg">
            <h4 class="text-sm font-medium text-gray-900 dark:text-gray-100 mb-2">
              Selected Products ({{ selectedProducts.length }})
            </h4>
            <div class="max-h-32 overflow-y-auto">
              <div v-for="product in selectedProducts" 
                   :key="product.id" 
                   class="flex justify-between items-center text-sm py-1">
                <span class="text-gray-900 dark:text-gray-100">{{ product.name }}</span>
                <span class="text-gray-500 dark:text-gray-400">{{ product.internal_sku || product.sku }}</span>
              </div>
            </div>
          </div>
          
          <form @submit.prevent="confirmBatchPrint" class="space-y-4">
            <!-- Global Settings -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
              <div>
                <label for="batch-quantity" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                  Quantity per Product
                </label>
                <input id="batch-quantity" 
                       v-model.number="batchSettings.quantity" 
                       type="number" 
                       min="1" 
                       max="100" 
                       class="block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-green-500 focus:border-green-500 dark:bg-gray-700 dark:text-gray-100 sm:text-sm">
              </div>
              
              <div>
                <label for="batch-template" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                  Template
                </label>
                <select id="batch-template" 
                        v-model="batchSettings.template_id"
                        class="block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-green-500 focus:border-green-500 dark:bg-gray-700 dark:text-gray-100 sm:text-sm">
                  <option value="">Default Template</option>
                  <option v-for="template in templates" 
                          :key="template.id" 
                          :value="template.id">
                    {{ template.name }}
                  </option>
                </select>
              </div>
              
              <div>
                <label for="batch-printer" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                  Printer
                </label>
                <select id="batch-printer" 
                        v-model="batchSettings.printer_id"
                        class="block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-green-500 focus:border-green-500 dark:bg-gray-700 dark:text-gray-100 sm:text-sm">
                  <option value="">Default Printer</option>
                  <option v-for="printer in printers" 
                          :key="printer.id" 
                          :value="printer.id">
                    {{ printer.name }}
                    <span v-if="!printer.is_active" class="text-red-500">(Offline)</span>
                  </option>
                </select>
              </div>
            </div>
            
            <!-- Individual Product Settings (Optional) -->
            <div v-if="showIndividualSettings" class="border border-gray-200 dark:border-gray-700 rounded-lg p-4">
              <h4 class="text-sm font-medium text-gray-900 dark:text-gray-100 mb-3">
                Individual Product Settings
              </h4>
              <div class="space-y-3 max-h-48 overflow-y-auto">
                <div v-for="product in selectedProducts" 
                     :key="product.id" 
                     class="flex items-center justify-between">
                  <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium text-gray-900 dark:text-gray-100 truncate">
                      {{ product.name }}
                    </p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                      {{ product.internal_sku || product.sku }}
                    </p>
                  </div>
                  <div class="flex items-center space-x-2 ml-4">
                    <input v-model.number="individualSettings[product.id].quantity" 
                           type="number" 
                           min="0" 
                           max="100"
                           class="w-16 px-2 py-1 border border-gray-300 dark:border-gray-600 rounded text-sm focus:outline-none focus:ring-green-500 focus:border-green-500 dark:bg-gray-700 dark:text-gray-100"
                           title="Quantity for this product">
                    <button type="button" 
                            @click="individualSettings[product.id].skip = !individualSettings[product.id].skip"
                            :class="individualSettings[product.id].skip ? 'text-red-500 hover:text-red-600' : 'text-gray-400 hover:text-gray-600'"
                            title="Skip this product">
                      <i :class="individualSettings[product.id].skip ? 'fas fa-eye-slash' : 'far fa-eye'"></i>
                    </button>
                  </div>
                </div>
              </div>
            </div>
            
            <!-- Print Summary -->
            <div class="bg-blue-50 dark:bg-blue-900 rounded-lg p-4">
              <h4 class="text-sm font-medium text-blue-900 dark:text-blue-100 mb-2">
                Print Summary
              </h4>
              <div class="text-sm text-blue-800 dark:text-blue-200">
                <p><strong>Total Products:</strong> {{ activeBatchCount }}</p>
                <p><strong>Total Labels:</strong> {{ totalLabelsCount }}</p>
                <p><strong>Estimated Print Time:</strong> {{ estimatedPrintTime }}</p>
              </div>
            </div>
            
            <!-- Actions -->
            <div class="flex justify-between items-center pt-4">
              <button type="button" 
                      @click="showIndividualSettings = !showIndividualSettings"
                      class="text-sm text-blue-600 dark:text-blue-400 hover:underline">
                {{ showIndividualSettings ? 'Hide' : 'Show' }} Individual Settings
              </button>
              
              <div class="flex space-x-3">
                <button type="button" 
                        @click="showBatchSettings = false"
                        class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                  Cancel
                </button>
                
                <button type="submit" 
                        :disabled="printing || activeBatchCount === 0"
                        class="px-4 py-2 bg-green-600 border border-transparent rounded-md text-sm font-medium text-white hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 disabled:opacity-50">
                  <i v-if="printing" class="fas fa-spinner fa-spin mr-2"></i>
                  <i v-else class="fas fa-print mr-2"></i>
                  {{ printing ? 'Printing...' : `Print ${totalLabelsCount} Labels` }}
                </button>
              </div>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'BatchPrintButton',
  props: {
    selectedProducts: {
      type: Array,
      default: () => []
    },
    templates: {
      type: Array,
      default: () => []
    },
    printers: {
      type: Array,
      default: () => []
    },
    defaultPrinterId: {
      type: [Number, String],
      default: null
    },
    defaultTemplateId: {
      type: [Number, String],
      default: null
    }
  },
  data() {
    return {
      printing: false,
      showBatchSettings: false,
      showIndividualSettings: false,
      batchSettings: {
        quantity: 1,
        template_id: this.defaultTemplateId,
        printer_id: this.defaultPrinterId
      },
      individualSettings: {}
    }
  },
  computed: {
    hasDefaultPrinter() {
      return this.printers.some(p => p.is_default && p.is_active);
    },
    activeBatchCount() {
      return this.selectedProducts.filter(p => !this.individualSettings[p.id]?.skip).length;
    },
    totalLabelsCount() {
      let total = 0;
      this.selectedProducts.forEach(product => {
        if (!this.individualSettings[product.id]?.skip) {
          total += this.individualSettings[product.id]?.quantity || this.batchSettings.quantity;
        }
      });
      return total;
    },
    estimatedPrintTime() {
      const labelsPerMinute = 10; // Adjust based on your printer speed
      const minutes = Math.ceil(this.totalLabelsCount / labelsPerMinute);
      if (minutes < 1) return '< 1 minute';
      if (minutes === 1) return '1 minute';
      if (minutes < 60) return `${minutes} minutes`;
      const hours = Math.floor(minutes / 60);
      const remainingMinutes = minutes % 60;
      return remainingMinutes > 0 ? `${hours}h ${remainingMinutes}m` : `${hours}h`;
    }
  },
  watch: {
    selectedProducts: {
      handler(products) {
        // Initialize individual settings for new products
        products.forEach(product => {
          if (!this.individualSettings[product.id]) {
            this.$set(this.individualSettings, product.id, {
              quantity: this.batchSettings.quantity,
              skip: false
            });
          }
        });
      },
      immediate: true,
      deep: true
    },
    'batchSettings.quantity'(newVal) {
      // Update all individual quantities when batch quantity changes
      Object.keys(this.individualSettings).forEach(productId => {
        this.individualSettings[productId].quantity = newVal;
      });
    }
  },
  methods: {
    async quickBatchPrint() {
      this.printing = true;
      try {
        const response = await axios.post('/labels/print-batch', {
          product_ids: this.selectedProducts.map(p => p.id),
          quantity: 1,
          template_id: this.defaultTemplateId,
          printer_id: this.defaultPrinterId
        });
        
        this.$toast.success(`${response.data.successful_jobs} label print jobs queued`);
        this.$emit('batch-printed', {
          products: this.selectedProducts,
          quantity: 1,
          total_jobs: response.data.total_jobs,
          successful_jobs: response.data.successful_jobs
        });
      } catch (error) {
        this.$toast.error('Error printing labels: ' + (error.response?.data?.message || error.message));
      } finally {
        this.printing = false;
      }
    },
    
    async confirmBatchPrint() {
      this.printing = true;
      try {
        // Prepare batch data with individual settings
        const batchData = {
          products: this.selectedProducts
            .filter(p => !this.individualSettings[p.id]?.skip)
            .map(p => ({
              product_id: p.id,
              quantity: this.individualSettings[p.id]?.quantity || this.batchSettings.quantity
            })),
          template_id: this.batchSettings.template_id,
          printer_id: this.batchSettings.printer_id
        };
        
        const response = await axios.post('/labels/print-batch-advanced', batchData);
        
        this.$toast.success(`${response.data.successful_jobs} of ${response.data.total_jobs} print jobs queued`);
        this.showBatchSettings = false;
        this.$emit('batch-printed', {
          products: this.selectedProducts,
          settings: this.batchSettings,
          individual_settings: this.individualSettings,
          total_jobs: response.data.total_jobs,
          successful_jobs: response.data.successful_jobs
        });
      } catch (error) {
        this.$toast.error('Error printing labels: ' + (error.response?.data?.message || error.message));
      } finally {
        this.printing = false;
      }
    }
  }
}
</script>
