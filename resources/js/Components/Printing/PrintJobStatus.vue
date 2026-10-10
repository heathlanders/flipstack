<template>
  <span :class="statusClasses">
    <i :class="iconClass" class="mr-1"></i>
    {{ statusText }}
  </span>
</template>

<script>
export default {
  name: 'PrintJobStatus',
  props: {
    status: {
      type: String,
      required: true,
      validator: value => ['pending', 'processing', 'completed', 'failed', 'cancelled'].includes(value)
    }
  },
  computed: {
    statusClasses() {
      const baseClasses = 'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium';
      
      switch (this.status) {
        case 'pending':
          return `${baseClasses} bg-yellow-100 text-yellow-800 dark:bg-yellow-800 dark:text-yellow-100`;
        case 'processing':
          return `${baseClasses} bg-blue-100 text-blue-800 dark:bg-blue-800 dark:text-blue-100`;
        case 'completed':
          return `${baseClasses} bg-green-100 text-green-800 dark:bg-green-800 dark:text-green-100`;
        case 'failed':
          return `${baseClasses} bg-red-100 text-red-800 dark:bg-red-800 dark:text-red-100`;
        case 'cancelled':
          return `${baseClasses} bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-100`;
        default:
          return `${baseClasses} bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-100`;
      }
    },
    iconClass() {
      switch (this.status) {
        case 'pending':
          return 'fas fa-clock text-yellow-500';
        case 'processing':
          return 'fas fa-spinner fa-spin text-blue-500';
        case 'completed':
          return 'fas fa-check-circle text-green-500';
        case 'failed':
          return 'fas fa-exclamation-triangle text-red-500';
        case 'cancelled':
          return 'fas fa-times-circle text-gray-500';
        default:
          return 'fas fa-question-circle text-gray-500';
      }
    },
    statusText() {
      switch (this.status) {
        case 'pending':
          return 'Pending';
        case 'processing':
          return 'Printing';
        case 'completed':
          return 'Completed';
        case 'failed':
          return 'Failed';
        case 'cancelled':
          return 'Cancelled';
        default:
          return 'Unknown';
      }
    }
  }
}
</script>
