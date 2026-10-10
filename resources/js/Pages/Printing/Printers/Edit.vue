<template>
  <AppLayout :title="`Edit Printer: ${printer.name}`">
    <template #header>
      <div class="flex items-center space-x-4">
        <Link :href="route('printing.printers.show', printer.id)" 
              class="text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300">
          <i class="fas fa-arrow-left"></i>
        </Link>
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
          Edit Printer: {{ printer.name }}
        </h2>
      </div>
    </template>

    <div class="py-12">
      <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg">
          <div class="p-6">
            <form @submit.prevent="updatePrinter" class="space-y-6">
              
              <!-- Basic Information -->
              <div class="border-b border-gray-200 dark:border-gray-700 pb-6">
                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">
                  Basic Information
                </h3>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                  <div>
                    <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                      Printer Name <span class="text-red-500">*</span>
                    </label>
                    <input id="name" 
                           v-model="form.name" 
                           type="text" 
                           required 
                           class="block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-gray-100 sm:text-sm">
                    <p v-if="errors.name" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ errors.name }}</p>
                  </div>
                  
                  <div class="flex items-center space-x-6">
                    <div class="flex items-center">
                      <input id="is_active" 
                             v-model="form.is_active" 
                             type="checkbox" 
                             class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 dark:border-gray-600 rounded dark:bg-gray-700">
                      <label for="is_active" class="ml-2 text-sm text-gray-700 dark:text-gray-300">
                        Active
                      </label>
                    </div>
                    
                    <div class="flex items-center">
                      <input id="is_default" 
                             v-model="form.is_default" 
                             type="checkbox" 
                             class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 dark:border-gray-600 rounded dark:bg-gray-700">
                      <label for="is_default" class="ml-2 text-sm text-gray-700 dark:text-gray-300">
                        Default printer
                      </label>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Connection Settings -->
              <div class="border-b border-gray-200 dark:border-gray-700 pb-6">
                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">
                  Connection Settings
                </h3>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                  <div>
                    <label for="printer_type" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                      Connection Type
                    </label>
                    <select id="printer_type" 
                            v-model="form.printer_type" 
                            disabled
                            class="block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm bg-gray-50 dark:bg-gray-600 text-gray-500 dark:text-gray-400 sm:text-sm">
                      <option value="network">Network Printer</option>
                      <option value="usb">USB Printer</option>
                      <option value="cups">CUPS Printer</option>
                    </select>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                      Connection type cannot be changed after creation
                    </p>
                  </div>
                  
                  <div>
                    <label for="connection_string" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                      Connection String
                    </label>
                    <input id="connection_string" 
                           v-model="form.connection_string" 
                           type="text" 
                           class="block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-gray-100 sm:text-sm">
                    <p v-if="errors.connection_string" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ errors.connection_string }}</p>
                  </div>
                </div>
              </div>

              <!-- Driver Settings -->
              <div class="border-b border-gray-200 dark:border-gray-700 pb-6">
                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">
                  Driver Configuration
                </h3>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                  <div>
                    <label for="driver" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                      Printer Driver
                    </label>
                    <select id="driver" 
                            v-model="form.driver" 
                            @change="updateDriverSettings"
                            class="block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-gray-100 sm:text-sm">
                      <option value="zebra_zpl">Zebra (ZPL)</option>
                      <option value="dymo_label">Dymo LabelWriter</option>
                      <option value="brother_ptouch">Brother P-touch</option>
                      <option value="generic_escp">Generic ESC-P</option>
                      <option value="pdf">PDF (Universal)</option>
                    </select>
                  </div>
                  
                  <div>
                    <label for="default_template_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                      Default Template
                    </label>
                    <select id="default_template_id" 
                            v-model="form.default_template_id"
                            class="block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-gray-100 sm:text-sm">
                      <option value="">No default template</option>
                      <option v-for="template in templates" 
                              :key="template.id" 
                              :value="template.id">
                        {{ template.name }}
                      </option>
                    </select>
                  </div>
                </div>
              </div>

              <!-- Print Settings -->
              <div class="pb-6">
                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">
                  Print Settings
                </h3>
                
                <!-- Zebra Settings -->
                <div v-if="form.driver === 'zebra_zpl'" class="grid grid-cols-1 md:grid-cols-3 gap-6">
                  <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                      DPI
                    </label>
                    <select v-model="form.print_settings.dpi"
                            class="block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-gray-100 sm:text-sm">
                      <option value="203">203 DPI</option>
                      <option value="300">300 DPI</option>
                      <option value="600">600 DPI</option>
                    </select>
                  </div>
                  
                  <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                      Print Speed
                    </label>
                    <select v-model="form.print_settings.speed"
                            class="block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-gray-100 sm:text-sm">
                      <option value="2">2 (Slow)</option>
                      <option value="4">4 (Medium)</option>
                      <option value="6">6 (Fast)</option>
                      <option value="8">8 (Very Fast)</option>
                    </select>
                  </div>
                  
                  <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                      Darkness
                    </label>
                    <input v-model.number="form.print_settings.darkness" 
                           type="range" 
                           min="0" 
                           max="30" 
                           class="block w-full">
                    <div class="flex justify-between text-xs text-gray-500 dark:text-gray-400 mt-1">
                      <span>Light</span>
                      <span>{{ form.print_settings.darkness }}</span>
                      <span>Dark</span>
                    </div>
                  </div>
                </div>
                
                <!-- PDF Settings -->
                <div v-else-if="form.driver === 'pdf'" class="grid grid-cols-1 md:grid-cols-2 gap-6">
                  <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                      Page Size
                    </label>
                    <select v-model="form.print_settings.page_size"
                            class="block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-gray-100 sm:text-sm">
                      <option value="A4">A4</option>
                      <option value="Letter">Letter</option>
                      <option value="Legal">Legal</option>
                    </select>
                  </div>
                  
                  <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                      Orientation
                    </label>
                    <select v-model="form.print_settings.orientation"
                            class="block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-gray-100 sm:text-sm">
                      <option value="portrait">Portrait</option>
                      <option value="landscape">Landscape</option>
                    </select>
                  </div>
                </div>
                
                <!-- Generic Settings -->
                <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-6">
                  <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                      Print Quality
                    </label>
                    <select v-model="form.print_settings.quality"
                            class="block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-gray-100 sm:text-sm">
                      <option value="draft">Draft</option>
                      <option value="normal">Normal</option>
                      <option value="high">High</option>
                    </select>
                  </div>
                  
                  <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                      Timeout (seconds)
                    </label>
                    <input v-model.number="form.print_settings.timeout" 
                           type="number" 
                           min="10" 
                           max="300"
                           class="block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-gray-100 sm:text-sm">
                  </div>
                </div>
              </div>

              <!-- Usage Statistics -->
              <div class="border-b border-gray-200 dark:border-gray-700 pb-6">
                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">
                  Usage Statistics
                </h3>
                
                <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                  <div class="text-center">
                    <div class="text-2xl font-bold text-blue-600 dark:text-blue-400">
                      {{ printer.total_jobs || 0 }}
                    </div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">Total Jobs</div>
                  </div>
                  <div class="text-center">
                    <div class="text-2xl font-bold text-green-600 dark:text-green-400">
                      {{ printer.successful_jobs || 0 }}
                    </div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">Successful</div>
                  </div>
                  <div class="text-center">
                    <div class="text-2xl font-bold text-red-600 dark:text-red-400">
                      {{ printer.failed_jobs || 0 }}
                    </div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">Failed</div>
                  </div>
                  <div class="text-center">
                    <div class="text-2xl font-bold text-purple-600 dark:text-purple-400">
                      {{ printer.success_rate || 0 }}%
                    </div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">Success Rate</div>
                  </div>
                </div>
              </div>

              <!-- Form Actions -->
              <div class="flex justify-between items-center pt-6">
                <div class="flex items-center space-x-4">
                  <button type="button" 
                          @click="testConnection" 
                          :disabled="testing"
                          class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50">
                    <i v-if="testing" class="fas fa-spinner fa-spin mr-2"></i>
                    <i v-else class="fas fa-vial mr-2"></i>
                    {{ testing ? 'Testing...' : 'Test Connection' }}
                  </button>
                  
                  <div v-if="testResult" 
                       :class="testResult.success ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'"
                       class="text-sm flex items-center">
                    <i :class="testResult.success ? 'fas fa-check-circle' : 'fas fa-times-circle'" class="mr-1"></i>
                    {{ testResult.message }}
                  </div>
                </div>
                
                <div class="flex space-x-3">
                  <Link :href="route('printing.printers.show', printer.id)" 
                        class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    Cancel
                  </Link>
                  
                  <button type="submit" 
                          :disabled="updating"
                          class="px-4 py-2 bg-blue-600 border border-transparent rounded-md text-sm font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50">
                    <i v-if="updating" class="fas fa-spinner fa-spin mr-2"></i>
                    <i v-else class="fas fa-save mr-2"></i>
                    {{ updating ? 'Updating...' : 'Update Printer' }}
                  </button>
                </div>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Link } from '@inertiajs/vue3'

export default {
  name: 'PrinterEdit',
  components: {
    AppLayout,
    Link
  },
  props: {
    printer: {
      type: Object,
      required: true
    },
    templates: {
      type: Array,
      default: () => []
    }
  },
  data() {
    return {
      form: {
        name: this.printer.name,
        printer_type: this.printer.printer_type,
        connection_string: this.printer.connection_string,
        driver: this.printer.driver,
        is_active: this.printer.is_active,
        is_default: this.printer.is_default,
        default_template_id: this.printer.default_template_id,
        print_settings: { ...this.printer.print_settings }
      },
      errors: {},
      updating: false,
      testing: false,
      testResult: null
    }
  },
  methods: {
    updateDriverSettings() {
      this.testResult = null;
      
      // Reset print settings based on driver
      if (this.form.driver === 'zebra_zpl') {
        this.form.print_settings = {
          dpi: this.form.print_settings.dpi || '203',
          speed: this.form.print_settings.speed || '4',
          darkness: this.form.print_settings.darkness || 15
        };
      } else if (this.form.driver === 'pdf') {
        this.form.print_settings = {
          page_size: this.form.print_settings.page_size || 'A4',
          orientation: this.form.print_settings.orientation || 'portrait'
        };
      } else {
        this.form.print_settings = {
          quality: this.form.print_settings.quality || 'normal',
          timeout: this.form.print_settings.timeout || 30
        };
      }
    },
    
    async testConnection() {
      this.testing = true;
      this.testResult = null;
      
      try {
        const response = await axios.post(`/printers/${this.printer.id}/test-connection`, {
          connection_string: this.form.connection_string,
          driver: this.form.driver,
          print_settings: this.form.print_settings
        });
        
        this.testResult = {
          success: true,
          message: 'Connection successful'
        };
        
        this.$toast.success('Printer connection successful');
      } catch (error) {
        this.testResult = {
          success: false,
          message: error.response?.data?.message || 'Connection failed'
        };
        
        this.$toast.error(`Connection failed: ${error.response?.data?.message || error.message}`);
      } finally {
        this.testing = false;
      }
    },
    
    async updatePrinter() {
      this.updating = true;
      this.errors = {};
      
      try {
        await axios.patch(`/printers/${this.printer.id}`, this.form);
        this.$toast.success('Printer updated successfully');
        this.$inertia.visit(route('printing.printers.show', this.printer.id));
      } catch (error) {
        if (error.response?.status === 422) {
          this.errors = error.response.data.errors || {};
        }
        this.$toast.error(`Failed to update printer: ${error.response?.data?.message || error.message}`);
      } finally {
        this.updating = false;
      }
    }
  }
}
</script>
