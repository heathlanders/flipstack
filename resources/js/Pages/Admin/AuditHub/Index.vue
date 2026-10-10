<template>
    <AdminLayout title="Audit Hub">
        <div>
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <!-- Breadcrumb + Header -->
                <nav class="text-xs text-gray-500 dark:text-gray-400 mb-2">
                    <Link :href="route('dashboard')" class="hover:text-gray-700 dark:hover:text-gray-200">Dashboard</Link>
                    <span class="mx-1">/</span>
                    <span class="text-gray-700 dark:text-gray-300">Admin</span>
                    <span class="mx-1">/</span>
                    <span class="text-gray-900 dark:text-white font-medium">Audit Hub</span>
                </nav>
                <div class="flex items-center justify-between mb-6">
                    <div>
                        <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">Audit Hub</h1>
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                            Every state-changing event across the organization. Filter by user, category, action, severity, model, or date.
                        </p>
                    </div>
                    <div class="flex items-center gap-2">
                        <Link
                            :href="route('admin.audit-hub.security')"
                            class="inline-flex items-center px-3 py-2 text-sm bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-600"
                        >
                            Security feed
                        </Link>
                        <a
                            :href="exportUrl"
                            class="inline-flex items-center px-3 py-2 text-sm bg-indigo-600 text-white rounded-lg hover:bg-indigo-700"
                        >
                            Export CSV
                        </a>
                    </div>
                </div>

                <!-- Filters -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm mb-4 p-4">
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Search</label>
                            <input v-model="local.search" type="text" placeholder="action or description" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 text-sm" @keyup.enter="apply" />
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">User</label>
                            <select v-model="local.user_id" @change="apply" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 text-sm">
                                <option value="">All users</option>
                                <option v-for="u in facets.users" :key="u.id" :value="u.id">{{ u.name }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Category</label>
                            <select v-model="local.category" @change="apply" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 text-sm">
                                <option value="">All categories</option>
                                <option v-for="c in facets.categories" :key="c" :value="c">{{ c }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Severity</label>
                            <select v-model="local.severity" @change="apply" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 text-sm">
                                <option value="">All severities</option>
                                <option v-for="s in facets.severities" :key="s" :value="s">{{ s }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Action prefix</label>
                            <input v-model="local.action" type="text" placeholder="e.g. order. or product.created" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 text-sm" @keyup.enter="apply" />
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Source</label>
                            <select v-model="local.source" @change="apply" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 text-sm">
                                <option value="">Any source</option>
                                <option v-for="s in facets.sources" :key="s" :value="s">{{ s }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">From</label>
                            <input v-model="local.date_from" type="date" @change="apply" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 text-sm" />
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">To</label>
                            <input v-model="local.date_to" type="date" @change="apply" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 text-sm" />
                        </div>
                    </div>
                    <div class="mt-3 flex items-center justify-between">
                        <span class="text-xs text-gray-500 dark:text-gray-400">{{ rows.total }} events match</span>
                        <button @click="clear" class="text-xs text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">Clear filters</button>
                    </div>
                </div>

                <!-- Table -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm overflow-hidden">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-700/50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">When</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">User</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Action</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Description</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Subject</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Source</th>
                                <th class="px-4 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            <tr v-for="row in rows.data" :key="row.id" @click="openDrawer(row)" class="hover:bg-gray-50 dark:hover:bg-gray-700/40 cursor-pointer">
                                <td class="px-4 py-2 text-xs text-gray-500 dark:text-gray-400" :title="row.created_at">{{ row.time_ago }}</td>
                                <td class="px-4 py-2 text-sm text-gray-700 dark:text-gray-200">{{ row.user.name }}</td>
                                <td class="px-4 py-2">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-mono" :class="severityClass(row.severity)">{{ row.action }}</span>
                                </td>
                                <td class="px-4 py-2 text-sm text-gray-700 dark:text-gray-300">{{ row.description }}</td>
                                <td class="px-4 py-2 text-xs">
                                    <Link v-if="row.model_type" :href="route('admin.audit-hub.entity', { type: row.model_type, id: row.model_id })" class="text-indigo-600 dark:text-indigo-400 hover:underline">
                                        {{ row.model_basename }}#{{ row.model_id }}
                                    </Link>
                                </td>
                                <td class="px-4 py-2 text-xs text-gray-500 dark:text-gray-400">{{ row.source || '-' }}</td>
                                <td class="px-4 py-2 text-right text-xs text-gray-400">Open</td>
                            </tr>
                            <tr v-if="rows.data.length === 0">
                                <td colspan="7" class="px-4 py-8 text-center text-sm text-gray-500 dark:text-gray-400">No audit rows match the current filters.</td>
                            </tr>
                        </tbody>
                    </table>
                    <div v-if="rows.last_page > 1" class="px-4 py-3 border-t border-gray-200 dark:border-gray-700 flex items-center justify-between text-xs text-gray-600 dark:text-gray-400">
                        <span>Page {{ rows.current_page }} of {{ rows.last_page }}</span>
                        <div class="flex gap-1">
                            <Link v-for="link in rows.links" :key="link.label" :href="link.url || ''" v-html="link.label" :class="['px-3 py-1 rounded', link.active ? 'bg-indigo-600 text-white' : 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200', !link.url && 'opacity-40 pointer-events-none']" />
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Detail drawer -->
        <div v-if="selected" class="fixed inset-0 z-50 flex">
            <div class="absolute inset-0 bg-black/40" @click="selected = null"></div>
            <div class="relative ml-auto w-full max-w-xl h-full bg-white dark:bg-gray-800 shadow-xl overflow-y-auto">
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 flex items-start justify-between">
                    <div>
                        <h3 class="text-sm font-mono">{{ selected.action }}</h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ selected.description }}</p>
                    </div>
                    <button @click="selected = null" class="text-gray-400 hover:text-gray-700 dark:hover:text-gray-200">&times;</button>
                </div>
                <div class="px-6 py-4 space-y-3 text-sm">
                    <div><span class="text-xs text-gray-500">When:</span> {{ selected.created_at }} ({{ selected.time_ago }})</div>
                    <div><span class="text-xs text-gray-500">User:</span> {{ selected.user.name }} ({{ selected.user.email }})</div>
                    <div><span class="text-xs text-gray-500">Category / Severity:</span> {{ selected.category }} / {{ selected.severity }}</div>
                    <div v-if="selected.model_type"><span class="text-xs text-gray-500">Subject:</span>
                        <Link :href="route('admin.audit-hub.entity', { type: selected.model_type, id: selected.model_id })" class="text-indigo-600 dark:text-indigo-400 hover:underline">{{ selected.model_basename }}#{{ selected.model_id }}</Link>
                    </div>
                    <div><span class="text-xs text-gray-500">IP:</span> {{ selected.ip_address || '-' }}</div>
                    <div>
                        <div class="text-xs text-gray-500 mb-1">Metadata:</div>
                        <pre class="text-xs bg-gray-50 dark:bg-gray-900 p-3 rounded overflow-x-auto">{{ JSON.stringify(selected.metadata, null, 2) }}</pre>
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>

<script setup>
import { Link, router } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
    rows: Object,
    filters: Object,
    facets: Object,
});

const local = reactive({ ...props.filters });
const selected = ref(null);

const exportUrl = computed(() => {
    const params = new URLSearchParams(Object.entries(local).filter(([, v]) => v));
    return route('admin.audit-hub.export') + (params.toString() ? '?' + params.toString() : '');
});

function apply() {
    router.get(route('admin.audit-hub.index'), local, { preserveState: true, preserveScroll: true, replace: true });
}

function clear() {
    Object.keys(local).forEach((k) => (local[k] = ''));
    apply();
}

function openDrawer(row) {
    selected.value = row;
}

function severityClass(severity) {
    switch (severity) {
        case 'critical':
        case 'error':
            return 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200';
        case 'warning':
            return 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200';
        case 'info':
            return 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200';
        case 'debug':
            return 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-200';
        default:
            return 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200';
    }
}
</script>
