<template>
  <AppLayout title="Printer Management">
    <template #header>
      <div class="flex justify-between items-center">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
          Printer Management
        </h2>
        <div class="flex space-x-3">
          <Link :href="route('printing.printers.discovery')" 
                class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 active:bg-blue-900 focus:outline-none focus:border-blue-900 focus:ring ring-blue-300 transition ease-in-out duration-150">
            <i class="fas fa-search mr-2"></i>
            Discover Printers
          </Link>
          <Link :href="route('printing.printers.create')" 
                class="inline-flex items-center px-4 py-2 bg-green-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-700 active:bg-green-900 focus:outline-none focus:border-green-900 focus:ring ring-green-300 transition ease-in-out duration-150">
            <i class="fas fa-plus mr-2"></i>
            Add Printer
          </Link>
        </div>
      </div>
    </template>

    <div class="py-12">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
        
        <!-- Filters and Search -->
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg">
          <div class="p-6 border-b border-gray-200 dark:border-gray-700">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
              <!-- Search -->
              <div class="md:col-span-2">
                <label for="search" class="sr-only">Search printers</label>
                <div class="relative">
                  <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <i class="fas fa-search text-gray-400"></i>
                  </div>
                  <input id="search" 
                         v-model="filters.search"
                         type="text" 
                         placeholder="Search printers..."
                         class="block w-full pl-10 pr-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md leading-5 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 placeholder-gray-500 dark:placeholder-gray-400 focus:outline-none focus:placeholder-gray-400 dark:focus:placeholder-gray-500 focus:ring-1 focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                </div>
              </div>
              
              <!-- Status Filter -->
              <div>
                <label for="status-filter" class="sr-only">Filter by status</label>
                <select id="status-filter" 
                        v-model="filters.status"
                        class="block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                  <option value="">All Status</option>
                  <option value="online">Online</option>
                  <option value="offline">Offline</option>
                  <option value="busy">Busy</option>
                  <option value="error">Error</option>
                </select>
              </div>
              
              <!-- Driver Filter -->
              <div>
                <label for="driver-filter" class="sr-only">Filter by driver</label>
                <select id="driver-filter" 
                        v-model="filters.driver"
                        class="block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                  <option value="">All Drivers</option>
                  <option value="zebra_zpl">Zebra (ZPL)</option>
                  <option value="dymo_label">Dymo LabelWriter</option>
                  <option value="brother_ptouch">Brother P-touch</option>
                  <option value="generic_escp">Generic ESC-P</option>
                  <option value="pdf">PDF</option>
                </select>
              </div>
            </div>
          </div>
        </div>

        <!-- Printers List -->
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg">
          <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
            <div class="flex justify-between items-center">
              <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                Printers ({{ filteredPrinters.length }})
              </h3>
              <div class="flex space-x-2">
                <button @click="testAllPrinters" 
                        :disabled="testingAll"
                        class="inline-flex items-center px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50">
                  <i v-if="testingAll" class="fas fa-spinner fa-spin mr-2"></i>
                  <i v-else class="fas fa-vial mr-2"></i>
                  Test All
                </button>
                <button @click="refreshPrinters" 
                        :disabled="refreshing"
                        class="inline-flex items-center px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50">
                  <i v-if="refreshing" class="fas fa-spinner fa-spin mr-2"></i>
                  <i v-else class="fas fa-sync-alt mr-2"></i>
                  Refresh
                </button>
              </div>
            </div>
          </div>

          <div v-if="filteredPrinters.length === 0" class="p-6 text-center">
            <div class="text-gray-500 dark:text-gray-400">
              <i class="fas fa-printer text-4xl mb-4"></i>
              <p class="text-lg font-medium">No printers found</p>
              <p class="text-sm">
                {{ printers.length === 0 ? 'Get started by discovering or adding printers.' : 'Try adjusting your filters.' }}
              </p>
            </div>
          </div>

          <div v-else class="divide-y divide-gray-200 dark:divide-gray-700">
            <div v-for="printer in filteredPrinters" 
                 :key="printer.id" 
                 class="p-6 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
              
              <div class="flex items-center justify-between">
                <div class="flex items-center space-x-4">
                  <!-- Printer Icon -->
                  <div class="flex-shrink-0">
                    <div class="w-10 h-10 bg-gray-100 dark:bg-gray-600 rounded-lg flex items-center justify-center">
                      <i :class="getPrinterIcon(printer)" class="text-gray-600 dark:text-gray-300 text-xl"></i>
                    </div>
                  </div>
                  
                  <!-- Printer Info -->
                  <div class="flex-1 min-w-0">
                    <div class="flex items-center space-x-3">
                      <h4 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                        {{ printer.name }}
                      </h4>
                      <span v-if="printer.is_default" 
                            class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-800 dark:text-blue-100">
                        <i class="fas fa-star mr-1"></i>
                        Default
                      </span>
                      <PrinterStatus :printer="printer" 
                                   :show-test-button="false"
                                   @status-updated="handleStatusUpdate" />
                    </div>
                    
                    <div class="mt-1 text-sm text-gray-500 dark:text-gray-400 space-y-1">
                      <div class="flex items-center space-x-4">
                        <span><strong>Type:</strong> {{ formatPrinterType(printer.printer_type) }}</span>
                        <span><strong>Driver:</strong> {{ formatDriverName(printer.driver) }}</span>
                      </div>
                      <div>
                        <strong>Connection:</strong> {{ printer.connection_string }}
                      </div>
                      <div v-if="printer.print_settings" class="flex items-center space-x-4">
                        <span v-if="printer.print_settings.dpi">DPI: {{ printer.print_settings.dpi }}</span>
                        <span v-if="printer.print_settings.speed">Speed: {{ printer.print_settings.speed }}</span>
                        <span v-if="printer.print_settings.darkness">Darkness: {{ printer.print_settings.darkness }}</span>
                      </div>
                    </div>
                  </div>
                </div>
                
                <!-- Actions -->
                <div class="flex items-center space-x-3">
                  <!-- Print Job Count -->
                  <div class="text-center">
                    <div class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                      {{ printer.total_jobs_count || 0 }}
                    </div>
                    <div class="text-xs text-gray-500 dark:text-gray-400">Jobs</div>
                  </div>
                  
                  <!-- Action Buttons -->
                  <div class="flex space-x-2">
                    <button @click="testPrinter(printer)" 
                            :disabled="testingPrinters[printer.id]"
                            class="inline-flex items-center px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50"
                            title="Test connection">
                      <i v-if="testingPrinters[printer.id]" class="fas fa-spinner fa-spin"></i>
                      <i v-else class="fas fa-vial"></i>
                    </button>
                    
                    <button @click="togglePrinter(printer)" 
                            :disabled="togglingPrinters[printer.id]"
                            :class="printer.is_active ? 'text-yellow-600 hover:text-yellow-700' : 'text-green-600 hover:text-green-700'"
                            class="inline-flex items-center px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm font-medium bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50"
                            :title="printer.is_active ? 'Disable printer' : 'Enable printer'">
                      <i v-if="togglingPrinters[printer.id]" class="fas fa-spinner fa-spin"></i>
                      <i v-else-if="printer.is_active" class="fas fa-power-off"></i>
                      <i v-else class="fas fa-play"></i>
                    </button>
                    
                    <Link :href="route('printing.printers.show', printer.id)" 
                          class="inline-flex items-center px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                          title="View details">
                      <i class="fas fa-eye"></i>
                    </Link>
                    
                    <button @click="confirmDelete(printer)" 
                            class="inline-flex items-center px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm font-medium text-red-600 dark:text-red-400 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500"
                            title="Delete printer">
                      <i class="fas fa-trash-alt"></i>
                    </button>
                  </div>
                </div>
              </div>

              <!-- Last Used Info -->
              <div v-if="printer.last_used_at" class="mt-4 text-xs text-gray-500 dark:text-gray-400">
                Last used: {{ formatDate(printer.last_used_at) }}
                <span v-if="printer.last_job_status"> • Last job: {{ printer.last_job_status }}</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Delete Confirmation Modal -->
        <div v-if="printerToDelete" 
             class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50"
             @click.self="printerToDelete = null">
          <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white dark:bg-gray-800">
            <div class="mt-3 text-center">
              <i class="fas fa-exclamation-triangle text-red-400 text-4xl mb-4"></i>
              <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-2">
                Delete Printer
              </h3>
              <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">
                Are you sure you want to delete "{{ printerToDelete.name }}"? 
                This action cannot be undone.
              </p>
              <div class="flex justify-center space-x-3">
                <button @click="printerToDelete = null" 
                        class="px-4 py-2 bg-gray-300 dark:bg-gray-600 text-gray-700 dark:text-gray-300 rounded-md hover:bg-gray-400 dark:hover:bg-gray-500 focus:outline-none focus:ring-2 focus:ring-gray-500">
                  Cancel
                </button>
                <button @click="deletePrinter" 
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
import PrinterStatus from '@/Components/Printing/PrinterStatus.vue'

export default {
  name: 'PrinterIndex',
  components: {
    AppLayout,
    Link,
    PrinterStatus
  },
  props: {
    printers: {
      type: Array,
      default: () => []
    }
  },
  data() {
    return {
      filters: {
        search: '',
        status: '',
        driver: ''
      },
      testingPrinters: {},
      togglingPrinters: {},
      testingAll: false,
      refreshing: false,
      deleting: false,
      printerToDelete: null
    }
  },
  computed: {
    filteredPrinters() {
      let filtered = this.printers;
      
      if (this.filters.search) {
        const search = this.filters.search.toLowerCase();
        filtered = filtered.filter(printer => 
          printer.name.toLowerCase().includes(search) ||
          printer.connection_string.toLowerCase().includes(search) ||
          printer.driver.toLowerCase().includes(search)
        );
      }
      
      if (this.filters.status) {
        filtered = filtered.filter(printer => printer.status === this.filters.status);
      }
      
      if (this.filters.driver) {
        filtered = filtered.filter(printer => printer.driver === this.filters.driver);
      }
      
      return filtered;
    }
  },
  methods: {
    getPrinterIcon(printer) {
      switch (printer.driver) {
        case 'zebra_zpl': return 'fas fa-barcode';
        case 'dymo_label': return 'fas fa-tag';
        case 'brother_ptouch': return 'fas fa-label';
        case 'pdf': return 'fas fa-file-pdf';
        default: return 'fas fa-print';
      }
    },
    
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
    
    async testPrinter(printer) {
      this.$set(this.testingPrinters, printer.id, true);
      
      try {
        const response = await axios.post(`/printers/${printer.id}/test`);
        
        if (response.data.success) {
          this.$toast.success(`${printer.name} connection successful`);
        } else {
          this.$toast.error(`${printer.name} connection failed`);
        }
      } catch (error) {
        this.$toast.error(`Test failed: ${error.response?.data?.message || error.message}`);
      } finally {
        this.$set(this.testingPrinters, printer.id, false);
      }
    },
    
    async testAllPrinters() {
      this.testingAll = true;
      
      const promises = this.filteredPrinters.map(printer => this.testPrinter(printer));
      
      try {
        await Promise.all(promises);
        this.$toast.success('All printer tests completed');
      } catch (error) {
        this.$toast.error('Some printer tests failed');
      } finally {
        this.testingAll = false;
      }
    },
    
    async togglePrinter(printer) {
      this.$set(this.togglingPrinters, printer.id, true);
      
      try {
        await axios.patch(`/printers/${printer.id}`, {
          is_active: !printer.is_active
        });
        
        printer.is_active = !printer.is_active;
        this.$toast.success(`${printer.name} ${printer.is_active ? 'enabled' : 'disabled'}`);
      } catch (error) {
        this.$toast.error(`Failed to toggle printer: ${error.response?.data?.message || error.message}`);
      } finally {
        this.$set(this.togglingPrinters, printer.id, false);
      }
    },
    
    async refreshPrinters() {
      this.refreshing = true;
      
      try {
        await this.$inertia.reload({ only: ['printers'] });
        this.$toast.success('Printers refreshed');
      } catch (error) {
        this.$toast.error('Failed to refresh printers');
      } finally {
        this.refreshing = false;
      }
    },
    
    confirmDelete(printer) {
      this.printerToDelete = printer;
    },
    
    async deletePrinter() {
      if (!this.printerToDelete) return;
      
      this.deleting = true;
      
      try {
        await axios.delete(`/printers/${this.printerToDelete.id}`);
        this.$toast.success(`${this.printerToDelete.name} deleted successfully`);
        this.printerToDelete = null;
        await this.refreshPrinters();
      } catch (error) {
        this.$toast.error(`Failed to delete printer: ${error.response?.data?.message || error.message}`);
      } finally {
        this.deleting = false;
      }
    },
    
    handleStatusUpdate(event) {
      const printer = this.printers.find(p => p.id === event.printer.id);
      if (printer) {
        printer.status = event.status;
      }
    }
  }
}
</script>
