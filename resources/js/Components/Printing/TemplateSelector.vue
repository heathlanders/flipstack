<template>
  <div class="template-selector">
    <label v-if="label" 
           :for="inputId" 
           class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
      {{ label }}
      <span v-if="required" class="text-red-500">*</span>
    </label>
    
    <div class="relative">
      <select :id="inputId" 
              v-model="selectedValue" 
              :required="required"
              :disabled="disabled"
              @change="handleChange"
              class="block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-gray-100 sm:text-sm disabled:opacity-50 disabled:cursor-not-allowed"
              :class="{ 'border-red-300 dark:border-red-600': hasError }">
        
        <option value="" :disabled="required">
          {{ placeholder || (required ? 'Select a template' : 'Default Template') }}
        </option>
        
        <!-- Grouped by size if showGrouped is true -->
        <template v-if="showGrouped">
          <optgroup v-for="group in groupedTemplates" 
                    :key="group.label" 
                    :label="group.label">
            <option v-for="template in group.templates" 
                    :key="template.id" 
                    :value="template.id"
                    :disabled="!template.is_active">
              {{ template.name }}
              <span v-if="showDetails">
                ({{ template.width_mm }}×{{ template.height_mm }}mm)
              </span>
              <span v-if="template.is_default" class="text-blue-500">✓</span>
              <span v-if="!template.is_active" class="text-red-500">[Inactive]</span>
            </option>
          </optgroup>
        </template>
        
        <!-- Flat list -->
        <template v-else>
          <option v-for="template in sortedTemplates" 
                  :key="template.id" 
                  :value="template.id"
                  :disabled="!template.is_active">
            {{ template.name }}
            <span v-if="showDetails">
              ({{ template.width_mm }}×{{ template.height_mm }}mm)
            </span>
            <span v-if="template.is_default" class="text-blue-500">✓</span>
            <span v-if="!template.is_active" class="text-red-500">[Inactive]</span>
          </option>
        </template>
      </select>
      
      <!-- Loading indicator -->
      <div v-if="loading" 
           class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
        <i class="fas fa-spinner fa-spin text-gray-400"></i>
      </div>
    </div>
    
    <!-- Template preview -->
    <div v-if="selectedTemplate && showPreview" 
         class="mt-3 p-3 border border-gray-200 dark:border-gray-700 rounded-lg bg-gray-50 dark:bg-gray-700">
      <div class="flex items-start justify-between mb-2">
        <div>
          <h4 class="text-sm font-medium text-gray-900 dark:text-gray-100">
            {{ selectedTemplate.name }}
          </h4>
          <p class="text-xs text-gray-500 dark:text-gray-400">
            {{ selectedTemplate.width_mm }}×{{ selectedTemplate.height_mm }}mm
            <span v-if="selectedTemplate.description">• {{ selectedTemplate.description }}</span>
          </p>
        </div>
        <button v-if="allowPreviewToggle" 
                @click="showPreviewModal = true"
                class="text-blue-600 dark:text-blue-400 hover:underline text-xs">
          Preview
        </button>
      </div>
      
      <!-- Mini preview -->
      <div v-if="sampleProduct" class="flex justify-center">
        <LabelPreview :template="selectedTemplate" 
                     :product="sampleProduct" 
                     :scale="1.5"
                     :show-controls="false"
                     max-width="150px" />
      </div>
    </div>
    
    <!-- Template info -->
    <div v-if="selectedTemplate && showInfo" 
         class="mt-2 text-xs text-gray-500 dark:text-gray-400">
      <div class="flex items-center space-x-4">
        <span>{{ selectedTemplate.width_mm }}×{{ selectedTemplate.height_mm }}mm</span>
        <span v-if="selectedTemplate.is_default" class="text-blue-600 dark:text-blue-400">
          <i class="fas fa-star"></i> Default
        </span>
        <span class="text-green-600 dark:text-green-400">
          {{ getElementCount(selectedTemplate) }} elements
        </span>
      </div>
    </div>
    
    <!-- Error message -->
    <p v-if="error" class="mt-1 text-sm text-red-600 dark:text-red-400">
      {{ error }}
    </p>
    
    <!-- Help text -->
    <p v-if="helpText && !error" class="mt-1 text-xs text-gray-500 dark:text-gray-400">
      {{ helpText }}
    </p>
    
    <!-- Actions -->
    <div v-if="showActions" class="mt-3 flex justify-between items-center">
      <div class="flex space-x-2">
        <button v-if="allowCreate" 
                @click="$emit('create-template')"
                class="text-blue-600 dark:text-blue-400 hover:underline text-xs">
          <i class="fas fa-plus mr-1"></i>
          Create Template
        </button>
        
        <button v-if="selectedTemplate && allowEdit" 
                @click="$emit('edit-template', selectedTemplate)"
                class="text-green-600 dark:text-green-400 hover:underline text-xs">
          <i class="fas fa-edit mr-1"></i>
          Edit
        </button>
      </div>
      
      <button v-if="selectedTemplate && allowDuplicate" 
              @click="duplicateTemplate"
              class="text-purple-600 dark:text-purple-400 hover:underline text-xs">
        <i class="fas fa-copy mr-1"></i>
        Duplicate
      </button>
    </div>
    
    <!-- Preview Modal -->
    <div v-if="showPreviewModal && selectedTemplate" 
         class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50"
         @click.self="showPreviewModal = false">
      <div class="relative top-20 mx-auto p-5 border w-full max-w-2xl shadow-lg rounded-md bg-white dark:bg-gray-800">
        <div class="flex justify-between items-center mb-4">
          <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">
            {{ selectedTemplate.name }} Preview
          </h3>
          <button @click="showPreviewModal = false" 
                  class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">
            <i class="fas fa-times"></i>
          </button>
        </div>
        
        <div class="flex justify-center">
          <LabelPreview :template="selectedTemplate" 
                       :product="sampleProduct || defaultSampleProduct" 
                       :scale="4"
                       :show-controls="true"
                       max-width="100%" />
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import LabelPreview from './LabelPreview.vue'

export default {
  name: 'TemplateSelector',
  components: {
    LabelPreview
  },
  props: {
    value: {
      type: [String, Number],
      default: ''
    },
    templates: {
      type: Array,
      default: () => []
    },
    label: {
      type: String,
      default: ''
    },
    placeholder: {
      type: String,
      default: ''
    },
    required: {
      type: Boolean,
      default: false
    },
    disabled: {
      type: Boolean,
      default: false
    },
    loading: {
      type: Boolean,
      default: false
    },
    error: {
      type: String,
      default: ''
    },
    helpText: {
      type: String,
      default: ''
    },
    showDetails: {
      type: Boolean,
      default: true
    },
    showGrouped: {
      type: Boolean,
      default: false
    },
    showPreview: {
      type: Boolean,
      default: false
    },
    showInfo: {
      type: Boolean,
      default: false
    },
    showActions: {
      type: Boolean,
      default: false
    },
    allowCreate: {
      type: Boolean,
      default: false
    },
    allowEdit: {
      type: Boolean,
      default: false
    },
    allowDuplicate: {
      type: Boolean,
      default: false
    },
    allowPreviewToggle: {
      type: Boolean,
      default: false
    },
    sampleProduct: {
      type: Object,
      default: null
    },
    filterBySize: {
      type: String,
      default: '' // 'small', 'medium', 'large'
    }
  },
  data() {
    return {
      selectedValue: this.value,
      showPreviewModal: false,
      inputId: `template-selector-${Math.random().toString(36).substr(2, 9)}`,
      defaultSampleProduct: {
        id: 1,
        name: 'Sample Product',
        sku: 'SAMPLE-001',
        internal_sku: 'ORG-000001',
        organization: { name: 'Sample Org' },
        warehouse_location: { location_code: 'A1-B2-C3' },
        created_at: new Date().toISOString()
      }
    }
  },
  computed: {
    hasError() {
      return !!this.error;
    },
    filteredTemplates() {
      let filtered = this.templates.filter(t => t.is_active || t.id === this.selectedValue);
      
      if (this.filterBySize) {
        filtered = filtered.filter(template => {
          const maxDimension = Math.max(template.width_mm, template.height_mm);
          switch (this.filterBySize) {
            case 'small': return maxDimension <= 50;
            case 'medium': return maxDimension > 50 && maxDimension <= 75;
            case 'large': return maxDimension > 75;
            default: return true;
          }
        });
      }
      
      return filtered;
    },
    sortedTemplates() {
      return [...this.filteredTemplates].sort((a, b) => {
        // Default templates first
        if (a.is_default && !b.is_default) return -1;
        if (!a.is_default && b.is_default) return 1;
        // Then alphabetical
        return a.name.localeCompare(b.name);
      });
    },
    groupedTemplates() {
      if (!this.showGrouped) return [];
      
      const groups = {
        small: { label: 'Small (≤ 50mm)', templates: [] },
        medium: { label: 'Medium (51-75mm)', templates: [] },
        large: { label: 'Large (> 75mm)', templates: [] }
      };
      
      this.sortedTemplates.forEach(template => {
        const maxDimension = Math.max(template.width_mm, template.height_mm);
        if (maxDimension <= 50) {
          groups.small.templates.push(template);
        } else if (maxDimension <= 75) {
          groups.medium.templates.push(template);
        } else {
          groups.large.templates.push(template);
        }
      });
      
      return Object.values(groups).filter(group => group.templates.length > 0);
    },
    selectedTemplate() {
      if (!this.selectedValue) return null;
      return this.templates.find(t => t.id == this.selectedValue);
    }
  },
  watch: {
    value(newVal) {
      this.selectedValue = newVal;
    },
    selectedValue(newVal) {
      this.$emit('input', newVal);
      this.$emit('change', newVal, this.selectedTemplate);
    }
  },
  methods: {
    handleChange() {
      this.$emit('template-selected', this.selectedTemplate);
    },
    
    getElementCount(template) {
      return template.template_data?.elements?.length || 0;
    },
    
    async duplicateTemplate() {
      if (!this.selectedTemplate) return;
      
      try {
        const response = await axios.post(`/printing/templates/${this.selectedTemplate.id}/duplicate`);
        this.$toast.success('Template duplicated successfully');
        this.$emit('template-duplicated', response.data.template);
      } catch (error) {
        this.$toast.error(`Failed to duplicate template: ${error.response?.data?.message || error.message}`);
      }
    }
  }
}
</script>
