<template>
  <div v-if="visible" 
       class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50"
       @click.self="$emit('close')">
    <div class="relative top-20 mx-auto p-5 border w-full max-w-md shadow-lg rounded-md bg-white dark:bg-gray-800">
      <div class="mt-3">
        <div class="flex justify-between items-center mb-4">
          <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">
            Print Settings
          </h3>
          <button @click="$emit('close')" 
                  class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">
            <i class="fas fa-times"></i>
          </button>
        </div>
        
        <form @submit.prevent="handleSubmit" class="space-y-4">
          <!-- Quantity -->
          <div>
            <label for="quantity" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
              Quantity
            </label>
            <input id="quantity" 
                   v-model.number="settings.quantity" 
                   type="number" 
                   min="1" 
                   max="100" 
                   required
                   class="block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-gray-100 sm:text-sm">
          </div>
          
          <!-- Template Selection -->
          <div>
            <label for="template" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
              Template
            </label>
            <select id="template" 
                    v-model="settings.template_id"
                    class="block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-gray-100 sm:text-sm">
              <option value="">Default Template</option>
              <option v-for="template in templates" 
                      :key="template.id" 
                      :value="template.id">
                {{ template.name }} ({{ template.width_mm }}×{{ template.height_mm }}mm)
              </option>
            </select>
          </div>
          
          <!-- Printer Selection -->
          <div>
            <label for="printer" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
              Printer
            </label>
            <select id="printer" 
                    v-model="settings.printer_id"
                    class="block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-gray-100 sm:text-sm">
              <option value="">Default Printer</option>
              <option v-for="printer in activePrinters" 
                      :key="printer.id" 
                      :value="printer.id">
                {{ printer.name }}
                <span v-if="printer.is_default" class="text-blue-500">(Default)</span>
              </option>
            </select>
            <p v-if="!activePrinters.length" class="mt-1 text-xs text-red-600 dark:text-red-400">
              No active printers available. Please add and configure a printer first.
            </p>
          </div>
          
          <!-- Job Priority (if supported) -->
          <div v-if="showAdvanced">
            <label for="priority" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
              Priority
            </label>
            <select id="priority" 
                    v-model="settings.priority"
                    class="block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-gray-100 sm:text-sm">
              <option value="normal">Normal</option>
              <option value="high">High</option>
              <option value="urgent">Urgent</option>
            </select>
          </div>
          
          <!-- Advanced Options Toggle -->
          <div class="flex items-center">
            <input id="show-advanced" 
                   v-model="showAdvanced" 
                   type="checkbox" 
                   class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 dark:border-gray-600 rounded dark:bg-gray-700">
            <label for="show-advanced" class="ml-2 block text-sm text-gray-900 dark:text-gray-100">
              Show advanced options
            </label>
          </div>
          
          <!-- Advanced Settings -->
          <div v-if="showAdvanced" class="space-y-4 pt-4 border-t border-gray-200 dark:border-gray-700">
            
            <!-- Print Multiple Copies -->
            <div class="flex items-center">
              <input id="multiple-copies" 
                     v-model="settings.print_multiple" 
                     type="checkbox" 
                     class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 dark:border-gray-600 rounded dark:bg-gray-700">
              <label for="multiple-copies" class="ml-2 block text-sm text-gray-900 dark:text-gray-100">
                Print multiple copies on single page
              </label>
            </div>
            
            <!-- Delay Printing -->
            <div v-if="allowScheduling">
              <label for="delay" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                Delay printing (minutes)
              </label>
              <input id="delay" 
                     v-model.number="settings.delay_minutes" 
                     type="number" 
                     min="0" 
                     max="1440"
                     class="block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-gray-100 sm:text-sm">
              <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                Set to 0 for immediate printing
              </p>
            </div>
            
            <!-- Save as Preset -->
            <div class="flex items-center">
              <input id="save-preset" 
                     v-model="settings.save_as_preset" 
                     type="checkbox" 
                     class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 dark:border-gray-600 rounded dark:bg-gray-700">
              <label for="save-preset" class="ml-2 block text-sm text-gray-900 dark:text-gray-100">
                Save these settings as default
              </label>
            </div>
          </div>
          
          <!-- Label Preview (if template and product provided) -->
          <div v-if="product && selectedTemplate" class="pt-4 border-t border-gray-200 dark:border-gray-700">
            <h4 class="text-sm font-medium text-gray-900 dark:text-gray-100 mb-2">Preview</h4>
            <div class="flex justify-center">
              <LabelPreview :product="product" 
                           :template="selectedTemplate" 
                           :scale="2"
                           :show-controls="false"
                           max-width="200px" />
            </div>
          </div>
          
          <!-- Actions -->
          <div class="flex justify-end space-x-3 pt-4">
            <button type="button" 
                    @click="$emit('close')"
                    class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
              Cancel
            </button>
            
            <button type="submit" 
                    :disabled="!canPrint || printing"
                    class="px-4 py-2 bg-blue-600 border border-transparent rounded-md text-sm font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed">
              <i v-if="printing" class="fas fa-spinner fa-spin mr-2"></i>
              <i v-else class="fas fa-print mr-2"></i>
              {{ printing ? 'Printing...' : `Print ${settings.quantity} Label${settings.quantity > 1 ? 's' : ''}` }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script>
import LabelPreview from './LabelPreview.vue'

export default {
  name: 'PrintSettingsModal',
  components: {
    LabelPreview
  },
  props: {
    visible: {
      type: Boolean,
      default: false
    },
    product: {
      type: Object,
      default: null
    },
    templates: {
      type: Array,
      default: () => []
    },
    printers: {
      type: Array,
      default: () => []
    },
    defaultSettings: {
      type: Object,
      default: () => ({
        quantity: 1,
        template_id: '',
        printer_id: '',
        priority: 'normal',
        print_multiple: false,
        delay_minutes: 0,
        save_as_preset: false
      })
    },
    allowScheduling: {
      type: Boolean,
      default: false
    },
    printing: {
      type: Boolean,
      default: false
    }
  },
  data() {
    return {
      showAdvanced: false,
      settings: { ...this.defaultSettings }
    }
  },
  computed: {
    activePrinters() {
      return this.printers.filter(p => p.is_active);
    },
    selectedTemplate() {
      if (!this.settings.template_id) {
        return this.templates.find(t => t.is_default);
      }
      return this.templates.find(t => t.id === this.settings.template_id);
    },
    canPrint() {
      return this.activePrinters.length > 0 && this.settings.quantity > 0;
    }
  },
  watch: {
    visible(newVal) {
      if (newVal) {
        // Reset settings when modal opens
        this.settings = { ...this.defaultSettings };
        this.showAdvanced = false;
        
        // Auto-select default printer and template if available
        const defaultPrinter = this.printers.find(p => p.is_default && p.is_active);
        if (defaultPrinter && !this.settings.printer_id) {
          this.settings.printer_id = defaultPrinter.id;
        }
        
        const defaultTemplate = this.templates.find(t => t.is_default);
        if (defaultTemplate && !this.settings.template_id) {
          this.settings.template_id = defaultTemplate.id;
        }
      }
    },
    defaultSettings: {
      handler(newVal) {
        this.settings = { ...newVal };
      },
      deep: true
    }
  },
  methods: {
    handleSubmit() {
      if (!this.canPrint) {
        this.$toast.error('Cannot print: No active printers available');
        return;
      }
      
      this.$emit('print', { ...this.settings });
    }
  }
}
</script>
