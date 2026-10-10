<script setup>
const props = defineProps({
    data: {
        type: Array,
        default: () => [],
    },
    title: {
        type: String,
        default: 'Leaderboard',
    },
    primaryLabel: {
        type: String,
        default: 'Score',
    },
});

function getRankBadgeClass(rank) {
    if (rank === 1) return 'bg-gradient-to-r from-yellow-400 to-amber-500 text-white';
    if (rank === 2) return 'bg-gradient-to-r from-gray-300 to-gray-400 text-gray-800';
    if (rank === 3) return 'bg-gradient-to-r from-amber-600 to-orange-700 text-white';
    return 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-400';
}

function getInitials(name) {
    if (!name) return '?';
    return name.split(' ').map(w => w[0]).join('').toUpperCase().slice(0, 2);
}
</script>

<template>
    <div>
        <!-- Empty state -->
        <div
            v-if="!data || data.length === 0"
            class="text-center py-12"
        >
            <svg
                class="mx-auto h-12 w-12 text-gray-400 dark:text-gray-500"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
            >
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"
                />
            </svg>
            <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">No data available</h3>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Performance data will appear here once activity is recorded.</p>
        </div>

        <!-- Table -->
        <div v-else class="overflow-x-auto">
            <table class="min-w-full">
                <thead>
                    <tr class="border-b border-gray-100 dark:border-gray-700">
                        <th class="py-3 px-4 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider w-16">
                            Rank
                        </th>
                        <th class="py-3 px-4 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            Name
                        </th>
                        <th class="py-3 px-4 text-right text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            {{ primaryLabel }}
                        </th>
                        <th
                            v-if="data.length > 0 && data[0].secondary_metrics && data[0].secondary_metrics.length > 0"
                            v-for="metric in data[0].secondary_metrics"
                            :key="metric.label"
                            class="py-3 px-4 text-right text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider"
                        >
                            {{ metric.label }}
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50 dark:divide-gray-700/50">
                    <tr
                        v-for="(row, index) in data"
                        :key="row.rank || index"
                        class="group hover:bg-gray-50 dark:hover:bg-gray-700/30 transition-colors"
                        :class="index % 2 === 1 ? 'bg-gray-50/50 dark:bg-gray-800/30' : ''"
                    >
                        <!-- Rank -->
                        <td class="py-4 px-4">
                            <span
                                class="inline-flex items-center justify-center w-7 h-7 rounded-full text-xs font-bold"
                                :class="getRankBadgeClass(row.rank || index + 1)"
                            >
                                {{ row.rank || index + 1 }}
                            </span>
                        </td>

                        <!-- Name -->
                        <td class="py-4 px-4">
                            <div class="flex items-center gap-3">
                                <div v-if="row.avatar_url" class="flex-shrink-0 w-8 h-8 rounded-full overflow-hidden">
                                    <img :src="row.avatar_url" :alt="row.name" class="w-full h-full object-cover" />
                                </div>
                                <div
                                    v-else
                                    class="flex-shrink-0 w-8 h-8 rounded-full bg-indigo-100 dark:bg-indigo-900/30 flex items-center justify-center"
                                >
                                    <span class="text-xs font-medium text-indigo-600 dark:text-indigo-400">
                                        {{ getInitials(row.name) }}
                                    </span>
                                </div>
                                <span class="text-sm font-medium text-gray-900 dark:text-white">
                                    {{ row.name }}
                                </span>
                            </div>
                        </td>

                        <!-- Primary value -->
                        <td class="py-4 px-4 text-right">
                            <span class="text-sm font-bold text-gray-900 dark:text-white">
                                {{ row.primary_value }}
                            </span>
                            <span v-if="row.primary_label" class="ml-1 text-xs text-gray-500 dark:text-gray-400">
                                {{ row.primary_label }}
                            </span>
                        </td>

                        <!-- Secondary metrics -->
                        <td
                            v-if="row.secondary_metrics && row.secondary_metrics.length > 0"
                            v-for="metric in row.secondary_metrics"
                            :key="metric.label"
                            class="py-4 px-4 text-right"
                        >
                            <span class="text-sm text-gray-600 dark:text-gray-300">
                                {{ metric.value }}
                            </span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
