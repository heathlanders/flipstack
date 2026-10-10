<template>
    <AppLayout title="eBay Sandbox Testing">
        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
                    <div class="p-6 lg:p-8 bg-white border-b border-gray-200">
                        <div class="flex items-center justify-between mb-8">
                            <h1 class="text-2xl font-medium text-gray-900">
                                eBay Sandbox Testing Dashboard
                            </h1>
                            <div class="flex space-x-4">
                                <button 
                                    @click="refreshData"
                                    :disabled="loading"
                                    class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-md text-sm font-medium disabled:opacity-50"
                                >
                                    <svg v-if="loading" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white inline" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    Refresh
                                </button>
                            </div>
                        </div>

                        <!-- Stats Grid -->
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                            <div class="bg-blue-50 overflow-hidden shadow rounded-lg">
                                <div class="p-5">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0">
                                            <svg class="h-6 w-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                                            </svg>
                                        </div>
                                        <div class="ml-5 w-0 flex-1">
                                            <dl>
                                                <dt class="text-sm font-medium text-gray-500 truncate">
                                                    Total Queue Items
                                                </dt>
                                                <dd class="text-lg font-medium text-gray-900">
                                                    {{ queueStats.total }}
                                                </dd>
                                            </dl>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="bg-green-50 overflow-hidden shadow rounded-lg">
                                <div class="p-5">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0">
                                            <svg class="h-6 w-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                            </svg>
                                        </div>
                                        <div class="ml-5 w-0 flex-1">
                                            <dl>
                                                <dt class="text-sm font-medium text-gray-500 truncate">
                                                    Ready for Listing
                                                </dt>
                                                <dd class="text-lg font-medium text-gray-900">
                                                    {{ queueStats.ready_for_listing }}
                                                </dd>
                                            </dl>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="bg-purple-50 overflow-hidden shadow rounded-lg">
                                <div class="p-5">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0">
                                            <svg class="h-6 w-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/>
                                            </svg>
                                        </div>
                                        <div class="ml-5 w-0 flex-1">
                                            <dl>
                                                <dt class="text-sm font-medium text-gray-500 truncate">
                                                    Successfully Listed
                                                </dt>
                                                <dd class="text-lg font-medium text-gray-900">
                                                    {{ queueStats.listed }}
                                                </dd>
                                            </dl>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="bg-red-50 overflow-hidden shadow rounded-lg">
                                <div class="p-5">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0">
                                            <svg class="h-6 w-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                            </svg>
                                        </div>
                                        <div class="ml-5 w-0 flex-1">
                                            <dl>
                                                <dt class="text-sm font-medium text-gray-500 truncate">
                                                    Failed Listings
                                                </dt>
                                                <dd class="text-lg font-medium text-gray-900">
                                                    {{ queueStats.failed }}
                                                </dd>
                                            </dl>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Bulk Actions -->
                        <div class="bg-white border border-gray-200 rounded-lg p-6 mb-8">
                            <h2 class="text-lg font-medium text-gray-900 mb-4">Bulk Testing Actions</h2>
                            <div class="flex space-x-4">
                                <button
                                    @click="bulkTest(1)"
                                    :disabled="processing"
                                    class="bg-green-600 hover:bg-green-700 text-white px-6 py-2 rounded-md text-sm font-medium disabled:opacity-50"
                                >
                                    Test 1 Listing
                                </button>
                                <button
                                    @click="bulkTest(5)"
                                    :disabled="processing"
                                    class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-md text-sm font-medium disabled:opacity-50"
                                >
                                    Test 5 Listings
                                </button>
                                <button
                                    @click="bulkTest(10)"
                                    :disabled="processing"
                                    class="bg-indigo-600 hover:bg-indigo-700 text-white px-6 py-2 rounded-md text-sm font-medium disabled:opacity-50"
                                >
                                    Test 10 Listings
                                </button>
                            </div>
                        </div>

                        <!-- Recent Items Table -->
                        <div class="bg-white border border-gray-200 rounded-lg overflow-hidden">
                            <div class="px-6 py-4 border-b border-gray-200">
                                <h2 class="text-lg font-medium text-gray-900">Recent Queue Items</h2>
                            </div>
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Product
                                            </th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Status
                                            </th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Condition
                                            </th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Quality Score
                                            </th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Created
                                            </th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Actions
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <tr v-for="item in recentItems" :key="item.id" class="hover:bg-gray-50">
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div>
                                                    <div class="text-sm font-medium text-gray-900">
                                                        {{ item.product_number || item.manufacturer_part_number || 'Unknown' }}
                                                    </div>
                                                    <div class="text-sm text-gray-500">
                                                        {{ item.barcode || 'No barcode' }}
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span :class="getStatusBadgeClass(item.processing_status)" class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full">
                                                    {{ item.processing_status }}
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">
                                                    {{ item.condition || 'NEW' }}
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                <span v-if="item.quality_score" class="font-medium">{{ item.quality_score }}%</span>
                                                <span v-else class="text-gray-400">N/A</span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                {{ formatDate(item.created_at) }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                <button
                                                    v-if="item.processing_status === 'enriched'"
                                                    @click="testSingleListing(item.id)"
                                                    :disabled="processing"
                                                    class="text-blue-600 hover:text-blue-900 disabled:opacity-50"
                                                >
                                                    Test Listing
                                                </button>
                                                <button
                                                    v-if="item.processing_status === 'listing_failed'"
                                                    @click="viewErrors(item)"
                                                    class="text-red-600 hover:text-red-900 ml-2"
                                                >
                                                    View Errors
                                                </button>
                                            </td>
                                        </tr>
                                        <tr v-if="recentItems.length === 0">
                                            <td colspan="6" class="px-6 py-4 text-center text-gray-500">
                                                No queue items found. <a href="/intake-queue" class="text-blue-600 hover:text-blue-900">Add some products to test!</a>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Error Modal -->
        <DialogModal :show="showErrorModal" @close="showErrorModal = false">
            <template #title>
                Listing Errors
            </template>
            <template #content>
                <div v-if="selectedItem">
                    <h3 class="text-lg font-medium mb-2">{{ selectedItem.product_number || selectedItem.manufacturer_part_number }}</h3>
                    <div v-if="selectedItem.validation_errors" class="space-y-2">
                        <div v-for="(error, key) in selectedItem.validation_errors" :key="key" class="text-sm">
                            <span class="font-medium text-red-600">{{ key }}:</span>
                            <span class="text-gray-700 ml-2">{{ error }}</span>
                        </div>
                    </div>
                </div>
            </template>
            <template #footer>
                <SecondaryButton @click="showErrorModal = false">
                    Close
                </SecondaryButton>
            </template>
        </DialogModal>
    </AppLayout>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import DialogModal from '@/Components/DialogModal.vue'
import SecondaryButton from '@/Components/SecondaryButton.vue'

// Props from controller
const props = defineProps({
    queueStats: Object,
    recentItems: Array
})

// Reactive data
const loading = ref(false)
const processing = ref(false)
const showErrorModal = ref(false)
const selectedItem = ref(null)

// Methods
const refreshData = () => {
    loading.value = true
    router.reload({ only: ['queueStats', 'recentItems'] })
    setTimeout(() => { loading.value = false }, 500)
}

const bulkTest = (limit) => {
    processing.value = true
    router.post('/ebay-testing/bulk-test', { limit }, {
        onFinish: () => {
            processing.value = false
            refreshData()
        }
    })
}

const testSingleListing = (queueItemId) => {
    processing.value = true
    router.post('/ebay-testing/test-listing', { queue_item_id: queueItemId }, {
        onFinish: () => {
            processing.value = false
            refreshData()
        }
    })
}

const viewErrors = (item) => {
    selectedItem.value = item
    showErrorModal.value = true
}

const getStatusBadgeClass = (status) => {
    const classes = {
        'enriched': 'bg-green-100 text-green-800',
        'listed_on_ebay': 'bg-purple-100 text-purple-800',
        'listing_failed': 'bg-red-100 text-red-800',
        'processing': 'bg-yellow-100 text-yellow-800'
    }
    return classes[status] || 'bg-gray-100 text-gray-800'
}

const formatDate = (date) => {
    return new Date(date).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    })
}

// Auto-refresh every 30 seconds
onMounted(() => {
    setInterval(() => {
        if (!processing.value && !showErrorModal.value) {
            refreshData()
        }
    }, 30000)
})
</script>