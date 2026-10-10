<script setup>
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    logs: {
        type: Object,
        required: true
    },
    filters: {
        type: Object,
        default: () => ({ status: '', operation: '' })
    },
    stats: {
        type: Object,
        default: () => ({ total: 0, success: 0, failed: 0, pending: 0 })
    }
});

const statusFilter = ref(props.filters.status || '');
const operationFilter = ref(props.filters.operation || '');
const expandedLogs = ref([]);

const applyFilters = () => {
    router.get(route('marketplaces.ebay.logs'), {
        status: statusFilter.value || undefined,
        operation: operationFilter.value || undefined,
    }, {
        preserveState: true,
        preserveScroll: true
    });
};

const clearFilters = () => {
    statusFilter.value = '';
    operationFilter.value = '';
    applyFilters();
};

const toggleExpand = (logId) => {
    const index = expandedLogs.value.indexOf(logId);
    if (index > -1) {
        expandedLogs.value.splice(index, 1);
    } else {
        expandedLogs.value.push(logId);
    }
};

const isExpanded = (logId) => expandedLogs.value.includes(logId);

const statusColor = (status) => {
    const colors = {
        success: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-400',
        error: 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400',
        pending: 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400',
        warning: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400',
        skipped: 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400',
    };
    return colors[status] || 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400';
};
</script>

<template>
    <AppLayout title="eBay Sync Logs">
        <Head title="eBay Sync Logs - Marketplaces" />

        <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
            <!-- Breadcrumb -->
            <nav class="flex mb-6" aria-label="Breadcrumb">
                <ol class="flex items-center space-x-2 text-sm text-gray-500 dark:text-gray-400">
                    <li><Link :href="route('marketplaces.ebay.index')" class="hover:text-gray-700 dark:hover:text-gray-200">eBay</Link></li>
                    <li><i class="fas fa-chevron-right text-xs mx-1"></i></li>
                    <li class="text-gray-900 dark:text-white font-medium">Sync Logs</li>
                </ol>
            </nav>

            <h1 class="text-2xl font-bold text-gray-900 dark:text-white mb-6">Sync Logs</h1>

            <!-- Stats -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-4">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Total</p>
                    <p class="text-xl font-bold text-gray-900 dark:text-white">{{ stats.total }}</p>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-4">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Success</p>
                    <p class="text-xl font-bold text-emerald-600 dark:text-emerald-400">{{ stats.success }}</p>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-4">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Failed</p>
                    <p class="text-xl font-bold text-red-600 dark:text-red-400">{{ stats.failed }}</p>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-4">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Pending</p>
                    <p class="text-xl font-bold text-amber-600 dark:text-amber-400">{{ stats.pending }}</p>
                </div>
            </div>

            <!-- Filters -->
            <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-4 mb-6">
                <div class="flex flex-wrap items-center gap-3">
                    <select
                        v-model="statusFilter"
                        class="px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 text-sm"
                        @change="applyFilters"
                    >
                        <option value="">All Statuses</option>
                        <option value="success">Success</option>
                        <option value="error">Error</option>
                        <option value="pending">Pending</option>
                        <option value="warning">Warning</option>
                    </select>
                    <select
                        v-model="operationFilter"
                        class="px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 text-sm"
                        @change="applyFilters"
                    >
                        <option value="">All Operations</option>
                        <option value="import_inventory">Import Inventory</option>
                        <option value="create_inventory">Create Inventory</option>
                        <option value="publish">Publish</option>
                        <option value="sync_quantity">Sync Quantity</option>
                        <option value="refresh_token">Refresh Token</option>
                    </select>
                    <button @click="clearFilters" class="px-3 py-2 text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white text-sm">Clear</button>
                </div>
            </div>

            <!-- Logs Table -->
            <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-900/50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase w-8"></th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Operation</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Direction</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Status</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">SKU / Item</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Duration</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Date</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            <template v-for="log in logs.data" :key="log.id">
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 cursor-pointer" @click="toggleExpand(log.id)">
                                    <td class="px-4 py-3">
                                        <i :class="isExpanded(log.id) ? 'fas fa-chevron-down' : 'fas fa-chevron-right'" class="text-xs text-gray-400"></i>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-900 dark:text-white">{{ log.operation_label }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-400">
                                        <i :class="log.direction === 'inbound' ? 'fas fa-arrow-down text-blue-500' : 'fas fa-arrow-up text-green-500'" class="mr-1"></i>
                                        {{ log.direction }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="inline-flex px-2 py-0.5 rounded text-xs font-medium" :class="statusColor(log.status)">
                                            {{ log.status }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-sm font-mono text-gray-600 dark:text-gray-400">
                                        {{ log.ebay_sku || log.ebay_item_id || '-' }}
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">{{ log.duration || '-' }}</td>
                                    <td class="px-4 py-3 text-xs text-gray-500 dark:text-gray-400">
                                        {{ new Date(log.created_at).toLocaleString() }}
                                    </td>
                                </tr>
                                <tr v-if="isExpanded(log.id)">
                                    <td colspan="7" class="px-4 py-3 bg-gray-50 dark:bg-gray-900/30">
                                        <div class="text-sm space-y-1">
                                            <div v-if="log.product" class="text-gray-600 dark:text-gray-400">
                                                <span class="font-medium">Product:</span> {{ log.product.name }} ({{ log.product.sku }})
                                            </div>
                                            <div v-if="log.error_message" class="text-red-600 dark:text-red-400">
                                                <span class="font-medium">Error:</span> {{ log.error_message }}
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            </template>
                            <tr v-if="logs.data.length === 0">
                                <td colspan="7" class="px-4 py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                                    No sync logs found.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div v-if="logs.last_page > 1" class="px-4 py-3 border-t border-gray-200 dark:border-gray-700 flex items-center justify-between">
                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        Showing {{ logs.from }} to {{ logs.to }} of {{ logs.total }}
                    </p>
                    <div class="flex gap-1">
                        <Link
                            v-for="link in logs.links"
                            :key="link.label"
                            :href="link.url"
                            :class="[
                                'px-3 py-1 rounded text-sm',
                                link.active ? 'bg-blue-600 text-white' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700',
                                !link.url ? 'opacity-50 pointer-events-none' : ''
                            ]"
                            v-html="link.label"
                            preserve-state
                        />
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
