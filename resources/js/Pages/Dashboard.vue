<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'

// The backend DashboardController handles routing to specific dashboard pages
// So this file should only be reached if there's a routing issue
defineProps({
    dashboardType: String,
    user: Object,
})
</script>

<template>
    <AppLayout
        title="Dashboard"
        page-title="Dashboard"
    >

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg p-6">
                    <div class="text-center">
                        <i class="fas fa-sync-alt text-gray-400 text-4xl mb-4"></i>
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">
                            Loading your dashboard...
                        </h3>
                        <p class="text-gray-600 dark:text-gray-400">
                            Redirecting you to the appropriate dashboard for your role.
                        </p>
                        
                        <!-- Fallback links -->
                        <div class="mt-6 space-x-4">
                            <a :href="route('inventory.dashboard')" class="text-blue-600 hover:text-blue-800">
                                Go to Inventory
                            </a>
                            <a :href="route('intake-queue.index')" class="text-blue-600 hover:text-blue-800">
                                Go to Intake Queue
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>