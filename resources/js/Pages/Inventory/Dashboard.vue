<template>
  <AppLayout
    title="Inventory Dashboard"
    page-title="Inventory Dashboard"
  >

    <div class="py-6">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
        
        <!-- Key Metrics -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
          <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
            <div class="flex items-center">
              <div class="flex-shrink-0">
                <div class="w-8 h-8 bg-blue-100 dark:bg-blue-900 rounded-full flex items-center justify-center">
                  <svg class="w-5 h-5 text-blue-600 dark:text-blue-400" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                  </svg>
                </div>
              </div>
              <div class="ml-5 w-0 flex-1">
                <dl>
                  <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">
                    Total Inventory Value
                  </dt>
                  <dd class="text-lg font-medium text-gray-900 dark:text-gray-100">
                    ${{ analytics.inventory_valuation.total_value.toLocaleString() }}
                  </dd>
                </dl>
              </div>
            </div>
          </div>

          <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
            <div class="flex items-center">
              <div class="flex-shrink-0">
                <div class="w-8 h-8 bg-green-100 dark:bg-green-900 rounded-full flex items-center justify-center">
                  <svg class="w-5 h-5 text-green-600 dark:text-green-400" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                  </svg>
                </div>
              </div>
              <div class="ml-5 w-0 flex-1">
                <dl>
                  <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">
                    Total Products
                  </dt>
                  <dd class="text-lg font-medium text-gray-900 dark:text-gray-100">
                    {{ analytics.total_products.toLocaleString() }}
                  </dd>
                </dl>
              </div>
            </div>
          </div>

          <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
            <div class="flex items-center">
              <div class="flex-shrink-0">
                <div class="w-8 h-8 bg-yellow-100 dark:bg-yellow-900 rounded-full flex items-center justify-center">
                  <svg class="w-5 h-5 text-yellow-600 dark:text-yellow-400" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                  </svg>
                </div>
              </div>
              <div class="ml-5 w-0 flex-1">
                <dl>
                  <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">
                    Low Stock Alerts
                  </dt>
                  <dd class="text-lg font-medium text-gray-900 dark:text-gray-100">
                    {{ analytics.low_stock_alerts }}
                  </dd>
                </dl>
              </div>
            </div>
          </div>

          <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
            <div class="flex items-center">
              <div class="flex-shrink-0">
                <div class="w-8 h-8 bg-indigo-100 dark:bg-indigo-900 rounded-full flex items-center justify-center">
                  <svg class="w-5 h-5 text-indigo-600 dark:text-indigo-400" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M13 6a3 3 0 11-6 0 3 3 0 016 0zM18 8a2 2 0 11-4 0 2 2 0 014 0zM14 15a4 4 0 00-8 0v3h8v-3z"/>
                  </svg>
                </div>
              </div>
              <div class="ml-5 w-0 flex-1">
                <dl>
                  <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">
                    Recent Movements
                  </dt>
                  <dd class="text-lg font-medium text-gray-900 dark:text-gray-100">
                    {{ recentMovements.length }}
                  </dd>
                </dl>
              </div>
            </div>
          </div>
        </div>

        <!-- Charts Row -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
          <!-- Inventory Value Chart -->
          <div class="bg-white dark:bg-gray-800 shadow sm:rounded-lg">
            <div class="px-4 py-5 sm:p-6">
              <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-gray-100 mb-4">
                Inventory Movement Trends
              </h3>
              <div class="h-64">
                <!-- Chart component would go here -->
                <div class="h-full flex items-center justify-center text-gray-400">
                  Chart Component Placeholder
                </div>
              </div>
            </div>
          </div>

          <!-- Top Categories -->
          <div class="bg-white dark:bg-gray-800 shadow sm:rounded-lg">
            <div class="px-4 py-5 sm:p-6">
              <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-gray-100 mb-4">
                Top Categories by Value
              </h3>
              <div class="space-y-3">
                <div v-for="category in topCategories" :key="category.id" class="flex items-center justify-between">
                  <div class="flex items-center">
                    <div class="w-3 h-3 rounded-full mr-3" :style="{ backgroundColor: category.color }"></div>
                    <span class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ category.name }}</span>
                  </div>
                  <span class="text-sm text-gray-500 dark:text-gray-400">${{ category.value.toLocaleString() }}</span>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Quick Actions & Recent Activity -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
          <!-- Quick Actions -->
          <div class="bg-white dark:bg-gray-800 shadow sm:rounded-lg">
            <div class="px-4 py-5 sm:p-6">
              <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-gray-100 mb-4">
                Quick Actions
              </h3>
              <div class="space-y-3">
                <button 
                  @click="$inertia.visit(route('inventory.products.create'))"
                  class="w-full flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                >
                  <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd"/>
                  </svg>
                  Add New Product
                </button>
                
                <button 
                  @click="$inertia.visit(route('inventory.operations.adjust.form'))"
                  class="w-full flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 text-sm font-medium rounded-md text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                >
                  <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M8 5a1 1 0 100 2h5.586l-1.293 1.293a1 1 0 001.414 1.414l3-3a1 1 0 000-1.414l-3-3a1 1 0 10-1.414 1.414L13.586 5H8zM12 15a1 1 0 100-2H6.414l1.293-1.293a1 1 0 10-1.414-1.414l-3 3a1 1 0 000 1.414l3 3a1 1 0 001.414-1.414L6.414 15H12z"/>
                  </svg>
                  Adjust Inventory
                </button>
                
                <button 
                  @click="$inertia.visit(route('inventory.operations.transfer.form'))"
                  class="w-full flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 text-sm font-medium rounded-md text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                >
                  <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10.293 3.293a1 1 0 011.414 0l6 6a1 1 0 010 1.414l-6 6a1 1 0 01-1.414-1.414L14.586 11H3a1 1 0 110-2h11.586l-4.293-4.293a1 1 0 010-1.414z" clip-rule="evenodd"/>
                  </svg>
                  Transfer Stock
                </button>
                
                <button 
                  @click="$inertia.visit(route('admin.suppliers.index'))"
                  class="w-full flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 text-sm font-medium rounded-md text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                >
                  <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M13 6a3 3 0 11-6 0 3 3 0 016 0zM18 8a2 2 0 11-4 0 2 2 0 014 0zM14 15a4 4 0 00-8 0v3h8v-3z"/>
                  </svg>
                  Manage Suppliers
                </button>
              </div>
            </div>
          </div>

          <!-- Low Stock Alerts -->
          <div class="bg-white dark:bg-gray-800 shadow sm:rounded-lg">
            <div class="px-4 py-5 sm:p-6">
              <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-gray-100 mb-4">
                Low Stock Alerts
              </h3>
              <div class="space-y-3 max-h-80 overflow-y-auto">
                <div v-for="alert in lowStockAlerts" :key="alert.id" class="flex items-center justify-between p-3 bg-yellow-50 dark:bg-yellow-900/20 rounded-lg">
                  <div class="flex-1">
                    <p class="text-sm font-medium text-gray-900 dark:text-gray-100">
                      {{ alert.product?.name }}
                    </p>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                      {{ alert.location?.name }} - {{ alert.quantity_available }} left
                    </p>
                  </div>
                  <button 
                    @click="adjustStock(alert)"
                    class="text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 text-sm font-medium"
                  >
                    Restock
                  </button>
                </div>
                
                <div v-if="lowStockAlerts.length === 0" class="text-center py-4">
                  <svg class="w-8 h-8 text-green-400 mx-auto mb-2" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                  </svg>
                  <p class="text-sm text-gray-500 dark:text-gray-400">All products are well stocked!</p>
                </div>
              </div>
            </div>
          </div>

          <!-- Recent Activity -->
          <div class="bg-white dark:bg-gray-800 shadow sm:rounded-lg">
            <div class="px-4 py-5 sm:p-6">
              <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-gray-100 mb-4">
                Recent Activity
              </h3>
              <div class="space-y-3 max-h-80 overflow-y-auto">
                <div v-for="movement in recentMovements" :key="movement.id" class="flex items-start space-x-3">
                  <div class="flex-shrink-0">
                    <div 
                      :class="{
                        'bg-green-100 text-green-600 dark:bg-green-900 dark:text-green-400': movement.is_increase,
                        'bg-red-100 text-red-600 dark:bg-red-900 dark:text-red-400': movement.is_decrease
                      }"
                      class="w-8 h-8 rounded-full flex items-center justify-center"
                    >
                      <svg v-if="movement.is_increase" class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd"/>
                      </svg>
                      <svg v-else class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M3 10a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1z" clip-rule="evenodd"/>
                      </svg>
                    </div>
                  </div>
                  <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium text-gray-900 dark:text-gray-100">
                      {{ movement.type_label }}
                    </p>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                      {{ movement.product?.name }} - {{ movement.display_quantity }}
                    </p>
                    <p class="text-xs text-gray-400 dark:text-gray-500">
                      {{ formatRelativeTime(movement.created_at) }}
                    </p>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Stock Adjustment Modal -->
    <Modal :show="showAdjustModal" @close="showAdjustModal = false">
      <div class="p-6">
        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">
          Adjust Stock: {{ selectedProduct?.product?.name }}
        </h3>
        
        <form @submit.prevent="submitAdjustment">
          <div class="space-y-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Current Stock</label>
              <p class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                {{ selectedProduct?.quantity_available || 0 }}
              </p>
            </div>
            
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Adjustment Quantity</label>
              <TextInput
                v-model="adjustmentForm.quantity"
                type="number"
                class="mt-1"
                placeholder="Enter quantity (positive to add, negative to subtract)"
                required
              />
            </div>
            
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Reason</label>
              <textarea
                v-model="adjustmentForm.reason"
                rows="3"
                class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600"
                placeholder="Reason for adjustment..."
                required
              ></textarea>
            </div>
          </div>

          <div class="mt-6 flex justify-end space-x-3">
            <SecondaryButton @click="showAdjustModal = false">
              Cancel
            </SecondaryButton>
            <PrimaryButton 
              type="submit"
              :disabled="adjusting"
              :class="{ 'opacity-25': adjusting }"
            >
              <span v-if="adjusting">Adjusting...</span>
              <span v-else>Adjust Stock</span>
            </PrimaryButton>
          </div>
        </form>
      </div>
    </Modal>
  </AppLayout>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import PrimaryButton from '@/Components/PrimaryButton.vue'
import SecondaryButton from '@/Components/SecondaryButton.vue'
import TextInput from '@/Components/TextInput.vue'
import Modal from '@/Components/Modal.vue'

// Props
const props = defineProps({
  analytics: Object,
  lowStockAlerts: Array,
  recentMovements: Array,
  topCategories: Array
})

// Reactive data
const showAdjustModal = ref(false)
const selectedProduct = ref(null)
const adjusting = ref(false)

const adjustmentForm = reactive({
  quantity: '',
  reason: ''
})

// Methods
const adjustStock = (alert) => {
  selectedProduct.value = alert
  adjustmentForm.quantity = ''
  adjustmentForm.reason = 'Restock due to low stock alert'
  showAdjustModal.value = true
}

const submitAdjustment = async () => {
  if (!selectedProduct.value) return
  
  adjusting.value = true
  
  try {
    await axios.post('/api/v1/inventory/adjust-stock', {
      product_id: selectedProduct.value.product_id,
      location_id: selectedProduct.value.location_id,
      quantity: parseInt(adjustmentForm.quantity),
      reason: adjustmentForm.reason
    })
    
    showAdjustModal.value = false
    // Refresh the dashboard data
    router.reload()
  } catch (error) {
    console.error('Failed to adjust stock:', error)
    alert('Failed to adjust stock. Please try again.')
  } finally {
    adjusting.value = false
  }
}

const formatRelativeTime = (dateString) => {
  const date = new Date(dateString)
  const now = new Date()
  const diffInSeconds = Math.floor((now - date) / 1000)
  
  if (diffInSeconds < 60) return 'Just now'
  if (diffInSeconds < 3600) return `${Math.floor(diffInSeconds / 60)}m ago`
  if (diffInSeconds < 86400) return `${Math.floor(diffInSeconds / 3600)}h ago`
  return `${Math.floor(diffInSeconds / 86400)}d ago`
}

// Real-time updates (if Echo is set up)
onMounted(() => {
  if (typeof Echo !== 'undefined' && window.Laravel?.user?.current_organization_id) {
    Echo.private(`inventory.${window.Laravel.user.current_organization_id}`)
      .listen('InventoryMovementCreated', (e) => {
        // Update recent movements
        props.recentMovements.unshift(e.movement)
        if (props.recentMovements.length > 10) {
          props.recentMovements.pop()
        }
      })
      .listen('LowStockAlert', (e) => {
        // Add to low stock alerts if not already present
        const exists = props.lowStockAlerts.find(alert => alert.id === e.inventory_level.id)
        if (!exists) {
          props.lowStockAlerts.unshift(e.inventory_level)
        }
      })
  }
})
</script>