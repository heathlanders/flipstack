<script setup>
import { computed, watch } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import MetricCard from '../../Components/Widgets/MetricCard.vue'
import { useDashboardRefresh } from '@/Composables/useDashboardRefresh'

// Original props from controller
const props = defineProps({
    metrics: Object,
    recentActivity: Array,
    alerts: Array,
    teamPerformance: Array,
    platformHealth: Object,
    financialSummary: Object
})

// Auto-refresh functionality
const { 
    liveData, 
    isRefreshing, 
    lastRefresh, 
    refreshEnabled, 
    refreshData, 
    toggleRefresh 
} = useDashboardRefresh('owner', 60000) // Refresh every 60 seconds

// Merge live data with initial props
const currentMetrics = computed(() => ({
    ...props.metrics,
    alerts: {
        ...props.metrics?.alerts,
        low_stock_count: liveData.value.businessMetrics?.lowStockAlerts || props.metrics?.alerts?.low_stock_count,
        pending_intake: liveData.value.businessMetrics?.pendingIntake || props.metrics?.alerts?.pending_intake
    }
}))

const currentPlatformHealth = computed(() => ({
    ...props.platformHealth,
    ...liveData.value.platformHealth
}))

const currentRecentActivity = computed(() => 
    liveData.value.recentActivity?.length > 0 
        ? liveData.value.recentActivity 
        : props.recentActivity
)

// Watch for critical alerts
watch(() => liveData.value.alerts, (newAlerts) => {
    if (newAlerts.critical?.length > 0) {
        // Show browser notification for critical alerts
        if (Notification.permission === 'granted') {
            new Notification('Critical Alert', {
                body: newAlerts.critical[0],
                icon: '/favicon.ico'
            })
        }
    }
}, { deep: true })

// Request notification permission on mount
if (Notification.permission === 'default') {
    Notification.requestPermission()
}
</script>

<template>
    <AppLayout title="Owner Dashboard">
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-3xl font-bold text-gray-900 dark:text-white">Executive Dashboard</h2>
                    <p class="text-gray-600 dark:text-gray-400 mt-1">
                        Complete business overview and metrics
                    </p>
                </div>
                
                <!-- Auto-refresh controls -->
                <div class="flex items-center space-x-4">
                    <!-- Last refresh indicator -->
                    <div class="flex items-center space-x-2 text-sm text-gray-500">
                        <div class="flex items-center space-x-1">
                            <i :class="[
                                'fas',
                                isRefreshing ? 'fa-spinner fa-spin' : 'fa-clock',
                                isRefreshing ? 'text-blue-500' : 'text-gray-400'
                            ]"></i>
                            <span>{{ isRefreshing ? 'Refreshing...' : `Updated ${lastRefresh.toLocaleTimeString()}` }}</span>
                        </div>
                    </div>
                    
                    <!-- Manual refresh button -->
                    <button @click="refreshData" 
                            :disabled="isRefreshing"
                            class="px-3 py-1 bg-blue-100 hover:bg-blue-200 text-blue-700 rounded-lg text-sm transition-colors disabled:opacity-50">
                        <i class="fas fa-refresh mr-1"></i>
                        Refresh Now
                    </button>
                    
                    <!-- Toggle auto-refresh -->
                    <button @click="toggleRefresh" 
                            :class="[
                                'px-3 py-1 rounded-lg text-sm transition-colors',
                                refreshEnabled 
                                    ? 'bg-emerald-100 hover:bg-emerald-200 text-emerald-700' 
                                    : 'bg-gray-100 hover:bg-gray-200 text-gray-700'
                            ]">
                        <i :class="['fas', refreshEnabled ? 'fa-pause' : 'fa-play', 'mr-1']"></i>
                        {{ refreshEnabled ? 'Auto-Refresh ON' : 'Auto-Refresh OFF' }}
                    </button>
                </div>
            </div>
        </template>

        <div class="space-y-8">
            <!-- Critical Alerts Banner -->
            <div v-if="liveData.alerts?.critical?.length > 0" 
                 class="bg-red-50 border border-red-200 rounded-lg p-4">
                <div class="flex items-start">
                    <i class="fas fa-exclamation-triangle text-red-500 mt-1 mr-3"></i>
                    <div>
                        <h4 class="text-red-800 font-semibold">Critical Alerts</h4>
                        <ul class="text-red-700 text-sm mt-1">
                            <li v-for="alert in liveData.alerts.critical" :key="alert">{{ alert }}</li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Top Metrics Row - With Live Data -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <!-- Inventory Value -->
                <MetricCard 
                    title="Inventory Value" 
                    :value="financialSummary.formatted.total_invested"
                    icon="fas fa-dollar-sign"
                    color="emerald"
                    subtitle="Total investment"
                />
                
                <!-- Products This Week -->
                <MetricCard 
                    title="Products This Week" 
                    :value="currentMetrics.inventory.products_this_week"
                    :change="currentMetrics.inventory.trend"
                    :trend="currentMetrics.inventory.trend > 0 ? 'up' : currentMetrics.inventory.trend < 0 ? 'down' : 'neutral'"
                    icon="fas fa-boxes"
                    color="blue"
                    :subtitle="`vs ${currentMetrics.inventory.products_last_week} last week`"
                />
                
                <!-- Low Stock Alerts - LIVE DATA -->
                <MetricCard 
                    title="Low Stock Items" 
                    :value="liveData.businessMetrics?.lowStockAlerts || currentMetrics.alerts.low_stock_count"
                    :trend="(liveData.businessMetrics?.lowStockAlerts || currentMetrics.alerts.low_stock_count) > 10 ? 'down' : 'neutral'"
                    icon="fas fa-exclamation-triangle"
                    :color="(liveData.businessMetrics?.lowStockAlerts || currentMetrics.alerts.low_stock_count) > 10 ? 'red' : 
                           (liveData.businessMetrics?.lowStockAlerts || currentMetrics.alerts.low_stock_count) > 5 ? 'yellow' : 'green'"
                    subtitle="Need restocking"
                    :pulse="(liveData.businessMetrics?.lowStockAlerts || 0) > (currentMetrics.alerts.low_stock_count || 0)"
                />
                
                <!-- Pending Intake - LIVE DATA -->
                <MetricCard 
                    title="Pending Intake" 
                    :value="liveData.businessMetrics?.pendingIntake || currentMetrics.alerts.pending_intake"
                    :trend="(liveData.businessMetrics?.pendingIntake || currentMetrics.alerts.pending_intake) > 20 ? 'down' : 'neutral'"
                    icon="fas fa-inbox"
                    :color="(liveData.businessMetrics?.pendingIntake || currentMetrics.alerts.pending_intake) > 20 ? 'red' : 
                           (liveData.businessMetrics?.pendingIntake || currentMetrics.alerts.pending_intake) > 10 ? 'yellow' : 'blue'"
                    subtitle="Items to process"
                    :pulse="(liveData.businessMetrics?.pendingIntake || 0) > (currentMetrics.alerts.pending_intake || 0)"
                />
            </div>

            <!-- Financial Overview & Platform Health -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Financial Summary -->
                <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-6">Financial Overview</h3>
                    
                    <div class="space-y-4">
                        <!-- Total Invested -->
                        <div class="flex items-center justify-between p-4 bg-gray-50 dark:bg-gray-700 rounded-xl">
                            <div>
                                <p class="text-sm text-gray-600 dark:text-gray-400">Total Invested</p>
                                <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ financialSummary.formatted.total_invested }}</p>
                            </div>
                            <div class="text-blue-500">
                                <i class="fas fa-money-bill-wave text-2xl"></i>
                            </div>
                        </div>
                        
                        <!-- Estimated Value -->
                        <div class="flex items-center justify-between p-4 bg-gray-50 dark:bg-gray-700 rounded-xl">
                            <div>
                                <p class="text-sm text-gray-600 dark:text-gray-400">Estimated Value</p>
                                <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ financialSummary.formatted.estimated_value }}</p>
                            </div>
                            <div class="text-emerald-500">
                                <i class="fas fa-chart-line text-2xl"></i>
                            </div>
                        </div>
                        
                        <!-- Potential Profit -->
                        <div class="flex items-center justify-between p-4 bg-gradient-to-r from-emerald-50 to-blue-50 dark:from-emerald-900/20 dark:to-blue-900/20 rounded-xl border border-emerald-200 dark:border-emerald-800">
                            <div>
                                <p class="text-sm text-gray-600 dark:text-gray-400">Potential Profit</p>
                                <div class="flex items-baseline space-x-2">
                                    <p class="text-2xl font-bold text-emerald-600 dark:text-emerald-400">{{ financialSummary.formatted.potential_profit }}</p>
                                    <span class="text-sm font-medium text-emerald-600 dark:text-emerald-400">
                                        ({{ financialSummary.profit_margin.toFixed(1) }}%)
                                    </span>
                                </div>
                            </div>
                            <div class="text-emerald-500">
                                <i class="fas fa-trophy text-2xl"></i>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Platform Health - LIVE DATA -->
                <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                    <div class="flex items-center justify-between mb-6">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Platform Health</h3>
                        <div v-if="isRefreshing" class="flex items-center space-x-2 text-sm text-gray-500">
                            <i class="fas fa-spinner fa-spin text-blue-500"></i>
                            <span>Checking...</span>
                        </div>
                    </div>
                    
                    <div class="space-y-4">
                        <!-- Platform status with live updates -->
                        <div v-for="(platform, key) in currentPlatformHealth" :key="key"
                             class="flex items-center justify-between p-4 bg-gray-50 dark:bg-gray-700 rounded-xl">
                            <div class="flex items-center space-x-3">
                                <div class="flex-shrink-0">
                                    <span 
                                        :class="{
                                            'bg-emerald-500': platform.status === 'connected',
                                            'bg-red-500': platform.status === 'disconnected',
                                            'bg-yellow-500': platform.status === 'warning'
                                        }"
                                        class="w-3 h-3 rounded-full"
                                    ></span>
                                </div>
                                <div>
                                    <h4 class="font-medium text-gray-900 dark:text-white capitalize">{{ key }}</h4>
                                    <p class="text-sm text-gray-600 dark:text-gray-400">
                                        {{ platform.active_listings || 0 }} active listings
                                    </p>
                                </div>
                            </div>
                            <div class="text-right">
                                <p class="text-sm text-gray-500 capitalize">{{ platform.status }}</p>
                                <p class="text-xs text-gray-400">{{ platform.rate_limit_status || 'normal' }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Team Performance & Recent Activity -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Team Performance -->
                <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-6">Team Performance</h3>
                    
                    <div v-if="teamPerformance.length === 0" class="text-center py-8">
                        <i class="fas fa-users text-gray-400 text-3xl mb-2"></i>
                        <p class="text-sm text-gray-500 dark:text-gray-400">No team data available</p>
                    </div>
                    
                    <div v-else class="space-y-4">
                        <div 
                            v-for="team in teamPerformance" 
                            :key="team.name"
                            class="flex items-center justify-between p-4 bg-gray-50 dark:bg-gray-700 rounded-xl"
                        >
                            <div class="flex-1">
                                <h4 class="font-medium text-gray-900 dark:text-white">{{ team.name }}</h4>
                                <div class="flex items-center mt-1">
                                    <span class="text-sm text-gray-600 dark:text-gray-400 mr-3">{{ team.department }}</span>
                                    <span class="text-sm text-gray-500">{{ team.member_count }} members</span>
                                </div>
                            </div>
                            <div class="text-right">
                                <div class="flex items-center justify-end mb-1">
                                    <span class="text-lg font-bold text-gray-900 dark:text-white mr-2">{{ team.productivity_score }}</span>
                                    <span class="text-xs text-gray-500">/100</span>
                                </div>
                                <div class="w-16 bg-gray-200 rounded-full h-2">
                                    <div 
                                        class="h-2 rounded-full transition-all duration-500"
                                        :class="{
                                            'bg-emerald-500': team.productivity_score >= 80,
                                            'bg-blue-500': team.productivity_score >= 60 && team.productivity_score < 80,
                                            'bg-yellow-500': team.productivity_score >= 40 && team.productivity_score < 60,
                                            'bg-red-500': team.productivity_score < 40
                                        }"
                                        :style="`width: ${team.productivity_score}%`"
                                    ></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Recent Activity - LIVE DATA -->
                <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                    <div class="flex items-center justify-between mb-6">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Recent Activity</h3>
                        <div v-if="currentRecentActivity.length > 0" class="text-xs text-emerald-600 bg-emerald-50 px-2 py-1 rounded">
                            Live Updates
                        </div>
                    </div>
                    
                    <div class="space-y-4 max-h-64 overflow-y-auto">
                        <div 
                            v-for="activity in currentRecentActivity" 
                            :key="`${activity.timestamp}-${activity.title}`"
                            class="flex items-start space-x-3 p-3 hover:bg-gray-50 dark:hover:bg-gray-700 rounded-lg transition-colors"
                        >
                            <div 
                                :class="{
                                    'bg-emerald-100 text-emerald-600': activity.type === 'inventory',
                                    'bg-blue-100 text-blue-600': activity.type === 'intake',
                                    'bg-purple-100 text-purple-600': activity.type === 'team',
                                    'bg-orange-100 text-orange-600': activity.type === 'platform'
                                }"
                                class="flex-shrink-0 w-8 h-8 rounded-full flex items-center justify-center"
                            >
                                <i 
                                    :class="{
                                        'fas fa-box': activity.type === 'inventory',
                                        'fas fa-inbox': activity.type === 'intake',
                                        'fas fa-users': activity.type === 'team',
                                        'fas fa-plug': activity.type === 'platform'
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

            <!-- Quick Actions -->
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-6">Executive Actions</h3>
                
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <Link :href="route('inventory.reports.analytics')" 
                          class="p-4 text-center bg-blue-50 dark:bg-blue-900/20 hover:bg-blue-100 dark:hover:bg-blue-900/30 rounded-xl transition-colors group">
                        <i class="fas fa-chart-bar text-blue-600 text-xl mb-2 group-hover:scale-110 transition-transform"></i>
                        <p class="text-sm font-medium text-gray-900 dark:text-white">View Reports</p>
                    </Link>
                    
                    <Link :href="route('inventory.products.index')" 
                          class="p-4 text-center bg-emerald-50 dark:bg-emerald-900/20 hover:bg-emerald-100 dark:hover:bg-emerald-900/30 rounded-xl transition-colors group">
                        <i class="fas fa-boxes text-emerald-600 text-xl mb-2 group-hover:scale-110 transition-transform"></i>
                        <p class="text-sm font-medium text-gray-900 dark:text-white">Inventory</p>
                    </Link>
                    
                    <Link :href="route('inventory.products.create')" 
                          class="p-4 text-center bg-purple-50 dark:bg-purple-900/20 hover:bg-purple-100 dark:hover:bg-purple-900/30 rounded-xl transition-colors group">
                        <i class="fas fa-plus text-purple-600 text-xl mb-2 group-hover:scale-110 transition-transform"></i>
                        <p class="text-sm font-medium text-gray-900 dark:text-white">Add Products</p>
                    </Link>
                    
                    <Link :href="route('organization.settings')"
                          class="p-4 text-center bg-gray-50 dark:bg-gray-700 hover:bg-gray-100 dark:hover:bg-gray-600 rounded-xl transition-colors group">
                        <i class="fas fa-cog text-gray-600 dark:text-gray-400 text-xl mb-2 group-hover:scale-110 transition-transform"></i>
                        <p class="text-sm font-medium text-gray-900 dark:text-white">Settings</p>
                    </Link>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
/* Pulse animation for updated metrics */
.pulse {
    animation: pulse-glow 2s infinite;
}

@keyframes pulse-glow {
    0%, 100% {
        box-shadow: 0 0 5px rgba(59, 130, 246, 0.5);
    }
    50% {
        box-shadow: 0 0 20px rgba(59, 130, 246, 0.8);
    }
}

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