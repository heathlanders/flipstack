<template>
    <AdminLayout title="Security Audit Feed">
        <div>
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
                <!-- Breadcrumb + Header -->
                <div>
                    <nav class="text-xs text-gray-500 dark:text-gray-400 mb-2">
                        <Link :href="route('dashboard')" class="hover:text-gray-700 dark:hover:text-gray-200">Dashboard</Link>
                        <span class="mx-1">/</span>
                        <span class="text-gray-700 dark:text-gray-300">Admin</span>
                        <span class="mx-1">/</span>
                        <Link :href="route('admin.audit-hub.index')" class="hover:text-gray-700 dark:hover:text-gray-200">Audit Hub</Link>
                        <span class="mx-1">/</span>
                        <span class="text-gray-900 dark:text-white font-medium">Security</span>
                    </nav>
                    <div class="flex items-center justify-between">
                        <div>
                            <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">Security Audit Feed</h1>
                            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                Logins, logouts, failed attempts, credential changes, 2FA toggles. Category=security only.
                            </p>
                        </div>
                        <Link :href="route('admin.audit-hub.index')" class="text-sm text-indigo-600 dark:text-indigo-400 hover:underline">All events</Link>
                    </div>
                </div>

                <!-- Failed-login burst summary -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-4">
                    <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-200 mb-3">Failed logins (last 7 days)</h3>
                    <div v-if="failed_by_day.length === 0" class="text-xs text-gray-500">No failed logins.</div>
                    <div v-else class="flex items-end gap-2 h-32">
                        <div v-for="day in failed_by_day" :key="day.day" class="flex-1 flex flex-col items-center">
                            <div class="w-full bg-red-500 rounded-t" :style="{ height: barHeight(day.count) + '%' }" :title="day.count + ' failures'"></div>
                            <span class="text-xs text-gray-500 mt-1">{{ formatDate(day.day) }}</span>
                            <span class="text-xs text-gray-400">{{ day.count }}</span>
                        </div>
                    </div>
                </div>

                <!-- Filter bar -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-4">
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Search</label>
                            <input v-model="local.search" type="text" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 text-sm" @keyup.enter="apply" />
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Action</label>
                            <select v-model="local.action" @change="apply" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 text-sm">
                                <option value="">All</option>
                                <option v-for="a in facets.actions" :key="a" :value="a">{{ a }}</option>
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
                </div>

                <!-- Table -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm overflow-hidden">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-700/50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">When</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Action</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">User</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Description</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">IP</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            <tr v-for="row in rows.data" :key="row.id">
                                <td class="px-4 py-2 text-xs text-gray-500 dark:text-gray-400" :title="row.created_at">{{ row.time_ago }}</td>
                                <td class="px-4 py-2"><span class="text-xs font-mono text-yellow-700 dark:text-yellow-300">{{ row.action }}</span></td>
                                <td class="px-4 py-2 text-sm text-gray-700 dark:text-gray-200">{{ row.user.name }}</td>
                                <td class="px-4 py-2 text-sm text-gray-700 dark:text-gray-300">{{ row.description }}</td>
                                <td class="px-4 py-2 text-xs text-gray-500">{{ row.ip_address || '-' }}</td>
                            </tr>
                            <tr v-if="rows.data.length === 0">
                                <td colspan="5" class="px-4 py-8 text-center text-sm text-gray-500">No security events match.</td>
                            </tr>
                        </tbody>
                    </table>
                    <div v-if="rows.last_page > 1" class="px-4 py-3 border-t border-gray-200 dark:border-gray-700 flex items-center justify-between text-xs text-gray-600">
                        <span>Page {{ rows.current_page }} of {{ rows.last_page }}</span>
                        <div class="flex gap-1">
                            <Link v-for="link in rows.links" :key="link.label" :href="link.url || ''" v-html="link.label" :class="['px-3 py-1 rounded', link.active ? 'bg-indigo-600 text-white' : 'bg-gray-100 dark:bg-gray-700', !link.url && 'opacity-40 pointer-events-none']" />
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>

<script setup>
import { Link, router } from '@inertiajs/vue3';
import { computed, reactive } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
    rows: Object,
    failed_by_day: Array,
    filters: Object,
    facets: Object,
});

const local = reactive({ ...props.filters });
const maxFailed = computed(() => Math.max(1, ...props.failed_by_day.map((d) => Number(d.count))));

function apply() {
    router.get(route('admin.audit-hub.security'), local, { preserveState: true, preserveScroll: true, replace: true });
}

function barHeight(count) {
    return Math.max(4, Math.round((Number(count) / maxFailed.value) * 100));
}

function formatDate(d) {
    return new Date(d).toLocaleDateString(undefined, { month: 'short', day: 'numeric' });
}
</script>
