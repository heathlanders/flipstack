<template>
  <div class="printer-discovery">
    <!-- Discovery Header -->
    <div class="flex justify-between items-center mb-6">
      <div>
        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">
          {{ title || 'Printer Discovery' }}
        </h3>
        <p v-if="showDescription" class="mt-1 text-sm text-gray-500 dark:text-gray-400">
          Automatically discover network printers or add printers manually
        </p>
      </div>
      
      <div class="flex space-x-3">
        <button v-if="!isDiscovering && !hideStartButton" 
                @click="startDiscovery"
                class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 active:bg-blue-900 focus:outline-none focus:border-blue-900 focus:ring ring-blue-300 transition ease-in-out duration-150">
          <i class="fas fa-search mr-2"></i>
          Start Discovery
        </button>
        
        <button v-if="isDiscovering" 
                @click="stopDiscovery"
                class="inline-flex items-center px-4 py-2 bg-red-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-red-700 active:bg-red-900 focus:outline-none focus:border-red-900 focus:ring ring-red-300 transition ease-in-out duration-150">
          <i class="fas fa-stop mr-2"></i>
          Stop Discovery
        </button>
        
        <button v-if="allowManualAdd"
                @click="showManualForm = !showManualForm"
                class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
          <i class="fas fa-plus mr-2"></i>
          Add Manually
        </button>
      </div>
    </div>

    <!-- Discovery Progress -->
    <div v-if="isDiscovering" class="mb-6 bg-blue-50 dark:bg-blue-900 border border-blue-200 dark:border-blue-700 rounded-lg p-4">
      <div class="flex items-center">
        <div class="flex-shrink-0">
          <i class="fas fa-spinner fa-spin text-blue-600 dark:text-blue-400"></i>
        </div>
        <div class="ml-3 flex-1">
          <h4 class="text-sm font-medium text-blue-800 dark:text-blue-200">
            Discovering Printers...
          </h4>
          <div class="mt-1 text-sm text-blue-700 dark:text-blue-300">
            {{ discoveryStatus }}
          </div>
          <!-- Progress Bar -->
          <div class="mt-2 w-full bg-blue-200 dark:bg-blue-800 rounded-full h-2">
            <div class="bg-blue-600 dark:bg-blue-400 h-2 rounded-full transition-all duration-300" 
                 :style="{ width: discoveryProgress + '%' }"></div>
          </div>
          <div class="mt-1 text-xs text-blue-600 dark:text-blue-300">
            {{ discoveryProgress }}% complete • {{ discoveredPrinters.length }} printer(s) found
          </div>
        </div>
      </div>
    </div>

    <!-- Discovery Results -->
    <div v-if="discoveredPrinters.length > 0" class="mb-6">
      <h4 class="text-md font-medium text-gray-900 dark:text-gray-100 mb-4">
        Discovered Printers ({{ discoveredPrinters.length }})
      </h4>
      
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        <div v-for="printer in discoveredPrinters" 
             :key="printer.ip"
             class="border border-gray-200 dark:border-gray-700 rounded-lg p-4 hover:shadow-lg transition-shadow">
          
          <div class="flex items-start justify-between mb-3">
            <div class="flex-1">
              <h5 class="font-medium text-gray-900 dark:text-gray-100">
                {{ printer.name || `Printer at ${printer.ip}` }}
              </h5>
              <p class="text-sm text-gray-500 dark:text-gray-400 font-mono">
                {{ printer.ip }}:{{ printer.port }}
              </p>
              <p v-if="printer.description" class="text-xs text-gray-600 dark:text-gray-300 mt-1">
                {{ printer.description }}
              </p>
            </div>
            
            <div class="flex flex-col items-end space-y-1">
              <span :class="printer.status === 'online' ? 
                    'bg-green-100 text-green-800 dark:bg-green-800 dark:text-green-100' :
                    printer.status === 'responding' ?
                    'bg-yellow-100 text-yellow-800 dark:bg-yellow-800 dark:text-yellow-100' :
                    'bg-red-100 text-red-800 dark:bg-red-800 dark:text-red-100'"
                    class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium">
                <i :class="printer.status === 'online' ? 'fas fa-circle' :
                          printer.status === 'responding' ? 'fas fa-exclamation-circle' :
                          'fas fa-times-circle'" class="mr-1"></i>
                {{ formatPrinterStatus(printer.status) }}
              </span>
              
              <span v-if="printer.driver_detected" 
                    class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-800 dark:text-blue-100">
                {{ formatDriverName(printer.driver_detected) }}
              </span>
            </div>
          </div>
          
          <div class="flex justify-between items-center">
            <div class="flex space-x-2">
              <button @click="testPrinter(printer)" 
                      :disabled="testingPrinters[printer.ip]"
                      class="text-blue-600 dark:text-blue-400 hover:underline text-sm disabled:opacity-50">
                <i v-if="testingPrinters[printer.ip]" class="fas fa-spinner fa-spin mr-1"></i>
                <i v-else class="fas fa-vial mr-1"></i>
                Test
              </button>
              
              <button @click="viewPrinterDetails(printer)" 
                      class="text-green-600 dark:text-green-400 hover:underline text-sm">
                <i class="fas fa-info-circle mr-1"></i>
                Details
              </button>
            </div>
            
            <button @click="addDiscoveredPrinter(printer)" 
                    :disabled="addingPrinters[printer.ip] || printer.already_added"
                    class="inline-flex items-center px-3 py-1 border border-transparent text-xs font-medium rounded text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 disabled:opacity-50 disabled:cursor-not-allowed">
              <i v-if="addingPrinters[printer.ip]" class="fas fa-spinner fa-spin mr-1"></i>
              <i v-else-if="printer.already_added" class="fas fa-check mr-1"></i>
              <i v-else class="fas fa-plus mr-1"></i>
              {{ printer.already_added ? 'Added' : addingPrinters[printer.ip] ? 'Adding...' : 'Add' }}
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Manual Printer Form -->
    <div v-if="showManualForm" class="mb-6 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-6">
      <h4 class="text-md font-medium text-gray-900 dark:text-gray-100 mb-4">
        Add Printer Manually
      </h4>
      
      <form @submit.prevent="addManualPrinter" class="space-y-4">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
              Printer Name *
            </label>
            <input v-model="manualForm.name" 
                   type="text" 
                   required
                   class="block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-gray-100 sm:text-sm"
                   placeholder="Office Label Printer">
          </div>
          
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
              Connection Type *
            </label>
            <select v-model="manualForm.printer_type" 
                    required
                    @change="updateManualPlaceholder"
                    class="block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-gray-100 sm:text-sm">
              <option value="">Select type</option>
              <option value="network">Network (IP)</option>
              <option value="usb">USB</option>
              <option value="cups">CUPS</option>
            </select>
          </div>
          
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
              Connection String *
            </label>
            <input v-model="manualForm.connection_string" 
                   type="text" 
                   required
                   :placeholder="manualPlaceholder"
                   class="block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-gray-100 sm:text-sm">
          </div>
          
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
              Driver *
            </label>
            <select v-model="manualForm.driver" 
                    required
                    class="block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-gray-100 sm:text-sm">
              <option value="">Select driver</option>
              <option value="zebra_zpl">Zebra (ZPL)</option>
              <option value="dymo_label">Dymo LabelWriter</option>
              <option value="brother_ptouch">Brother P-touch</option>
              <option value="generic_escp">Generic ESC-P</option>
              <option value="pdf">PDF (Universal)</option>
            </select>
          </div>
        </div>
        
        <div class="flex justify-end space-x-3">
          <button type="button" 
                  @click="showManualForm = false"
                  class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
            Cancel
          </button>
          
          <button type="submit" 
                  :disabled="addingManual"
                  class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 active:bg-blue-900 focus:outline-none focus:border-blue-900 focus:ring ring-blue-300 disabled:opacity-25 transition ease-in-out duration-150">
            <i v-if="addingManual" class="fas fa-spinner fa-spin mr-2"></i>
            <i v-else class="fas fa-plus mr-2"></i>
            {{ addingManual ? 'Adding...' : 'Add Printer' }}
          </button>
        </div>
      </form>
    </div>

    <!-- No Printers Found -->
    <div v-if="discoveryCompleted && discoveredPrinters.length === 0" 
         class="text-center py-8 text-gray-500 dark:text-gray-400">
      <i class="fas fa-search text-4xl mb-4"></i>
      <h4 class="text-lg font-medium mb-2">No Printers Found</h4>
      <p class="text-sm mb-4">
        No network printers were discovered automatically. You can:
      </p>
      <div class="flex justify-center space-x-3">
        <button @click="startDiscovery" 
                class="text-blue-600 dark:text-blue-400 hover:underline">
          Try discovery again
        </button>
        <span class="text-gray-300">•</span>
        <button @click="showManualForm = true" 
                class="text-blue-600 dark:text-blue-400 hover:underline">
          Add printer manually
        </button>
      </div>
    </div>

    <!-- Printer Details Modal -->
    <div v-if="selectedPrinter" 
         class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50"
         @click.self="selectedPrinter = null">
      <div class="relative top-20 mx-auto p-5 border w-full max-w-md shadow-lg rounded-md bg-white dark:bg-gray-800">
        <div class="mt-3">
          <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">
              Printer Details
            </h3>
            <button @click="selectedPrinter = null" 
                    class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">
              <i class="fas fa-times"></i>
            </button>
          </div>
          
          <div class="space-y-3 text-sm">
            <div>
              <span class="font-medium text-gray-700 dark:text-gray-300">IP Address:</span>
              <span class="ml-2 font-mono">{{ selectedPrinter.ip }}:{{ selectedPrinter.port }}</span>
            </div>
            <div v-if="selectedPrinter.name">
              <span class="font-medium text-gray-700 dark:text-gray-300">Name:</span>
              <span class="ml-2">{{ selectedPrinter.name }}</span>
            </div>
            <div>
              <span class="font-medium text-gray-700 dark:text-gray-300">Status:</span>
              <span class="ml-2">{{ formatPrinterStatus(selectedPrinter.status) }}</span>
            </div>
            <div v-if="selectedPrinter.description">
              <span class="font-medium text-gray-700 dark:text-gray-300">Description:</span>
              <span class="ml-2">{{ selectedPrinter.description }}</span>
            </div>
            <div v-if="selectedPrinter.driver_detected">
              <span class="font-medium text-gray-700 dark:text-gray-300">Detected Driver:</span>
              <span class="ml-2">{{ formatDriverName(selectedPrinter.driver_detected) }}</span>
            </div>
            <div v-if="selectedPrinter.response_time">
              <span class="font-medium text-gray-700 dark:text-gray-300">Response Time:</span>
              <span class="ml-2">{{ selectedPrinter.response_time }}ms</span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'PrinterDiscovery',
  props: {
    title: {
      type: String,
      default: 'Printer Discovery'
    },
    showDescription: {
      type: Boolean,
      default: true
    },
    hideStartButton: {
      type: Boolean,
      default: false
    },
    allowManualAdd: {
      type: Boolean,
      default: true
    },
    autoStart: {
      type: Boolean,
      default: false
    },
    discoveryTimeout: {
      type: Number,
      default: 30000 // 30 seconds
    }
  },
  data() {
    return {
      isDiscovering: false,
      discoveryCompleted: false,
      discoveryProgress: 0,
      discoveryStatus: 'Initializing...',
      discoveredPrinters: [],
      testingPrinters: {},
      addingPrinters: {},
      selectedPrinter: null,
      showManualForm: false,
      addingManual: false,
      discoveryInterval: null,
      manualForm: {
        name: '',
        printer_type: '',
        connection_string: '',
        driver: ''
      },
      manualPlaceholder: ''
    }
  },
  mounted() {
    if (this.autoStart) {
      this.startDiscovery();
    }
  },
  beforeDestroy() {
    if (this.discoveryInterval) {
      clearInterval(this.discoveryInterval);
    }
  },
  methods: {
    async startDiscovery() {
      this.isDiscovering = true;
      this.discoveryCompleted = false;
      this.discoveryProgress = 0;
      this.discoveredPrinters = [];
      this.discoveryStatus = 'Scanning network for printers...';
      
      try {
        const response = await axios.post('/printers/discover');
        
        if (response.data.job_id) {
          // Start polling for discovery results
          this.pollDiscoveryStatus(response.data.job_id);
        } else if (response.data.printers) {
          // Immediate results
          this.discoveredPrinters = response.data.printers;
          this.discoveryCompleted = true;
          this.isDiscovering = false;
          this.discoveryProgress = 100;
        }
        
        this.$emit('discovery-started', response.data);
      } catch (error) {
        this.isDiscovering = false;
        this.discoveryStatus = 'Discovery failed';
        this.$toast.error(`Discovery failed: ${error.response?.data?.message || error.message}`);
      }
    },
    
    async pollDiscoveryStatus(jobId) {
      const startTime = Date.now();
      
      this.discoveryInterval = setInterval(async () => {
        try {
          const response = await axios.get(`/printers/discover/${jobId}/status`);
          const status = response.data;
          
          this.discoveryProgress = status.progress || 0;
          this.discoveryStatus = status.message || 'Scanning...';
          
          if (status.printers) {
            this.discoveredPrinters = status.printers;
          }
          
          if (status.completed || (Date.now() - startTime) > this.discoveryTimeout) {
            this.stopDiscovery();
            this.discoveryCompleted = true;
            this.$emit('discovery-completed', {
              printers: this.discoveredPrinters,
              completed: status.completed
            });
          }
        } catch (error) {
          this.stopDiscovery();
          this.$toast.error('Failed to check discovery status');
        }
      }, 2000); // Poll every 2 seconds
    },
    
    stopDiscovery() {
      this.isDiscovering = false;
      if (this.discoveryInterval) {
        clearInterval(this.discoveryInterval);
        this.discoveryInterval = null;
      }
    },
    
    async testPrinter(printer) {
      this.$set(this.testingPrinters, printer.ip, true);
      
      try {
        const response = await axios.post('/printers/test-discovered', {
          ip: printer.ip,
          port: printer.port,
          driver: printer.driver_detected || 'generic_escp'
        });
        
        printer.status = response.data.success ? 'online' : 'offline';
        this.$toast.success(response.data.message || 'Printer test completed');
      } catch (error) {
        printer.status = 'offline';
        this.$toast.error(`Test failed: ${error.response?.data?.message || error.message}`);
      } finally {
        this.$set(this.testingPrinters, printer.ip, false);
      }
    },
    
    async addDiscoveredPrinter(printer) {
      if (printer.already_added) return;
      
      this.$set(this.addingPrinters, printer.ip, true);
      
      try {
        const printerData = {
          name: printer.name || `Printer at ${printer.ip}`,
          printer_type: 'network',
          connection_string: printer.ip,
          driver: printer.driver_detected || 'generic_escp',
          is_active: true,
          print_settings: {}
        };
        
        const response = await axios.post('/printers', printerData);
        printer.already_added = true;
        
        this.$toast.success(`${printer.name || 'Printer'} added successfully`);
        this.$emit('printer-added', {
          printer: response.data.printer,
          discovered: printer
        });
      } catch (error) {
        this.$toast.error(`Failed to add printer: ${error.response?.data?.message || error.message}`);
      } finally {
        this.$set(this.addingPrinters, printer.ip, false);
      }
    },
    
    viewPrinterDetails(printer) {
      this.selectedPrinter = printer;
    },
    
    updateManualPlaceholder() {
      switch (this.manualForm.printer_type) {
        case 'network':
          this.manualPlaceholder = '192.168.1.100';
          break;
        case 'usb':
          this.manualPlaceholder = '/dev/usb/lp0 or COM1';
          break;
        case 'cups':
          this.manualPlaceholder = 'printer_name';
          break;
        default:
          this.manualPlaceholder = '';
      }
    },
    
    async addManualPrinter() {
      this.addingManual = true;
      
      try {
        const response = await axios.post('/printers', {
          ...this.manualForm,
          is_active: true,
          print_settings: {}
        });
        
        this.manualForm = {
          name: '',
          printer_type: '',
          connection_string: '',
          driver: ''
        };
        this.showManualForm = false;
        
        this.$toast.success('Printer added successfully');
        this.$emit('printer-added', {
          printer: response.data.printer,
          manual: true
        });
      } catch (error) {
        this.$toast.error(`Failed to add printer: ${error.response?.data?.message || error.message}`);
      } finally {
        this.addingManual = false;
      }
    },
    
    formatPrinterStatus(status) {
      switch (status) {
        case 'online': return 'Online';
        case 'responding': return 'Responding';
        case 'offline': return 'Offline';
        case 'timeout': return 'Timeout';
        default: return 'Unknown';
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
    }
  }
}
</script>
