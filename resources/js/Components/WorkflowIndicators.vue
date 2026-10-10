<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { Link, router } from '@inertiajs/vue3'

const props = defineProps({
    initialCounts: {
        type: Object,
        default: () => ({
            intake: 0,
            to_list: 0,
            to_ship: 0,
            settlements: 0
        })
    },
    refreshInterval: {
        type: Number,
        default: 60000 // 1 minute
    },
    compact: {
        type: Boolean,
        default: false
    }
})

const emit = defineEmits(['indicator-click'])

// State
const counts = ref({ ...props.initialCounts })
const isLoading = ref(false)
const refreshTimer = ref(null)

// Workflow configurations
const workflows = computed(() => [
    {
        key: 'intake',
        label: 'Intake',
        icon: 'inbox',
        route: 'intake-queue.index',
        count: counts.value.intake,
        urgency: getUrgency(counts.value.intake, 20, 50),
        description: 'Products pending intake'
    },
    {
        key: 'to_list',
        label: 'To List',
        icon: 'tags',
        route: 'inventory.products.index',
        routeParams: { status: 'to_list' },
        count: counts.value.to_list,
        urgency: getUrgency(counts.value.to_list, 10, 30),
        description: 'Products ready to list'
    },
    {
        key: 'to_ship',
        label: 'To Ship',
        icon: 'shipping-fast',
        route: 'orders.index',
        routeParams: { status: 'pending_shipment' },
        count: counts.value.to_ship,
        urgency: getUrgency(counts.value.to_ship, 5, 15),
        description: 'Orders pending shipment'
    },
    {
        key: 'settlements',
        label: 'Settlements',
        icon: 'dollar-sign',
        route: 'admin.suppliers.index',
        routeParams: { pending_settlement: true },
        count: counts.value.settlements,
        urgency: getUrgency(counts.value.settlements, 3, 10),
        description: 'Pending supplier settlements'
    }
])

// Get urgency level based on count
function getUrgency(count, moderateThreshold, highThreshold) {
    if (count >= highThreshold) return 'high'
    if (count >= moderateThreshold) return 'moderate'
    return 'normal'
}

// Get urgency color classes
function getUrgencyClasses(urgency) {
    switch (urgency) {
        case 'high':
            return 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400 border-red-200 dark:border-red-800'
        case 'moderate':
            return 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400 border-amber-200 dark:border-amber-800'
        default:
            return 'bg-gray-100 text-gray-700 dark:bg-slate-700 dark:text-gray-300 border-gray-200 dark:border-slate-600'
    }
}

// Fetch updated counts
const fetchCounts = async () => {
    isLoading.value = true

    try {
        const response = await fetch('/api/workflow-counts', {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
            },
            credentials: 'same-origin'
        })

        if (response.ok) {
            const data = await response.json()
            counts.value = {
                intake: data.intake || 0,
                to_list: data.to_list || 0,
                to_ship: data.to_ship || 0,
                settlements: data.settlements || 0
            }
        }
    } catch (e) {
        console.error('Failed to fetch workflow counts:', e)
    } finally {
        isLoading.value = false
    }
}

// Handle indicator click
const handleClick = (workflow) => {
    emit('indicator-click', workflow)

    // Navigate to the workflow page
    if (workflow.route) {
        const routeParams = workflow.routeParams || {}
        router.visit(route(workflow.route, routeParams))
    }
}

// Setup periodic refresh
onMounted(() => {
    if (props.refreshInterval > 0) {
        refreshTimer.value = setInterval(fetchCounts, props.refreshInterval)
    }

    // Initial fetch if no counts provided
    if (Object.values(props.initialCounts).every(v => v === 0)) {
        fetchCounts()
    }

    // Listen for Pusher events if available
    if (window.Echo) {
        window.Echo.private('workflow-updates')
            .listen('WorkflowCountUpdated', (e) => {
                if (e.counts) {
                    counts.value = { ...counts.value, ...e.counts }
                }
            })
    }
})

onUnmounted(() => {
    if (refreshTimer.value) {
        clearInterval(refreshTimer.value)
    }
})

// Expose refresh method
defineExpose({ refresh: fetchCounts })
</script>

<template>
    <div :class="[
        'flex items-center gap-2',
        compact ? 'flex-wrap' : ''
    ]">
        <button
            v-for="workflow in workflows"
            :key="workflow.key"
            @click="handleClick(workflow)"
            :class="[
                'inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border text-sm font-medium transition-all hover:shadow-sm',
                getUrgencyClasses(workflow.urgency),
                workflow.count === 0 ? 'opacity-50' : ''
            ]"
            :title="workflow.description"
        >
            <i :class="['fas', `fa-${workflow.icon}`, 'text-xs']"></i>
            <span v-if="!compact" class="hidden sm:inline">{{ workflow.label }}:</span>
            <span class="font-bold">{{ workflow.count }}</span>

            <!-- Pulse animation for high urgency -->
            <span
                v-if="workflow.urgency === 'high' && workflow.count > 0"
                class="relative flex h-2 w-2"
            >
                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75"></span>
                <span class="relative inline-flex rounded-full h-2 w-2 bg-red-500"></span>
            </span>
        </button>

        <!-- Refresh Button -->
        <button
            v-if="!compact"
            @click="fetchCounts"
            :disabled="isLoading"
            class="p-1.5 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition-colors"
            title="Refresh counts"
        >
            <svg
                :class="['w-4 h-4', isLoading ? 'animate-spin' : '']"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
            >
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
            </svg>
        </button>
    </div>
</template>
