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
    recentMessages: Array,
    returnRequests: Array,
})

const safeRoute = (name, params = {}) => {
    try {
        return route(name, params)
    } catch (e) {
        return '#'
    }
}
</script>

<template>
    <AppLayout title="Support Dashboard">
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-3xl font-bold text-gray-900 dark:text-white">Support Dashboard</h2>
                    <p class="text-gray-600 dark:text-gray-400 mt-1">
                        Welcome back, {{ user.name }}
                    </p>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-8">
                <!-- Metrics Row -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                    <MetricCard
                        title="Unread Messages"
                        :value="metrics.messages.unread"
                        icon="fas fa-envelope"
                        :color="metrics.messages.unread > 5 ? 'red' : 'blue'"
                        subtitle="Awaiting response"
                    />
                    <MetricCard
                        title="Messages Today"
                        :value="metrics.messages.today"
                        icon="fas fa-comments"
                        color="emerald"
                        subtitle="All channels"
                    />
                    <MetricCard
                        title="Avg Response Time"
                        :value="`${metrics.messages.avg_response_hours}h`"
                        icon="fas fa-clock"
                        :color="metrics.messages.avg_response_hours > 24 ? 'red' : 'green'"
                        subtitle="Last 7 days"
                    />
                    <MetricCard
                        title="Open Returns"
                        :value="metrics.returns.open"
                        icon="fas fa-undo"
                        :color="metrics.returns.open > 3 ? 'amber' : 'green'"
                        subtitle="Pending resolution"
                    />
                </div>

                <!-- Alerts -->
                <div v-if="alerts.length > 0" class="space-y-3">
                    <div
                        v-for="(alert, idx) in alerts"
                        :key="idx"
                        class="flex items-center justify-between p-4 rounded-xl border"
                        :class="{
                            'bg-red-50 border-red-200 dark:bg-red-900/20 dark:border-red-800': alert.type === 'error',
                            'bg-amber-50 border-amber-200 dark:bg-amber-900/20 dark:border-amber-800': alert.type === 'warning',
                            'bg-blue-50 border-blue-200 dark:bg-blue-900/20 dark:border-blue-800': alert.type === 'info',
                        }"
                    >
                        <div>
                            <p class="font-medium text-gray-900 dark:text-white">{{ alert.title }}</p>
                            <p class="text-sm text-gray-600 dark:text-gray-400">{{ alert.message }}</p>
                        </div>
                        <a v-if="alert.action" :href="alert.action" class="text-sm font-medium text-blue-600 hover:text-blue-800 dark:text-blue-400">
                            {{ alert.action_label }}
                        </a>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                    <!-- Recent Messages -->
                    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Recent Messages</h3>
                            <a :href="safeRoute('messages.index')" class="text-sm text-blue-600 hover:text-blue-800 dark:text-blue-400">
                                View All
                            </a>
                        </div>
                        <div v-if="recentMessages.length === 0" class="text-center py-8 text-gray-500 dark:text-gray-400">
                            No recent messages
                        </div>
                        <div v-else class="divide-y divide-gray-100 dark:divide-gray-700">
                            <div v-for="msg in recentMessages" :key="msg.id" class="py-3 flex items-start gap-3">
                                <div class="flex-shrink-0 mt-1">
                                    <span
                                        class="inline-block w-2 h-2 rounded-full"
                                        :class="msg.is_read ? 'bg-gray-300' : 'bg-blue-500'"
                                    ></span>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-medium text-gray-900 dark:text-white truncate">
                                        {{ msg.sender }}
                                    </p>
                                    <p class="text-sm text-gray-600 dark:text-gray-400 truncate">
                                        {{ msg.subject || '(No subject)' }}
                                    </p>
                                    <div class="flex items-center gap-2 mt-1">
                                        <span class="text-xs px-2 py-0.5 rounded-full bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-400">
                                            {{ msg.marketplace }}
                                        </span>
                                        <span class="text-xs text-gray-400">
                                            {{ new Date(msg.created_at).toLocaleDateString() }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Return Requests -->
                    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Open Returns</h3>
                            <a :href="safeRoute('fulfillment.returns.index')" class="text-sm text-blue-600 hover:text-blue-800 dark:text-blue-400">
                                View All
                            </a>
                        </div>
                        <div v-if="returnRequests.length === 0" class="text-center py-8 text-gray-500 dark:text-gray-400">
                            No open return requests
                        </div>
                        <div v-else class="divide-y divide-gray-100 dark:divide-gray-700">
                            <div v-for="ret in returnRequests" :key="ret.id" class="py-3">
                                <div class="flex items-center justify-between">
                                    <p class="text-sm font-medium text-gray-900 dark:text-white">
                                        Order #{{ ret.order_id }}
                                    </p>
                                    <span
                                        class="text-xs px-2 py-0.5 rounded-full"
                                        :class="{
                                            'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400': ret.status === 'requested',
                                            'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400': ret.status === 'approved',
                                            'bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-400': ret.status === 'in_transit',
                                        }"
                                    >
                                        {{ ret.status }}
                                    </span>
                                </div>
                                <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                                    {{ ret.buyer }} - {{ ret.reason }}
                                </p>
                                <div class="flex items-center gap-2 mt-1">
                                    <span class="text-xs px-2 py-0.5 rounded-full bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-400">
                                        {{ ret.marketplace }}
                                    </span>
                                    <span class="text-xs text-gray-400">
                                        {{ new Date(ret.created_at).toLocaleDateString() }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Activity -->
                <div v-if="recentActivity.length > 0" class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Recent Activity</h3>
                    <div class="divide-y divide-gray-100 dark:divide-gray-700">
                        <div v-for="(activity, idx) in recentActivity" :key="idx" class="py-3 flex items-center gap-3">
                            <div class="flex-shrink-0 w-8 h-8 rounded-full bg-gray-100 dark:bg-gray-700 flex items-center justify-center">
                                <i
                                    class="text-xs"
                                    :class="{
                                        'fas fa-inbox text-blue-500': activity.type === 'intake',
                                        'fas fa-box text-emerald-500': activity.type === 'inventory',
                                    }"
                                ></i>
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="text-sm text-gray-900 dark:text-white">{{ activity.title }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ activity.description }}</p>
                            </div>
                            <span class="text-xs text-gray-400">
                                {{ new Date(activity.timestamp).toLocaleDateString() }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
