<script setup>
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import KPICard from '@/Components/Analytics/KPICard.vue';

const props = defineProps({
    stats: { type: Object, default: () => ({}) },
    recentWorkOrders: { type: Array, default: () => [] },
    topTemplates: { type: Array, default: () => [] },
    recentComponents: { type: Array, default: () => [] },
});

const roiColor = computed(() => (props.stats.roi_percent ?? 0) >= 0 ? 'emerald' : 'red');
const roiTrend = computed(() => (props.stats.roi_percent ?? 0) >= 0 ? 'up' : 'down');

const statusConfig = {
    draft: { label: 'Draft', bg: 'bg-blue-100 dark:bg-blue-900/30', text: 'text-blue-700 dark:text-blue-400' },
    in_progress: { label: 'In Progress', bg: 'bg-amber-100 dark:bg-amber-900/30', text: 'text-amber-700 dark:text-amber-400' },
    completed: { label: 'Completed', bg: 'bg-emerald-100 dark:bg-emerald-900/30', text: 'text-emerald-700 dark:text-emerald-400' },
    cancelled: { label: 'Cancelled', bg: 'bg-red-100 dark:bg-red-900/30', text: 'text-red-700 dark:text-red-400' },
};

function getStatusBadge(status) {
    return statusConfig[status] || statusConfig.draft;
}

function formatCurrency(value) {
    return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(value || 0);
}

function timeAgo(dateString) {
    if (!dateString) return '';
    const date = new Date(dateString);
    const now = new Date();
    const seconds = Math.floor((now - date) / 1000);

    if (seconds < 60) return 'just now';
    const minutes = Math.floor(seconds / 60);
    if (minutes < 60) return `${minutes}m ago`;
    const hours = Math.floor(minutes / 60);
    if (hours < 24) return `${hours}h ago`;
    const days = Math.floor(hours / 24);
    if (days < 30) return `${days}d ago`;
    return date.toLocaleDateString();
}

function allocationLabel(method) {
    const labels = {
        equal: 'Equal',
        by_weight: 'By Weight',
        by_value: 'By Value',
        manual: 'Manual',
        proportional: 'Proportional',
    };
    return labels[method] || method;
}

function extractionPercent(progress) {
    if (!progress || !progress.total || progress.total === 0) return 0;
    return Math.round(((progress.extracted ?? 0) / progress.total) * 100);
}
</script>

<template>
    <Head title="Part-Out Hub" />
    <AppLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                Part-Out Hub
            </h2>
        </template>

        <div class="py-6">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

                <!-- Header -->
                <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between mb-6 gap-4">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Part-Out Hub</h1>
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                            Disassemble complete units into components for higher-margin resale
                        </p>
                    </div>
                    <div class="flex items-center gap-3">
                        <Link
                            :href="route('inventory.part-out.templates.create')"
                            class="inline-flex items-center px-4 py-2 bg-white dark:bg-gray-700 border border-indigo-300 dark:border-indigo-600 rounded-md font-semibold text-xs text-indigo-700 dark:text-indigo-300 uppercase tracking-widest hover:bg-indigo-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150"
                        >
                            <i class="fas fa-plus-circle mr-2"></i>
                            New Template
                        </Link>
                        <Link
                            :href="route('inventory.part-out.work-orders.create')"
                            class="inline-flex items-center px-4 py-2 bg-teal-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-teal-700 focus:bg-teal-700 active:bg-teal-900 focus:outline-none focus:ring-2 focus:ring-teal-500 focus:ring-offset-2 transition ease-in-out duration-150"
                        >
                            <i class="fas fa-play-circle mr-2"></i>
                            New Work Order
                        </Link>
                    </div>
                </div>

                <!-- KPI Cards -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mb-8">
                    <KPICard
                        title="Templates"
                        :value="stats.templates_total ?? 0"
                        :subtitle="`${stats.templates_active ?? 0} active`"
                        icon="archive"
                        color="blue"
                    />
                    <KPICard
                        title="Work Orders"
                        :value="stats.work_orders_total ?? 0"
                        :subtitle="`${stats.work_orders_in_progress ?? 0} in progress`"
                        icon="cube"
                        color="indigo"
                    />
                    <KPICard
                        title="Components Created"
                        :value="stats.components_created ?? 0"
                        icon="chart-bar"
                        color="cyan"
                    />
                    <KPICard
                        title="In Progress"
                        :value="stats.work_orders_in_progress ?? 0"
                        icon="exclamation-triangle"
                        color="amber"
                        :alertLevel="(stats.work_orders_in_progress ?? 0) > 0 ? 'warning' : 'normal'"
                    />
                    <KPICard
                        title="Completed"
                        :value="stats.work_orders_completed ?? 0"
                        icon="chart-bar"
                        color="emerald"
                    />
                    <KPICard
                        title="Part-Out ROI"
                        :value="`${stats.roi_percent ?? 0}%`"
                        icon="trending-up"
                        :color="roiColor"
                        :trend="roiTrend"
                    />
                </div>

                <!-- Section 1: Recent Work Orders + Quick Actions -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">

                    <!-- Recent Work Orders -->
                    <div class="lg:col-span-2 bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700">
                        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Recent Work Orders</h3>
                            <Link
                                :href="route('inventory.part-out.work-orders.index')"
                                class="text-sm font-medium text-indigo-600 dark:text-indigo-400 hover:text-indigo-800 dark:hover:text-indigo-300"
                            >
                                View All
                            </Link>
                        </div>

                        <div v-if="recentWorkOrders.length > 0" class="divide-y divide-gray-100 dark:divide-gray-700">
                            <Link
                                v-for="wo in recentWorkOrders"
                                :key="wo.id"
                                :href="route('inventory.part-out.work-orders.show', wo.id)"
                                class="block px-6 py-4 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors"
                            >
                                <div class="flex items-start justify-between gap-4">
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center gap-2 mb-1">
                                            <span
                                                class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium"
                                                :class="[getStatusBadge(wo.status).bg, getStatusBadge(wo.status).text]"
                                            >
                                                {{ getStatusBadge(wo.status).label }}
                                            </span>
                                            <span class="text-sm font-bold text-gray-900 dark:text-white">
                                                {{ wo.work_order_number }}
                                            </span>
                                        </div>
                                        <p class="text-sm text-gray-500 dark:text-gray-400 truncate">
                                            {{ wo.source_product?.name }}
                                            <span v-if="wo.source_product?.sku" class="text-xs text-gray-400 dark:text-gray-500 ml-1">
                                                ({{ wo.source_product.sku }})
                                            </span>
                                        </p>
                                        <div v-if="wo.template_name" class="text-xs text-gray-400 dark:text-gray-500 mt-0.5">
                                            Template: {{ wo.template_name }}
                                        </div>

                                        <!-- Progress bar for in-progress WOs -->
                                        <div v-if="wo.status === 'in_progress' && wo.extraction_progress" class="mt-2">
                                            <div class="flex items-center gap-2">
                                                <div class="flex-1 bg-gray-200 dark:bg-gray-700 rounded-full h-1.5">
                                                    <div
                                                        class="bg-amber-500 h-1.5 rounded-full transition-all"
                                                        :style="{ width: extractionPercent(wo.extraction_progress) + '%' }"
                                                    ></div>
                                                </div>
                                                <span class="text-xs text-gray-500 dark:text-gray-400 whitespace-nowrap">
                                                    {{ wo.extraction_progress.extracted ?? 0 }}/{{ wo.extraction_progress.total ?? 0 }} items
                                                </span>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="flex-shrink-0 text-right">
                                        <span v-if="wo.items_count" class="text-xs text-gray-500 dark:text-gray-400">
                                            {{ wo.items_count }} items
                                        </span>
                                        <p class="text-xs text-gray-400 dark:text-gray-500 mt-0.5">
                                            {{ timeAgo(wo.created_at) }}
                                        </p>
                                    </div>
                                </div>
                            </Link>
                        </div>

                        <div v-else class="px-6 py-12 text-center">
                            <i class="fas fa-clipboard-list text-4xl text-gray-300 dark:text-gray-600 mb-3"></i>
                            <p class="text-sm text-gray-500 dark:text-gray-400">
                                No work orders yet. Create your first work order to start parting out products.
                            </p>
                            <Link
                                :href="route('inventory.part-out.work-orders.create')"
                                class="inline-flex items-center mt-4 px-4 py-2 bg-teal-600 rounded-md text-xs font-semibold text-white uppercase tracking-widest hover:bg-teal-700 transition"
                            >
                                Create Work Order
                            </Link>
                        </div>
                    </div>

                    <!-- Quick Actions -->
                    <div class="lg:col-span-1 bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700">
                        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Quick Actions</h3>
                        </div>
                        <div class="p-4 space-y-3">
                            <Link
                                :href="route('inventory.part-out.templates.create')"
                                class="flex items-center gap-3 p-3 rounded-lg border border-gray-200 dark:border-gray-700 hover:border-indigo-300 dark:hover:border-indigo-600 hover:bg-indigo-50 dark:hover:bg-indigo-900/10 transition-all group"
                            >
                                <div class="flex-shrink-0 w-9 h-9 rounded-lg bg-indigo-50 dark:bg-indigo-900/20 flex items-center justify-center">
                                    <i class="fas fa-plus-circle text-indigo-600 dark:text-indigo-400"></i>
                                </div>
                                <span class="text-sm font-medium text-gray-700 dark:text-gray-300 group-hover:text-indigo-700 dark:group-hover:text-indigo-400">
                                    Create Template
                                </span>
                            </Link>

                            <Link
                                :href="route('inventory.part-out.work-orders.create')"
                                class="flex items-center gap-3 p-3 rounded-lg border border-gray-200 dark:border-gray-700 hover:border-teal-300 dark:hover:border-teal-600 hover:bg-teal-50 dark:hover:bg-teal-900/10 transition-all group"
                            >
                                <div class="flex-shrink-0 w-9 h-9 rounded-lg bg-teal-50 dark:bg-teal-900/20 flex items-center justify-center">
                                    <i class="fas fa-play-circle text-teal-600 dark:text-teal-400"></i>
                                </div>
                                <span class="text-sm font-medium text-gray-700 dark:text-gray-300 group-hover:text-teal-700 dark:group-hover:text-teal-400">
                                    Create Work Order
                                </span>
                            </Link>

                            <Link
                                :href="route('inventory.part-out.templates.index')"
                                class="flex items-center gap-3 p-3 rounded-lg border border-gray-200 dark:border-gray-700 hover:border-gray-300 dark:hover:border-gray-500 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-all group"
                            >
                                <div class="flex-shrink-0 w-9 h-9 rounded-lg bg-gray-100 dark:bg-gray-700 flex items-center justify-center">
                                    <i class="fas fa-list text-gray-600 dark:text-gray-400"></i>
                                </div>
                                <span class="text-sm font-medium text-gray-700 dark:text-gray-300 group-hover:text-gray-900 dark:group-hover:text-gray-100">
                                    View Templates
                                </span>
                            </Link>

                            <Link
                                :href="route('inventory.part-out.work-orders.index')"
                                class="flex items-center gap-3 p-3 rounded-lg border border-gray-200 dark:border-gray-700 hover:border-gray-300 dark:hover:border-gray-500 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-all group"
                            >
                                <div class="flex-shrink-0 w-9 h-9 rounded-lg bg-gray-100 dark:bg-gray-700 flex items-center justify-center">
                                    <i class="fas fa-clipboard-list text-gray-600 dark:text-gray-400"></i>
                                </div>
                                <span class="text-sm font-medium text-gray-700 dark:text-gray-300 group-hover:text-gray-900 dark:group-hover:text-gray-100">
                                    View Work Orders
                                </span>
                            </Link>

                            <Link
                                :href="route('inventory.products.index')"
                                class="flex items-center gap-3 p-3 rounded-lg border border-gray-200 dark:border-gray-700 hover:border-gray-300 dark:hover:border-gray-500 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-all group"
                            >
                                <div class="flex-shrink-0 w-9 h-9 rounded-lg bg-gray-100 dark:bg-gray-700 flex items-center justify-center">
                                    <i class="fas fa-boxes text-gray-600 dark:text-gray-400"></i>
                                </div>
                                <div class="flex-1 flex items-center justify-between">
                                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300 group-hover:text-gray-900 dark:group-hover:text-gray-100">
                                        Products Ready for Part-Out
                                    </span>
                                    <span
                                        v-if="(stats.products_with_components ?? 0) > 0"
                                        class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-indigo-100 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-400"
                                    >
                                        {{ stats.products_with_components }}
                                    </span>
                                </div>
                            </Link>
                        </div>
                    </div>
                </div>

                <!-- Section 2: Top Templates + Recent Components -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

                    <!-- Top Templates -->
                    <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700">
                        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Most Used Templates</h3>
                            <Link
                                :href="route('inventory.part-out.templates.index')"
                                class="text-sm font-medium text-indigo-600 dark:text-indigo-400 hover:text-indigo-800 dark:hover:text-indigo-300"
                            >
                                View All
                            </Link>
                        </div>

                        <div v-if="topTemplates.length > 0">
                            <table class="w-full">
                                <thead>
                                    <tr class="border-b border-gray-100 dark:border-gray-700">
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Template</th>
                                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Used</th>
                                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Items</th>
                                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Allocation</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                    <tr
                                        v-for="tpl in topTemplates"
                                        :key="tpl.id"
                                        class="hover:bg-gray-50 dark:hover:bg-gray-700/50 cursor-pointer transition-colors"
                                        @click="$inertia.visit(route('inventory.part-out.templates.show', tpl.id))"
                                    >
                                        <td class="px-6 py-3">
                                            <span class="text-sm font-medium text-gray-900 dark:text-white">{{ tpl.name }}</span>
                                        </td>
                                        <td class="px-6 py-3 text-center">
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-indigo-100 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-400">
                                                {{ tpl.times_used }}x
                                            </span>
                                        </td>
                                        <td class="px-6 py-3 text-center text-sm text-gray-500 dark:text-gray-400">
                                            {{ tpl.items_count }}
                                        </td>
                                        <td class="px-6 py-3 text-right">
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400">
                                                {{ allocationLabel(tpl.cost_allocation_method) }}
                                            </span>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div v-else class="px-6 py-12 text-center">
                            <i class="fas fa-file-alt text-4xl text-gray-300 dark:text-gray-600 mb-3"></i>
                            <p class="text-sm text-gray-500 dark:text-gray-400">
                                No templates yet. Create a template to define reusable part-out configurations.
                            </p>
                            <Link
                                :href="route('inventory.part-out.templates.create')"
                                class="inline-flex items-center mt-4 px-4 py-2 bg-indigo-600 rounded-md text-xs font-semibold text-white uppercase tracking-widest hover:bg-indigo-700 transition"
                            >
                                Create Template
                            </Link>
                        </div>
                    </div>

                    <!-- Recent Components -->
                    <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700">
                        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Recent Components</h3>
                        </div>

                        <div v-if="recentComponents.length > 0" class="divide-y divide-gray-100 dark:divide-gray-700">
                            <div
                                v-for="comp in recentComponents"
                                :key="comp.id"
                                class="px-6 py-4"
                            >
                                <div class="flex items-start justify-between gap-3">
                                    <div class="flex-1 min-w-0">
                                        <Link
                                            :href="route('inventory.products.show', comp.id)"
                                            class="text-sm font-medium text-gray-900 dark:text-white hover:text-indigo-600 dark:hover:text-indigo-400"
                                        >
                                            {{ comp.name }}
                                        </Link>
                                        <p v-if="comp.sku" class="text-xs text-gray-400 dark:text-gray-500">
                                            SKU: {{ comp.sku }}
                                        </p>
                                        <p v-if="comp.parent_name" class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                            From:
                                            <Link
                                                v-if="comp.parent_id"
                                                :href="route('inventory.products.show', comp.parent_id)"
                                                class="text-indigo-600 dark:text-indigo-400 hover:underline"
                                            >
                                                {{ comp.parent_name }}
                                            </Link>
                                            <span v-else>{{ comp.parent_name }}</span>
                                            <span v-if="comp.parent_sku" class="text-gray-400 dark:text-gray-500 ml-1">({{ comp.parent_sku }})</span>
                                        </p>
                                    </div>
                                    <div class="flex-shrink-0 text-right">
                                        <div class="text-sm text-gray-900 dark:text-white">
                                            {{ formatCurrency(comp.selling_price) }}
                                        </div>
                                        <div class="text-xs text-gray-400 dark:text-gray-500">
                                            Cost: {{ formatCurrency(comp.cost_price) }}
                                        </div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                            Qty: {{ comp.available_quantity ?? 0 }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div v-else class="px-6 py-12 text-center">
                            <i class="fas fa-puzzle-piece text-4xl text-gray-300 dark:text-gray-600 mb-3"></i>
                            <p class="text-sm text-gray-500 dark:text-gray-400">
                                No components created yet. Execute a work order to generate component products.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
