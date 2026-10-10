<template>
    <AppLayout title="Audit Log">
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Audit Log</h2>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <!-- Filters -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm mb-4 p-4">
                    <div class="grid grid-cols-1 md:grid-cols-5 gap-3">
                        <div class="md:col-span-2">
                            <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Search</label>
                            <input
                                v-model="local.search"
                                type="text"
                                placeholder="Search description..."
                                class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 shadow-sm text-sm"
                                @keyup.enter="apply"
                            />
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">User</label>
                            <select v-model="local.user_id" @change="apply" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 shadow-sm text-sm">
                                <option value="">All Users</option>
                                <option v-for="u in available_users" :key="u.id" :value="u.id">{{ u.name }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Action</label>
                            <select v-model="local.action" @change="apply" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 shadow-sm text-sm">
                                <option value="">All Actions</option>
                                <option v-for="a in available_actions" :key="a" :value="a">{{ a }}</option>
                            </select>
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">From</label>
                                <input v-model="local.date_from" type="date" @change="apply" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 shadow-sm text-sm" />
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">To</label>
                                <input v-model="local.date_to" type="date" @change="apply" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 shadow-sm text-sm" />
                            </div>
                        </div>
                    </div>
                    <div class="mt-3 flex justify-end space-x-2">
                        <button type="button" @click="clear" class="text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">Clear</button>
                    </div>
                </div>

                <!-- Activity Table -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm overflow-hidden">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-700/50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">When</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">User</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Action</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Description</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">IP</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            <tr v-for="a in activities.data" :key="a.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    <div class="text-gray-900 dark:text-white">{{ a.time_ago }}</div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400">{{ a.created_at }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    <div class="text-gray-900 dark:text-white">{{ a.user.name }}</div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400">{{ a.user.email }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 py-0.5 text-xs font-mono font-medium rounded bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300">
                                        {{ a.action }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300">{{ a.description || '—' }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-xs font-mono text-gray-500 dark:text-gray-400">{{ a.ip_address || '—' }}</td>
                            </tr>
                            <tr v-if="!activities.data?.length">
                                <td colspan="5" class="px-6 py-12 text-center text-sm text-gray-500 dark:text-gray-400">
                                    <i class="fas fa-history text-3xl mb-2 block"></i>
                                    No activity matches the filters.
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <div v-if="activities.links?.length > 3" class="px-4 py-3 border-t border-gray-200 dark:border-gray-700 flex items-center justify-between">
                        <div class="text-sm text-gray-600 dark:text-gray-400">
                            Showing {{ activities.from || 0 }}-{{ activities.to || 0 }} of {{ activities.total }}
                        </div>
                        <div class="flex space-x-1">
                            <Link
                                v-for="(link, i) in activities.links"
                                :key="i"
                                :href="link.url || '#'"
                                v-html="link.label"
                                class="px-3 py-1 text-sm rounded"
                                :class="[
                                    link.active ? 'bg-blue-600 text-white' : 'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700',
                                    !link.url ? 'opacity-50 pointer-events-none' : ''
                                ]"
                                preserve-scroll
                            />
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { ref } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

const props = defineProps({
    activities: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    available_actions: { type: Array, default: () => [] },
    available_users: { type: Array, default: () => [] },
    can_export: { type: Boolean, default: false },
})

const local = ref({
    search: props.filters.search || '',
    user_id: props.filters.user_id || '',
    action: props.filters.action || '',
    date_from: props.filters.date_from || '',
    date_to: props.filters.date_to || '',
})

const apply = () => {
    const params = {}
    Object.entries(local.value).forEach(([k, v]) => { if (v) params[k] = v })
    router.get(route('organization.audit-log'), params, { preserveState: true, preserveScroll: true })
}

const clear = () => {
    local.value = { search: '', user_id: '', action: '', date_from: '', date_to: '' }
    apply()
}
</script>
