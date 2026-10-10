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
    roiAnalysis: Array,
    supplierPerformance: Array,
    marketInsights: Array,
    budgetTracking: Object
})
</script>

<template>
    <AppLayout title="Buyer Dashboard">
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-3xl font-bold text-gray-900 dark:text-white">Buyer Dashboard</h2>
                    <p class="text-gray-600 dark:text-gray-400 mt-1">Sourcing and procurement overview</p>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
    <div class="space-y-8">
        <!-- Top Metrics Row - Sourcing Focus -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
            <!-- Monthly Purchases -->
            <MetricCard 
                title="Products This Month" 
                :value="metrics.purchasing.this_month"
                :change="metrics.purchasing.trend"
                :trend="metrics.purchasing.trend > 0 ? 'up' : metrics.purchasing.trend < 0 ? 'down' : 'neutral'"
                icon="fas fa-shopping-cart"
                color="emerald"
                :subtitle="`vs ${metrics.purchasing.last_month} last month`"
            />
            
            <!-- Average ROI -->
            <MetricCard 
                title="Average ROI" 
                :value="`${metrics.investment.avg_roi.toFixed(1)}%`"
                :trend="metrics.investment.avg_roi > 50 ? 'up' : metrics.investment.avg_roi < 20 ? 'down' : 'neutral'"
                icon="fas fa-chart-line"
                color="blue"
                subtitle="Profit margin potential"
            />
            
            <!-- Monthly Investment -->
            <MetricCard 
                title="Monthly Investment" 
                :value="$page.props.formatCurrency ? $page.props.formatCurrency(metrics.investment.monthly_total) : `$${metrics.investment.monthly_total.toFixed(2)}`"
                icon="fas fa-dollar-sign"
                color="purple"
                subtitle="Total cost this month"
            />
            
            <!-- Needs Research -->
            <MetricCard 
                title="Needs Pricing" 
                :value="metrics.research.needs_pricing"
                :trend="metrics.research.needs_pricing > 10 ? 'down' : 'neutral'"
                icon="fas fa-search"
                :color="metrics.research.needs_pricing > 10 ? 'red' : 'green'"
                subtitle="Items need research"
            />
        </div>

        <!-- Budget Tracking & ROI Analysis -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Budget Progress -->
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                <div class="flex items-center justify-between mb-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Budget Tracking</h3>
                    <span class="text-sm text-gray-500 dark:text-gray-400">Monthly Overview</span>
                </div>
                
                <!-- Monthly Budget -->
                <div class="mb-6">
                    <div class="flex justify-between items-center mb-2">
                        <span class="text-sm font-medium text-gray-600 dark:text-gray-400">Monthly Budget</span>
                        <span class="text-sm text-gray-900 dark:text-white">
                            {{ budgetTracking.monthly.formatted_spent }} / ${{ budgetTracking.monthly.budget.toFixed(0) }}
                        </span>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-3 mb-2">
                        <div 
                            class="h-3 rounded-full transition-all duration-500"
                            :class="{
                                'bg-emerald-500': budgetTracking.monthly.percentage_used < 70,
                                'bg-yellow-500': budgetTracking.monthly.percentage_used >= 70 && budgetTracking.monthly.percentage_used < 90,
                                'bg-red-500': budgetTracking.monthly.percentage_used >= 90
                            }"
                            :style="`width: ${Math.min(budgetTracking.monthly.percentage_used, 100)}%`"
                        ></div>
                    </div>
                    <div class="flex justify-between text-xs text-gray-500">
                        <span>{{ budgetTracking.monthly.percentage_used.toFixed(1) }}% used</span>
                        <span>{{ budgetTracking.monthly.formatted_remaining }} remaining</span>
                    </div>
                </div>

                <!-- Budget Status Alert -->
                <div 
                    v-if="budgetTracking.monthly.percentage_used > 80"
                    class="p-3 rounded-lg"
                    :class="{
                        'bg-yellow-50 border border-yellow-200 dark:bg-yellow-900/20': budgetTracking.monthly.percentage_used < 90,
                        'bg-red-50 border border-red-200 dark:bg-red-900/20': budgetTracking.monthly.percentage_used >= 90
                    }"
                >
                    <div class="flex">
                        <i 
                            :class="{
                                'fas fa-exclamation-triangle text-yellow-500': budgetTracking.monthly.percentage_used < 90,
                                'fas fa-exclamation-circle text-red-500': budgetTracking.monthly.percentage_used >= 90
                            }"
                            class="mt-1 mr-2"
                        ></i>
                        <div>
                            <p class="text-sm font-medium text-gray-900 dark:text-white">
                                {{ budgetTracking.monthly.percentage_used >= 90 ? 'Budget Exceeded!' : 'Budget Warning' }}
                            </p>
                            <p class="text-sm text-gray-600 dark:text-gray-400">
                                Consider reviewing upcoming purchases
                            </p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- ROI Analysis by Category -->
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-6">ROI by Category</h3>
                <div class="space-y-4">
                    <div 
                        v-for="category in roiAnalysis" 
                        :key="category.category"
                        class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-700 rounded-lg"
                    >
                        <div class="flex-1">
                            <h4 class="font-medium text-gray-900 dark:text-white">{{ category.category }}</h4>
                            <p class="text-sm text-gray-600 dark:text-gray-400">
                                {{ category.product_count }} products • {{ category.formatted_invested }}
                            </p>
                        </div>
                        <div class="text-right">
                            <span 
                                class="text-lg font-bold"
                                :class="{
                                    'text-emerald-600': category.avg_roi > 50,
                                    'text-blue-600': category.avg_roi > 25 && category.avg_roi <= 50,
                                    'text-yellow-600': category.avg_roi > 10 && category.avg_roi <= 25,
                                    'text-red-600': category.avg_roi <= 10
                                }"
                            >
                                {{ category.avg_roi }}%
                            </span>
                            <p class="text-xs text-gray-500">ROI</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Market Insights & Supplier Performance -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Market Insights -->
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-6">Market Insights</h3>
                <div v-if="marketInsights.length === 0" class="text-center py-8">
                    <i class="fas fa-chart-pie text-gray-400 text-3xl mb-2"></i>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Building insights from your data...</p>
                </div>
                <div v-else class="space-y-4">
                    <div 
                        v-for="insight in marketInsights" 
                        :key="insight.title"
                        class="p-4 border border-gray-200 dark:border-gray-600 rounded-lg"
                    >
                        <div class="flex items-start">
                            <div 
                                :class="{
                                    'bg-emerald-100 text-emerald-600 dark:bg-emerald-900/20': insight.type === 'opportunity',
                                    'bg-blue-100 text-blue-600 dark:bg-blue-900/20': insight.type === 'insight',
                                    'bg-yellow-100 text-yellow-600 dark:bg-yellow-900/20': insight.type === 'action'
                                }"
                                class="flex-shrink-0 w-8 h-8 rounded-full flex items-center justify-center mr-3 mt-1"
                            >
                                <i 
                                    :class="{
                                        'fas fa-lightbulb': insight.type === 'opportunity',
                                        'fas fa-info-circle': insight.type === 'insight',
                                        'fas fa-exclamation-triangle': insight.type === 'action'
                                    }"
                                    class="text-sm"
                                ></i>
                            </div>
                            <div class="flex-1">
                                <h4 class="font-semibold text-gray-900 dark:text-white mb-1">{{ insight.title }}</h4>
                                <p class="text-sm text-gray-600 dark:text-gray-400 mb-2">{{ insight.message }}</p>
                                <button class="text-xs text-indigo-600 hover:text-indigo-800 font-medium">
                                    {{ insight.action }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Supplier Performance -->
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-6">Top Suppliers</h3>
                <div class="space-y-4">
                    <div 
                        v-for="supplier in supplierPerformance" 
                        :key="supplier.id"
                        class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-700 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-600 transition-colors cursor-pointer"
                    >
                        <div class="flex-1">
                            <h4 class="font-medium text-gray-900 dark:text-white">{{ supplier.name }}</h4>
                            <div class="flex items-center mt-1">
                                <span class="text-sm text-gray-600 dark:text-gray-400 mr-3">
                                    {{ supplier.product_count }} products
                                </span>
                                <div class="flex items-center">
                                    <div class="flex text-yellow-400 mr-1">
                                        <i 
                                            v-for="star in 5" 
                                            :key="star"
                                            :class="star <= supplier.rating ? 'fas fa-star' : 'far fa-star'"
                                            class="text-xs"
                                        ></i>
                                    </div>
                                    <span class="text-xs text-gray-500">{{ supplier.rating }}/5</span>
                                </div>
                            </div>
                        </div>
                        <div class="text-right">
                            <p class="font-semibold text-gray-900 dark:text-white">{{ supplier.formatted_invested }}</p>
                            <p class="text-xs text-gray-500">{{ supplier.formatted_avg_cost }} avg</p>
                        </div>
                    </div>
                </div>
                
                <!-- Add New Supplier Button -->
                <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-600">
                    <button 
                        @click="$inertia.visit(route('admin.suppliers.index'))"
                        class="w-full px-4 py-2 border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-lg text-gray-600 dark:text-gray-400 hover:border-indigo-500 hover:text-indigo-600 transition-colors"
                    >
                        <i class="fas fa-plus mr-2"></i>
                        Add New Supplier
                    </button>
                </div>
            </div>
        </div>

        <!-- Recent Activity Feed -->
        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-6">Recent Sourcing Activity</h3>
            <div class="space-y-4 max-h-64 overflow-y-auto">
                <div 
                    v-for="activity in recentActivity" 
                    :key="activity.timestamp"
                    class="flex items-start space-x-3 p-3 hover:bg-gray-50 dark:hover:bg-gray-700 rounded-lg transition-colors"
                >
                    <div 
                        :class="{
                            'bg-emerald-100 text-emerald-600': activity.type === 'inventory',
                            'bg-blue-100 text-blue-600': activity.type === 'intake',
                            'bg-purple-100 text-purple-600': activity.type === 'supplier'
                        }"
                        class="flex-shrink-0 w-8 h-8 rounded-full flex items-center justify-center"
                    >
                        <i 
                            :class="{
                                'fas fa-box': activity.type === 'inventory',
                                'fas fa-inbox': activity.type === 'intake',
                                'fas fa-truck': activity.type === 'supplier'
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
/* Custom scrollbar for activity feed */
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