<template>
  <AppLayout title="Discover Printers">
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
        Discover Printers
      </h2>
    </template>

    <div class="py-12">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg">
          <div class="p-6">
            
            <!-- Discovery Section -->
            <div class="mb-8">
              <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">
                Automatic Discovery
              </h3>
              <p class="text-gray-600 dark:text-gray-400 mb-4">
                Click below to automatically discover printers on your network.
              </p>
              
              <button @click="discoverPrinters" 
                      :disabled="discovering" 
                      class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 active:bg-blue-900 focus:outline-none focus:border-blue-900 focus:ring ring-blue-300 disabled:opacity-25 transition ease-in-out duration-150">
                <i class="fas fa-search mr-2"></i>
                {{ discovering ? 'Discovering...' : 'Discover Printers' }}
              </button>

              <!-- Progress Bar -->
              <div v-if="discovering" class="mt-4">
                <div class="bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                  <div class="bg-blue-600 h-2 rounded-full transition-all duration-300" 
                       :style="{ width: discoveryProgress + '%' }"></div>
                </div>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                  {{ discoveryStatus }}
                </p>
              </div>
            </div>

            <!-- Discovered Printers -->
            <div v-if="discoveredPrinters.length > 0" class="mb-8">
              <h4 class="text-md font-medium text-gray-900 dark:text-gray-100 mb-4">
                Found {{ discoveredPrinters.length }} printer(s)
              </h4>
              
              <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <div v-for="printer in discoveredPrinters" 
                     :key="printer.connection_string" 
                     class="border border-gray-300 dark:border-gray-600 rounded-lg p-4">
                  
                  <div class="flex items-start justify-between mb-3">
                    <div>
                      <h5 class="font-medium text-gray-900 dark:text-gray-100">
                        {{ printer.name }}
                      </h5>
                      <div class="text-sm text-gray-500 dark:text-gray-400 space-y-1">
                        <p><strong>Type:</strong> {{ printer.type }}</p>
                        <p><strong>Connection:</strong> {{ printer.connection_string }}</p>
                        <p><strong>Driver:</strong> {{ printer.driver }}</p>
                        <p v-if="printer.port"><strong>Port:</strong> {{ printer.port }}</p>
                      </div>
                    </div>
                    
                    <div class="flex items-center">
                      <span v-if="printer.status === 'online'" 
                            class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-800 dark:text-green-100">
                        <i class="fas fa-circle text-green-400 mr-1"></i>
                        Online
                      </span>
                      <span v-else-if="printer.status === 'offline'" 
                            class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 dark:bg-red-800 dark:text-red-100">
                        <i class="fas fa-circle text-red-400 mr-1"></i>
                        Offline
                      </span>
                      <span v-else 
                            class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-100">
                        <i class="fas fa-question-circle text-gray-400 mr-1"></i>
                        Unknown
                      </span>
                    </div>
                  </div>
                  
                  <div class="flex space-x-2">
                    <button @click="testPrinter(printer)" 
                            :disabled="testingPrinters[printer.connection_string]"
                            class="flex-1 inline-flex justify-center items-center px-3 py-1 border border-gray-300 dark:border-gray-600 rounded-md text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50">
                      <i v-if="testingPrinters[printer.connection_string]" class="fas fa-spinner fa-spin mr-1"></i>
                      <i v-else class="fas fa-vial mr-1"></i>
                      {{ testingPrinters[printer.connection_string] ? 'Testing...' : 'Test' }}
                    </button>
                    
                    <button @click="addPrinter(printer)" 
                            :disabled="addingPrinters[printer.connection_string]"
                            class="flex-1 inline-flex justify-center items-center px-3 py-1 bg-green-600 border border-transparent rounded-md text-sm font-medium text-white hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 disabled:opacity-50">
                      <i v-if="addingPrinters[printer.connection_string]" class="fas fa-spinner fa-spin mr-1"></i>
                      <i v-else class="fas fa-plus mr-1"></i>
                      {{ addingPrinters[printer.connection_string] ? 'Adding...' : 'Add' }}
                    </button>
                  </div>
                  
                  <!-- Test Results -->
                  <div v-if="testResults[printer.connection_string]" class="mt-3">
                    <div v-if="testResults[printer.connection_string].success" 
                         class="text-sm text-green-600 dark:text-green-400 flex items-center">
                      <i class="fas fa-check-circle mr-1"></i>
                      Connection successful
                    </div>
                    <div v-else 
                         class="text-sm text-red-600 dark:text-red-400 flex items-center">
                      <i class="fas fa-times-circle mr-1"></i>
                      {{ testResults[printer.connection_string].error || 'Connection failed' }}
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Manual Setup Section -->
            <div class="border-t border-gray-200 dark:border-gray-700 pt-8">
              <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">
                Manual Printer Setup
              </h3>
              
              <form @submit.prevent="addManualPrinter" class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div>
                    <label for="manual-name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                      Printer Name
                    </label>
                    <input id="manual-name" 
                           v-model="manualPrinter.name" 
                           type="text" 
                           required 
                           class="block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-gray-100 sm:text-sm"
                           placeholder="My Label Printer">
                  </div>
                  
                  <div>
                    <label for="manual-type" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                      Connection Type
                    </label>
                    <select id="manual-type" 
                            v-model="manualPrinter.type" 
                            required 
                            class="block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-gray-100 sm:text-sm">
                      <option value="network">Network Printer (IP Address)</option>
                      <option value="usb">USB Printer</option>
                      <option value="cups">CUPS Printer</option>
                    </select>
                  </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div>
                    <label for="manual-connection" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                      Connection String
                    </label>
                    <input id="manual-connection" 
                           v-model="manualPrinter.connection_string" 
                           type="text" 
                           required 
                           :placeholder="connectionPlaceholder"
                           class="block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-gray-100 sm:text-sm">
                  </div>
                  
                  <div>
                    <label for="manual-driver" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                      Driver
                    </label>
                    <select id="manual-driver" 
                            v-model="manualPrinter.driver" 
                            required 
                            class="block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-gray-100 sm:text-sm">
                      <option value="zebra_zpl">Zebra (ZPL)</option>
                      <option value="dymo_label">Dymo LabelWriter</option>
                      <option value="brother_ptouch">Brother P-touch</option>
                      <option value="generic_escp">Generic ESC-P</option>
                      <option value="pdf">PDF (Universal)</option>
                    </select>
                  </div>
                </div>
                
                <div class="flex justify-end">
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
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script>
import AppLayout from '@/Layouts/AppLayout.vue'

export default {
  name: 'PrinterDiscovery',
  components: {
    AppLayout
  },
  data() {
    return {
      discovering: false,
      discoveryProgress: 0,
      discoveryStatus: '',
      discoveredPrinters: [],
      testingPrinters: {},
      addingPrinters: {},
      testResults: {},
      addingManual: false,
      manualPrinter: {
        name: '',
        type: 'network',
        connection_string: '',
        driver: 'generic_escp'
      }
    }
  },
  computed: {
    connectionPlaceholder() {
      switch (this.manualPrinter.type) {
        case 'network': return '192.168.1.100';
        case 'usb': return '/dev/usb/lp0 or COM1';
        case 'cups': return 'printer_name';
        default: return '';
      }
    }
  },
  methods: {
    async discoverPrinters() {
      this.discovering = true;
      this.discoveryProgress = 0;
      this.discoveredPrinters = [];
      this.discoveryStatus = 'Starting discovery...';
      
      try {
        // Simulate progress updates
        const progressInterval = setInterval(() => {
          if (this.discoveryProgress < 90) {
            this.discoveryProgress += Math.random() * 10;
            if (this.discoveryProgress < 30) {
              this.discoveryStatus = 'Scanning network for printers...';
            } else if (this.discoveryProgress < 60) {
              this.discoveryStatus = 'Checking USB connections...';
            } else {
              this.discoveryStatus = 'Identifying printer models...';
            }
          }
        }, 500);
        
        const response = await axios.get('/printers/discover');
        
        clearInterval(progressInterval);
        this.discoveryProgress = 100;
        this.discoveryStatus = 'Discovery complete!';
        
        this.discoveredPrinters = response.data.printers;
        
        if (this.discoveredPrinters.length === 0) {
          this.$toast.info('No printers found. Try manual setup below.');
        } else {
          this.$toast.success(`Found ${this.discoveredPrinters.length} printer(s)`);
        }
      } catch (error) {
        this.$toast.error('Discovery failed: ' + (error.response?.data?.message || error.message));
      } finally {
        this.discovering = false;
      }
    },
    
    async testPrinter(printer) {
      this.$set(this.testingPrinters, printer.connection_string, true);
      this.$delete(this.testResults, printer.connection_string);
      
      try {
        const response = await axios.post('/printers/test-connection', {
          type: printer.type,
          connection_string: printer.connection_string,
          driver: printer.driver
        });
        
        this.$set(this.testResults, printer.connection_string, {
          success: true,
          message: 'Connection successful'
        });
        
        this.$toast.success(`${printer.name} connection successful`);
      } catch (error) {
        this.$set(this.testResults, printer.connection_string, {
          success: false,
          error: error.response?.data?.message || error.message
        });
        
        this.$toast.error(`${printer.name} connection failed: ${error.response?.data?.message || error.message}`);
      } finally {
        this.$set(this.testingPrinters, printer.connection_string, false);
      }
    },
    
    async addPrinter(printer) {
      this.$set(this.addingPrinters, printer.connection_string, true);
      
      try {
        await axios.post('/printers/add-discovered', printer);
        this.$toast.success(`${printer.name} added successfully`);
        this.$emit('printer-added');
        
        // Remove from discovered list
        this.discoveredPrinters = this.discoveredPrinters.filter(
          p => p.connection_string !== printer.connection_string
        );
      } catch (error) {
        this.$toast.error(`Failed to add ${printer.name}: ${error.response?.data?.message || error.message}`);
      } finally {
        this.$set(this.addingPrinters, printer.connection_string, false);
      }
    },
    
    async addManualPrinter() {
      this.addingManual = true;
      
      try {
        await axios.post('/printers/add-discovered', this.manualPrinter);
        this.$toast.success('Printer added successfully');
        this.$emit('printer-added');
        this.resetManualForm();
      } catch (error) {
        this.$toast.error('Failed to add printer: ' + (error.response?.data?.message || error.message));
      } finally {
        this.addingManual = false;
      }
    },
    
    resetManualForm() {
      this.manualPrinter = {
        name: '',
        type: 'network',
        connection_string: '',
        driver: 'generic_escp'
      };
    }
  }
}
</script>
