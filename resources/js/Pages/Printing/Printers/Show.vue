<template>
  <AppLayout :title="`Printer: ${printer.name}`">
    <template #header>
      <div class="flex justify-between items-center">
        <div class="flex items-center space-x-4">
          <Link :href="route('printing.printers.index')" 
                class="text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300">
            <i class="fas fa-arrow-left"></i>
          </Link>
          <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ printer.name }}
          </h2>
          <PrinterStatus :printer="printer" 
                        :show-details="true" 
                        @status-updated="handleStatusUpdate" />
        </div>
        <div class="flex space-x-3">
          <button @click="testPrinter" 
                  :disabled="testing"
                  class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50">
            <i v-if="testing" class="fas fa-spinner fa-spin mr-2"></i>
            <i v-else class="fas fa-vial mr-2"></i>
            Test Connection
          </button>
          <Link :href="route('printing.printers.edit', printer.id)" 
                class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 active:bg-blue-900 focus:outline-none focus:border-blue-900 focus:ring ring-blue-300 transition ease-in-out duration-150">
            <i class="fas fa-edit mr-2"></i>
            Edit
          </Link>
        </div>
      </div>
    </template>

    <div class="py-12">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
        
        <!-- Printer Details -->
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg">
          <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">
              Printer Configuration
            </h3>
          </div>
          <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
              <!-- Basic Info -->
              <div class="space-y-4">
                <div>
                  <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Name</label>
                  <p class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ printer.name }}</p>
                </div>
                
                <div>
                  <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Connection Type</label>
                  <p class="mt-1 text-sm text-gray-900 dark:text-gray-100">
                    {{ formatPrinterType(printer.printer_type) }}
                  </p>
                </div>
                
                <div>
                  <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Connection String</label>
                  <p class="mt-1 text-sm text-gray-900 dark:text-gray-100 font-mono bg-gray-100 dark:bg-gray-700 px-2 py-1 rounded">
                    {{ printer.connection_string }}
                  </p>
                </div>
                
                <div>
                  <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Driver</label>
                  <p class="mt-1 text-sm text-gray-900 dark:text-gray-100">
                    {{ formatDriverName(printer.driver) }}
                  </p>
                </div>
              </div>
              
              <!-- Settings -->
              <div class="space-y-4">
                <div>
                  <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Status</label>
                  <div class="mt-1 flex items-center space-x-2">
                    <span :class="printer.is_active ? 'text-green-600' : 'text-red-600'">
                      <i :class="printer.is_active ? 'fas fa-play' : 'fas fa-stop'"></i>
                      {{ printer.is_active ? 'Active' : 'Disabled' }}
                    </span>
                    <span v-if="printer.is_default" 
                          class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-800 dark:text-blue-100">
                      <i class="fas fa-star mr-1"></i>
                      Default Printer
                    </span>
                  </div>
                </div>
                
                <div v-if="printer.print_settings">
                  <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Print Settings</label>
                  <div class="mt-1 space-y-1">
                    <p v-if="printer.print_settings.dpi" class="text-sm text-gray-900 dark:text-gray-100">
                      <strong>DPI:</strong> {{ printer.print_settings.dpi }}
                    </p>
                    <p v-if="printer.print_settings.speed" class="text-sm text-gray-900 dark:text-gray-100">
                      <strong>Speed:</strong> {{ printer.print_settings.speed }}
                    </p>
                    <p v-if="printer.print_settings.darkness" class="text-sm text-gray-900 dark:text-gray-100">
                      <strong>Darkness:</strong> {{ printer.print_settings.darkness }}
                    </p>
                  </div>
                </div>
                
                <div v-if="printer.created_at">
                  <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Added</label>
                  <p class="mt-1 text-sm text-gray-900 dark:text-gray-100">
                    {{ formatDate(printer.created_at) }}
                  </p>
                </div>
                
                <div v-if="printer.last_used_at">
                  <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Last Used</label>
                  <p class="mt-1 text-sm text-gray-900 dark:text-gray-100">
                    {{ formatDate(printer.last_used_at) }}
                  </p>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Usage Statistics -->
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg">
          <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">
              Usage Statistics
            </h3>
          </div>
          <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
              <div class="text-center">
                <div class="text-2xl font-bold text-blue-600 dark:text-blue-400">
                  {{ statistics.total_jobs || 0 }}
                </div>
                <div class="text-sm text-gray-500 dark:text-gray-400">Total Jobs</div>
              </div>
              <div class="text-center">
                <div class="text-2xl font-bold text-green-600 dark:text-green-400">
                  {{ statistics.successful_jobs || 0 }}
                </div>
                <div class="text-sm text-gray-500 dark:text-gray-400">Successful</div>
              </div>
              <div class="text-center">
                <div class="text-2xl font-bold text-red-600 dark:text-red-400">
                  {{ statistics.failed_jobs || 0 }}
                </div>
                <div class="text-sm text-gray-500 dark:text-gray-400">Failed</div>
              </div>
              <div class="text-center">
                <div class="text-2xl font-bold text-purple-600 dark:text-purple-400">
                  {{ statistics.success_rate || 0 }}%
                </div>
                <div class="text-sm text-gray-500 dark:text-gray-400">Success Rate</div>
              </div>
            </div>
          </div>
        </div>

        <!-- Recent Print Jobs -->
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg">
          <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
            <div class="flex justify-between items-center">
              <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                Recent Print Jobs
              </h3>
              <Link :href="route('printing.jobs.index', { printer_id: printer.id })" 
                    class="text-blue-600 dark:text-blue-400 hover:underline text-sm">
                View All Jobs
              </Link>
            </div>
          </div>

          <div v-if="recentJobs.length === 0" class="p-6 text-center">
            <div class="text-gray-500 dark:text-gray-400">
              <i class="fas fa-history text-3xl mb-2"></i>
              <p class="text-sm">No print jobs found for this printer</p>
            </div>
          </div>

          <div v-else class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
              <thead class="bg-gray-50 dark:bg-gray-700">
                <tr>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                    Product
                  </th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                    Template
                  </th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                    Quantity
                  </th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                    Status
                  </th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                    Created
                  </th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                    Actions
                  </th>
                </tr>
              </thead>
              <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                <tr v-for="job in recentJobs" :key="job.id">
                  <td class="px-6 py-4 whitespace-nowrap">
                    <div v-if="job.product" class="text-sm">
                      <div class="font-medium text-gray-900 dark:text-gray-100">
                        {{ job.product.name }}
                      </div>
                      <div class="text-gray-500 dark:text-gray-400">
                        {{ job.product.internal_sku || job.product.sku }}
                      </div>
                    </div>
                    <div v-else class="text-sm text-gray-500 dark:text-gray-400">
                      Product deleted
                    </div>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                    {{ job.template?.name || 'Default' }}
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                    {{ job.quantity }}
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap">
                    <PrintJobStatus :status="job.status" />
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                    {{ formatDate(job.created_at) }}
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                    <div class="flex space-x-2">
                      <Link :href="route('printing.jobs.show', job.id)" 
                            class="text-blue-600 dark:text-blue-400 hover:underline">
                        View
                      </Link>
                      <button v-if="job.status === 'failed'" 
                              @click="retryJob(job)"
                              :disabled="retryingJobs[job.id]"
                              class="text-green-600 dark:text-green-400 hover:underline disabled:opacity-50">
                        <i v-if="retryingJobs[job.id]" class="fas fa-spinner fa-spin mr-1"></i>
                        Retry
                      </button>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Test Print Section -->
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg">
          <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">
              Test Print
            </h3>
          </div>
          <div class="p-6">
            <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
              Print a test label to verify printer functionality.
            </p>
            <div class="flex items-center space-x-4">
              <select v-model="testTemplate" 
                      class="block px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                <option value="">Select Template</option>
                <option v-for="template in templates" 
                        :key="template.id" 
                        :value="template.id">
                  {{ template.name }}
                </option>
              </select>
              
              <button @click="printTestLabel" 
                      :disabled="printingTest || !testTemplate"
                      class="inline-flex items-center px-4 py-2 bg-green-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-700 active:bg-green-900 focus:outline-none focus:border-green-900 focus:ring ring-green-300 disabled:opacity-25 transition ease-in-out duration-150">
                <i v-if="printingTest" class="fas fa-spinner fa-spin mr-2"></i>
                <i v-else class="fas fa-print mr-2"></i>
                {{ printingTest ? 'Printing...' : 'Print Test Label' }}
              </button>
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
import PrinterStatus from '@/Components/Printing/PrinterStatus.vue'
import PrintJobStatus from '@/Components/Printing/PrintJobStatus.vue'

export default {
  name: 'PrinterShow',
  components: {
    AppLayout,
    Link,
    PrinterStatus,
    PrintJobStatus
  },
  props: {
    printer: {
      type: Object,
      required: true
    },
    statistics: {
      type: Object,
      default: () => ({})
    },
    recentJobs: {
      type: Array,
      default: () => []
    },
    templates: {
      type: Array,
      default: () => []
    }
  },
  data() {
    return {
      testing: false,
      printingTest: false,
      testTemplate: '',
      retryingJobs: {}
    }
  },
  methods: {
    formatPrinterType(type) {
      switch (type) {
        case 'network': return 'Network';
        case 'usb': return 'USB';
        case 'cups': return 'CUPS';
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
    
    formatDate(date) {
      return new Date(date).toLocaleString();
    },
    
    async testPrinter() {
      this.testing = true;
      
      try {
        const response = await axios.post(`/printers/${this.printer.id}/test`);
        
        if (response.data.success) {
          this.$toast.success('Printer connection successful');
        } else {
          this.$toast.error('Printer connection failed');
        }
      } catch (error) {
        this.$toast.error(`Test failed: ${error.response?.data?.message || error.message}`);
      } finally {
        this.testing = false;
      }
    },
    
    async printTestLabel() {
      this.printingTest = true;
      
      try {
        const response = await axios.post(`/printers/${this.printer.id}/test-print`, {
          template_id: this.testTemplate
        });
        
        if (response.data.success) {
          this.$toast.success('Test label print job queued');
        } else {
          this.$toast.error('Failed to queue test print job');
        }
      } catch (error) {
        this.$toast.error(`Test print failed: ${error.response?.data?.message || error.message}`);
      } finally {
        this.printingTest = false;
      }
    },
    
    async retryJob(job) {
      this.$set(this.retryingJobs, job.id, true);
      
      try {
        const response = await axios.post(`/print-jobs/${job.id}/retry`);
        
        if (response.data.success) {
          this.$toast.success('Print job retry queued');
          job.status = 'pending';
        } else {
          this.$toast.error('Failed to retry print job');
        }
      } catch (error) {
        this.$toast.error(`Retry failed: ${error.response?.data?.message || error.message}`);
      } finally {
        this.$set(this.retryingJobs, job.id, false);
      }
    },
    
    handleStatusUpdate(event) {
      Object.assign(this.printer, event.printer);
    }
  }
}
</script>
