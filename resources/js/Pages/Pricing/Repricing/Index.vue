<script setup>
import { ref, reactive, watch } from 'vue'
import { router, Head, Link } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { route } from 'ziggy-js'

const props = defineProps({
    rules: { type: Array, default: () => [] },
    stats: { type: Object, default: () => ({}) },
})

const strategyLabels = {
    match_lowest: 'Match Lowest',
    beat_by_amount: 'Beat by Amount',
    beat_by_percent: 'Beat by Percent',
    cost_plus: 'Cost Plus',
    map_floor: 'MAP Floor',
}

function toggleRule(rule) {
    router.post(route('pricing.repricing.toggle', rule.id), {}, {
        preserveScroll: true,
    })
}

function deleteRule(rule) {
    if (confirm(`Delete rule "${rule.name}"?`)) {
        router.delete(route('pricing.repricing.destroy', rule.id), {
            preserveScroll: true,
        })
    }
}

function runRepricing() {
    if (confirm('Run repricing engine now for all active rules?')) {
        router.post(route('pricing.repricing.run'), {}, {
            preserveScroll: true,
        })
    }
}
</script>

<template>
    <Head title="Repricing Rules" />

    <AppLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200">Repricing Rules</h2>
                <div class="flex items-center gap-3">
                    <Link
                        :href="route('pricing.repricing.logs')"
                        class="inline-flex items-center px-4 py-2 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md font-semibold text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest hover:bg-gray-50 dark:hover:bg-gray-600 transition"
                    >
                        View Logs
                    </Link>
                    <button
                        @click="runRepricing"
                        class="inline-flex items-center px-4 py-2 bg-emerald-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-emerald-500 transition"
                    >
                        Run Now
                    </button>
                    <Link
                        :href="route('pricing.repricing.create')"
                        class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-500 transition"
                    >
                        New Rule
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <!-- Stats -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
                        <div class="text-sm text-gray-500 dark:text-gray-400">Total Rules</div>
                        <div class="text-2xl font-bold text-gray-900 dark:text-white">{{ stats.total_rules ?? 0 }}</div>
                    </div>
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
                        <div class="text-sm text-gray-500 dark:text-gray-400">Active Rules</div>
                        <div class="text-2xl font-bold text-emerald-600">{{ stats.active_rules ?? 0 }}</div>
                    </div>
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
                        <div class="text-sm text-gray-500 dark:text-gray-400">Changes (24h)</div>
                        <div class="text-2xl font-bold text-indigo-600">{{ stats.recent_changes ?? 0 }}</div>
                    </div>
                </div>

                <!-- Rules Table -->
                <div class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Priority</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Name</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Strategy</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Marketplace</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Scope</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Last Run</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            <tr v-for="rule in rules" :key="rule.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                <td class="px-6 py-4 text-sm text-gray-900 dark:text-gray-100">{{ rule.priority }}</td>
                                <td class="px-6 py-4 text-sm font-medium text-gray-900 dark:text-gray-100">{{ rule.name }}</td>
                                <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-400">{{ strategyLabels[rule.strategy] || rule.strategy }}</td>
                                <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-400 capitalize">{{ rule.marketplace === '*' ? 'All' : rule.marketplace }}</td>
                                <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-400 capitalize">{{ rule.scope_type }}</td>
                                <td class="px-6 py-4">
                                    <button
                                        @click="toggleRule(rule)"
                                        :class="rule.is_active ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900 dark:text-emerald-300' : 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300'"
                                        class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium cursor-pointer hover:opacity-80"
                                    >
                                        {{ rule.is_active ? 'Active' : 'Paused' }}
                                    </button>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                    {{ rule.last_run_at ? new Date(rule.last_run_at).toLocaleString() : 'Never' }}
                                </td>
                                <td class="px-6 py-4 text-right text-sm space-x-2">
                                    <Link :href="route('pricing.repricing.edit', rule.id)" class="text-indigo-600 dark:text-indigo-400 hover:underline">Edit</Link>
                                    <button @click="deleteRule(rule)" class="text-red-600 dark:text-red-400 hover:underline">Delete</button>
                                </td>
                            </tr>
                            <tr v-if="rules.length === 0">
                                <td colspan="8" class="px-6 py-12 text-center text-gray-500 dark:text-gray-400">
                                    No repricing rules yet. Create your first rule to get started.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
