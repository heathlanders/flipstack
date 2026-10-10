<template>
  <AppLayout :title="`Template: ${template.name}`">
    <template #header>
      <div class="flex justify-between items-center">
        <div class="flex items-center space-x-4">
          <Link :href="route('printing.templates.index')" 
                class="text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300">
            <i class="fas fa-arrow-left"></i>
          </Link>
          <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ template.name }}
          </h2>
          <span v-if="template.is_default" 
                class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-800 dark:text-blue-100">
            <i class="fas fa-star mr-1"></i>
            Default Template
          </span>
        </div>
        <div class="flex space-x-3">
          <button @click="printTestLabel" 
                  :disabled="printingTest"
                  class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50">
            <i v-if="printingTest" class="fas fa-spinner fa-spin mr-2"></i>
            <i v-else class="fas fa-print mr-2"></i>
            Test Print
          </button>
          <button @click="duplicateTemplate" 
                  class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
            <i class="fas fa-copy mr-2"></i>
            Duplicate
          </button>
          <Link :href="route('printing.templates.edit', template.id)" 
                class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 active:bg-blue-900 focus:outline-none focus:border-blue-900 focus:ring ring-blue-300 transition ease-in-out duration-150">
            <i class="fas fa-edit mr-2"></i>
            Edit
          </Link>
        </div>
      </div>
    </template>

    <div class="py-12">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
        
        <!-- Template Details -->
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg">
          <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">
              Template Information
            </h3>
          </div>
          <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
              <!-- Basic Info -->
              <div class="space-y-4">
                <div>
                  <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Name</label>
                  <p class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ template.name }}</p>
                </div>
                
                <div v-if="template.description">
                  <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Description</label>
                  <p class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ template.description }}</p>
                </div>
                
                <div>
                  <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Dimensions</label>
                  <p class="mt-1 text-sm text-gray-900 dark:text-gray-100">
                    {{ template.width_mm }}×{{ template.height_mm }}mm
                    ({{ (template.width_mm / 25.4).toFixed(2) }}×{{ (template.height_mm / 25.4).toFixed(2) }} inches)
                  </p>
                </div>
                
                <div>
                  <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Status</label>
                  <div class="mt-1 flex items-center space-x-2">
                    <span :class="template.is_active ? 'text-green-600' : 'text-red-600'">
                      <i :class="template.is_active ? 'fas fa-circle' : 'fas fa-stop-circle'"></i>
                      {{ template.is_active ? 'Active' : 'Inactive' }}
                    </span>
                  </div>
                </div>
                
                <div v-if="template.created_at">
                  <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Created</label>
                  <p class="mt-1 text-sm text-gray-900 dark:text-gray-100">
                    {{ formatDate(template.created_at) }}
                  </p>
                </div>
                
                <div v-if="template.updated_at">
                  <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Last Modified</label>
                  <p class="mt-1 text-sm text-gray-900 dark:text-gray-100">
                    {{ formatDate(template.updated_at) }}
                  </p>
                </div>
              </div>
              
              <!-- Usage Stats -->
              <div class="space-y-4">
                <div>
                  <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Usage Statistics</label>
                  <div class="mt-1 grid grid-cols-2 gap-4 text-center">
                    <div class="bg-blue-50 dark:bg-blue-900 rounded-lg p-3">
                      <div class="text-2xl font-bold text-blue-600 dark:text-blue-400">
                        {{ statistics.total_prints || 0 }}
                      </div>
                      <div class="text-xs text-blue-800 dark:text-blue-200">Total Prints</div>
                    </div>
                    <div class="bg-green-50 dark:bg-green-900 rounded-lg p-3">
                      <div class="text-2xl font-bold text-green-600 dark:text-green-400">
                        {{ statistics.this_month || 0 }}
                      </div>
                      <div class="text-xs text-green-800 dark:text-green-200">This Month</div>
                    </div>
                  </div>
                </div>
                
                <div v-if="statistics.last_used_at">
                  <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Last Used</label>
                  <p class="mt-1 text-sm text-gray-900 dark:text-gray-100">
                    {{ formatDate(statistics.last_used_at) }}
                  </p>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Template Preview -->
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg">
          <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
            <div class="flex justify-between items-center">
              <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                Preview
              </h3>
              <div class="flex items-center space-x-4">
                <label for="sample-product" class="text-sm font-medium text-gray-700 dark:text-gray-300">
                  Sample Data:
                </label>
                <select id="sample-product" 
                        v-model="selectedSampleProduct"
                        class="px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 text-sm">
                  <option value="demo">Demo Product</option>
                  <option v-for="product in recentProducts" 
                          :key="product.id" 
                          :value="product.id">
                    {{ product.name }} ({{ product.internal_sku || product.sku }})
                  </option>
                </select>
              </div>
            </div>
          </div>
          <div class="p-6">
            <div class="flex justify-center">
              <LabelPreview :template="template" 
                           :product="currentSampleProduct" 
                           :scale="6"
                           :show-controls="true"
                           max-width="600px" />
            </div>
          </div>
        </div>

        <!-- Template Elements -->
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg">
          <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">
              Template Elements
            </h3>
          </div>
          <div class="p-6">
            <div v-if="templateElements.length === 0" class="text-center py-8 text-gray-500 dark:text-gray-400">
              <i class="fas fa-layer-group text-3xl mb-2"></i>
              <p>No elements configured</p>
            </div>
            <div v-else class="overflow-x-auto">
              <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-700">
                  <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                      Type
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                      Field/Data
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                      Position
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                      Properties
                    </th>
                  </tr>
                </thead>
                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                  <tr v-for="(element, index) in templateElements" :key="index">
                    <td class="px-6 py-4 whitespace-nowrap">
                      <div class="flex items-center">
                        <i :class="getElementIcon(element.type)" class="text-gray-500 mr-2"></i>
                        <span class="text-sm font-medium text-gray-900 dark:text-gray-100">
                          {{ formatElementType(element.type) }}
                        </span>
                      </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                      {{ element.field || element.content || '-' }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                      ({{ element.x }}, {{ element.y }})
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                      <div class="space-y-1">
                        <div v-if="element.size">Size: {{ element.size }}</div>
                        <div v-if="element.width">Width: {{ element.width }}</div>
                        <div v-if="element.height">Height: {{ element.height }}</div>
                        <div v-if="element.weight">Weight: {{ element.weight }}</div>
                        <div v-if="element.color">Color: {{ element.color }}</div>
                      </div>
                    </td>
                  </tr>
                </tbody>
              </table>
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
              <Link :href="route('printing.jobs.index', { template_id: template.id })" 
                    class="text-blue-600 dark:text-blue-400 hover:underline text-sm">
                View All Jobs
              </Link>
            </div>
          </div>

          <div v-if="recentJobs.length === 0" class="p-6 text-center">
            <div class="text-gray-500 dark:text-gray-400">
              <i class="fas fa-history text-3xl mb-2"></i>
              <p class="text-sm">No print jobs using this template</p>
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
                    Printer
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
                    {{ job.printer?.name || 'Unknown' }}
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
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Link } from '@inertiajs/vue3'
import LabelPreview from '@/Components/Printing/LabelPreview.vue'
import PrintJobStatus from '@/Components/Printing/PrintJobStatus.vue'

export default {
  name: 'TemplateShow',
  components: {
    AppLayout,
    Link,
    LabelPreview,
    PrintJobStatus
  },
  props: {
    template: {
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
    recentProducts: {
      type: Array,
      default: () => []
    }
  },
  data() {
    return {
      printingTest: false,
      selectedSampleProduct: 'demo',
      demoProduct: {
        id: 1,
        name: 'Demo Product Label',
        sku: 'DEMO-001',
        internal_sku: 'ORG-000001',
        organization: { name: 'Your Organization' },
        warehouse_location: { location_code: 'A1-B2-C3' },
        created_at: new Date().toISOString(),
        brand: { name: 'Demo Brand' },
        condition: 'New',
        selling_price: 29.99
      }
    }
  },
  computed: {
    templateElements() {
      return this.template.template_data?.elements || [];
    },
    currentSampleProduct() {
      if (this.selectedSampleProduct === 'demo') {
        return this.demoProduct;
      }
      return this.recentProducts.find(p => p.id == this.selectedSampleProduct) || this.demoProduct;
    }
  },
  methods: {
    formatDate(date) {
      return new Date(date).toLocaleString();
    },
    
    getElementIcon(type) {
      switch (type) {
        case 'text': return 'fas fa-font';
        case 'qr_code': return 'fas fa-qrcode';
        case 'barcode': return 'fas fa-barcode';
        case 'line': return 'fas fa-minus';
        case 'image': return 'fas fa-image';
        case 'rectangle': return 'far fa-square';
        default: return 'fas fa-question';
      }
    },
    
    formatElementType(type) {
      switch (type) {
        case 'text': return 'Text';
        case 'qr_code': return 'QR Code';
        case 'barcode': return 'Barcode';
        case 'line': return 'Line';
        case 'image': return 'Image';
        case 'rectangle': return 'Rectangle';
        default: return type;
      }
    },
    
    async printTestLabel() {
      this.printingTest = true;
      
      try {
        const response = await axios.post(`/printing/templates/${this.template.id}/test-print`, {
          sample_product: this.currentSampleProduct
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
    
    async duplicateTemplate() {
      try {
        const response = await axios.post(`/printing/templates/${this.template.id}/duplicate`);
        this.$toast.success('Template duplicated successfully');
        this.$inertia.visit(route('printing.templates.edit', response.data.template.id));
      } catch (error) {
        this.$toast.error(`Failed to duplicate template: ${error.response?.data?.message || error.message}`);
      }
    }
  }
}
</script>
