<template>
    <AdminLayout :title="`Audit Timeline · ${basename}#${modelId}`">
        <div>
            <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
                <!-- Breadcrumb + Header -->
                <nav class="text-xs text-gray-500 dark:text-gray-400 mb-2">
                    <Link :href="route('dashboard')" class="hover:text-gray-700 dark:hover:text-gray-200">Dashboard</Link>
                    <span class="mx-1">/</span>
                    <span class="text-gray-700 dark:text-gray-300">Admin</span>
                    <span class="mx-1">/</span>
                    <Link :href="route('admin.audit-hub.index')" class="hover:text-gray-700 dark:hover:text-gray-200">Audit Hub</Link>
                    <span class="mx-1">/</span>
                    <span class="text-gray-900 dark:text-white font-medium">Timeline</span>
                </nav>
                <div class="flex items-center justify-between mb-6">
                    <div>
                        <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">
                            {{ basename }} #{{ modelId }}
                        </h1>
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400 font-mono">{{ modelType }}</p>
                    </div>
                    <Link :href="route('admin.audit-hub.index')" class="text-sm text-indigo-600 dark:text-indigo-400 hover:underline">Back to hub</Link>
                </div>

                <div v-if="rows.length === 0" class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6 text-sm text-gray-500 dark:text-gray-400">
                    No audit history for this entity.
                </div>
                <ol v-else class="relative border-l border-gray-200 dark:border-gray-700 ml-3">
                    <li v-for="row in rows" :key="row.id" class="mb-6 ml-6">
                        <span class="absolute -left-2 flex items-center justify-center w-4 h-4 rounded-full" :class="dotClass(row.severity)"></span>
                        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-4">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-mono" :class="severityTextClass(row.severity)">{{ row.action }}</span>
                                <time class="text-xs text-gray-500 dark:text-gray-400" :title="row.created_at">{{ row.time_ago }}</time>
                            </div>
                            <p class="mt-1 text-sm text-gray-700 dark:text-gray-200">{{ row.description }}</p>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">by {{ row.user.name }} · {{ row.source || 'web' }}</p>

                            <!-- Show diff if present (trait-emitted updates) -->
                            <div v-if="row.metadata?.changes" class="mt-3 border-t border-gray-100 dark:border-gray-700 pt-2">
                                <div v-for="(diff, field) in row.metadata.changes" :key="field" class="text-xs grid grid-cols-3 gap-2 py-1">
                                    <span class="font-mono text-gray-500 dark:text-gray-400">{{ field }}</span>
                                    <span class="text-red-600 dark:text-red-400 line-through truncate">{{ formatValue(diff.old) }}</span>
                                    <span class="text-green-600 dark:text-green-400 truncate">{{ formatValue(diff.new) }}</span>
                                </div>
                            </div>

                            <details v-else-if="row.metadata && Object.keys(row.metadata).length" class="mt-2">
                                <summary class="text-xs text-gray-400 cursor-pointer">metadata</summary>
                                <pre class="text-xs bg-gray-50 dark:bg-gray-900 p-2 rounded mt-1 overflow-x-auto">{{ JSON.stringify(row.metadata, null, 2) }}</pre>
                            </details>
                        </div>
                    </li>
                </ol>
            </div>
        </div>
    </AdminLayout>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
    modelType: String,
    modelId: String,
    rows: Array,
});

const basename = computed(() => props.modelType.split('\\').pop());

function dotClass(severity) {
    switch (severity) {
        case 'critical':
        case 'error':
            return 'bg-red-500';
        case 'warning':
            return 'bg-yellow-500';
        case 'info':
            return 'bg-blue-500';
        case 'debug':
            return 'bg-gray-400';
        default:
            return 'bg-gray-400';
    }
}

function severityTextClass(severity) {
    return {
        critical: 'text-red-700 dark:text-red-300',
        error: 'text-red-700 dark:text-red-300',
        warning: 'text-yellow-700 dark:text-yellow-300',
        info: 'text-blue-700 dark:text-blue-300',
        debug: 'text-gray-600 dark:text-gray-300',
    }[severity] || 'text-gray-700 dark:text-gray-300';
}

function formatValue(v) {
    if (v === null || v === undefined) return 'null';
    if (typeof v === 'object') return JSON.stringify(v);
    return String(v);
}
</script>
