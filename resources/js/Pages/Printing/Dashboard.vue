<template>
  <AppLayout title="Label Printing Dashboard">
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
        Label Printing Dashboard
      </h2>
    </template>

    <div class="py-12">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
        
        <!-- Quick Stats -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
          <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg p-6">
            <div class="flex items-center">
              <div class="flex-1">
                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                  Labels Printed Today
                </h3>
                <p class="text-3xl font-bold text-blue-600 dark:text-blue-400">
                  {{ stats.labelsToday }}
                </p>
              </div>
              <div class="ml-4">
                <i class="fas fa-print text-4xl text-blue-600 dark:text-blue-400"></i>
              </div>
            </div>
          </div>

          <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg p-6">
            <div class="flex items-center">
              <div class="flex-1">
                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                  Active Printers
                </h3>
                <p class="text-3xl font-bold text-green-600 dark:text-green-400">
                  {{ stats.activePrinters }}
                </p>
              </div>
              <div class="ml-4">
                <i class="fas fa-printer text-4xl text-green-600 dark:text-green-400"></i>
              </div>
            </div>
          </div>

          <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg p-6">
            <div class="flex items-center">
              <div class="flex-1">
                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                  Pending Jobs
                </h3>
                <p class="text-3xl font-bold text-yellow-600 dark:text-yellow-400">
                  {{ stats.pendingJobs }}
                </p>
              </div>
              <div class="ml-4">
                <i class="fas fa-clock text-4xl text-yellow-600 dark:text-yellow-400"></i>
              </div>
            </div>
          </div>

          <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg p-6">
            <div class="flex items-center">
              <div class="flex-1">
                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                  Failed Jobs
                </h3>
                <p class="text-3xl font-bold text-red-600 dark:text-red-400">
                  {{ stats.failedJobs }}
                </p>
              </div>
              <div class="ml-4">
                <i class="fas fa-exclamation-triangle text-4xl text-red-600 dark:text-red-400"></i>
              </div>
            </div>
          </div>
        </div>

        <!-- Quick Actions -->
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg">
          <div class="p-6">
            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">
              Quick Actions
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
              <Link :href="route('printing.printers.discovery')" 
                    class="flex items-center p-4 border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-lg hover:border-blue-500 dark:hover:border-blue-400 transition-colors">
                <i class="fas fa-search text-2xl text-blue-600 dark:text-blue-400 mr-3"></i>
                <div>
                  <h4 class="font-medium text-gray-900 dark:text-gray-100">Discover Printers</h4>
                  <p class="text-sm text-gray-500 dark:text-gray-400">Find and add new printers</p>
                </div>
              </Link>

              <Link :href="route('printing.templates.index')" 
                    class="flex items-center p-4 border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-lg hover:border-green-500 dark:hover:border-green-400 transition-colors">
                <i class="fas fa-tags text-2xl text-green-600 dark:text-green-400 mr-3"></i>
                <div>
                  <h4 class="font-medium text-gray-900 dark:text-gray-100">Manage Templates</h4>
                  <p class="text-sm text-gray-500 dark:text-gray-400">Create and edit label templates</p>
                </div>
              </Link>

              <Link :href="route('printing.jobs.index')" 
                    class="flex items-center p-4 border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-lg hover:border-purple-500 dark:hover:border-purple-400 transition-colors">
                <i class="fas fa-history text-2xl text-purple-600 dark:text-purple-400 mr-3"></i>
                <div>
                  <h4 class="font-medium text-gray-900 dark:text-gray-100">Print History</h4>
                  <p class="text-sm text-gray-500 dark:text-gray-400">View print jobs and logs</p>
                </div>
              </Link>
            </div>
          </div>
        </div>

        <!-- Recent Print Jobs -->
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg">
          <div class="p-6">
            <div class="flex justify-between items-center mb-4">
              <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                Recent Print Jobs
              </h3>
              <Link :href="route('printing.jobs.index')" 
                    class="text-blue-600 dark:text-blue-400 hover:underline">
                View All
              </Link>
            </div>
            
            <div class="overflow-x-auto">
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
                      Status
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                      Printed At
                    </th>
                  </tr>
                </thead>
                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                  <tr v-for="job in recentJobs" :key="job.id">
                    <td class="px-6 py-4 whitespace-nowrap">
                      <div class="text-sm font-medium text-gray-900 dark:text-gray-100">
                        {{ job.product_name }}
                      </div>
                      <div class="text-sm text-gray-500 dark:text-gray-400">
                        {{ job.sku }}
                      </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                      {{ job.printer_name }}
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

        <!-- Printer Status -->
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg">
          <div class="p-6">
            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">
              Printer Status
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
              <div v-for="printer in printers" :key="printer.id" 
                   class="border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                <div class="flex justify-between items-start mb-2">
                  <h4 class="font-medium text-gray-900 dark:text-gray-100">
                    {{ printer.name }}
                  </h4>
                  <PrinterStatus :printer="printer" />
                </div>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                  {{ printer.connection_string }}
                </p>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                  Driver: {{ printer.driver }}
                </p>
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
import PrintJobStatus from '@/Components/Printing/PrintJobStatus.vue'
import PrinterStatus from '@/Components/Printing/PrinterStatus.vue'

export default {
  name: 'PrintingDashboard',
  components: {
    AppLayout,
    Link,
    PrintJobStatus,
    PrinterStatus
  },
  props: {
    stats: {
      type: Object,
      required: true
    },
    recentJobs: {
      type: Array,
      default: () => []
    },
    printers: {
      type: Array,
      default: () => []
    }
  },
  methods: {
    formatDate(date) {
      return new Date(date).toLocaleString()
    }
  }
}
</script>
