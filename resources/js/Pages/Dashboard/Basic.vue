<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import MetricCard from '@/Components/Widgets/MetricCard.vue'

defineProps({
    dashboardType: String,
    user: Object,
    permissions: Array,
    quickActions: Array,
    availableFeatures: Object
})

// Safe route helper - returns '#' if route doesn't exist
const safeRoute = (name, params = {}) => {
    try {
        return route(name, params)
    } catch (e) {
        console.warn(`Route '${name}' not found`)
        return '#'
    }
}
</script>

<template>
    <AppLayout title="Dashboard">
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-3xl font-bold text-gray-900 dark:text-white">Welcome Dashboard</h2>
                    <p class="text-gray-600 dark:text-gray-400 mt-1">
                        Welcome back, {{ user.name }}!
                    </p>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 p-8">
                    <div class="text-center">
                        <i class="fas fa-tachometer-alt text-gray-400 text-6xl mb-4"></i>
                        <h3 class="text-xl font-semibold text-gray-900 dark:text-white mb-2">
                            Welcome to your Dashboard
                        </h3>
                        <p class="text-gray-600 dark:text-gray-400 mb-6">
                            Your role-specific dashboard will appear here once permissions are configured.
                        </p>
                        
                        <!-- Available Features -->
                        <div v-if="Object.keys(availableFeatures).length > 0" class="grid grid-cols-2 md:grid-cols-4 gap-4 mt-8">
                            <a 
                                v-if="availableFeatures.inventory"
                                :href="route('inventory.dashboard')"
                                class="p-4 bg-blue-50 dark:bg-blue-900/20 rounded-lg hover:shadow-sm transition-shadow"
                            >
                                <i class="fas fa-boxes text-blue-600 text-2xl mb-2"></i>
                                <p class="text-sm font-medium text-gray-900 dark:text-white">Inventory</p>
                            </a>
                            
                            <a
                                v-if="availableFeatures.orders"
                                :href="safeRoute('orders.index')"
                                class="p-4 bg-emerald-50 dark:bg-emerald-900/20 rounded-lg hover:shadow-sm transition-shadow"
                            >
                                <i class="fas fa-shopping-cart text-emerald-600 text-2xl mb-2"></i>
                                <p class="text-sm font-medium text-gray-900 dark:text-white">Orders</p>
                            </a>

                            <a
                                v-if="availableFeatures.analytics"
                                :href="safeRoute('analytics.index')"
                                class="p-4 bg-purple-50 dark:bg-purple-900/20 rounded-lg hover:shadow-sm transition-shadow"
                            >
                                <i class="fas fa-chart-bar text-purple-600 text-2xl mb-2"></i>
                                <p class="text-sm font-medium text-gray-900 dark:text-white">Analytics</p>
                            </a>
                            
                            <a
                                v-if="availableFeatures.settings"
                                :href="route('organization.settings')"
                                class="p-4 bg-gray-50 dark:bg-gray-700 rounded-lg hover:shadow-sm transition-shadow"
                            >
                                <i class="fas fa-cog text-gray-600 text-2xl mb-2"></i>
                                <p class="text-sm font-medium text-gray-900 dark:text-white">Settings</p>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>