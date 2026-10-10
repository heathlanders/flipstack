<template>
  <AppLayout :title="`Print Job #${job.id}`">
    <template #header>
      <div class="flex justify-between items-center">
        <div class="flex items-center space-x-4">
          <Link :href="route('printing.jobs.index')" 
                class="text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300">
            <i class="fas fa-arrow-left"></i>
          </Link>
          <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            Print Job #{{ job.id }}
          </h2>
          <PrintJobStatus :status="job.status" />
        </div>
        <div class="flex space-x-3">
          <button v-if="job.status === 'failed'" 
                  @click="retryJob"
                  :disabled="retrying"
                  class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm font-medium text-green-700 dark:text-green-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 disabled:opacity-50">
            <i v-if="retrying" class="fas fa-spinner fa-spin mr-2"></i>
            <i v-else class="fas fa-redo mr-2"></i>
            Retry Job
          </button>
          <button v-if="['pending', 'processing'].includes(job.status)" 
                  @click="cancelJob"
                  :disabled="cancelling"
                  class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm font-medium text-red-700 dark:text-red-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 disabled:opacity-50">
            <i v-if="cancelling" class="fas fa-spinner fa-spin mr-2"></i>
            <i v-else class="fas fa-times mr-2"></i>
            Cancel Job
          </button>
        </div>
      </div>
    </template>

    <div class="py-12">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
        
        <!-- Job Details -->
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg">
          <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">
              Job Information
            </h3>
          </div>
          <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
              <!-- Basic Info -->
              <div class="space-y-4">
                <div>
                  <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Job ID</label>
                  <p class="mt-1 text-sm text-gray-900 dark:text-gray-100 font-mono">#{{ job.id }}</p>
                </div>
                
                <div>
                  <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Type</label>
                  <p class="mt-1 text-sm text-gray-900 dark:text-gray-100">
                    {{ formatJobType(job.job_type) }}
                  </p>
                </div>
                
                <div>
                  <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Quantity</label>
                  <p class="mt-1 text-sm text-gray-900 dark:text-gray-100">
                    {{ job.quantity }} label(s)
                  </p>
                </div>
                
                <div>
                  <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Status</label>
                  <div class="mt-1">
                    <PrintJobStatus :status="job.status" />
                  </div>
                </div>
                
                <div>
                  <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Created</label>
                  <p class="mt-1 text-sm text-gray-900 dark:text-gray-100">
                    {{ formatDate(job.created_at) }}
                  </p>
                </div>
                
                <div v-if="job.printed_at">
                  <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Printed</label>
                  <p class="mt-1 text-sm text-gray-900 dark:text-gray-100">
                    {{ formatDate(job.printed_at) }}
                  </p>
                </div>
                
                <div v-if="job.created_by">
                  <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Created By</label>
                  <p class="mt-1 text-sm text-gray-900 dark:text-gray-100">
                    {{ job.created_by.name || job.created_by.email }}
                  </p>
                </div>
              </div>
              
              <!-- Error Info -->
              <div v-if="job.status === 'failed' && job.error_message" class="space-y-4">
                <div>
                  <label class="block text-sm font-medium text-red-700 dark:text-red-300 mb-2">
                    Error Details
                  </label>
                  <div class="bg-red-50 dark:bg-red-900 border border-red-200 dark:border-red-700 rounded-lg p-4">
                    <div class="flex">
                      <div class="flex-shrink-0">
                        <i class="fas fa-exclamation-triangle text-red-400"></i>
                      </div>
                      <div class="ml-3">
                        <h4 class="text-sm font-medium text-red-800 dark:text-red-200">
                          Print Job Failed
                        </h4>
                        <div class="mt-2 text-sm text-red-700 dark:text-red-300">
                          <pre class="whitespace-pre-wrap">{{ job.error_message }}</pre>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Product Information -->
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg">
          <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">
              Product Information
            </h3>
          </div>
          <div class="p-6">
            <div v-if="job.product" class="grid grid-cols-1 md:grid-cols-2 gap-8">
              <div class="space-y-4">
                <div>
                  <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Product Name</label>
                  <p class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ job.product.name }}</p>
                </div>
                
                <div>
                  <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">SKU</label>
                  <p class="mt-1 text-sm text-gray-900 dark:text-gray-100 font-mono">
                    {{ job.product.sku }}
                  </p>
                </div>
                
                <div v-if="job.product.internal_sku">
                  <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Internal SKU</label>
                  <p class="mt-1 text-sm text-gray-900 dark:text-gray-100 font-mono">
                    {{ job.product.internal_sku }}
                  </p>
                </div>
                
                <div v-if="job.product.warehouse_location">
                  <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Location</label>
                  <p class="mt-1 text-sm text-gray-900 dark:text-gray-100">
                    {{ job.product.warehouse_location.location_code }}
                  </p>
                </div>
              </div>
              
              <div class="space-y-4">
                <div v-if="job.product.condition">
                  <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Condition</label>
                  <p class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ job.product.condition }}</p>
                </div>
                
                <div v-if="job.product.selling_price">
                  <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Price</label>
                  <p class="mt-1 text-sm text-gray-900 dark:text-gray-100">${{ job.product.selling_price }}</p>
                </div>
                
                <div>
                  <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Actions</label>
                  <div class="mt-1">
                    <Link :href="route('products.show', job.product.id)" 
                          class="text-blue-600 dark:text-blue-400 hover:underline text-sm">
                      View Product Details
                    </Link>
                  </div>
                </div>
              </div>
            </div>
            <div v-else class="text-center py-8 text-gray-500 dark:text-gray-400">
              <i class="fas fa-box-open text-3xl mb-2"></i>
              <p>Product has been deleted</p>
              <p class="text-sm">Product ID: {{ job.product_id }}</p>
            </div>
          </div>
        </div>

        <!-- Printer and Template Information -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <!-- Printer Info -->
          <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg">
            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
              <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                Printer
              </h3>
            </div>
            <div class="p-6">
              <div v-if="job.printer" class="space-y-4">
                <div>
                  <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Name</label>
                  <p class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ job.printer.name }}</p>
                </div>
                
                <div>
                  <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Driver</label>
                  <p class="mt-1 text-sm text-gray-900 dark:text-gray-100">
                    {{ formatDriverName(job.printer.driver) }}
                  </p>
                </div>
                
                <div>
                  <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Connection</label>
                  <p class="mt-1 text-sm text-gray-900 dark:text-gray-100 font-mono text-xs">
                    {{ job.printer.connection_string }}
                  </p>
                </div>
                
                <div>
                  <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Status</label>
                  <div class="mt-1">
                    <PrinterStatus :printer="job.printer" :show-test-button="false" />
                  </div>
                </div>
                
                <div class="pt-2">
                  <Link :href="route('printing.printers.show', job.printer.id)" 
                        class="text-blue-600 dark:text-blue-400 hover:underline text-sm">
                    View Printer Details
                  </Link>
                </div>
              </div>
              <div v-else class="text-center py-8 text-gray-500 dark:text-gray-400">
                <i class="fas fa-printer text-2xl mb-2"></i>
                <p class="text-sm">Printer information unavailable</p>
              </div>
            </div>
          </div>

          <!-- Template Info -->
          <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg">
            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
              <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                Template
              </h3>
            </div>
            <div class="p-6">
              <div v-if="job.template" class="space-y-4">
                <div>
                  <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Name</label>
                  <p class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ job.template.name }}</p>
                </div>
                
                <div>
                  <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Dimensions</label>
                  <p class="mt-1 text-sm text-gray-900 dark:text-gray-100">
                    {{ job.template.width_mm }}×{{ job.template.height_mm }}mm
                  </p>
                </div>
                
                <div v-if="job.template.description">
                  <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Description</label>
                  <p class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ job.template.description }}</p>
                </div>
                
                <div class="pt-2">
                  <Link :href="route('printing.templates.show', job.template.id)" 
                        class="text-blue-600 dark:text-blue-400 hover:underline text-sm">
                    View Template Details
                  </Link>
                </div>
              </div>
              <div v-else class="space-y-4">
                <p class="text-sm text-gray-500 dark:text-gray-400">
                  Default template was used
                </p>
              </div>
            </div>
          </div>
        </div>

        <!-- Label Preview -->
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg">
          <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">
              Label Preview
            </h3>
          </div>
          <div class="p-6">
            <div v-if="job.label_data && job.template && job.product" class="flex justify-center">
              <LabelPreview :template="job.template" 
                           :product="job.product" 
                           :scale="6"
                           :show-controls="true"
                           max-width="600px" />
            </div>
            <div v-else class="text-center py-8 text-gray-500 dark:text-gray-400">
              <i class="fas fa-eye-slash text-3xl mb-2"></i>
              <p>Label preview not available</p>
              <p class="text-sm">Missing label data, template, or product information</p>
            </div>
          </div>
        </div>

        <!-- Raw Label Data (Developer Info) -->
        <div v-if="job.label_data" class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg">
          <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
            <div class="flex justify-between items-center">
              <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                Label Data
              </h3>
              <button @click="showRawData = !showRawData"
                      class="text-sm text-blue-600 dark:text-blue-400 hover:underline">
                {{ showRawData ? 'Hide' : 'Show' }} Raw Data
              </button>
            </div>
          </div>
          <div v-if="showRawData" class="p-6">
            <pre class="bg-gray-100 dark:bg-gray-700 rounded-lg p-4 text-xs overflow-x-auto">{{ JSON.stringify(job.label_data, null, 2) }}</pre>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Link } from '@inertiajs/vue3'
import PrintJobStatus from '@/Components/Printing/PrintJobStatus.vue'
import PrinterStatus from '@/Components/Printing/PrinterStatus.vue'
import LabelPreview from '@/Components/Printing/LabelPreview.vue'

export default {
  name: 'JobShow',
  components: {
    AppLayout,
    Link,
    PrintJobStatus,
    PrinterStatus,
    LabelPreview
  },
  props: {
    job: {
      type: Object,
      required: true
    }
  },
  data() {
    return {
      retrying: false,
      cancelling: false,
      showRawData: false
    }
  },
  methods: {
    formatDate(date) {
      return new Date(date).toLocaleString();
    },
    
    formatJobType(type) {
      switch (type) {
        case 'auto_sku': return 'Automatic (SKU Creation)';
        case 'on_demand': return 'On Demand';
        case 'batch': return 'Batch Print';
        case 'location': return 'Location Label';
        default: return type;
      }
    },
    
    formatDriverName(driver) {
      switch (driver) {
        case 'zebra_zpl': return 'Zebra (ZPL)';
        case 'dymo_label': return 'Dymo LabelWriter';
        case 'brother_ptouch': return 'Brother P-touch';
        case 'generic_escp': return 'Generic ESC-P';
        case 'pdf': return 'PDF';
        default: return driver;
      }
    },
    
    async retryJob() {
      this.retrying = true;
      
      try {
        const response = await axios.post(`/printing/jobs/${this.job.id}/retry`);
        
        if (response.data.success) {
          this.$toast.success('Print job retry queued');
          this.job.status = 'pending';
          this.job.error_message = null;
        } else {
          this.$toast.error('Failed to retry print job');
        }
      } catch (error) {
        this.$toast.error(`Retry failed: ${error.response?.data?.message || error.message}`);
      } finally {
        this.retrying = false;
      }
    },
    
    async cancelJob() {
      this.cancelling = true;
      
      try {
        const response = await axios.post(`/printing/jobs/${this.job.id}/cancel`);
        
        if (response.data.success) {
          this.$toast.success('Print job cancelled');
          this.job.status = 'cancelled';
        } else {
          this.$toast.error('Failed to cancel print job');
        }
      } catch (error) {
        this.$toast.error(`Cancel failed: ${error.response?.data?.message || error.message}`);
      } finally {
        this.cancelling = false;
      }
    }
  }
}
</script>
