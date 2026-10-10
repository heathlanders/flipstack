<template>
  <div class="printer-status">
    <!-- Status Badge -->
    <div class="flex items-center space-x-2">
      <span :class="statusBadgeClasses">
        <i :class="statusIconClass" class="mr-1"></i>
        {{ statusText }}
      </span>
      
      <!-- Test Connection Button -->
      <button v-if="showTestButton"
              @click="testConnection" 
              :disabled="testing"
              class="text-sm text-blue-600 dark:text-blue-400 hover:underline disabled:opacity-50"
              title="Test printer connection">
        <i v-if="testing" class="fas fa-spinner fa-spin"></i>
        <i v-else class="fas fa-vial"></i>
      </button>
    </div>
    
    <!-- Additional Info -->
    <div v-if="showDetails" class="mt-2 text-xs text-gray-500 dark:text-gray-400">
      <div v-if="printer.last_used_at">
        Last used: {{ formatDate(printer.last_used_at) }}
      </div>
      <div v-if="printer.total_jobs_count">
        Total jobs: {{ printer.total_jobs_count }}
      </div>
      <div v-if="lastTestResult">
        {{ lastTestResult }}
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'PrinterStatus',
  props: {
    printer: {
      type: Object,
      required: true
    },
    showTestButton: {
      type: Boolean,
      default: true
    },
    showDetails: {
      type: Boolean,
      default: false
    }
  },
  data() {
    return {
      testing: false,
      lastTestResult: null,
      testTimeout: null
    }
  },
  computed: {
    statusBadgeClasses() {
      const baseClasses = 'inline-flex items-center px-2 py-1 rounded-full text-xs font-medium';
      
      if (!this.printer.is_active) {
        return `${baseClasses} bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-100`;
      }
      
      switch (this.printer.status) {
        case 'online':
          return `${baseClasses} bg-green-100 text-green-800 dark:bg-green-800 dark:text-green-100`;
        case 'offline':
          return `${baseClasses} bg-red-100 text-red-800 dark:bg-red-800 dark:text-red-100`;
        case 'busy':
          return `${baseClasses} bg-yellow-100 text-yellow-800 dark:bg-yellow-800 dark:text-yellow-100`;
        case 'error':
          return `${baseClasses} bg-red-100 text-red-800 dark:bg-red-800 dark:text-red-100`;
        default:
          return `${baseClasses} bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-100`;
      }
    },
    statusIconClass() {
      if (!this.printer.is_active) {
        return 'fas fa-power-off text-gray-400';
      }
      
      switch (this.printer.status) {
        case 'online':
          return 'fas fa-circle text-green-400';
        case 'offline':
          return 'fas fa-circle text-red-400';
        case 'busy':
          return 'fas fa-circle text-yellow-400';
        case 'error':
          return 'fas fa-exclamation-triangle text-red-400';
        default:
          return 'fas fa-question-circle text-gray-400';
      }
    },
    statusText() {
      if (!this.printer.is_active) {
        return 'Disabled';
      }
      
      switch (this.printer.status) {
        case 'online':
          return 'Online';
        case 'offline':
          return 'Offline';
        case 'busy':
          return 'Busy';
        case 'error':
          return 'Error';
        default:
          return 'Unknown';
      }
    }
  },
  methods: {
    async testConnection() {
      if (this.testing) return;
      
      this.testing = true;
      this.lastTestResult = null;
      
      try {
        const response = await axios.post(`/printers/${this.printer.id}/test`);
        
        if (response.data.success) {
          this.lastTestResult = 'Connection successful';
          this.$toast.success(`${this.printer.name} connection successful`);
          
          // Update printer status if needed
          this.$emit('status-updated', {
            printer: this.printer,
            status: 'online'
          });
        } else {
          this.lastTestResult = 'Connection failed';
          this.$toast.error(`${this.printer.name} connection failed`);
          
          this.$emit('status-updated', {
            printer: this.printer,
            status: 'offline'
          });
        }
      } catch (error) {
        this.lastTestResult = `Error: ${error.response?.data?.message || error.message}`;
        this.$toast.error(`Test failed: ${error.response?.data?.message || error.message}`);
        
        this.$emit('status-updated', {
          printer: this.printer,
          status: 'error'
        });
      } finally {
        this.testing = false;
        
        // Clear test result after 5 seconds
        if (this.testTimeout) {
          clearTimeout(this.testTimeout);
        }
        this.testTimeout = setTimeout(() => {
          this.lastTestResult = null;
        }, 5000);
      }
    },
    
    formatDate(date) {
      if (!date) return 'Never';
      
      const now = new Date();
      const testDate = new Date(date);
      const diffInMs = now - testDate;
      const diffInMins = Math.floor(diffInMs / (1000 * 60));
      const diffInHours = Math.floor(diffInMs / (1000 * 60 * 60));
      const diffInDays = Math.floor(diffInMs / (1000 * 60 * 60 * 24));
      
      if (diffInMins < 1) {
        return 'Just now';
      } else if (diffInMins < 60) {
        return `${diffInMins}m ago`;
      } else if (diffInHours < 24) {
        return `${diffInHours}h ago`;
      } else if (diffInDays < 30) {
        return `${diffInDays}d ago`;
      } else {
        return testDate.toLocaleDateString();
      }
    }
  },
  beforeDestroy() {
    if (this.testTimeout) {
      clearTimeout(this.testTimeout);
    }
  }
}
</script>
