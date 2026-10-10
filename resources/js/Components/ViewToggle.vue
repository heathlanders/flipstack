<script setup>
import { computed } from 'vue'

const props = defineProps({
    views: {
        type: Array,
        default: () => ['list', 'grid']
    },
    currentView: {
        type: String,
        default: 'list'
    }
})

const emit = defineEmits(['change'])

const viewIcons = {
    list: {
        icon: 'M4 6h16M4 10h16M4 14h16M4 18h16',
        label: 'List view'
    },
    grid: {
        icon: 'M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z',
        label: 'Grid view'
    },
    card: {
        icon: 'M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10',
        label: 'Card view'
    },
    table: {
        icon: 'M3 10h18M3 14h18m-9-4v8m-7 0h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z',
        label: 'Table view'
    }
}

const getViewConfig = (view) => {
    return viewIcons[view] || viewIcons.list
}
</script>

<template>
    <div class="flex items-center bg-gray-100 dark:bg-slate-700 rounded-lg p-1">
        <button
            v-for="view in views"
            :key="view"
            @click="emit('change', view)"
            :class="[
                'p-1.5 rounded transition-colors',
                currentView === view
                    ? 'bg-white dark:bg-slate-600 text-indigo-600 dark:text-indigo-400 shadow-sm'
                    : 'text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300'
            ]"
            :title="getViewConfig(view).label"
        >
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="getViewConfig(view).icon" />
            </svg>
        </button>
    </div>
</template>
