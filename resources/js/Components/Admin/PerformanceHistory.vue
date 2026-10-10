<script setup>
defineProps({
    history: { type: Array, default: () => [] }
})

const formatDate = (date) => {
    if (!date) return '-'
    return new Date(date).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' })
}
</script>

<template>
    <div class="space-y-4">
        <div v-if="history.length === 0" class="text-center py-8 text-gray-500">
            No performance history available
        </div>
        <div v-for="(record, index) in history" :key="index" class="flex items-center justify-between p-4 bg-gray-50 dark:bg-slate-700 rounded-lg">
            <div>
                <p class="text-sm font-medium text-gray-900 dark:text-white">
                    Score: {{ record.score?.toFixed(1) || '-' }}
                </p>
                <p class="text-xs text-gray-500 dark:text-gray-400">{{ formatDate(record.recorded_at) }}</p>
            </div>
            <div class="text-right">
                <div class="text-sm text-gray-600 dark:text-gray-300">
                    Sales: {{ record.sales_count || 0 }}
                </div>
                <div class="text-xs text-gray-500 dark:text-gray-400">
                    Returns: {{ record.return_rate?.toFixed(1) || 0 }}%
                </div>
            </div>
        </div>
    </div>
</template>
