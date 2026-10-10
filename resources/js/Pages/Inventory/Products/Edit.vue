<template>
  <AppLayout title="Edit Product">
    <template #header>
      <div class="flex items-center">
        <button
          @click="$inertia.visit(route('inventory.products.show', props.product.id))"
          class="mr-4 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
        >
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
          </svg>
        </button>
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
          Edit Product
        </h2>
      </div>
    </template>

    <div class="py-6">
      <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
        <form @submit.prevent="submit">
          <div class="space-y-6">

            <!-- Basic Information -->
            <div class="bg-white dark:bg-gray-800 shadow sm:rounded-lg">
              <div class="px-4 py-5 sm:p-6">
                <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-gray-100 mb-4">
                  Basic Information
                </h3>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                  <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Product Name</label>
                    <TextInput
                      v-model="form.name"
                      type="text"
                      class="mt-1"
                      required
                      placeholder="Enter product name"
                    />
                    <InputError :message="form.errors.name" class="mt-2" />
                  </div>

                  <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">SKU</label>
                    <TextInput
                      v-model="form.sku"
                      type="text"
                      class="mt-1"
                      placeholder="Will be auto-generated if empty"
                    />
                    <InputError :message="form.errors.sku" class="mt-2" />
                  </div>

                  <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Category</label>
                    <select
                      v-model="form.category_id"
                      class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600"
                    >
                      <option value="">Select Category</option>
                      <option v-for="category in filterOptions?.categories" :key="category.id" :value="category.id">
                        {{ category.name }}
                      </option>
                    </select>
                    <InputError :message="form.errors.category_id" class="mt-2" />
                  </div>

                  <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Short Description</label>
                    <textarea
                      v-model="form.short_description"
                      rows="2"
                      class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600"
                      placeholder="Brief product description"
                    ></textarea>
                    <InputError :message="form.errors.short_description" class="mt-2" />
                  </div>

                  <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Full Description</label>
                    <textarea
                      v-model="form.description"
                      rows="4"
                      class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600"
                      placeholder="Detailed product description"
                    ></textarea>
                    <InputError :message="form.errors.description" class="mt-2" />
                  </div>
                </div>
              </div>
            </div>

            <!-- Pricing & Inventory -->
            <div class="bg-white dark:bg-gray-800 shadow sm:rounded-lg">
              <div class="px-4 py-5 sm:p-6">
                <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-gray-100 mb-4">
                  Pricing & Inventory
                </h3>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                  <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Cost Price</label>
                    <div class="mt-1 relative rounded-md shadow-sm">
                      <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <span class="text-gray-500 sm:text-sm">$</span>
                      </div>
                      <TextInput
                        v-model="form.cost_price"
                        type="number"
                        step="0.01"
                        min="0"
                        class="pl-7"
                        required
                        placeholder="0.00"
                      />
                    </div>
                    <InputError :message="form.errors.cost_price" class="mt-2" />
                  </div>

                  <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Selling Price</label>
                    <div class="mt-1 relative rounded-md shadow-sm">
                      <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <span class="text-gray-500 sm:text-sm">$</span>
                      </div>
                      <TextInput
                        v-model="form.selling_price"
                        type="number"
                        step="0.01"
                        min="0"
                        class="pl-7"
                        required
                        placeholder="0.00"
                      />
                    </div>
                    <InputError :message="form.errors.selling_price" class="mt-2" />
                  </div>

                  <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">MSRP</label>
                    <div class="mt-1 relative rounded-md shadow-sm">
                      <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <span class="text-gray-500 sm:text-sm">$</span>
                      </div>
                      <TextInput
                        v-model="form.msrp"
                        type="number"
                        step="0.01"
                        min="0"
                        class="pl-7"
                        placeholder="0.00"
                      />
                    </div>
                    <InputError :message="form.errors.msrp" class="mt-2" />
                  </div>

                  <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Stock Quantity</label>
                    <TextInput
                      v-model="form.stock_quantity"
                      type="number"
                      min="0"
                      class="mt-1"
                      placeholder="0"
                    />
                    <InputError :message="form.errors.stock_quantity" class="mt-2" />
                  </div>

                  <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Low Stock Threshold</label>
                    <TextInput
                      v-model="form.low_stock_threshold"
                      type="number"
                      min="0"
                      class="mt-1"
                      placeholder="10"
                    />
                    <InputError :message="form.errors.low_stock_threshold" class="mt-2" />
                  </div>

                  <div class="flex items-center space-x-6">
                    <div class="flex items-center">
                      <input
                        v-model="form.track_inventory"
                        type="checkbox"
                        class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded"
                      >
                      <label class="ml-2 text-sm text-gray-700 dark:text-gray-300">Track Inventory</label>
                    </div>
                    <div class="flex items-center">
                      <input
                        v-model="form.manage_stock"
                        type="checkbox"
                        class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded"
                      >
                      <label class="ml-2 text-sm text-gray-700 dark:text-gray-300">Manage Stock</label>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Physical Attributes -->
            <div class="bg-white dark:bg-gray-800 shadow sm:rounded-lg">
              <div class="px-4 py-5 sm:p-6">
                <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-gray-100 mb-4">
                  Physical Attributes
                </h3>

                <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                  <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Weight (kg)</label>
                    <TextInput
                      v-model="form.weight"
                      type="number"
                      step="0.001"
                      min="0"
                      class="mt-1"
                      placeholder="0.000"
                    />
                    <InputError :message="form.errors.weight" class="mt-2" />
                  </div>

                  <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Length (cm)</label>
                    <TextInput
                      v-model="form.length"
                      type="number"
                      step="0.01"
                      min="0"
                      class="mt-1"
                      placeholder="0.00"
                    />
                    <InputError :message="form.errors.length" class="mt-2" />
                  </div>

                  <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Width (cm)</label>
                    <TextInput
                      v-model="form.width"
                      type="number"
                      step="0.01"
                      min="0"
                      class="mt-1"
                      placeholder="0.00"
                    />
                    <InputError :message="form.errors.width" class="mt-2" />
                  </div>

                  <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Height (cm)</label>
                    <TextInput
                      v-model="form.height"
                      type="number"
                      step="0.01"
                      min="0"
                      class="mt-1"
                      placeholder="0.00"
                    />
                    <InputError :message="form.errors.height" class="mt-2" />
                  </div>
                </div>
              </div>
            </div>

            <!-- Status & Publishing -->
            <div class="bg-white dark:bg-gray-800 shadow sm:rounded-lg">
              <div class="px-4 py-5 sm:p-6">
                <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-gray-100 mb-4">
                  Status & Publishing
                </h3>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                  <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Status</label>
                    <select
                      v-model="form.status"
                      class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600"
                    >
                      <option value="draft">Draft</option>
                      <option value="active">Active</option>
                      <option value="inactive">Inactive</option>
                    </select>
                    <InputError :message="form.errors.status" class="mt-2" />
                  </div>

                  <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Product Type</label>
                    <select
                      v-model="form.type"
                      class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600"
                    >
                      <option value="simple">Simple Product</option>
                      <option value="variable">Variable Product</option>
                      <option value="bundle">Bundle Product</option>
                      <option value="digital">Digital Product</option>
                    </select>
                    <InputError :message="form.errors.type" class="mt-2" />
                  </div>
                </div>
              </div>
            </div>

            <!-- Marketplace Listings (Read-only) -->
            <div v-if="hasMarketplaceListings" class="bg-white dark:bg-gray-800 shadow sm:rounded-lg">
              <div class="px-4 py-5 sm:p-6">
                <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-gray-100 mb-4">
                  Marketplace Listings
                </h3>
                <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">
                  Changing the selling price will automatically sync to these listings.
                </p>

                <!-- eBay Listings -->
                <div v-if="props.product.ebay_listings?.length" class="mb-4">
                  <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">eBay</h4>
                  <div class="space-y-2">
                    <div
                      v-for="listing in props.product.ebay_listings"
                      :key="listing.id"
                      class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-700 rounded-lg"
                    >
                      <div class="flex items-center space-x-3">
                        <span
                          class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                          :class="{
                            'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200': listing.status_color === 'green',
                            'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200': listing.status_color === 'yellow',
                            'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200': listing.status_color === 'red',
                            'bg-gray-100 text-gray-800 dark:bg-gray-600 dark:text-gray-200': listing.status_color === 'gray',
                            'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200': listing.status_color === 'blue',
                          }"
                        >
                          {{ listing.status_label }}
                        </span>
                        <span class="text-sm text-gray-600 dark:text-gray-300">
                          #{{ listing.ebay_listing_id }}
                        </span>
                      </div>
                      <div class="flex items-center space-x-4 text-sm">
                        <span class="text-gray-600 dark:text-gray-300">${{ Number(listing.listed_price).toFixed(2) }}</span>
                        <span class="text-gray-400 dark:text-gray-500">Qty: {{ listing.available_quantity }}</span>
                        <span v-if="listing.last_synced_at_human" class="text-gray-400 dark:text-gray-500">
                          Synced {{ listing.last_synced_at_human }}
                        </span>
                        <a
                          v-if="listing.ebay_url"
                          :href="listing.ebay_url"
                          target="_blank"
                          class="text-indigo-600 hover:text-indigo-500 dark:text-indigo-400"
                        >
                          View
                        </a>
                      </div>
                    </div>
                  </div>
                </div>

                <!-- Shopify Listings -->
                <div v-if="props.product.shopify_listings?.length">
                  <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Shopify</h4>
                  <div class="space-y-2">
                    <div
                      v-for="listing in props.product.shopify_listings"
                      :key="listing.id"
                      class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-700 rounded-lg"
                    >
                      <div class="flex items-center space-x-3">
                        <span
                          class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                          :class="{
                            'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200': listing.status_color === 'green',
                            'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200': listing.status_color === 'yellow',
                            'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200': listing.status_color === 'red',
                            'bg-gray-100 text-gray-800 dark:bg-gray-600 dark:text-gray-200': listing.status_color === 'gray',
                            'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200': listing.status_color === 'blue',
                          }"
                        >
                          {{ listing.status_label }}
                        </span>
                        <span class="text-sm text-gray-600 dark:text-gray-300">
                          Shopify #{{ listing.shopify_product_id }}
                        </span>
                      </div>
                      <div class="flex items-center space-x-4 text-sm">
                        <span class="text-gray-600 dark:text-gray-300">${{ Number(listing.listed_price).toFixed(2) }}</span>
                        <span class="text-gray-400 dark:text-gray-500">Qty: {{ listing.inventory_quantity }}</span>
                        <span v-if="listing.last_synced_at_human" class="text-gray-400 dark:text-gray-500">
                          Synced {{ listing.last_synced_at_human }}
                        </span>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Marketplace Sync -->
            <div class="bg-white dark:bg-gray-800 shadow sm:rounded-lg">
              <div class="px-4 py-5 sm:p-6">
                <div class="flex items-center justify-between mb-4">
                  <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-gray-100">
                    Marketplace Sync
                  </h3>
                  <a
                    :href="route('marketplaces.sync-rules.index')"
                    class="text-sm text-indigo-600 dark:text-indigo-400 hover:underline"
                  >
                    Manage Rules
                  </a>
                </div>

                <div class="space-y-4">
                  <div
                    v-for="platform in ['ebay', 'shopify']"
                    :key="platform"
                    class="flex items-center justify-between py-3 border-b border-gray-100 dark:border-gray-700 last:border-0"
                  >
                    <div>
                      <p class="text-sm font-medium text-gray-900 dark:text-gray-100 capitalize">{{ platform === 'ebay' ? 'eBay' : 'Shopify' }}</p>
                      <p
                        v-if="marketplaceSyncStatus[platform]"
                        class="text-xs mt-0.5"
                        :class="{
                          'text-emerald-600 dark:text-emerald-400': marketplaceSyncStatus[platform].syncs,
                          'text-red-600 dark:text-red-400': !marketplaceSyncStatus[platform].syncs
                        }"
                      >
                        {{ marketplaceSyncStatus[platform].label }}
                      </p>
                    </div>
                    <select
                      v-model="form.marketplace_overrides[platform]"
                      class="text-sm rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 focus:ring-indigo-500"
                    >
                      <option value="auto">Auto (use rules)</option>
                      <option value="include">Always Include</option>
                      <option value="exclude">Always Exclude</option>
                    </select>
                  </div>
                </div>
              </div>
            </div>

            <!-- Form Actions -->
            <div class="flex justify-end space-x-3">
              <SecondaryButton
                type="button"
                @click="$inertia.visit(route('inventory.products.show', props.product.id))"
              >
                Cancel
              </SecondaryButton>
              <PrimaryButton
                type="submit"
                :disabled="form.processing"
                :class="{ 'opacity-25': form.processing }"
              >
                <span v-if="form.processing">Saving...</span>
                <span v-else>Save Changes</span>
              </PrimaryButton>
            </div>
          </div>
        </form>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { computed } from 'vue'
import { useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import PrimaryButton from '@/Components/PrimaryButton.vue'
import SecondaryButton from '@/Components/SecondaryButton.vue'
import TextInput from '@/Components/TextInput.vue'
import InputError from '@/Components/InputError.vue'

const props = defineProps({
  product: Object,
  filterOptions: Object,
  marketplaceSyncStatus: { type: Object, default: () => ({}) }
})

const hasMarketplaceListings = computed(() => {
  return props.product.ebay_listings?.length || props.product.shopify_listings?.length
})

const form = useForm({
  name: props.product.name || '',
  sku: props.product.sku || '',
  category_id: props.product.category?.id || '',
  short_description: props.product.short_description || '',
  description: props.product.description || '',
  cost_price: props.product.cost_price || '',
  selling_price: props.product.selling_price || '',
  msrp: props.product.msrp || '',
  weight: props.product.weight || '',
  length: props.product.length || '',
  width: props.product.width || '',
  height: props.product.height || '',
  stock_quantity: props.product.stock_quantity || 0,
  low_stock_threshold: props.product.low_stock_threshold || 10,
  track_inventory: props.product.track_inventory ?? true,
  manage_stock: props.product.manage_stock ?? true,
  status: props.product.status || 'draft',
  type: props.product.type || 'simple',
  marketplace_overrides: {
    ebay: props.product.marketplace_overrides?.ebay ?? 'auto',
    shopify: props.product.marketplace_overrides?.shopify ?? 'auto',
  }
})

const submit = () => {
  form.put(route('inventory.products.update', props.product.id), {
    onError: (errors) => {
      console.error('Validation errors:', errors)
    }
  })
}
</script>
