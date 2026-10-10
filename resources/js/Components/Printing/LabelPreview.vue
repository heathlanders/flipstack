<template>
  <div class="label-preview" :style="previewStyle">
    <div class="label-container" :style="labelContainerStyle">
      <!-- Template Elements -->
      <div v-for="element in templateElements" 
           :key="`${element.type}-${element.x}-${element.y}`"
           class="label-element absolute"
           :style="getElementStyle(element)">
        
        <!-- Text Elements -->
        <span v-if="element.type === 'text'" 
              :class="getTextClasses(element)"
              :style="getTextStyle(element)">
          {{ getFieldValue(element.field) }}
        </span>
        
        <!-- QR Code Elements -->
        <div v-else-if="element.type === 'qr_code'" 
             class="qr-code-container">
          <QRCodeGenerator :data="getFieldValue(element.field)" 
                          :size="element.size * 10" 
                          :color="element.color || '#000000'"
                          :background="element.background || '#ffffff'" />
        </div>
        
        <!-- Barcode Elements -->
        <div v-else-if="element.type === 'barcode'" 
             class="barcode-container">
          <div class="barcode-bars" 
               :style="getBarcodeStyle(element)">
            <div v-for="bar in generateBarcode(getFieldValue(element.field))" 
                 :key="bar.id"
                 class="barcode-bar"
                 :style="getBarStyle(bar, element)">
            </div>
          </div>
          <div v-if="element.show_text !== false" 
               class="barcode-text text-center text-xs mt-1">
            {{ getFieldValue(element.field) }}
          </div>
        </div>
        
        <!-- Image Elements -->
        <img v-else-if="element.type === 'image'" 
             :src="getFieldValue(element.field)" 
             :style="getImageStyle(element)"
             class="max-w-full max-h-full object-contain"
             @error="handleImageError" />
        
        <!-- Line Elements -->
        <div v-else-if="element.type === 'line'" 
             class="line-element"
             :style="getLineStyle(element)">
        </div>
        
        <!-- Rectangle Elements -->
        <div v-else-if="element.type === 'rectangle'" 
             class="rectangle-element"
             :style="getRectangleStyle(element)">
        </div>
      </div>
      
      <!-- Label Border (for preview) -->
      <div v-if="showBorder" 
           class="absolute inset-0 border-2 border-dashed border-gray-400 dark:border-gray-600 pointer-events-none">
      </div>
      
      <!-- Grid Overlay (for editing) -->
      <div v-if="showGrid" 
           class="absolute inset-0 pointer-events-none"
           :style="gridStyle">
      </div>
    </div>
    
    <!-- Preview Controls -->
    <div v-if="showControls" class="preview-controls mt-2 flex items-center justify-between text-xs text-gray-500 dark:text-gray-400">
      <div>
        {{ template?.name || 'Default Template' }} 
        ({{ template?.width_mm || 50 }}×{{ template?.height_mm || 30 }}mm)
      </div>
      <div class="flex items-center space-x-2">
        <button @click="showBorder = !showBorder" 
                :class="showBorder ? 'text-blue-600' : ''"
                title="Toggle border">
          <i class="fas fa-border-all"></i>
        </button>
        <button @click="showGrid = !showGrid" 
                :class="showGrid ? 'text-blue-600' : ''"
                title="Toggle grid">
          <i class="fas fa-th"></i>
        </button>
        <button @click="zoomIn" title="Zoom in">
          <i class="fas fa-search-plus"></i>
        </button>
        <button @click="zoomOut" title="Zoom out">
          <i class="fas fa-search-minus"></i>
        </button>
        <span class="text-xs">{{ Math.round(zoom * 100) }}%</span>
      </div>
    </div>
  </div>
</template>

<script>
import QRCodeGenerator from './QRCodeGenerator.vue'

export default {
  name: 'LabelPreview',
  components: {
    QRCodeGenerator
  },
  props: {
    product: {
      type: Object,
      required: true
    },
    template: {
      type: Object,
      default: () => ({
        width_mm: 50,
        height_mm: 30,
        template_data: {
          elements: [
            { type: 'qr_code', field: 'qr_data', x: 2, y: 2, size: 3 },
            { type: 'text', field: 'sku', x: 20, y: 2, size: 12, font: 'bold' },
            { type: 'text', field: 'product_name', x: 20, y: 12, size: 8 },
            { type: 'text', field: 'location', x: 20, y: 20, size: 6 }
          ]
        }
      })
    },
    scale: {
      type: Number,
      default: 4 // 4 pixels per mm for good preview quality
    },
    showControls: {
      type: Boolean,
      default: true
    },
    maxWidth: {
      type: String,
      default: '100%'
    }
  },
  data() {
    return {
      showBorder: true,
      showGrid: false,
      zoom: 1,
      imageErrors: new Set()
    }
  },
  computed: {
    templateElements() {
      return this.template?.template_data?.elements || [];
    },
    previewStyle() {
      return {
        maxWidth: this.maxWidth
      };
    },
    labelContainerStyle() {
      const widthPx = this.template.width_mm * this.scale * this.zoom;
      const heightPx = this.template.height_mm * this.scale * this.zoom;
      
      return {
        width: `${widthPx}px`,
        height: `${heightPx}px`,
        position: 'relative',
        backgroundColor: '#ffffff',
        border: '1px solid #e5e7eb',
        margin: '0 auto'
      };
    },
    gridStyle() {
      const gridSize = 5 * this.scale * this.zoom; // 5mm grid
      return {
        backgroundImage: `
          linear-gradient(to right, rgba(0,0,0,0.1) 1px, transparent 1px),
          linear-gradient(to bottom, rgba(0,0,0,0.1) 1px, transparent 1px)
        `,
        backgroundSize: `${gridSize}px ${gridSize}px`
      };
    }
  },
  methods: {
    getElementStyle(element) {
      const x = element.x * this.scale * this.zoom;
      const y = element.y * this.scale * this.zoom;
      
      return {
        left: `${x}px`,
        top: `${y}px`,
        transform: `rotate(${element.rotation || 0}deg)`
      };
    },
    
    getTextClasses(element) {
      const classes = [];
      
      if (element.font === 'bold' || element.weight === 'bold') {
        classes.push('font-bold');
      }
      if (element.font === 'italic' || element.style === 'italic') {
        classes.push('italic');
      }
      if (element.align === 'center') {
        classes.push('text-center');
      } else if (element.align === 'right') {
        classes.push('text-right');
      }
      
      return classes.join(' ');
    },
    
    getTextStyle(element) {
      const fontSize = (element.size || 12) * this.scale * this.zoom * 0.75; // Convert to px
      
      return {
        fontSize: `${fontSize}px`,
        color: element.color || '#000000',
        fontFamily: element.font_family || 'Arial, sans-serif',
        lineHeight: '1.2',
        whiteSpace: 'nowrap',
        maxWidth: element.max_width ? `${element.max_width * this.scale * this.zoom}px` : 'none',
        overflow: element.max_width ? 'hidden' : 'visible',
        textOverflow: element.max_width ? 'ellipsis' : 'clip'
      };
    },
    
    getBarcodeStyle(element) {
      const width = (element.width || 40) * this.scale * this.zoom;
      const height = (element.height || 15) * this.scale * this.zoom;
      
      return {
        width: `${width}px`,
        height: `${height}px`,
        display: 'flex',
        alignItems: 'flex-end'
      };
    },
    
    getBarStyle(bar, element) {
      const width = this.scale * this.zoom * 0.5; // Base bar width
      const height = bar.height * (element.height || 15) * this.scale * this.zoom;
      
      return {
        width: `${width}px`,
        height: `${height}px`,
        backgroundColor: bar.color || element.color || '#000000',
        marginRight: `${this.scale * this.zoom * 0.2}px`
      };
    },
    
    getImageStyle(element) {
      const width = (element.width || 20) * this.scale * this.zoom;
      const height = (element.height || 20) * this.scale * this.zoom;
      
      return {
        width: `${width}px`,
        height: `${height}px`
      };
    },
    
    getLineStyle(element) {
      const width = (element.width || 1) * this.scale * this.zoom;
      const length = (element.length || 20) * this.scale * this.zoom;
      
      if (element.orientation === 'vertical') {
        return {
          width: `${width}px`,
          height: `${length}px`,
          backgroundColor: element.color || '#000000'
        };
      } else {
        return {
          width: `${length}px`,
          height: `${width}px`,
          backgroundColor: element.color || '#000000'
        };
      }
    },
    
    getRectangleStyle(element) {
      const width = (element.width || 20) * this.scale * this.zoom;
      const height = (element.height || 20) * this.scale * this.zoom;
      const borderWidth = (element.border_width || 1) * this.scale * this.zoom;
      
      return {
        width: `${width}px`,
        height: `${height}px`,
        border: `${borderWidth}px solid ${element.border_color || '#000000'}`,
        backgroundColor: element.fill_color || 'transparent'
      };
    },
    
    getFieldValue(field) {
      const data = this.getLabelData();
      return data[field] || '';
    },
    
    getLabelData() {
      return {
        sku: this.product.internal_sku || this.product.sku || '',
        product_name: this.product.name || '',
        qr_data: JSON.stringify({
          sku: this.product.internal_sku || this.product.sku,
          id: this.product.id,
          org: this.product.organization_id
        }),
        qr_code: this.product.internal_sku || this.product.sku || '',
        location: this.product.warehouse_location?.location_code || '',
        organization: this.product.organization?.name || '',
        date_created: this.product.created_at ? new Date(this.product.created_at).toLocaleDateString() : '',
        barcode_data: this.product.internal_sku || this.product.sku || '',
        brand: this.product.brand?.name || '',
        model: this.product.model_number || '',
        condition: this.product.condition || '',
        price: this.product.selling_price ? `$${this.product.selling_price}` : ''
      };
    },
    
    generateBarcode(data) {
      // Simple barcode generation (mock implementation)
      // In a real app, you'd use a proper barcode library
      const bars = [];
      for (let i = 0; i < data.length; i++) {
        const char = data.charCodeAt(i);
        bars.push({
          id: i * 2,
          height: 0.8 + (char % 20) / 100,
          color: '#000000'
        });
        bars.push({
          id: i * 2 + 1,
          height: 0.6 + (char % 15) / 100,
          color: '#000000'
        });
      }
      return bars;
    },
    
    handleImageError(event) {
      this.imageErrors.add(event.target.src);
      event.target.style.display = 'none';
    },
    
    zoomIn() {
      this.zoom = Math.min(this.zoom * 1.25, 5);
    },
    
    zoomOut() {
      this.zoom = Math.max(this.zoom / 1.25, 0.25);
    }
  }
}
</script>

<style scoped>
.label-preview {
  @apply bg-gray-50 dark:bg-gray-900 p-4 rounded-lg;
}

.label-element {
  @apply select-none;
}

.barcode-bar {
  @apply inline-block;
}

.preview-controls button {
  @apply p-1 hover:bg-gray-200 dark:hover:bg-gray-700 rounded;
}
</style>
