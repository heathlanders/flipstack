<template>
    <AppLayout :title="`Bulk Job ${j.id}`">
        <template #header>
            <div class="flex items-center space-x-4">
                <Link :href="route('inventory.bulk.jobs')" class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">
                    <i class="fas fa-arrow-left"></i>
                </Link>
                <div>
                    <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">{{ j.operation_type_label }}</h2>
                    <p class="text-xs text-gray-500 dark:text-gray-400 font-mono">Job #{{ j.id }}</p>
                </div>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">
                <!-- Status + Progress -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6">
                    <div class="flex items-center justify-between mb-4">
                        <div>
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium" :class="statusClass">
                                <i :class="statusIcon" class="mr-1.5"></i>
                                {{ j.status_label }}
                            </span>
                        </div>
                        <div class="flex space-x-2">
                            <a v-if="j.has_download" :href="`/inventory/bulk/jobs/${j.id}/download`" class="inline-flex items-center px-3 py-1.5 bg-green-600 text-white text-sm rounded-md hover:bg-green-700">
                                <i class="fas fa-download mr-1.5"></i>Download
                            </a>
                            <button v-if="j.is_cancellable" @click="cancel" :disabled="busy" class="inline-flex items-center px-3 py-1.5 bg-red-600 text-white text-sm rounded-md hover:bg-red-700 disabled:opacity-50">
                                <i class="fas fa-times mr-1.5"></i>Cancel
                            </button>
                            <button v-if="j.is_retryable" @click="retry" :disabled="busy" class="inline-flex items-center px-3 py-1.5 bg-blue-600 text-white text-sm rounded-md hover:bg-blue-700 disabled:opacity-50">
                                <i class="fas fa-redo mr-1.5"></i>Retry
                            </button>
                        </div>
                    </div>

                    <!-- Progress Bar -->
                    <div class="mb-2 flex justify-between text-xs text-gray-600 dark:text-gray-400">
                        <span>Progress</span>
                        <span>{{ j.progress_percent || 0 }}%</span>
                    </div>
                    <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-3 overflow-hidden">
                        <div
                            class="h-3 transition-all duration-500"
                            :class="j.failed_items > 0 ? 'bg-amber-500' : 'bg-blue-600'"
                            :style="{ width: `${j.progress_percent || 0}%` }"
                        ></div>
                    </div>
                </div>

                <!-- Counts -->
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-4">
                        <div class="text-sm text-gray-500 dark:text-gray-400">Total Items</div>
                        <div class="text-2xl font-semibold text-gray-900 dark:text-white">{{ j.total_items || 0 }}</div>
                    </div>
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-4">
                        <div class="text-sm text-gray-500 dark:text-gray-400">Processed</div>
                        <div class="text-2xl font-semibold text-blue-600 dark:text-blue-400">{{ j.processed_items || 0 }}</div>
                    </div>
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-4">
                        <div class="text-sm text-gray-500 dark:text-gray-400">Failed</div>
                        <div class="text-2xl font-semibold" :class="j.failed_items > 0 ? 'text-red-600 dark:text-red-400' : 'text-gray-400'">{{ j.failed_items || 0 }}</div>
                    </div>
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-4">
                        <div class="text-sm text-gray-500 dark:text-gray-400">Errors</div>
                        <div class="text-2xl font-semibold" :class="j.error_count > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-gray-400'">{{ j.error_count || 0 }}</div>
                    </div>
                </div>

                <!-- Timing -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Timing</h3>
                    <dl class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
                        <div>
                            <dt class="text-gray-500 dark:text-gray-400">Created</dt>
                            <dd class="text-gray-900 dark:text-white">{{ formatDate(j.created_at) }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500 dark:text-gray-400">Started</dt>
                            <dd class="text-gray-900 dark:text-white">{{ formatDate(j.started_at) }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500 dark:text-gray-400">Completed</dt>
                            <dd class="text-gray-900 dark:text-white">{{ formatDate(j.completed_at) }}</dd>
                        </div>
                        <div v-if="j.duration" class="md:col-span-3">
                            <dt class="text-gray-500 dark:text-gray-400">Duration</dt>
                            <dd class="text-gray-900 dark:text-white">{{ j.duration }}</dd>
                        </div>
                    </dl>
                </div>

                <!-- Auto-refresh notice when running -->
                <div v-if="j.is_running" class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-700 rounded-lg p-4 text-sm text-blue-700 dark:text-blue-300">
                    <i class="fas fa-spinner fa-spin mr-2"></i>
                    Job is running. This page auto-refreshes every 5 seconds.
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { computed, ref, onMounted, onUnmounted } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

const props = defineProps({
    job: { type: Object, required: true },
})

const j = computed(() => props.job.data || props.job)
const busy = ref(false)

const statusClass = computed(() => {
    const map = {
        pending: 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300',
        running: 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300',
        completed: 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300',
        failed: 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300',
        cancelled: 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300',
    }
    return map[j.value.status] || map.pending
})

const statusIcon = computed(() => {
    const map = {
        pending: 'fas fa-clock',
        running: 'fas fa-spinner fa-spin',
        completed: 'fas fa-check',
        failed: 'fas fa-times',
        cancelled: 'fas fa-ban',
    }
    return map[j.value.status] || 'fas fa-clock'
})

const formatDate = (date) => {
    if (!date) return '—'
    return new Date(date).toLocaleString('en-US', { dateStyle: 'medium', timeStyle: 'short' })
}

const cancel = () => {
    if (!confirm('Cancel this job?')) return
    busy.value = true
    router.post(route('inventory.bulk.jobs.cancel', j.value.id), {}, {
        preserveScroll: true,
        onFinish: () => { busy.value = false },
    })
}

const retry = () => {
    busy.value = true
    router.post(route('inventory.bulk.jobs.retry', j.value.id), {}, {
        preserveScroll: true,
        onFinish: () => { busy.value = false },
    })
}

let refreshTimer = null
onMounted(() => {
    if (j.value.is_running) {
        refreshTimer = setInterval(() => {
            router.reload({ preserveScroll: true, only: ['job'] })
        }, 5000)
    }
})
onUnmounted(() => {
    if (refreshTimer) clearInterval(refreshTimer)
})
</script>
