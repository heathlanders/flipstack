<script setup>
import { Link, usePage } from '@inertiajs/vue3'
import { computed } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import StatCard from '@/Components/Dashboard/StatCard.vue'
import ActivityTimeline from '@/Components/Dashboard/ActivityTimeline.vue'

const props = defineProps({
    metrics: Object,
    recentActivity: Array,
    alerts: Array,
    platformHealth: Object,
    financialSummary: Object,
    marketplaceConnections: Array,
})

const page = usePage()
const userName = computed(() => page.props.auth?.user?.name ?? 'there')

const todayDate = computed(() => {
    return new Date().toLocaleDateString('en-US', {
        weekday: 'long',
        year: 'numeric',
        month: 'long',
        day: 'numeric',
    })
})

const formatCurrency = (val) => {
    if (val === null || val === undefined) return '$0.00'
    return '$' + Number(val).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

const connectionStatusColor = (status) => {
    if (status === 'connected') return 'bg-emerald-500'
    if (status === 'warning') return 'bg-amber-500'
    return 'bg-gray-300 dark:bg-gray-600'
}

const connectionStatusText = (status) => {
    if (status === 'connected') return 'text-emerald-700 dark:text-emerald-400'
    if (status === 'warning') return 'text-amber-700 dark:text-amber-400'
    return 'text-gray-500 dark:text-gray-400'
}

const connectionStatusLabel = (status) => {
    if (status === 'connected') return 'Connected'
    if (status === 'warning') return 'Warning'
    return 'Disconnected'
}

const investedPercent = computed(() => {
    const invested = props.financialSummary?.total_invested ?? 0
    const estimated = props.financialSummary?.estimated_value ?? 0
    const total = invested + estimated
    if (total === 0) return 50
    return Math.round((invested / total) * 100)
})
</script>

<template>
    <AppLayout title="Dashboard">
        <template #header>
            <!-- Welcome Banner -->
            <div class="bg-gradient-to-r from-primary-600 to-primary-500 dark:from-primary-900 dark:to-gray-950 -mx-4 sm:-mx-6 lg:-mx-8 -mt-4 sm:-mt-6 px-4 sm:px-6 lg:px-8 py-6 sm:py-8">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-xl sm:text-2xl font-bold text-white">Welcome back, {{ userName }}</h2>
                        <p class="mt-1 text-sm text-primary-100 dark:text-primary-300/70">{{ todayDate }}</p>
                    </div>
                    <Link
                        :href="route('dashboard')"
                        method="get"
                        as="button"
                        preserve-state
                        class="inline-flex items-center gap-1.5 rounded-lg border border-white/30 bg-white/10 backdrop-blur-sm px-3 py-1.5 text-sm font-medium text-white shadow-sm hover:bg-white/20 transition-colors"
                    >
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182" />
                        </svg>
                        Refresh
                    </Link>
                </div>
            </div>
        </template>

        <div class="space-y-6">
            <!-- Alert Banner -->
            <div
                v-for="alert in alerts"
                :key="alert.title"
                :class="{
                    'bg-amber-50 dark:bg-amber-900/20 border-amber-200 dark:border-amber-800': alert.type === 'warning',
                    'bg-red-50 dark:bg-red-900/20 border-red-200 dark:border-red-800': alert.type === 'error',
                    'bg-blue-50 dark:bg-blue-900/20 border-blue-200 dark:border-blue-800': alert.type === 'info',
                }"
                class="border rounded-xl p-4 flex items-center justify-between"
            >
                <div class="flex items-center gap-3">
                    <svg
                        v-if="alert.type === 'warning'"
                        class="w-5 h-5 text-amber-500 flex-shrink-0"
                        fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"
                    >
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                    </svg>
                    <div>
                        <p class="text-sm font-medium text-gray-900 dark:text-white">{{ alert.title }}</p>
                        <p class="text-sm text-gray-600 dark:text-gray-400">{{ alert.message }}</p>
                    </div>
                </div>
                <Link
                    v-if="alert.action"
                    :href="alert.action"
                    class="text-sm font-medium text-blue-600 dark:text-blue-400 hover:underline whitespace-nowrap"
                >
                    {{ alert.action_label }}
                </Link>
            </div>

            <!-- Stat Cards Grid -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6">
                <StatCard
                    label="Inventory Value"
                    :value="financialSummary?.formatted?.total_invested ?? '$0.00'"
                    icon="dollar"
                    color="emerald"
                />
                <StatCard
                    label="Total Products"
                    :value="metrics?.inventory?.total_products ?? 0"
                    :trend="metrics?.inventory?.trend ? { value: metrics.inventory.trend, direction: metrics.inventory.trend > 0 ? 'up' : metrics.inventory.trend < 0 ? 'down' : 'neutral' } : null"
                    icon="cube"
                    color="blue"
                />
                <StatCard
                    label="Low Stock"
                    :value="metrics?.alerts?.low_stock_count ?? 0"
                    icon="exclamation"
                    :color="(metrics?.alerts?.low_stock_count ?? 0) > 10 ? 'red' : (metrics?.alerts?.low_stock_count ?? 0) > 5 ? 'amber' : 'emerald'"
                />
                <StatCard
                    label="Pending Intake"
                    :value="metrics?.alerts?.pending_intake ?? 0"
                    icon="inbox"
                    :color="(metrics?.alerts?.pending_intake ?? 0) > 20 ? 'red' : 'blue'"
                />
            </div>

            <!-- Two-Column Content -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Left Column (2/3) -->
                <div class="lg:col-span-2 space-y-6">
                    <!-- Financial Overview -->
                    <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 shadow-sm rounded-xl p-5">
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-white mb-4">Financial Overview</h3>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                            <div class="rounded-lg bg-gray-50 dark:bg-gray-700/50 p-4">
                                <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Total Invested</p>
                                <p class="mt-1 text-lg font-bold text-gray-900 dark:text-white tabular-nums">{{ financialSummary?.formatted?.total_invested ?? '$0.00' }}</p>
                            </div>
                            <div class="rounded-lg bg-gray-50 dark:bg-gray-700/50 p-4">
                                <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Estimated Value</p>
                                <p class="mt-1 text-lg font-bold text-gray-900 dark:text-white tabular-nums">{{ financialSummary?.formatted?.estimated_value ?? '$0.00' }}</p>
                            </div>
                            <div class="rounded-lg bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-800 p-4">
                                <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Potential Profit</p>
                                <div class="mt-1 flex items-baseline gap-2">
                                    <p class="text-lg font-bold text-emerald-600 dark:text-emerald-400 tabular-nums">{{ financialSummary?.formatted?.potential_profit ?? '$0.00' }}</p>
                                    <span class="text-xs font-medium text-emerald-600 dark:text-emerald-400">
                                        {{ financialSummary?.profit_margin ? financialSummary.profit_margin.toFixed(1) + '%' : '0.0%' }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Investment vs Value progress bar -->
                        <div class="mt-4 pt-4 border-t border-gray-100 dark:border-gray-700">
                            <div class="flex items-center justify-between text-xs text-gray-500 dark:text-gray-400 mb-2">
                                <span class="flex items-center gap-1.5">
                                    <span class="inline-block w-2.5 h-2.5 rounded-sm bg-blue-500"></span>
                                    Invested
                                </span>
                                <span class="flex items-center gap-1.5">
                                    <span class="inline-block w-2.5 h-2.5 rounded-sm bg-emerald-500"></span>
                                    Estimated Value
                                </span>
                            </div>
                            <div class="h-3 rounded-full bg-gray-100 dark:bg-gray-700 overflow-hidden flex">
                                <div
                                    class="bg-blue-500 transition-all duration-500 rounded-l-full"
                                    :style="{ width: investedPercent + '%' }"
                                ></div>
                                <div
                                    class="bg-emerald-500 transition-all duration-500 rounded-r-full"
                                    :style="{ width: (100 - investedPercent) + '%' }"
                                ></div>
                            </div>
                            <div class="flex items-center justify-between text-xs font-medium mt-1.5">
                                <span class="text-blue-600 dark:text-blue-400 tabular-nums">{{ financialSummary?.formatted?.total_invested ?? '$0.00' }}</span>
                                <span class="text-emerald-600 dark:text-emerald-400 tabular-nums">{{ financialSummary?.formatted?.estimated_value ?? '$0.00' }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Marketplace Connections - Card Grid -->
                    <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 shadow-sm rounded-xl p-5">
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-white mb-4">Marketplace Connections</h3>
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                            <div
                                v-for="conn in marketplaceConnections"
                                :key="conn.platform"
                                class="rounded-lg border border-gray-200 dark:border-gray-600 p-4 flex flex-col gap-3"
                            >
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-2.5">
                                        <!-- Platform icon -->
                                        <div class="w-8 h-8 rounded-lg bg-gray-100 dark:bg-gray-700 flex items-center justify-center flex-shrink-0">
                                            <svg v-if="conn.platform === 'ebay'" class="w-4 h-4 text-gray-600 dark:text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 21v-7.5a.75.75 0 01.75-.75h3a.75.75 0 01.75.75V21m-4.5 0H2.36m11.14 0H18m0 0h3.64m-1.39 0V9.349m-16.5 11.65V9.35m0 0a3.001 3.001 0 003.75-.615A2.993 2.993 0 009.75 9.75c.896 0 1.7-.393 2.25-1.016a2.993 2.993 0 002.25 1.016c.896 0 1.7-.393 2.25-1.016a3.001 3.001 0 003.75.614m-16.5 0a3.004 3.004 0 01-.621-4.72L4.318 3.44A1.5 1.5 0 015.378 3h13.243a1.5 1.5 0 011.06.44l1.19 1.189a3 3 0 01-.621 4.72m-13.5 8.65h3.75a.75.75 0 00.75-.75V13.5a.75.75 0 00-.75-.75H6.75a.75.75 0 00-.75.75v3.15c0 .415.336.75.75.75z" />
                                            </svg>
                                            <svg v-else-if="conn.platform === 'shopify'" class="w-4 h-4 text-gray-600 dark:text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 10.5V6a3.75 3.75 0 10-7.5 0v4.5m11.356-1.993l1.263 12c.07.665-.45 1.243-1.119 1.243H4.25a1.125 1.125 0 01-1.12-1.243l1.264-12A1.125 1.125 0 015.513 7.5h12.974c.576 0 1.059.435 1.119 1.007zM8.625 10.5a.375.375 0 11-.75 0 .375.375 0 01.75 0zm7.5 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z" />
                                            </svg>
                                            <svg v-else-if="conn.platform === 'amazon'" class="w-4 h-4 text-gray-600 dark:text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M12.75 3.03v.568c0 .334.148.65.405.864l1.068.89c.442.369.535 1.01.216 1.49l-.51.766a2.25 2.25 0 01-1.161.886l-.143.048a1.107 1.107 0 00-.57 1.664c.369.555.169 1.307-.427 1.592L9 13.125l.423 1.059a.956.956 0 01-1.652.928l-.679-.906a1.125 1.125 0 00-1.906.172L4.5 15.75l-.612.153M12.75 3.031a9 9 0 00-8.862 12.872M12.75 3.031a9 9 0 016.69 14.036m0 0l-.177-.529A2.25 2.25 0 0017.128 15H16.5l-.324-.324a1.453 1.453 0 00-2.328.377l-.036.073a1.586 1.586 0 01-.982.816l-.99.282c-.55.157-.894.702-.8 1.267l.073.438c.08.474.49.821.97.821.846 0 1.598.542 1.865 1.345l.215.643m5.276-3.67a9.012 9.012 0 01-5.276 3.67m0 0a9 9 0 01-10.275-4.835M15.75 9c0 .896-.393 1.7-1.016 2.25" />
                                            </svg>
                                            <svg v-else class="w-4 h-4 text-gray-600 dark:text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 011.242 7.244l-4.5 4.5a4.5 4.5 0 01-6.364-6.364l1.757-1.757m13.35-.622l1.757-1.757a4.5 4.5 0 00-6.364-6.364l-4.5 4.5a4.5 4.5 0 001.242 7.244" />
                                            </svg>
                                        </div>
                                        <span class="text-sm font-semibold text-gray-900 dark:text-white capitalize">{{ conn.platform }}</span>
                                    </div>
                                    <span
                                        :class="connectionStatusColor(conn.status)"
                                        class="inline-block h-2.5 w-2.5 rounded-full flex-shrink-0 ring-2 ring-white dark:ring-gray-800"
                                    ></span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span
                                        :class="connectionStatusText(conn.status)"
                                        class="text-xs font-medium"
                                    >
                                        {{ connectionStatusLabel(conn.status) }}
                                    </span>
                                    <span v-if="conn.last_synced" class="text-xs text-gray-400 dark:text-gray-500">
                                        {{ conn.last_synced }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Column (1/3) -->
                <div class="space-y-6">
                    <!-- Activity Timeline -->
                    <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 shadow-sm rounded-xl p-5">
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-white mb-4">Recent Activity</h3>
                        <div class="max-h-80 overflow-y-auto">
                            <ActivityTimeline :items="recentActivity ?? []" />
                        </div>
                    </div>

                    <!-- Quick Actions - Colored cards -->
                    <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 shadow-sm rounded-xl p-5">
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-white mb-4">Quick Actions</h3>
                        <div class="grid grid-cols-2 gap-3">
                            <Link
                                :href="route('inventory.reports.analytics')"
                                class="flex flex-col items-center gap-1.5 rounded-lg bg-blue-50 dark:bg-blue-500/10 border border-blue-100 dark:border-blue-800/50 p-3 text-center transition-colors hover:bg-blue-100 dark:hover:bg-blue-500/15"
                            >
                                <svg class="w-5 h-5 text-blue-600 dark:text-blue-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z" />
                                </svg>
                                <span class="text-xs font-medium text-blue-700 dark:text-blue-300">Reports</span>
                            </Link>
                            <Link
                                :href="route('inventory.products.index')"
                                class="flex flex-col items-center gap-1.5 rounded-lg bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-100 dark:border-emerald-800/50 p-3 text-center transition-colors hover:bg-emerald-100 dark:hover:bg-emerald-500/15"
                            >
                                <svg class="w-5 h-5 text-emerald-600 dark:text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                                </svg>
                                <span class="text-xs font-medium text-emerald-700 dark:text-emerald-300">Inventory</span>
                            </Link>
                            <Link
                                :href="route('inventory.products.create')"
                                class="flex flex-col items-center gap-1.5 rounded-lg bg-purple-50 dark:bg-purple-500/10 border border-purple-100 dark:border-purple-800/50 p-3 text-center transition-colors hover:bg-purple-100 dark:hover:bg-purple-500/15"
                            >
                                <svg class="w-5 h-5 text-purple-600 dark:text-purple-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                                </svg>
                                <span class="text-xs font-medium text-purple-700 dark:text-purple-300">Add Product</span>
                            </Link>
                            <Link
                                :href="route('organization.settings')"
                                class="flex flex-col items-center gap-1.5 rounded-lg bg-gray-50 dark:bg-gray-700/50 border border-gray-200 dark:border-gray-600 p-3 text-center transition-colors hover:bg-gray-100 dark:hover:bg-gray-700"
                            >
                                <svg class="w-5 h-5 text-gray-600 dark:text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.324.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.431l-1.003.827c-.293.24-.438.613-.431.992a6.759 6.759 0 010 .255c-.007.378.138.75.43.99l1.005.828c.424.35.534.954.26 1.43l-1.298 2.247a1.125 1.125 0 01-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.57 6.57 0 01-.22.128c-.331.183-.581.495-.644.869l-.213 1.28c-.09.543-.56.941-1.11.941h-2.594c-.55 0-1.02-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.431l1.004-.827c.292-.24.437-.613.43-.992a6.932 6.932 0 010-.255c.007-.378-.138-.75-.43-.99l-1.004-.828a1.125 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0 011.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.087.22-.128.332-.183.582-.495.644-.869l.214-1.281z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                </svg>
                                <span class="text-xs font-medium text-gray-700 dark:text-gray-300">Settings</span>
                            </Link>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
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
