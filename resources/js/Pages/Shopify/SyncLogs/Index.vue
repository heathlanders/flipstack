<script setup>
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    logs: Object,
    filters: Object,
});

const operation = ref(props.filters?.operation || '');
const status = ref(props.filters?.status || '');
const expandedRows = ref({});

const applyFilters = () => {
    router.get(route('marketplaces.shopify.logs'), {
        operation: operation.value || undefined,
        status: status.value || undefined,
    }, {
        preserveState: true,
        preserveScroll: true,
    });
};

const clearFilters = () => {
    operation.value = '';
    status.value = '';
    router.get(route('marketplaces.shopify.logs'), {}, { preserveState: true });
};

const toggleRow = (id) => {
    expandedRows.value[id] = !expandedRows.value[id];
};

const statusColor = (status) => {
    const colors = {
        success: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-400',
        error: 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400',
        warning: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400',
        skipped: 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-400',
        pending: 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400',
    };
    return colors[status] || 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-400';
};

const directionIcon = (direction) => {
    return direction === 'outbound' ? 'fas fa-arrow-right text-blue-500' : 'fas fa-arrow-left text-green-500';
};

const formatDate = (dateStr) => {
    if (!dateStr) return '-';
    return new Date(dateStr).toLocaleString();
};
</script>

<template>
    <AppLayout title="Shopify - Sync Logs">
        <Head title="Shopify Sync Logs" />

        <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Shopify Sync Logs</h1>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                        History of sync operations with Shopify
                    </p>
                </div>
                <Link :href="route('marketplaces.shopify.index')" class="text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">
                    Back to Shopify
                </Link>
            </div>

            <!-- Filters -->
            <div class="mb-6 flex items-center gap-4">
                <select
                    v-model="operation"
                    @change="applyFilters"
                    class="px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 text-sm"
                >
                    <option value="">All Operations</option>
                    <option value="create_product">Create Product</option>
                    <option value="update_product">Update Product</option>
                    <option value="delete_product">Delete Product</option>
                    <option value="set_inventory">Set Inventory</option>
                    <option value="adjust_inventory">Adjust Inventory</option>
                    <option value="import_products">Import Products</option>
                    <option value="register_webhook">Register Webhook</option>
                </select>
                <select
                    v-model="status"
                    @change="applyFilters"
                    class="px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 text-sm"
                >
                    <option value="">All Statuses</option>
                    <option value="success">Success</option>
                    <option value="error">Error</option>
                    <option value="warning">Warning</option>
                    <option value="skipped">Skipped</option>
                    <option value="pending">Pending</option>
                </select>
                <button v-if="filters?.operation || filters?.status" @click="clearFilters" class="px-3 py-2 border border-gray-300 dark:border-gray-600 text-gray-600 dark:text-gray-400 rounded-lg text-sm">
                    Clear
                </button>
            </div>

            <!-- Logs Table -->
            <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-900">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase w-8"></th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Time</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Operation</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Direction</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Status</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Product</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Duration</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        <template v-for="log in logs.data" :key="log.id">
                            <tr @click="toggleRow(log.id)" class="cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-750">
                                <td class="px-4 py-3 text-gray-400">
                                    <i :class="expandedRows[log.id] ? 'fas fa-chevron-down' : 'fas fa-chevron-right'" class="text-xs"></i>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-400 whitespace-nowrap">{{ formatDate(log.created_at) }}</td>
                                <td class="px-4 py-3 text-sm font-medium text-gray-900 dark:text-white">{{ log.operation_label || log.operation }}</td>
                                <td class="px-4 py-3">
                                    <i :class="directionIcon(log.direction)"></i>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex px-2 py-0.5 rounded text-xs font-medium" :class="statusColor(log.status)">
                                        {{ log.status }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-400 truncate max-w-xs">
                                    {{ log.product?.name || log.shopify_sku || '-' }}
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">{{ log.duration || '-' }}</td>
                            </tr>
                            <tr v-if="expandedRows[log.id]">
                                <td colspan="7" class="px-4 py-4 bg-gray-50 dark:bg-gray-900">
                                    <div class="space-y-2 text-sm">
                                        <div v-if="log.api_endpoint">
                                            <span class="font-medium text-gray-700 dark:text-gray-300">Endpoint:</span>
                                            <span class="text-gray-600 dark:text-gray-400 ml-2">{{ log.http_method }} {{ log.api_endpoint }}</span>
                                        </div>
                                        <div v-if="log.http_status_code">
                                            <span class="font-medium text-gray-700 dark:text-gray-300">HTTP Status:</span>
                                            <span class="text-gray-600 dark:text-gray-400 ml-2">{{ log.http_status_code }}</span>
                                        </div>
                                        <div v-if="log.error_message" class="text-red-600 dark:text-red-400">
                                            <span class="font-medium">Error:</span> {{ log.error_message }}
                                        </div>
                                        <div v-if="log.initiated_by">
                                            <span class="font-medium text-gray-700 dark:text-gray-300">Initiated by:</span>
                                            <span class="text-gray-600 dark:text-gray-400 ml-2">{{ log.initiator?.name || 'System' }}</span>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        </template>
                        <tr v-if="logs.data.length === 0">
                            <td colspan="7" class="px-4 py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                                No sync logs found
                            </td>
                        </tr>
                    </tbody>
                </table>

                <!-- Pagination -->
                <div v-if="logs.links && logs.links.length > 3" class="px-4 py-3 bg-gray-50 dark:bg-gray-900 border-t border-gray-200 dark:border-gray-700 flex justify-between items-center">
                    <span class="text-sm text-gray-500 dark:text-gray-400">
                        Showing {{ logs.from }} to {{ logs.to }} of {{ logs.total }}
                    </span>
                    <div class="flex gap-1">
                        <Link
                            v-for="link in logs.links"
                            :key="link.label"
                            :href="link.url || '#'"
                            v-html="link.label"
                            class="px-3 py-1 text-sm rounded border"
                            :class="link.active ? 'bg-green-600 text-white border-green-600' : 'border-gray-300 dark:border-gray-600 text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700'"
                        />
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
