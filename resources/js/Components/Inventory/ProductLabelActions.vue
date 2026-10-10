<template>
  <div class="product-label-actions">
    <!-- Single Product Actions -->
    <div v-if="product && !isBatch" class="flex items-center space-x-2">
      <!-- Quick Print Button (uses defaults) -->
      <button v-if="hasDefaultSettings" 
              @click="quickPrint"
              :disabled="printing"
              class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed"
              title="Quick print with default settings">
        <i v-if="printing" class="fas fa-spinner fa-spin mr-2"></i>
        <i v-else class="fas fa-bolt mr-2"></i>
        {{ printing ? 'Printing...' : 'Quick Print' }}
      </button>

      <!-- Settings Print Button -->
      <button @click="showPrintModal = true" 
              :disabled="printing"
              class="inline-flex items-center px-3 py-2 border border-gray-300 dark:border-gray-600 text-sm font-medium rounded-md text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50">
        <i class="fas fa-print mr-2"></i>
        Print Label
      </button>

      <!-- Label History -->
      <button v-if="showHistory && product.labels_printed_count > 0" 
              @click="showLabelHistory = true"
              class="inline-flex items-center px-2 py-2 border border-gray-300 dark:border-gray-600 text-sm font-medium rounded-md text-gray-500 dark:text-gray-400 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
              title="View label printing history">
        <i class="fas fa-history mr-1"></i>
        {{ product.labels_printed_count }}
      </button>

      <!-- Auto-print Toggle -->
      <button v-if="allowToggleAutoPrint" 
              @click="toggleAutoPrint"
              :disabled="togglingAutoPrint"
              :class="product.auto_print_labels ? 
                'text-green-600 dark:text-green-400 border-green-300 dark:border-green-600' : 
                'text-gray-500 dark:text-gray-400 border-gray-300 dark:border-gray-600'"
              class="inline-flex items-center px-2 py-2 border text-sm font-medium rounded-md bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50"
              :title="product.auto_print_labels ? 'Disable auto-print' : 'Enable auto-print'">
        <i v-if="togglingAutoPrint" class="fas fa-spinner fa-spin"></i>
        <i v-else :class="product.auto_print_labels ? 'fas fa-toggle-on' : 'fas fa-toggle-off'"></i>
      </button>
    </div>

    <!-- Batch Actions -->
    <div v-if="isBatch && selectedProducts.length > 0" class="flex items-center space-x-3">
      <!-- Quick Batch Print -->
      <button v-if="hasDefaultSettings" 
              @click="quickBatchPrint"
              :disabled="printing"
              class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 disabled:opacity-50">
        <i v-if="printing" class="fas fa-spinner fa-spin mr-2"></i>
        <i v-else class="fas fa-bolt mr-2"></i>
        {{ printing ? 'Printing...' : `Quick Print ${selectedProducts.length}` }}
      </button>

      <!-- Batch Settings Print -->
      <button @click="showBatchModal = true" 
              :disabled="printing"
              class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 text-sm font-medium rounded-md text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50">
        <i class="fas fa-print mr-2"></i>
        Batch Print Settings
      </button>

      <!-- Batch Auto-print Toggle -->
      <button v-if="allowToggleAutoPrint" 
              @click="toggleBatchAutoPrint"
              :disabled="togglingAutoPrint"
              class="inline-flex items-center px-3 py-2 border border-gray-300 dark:border-gray-600 text-sm font-medium rounded-md text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50">
        <i v-if="togglingAutoPrint" class="fas fa-spinner fa-spin mr-2"></i>
        <i v-else class="fas fa-magic mr-2"></i>
        Toggle Auto-print
      </button>
    </div>

    <!-- No Selection Message -->
    <div v-if="isBatch && selectedProducts.length === 0" class="text-sm text-gray-500 dark:text-gray-400">
      Select products to enable batch printing
    </div>

    <!-- Single Product Print Modal -->
    <PrintSettingsModal v-if="product"
                       :visible="showPrintModal"
                       :product="product"
                       :templates="templates"
                       :printers="printers"
                       :default-settings="defaultPrintSettings"
                       :printing="printing"
                       @close="showPrintModal = false"
                       @print="handleSinglePrint" />

    <!-- Batch Print Modal -->
    <div v-if="showBatchModal" 
         class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50"
         @click.self="showBatchModal = false">
      <div class="relative top-10 mx-auto p-5 border w-full max-w-3xl shadow-lg rounded-md bg-white dark:bg-gray-800">
        <div class="mt-3">
          <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">
              Batch Print Settings
            </h3>
            <button @click="showBatchModal = false" 
                    class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">
              <i class="fas fa-times"></i>
            </button>
          </div>
          
          <BatchPrintButton :selected-products="selectedProducts"
                           :templates="templates"
                           :printers="printers"
                           :default-printer-id="defaultPrinterId"
                           :default-template-id="defaultTemplateId"
                           @batch-printed="handleBatchPrinted"
                           class="w-full" />
        </div>
      </div>
    </div>

    <!-- Label History Modal -->
    <div v-if="showLabelHistory" 
         class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50"
         @click.self="showLabelHistory = false">
      <div class="relative top-20 mx-auto p-5 border w-full max-w-2xl shadow-lg rounded-md bg-white dark:bg-gray-800">
        <div class="mt-3">
          <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">
              Label Print History - {{ product.name }}
            </h3>
            <button @click="showLabelHistory = false" 
                    class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">
              <i class="fas fa-times"></i>
            </button>
          </div>
          
          <div class="space-y-3 max-h-96 overflow-y-auto">
            <div v-for="job in labelHistory" 
                 :key="job.id" 
                 class="flex items-center justify-between p-3 border border-gray-200 dark:border-gray-700 rounded-lg">
              <div>
                <div class="text-sm font-medium text-gray-900 dark:text-gray-100">
                  {{ formatJobType(job.job_type) }}
                </div>
                <div class="text-xs text-gray-500 dark:text-gray-400">
                  {{ formatDate(job.created_at) }} • {{ job.quantity }} label(s)
                </div>
                <div v-if="job.printer" class="text-xs text-gray-500 dark:text-gray-400">
                  {{ job.printer.name }}
                </div>
              </div>
              <div class="flex items-center space-x-2">
                <PrintJobStatus :status="job.status" />
                <Link v-if="allowViewJobs" 
                      :href="route('printing.jobs.show', job.id)"
                      class="text-blue-600 dark:text-blue-400 hover:underline text-xs">
                  View
                </Link>
              </div>
            </div>
            
            <div v-if="labelHistory.length === 0" class="text-center py-8 text-gray-500 dark:text-gray-400">
              <i class="fas fa-history text-2xl mb-2"></i>
              <p class="text-sm">No label printing history</p>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Status Messages -->
    <div v-if="statusMessage" 
         class="mt-2 text-xs"
         :class="statusMessage.type === 'error' ? 'text-red-600 dark:text-red-400' : 
                 statusMessage.type === 'success' ? 'text-green-600 dark:text-green-400' :
                 'text-blue-600 dark:text-blue-400'">
      <i :class="statusMessage.type === 'error' ? 'fas fa-exclamation-triangle' :
                statusMessage.type === 'success' ? 'fas fa-check-circle' :
                'fas fa-info-circle'" class="mr-1"></i>
      {{ statusMessage.message }}
    </div>
  </div>
</template>

<script>
import { Link } from '@inertiajs/vue3'
import PrintSettingsModal from './PrintSettingsModal.vue'
import BatchPrintButton from './BatchPrintButton.vue'
import PrintJobStatus from './PrintJobStatus.vue'

export default {
  name: 'ProductLabelActions',
  components: {
    Link,
    PrintSettingsModal,
    BatchPrintButton,
    PrintJobStatus
  },
  props: {
    // Single product mode
    product: {
      type: Object,
      default: null
    },
    // Batch mode
    selectedProducts: {
      type: Array,
      default: () => []
    },
    isBatch: {
      type: Boolean,
      default: false
    },
    // Settings
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
    },
    // Features
    showHistory: {
      type: Boolean,
      default: true
    },
    allowToggleAutoPrint: {
      type: Boolean,
      default: true
    },
    allowViewJobs: {
      type: Boolean,
      default: true
    },
    // Styling
    size: {
      type: String,
      default: 'md', // sm, md, lg
      validator: value => ['sm', 'md', 'lg'].includes(value)
    }
  },
  data() {
    return {
      printing: false,
      togglingAutoPrint: false,
      showPrintModal: false,
      showBatchModal: false,
      showLabelHistory: false,
      labelHistory: [],
      statusMessage: null,
      statusTimeout: null
    }
  },
  computed: {
    hasDefaultSettings() {
      return this.defaultPrinterId && this.printers.some(p => p.id == this.defaultPrinterId && p.is_active);
    },
    defaultPrintSettings() {
      return {
        quantity: 1,
        template_id: this.defaultTemplateId,
        printer_id: this.defaultPrinterId
      };
    }
  },
  watch: {
    showLabelHistory(newVal) {
      if (newVal && this.product) {
        this.loadLabelHistory();
      }
    }
  },
  methods: {
    async quickPrint() {
      if (!this.product || !this.hasDefaultSettings) return;
      
      this.printing = true;
      this.clearStatusMessage();
      
      try {
        const response = await axios.post(`/products/${this.product.id}/print-label`, {
          quantity: 1,
          template_id: this.defaultTemplateId,
          printer_id: this.defaultPrinterId
        });
        
        if (response.data.success) {
          this.showStatusMessage('Label queued for printing', 'success');
          this.$emit('label-printed', {
            product: this.product,
            job: response.data.job
          });
        } else {
          this.showStatusMessage('Failed to queue print job', 'error');
        }
      } catch (error) {
        this.showStatusMessage(`Print error: ${error.response?.data?.message || error.message}`, 'error');
      } finally {
        this.printing = false;
      }
    },
    
    async quickBatchPrint() {
      if (!this.selectedProducts.length || !this.hasDefaultSettings) return;
      
      this.printing = true;
      this.clearStatusMessage();
      
      try {
        const response = await axios.post('/labels/print-batch', {
          product_ids: this.selectedProducts.map(p => p.id),
          quantity: 1,
          template_id: this.defaultTemplateId,
          printer_id: this.defaultPrinterId
        });
        
        this.showStatusMessage(`${response.data.successful_jobs} labels queued for printing`, 'success');
        this.$emit('batch-printed', {
          products: this.selectedProducts,
          jobs: response.data.jobs
        });
      } catch (error) {
        this.showStatusMessage(`Batch print error: ${error.response?.data?.message || error.message}`, 'error');
      } finally {
        this.printing = false;
      }
    },
    
    async handleSinglePrint(settings) {
      this.printing = true;
      this.clearStatusMessage();
      
      try {
        const response = await axios.post(`/products/${this.product.id}/print-label`, settings);
        
        if (response.data.success) {
          this.showStatusMessage(`${settings.quantity} label(s) queued for printing`, 'success');
          this.showPrintModal = false;
          this.$emit('label-printed', {
            product: this.product,
            settings,
            job: response.data.job
          });
        } else {
          this.showStatusMessage('Failed to queue print job', 'error');
        }
      } catch (error) {
        this.showStatusMessage(`Print error: ${error.response?.data?.message || error.message}`, 'error');
      } finally {
        this.printing = false;
      }
    },
    
    handleBatchPrinted(event) {
      this.showBatchModal = false;
      this.showStatusMessage(`${event.successful_jobs} of ${event.total_jobs} jobs queued`, 'success');
      this.$emit('batch-printed', event);
    },
    
    async toggleAutoPrint() {
      if (!this.product) return;
      
      this.togglingAutoPrint = true;
      this.clearStatusMessage();
      
      try {
        await axios.patch(`/products/${this.product.id}`, {
          auto_print_labels: !this.product.auto_print_labels
        });
        
        this.product.auto_print_labels = !this.product.auto_print_labels;
        this.showStatusMessage(
          `Auto-print ${this.product.auto_print_labels ? 'enabled' : 'disabled'}`,
          'info'
        );
        
        this.$emit('auto-print-toggled', {
          product: this.product,
          enabled: this.product.auto_print_labels
        });
      } catch (error) {
        this.showStatusMessage(`Failed to toggle auto-print: ${error.response?.data?.message || error.message}`, 'error');
      } finally {
        this.togglingAutoPrint = false;
      }
    },
    
    async toggleBatchAutoPrint() {
      if (!this.selectedProducts.length) return;
      
      this.togglingAutoPrint = true;
      this.clearStatusMessage();
      
      try {
        const productIds = this.selectedProducts.map(p => p.id);
        const response = await axios.patch('/products/batch-update', {
          product_ids: productIds,
          auto_print_labels: 'toggle'
        });
        
        this.showStatusMessage(`Auto-print settings updated for ${response.data.updated_count} products`, 'success');
        this.$emit('batch-auto-print-toggled', {
          products: this.selectedProducts,
          updated_count: response.data.updated_count
        });
      } catch (error) {
        this.showStatusMessage(`Failed to toggle auto-print: ${error.response?.data?.message || error.message}`, 'error');
      } finally {
        this.togglingAutoPrint = false;
      }
    },
    
    async loadLabelHistory() {
      if (!this.product) return;
      
      try {
        const response = await axios.get(`/products/${this.product.id}/print-history`);
        this.labelHistory = response.data.jobs || [];
      } catch (error) {
        console.error('Failed to load label history:', error);
        this.labelHistory = [];
      }
    },
    
    showStatusMessage(message, type = 'info') {
      this.statusMessage = { message, type };
      
      if (this.statusTimeout) {
        clearTimeout(this.statusTimeout);
      }
      
      this.statusTimeout = setTimeout(() => {
        this.statusMessage = null;
      }, 5000);
    },
    
    clearStatusMessage() {
      this.statusMessage = null;
      if (this.statusTimeout) {
        clearTimeout(this.statusTimeout);
        this.statusTimeout = null;
      }
    },
    
    formatJobType(type) {
      switch (type) {
        case 'auto_sku': return 'Auto (SKU Creation)';
        case 'on_demand': return 'On Demand';
        case 'batch': return 'Batch Print';
        case 'location': return 'Location Label';
        default: return type;
      }
    },
    
    formatDate(date) {
      return new Date(date).toLocaleDateString();
    }
  },
  beforeDestroy() {
    if (this.statusTimeout) {
      clearTimeout(this.statusTimeout);
    }
  }
}
</script>
