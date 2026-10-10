<template>
  <AppLayout :title="`Location: ${location.location_code}`">
    <template #header>
      <div class="flex justify-between items-center">
        <div class="flex items-center space-x-4">
          <Link :href="route('printing.locations.index')" 
                class="text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300">
            <i class="fas fa-arrow-left"></i>
          </Link>
          <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ location.location_code }}
          </h2>
          <span :class="location.is_active ? 
                'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-800 dark:text-green-100' :
                'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 dark:bg-red-800 dark:text-red-100'">
            <i :class="location.is_active ? 'fas fa-circle' : 'fas fa-stop-circle'" class="mr-1"></i>
            {{ location.is_active ? 'Active' : 'Inactive' }}
          </span>
        </div>
        <div class="flex space-x-3">
          <button @click="printLocationLabel" 
                  :disabled="printing"
                  class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50">
            <i v-if="printing" class="fas fa-spinner fa-spin mr-2"></i>
            <i v-else class="fas fa-print mr-2"></i>
            Print Label
          </button>
          <Link :href="route('printing.locations.edit', location.id)" 
                class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 active:bg-blue-900 focus:outline-none focus:border-blue-900 focus:ring ring-blue-300 transition ease-in-out duration-150">
            <i class="fas fa-edit mr-2"></i>
            Edit
          </Link>
        </div>
      </div>
    </template>

    <div class="py-12">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
        
        <!-- Location Details -->
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg">
          <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">
              Location Information
            </h3>
          </div>
          <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
              <!-- Basic Info -->
              <div class="space-y-4">
                <div>
                  <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Location Code</label>
                  <p class="mt-1 text-lg font-mono text-gray-900 dark:text-gray-100">{{ location.location_code }}</p>
                </div>
                
                <div v-if="location.description">
                  <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Description</label>
                  <p class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ location.description }}</p>
                </div>
                
                <div>
                  <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Status</label>
                  <div class="mt-1">
                    <span :class="location.is_active ? 'text-green-600' : 'text-red-600'">
                      <i :class="location.is_active ? 'fas fa-circle' : 'fas fa-stop-circle'"></i>
                      {{ location.is_active ? 'Active' : 'Inactive' }}
                    </span>
                  </div>
                </div>
                
                <div v-if="location.created_at">
                  <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Created</label>
                  <p class="mt-1 text-sm text-gray-900 dark:text-gray-100">
                    {{ formatDate(location.created_at) }}
                  </p>
                </div>
                
                <div v-if="location.updated_at">
                  <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Last Updated</label>
                  <p class="mt-1 text-sm text-gray-900 dark:text-gray-100">
                    {{ formatDate(location.updated_at) }}
                  </p>
                </div>
              </div>
              
              <!-- Position Info -->
              <div class="space-y-4">
                <div v-if="location.aisle">
                  <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Aisle</label>
                  <p class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ location.aisle }}</p>
                </div>
                
                <div v-if="location.shelf">
                  <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Shelf</label>
                  <p class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ location.shelf }}</p>
                </div>
                
                <div v-if="location.bin">
                  <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Bin</label>
                  <p class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ location.bin }}</p>
                </div>
                
                <div v-if="!location.aisle && !location.shelf && !location.bin" 
                     class="text-gray-500 dark:text-gray-400">
                  <p class="text-sm">No position details specified</p>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Usage Statistics -->
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg">
          <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">
              Usage Statistics
            </h3>
          </div>
          <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
              <div class="text-center">
                <div class="text-3xl font-bold text-blue-600 dark:text-blue-400">
                  {{ products.length }}
                </div>
                <div class="text-sm text-gray-500 dark:text-gray-400">Total Products</div>
              </div>
              <div class="text-center">
                <div class="text-3xl font-bold text-green-600 dark:text-green-400">
                  {{ statistics.labels_printed || 0 }}
                </div>
                <div class="text-sm text-gray-500 dark:text-gray-400">Labels Printed</div>
              </div>
              <div class="text-center">
                <div class="text-3xl font-bold text-orange-600 dark:text-orange-400">
                  {{ statistics.recent_additions || 0 }}
                </div>
                <div class="text-sm text-gray-500 dark:text-gray-400">Added This Month</div>
              </div>
              <div class="text-center">
                <div class="text-3xl font-bold text-purple-600 dark:text-purple-400">
                  {{ statistics.avg_days_stored || 0 }}
                </div>
                <div class="text-sm text-gray-500 dark:text-gray-400">Avg. Days Stored</div>
              </div>
            </div>
          </div>
        </div>

        <!-- Products at Location -->
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg">
          <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
            <div class="flex justify-between items-center">
              <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                Products at this Location ({{ products.length }})
              </h3>
              <div class="flex space-x-2">
                <input v-model="productSearch" 
                       type="text" 
                       placeholder="Search products..."
                       class="px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 text-sm">
                <button v-if="filteredProducts.length > 0"
                        @click="printAllProductLabels" 
                        :disabled="printingAll"
                        class="inline-flex items-center px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm font-medium text-purple-700 dark:text-purple-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-purple-500 disabled:opacity-50">
                  <i v-if="printingAll" class="fas fa-spinner fa-spin mr-2"></i>
                  <i v-else class="fas fa-print mr-2"></i>
                  Print All Labels
                </button>
              </div>
            </div>
          </div>

          <div v-if="filteredProducts.length === 0" class="p-6 text-center">
            <div class="text-gray-500 dark:text-gray-400">
              <i class="fas fa-box-open text-4xl mb-4"></i>
              <p class="text-lg font-medium">
                {{ products.length === 0 ? 'No products at this location' : 'No products match your search' }}
              </p>
              <p class="text-sm">
                {{ products.length === 0 ? 'Products will appear here when they are assigned to this location.' : 'Try adjusting your search terms.' }}
              </p>
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
                    SKU
                  </th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                    Condition
                  </th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                    Added to Location
                  </th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                    Actions
                  </th>
                </tr>
              </thead>
              <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                <tr v-for="product in paginatedProducts" 
                    :key="product.id" 
                    class="hover:bg-gray-50 dark:hover:bg-gray-700">
                  <td class="px-6 py-4 whitespace-nowrap">
                    <div class="text-sm">
                      <div class="font-medium text-gray-900 dark:text-gray-100">
                        {{ product.name }}
                      </div>
                      <div v-if="product.brand" class="text-gray-500 dark:text-gray-400">
                        {{ product.brand.name }}
                      </div>
                    </div>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap">
                    <div class="text-sm font-mono">
                      <div class="text-gray-900 dark:text-gray-100">
                        {{ product.internal_sku || product.sku }}
                      </div>
                      <div v-if="product.internal_sku && product.sku !== product.internal_sku" 
                           class="text-gray-500 dark:text-gray-400">
                        {{ product.sku }}
                      </div>
                    </div>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                    {{ product.condition || '-' }}
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                    {{ product.location_assigned_at ? formatDate(product.location_assigned_at) : formatDate(product.created_at) }}
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                    <div class="flex space-x-3">
                      <Link :href="route('products.show', product.id)" 
                            class="text-blue-600 dark:text-blue-400 hover:underline">
                        View
                      </Link>
                      <button @click="printProductLabel(product)" 
                              :disabled="printingProducts[product.id]"
                              class="text-purple-600 dark:text-purple-400 hover:underline disabled:opacity-50">
                        <i v-if="printingProducts[product.id]" class="fas fa-spinner fa-spin mr-1"></i>
                        Print Label
                      </button>
                      <button @click="moveProduct(product)" 
                              class="text-orange-600 dark:text-orange-400 hover:underline">
                        Move
                      </button>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- Pagination -->
          <div v-if="filteredProducts.length > itemsPerPage" 
               class="px-6 py-4 border-t border-gray-200 dark:border-gray-700">
            <div class="flex items-center justify-between">
              <div class="text-sm text-gray-700 dark:text-gray-300">
                Showing {{ (currentPage - 1) * itemsPerPage + 1 }} to 
                {{ Math.min(currentPage * itemsPerPage, filteredProducts.length) }} of 
                {{ filteredProducts.length }} results
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

        <!-- Recent Activities -->
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg">
          <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">
              Recent Activities
            </h3>
          </div>
          <div class="p-6">
            <div v-if="recentActivities.length === 0" class="text-center py-8 text-gray-500 dark:text-gray-400">
              <i class="fas fa-history text-3xl mb-2"></i>
              <p class="text-sm">No recent activities</p>
            </div>
            <div v-else class="space-y-4">
              <div v-for="activity in recentActivities" 
                   :key="activity.id" 
                   class="flex items-start space-x-3">
                <div class="flex-shrink-0">
                  <i :class="getActivityIcon(activity.type)" 
                     class="text-gray-400 mt-1"></i>
                </div>
                <div class="flex-1 min-w-0">
                  <p class="text-sm text-gray-900 dark:text-gray-100">
                    {{ formatActivity(activity) }}
                  </p>
                  <p class="text-xs text-gray-500 dark:text-gray-400">
                    {{ formatDate(activity.created_at) }}
                  </p>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Move Product Modal -->
        <div v-if="productToMove" 
             class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50"
             @click.self="productToMove = null">
          <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white dark:bg-gray-800">
            <div class="mt-3">
              <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">
                Move Product
              </h3>
              <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                Move "{{ productToMove.name }}" to a different location.
              </p>
              <div class="mb-4">
                <label for="new-location" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                  New Location
                </label>
                <select id="new-location" 
                        v-model="newLocationId"
                        class="block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                  <option value="">Select a location</option>
                  <option v-for="loc in availableLocations" 
                          :key="loc.id" 
                          :value="loc.id">
                    {{ loc.location_code }}{{ loc.description ? ` - ${loc.description}` : '' }}
                  </option>
                </select>
              </div>
              <div class="flex justify-end space-x-3">
                <button @click="productToMove = null" 
                        class="px-4 py-2 bg-gray-300 dark:bg-gray-600 text-gray-700 dark:text-gray-300 rounded-md hover:bg-gray-400 dark:hover:bg-gray-500 focus:outline-none focus:ring-2 focus:ring-gray-500">
                  Cancel
                </button>
                <button @click="confirmMoveProduct" 
                        :disabled="moving || !newLocationId"
                        class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:opacity-50">
                  <i v-if="moving" class="fas fa-spinner fa-spin mr-2"></i>
                  Move Product
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

export default {
  name: 'LocationShow',
  components: {
    AppLayout,
    Link
  },
  props: {
    location: {
      type: Object,
      required: true
    },
    products: {
      type: Array,
      default: () => []
    },
    statistics: {
      type: Object,
      default: () => ({})
    },
    recentActivities: {
      type: Array,
      default: () => []
    },
    availableLocations: {
      type: Array,
      default: () => []
    }
  },
  data() {
    return {
      printing: false,
      printingAll: false,
      printingProducts: {},
      productSearch: '',
      currentPage: 1,
      itemsPerPage: 10,
      productToMove: null,
      newLocationId: '',
      moving: false
    }
  },
  computed: {
    filteredProducts() {
      if (!this.productSearch) {
        return this.products;
      }
      
      const search = this.productSearch.toLowerCase();
      return this.products.filter(product => 
        product.name.toLowerCase().includes(search) ||
        product.sku.toLowerCase().includes(search) ||
        (product.internal_sku && product.internal_sku.toLowerCase().includes(search)) ||
        (product.brand?.name && product.brand.name.toLowerCase().includes(search))
      );
    },
    paginatedProducts() {
      const start = (this.currentPage - 1) * this.itemsPerPage;
      const end = start + this.itemsPerPage;
      return this.filteredProducts.slice(start, end);
    },
    totalPages() {
      return Math.ceil(this.filteredProducts.length / this.itemsPerPage);
    }
  },
  watch: {
    productSearch() {
      this.currentPage = 1;
    }
  },
  methods: {
    formatDate(date) {
      return new Date(date).toLocaleString();
    },
    
    getActivityIcon(type) {
      switch (type) {
        case 'product_added': return 'fas fa-plus-circle';
        case 'product_removed': return 'fas fa-minus-circle';
        case 'location_updated': return 'fas fa-edit';
        case 'label_printed': return 'fas fa-print';
        default: return 'fas fa-circle';
      }
    },
    
    formatActivity(activity) {
      switch (activity.type) {
        case 'product_added':
          return `Product "${activity.product_name}" was added to this location`;
        case 'product_removed':
          return `Product "${activity.product_name}" was removed from this location`;
        case 'location_updated':
          return 'Location information was updated';
        case 'label_printed':
          return `Label was printed for this location`;
        default:
          return activity.description || 'Activity recorded';
      }
    },
    
    async printLocationLabel() {
      this.printing = true;
      
      try {
        const response = await axios.post(`/printing/locations/${this.location.id}/print-label`);
        
        if (response.data.success) {
          this.$toast.success('Location label print job queued');
        } else {
          this.$toast.error('Failed to queue location label print');
        }
      } catch (error) {
        this.$toast.error(`Print failed: ${error.response?.data?.message || error.message}`);
      } finally {
        this.printing = false;
      }
    },
    
    async printProductLabel(product) {
      this.$set(this.printingProducts, product.id, true);
      
      try {
        const response = await axios.post(`/products/${product.id}/print-label`);
        
        if (response.data.success) {
          this.$toast.success(`Label for "${product.name}" queued for printing`);
        } else {
          this.$toast.error('Failed to queue product label print');
        }
      } catch (error) {
        this.$toast.error(`Print failed: ${error.response?.data?.message || error.message}`);
      } finally {
        this.$set(this.printingProducts, product.id, false);
      }
    },
    
    async printAllProductLabels() {
      this.printingAll = true;
      
      try {
        const productIds = this.filteredProducts.map(p => p.id);
        const response = await axios.post('/labels/print-batch', {
          product_ids: productIds,
          quantity: 1
        });
        
        this.$toast.success(`${response.data.successful_jobs} product labels queued for printing`);
      } catch (error) {
        this.$toast.error(`Batch print failed: ${error.response?.data?.message || error.message}`);
      } finally {
        this.printingAll = false;
      }
    },
    
    moveProduct(product) {
      this.productToMove = product;
      this.newLocationId = '';
    },
    
    async confirmMoveProduct() {
      if (!this.productToMove || !this.newLocationId) return;
      
      this.moving = true;
      
      try {
        await axios.patch(`/products/${this.productToMove.id}`, {
          warehouse_location_id: this.newLocationId
        });
        
        this.$toast.success(`"${this.productToMove.name}" moved successfully`);
        this.productToMove = null;
        this.newLocationId = '';
        
        // Refresh the products list
        await this.$inertia.reload({ only: ['products', 'statistics'] });
      } catch (error) {
        this.$toast.error(`Move failed: ${error.response?.data?.message || error.message}`);
      } finally {
        this.moving = false;
      }
    }
  }
}
</script>
