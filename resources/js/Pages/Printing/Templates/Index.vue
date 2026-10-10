<template>
  <AppLayout title="Label Templates">
    <template #header>
      <div class="flex justify-between items-center">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
          Label Templates
        </h2>
        <div class="flex space-x-3">
          <button @click="createDefaultTemplates" 
                  :disabled="creatingDefaults"
                  class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50">
            <i v-if="creatingDefaults" class="fas fa-spinner fa-spin mr-2"></i>
            <i v-else class="fas fa-magic mr-2"></i>
            Create Defaults
          </button>
          <Link :href="route('printing.templates.create')" 
                class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 active:bg-blue-900 focus:outline-none focus:border-blue-900 focus:ring ring-blue-300 transition ease-in-out duration-150">
            <i class="fas fa-plus mr-2"></i>
            Create Template
          </Link>
        </div>
      </div>
    </template>

    <div class="py-12">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
        
        <!-- Filters and Search -->
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg">
          <div class="p-6 border-b border-gray-200 dark:border-gray-700">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
              <!-- Search -->
              <div class="md:col-span-2">
                <label for="search" class="sr-only">Search templates</label>
                <div class="relative">
                  <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <i class="fas fa-search text-gray-400"></i>
                  </div>
                  <input id="search" 
                         v-model="filters.search"
                         type="text" 
                         placeholder="Search templates..."
                         class="block w-full pl-10 pr-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md leading-5 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 placeholder-gray-500 dark:placeholder-gray-400 focus:outline-none focus:placeholder-gray-400 dark:focus:placeholder-gray-500 focus:ring-1 focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                </div>
              </div>
              
              <!-- Size Filter -->
              <div>
                <label for="size-filter" class="sr-only">Filter by size</label>
                <select id="size-filter" 
                        v-model="filters.size"
                        class="block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                  <option value="">All Sizes</option>
                  <option value="small">Small (≤ 50mm)</option>
                  <option value="medium">Medium (51-75mm)</option>
                  <option value="large">Large (> 75mm)</option>
                </select>
              </div>
            </div>
          </div>
        </div>

        <!-- Templates Grid -->
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg">
          <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
            <div class="flex justify-between items-center">
              <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                Templates ({{ filteredTemplates.length }})
              </h3>
              <div class="flex items-center space-x-3">
                <span class="text-sm text-gray-500 dark:text-gray-400">
                  View:
                </span>
                <button @click="viewMode = 'grid'" 
                        :class="viewMode === 'grid' ? 'text-blue-600 dark:text-blue-400' : 'text-gray-400'"
                        class="hover:text-blue-600 dark:hover:text-blue-400">
                  <i class="fas fa-th-large"></i>
                </button>
                <button @click="viewMode = 'list'" 
                        :class="viewMode === 'list' ? 'text-blue-600 dark:text-blue-400' : 'text-gray-400'"
                        class="hover:text-blue-600 dark:hover:text-blue-400">
                  <i class="fas fa-list"></i>
                </button>
              </div>
            </div>
          </div>

          <div v-if="filteredTemplates.length === 0" class="p-6 text-center">
            <div class="text-gray-500 dark:text-gray-400">
              <i class="fas fa-tags text-4xl mb-4"></i>
              <p class="text-lg font-medium">No templates found</p>
              <p class="text-sm">
                {{ templates.length === 0 ? 'Create your first template to get started.' : 'Try adjusting your filters.' }}
              </p>
            </div>
          </div>

          <!-- Grid View -->
          <div v-else-if="viewMode === 'grid'" class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
              <div v-for="template in filteredTemplates" 
                   :key="template.id" 
                   class="border border-gray-200 dark:border-gray-700 rounded-lg p-4 hover:shadow-lg transition-shadow cursor-pointer"
                   @click="$inertia.visit(route('printing.templates.show', template.id))">
                
                <!-- Template Preview -->
                <div class="relative mb-4">
                  <div class="w-full h-32 bg-gray-50 dark:bg-gray-700 rounded border-2 border-dashed border-gray-300 dark:border-gray-600 flex items-center justify-center">
                    <LabelPreview v-if="template.template_data" 
                                 :template="template" 
                                 :product="sampleProduct"
                                 :scale="1.5"
                                 :show-controls="false"
                                 max-width="100%" />
                    <div v-else class="text-gray-400">
                      <i class="fas fa-tags text-2xl"></i>
                      <p class="text-sm mt-1">No Preview</p>
                    </div>
                  </div>
                  
                  <!-- Default Badge -->
                  <div v-if="template.is_default" 
                       class="absolute top-2 right-2">
                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-800 dark:text-blue-100">
                      <i class="fas fa-star mr-1"></i>
                      Default
                    </span>
                  </div>
                </div>
                
                <!-- Template Info -->
                <div class="space-y-2">
                  <h4 class="font-medium text-gray-900 dark:text-gray-100 truncate">
                    {{ template.name }}
                  </h4>
                  <p v-if="template.description" 
                     class="text-sm text-gray-500 dark:text-gray-400 line-clamp-2">
                    {{ template.description }}
                  </p>
                  <div class="flex items-center justify-between text-sm text-gray-500 dark:text-gray-400">
                    <span>{{ template.width_mm }}×{{ template.height_mm }}mm</span>
                    <span :class="template.is_active ? 'text-green-600' : 'text-red-600'">
                      <i :class="template.is_active ? 'fas fa-circle' : 'fas fa-stop-circle'"></i>
                      {{ template.is_active ? 'Active' : 'Inactive' }}
                    </span>
                  </div>
                </div>
                
                <!-- Actions -->
                <div class="mt-4 flex justify-between items-center">
                  <div class="text-xs text-gray-400">
                    {{ formatDate(template.updated_at) }}
                  </div>
                  <div class="flex space-x-2" @click.stop="">
                    <button @click="duplicateTemplate(template)" 
                            class="text-gray-400 hover:text-blue-600 dark:hover:text-blue-400"
                            title="Duplicate">
                      <i class="fas fa-copy"></i>
                    </button>
                    <Link :href="route('printing.templates.edit', template.id)" 
                          class="text-gray-400 hover:text-green-600 dark:hover:text-green-400"
                          title="Edit">
                      <i class="fas fa-edit"></i>
                    </Link>
                    <button @click="confirmDelete(template)" 
                            class="text-gray-400 hover:text-red-600 dark:hover:text-red-400"
                            title="Delete">
                      <i class="fas fa-trash-alt"></i>
                    </button>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- List View -->
          <div v-else class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
              <thead class="bg-gray-50 dark:bg-gray-700">
                <tr>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                    Template
                  </th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                    Size
                  </th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                    Status
                  </th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                    Updated
                  </th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                    Actions
                  </th>
                </tr>
              </thead>
              <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                <tr v-for="template in filteredTemplates" 
                    :key="template.id" 
                    class="hover:bg-gray-50 dark:hover:bg-gray-700">
                  <td class="px-6 py-4 whitespace-nowrap">
                    <div class="flex items-center">
                      <!-- Mini Preview -->
                      <div class="flex-shrink-0 w-16 h-10 mr-4">
                        <div class="w-full h-full bg-gray-50 dark:bg-gray-700 rounded border border-gray-300 dark:border-gray-600 flex items-center justify-center">
                          <i class="fas fa-tag text-gray-400 text-xs"></i>
                        </div>
                      </div>
                      
                      <div>
                        <div class="flex items-center space-x-2">
                          <div class="text-sm font-medium text-gray-900 dark:text-gray-100">
                            {{ template.name }}
                          </div>
                          <span v-if="template.is_default" 
                                class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-800 dark:text-blue-100">
                            Default
                          </span>
                        </div>
                        <div v-if="template.description" 
                             class="text-sm text-gray-500 dark:text-gray-400 truncate max-w-md">
                          {{ template.description }}
                        </div>
                      </div>
                    </div>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                    {{ template.width_mm }}×{{ template.height_mm }}mm
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap">
                    <span :class="template.is_active ? 
                          'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-800 dark:text-green-100' :
                          'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 dark:bg-red-800 dark:text-red-100'">
                      <i :class="template.is_active ? 'fas fa-circle' : 'fas fa-stop-circle'" class="mr-1"></i>
                      {{ template.is_active ? 'Active' : 'Inactive' }}
                    </span>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                    {{ formatDate(template.updated_at) }}
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                    <div class="flex space-x-3">
                      <Link :href="route('printing.templates.show', template.id)" 
                            class="text-blue-600 dark:text-blue-400 hover:underline">
                        View
                      </Link>
                      <Link :href="route('printing.templates.edit', template.id)" 
                            class="text-green-600 dark:text-green-400 hover:underline">
                        Edit
                      </Link>
                      <button @click="duplicateTemplate(template)" 
                              class="text-purple-600 dark:text-purple-400 hover:underline">
                        Duplicate
                      </button>
                      <button @click="confirmDelete(template)" 
                              class="text-red-600 dark:text-red-400 hover:underline">
                        Delete
                      </button>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Delete Confirmation Modal -->
        <div v-if="templateToDelete" 
             class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50"
             @click.self="templateToDelete = null">
          <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white dark:bg-gray-800">
            <div class="mt-3 text-center">
              <i class="fas fa-exclamation-triangle text-red-400 text-4xl mb-4"></i>
              <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-2">
                Delete Template
              </h3>
              <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">
                Are you sure you want to delete "{{ templateToDelete.name }}"? 
                This action cannot be undone.
              </p>
              <div class="flex justify-center space-x-3">
                <button @click="templateToDelete = null" 
                        class="px-4 py-2 bg-gray-300 dark:bg-gray-600 text-gray-700 dark:text-gray-300 rounded-md hover:bg-gray-400 dark:hover:bg-gray-500 focus:outline-none focus:ring-2 focus:ring-gray-500">
                  Cancel
                </button>
                <button @click="deleteTemplate" 
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
import LabelPreview from '@/Components/Printing/LabelPreview.vue'

export default {
  name: 'TemplateIndex',
  components: {
    AppLayout,
    Link,
    LabelPreview
  },
  props: {
    templates: {
      type: Array,
      default: () => []
    }
  },
  data() {
    return {
      viewMode: 'grid',
      filters: {
        search: '',
        size: ''
      },
      creatingDefaults: false,
      deleting: false,
      templateToDelete: null,
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
    filteredTemplates() {
      let filtered = this.templates;
      
      if (this.filters.search) {
        const search = this.filters.search.toLowerCase();
        filtered = filtered.filter(template => 
          template.name.toLowerCase().includes(search) ||
          (template.description && template.description.toLowerCase().includes(search))
        );
      }
      
      if (this.filters.size) {
        filtered = filtered.filter(template => {
          const maxDimension = Math.max(template.width_mm, template.height_mm);
          switch (this.filters.size) {
            case 'small': return maxDimension <= 50;
            case 'medium': return maxDimension > 50 && maxDimension <= 75;
            case 'large': return maxDimension > 75;
            default: return true;
          }
        });
      }
      
      return filtered;
    }
  },
  methods: {
    formatDate(date) {
      return new Date(date).toLocaleDateString();
    },
    
    async createDefaultTemplates() {
      this.creatingDefaults = true;
      
      try {
        await axios.post('/printing/templates/create-defaults');
        this.$toast.success('Default templates created successfully');
        await this.$inertia.reload({ only: ['templates'] });
      } catch (error) {
        this.$toast.error(`Failed to create default templates: ${error.response?.data?.message || error.message}`);
      } finally {
        this.creatingDefaults = false;
      }
    },
    
    async duplicateTemplate(template) {
      try {
        const response = await axios.post(`/printing/templates/${template.id}/duplicate`);
        this.$toast.success('Template duplicated successfully');
        this.$inertia.visit(route('printing.templates.edit', response.data.template.id));
      } catch (error) {
        this.$toast.error(`Failed to duplicate template: ${error.response?.data?.message || error.message}`);
      }
    },
    
    confirmDelete(template) {
      this.templateToDelete = template;
    },
    
    async deleteTemplate() {
      if (!this.templateToDelete) return;
      
      this.deleting = true;
      
      try {
        await axios.delete(`/printing/templates/${this.templateToDelete.id}`);
        this.$toast.success(`${this.templateToDelete.name} deleted successfully`);
        this.templateToDelete = null;
        await this.$inertia.reload({ only: ['templates'] });
      } catch (error) {
        this.$toast.error(`Failed to delete template: ${error.response?.data?.message || error.message}`);
      } finally {
        this.deleting = false;
      }
    }
  }
}
</script>

<style scoped>
.line-clamp-2 {
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}
</style>
