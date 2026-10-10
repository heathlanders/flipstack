<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import MetricCard from '@/Components/Widgets/MetricCard.vue'

defineProps({
    dashboardType: String,
    user: Object,
    metrics: Object,
    recentActivity: Array,
    alerts: Array,
    quickActions: Array,
    orderQueue: Object,
    shippingMetrics: Object,
    inventoryLocations: Array,
    carrierPerformance: Object
})
</script>

<template>
    <AppLayout title="Fulfillment Dashboard">
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-3xl font-bold text-gray-900 dark:text-white">Fulfillment Dashboard</h2>
                    <p class="text-gray-600 dark:text-gray-400 mt-1">Order processing and shipping</p>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
    <div class="space-y-8">
        <!-- Top Metrics Row - Fulfillment Focus -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
            <!-- Pending Orders -->
            <MetricCard 
                title="Pending Orders" 
                :value="metrics.orders.pending"
                :trend="metrics.orders.pending > 15 ? 'down' : 'neutral'"
                icon="fas fa-clock"
                :color="metrics.orders.pending > 15 ? 'red' : 'blue'"
                subtitle="Awaiting processing"
            />
            
            <!-- Shipped Today -->
            <MetricCard 
                title="Shipped Today" 
                :value="metrics.orders.shipped_today"
                icon="fas fa-shipping-fast"
                color="emerald"
                subtitle="Orders out the door"
            />
            
            <!-- Same-Day Rate -->
            <MetricCard 
                title="Same-Day Shipping" 
                :value="`${metrics.performance.same_day_shipping_rate}%`"
                :trend="metrics.performance.same_day_shipping_rate > 80 ? 'up' : 'down'"
                icon="fas fa-stopwatch"
                :color="metrics.performance.same_day_shipping_rate > 80 ? 'emerald' : 'yellow'"
                subtitle="Orders shipped same day"
            />
            
            <!-- Low Stock Alerts -->
            <MetricCard 
                title="Stock Alerts" 
                :value="metrics.inventory.low_stock_alerts"
                :trend="metrics.inventory.low_stock_alerts > 5 ? 'down' : 'neutral'"
                icon="fas fa-exclamation-triangle"
                :color="metrics.inventory.low_stock_alerts > 5 ? 'red' : 'green'"
                subtitle="Items running low"
            />
        </div>

        <!-- Order Processing Queue - Workflow Style -->
        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Order Processing Queue</h3>
                <div class="flex items-center space-x-2">
                    <button class="px-3 py-1 bg-blue-100 text-blue-800 rounded-full text-sm font-medium">
                        Print All Labels
                    </button>
                    <button class="px-3 py-1 bg-emerald-100 text-emerald-800 rounded-full text-sm font-medium">
                        Bulk Process
                    </button>
                </div>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <!-- To Pick -->
                <div class="space-y-3">
                    <div class="flex items-center justify-between">
                        <h4 class="font-medium text-gray-700 dark:text-gray-300">To Pick</h4>
                        <span class="bg-red-100 text-red-800 text-xs px-2 py-1 rounded-full">
                            {{ orderQueue.to_pick?.length || 0 }}
                        </span>
                    </div>
                    <div class="space-y-2 max-h-96 overflow-y-auto">
                        <div 
                            v-for="order in orderQueue.to_pick" 
                            :key="order.id"
                            class="p-3 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg hover:shadow-sm transition-shadow cursor-pointer"
                        >
                            <div class="flex items-center justify-between mb-2">
                                <span class="font-medium text-sm text-gray-900 dark:text-white">{{ order.id }}</span>
                                <span 
                                    :class="{
                                        'bg-red-500 text-white': order.priority === 'high',
                                        'bg-yellow-500 text-white': order.priority === 'medium',
                                        'bg-gray-500 text-white': order.priority === 'normal'
                                    }"
                                    class="text-xs px-2 py-1 rounded-full"
                                >
                                    {{ order.priority }}
                                </span>
                            </div>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mb-1">{{ order.customer }}</p>
                            <div class="flex items-center justify-between text-xs">
                                <span class="text-gray-500">{{ order.items }} items • ${{ order.total }}</span>
                                <span class="text-red-600">{{ order.deadline }}</span>
                            </div>
                            <div class="flex items-center mt-2">
                                <span class="text-xs text-gray-400 mr-2">{{ order.platform }}</span>
                                <button class="text-xs text-blue-600 hover:text-blue-800">Start Picking</button>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- To Pack -->
                <div class="space-y-3">
                    <div class="flex items-center justify-between">
                        <h4 class="font-medium text-gray-700 dark:text-gray-300">To Pack</h4>
                        <span class="bg-yellow-100 text-yellow-800 text-xs px-2 py-1 rounded-full">
                            {{ orderQueue.to_pack?.length || 0 }}
                        </span>
                    </div>
                    <div class="space-y-2 max-h-96 overflow-y-auto">
                        <div 
                            v-for="order in orderQueue.to_pack" 
                            :key="order.id"
                            class="p-3 bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg hover:shadow-sm transition-shadow cursor-pointer"
                        >
                            <div class="flex items-center justify-between mb-2">
                                <span class="font-medium text-sm text-gray-900 dark:text-white">{{ order.id }}</span>
                                <span class="text-xs text-yellow-600">Picked</span>
                            </div>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mb-1">{{ order.customer }}</p>
                            <div class="flex items-center justify-between text-xs">
                                <span class="text-gray-500">{{ order.items }} items</span>
                                <span class="text-gray-500">{{ order.picked_at }}</span>
                            </div>
                            <div class="flex items-center justify-between mt-2">
                                <span class="text-xs text-gray-400">{{ order.shipping_method }}</span>
                                <button class="text-xs text-yellow-600 hover:text-yellow-800">Pack Order</button>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Ready to Ship -->
                <div class="space-y-3">
                    <div class="flex items-center justify-between">
                        <h4 class="font-medium text-gray-700 dark:text-gray-300">Ready to Ship</h4>
                        <span class="bg-emerald-100 text-emerald-800 text-xs px-2 py-1 rounded-full">
                            {{ orderQueue.ready_to_ship?.length || 0 }}
                        </span>
                    </div>
                    <div class="space-y-2 max-h-96 overflow-y-auto">
                        <div 
                            v-for="order in orderQueue.ready_to_ship" 
                            :key="order.id"
                            class="p-3 bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-800 rounded-lg hover:shadow-sm transition-shadow cursor-pointer"
                        >
                            <div class="flex items-center justify-between mb-2">
                                <span class="font-medium text-sm text-gray-900 dark:text-white">{{ order.id }}</span>
                                <span class="text-xs text-emerald-600">Packed</span>
                            </div>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mb-1">{{ order.customer }}</p>
                            <div class="flex items-center justify-between text-xs">
                                <span class="text-gray-500">{{ order.items }} items</span>
                                <span class="text-gray-500">{{ order.packed_at }}</span>
                            </div>
                            <div class="flex items-center justify-between mt-2">
                                <span class="text-xs text-gray-400">{{ order.shipping_method }}</span>
                                <button class="text-xs text-emerald-600 hover:text-emerald-800">Print Label</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Shipping Performance & Inventory Locations -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Shipping Performance -->
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-6">Shipping Performance</h3>
                
                <!-- Daily Volume -->
                <div class="mb-6">
                    <h4 class="font-medium text-gray-700 dark:text-gray-300 mb-3">Daily Volume</h4>
                    <div class="grid grid-cols-3 gap-3">
                        <div class="text-center p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
                            <p class="text-lg font-bold text-gray-900 dark:text-white">{{ shippingMetrics.daily_volume.today }}</p>
                            <p class="text-xs text-gray-600 dark:text-gray-400">Today</p>
                        </div>
                        <div class="text-center p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
                            <p class="text-lg font-bold text-gray-900 dark:text-white">{{ shippingMetrics.daily_volume.yesterday }}</p>
                            <p class="text-xs text-gray-600 dark:text-gray-400">Yesterday</p>
                        </div>
                        <div class="text-center p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
                            <p class="text-lg font-bold text-gray-900 dark:text-white">{{ shippingMetrics.daily_volume.avg_last_7_days }}</p>
                            <p class="text-xs text-gray-600 dark:text-gray-400">7-day avg</p>
                        </div>
                    </div>
                </div>

                <!-- Shipping Methods -->
                <div class="mb-6">
                    <h4 class="font-medium text-gray-700 dark:text-gray-300 mb-3">Shipping Methods</h4>
                    <div class="space-y-2">
                        <div 
                            v-for="(method, key) in shippingMetrics.shipping_methods" 
                            :key="key"
                            class="flex items-center justify-between p-2 bg-gray-50 dark:bg-gray-700 rounded"
                        >
                            <span class="text-sm text-gray-900 dark:text-white capitalize">{{ key.replace('_', ' ') }}</span>
                            <div class="text-right">
                                <span class="text-sm font-medium text-gray-900 dark:text-white">{{ method.count }}</span>
                                <span class="text-xs text-gray-500 ml-2">${{ method.avg_cost }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Performance Metrics -->
                <div>
                    <h4 class="font-medium text-gray-700 dark:text-gray-300 mb-3">Performance</h4>
                    <div class="space-y-3">
                        <div>
                            <div class="flex justify-between text-sm mb-1">
                                <span class="text-gray-600 dark:text-gray-400">On-Time Delivery</span>
                                <span class="text-gray-900 dark:text-white">{{ shippingMetrics.delivery_performance.on_time_rate }}%</span>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-2">
                                <div 
                                    class="bg-emerald-600 h-2 rounded-full transition-all duration-500"
                                    :style="`width: ${shippingMetrics.delivery_performance.on_time_rate}%`"
                                ></div>
                            </div>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-600 dark:text-gray-400">Avg Delivery Time</span>
                            <span class="text-gray-900 dark:text-white">{{ shippingMetrics.delivery_performance.avg_delivery_time }} days</span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-600 dark:text-gray-400">Tracking Issues</span>
                            <span :class="shippingMetrics.delivery_performance.tracking_issues > 0 ? 'text-red-600' : 'text-gray-900 dark:text-white'">
                                {{ shippingMetrics.delivery_performance.tracking_issues }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Inventory Locations -->
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-6">Inventory Locations</h3>
                
                <div v-if="inventoryLocations.length === 0" class="text-center py-8">
                    <i class="fas fa-map-marker-alt text-gray-400 text-3xl mb-2"></i>
                    <p class="text-sm text-gray-500 dark:text-gray-400">No location data available</p>
                </div>
                
                <div v-else class="space-y-4">
                    <div 
                        v-for="location in inventoryLocations" 
                        :key="location.name"
                        class="p-4 border border-gray-200 dark:border-gray-600 rounded-lg hover:shadow-sm transition-shadow"
                    >
                        <div class="flex items-center justify-between mb-2">
                            <h4 class="font-medium text-gray-900 dark:text-white">{{ location.name }}</h4>
                            <span class="text-sm text-gray-500">{{ location.utilization }}% utilized</span>
                        </div>
                        <div class="flex items-center justify-between text-sm text-gray-600 dark:text-gray-400 mb-2">
                            <span>{{ location.product_count }} products</span>
                            <span>{{ location.total_quantity }} total qty</span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-2">
                            <div 
                                class="h-2 rounded-full transition-all duration-500"
                                :class="{
                                    'bg-emerald-500': location.utilization < 70,
                                    'bg-yellow-500': location.utilization >= 70 && location.utilization < 90,
                                    'bg-red-500': location.utilization >= 90
                                }"
                                :style="`width: ${location.utilization}%`"
                            ></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Carrier Performance -->
        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-6">Carrier Performance</h3>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div 
                    v-for="(carrier, key) in carrierPerformance" 
                    :key="key"
                    class="p-4 bg-gray-50 dark:bg-gray-700 rounded-xl"
                >
                    <div class="flex items-center justify-between mb-3">
                        <h4 class="font-medium text-gray-900 dark:text-white">{{ carrier.name }}</h4>
                        <div class="flex items-center">
                            <div class="flex text-yellow-400 mr-1">
                                <i 
                                    v-for="star in 5" 
                                    :key="star"
                                    :class="star <= Math.floor(carrier.rating) ? 'fas fa-star' : 'far fa-star'"
                                    class="text-xs"
                                ></i>
                            </div>
                            <span class="text-xs text-gray-500">{{ carrier.rating }}</span>
                        </div>
                    </div>
                    
                    <div class="space-y-2">
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-600 dark:text-gray-400">Packages Shipped</span>
                            <span class="text-gray-900 dark:text-white">{{ carrier.packages_shipped }}</span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-600 dark:text-gray-400">On-Time Rate</span>
                            <span class="text-gray-900 dark:text-white">{{ carrier.on_time_rate }}%</span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-600 dark:text-gray-400">Avg Cost</span>
                            <span class="text-gray-900 dark:text-white">${{ carrier.avg_cost }}</span>
                        </div>
                    </div>
                    
                    <!-- Performance Bar -->
                    <div class="mt-3">
                        <div class="w-full bg-gray-200 rounded-full h-2">
                            <div 
                                class="h-2 rounded-full transition-all duration-500"
                                :class="{
                                    'bg-emerald-500': carrier.on_time_rate >= 95,
                                    'bg-blue-500': carrier.on_time_rate >= 90 && carrier.on_time_rate < 95,
                                    'bg-yellow-500': carrier.on_time_rate >= 80 && carrier.on_time_rate < 90,
                                    'bg-red-500': carrier.on_time_rate < 80
                                }"
                                :style="`width: ${carrier.on_time_rate}%`"
                            ></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Activity Feed -->
        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-6">Recent Fulfillment Activity</h3>
            <div class="space-y-4 max-h-64 overflow-y-auto">
                <div 
                    v-for="activity in recentActivity" 
                    :key="activity.timestamp"
                    class="flex items-start space-x-3 p-3 hover:bg-gray-50 dark:hover:bg-gray-700 rounded-lg transition-colors"
                >
                    <div 
                        :class="{
                            'bg-blue-100 text-blue-600': activity.type === 'order',
                            'bg-emerald-100 text-emerald-600': activity.type === 'shipment',
                            'bg-yellow-100 text-yellow-600': activity.type === 'inventory'
                        }"
                        class="flex-shrink-0 w-8 h-8 rounded-full flex items-center justify-center"
                    >
                        <i 
                            :class="{
                                'fas fa-shopping-cart': activity.type === 'order',
                                'fas fa-shipping-fast': activity.type === 'shipment',
                                'fas fa-box': activity.type === 'inventory'
                            }"
                            class="text-xs"
                        ></i>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-gray-900 dark:text-white">{{ activity.title }}</p>
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ activity.description }}</p>
                        <div class="flex items-center justify-between mt-1">
                            <span class="text-xs text-gray-400">by {{ activity.user }}</span>
                            <span class="text-xs text-gray-400">{{ activity.timestamp }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
/* Custom scrollbar styling */
.overflow-y-auto::-webkit-scrollbar {
    width: 4px;
}

.overflow-y-auto::-webkit-scrollbar-track {
    background: transparent;
}

.overflow-y-auto::-webkit-scrollbar-thumb {
    background: #d1d5db;
    border-radius: 2px;
}

.dark .overflow-y-auto::-webkit-scrollbar-thumb {
    background: #6b7280;
}
</style>