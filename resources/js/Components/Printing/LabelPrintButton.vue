<template>
  <div class="label-print-actions">
    <!-- Single Print Button -->
    <button @click="showPrintSettings = true" 
            :disabled="printing"
            class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed">
      <i class="fas fa-print mr-2"></i>
      {{ printing ? 'Printing...' : 'Print Label' }}
    </button>

    <!-- Quick Print (uses defaults) -->
    <button v-if="hasDefaultPrinter" 
            @click="quickPrint"
            :disabled="printing"
            class="inline-flex items-center px-2 py-2 border border-gray-300 dark:border-gray-600 text-sm font-medium rounded-md text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50 ml-2"
            title="Quick print with default settings">
      <i class="fas fa-bolt"></i>
    </button>

    <!-- Print Settings Modal -->
    <div v-if="showPrintSettings" 
         class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50"
         @click.self="showPrintSettings = false">
      <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white dark:bg-gray-800">
        <div class="mt-3">
          <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">
            Print Label Settings
          </h3>
          
          <form @submit.prevent="confirmPrint" class="space-y-4">
            <!-- Quantity -->
            <div>
              <label for="quantity" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                Quantity
              </label>
              <input id="quantity" 
                     v-model.number="printSettings.quantity" 
                     type="number" 
                     min="1" 
                     max="100" 
                     class="block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-gray-100 sm:text-sm">
            </div>
            
            <!-- Template -->
            <div>
              <label for="template" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                Template
              </label>
              <select id="template" 
                      v-model="printSettings.template_id"
                      class="block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-gray-100 sm:text-sm">
                <option value="">Default Template</option>
                <option v-for="template in templates" 
                        :key="template.id" 
                        :value="template.id">
                  {{ template.name }}
                </option>
              </select>
            </div>
            
            <!-- Printer -->
            <div>
              <label for="printer" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                Printer
              </label>
              <select id="printer" 
                      v-model="printSettings.printer_id"
                      class="block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-gray-100 sm:text-sm">
                <option value="">Default Printer</option>
                <option v-for="printer in printers" 
                        :key="printer.id" 
                        :value="printer.id">
                  {{ printer.name }}
                  <span v-if="!printer.is_active" class="text-red-500">(Offline)</span>
                </option>
              </select>
            </div>
            
            <!-- Label Preview -->
            <div v-if="showPreview" class="border border-gray-200 dark:border-gray-700 rounded-lg p-3">
              <h4 class="text-sm font-medium text-gray-900 dark:text-gray-100 mb-2">Preview</h4>
              <LabelPreview :product="product" 
                           :template="selectedTemplate" 
                           class="max-w-full" />
            </div>
            
            <!-- Actions -->
            <div class="flex justify-end space-x-3 pt-4">
              <button type="button" 
                      @click="showPrintSettings = false"
                      class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                Cancel
              </button>
              
              <button type="button" 
                      @click="showPreview = !showPreview"
                      class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                {{ showPreview ? 'Hide Preview' : 'Show Preview' }}
              </button>
              
              <button type="submit" 
                      :disabled="printing"
                      class="px-4 py-2 bg-blue-600 border border-transparent rounded-md text-sm font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50">
                <i v-if="printing" class="fas fa-spinner fa-spin mr-2"></i>
                <i v-else class="fas fa-print mr-2"></i>
                {{ printing ? 'Printing...' : 'Print' }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import LabelPreview from './LabelPreview.vue'

export default {
  name: 'LabelPrintButton',
  components: {
    LabelPreview
  },
  props: {
    product: {
      type: Object,
      required: true
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
      showPrintSettings: false,
      showPreview: false,
      printSettings: {
        quantity: 1,
        template_id: this.defaultTemplateId,
        printer_id: this.defaultPrinterId
      }
    }
  },
  computed: {
    hasDefaultPrinter() {
      return this.printers.some(p => p.is_default && p.is_active);
    },
    selectedTemplate() {
      if (!this.printSettings.template_id) {
        return this.templates.find(t => t.is_default);
      }
      return this.templates.find(t => t.id === this.printSettings.template_id);
    }
  },
  watch: {
    defaultPrinterId(newVal) {
      this.printSettings.printer_id = newVal;
    },
    defaultTemplateId(newVal) {
      this.printSettings.template_id = newVal;
    }
  },
  methods: {
    async quickPrint() {
      this.printing = true;
      try {
        const response = await axios.post(`/products/${this.product.id}/print-label`, {
          quantity: 1,
          template_id: this.defaultTemplateId,
          printer_id: this.defaultPrinterId
        });
        
        if (response.data.success) {
          this.$toast.success('Label print job queued successfully');
          this.$emit('label-printed', {
            product: this.product,
            quantity: 1,
            job_id: response.data.job_id
          });
        } else {
          this.$toast.error('Failed to queue print job');
        }
      } catch (error) {
        this.$toast.error('Error printing label: ' + (error.response?.data?.message || error.message));
      } finally {
        this.printing = false;
      }
    },
    
    async confirmPrint() {
      this.printing = true;
      try {
        const response = await axios.post(`/products/${this.product.id}/print-label`, this.printSettings);
        
        if (response.data.success) {
          this.$toast.success(`${this.printSettings.quantity} label(s) queued for printing`);
          this.showPrintSettings = false;
          this.$emit('label-printed', {
            product: this.product,
            quantity: this.printSettings.quantity,
            settings: this.printSettings,
            job_id: response.data.job_id
          });
        } else {
          this.$toast.error('Failed to queue print job');
        }
      } catch (error) {
        this.$toast.error('Error printing label: ' + (error.response?.data?.message || error.message));
      } finally {
        this.printing = false;
      }
    }
  }
}
</script>
