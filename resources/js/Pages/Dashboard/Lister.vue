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
    listingPipeline: Object,
    performanceMetrics: Object,
    platformBreakdown: Object,
    optimizationSuggestions: Array
})
</script>

<template>
    <AppLayout title="Lister Dashboard">
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-3xl font-bold text-gray-900 dark:text-white">Lister Dashboard</h2>
                    <p class="text-gray-600 dark:text-gray-400 mt-1">Listing pipeline and performance</p>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
    <div class="space-y-8">
        <!-- Top Metrics Row - Listing Focus -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
            <!-- Pending Intake -->
            <MetricCard 
                title="Pending Intake" 
                :value="metrics.pipeline.pending_intake"
                :trend="metrics.pipeline.pending_intake > 20 ? 'down' : 'neutral'"
                icon="fas fa-inbox"
                :color="metrics.pipeline.pending_intake > 20 ? 'red' : 'blue'"
                subtitle="Items awaiting processing"
            />
            
            <!-- Weekly Completions -->
            <MetricCard 
                title="Completed This Week" 
                :value="metrics.pipeline.completed_this_week"
                icon="fas fa-check-circle"
                color="emerald"
                subtitle="Items processed to listings"
            />
            
            <!-- Listings This Month -->
            <MetricCard 
                title="Monthly Listings" 
                :value="metrics.listings.this_month"
                icon="fas fa-tags"
                color="purple"
                subtitle="New products listed"
            />
            
            <!-- Needs Photos -->
            <MetricCard 
                title="Needs Photos" 
                :value="metrics.listings.needs_photos"
                :trend="metrics.listings.needs_photos > 10 ? 'down' : 'neutral'"
                icon="fas fa-camera"
                :color="metrics.listings.needs_photos > 10 ? 'yellow' : 'green'"
                subtitle="Products missing images"
            />
        </div>

        <!-- Listing Pipeline - Kanban Style -->
        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-6">Listing Pipeline</h3>
            
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <!-- Intake Queue -->
                <div class="space-y-3">
                    <div class="flex items-center justify-between">
                        <h4 class="font-medium text-gray-700 dark:text-gray-300">To Process</h4>
                        <span class="bg-blue-100 text-blue-800 text-xs px-2 py-1 rounded-full">
                            {{ listingPipeline.intake?.length || 0 }}
                        </span>
                    </div>
                    <div class="space-y-2 max-h-96 overflow-y-auto">
                        <div 
                            v-for="item in listingPipeline.intake" 
                            :key="item.id"
                            class="p-3 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg hover:shadow-sm transition-shadow cursor-pointer"
                            @click="$inertia.visit(item.url)"
                        >
                            <p class="font-medium text-sm text-gray-900 dark:text-white truncate">{{ item.title }}</p>
                            <div class="flex items-center justify-between mt-1">
                                <span class="text-xs text-gray-500 dark:text-gray-400">{{ item.type }}</span>
                                <span class="text-xs text-gray-400">{{ item.created_at }}</span>
                            </div>
                        </div>
                    </div>
                    <button 
                        @click="$inertia.visit(route('intake-queue.index'))"
                        class="w-full mt-3 px-3 py-2 text-sm text-blue-600 hover:text-blue-800 border border-blue-300 hover:border-blue-400 rounded-lg transition-colors"
                    >
                        View All Intake
                    </button>
                </div>
                
                <!-- Drafts -->
                <div class="space-y-3">
                    <div class="flex items-center justify-between">
                        <h4 class="font-medium text-gray-700 dark:text-gray-300">In Progress</h4>
                        <span class="bg-yellow-100 text-yellow-800 text-xs px-2 py-1 rounded-full">
                            {{ listingPipeline.drafts?.length || 0 }}
                        </span>
                    </div>
                    <div class="space-y-2 max-h-96 overflow-y-auto">
                        <div 
                            v-for="item in listingPipeline.drafts" 
                            :key="item.id"
                            class="p-3 bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg hover:shadow-sm transition-shadow cursor-pointer"
                            @click="$inertia.visit(item.url)"
                        >
                            <p class="font-medium text-sm text-gray-900 dark:text-white truncate">{{ item.title }}</p>
                            <div class="flex items-center justify-between mt-1">
                                <span class="text-xs text-gray-500 dark:text-gray-400">{{ item.category }}</span>
                                <div class="flex items-center">
                                    <div class="w-12 bg-gray-200 rounded-full h-1.5 mr-1">
                                        <div 
                                            :style="`width: ${item.completion}%`"
                                            class="bg-yellow-600 h-1.5 rounded-full transition-all duration-300"
                                        ></div>
                                    </div>
                                    <span class="text-xs text-gray-400">{{ item.completion }}%</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <button 
                        @click="$inertia.visit(route('inventory.products.create'))"
                        class="w-full mt-3 px-3 py-2 text-sm text-yellow-600 hover:text-yellow-800 border border-yellow-300 hover:border-yellow-400 rounded-lg transition-colors"
                    >
                        Create New Draft
                    </button>
                </div>
                
                <!-- Review -->
                <div class="space-y-3">
                    <div class="flex items-center justify-between">
                        <h4 class="font-medium text-gray-700 dark:text-gray-300">Review</h4>
                        <span class="bg-purple-100 text-purple-800 text-xs px-2 py-1 rounded-full">
                            {{ listingPipeline.review?.length || 0 }}
                        </span>
                    </div>
                    <div class="space-y-2 max-h-96 overflow-y-auto">
                        <div 
                            v-for="item in listingPipeline.review" 
                            :key="item.id"
                            class="p-3 bg-purple-50 dark:bg-purple-900/20 border border-purple-200 dark:border-purple-800 rounded-lg hover:shadow-sm transition-shadow cursor-pointer"
                            @click="$inertia.visit(item.url)"
                        >
                            <p class="font-medium text-sm text-gray-900 dark:text-white truncate">{{ item.title }}</p>
                            <div class="flex items-center justify-between mt-1">
                                <span class="text-xs text-gray-500 dark:text-gray-400">{{ item.category }}</span>
                                <span class="text-xs text-gray-400">Ready</span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Live Listings -->
                <div class="space-y-3">
                    <div class="flex items-center justify-between">
                        <h4 class="font-medium text-gray-700 dark:text-gray-300">Live</h4>
                        <span class="bg-emerald-100 text-emerald-800 text-xs px-2 py-1 rounded-full">
                            {{ listingPipeline.live?.length || 0 }}
                        </span>
                    </div>
                    <div class="space-y-2 max-h-96 overflow-y-auto">
                        <div 
                            v-for="item in listingPipeline.live" 
                            :key="item.id"
                            class="p-3 bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-800 rounded-lg hover:shadow-sm transition-shadow cursor-pointer"
                            @click="$inertia.visit(item.url)"
                        >
                            <p class="font-medium text-sm text-gray-900 dark:text-white truncate">{{ item.title }}</p>
                            <div class="flex items-center justify-between mt-1">
                                <span class="text-xs text-gray-500 dark:text-gray-400">${{ item.price }}</span>
                                <div class="flex items-center space-x-1">
                                    <span class="text-xs text-emerald-600">{{ item.views }} views</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Performance Metrics & Optimization -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Performance Dashboard -->
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-6">Performance Metrics</h3>
                
                <!-- Velocity Metrics -->
                <div class="mb-6">
                    <h4 class="font-medium text-gray-700 dark:text-gray-300 mb-3">Listing Velocity</h4>
                    <div class="grid grid-cols-2 gap-4">
                        <div class="text-center p-4 bg-gray-50 dark:bg-gray-700 rounded-xl">
                            <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ performanceMetrics.velocity.items_per_day }}</p>
                            <p class="text-sm text-gray-600 dark:text-gray-400">Items/Day</p>
                        </div>
                        <div class="text-center p-4 bg-gray-50 dark:bg-gray-700 rounded-xl">
                            <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ performanceMetrics.velocity.monthly_total }}</p>
                            <p class="text-sm text-gray-600 dark:text-gray-400">This Month</p>
                        </div>
                    </div>
                </div>

                <!-- Quality Metrics -->
                <div>
                    <h4 class="font-medium text-gray-700 dark:text-gray-300 mb-3">Completion Rates</h4>
                    <div class="space-y-3">
                        <div>
                            <div class="flex justify-between text-sm mb-1">
                                <span class="text-gray-600 dark:text-gray-400">Photos</span>
                                <span class="text-gray-900 dark:text-white">{{ performanceMetrics.quality.photo_completion }}%</span>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-2">
                                <div 
                                    class="bg-blue-600 h-2 rounded-full transition-all duration-500"
                                    :style="`width: ${performanceMetrics.quality.photo_completion}%`"
                                ></div>
                            </div>
                        </div>
                        <div>
                            <div class="flex justify-between text-sm mb-1">
                                <span class="text-gray-600 dark:text-gray-400">Descriptions</span>
                                <span class="text-gray-900 dark:text-white">{{ performanceMetrics.quality.description_completion }}%</span>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-2">
                                <div 
                                    class="bg-emerald-600 h-2 rounded-full transition-all duration-500"
                                    :style="`width: ${performanceMetrics.quality.description_completion}%`"
                                ></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Optimization Suggestions -->
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-6">Optimization Suggestions</h3>
                
                <div v-if="optimizationSuggestions.length === 0" class="text-center py-8">
                    <i class="fas fa-rocket text-emerald-500 text-3xl mb-2"></i>
                    <p class="text-sm text-gray-500 dark:text-gray-400">All optimized! Keep up the great work.</p>
                </div>
                
                <div v-else class="space-y-4">
                    <div 
                        v-for="suggestion in optimizationSuggestions" 
                        :key="suggestion.title"
                        class="p-4 border border-gray-200 dark:border-gray-600 rounded-lg hover:shadow-sm transition-shadow"
                    >
                        <div class="flex items-start">
                            <div 
                                :class="{
                                    'bg-red-100 text-red-600 dark:bg-red-900/20': suggestion.type === 'quality',
                                    'bg-yellow-100 text-yellow-600 dark:bg-yellow-900/20': suggestion.type === 'pricing',
                                    'bg-blue-100 text-blue-600 dark:bg-blue-900/20': suggestion.type === 'efficiency'
                                }"
                                class="flex-shrink-0 w-8 h-8 rounded-full flex items-center justify-center mr-3 mt-1"
                            >
                                <i :class="suggestion.icon || 'fas fa-lightbulb'" class="text-sm"></i>
                            </div>
                            <div class="flex-1">
                                <h4 class="font-semibold text-gray-900 dark:text-white mb-1">{{ suggestion.title }}</h4>
                                <p class="text-sm text-gray-600 dark:text-gray-400 mb-2">{{ suggestion.message }}</p>
                                <button class="text-xs text-indigo-600 hover:text-indigo-800 font-medium">
                                    {{ suggestion.action }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Platform Breakdown -->
        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-6">Platform Performance</h3>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <!-- eBay -->
                <div class="p-4 bg-gray-50 dark:bg-gray-700 rounded-xl">
                    <div class="flex items-center justify-between mb-3">
                        <h4 class="font-medium text-gray-900 dark:text-white">eBay</h4>
                        <span 
                            :class="{
                                'bg-emerald-500': platformBreakdown.ebay.status === 'connected',
                                'bg-gray-400': platformBreakdown.ebay.status !== 'connected'
                            }"
                            class="w-3 h-3 rounded-full"
                        ></span>
                    </div>
                    <div class="space-y-2">
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-600 dark:text-gray-400">Active Listings</span>
                            <span class="text-gray-900 dark:text-white">{{ platformBreakdown.ebay.total_listings }}</span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-600 dark:text-gray-400">Avg Price</span>
                            <span class="text-gray-900 dark:text-white">${{ platformBreakdown.ebay.avg_price.toFixed(2) }}</span>
                        </div>
                    </div>
                </div>
                
                <!-- Amazon -->
                <div class="p-4 bg-gray-50 dark:bg-gray-700 rounded-xl opacity-60">
                    <div class="flex items-center justify-between mb-3">
                        <h4 class="font-medium text-gray-900 dark:text-white">Amazon</h4>
                        <span class="bg-gray-400 w-3 h-3 rounded-full"></span>
                    </div>
                    <div class="space-y-2">
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-600 dark:text-gray-400">Active Listings</span>
                            <span class="text-gray-900 dark:text-white">0</span>
                        </div>
                        <button class="text-xs text-indigo-600 hover:text-indigo-800">Connect Platform</button>
                    </div>
                </div>
                
                <!-- Mercari -->
                <div class="p-4 bg-gray-50 dark:bg-gray-700 rounded-xl opacity-60">
                    <div class="flex items-center justify-between mb-3">
                        <h4 class="font-medium text-gray-900 dark:text-white">Mercari</h4>
                        <span class="bg-gray-400 w-3 h-3 rounded-full"></span>
                    </div>
                    <div class="space-y-2">
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-600 dark:text-gray-400">Active Listings</span>
                            <span class="text-gray-900 dark:text-white">0</span>
                        </div>
                        <button class="text-xs text-indigo-600 hover:text-indigo-800">Connect Platform</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Activity -->
        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-6">Recent Listing Activity</h3>
            <div class="space-y-4 max-h-64 overflow-y-auto">
                <div 
                    v-for="activity in recentActivity" 
                    :key="activity.timestamp"
                    class="flex items-start space-x-3 p-3 hover:bg-gray-50 dark:hover:bg-gray-700 rounded-lg transition-colors"
                >
                    <div 
                        :class="{
                            'bg-blue-100 text-blue-600': activity.type === 'inventory',
                            'bg-emerald-100 text-emerald-600': activity.type === 'intake',
                            'bg-purple-100 text-purple-600': activity.type === 'listing'
                        }"
                        class="flex-shrink-0 w-8 h-8 rounded-full flex items-center justify-center"
                    >
                        <i 
                            :class="{
                                'fas fa-box': activity.type === 'inventory',
                                'fas fa-inbox': activity.type === 'intake',
                                'fas fa-tags': activity.type === 'listing'
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