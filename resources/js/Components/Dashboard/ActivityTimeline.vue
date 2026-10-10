<script setup>
defineProps({
    items: {
        type: Array,
        default: () => [],
        // Each item: { type, title, description, user, timestamp }
    },
})

const typeConfig = {
    intake: { bg: 'bg-blue-100 dark:bg-blue-900/30', text: 'text-blue-600 dark:text-blue-400', label: 'IN' },
    inventory: { bg: 'bg-amber-100 dark:bg-amber-900/30', text: 'text-amber-600 dark:text-amber-400', label: 'IV' },
    created: { bg: 'bg-blue-100 dark:bg-blue-900/30', text: 'text-blue-600 dark:text-blue-400', label: '+' },
    updated: { bg: 'bg-amber-100 dark:bg-amber-900/30', text: 'text-amber-600 dark:text-amber-400', label: 'U' },
    deleted: { bg: 'bg-red-100 dark:bg-red-900/30', text: 'text-red-600 dark:text-red-400', label: 'X' },
    comment: { bg: 'bg-gray-100 dark:bg-gray-700', text: 'text-gray-600 dark:text-gray-400', label: 'C' },
    status: { bg: 'bg-purple-100 dark:bg-purple-900/30', text: 'text-purple-600 dark:text-purple-400', label: 'S' },
    system: { bg: 'bg-gray-100 dark:bg-gray-700', text: 'text-gray-500 dark:text-gray-400', label: 'SY' },
    platform: { bg: 'bg-orange-100 dark:bg-orange-900/30', text: 'text-orange-600 dark:text-orange-400', label: 'P' },
    team: { bg: 'bg-purple-100 dark:bg-purple-900/30', text: 'text-purple-600 dark:text-purple-400', label: 'T' },
}

const getConfig = (type) => typeConfig[type] || typeConfig.system

const formatTime = (timestamp) => {
    if (!timestamp) return ''
    const date = new Date(timestamp)
    const now = new Date()
    const diffMs = now - date
    const diffMins = Math.floor(diffMs / 60000)
    if (diffMins < 1) return 'Just now'
    if (diffMins < 60) return `${diffMins}m ago`
    const diffHours = Math.floor(diffMins / 60)
    if (diffHours < 24) return `${diffHours}h ago`
    const diffDays = Math.floor(diffHours / 24)
    if (diffDays < 7) return `${diffDays}d ago`
    return date.toLocaleDateString()
}
</script>

<template>
    <div class="flow-root">
        <ul v-if="items.length > 0" class="-mb-8">
            <li v-for="(item, idx) in items" :key="`${item.timestamp}-${idx}`">
                <div class="relative pb-8">
                    <!-- Connector line -->
                    <span
                        v-if="idx !== items.length - 1"
                        class="absolute left-4 top-8 -ml-px h-full w-0.5 bg-gray-200 dark:bg-gray-700"
                        aria-hidden="true"
                    ></span>

                    <div class="relative flex items-start space-x-3">
                        <!-- Event icon -->
                        <div
                            :class="[getConfig(item.type).bg, getConfig(item.type).text]"
                            class="relative flex h-8 w-8 items-center justify-center rounded-full text-xs font-bold"
                        >
                            {{ getConfig(item.type).label }}
                        </div>

                        <!-- Content -->
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-medium text-gray-900 dark:text-white">{{ item.title }}</p>
                            <p v-if="item.description" class="mt-0.5 text-sm text-gray-500 dark:text-gray-400 truncate">{{ item.description }}</p>
                            <div class="mt-1 flex items-center gap-2 text-xs text-gray-400 dark:text-gray-500">
                                <span v-if="item.user">{{ item.user }}</span>
                                <span v-if="item.user && item.timestamp" aria-hidden="true">&middot;</span>
                                <span>{{ formatTime(item.timestamp) }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </li>
        </ul>

        <!-- Empty state -->
        <div v-else class="text-center py-8">
            <svg class="mx-auto h-8 w-8 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">No recent activity</p>
        </div>
    </div>
</template>
