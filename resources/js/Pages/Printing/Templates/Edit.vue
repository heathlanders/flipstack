<template>
  <AppLayout :title="`Edit Template: ${template.name}`">
    <template #header>
      <div class="flex items-center space-x-4">
        <Link :href="route('printing.templates.show', template.id)" 
              class="text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300">
          <i class="fas fa-arrow-left"></i>
        </Link>
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
          Edit Template: {{ template.name }}
        </h2>
      </div>
    </template>

    <div class="py-12">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <form @submit.prevent="updateTemplate" class="space-y-6">
          
          <!-- Template Info -->
          <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg">
            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
              <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                Template Information
              </h3>
            </div>
            <div class="p-6 space-y-6">
              <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Name -->
                <div>
                  <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                    Template Name *
                  </label>
                  <input id="name" 
                         v-model="form.name" 
                         type="text" 
                         required 
                         class="block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-gray-100 sm:text-sm">
                </div>
                
                <!-- Description -->
                <div>
                  <label for="description" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                    Description
                  </label>
                  <input id="description" 
                         v-model="form.description" 
                         type="text" 
                         class="block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-gray-100 sm:text-sm">
                </div>
              </div>
              
              <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                <!-- Width -->
                <div>
                  <label for="width" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                    Width (mm) *
                  </label>
                  <input id="width" 
                         v-model.number="form.width_mm" 
                         type="number" 
                         min="10" 
                         max="200" 
                         required 
                         class="block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-gray-100 sm:text-sm">
                </div>
                
                <!-- Height -->
                <div>
                  <label for="height" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                    Height (mm) *
                  </label>
                  <input id="height" 
                         v-model.number="form.height_mm" 
                         type="number" 
                         min="10" 
                         max="200" 
                         required 
                         class="block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-gray-100 sm:text-sm">
                </div>
                
                <!-- Status -->
                <div class="flex items-center pt-6">
                  <input id="is_active" 
                         v-model="form.is_active" 
                         type="checkbox" 
                         class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 dark:border-gray-600 rounded dark:bg-gray-700">
                  <label for="is_active" class="ml-2 block text-sm text-gray-900 dark:text-gray-100">
                    Active template
                  </label>
                </div>
                
                <!-- Default -->
                <div class="flex items-center pt-6">
                  <input id="is_default" 
                         v-model="form.is_default" 
                         type="checkbox" 
                         class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 dark:border-gray-600 rounded dark:bg-gray-700">
                  <label for="is_default" class="ml-2 block text-sm text-gray-900 dark:text-gray-100">
                    Set as default
                  </label>
                </div>
              </div>
            </div>
          </div>

          <!-- Template Designer -->
          <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg">
            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
              <div class="flex justify-between items-center">
                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                  Template Design
                </h3>
                <div class="flex space-x-2">
                  <button type="button" 
                          @click="addElement('text')"
                          class="inline-flex items-center px-3 py-1 border border-gray-300 dark:border-gray-600 rounded-md text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    <i class="fas fa-font mr-1"></i>
                    Text
                  </button>
                  <button type="button" 
                          @click="addElement('qr_code')"
                          class="inline-flex items-center px-3 py-1 border border-gray-300 dark:border-gray-600 rounded-md text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    <i class="fas fa-qrcode mr-1"></i>
                    QR Code
                  </button>
                  <button type="button" 
                          @click="addElement('barcode')"
                          class="inline-flex items-center px-3 py-1 border border-gray-300 dark:border-gray-600 rounded-md text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    <i class="fas fa-barcode mr-1"></i>
                    Barcode
                  </button>
                  <button type="button" 
                          @click="addElement('line')"
                          class="inline-flex items-center px-3 py-1 border border-gray-300 dark:border-gray-600 rounded-md text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    <i class="fas fa-minus mr-1"></i>
                    Line
                  </button>
                </div>
              </div>
            </div>
            <div class="p-6">
              <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                
                <!-- Preview -->
                <div class="space-y-4">
                  <h4 class="font-medium text-gray-900 dark:text-gray-100">Preview</h4>
                  <div class="border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-lg p-4 bg-gray-50 dark:bg-gray-700">
                    <LabelPreview :template="previewTemplate" 
                                 :product="sampleProduct" 
                                 :scale="4"
                                 class="mx-auto" />
                  </div>
                  
                  <!-- Usage Stats -->
                  <div v-if="template.usage_stats" class="bg-blue-50 dark:bg-blue-900 rounded-lg p-4">
                    <h5 class="text-sm font-medium text-blue-900 dark:text-blue-100 mb-2">Usage Statistics</h5>
                    <div class="grid grid-cols-2 gap-4 text-sm">
                      <div>
                        <span class="text-blue-700 dark:text-blue-300">Total Prints:</span>
                        <span class="font-medium text-blue-900 dark:text-blue-100">{{ template.usage_stats.total_prints || 0 }}</span>
                      </div>
                      <div>
                        <span class="text-blue-700 dark:text-blue-300">This Month:</span>
                        <span class="font-medium text-blue-900 dark:text-blue-100">{{ template.usage_stats.this_month || 0 }}</span>
                      </div>
                    </div>
                  </div>
                </div>
                
                <!-- Element Configuration -->
                <div class="space-y-4">
                  <div class="flex justify-between items-center">
                    <h4 class="font-medium text-gray-900 dark:text-gray-100">Elements</h4>
                    <span class="text-sm text-gray-500 dark:text-gray-400">
                      {{ form.template_data.elements.length }} element(s)
                    </span>
                  </div>
                  
                  <div v-if="form.template_data.elements.length === 0" 
                       class="text-center py-8 text-gray-500 dark:text-gray-400">
                    <i class="fas fa-plus-circle text-3xl mb-2"></i>
                    <p>Add elements to design your label</p>
                  </div>
                  
                  <div v-else class="space-y-3 max-h-96 overflow-y-auto">
                    <div v-for="(element, index) in form.template_data.elements" 
                         :key="index"
                         class="border border-gray-200 dark:border-gray-600 rounded-lg p-4"
                         :class="{ 'border-blue-500 bg-blue-50 dark:bg-blue-900': selectedElement === index }">
                      
                      <div class="flex justify-between items-start mb-3">
                        <div class="flex items-center space-x-2">
                          <i :class="getElementIcon(element.type)" class="text-gray-500"></i>
                          <span class="font-medium text-gray-900 dark:text-gray-100">
                            {{ formatElementType(element.type) }}
                          </span>
                          <button @click="selectedElement = selectedElement === index ? null : index"
                                  class="text-blue-600 dark:text-blue-400 hover:underline text-sm">
                            {{ selectedElement === index ? 'Collapse' : 'Configure' }}
                          </button>
                        </div>
                        <button @click="removeElement(index)"
                                class="text-red-600 dark:text-red-400 hover:underline text-sm">
                          <i class="fas fa-trash-alt"></i>
                        </button>
                      </div>
                      
                      <!-- Element Configuration (Same as template-create.vue) -->
                      <div v-if="selectedElement === index" class="space-y-3">
                        <!-- Position -->
                        <div class="grid grid-cols-2 gap-3">
                          <div>
                            <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">
                              X Position (mm)
                            </label>
                            <input v-model.number="element.x" 
                                   type="number" 
                                   min="0" 
                                   :max="form.width_mm"
                                   class="block w-full px-2 py-1 border border-gray-300 dark:border-gray-600 rounded text-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-gray-100">
                          </div>
                          <div>
                            <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">
                              Y Position (mm)
                            </label>
                            <input v-model.number="element.y" 
                                   type="number" 
                                   min="0" 
                                   :max="form.height_mm"
                                   class="block w-full px-2 py-1 border border-gray-300 dark:border-gray-600 rounded text-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-gray-100">
                          </div>
                        </div>
                        
                        <!-- Text Specific -->
                        <div v-if="element.type === 'text'" class="space-y-3">
                          <div>
                            <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">
                              Field
                            </label>
                            <select v-model="element.field" 
                                    class="block w-full px-2 py-1 border border-gray-300 dark:border-gray-600 rounded text-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-gray-100">
                              <option value="sku">SKU</option>
                              <option value="product_name">Product Name</option>
                              <option value="location">Location</option>
                              <option value="organization">Organization</option>
                              <option value="date_created">Date Created</option>
                            </select>
                          </div>
                          <div class="grid grid-cols-2 gap-3">
                            <div>
                              <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Font Size
                              </label>
                              <input v-model.number="element.size" 
                                     type="number" 
                                     min="6" 
                                     max="72"
                                     class="block w-full px-2 py-1 border border-gray-300 dark:border-gray-600 rounded text-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-gray-100">
                            </div>
                            <div>
                              <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Font Weight
                              </label>
                              <select v-model="element.weight" 
                                      class="block w-full px-2 py-1 border border-gray-300 dark:border-gray-600 rounded text-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-gray-100">
                                <option value="normal">Normal</option>
                                <option value="bold">Bold</option>
                              </select>
                            </div>
                          </div>
                        </div>
                        
                        <!-- QR Code Specific -->
                        <div v-else-if="element.type === 'qr_code'" class="space-y-3">
                          <div>
                            <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">
                              Data Field
                            </label>
                            <select v-model="element.field" 
                                    class="block w-full px-2 py-1 border border-gray-300 dark:border-gray-600 rounded text-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-gray-100">
                              <option value="qr_data">Full QR Data</option>
                              <option value="qr_code">SKU Only</option>
                              <option value="sku">SKU</option>
                            </select>
                          </div>
                          <div>
                            <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">
                              Size (mm)
                            </label>
                            <input v-model.number="element.size" 
                                   type="number" 
                                   min="5" 
                                   max="50"
                                   class="block w-full px-2 py-1 border border-gray-300 dark:border-gray-600 rounded text-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-gray-100">
                          </div>
                        </div>
                        
                        <!-- Barcode Specific -->
                        <div v-else-if="element.type === 'barcode'" class="space-y-3">
                          <div>
                            <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">
                              Data Field
                            </label>
                            <select v-model="element.field" 
                                    class="block w-full px-2 py-1 border border-gray-300 dark:border-gray-600 rounded text-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-gray-100">
                              <option value="sku">SKU</option>
                              <option value="barcode_data">Barcode Data</option>
                            </select>
                          </div>
                          <div class="grid grid-cols-2 gap-3">
                            <div>
                              <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Width (mm)
                              </label>
                              <input v-model.number="element.width" 
                                     type="number" 
                                     min="10" 
                                     max="100"
                                     class="block w-full px-2 py-1 border border-gray-300 dark:border-gray-600 rounded text-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-gray-100">
                            </div>
                            <div>
                              <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Height (mm)
                              </label>
                              <input v-model.number="element.height" 
                                     type="number" 
                                     min="5" 
                                     max="50"
                                     class="block w-full px-2 py-1 border border-gray-300 dark:border-gray-600 rounded text-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-gray-100">
                            </div>
                          </div>
                        </div>
                        
                        <!-- Line Specific -->
                        <div v-else-if="element.type === 'line'" class="space-y-3">
                          <div class="grid grid-cols-2 gap-3">
                            <div>
                              <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Length (mm)
                              </label>
                              <input v-model.number="element.length" 
                                     type="number" 
                                     min="1" 
                                     max="200"
                                     class="block w-full px-2 py-1 border border-gray-300 dark:border-gray-600 rounded text-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-gray-100">
                            </div>
                            <div>
                              <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Width (mm)
                              </label>
                              <input v-model.number="element.width" 
                                     type="number" 
                                     min="0.1" 
                                     max="10"
                                     step="0.1"
                                     class="block w-full px-2 py-1 border border-gray-300 dark:border-gray-600 rounded text-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-gray-100">
                            </div>
                          </div>
                          <div>
                            <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">
                              Orientation
                            </label>
                            <select v-model="element.orientation" 
                                    class="block w-full px-2 py-1 border border-gray-300 dark:border-gray-600 rounded text-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-gray-100">
                              <option value="horizontal">Horizontal</option>
                              <option value="vertical">Vertical</option>
                            </select>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Actions -->
          <div class="flex justify-end space-x-4">
            <Link :href="route('printing.templates.show', template.id)" 
                  class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
              Cancel
            </Link>
            <button type="submit" 
                    :disabled="saving"
                    class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 active:bg-blue-900 focus:outline-none focus:border-blue-900 focus:ring ring-blue-300 disabled:opacity-25 transition ease-in-out duration-150">
              <i v-if="saving" class="fas fa-spinner fa-spin mr-2"></i>
              <i v-else class="fas fa-save mr-2"></i>
              {{ saving ? 'Updating...' : 'Update Template' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </AppLayout>
</template>

<script>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Link } from '@inertiajs/vue3'
import LabelPreview from '@/Components/Printing/LabelPreview.vue'

export default {
  name: 'TemplateEdit',
  components: {
    AppLayout,
    Link,
    LabelPreview
  },
  props: {
    template: {
      type: Object,
      required: true
    }
  },
  data() {
    return {
      saving: false,
      selectedElement: null,
      form: {
        name: this.template.name,
        description: this.template.description,
        width_mm: this.template.width_mm,
        height_mm: this.template.height_mm,
        is_active: this.template.is_active,
        is_default: this.template.is_default,
        template_data: {
          elements: [...(this.template.template_data?.elements || [])]
        }
      },
      sampleProduct: {
        id: 1,
        name: 'Sample Product',
        sku: 'SAMPLE-001',
        internal_sku: 'DEMO-000001',
        organization: { name: 'Demo Organization' },
        warehouse_location: { location_code: 'A1-B2-C3' },
        created_at: new Date().toISOString()
      }
    }
  },
  computed: {
    previewTemplate() {
      return {
        ...this.form,
        template_data: {
          elements: [...this.form.template_data.elements]
        }
      };
    }
  },
  methods: {
    addElement(type) {
      const element = {
        type,
        x: 5,
        y: 5
      };
      
      switch (type) {
        case 'text':
          Object.assign(element, {
            field: 'sku',
            size: 12,
            weight: 'normal'
          });
          break;
        case 'qr_code':
          Object.assign(element, {
            field: 'qr_data',
            size: 10
          });
          break;
        case 'barcode':
          Object.assign(element, {
            field: 'sku',
            width: 30,
            height: 10
          });
          break;
        case 'line':
          Object.assign(element, {
            length: 20,
            width: 1,
            orientation: 'horizontal'
          });
          break;
      }
      
      this.form.template_data.elements.push(element);
      this.selectedElement = this.form.template_data.elements.length - 1;
    },
    
    removeElement(index) {
      this.form.template_data.elements.splice(index, 1);
      if (this.selectedElement >= index) {
        this.selectedElement = null;
      }
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
    
    async updateTemplate() {
      this.saving = true;
      
      try {
        await axios.patch(`/printing/templates/${this.template.id}`, this.form);
        this.$toast.success('Template updated successfully');
        this.$inertia.visit(route('printing.templates.show', this.template.id));
      } catch (error) {
        this.$toast.error('Failed to update template: ' + (error.response?.data?.message || error.message));
      } finally {
        this.saving = false;
      }
    }
  }
}
</script>
