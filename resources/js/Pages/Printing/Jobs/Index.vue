<template>
  <AppLayout title="Print Jobs">
    <template #header>
      <div class="flex justify-between items-center">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
          Print Jobs
        </h2>
        <div class="flex space-x-3">
          <button @click="refreshJobs" 
                  :disabled="refreshing"
                  class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50">
            <i v-if="refreshing" class="fas fa-spinner fa-spin mr-2"></i>
            <i v-else class="fas fa-sync-alt mr-2"></i>
            Refresh
          </button>
          <button @click="clearCompleted" 
                  :disabled="clearing"
                  class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50">
            <i v-if="clearing" class="fas fa-spinner fa-spin mr-2"></i>
            <i v-else class="fas fa-trash mr-2"></i>
            Clear Completed
          </button>
        </div>
      </div>
    </template>

    <div class="py-12">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
        
        <!-- Quick Stats -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
          <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg p-6">
            <div class="flex items-center">
              <div class="flex-1">
                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                  Total Jobs
                </h3>
                <p class="text-3xl font-bold text-blue-600 dark:text-blue-400">
                  {{ statistics.total_jobs || 0 }}
                </p>
              </div>
              <div class="ml-4">
                <i class="fas fa-tasks text-4xl text-blue-600 dark:text-blue-400"></i>
              </div>
            </div>
          </div>

          <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg p-6">
            <div class="flex items-center">
              <div class="flex-1">
                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                  Pending
                </h3>
                <p class="text-3xl font-bold text-yellow-600 dark:text-yellow-400">
                  {{ statistics.pending_jobs || 0 }}
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
                  Completed Today
                </h3>
                <p class="text-3xl font-bold text-green-600 dark:text-green-400">
                  {{ statistics.completed_today || 0 }}
                </p>
              </div>
              <div class="ml-4">
                <i class="fas fa-check-circle text-4xl text-green-600 dark:text-green-400"></i>
              </div>
            </div>
          </div>

          <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg p-6">
            <div class="flex items-center">
              <div class="flex-1">
                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                  Failed
                </h3>
                <p class="text-3xl font-bold text-red-600 dark:text-red-400">
                  {{ statistics.failed_jobs || 0 }}
                </p>
              </div>
              <div class="ml-4">
                <i class="fas fa-exclamation-triangle text-4xl text-red-600 dark:text-red-400"></i>
              </div>
            </div>
          </div>
        </div>
        
        <!-- Filters and Search -->
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg">
          <div class="p-6 border-b border-gray-200 dark:border-gray-700">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
              <!-- Search -->
              <div class="md:col-span-2">
                <label for="search" class="sr-only">Search jobs</label>
                <div class="relative">
                  <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <i class="fas fa-search text-gray-400"></i>
                  </div>
                  <input id="search" 
                         v-model="filters.search"
                         @input="debouncedFilter"
                         type="text" 
                         placeholder="Search by product name or SKU..."
                         class="block w-full pl-10 pr-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md leading-5 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 placeholder-gray-500 dark:placeholder-gray-400 focus:outline-none focus:placeholder-gray-400 dark:focus:placeholder-gray-500 focus:ring-1 focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                </div>
              </div>
              
              <!-- Status Filter -->
              <div>
                <label for="status-filter" class="sr-only">Filter by status</label>
                <select id="status-filter" 
                        v-model="filters.status"
                        @change="applyFilters"
                        class="block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                  <option value="">All Status</option>
                  <option value="pending">Pending</option>
                  <option value="processing">Processing</option>
                  <option value="completed">Completed</option>
                  <option value="failed">Failed</option>
                  <option value="cancelled">Cancelled</option>
                </select>
              </div>
              
              <!-- Date Range Filter -->
              <div>
                <label for="date-filter" class="sr-only">Filter by date</label>
                <select id="date-filter" 
                        v-model="filters.date_range"
                        @change="applyFilters"
                        class="block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                  <option value="">All Time</option>
                  <option value="today">Today</option>
                  <option value="yesterday">Yesterday</option>
                  <option value="week">This Week</option>
                  <option value="month">This Month</option>
                </select>
              </div>
            </div>
          </div>
        </div>

        <!-- Print Jobs List -->
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg">
          <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
            <div class="flex justify-between items-center">
              <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                Print Jobs ({{ filteredJobs.length }})
              </h3>
              <div class="flex space-x-2">
                <button @click="retrySelectedFailedJobs" 
                        v-if="selectedFailedJobs.length > 0"
                        :disabled="retryingBatch"
                        class="inline-flex items-center px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm font-medium text-green-700 dark:text-green-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 disabled:opacity-50">
                  <i v-if="retryingBatch" class="fas fa-spinner fa-spin mr-2"></i>
                  <i v-else class="fas fa-redo mr-2"></i>
                  Retry Selected ({{ selectedFailedJobs.length }})
                </button>
              </div>
            </div>
          </div>

          <div v-if="loading" class="p-6 text-center">
            <i class="fas fa-spinner fa-spin text-3xl text-gray-400 mb-2"></i>
            <p class="text-gray-500 dark:text-gray-400">Loading print jobs...</p>
          </div>

          <div v-else-if="filteredJobs.length === 0" class="p-6 text-center">
            <div class="text-gray-500 dark:text-gray-400">
              <i class="fas fa-inbox text-4xl mb-4"></i>
              <p class="text-lg font-medium">No print jobs found</p>
              <p class="text-sm">
                {{ jobs.length === 0 ? 'No jobs have been created yet.' : 'Try adjusting your filters.' }}
              </p>
            </div>
          </div>

          <div v-else class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
              <thead class="bg-gray-50 dark:bg-gray-700">
                <tr>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                    <input type="checkbox" 
                           v-model="selectAll" 
                           @change="toggleSelectAll"
                           class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 dark:border-gray-600 rounded">
                  </th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                    Product
                  </th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                    Printer
                  </th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                    Template
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
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                    Actions
                  </th>
                </tr>
              </thead>
              <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                <tr v-for="job in paginatedJobs" 
                    :key="job.id" 
                    class="hover:bg-gray-50 dark:hover:bg-gray-700"
                    :class="{ 'bg-red-50 dark:bg-red-900': job.status === 'failed' }">
                  <td class="px-6 py-4 whitespace-nowrap">
                    <input type="checkbox" 
                           v-model="selectedJobs" 
                           :value="job.id"
                           :disabled="job.status !== 'failed'"
                           class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 dark:border-gray-600 rounded disabled:opacity-50">
                  </td>
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
                      Product deleted (ID: {{ job.product_id }})
                    </div>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                    {{ job.printer?.name || 'Unknown' }}
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                    {{ job.template?.name || 'Default' }}
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                    {{ job.quantity }}
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap">
                    <PrintJobStatus :status="job.status" />
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                    <div>{{ formatDate(job.created_at) }}</div>
                    <div v-if="job.printed_at" class="text-xs">
                      Printed: {{ formatDate(job.printed_at) }}
                    </div>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                    <div class="flex space-x-2">
                      <Link :href="route('printing.jobs.show', job.id)" 
                            class="text-blue-600 dark:text-blue-400 hover:underline">
                        View
                      </Link>
                      <button v-if="job.status === 'failed'" 
                              @click="retryJob(job)"
                              :disabled="retryingJobs[job.id]"
                              class="text-green-600 dark:text-green-400 hover:underline disabled:opacity-50">
                        <i v-if="retryingJobs[job.id]" class="fas fa-spinner fa-spin mr-1"></i>
                        Retry
                      </button>
                      <button v-if="['pending', 'processing'].includes(job.status)" 
                              @click="cancelJob(job)"
                              :disabled="cancellingJobs[job.id]"
                              class="text-red-600 dark:text-red-400 hover:underline disabled:opacity-50">
                        <i v-if="cancellingJobs[job.id]" class="fas fa-spinner fa-spin mr-1"></i>
                        Cancel
                      </button>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- Pagination -->
          <div v-if="filteredJobs.length > itemsPerPage" 
               class="px-6 py-4 border-t border-gray-200 dark:border-gray-700">
            <div class="flex items-center justify-between">
              <div class="text-sm text-gray-700 dark:text-gray-300">
                Showing {{ (currentPage - 1) * itemsPerPage + 1 }} to 
                {{ Math.min(currentPage * itemsPerPage, filteredJobs.length) }} of 
                {{ filteredJobs.length }} results
              </div>
              <div class="flex space-x-2">
                <button @click="currentPage--" 
                        :disabled="currentPage === 1"
                        class="px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50">
                  Previous
                </button>
                <button @click="currentPage++" 
                        :disabled="currentPage >= totalPages"
                        class="px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50">
                  Next
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
import PrintJobStatus from '@/Components/Printing/PrintJobStatus.vue'
import { debounce } from 'lodash'

export default {
  name: 'JobsIndex',
  components: {
    AppLayout,
    Link,
    PrintJobStatus
  },
  props: {
    jobs: {
      type: Array,
      default: () => []
    },
    statistics: {
      type: Object,
      default: () => ({})
    }
  },
  data() {
    return {
      loading: false,
      refreshing: false,
      clearing: false,
      retryingBatch: false,
      selectAll: false,
      selectedJobs: [],
      retryingJobs: {},
      cancellingJobs: {},
      currentPage: 1,
      itemsPerPage: 25,
      filters: {
        search: '',
        status: '',
        date_range: ''
      }
    }
  },
  computed: {
    filteredJobs() {
      let filtered = [...this.jobs];
      
      if (this.filters.search) {
        const search = this.filters.search.toLowerCase();
        filtered = filtered.filter(job => 
          job.product?.name?.toLowerCase().includes(search) ||
          job.product?.sku?.toLowerCase().includes(search) ||
          job.product?.internal_sku?.toLowerCase().includes(search)
        );
      }
      
      if (this.filters.status) {
        filtered = filtered.filter(job => job.status === this.filters.status);
      }
      
      if (this.filters.date_range) {
        const now = new Date();
        const today = new Date(now.getFullYear(), now.getMonth(), now.getDate());
        
        filtered = filtered.filter(job => {
          const jobDate = new Date(job.created_at);
          
          switch (this.filters.date_range) {
            case 'today':
              return jobDate >= today;
            case 'yesterday':
              const yesterday = new Date(today);
              yesterday.setDate(yesterday.getDate() - 1);
              return jobDate >= yesterday && jobDate < today;
            case 'week':
              const weekStart = new Date(today);
              weekStart.setDate(weekStart.getDate() - weekStart.getDay());
              return jobDate >= weekStart;
            case 'month':
              const monthStart = new Date(today.getFullYear(), today.getMonth(), 1);
              return jobDate >= monthStart;
            default:
              return true;
          }
        });
      }
      
      return filtered.sort((a, b) => new Date(b.created_at) - new Date(a.created_at));
    },
    paginatedJobs() {
      const start = (this.currentPage - 1) * this.itemsPerPage;
      const end = start + this.itemsPerPage;
      return this.filteredJobs.slice(start, end);
    },
    totalPages() {
      return Math.ceil(this.filteredJobs.length / this.itemsPerPage);
    },
    selectedFailedJobs() {
      return this.selectedJobs.filter(jobId => {
        const job = this.jobs.find(j => j.id === jobId);
        return job && job.status === 'failed';
      });
    }
  },
  created() {
    this.debouncedFilter = debounce(this.applyFilters, 300);
  },
  methods: {
    formatDate(date) {
      return new Date(date).toLocaleString();
    },
    
    toggleSelectAll() {
      if (this.selectAll) {
        this.selectedJobs = this.filteredJobs
          .filter(job => job.status === 'failed')
          .map(job => job.id);
      } else {
        this.selectedJobs = [];
      }
    },
    
    applyFilters() {
      this.currentPage = 1;
      // Filters are applied through computed property
    },
    
    async refreshJobs() {
      this.refreshing = true;
      try {
        await this.$inertia.reload({ only: ['jobs', 'statistics'] });
        this.$toast.success('Jobs refreshed');
      } catch (error) {
        this.$toast.error('Failed to refresh jobs');
      } finally {
        this.refreshing = false;
      }
    },
    
    async clearCompleted() {
      this.clearing = true;
      try {
        await axios.delete('/printing/jobs/clear-completed');
        this.$toast.success('Completed jobs cleared');
        await this.refreshJobs();
      } catch (error) {
        this.$toast.error(`Failed to clear jobs: ${error.response?.data?.message || error.message}`);
      } finally {
        this.clearing = false;
      }
    },
    
    async retryJob(job) {
      this.$set(this.retryingJobs, job.id, true);
      
      try {
        const response = await axios.post(`/printing/jobs/${job.id}/retry`);
        
        if (response.data.success) {
          this.$toast.success('Print job retry queued');
          job.status = 'pending';
          job.error_message = null;
        } else {
          this.$toast.error('Failed to retry print job');
        }
      } catch (error) {
        this.$toast.error(`Retry failed: ${error.response?.data?.message || error.message}`);
      } finally {
        this.$set(this.retryingJobs, job.id, false);
      }
    },
    
    async retrySelectedFailedJobs() {
      this.retryingBatch = true;
      
      try {
        const response = await axios.post('/printing/jobs/retry-batch', {
          job_ids: this.selectedFailedJobs
        });
        
        this.$toast.success(`${response.data.successful_retries} jobs queued for retry`);
        this.selectedJobs = [];
        this.selectAll = false;
        await this.refreshJobs();
      } catch (error) {
        this.$toast.error(`Batch retry failed: ${error.response?.data?.message || error.message}`);
      } finally {
        this.retryingBatch = false;
      }
    },
    
    async cancelJob(job) {
      this.$set(this.cancellingJobs, job.id, true);
      
      try {
        const response = await axios.post(`/printing/jobs/${job.id}/cancel`);
        
        if (response.data.success) {
          this.$toast.success('Print job cancelled');
          job.status = 'cancelled';
        } else {
          this.$toast.error('Failed to cancel print job');
        }
      } catch (error) {
        this.$toast.error(`Cancel failed: ${error.response?.data?.message || error.message}`);
      } finally {
        this.$set(this.cancellingJobs, job.id, false);
      }
    }
  },
  watch: {
    selectedJobs(newVal) {
      this.selectAll = newVal.length > 0 && 
        newVal.length === this.filteredJobs.filter(job => job.status === 'failed').length;
    }
  }
}
</script>
