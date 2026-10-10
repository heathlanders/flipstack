<template>
  <div class="qr-code-generator" :style="containerStyle">
    <svg v-if="qrMatrix" 
         :width="size" 
         :height="size" 
         :style="svgStyle"
         class="qr-code-svg">
      <!-- Background -->
      <rect :width="size" 
            :height="size" 
            :fill="background" />
      
      <!-- QR Code modules -->
      <template v-for="(row, y) in qrMatrix" :key="`row-${y}`">
        <rect v-for="(module, x) in row"  
              v-if="module"
              :key="`${x}-${y}`"
              :x="x * moduleSize" 
              :y="y * moduleSize" 
              :width="moduleSize" 
              :height="moduleSize" 
              :fill="color" />
      </template>
    </svg>
    
    <!-- Error message if QR generation fails -->
    <div v-else-if="error" 
         class="qr-error text-center p-2 bg-red-100 dark:bg-red-900 text-red-700 dark:text-red-300 rounded text-xs">
      <i class="fas fa-exclamation-triangle"></i>
      QR Error: {{ error }}
    </div>
    
    <!-- Loading placeholder -->
    <div v-else 
         class="qr-loading flex items-center justify-center bg-gray-100 dark:bg-gray-800 rounded"
         :style="{ width: size + 'px', height: size + 'px' }">
      <i class="fas fa-qrcode text-gray-400 text-2xl"></i>
    </div>
  </div>
</template>

<script>
export default {
  name: 'QRCodeGenerator',
  props: {
    data: {
      type: String,
      required: true
    },
    size: {
      type: Number,
      default: 100
    },
    color: {
      type: String,
      default: '#000000'
    },
    background: {
      type: String,
      default: '#ffffff'
    },
    errorCorrection: {
      type: String,
      default: 'M',
      validator: value => ['L', 'M', 'Q', 'H'].includes(value)
    },
    margin: {
      type: Number,
      default: 4
    }
  },
  data() {
    return {
      qrMatrix: null,
      error: null
    }
  },
  computed: {
    containerStyle() {
      return {
        display: 'inline-block',
        lineHeight: 0
      };
    },
    svgStyle() {
      return {
        display: 'block',
        shapeRendering: 'crispEdges'
      };
    },
    moduleSize() {
      if (!this.qrMatrix) return 1;
      const modules = this.qrMatrix.length + (this.margin * 2);
      return this.size / modules;
    }
  },
  watch: {
    data: {
      handler() {
        this.generateQR();
      },
      immediate: true
    },
    errorCorrection() {
      this.generateQR();
    }
  },
  methods: {
    generateQR() {
      if (!this.data) {
        this.qrMatrix = null;
        this.error = null;
        return;
      }
      
      try {
        // Simple QR code generation using basic algorithm
        // In production, you'd use a proper QR code library like qrcode.js or similar
        this.qrMatrix = this.createBasicQRMatrix(this.data);
        this.error = null;
      } catch (err) {
        this.error = err.message || 'Failed to generate QR code';
        this.qrMatrix = null;
      }
    },
    
    createBasicQRMatrix(data) {
      // This is a simplified QR code generator for demonstration
      // In a real implementation, you'd use a proper QR library
      
      // Determine version based on data length
      const version = this.getQRVersion(data.length);
      const size = this.getQRSize(version);
      
      // Initialize matrix
      const matrix = Array(size).fill().map(() => Array(size).fill(false));
      
      // Add finder patterns (corners)
      this.addFinderPatterns(matrix);
      
      // Add timing patterns
      this.addTimingPatterns(matrix);
      
      // Add data (simplified - just create a pattern based on data)
      this.addDataPattern(matrix, data);
      
      // Add margin
      return this.addMargin(matrix);
    },
    
    getQRVersion(dataLength) {
      // Simplified version selection
      if (dataLength <= 25) return 1;
      if (dataLength <= 47) return 2;
      if (dataLength <= 77) return 3;
      if (dataLength <= 114) return 4;
      return 5; // Max for this simple implementation
    },
    
    getQRSize(version) {
      return 21 + (version - 1) * 4;
    },
    
    addFinderPatterns(matrix) {
      const size = matrix.length;
      const patterns = [
        [0, 0], // Top-left
        [0, size - 7], // Top-right
        [size - 7, 0] // Bottom-left
      ];
      
      patterns.forEach(([startX, startY]) => {
        // 7x7 finder pattern
        for (let y = 0; y < 7; y++) {
          for (let x = 0; x < 7; x++) {
            const isEdge = x === 0 || x === 6 || y === 0 || y === 6;
            const isCenter = x >= 2 && x <= 4 && y >= 2 && y <= 4;
            matrix[startY + y][startX + x] = isEdge || isCenter;
          }
        }
      });
    },
    
    addTimingPatterns(matrix) {
      const size = matrix.length;
      
      // Horizontal timing pattern
      for (let x = 8; x < size - 8; x++) {
        matrix[6][x] = x % 2 === 0;
      }
      
      // Vertical timing pattern
      for (let y = 8; y < size - 8; y++) {
        matrix[y][6] = y % 2 === 0;
      }
    },
    
    addDataPattern(matrix, data) {
      const size = matrix.length;
      
      // Simple data encoding - create pattern based on data
      let dataIndex = 0;
      
      for (let y = 9; y < size - 9; y++) {
        for (let x = 9; x < size - 9; x++) {
          if (matrix[y][x] === undefined) { // Only fill empty areas
            const char = data.charCodeAt(dataIndex % data.length);
            matrix[y][x] = (char + x + y) % 2 === 0;
            dataIndex++;
          }
        }
      }
    },
    
    addMargin(matrix) {
      const margin = this.margin;
      const size = matrix.length;
      const newSize = size + (margin * 2);
      
      const marginedMatrix = Array(newSize).fill().map(() => Array(newSize).fill(false));
      
      // Copy original matrix to center
      for (let y = 0; y < size; y++) {
        for (let x = 0; x < size; x++) {
          marginedMatrix[y + margin][x + margin] = matrix[y][x];
        }
      }
      
      return marginedMatrix;
    }
  }
}
</script>

<style scoped>
.qr-code-svg {
  max-width: 100%;
  height: auto;
}
</style>