<template>
    <AppLayout :title="`Prompt History: ${prompt.name}`">
        <template #header>
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-4">
                    <Link :href="route('admin.ai-prompts.edit', prompt.id)" class="text-gray-500 hover:text-gray-700 dark:text-gray-400">
                        <ArrowLeftIcon class="w-5 h-5" />
                    </Link>
                    <div>
                        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                            Prompt History
                        </h2>
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ prompt.name }}</p>
                    </div>
                </div>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-5xl mx-auto sm:px-6 lg:px-8">
                <!-- Prompt Summary Card -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-6 mb-6">
                    <div class="grid grid-cols-4 gap-4 text-sm">
                        <div>
                            <p class="text-gray-500 dark:text-gray-400">Category</p>
                            <p class="font-medium text-gray-900 dark:text-white">{{ prompt.category_name || prompt.category_slug || 'Default' }}</p>
                        </div>
                        <div>
                            <p class="text-gray-500 dark:text-gray-400">Type</p>
                            <p class="font-medium text-gray-900 dark:text-white">{{ prompt.prompt_type_label || prompt.prompt_type }}</p>
                        </div>
                        <div>
                            <p class="text-gray-500 dark:text-gray-400">Status</p>
                            <span :class="[
                                'inline-flex items-center px-2 py-0.5 rounded text-xs font-medium',
                                prompt.is_active ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200'
                            ]">
                                {{ prompt.is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </div>
                        <div>
                            <p class="text-gray-500 dark:text-gray-400">Scope</p>
                            <span :class="[
                                'inline-flex items-center px-2 py-0.5 rounded text-xs font-medium',
                                prompt.is_system ? 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200' : 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200'
                            ]">
                                {{ prompt.is_system ? 'System' : 'Organization' }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- History Timeline -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white">Change History</h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400">All changes made to this prompt</p>
                    </div>

                    <div v-if="history.data && history.data.length > 0" class="divide-y divide-gray-200 dark:divide-gray-700">
                        <div v-for="entry in history.data" :key="entry.id" class="p-6">
                            <div class="flex items-start space-x-4">
                                <!-- Action Icon -->
                                <div :class="[
                                    'flex-shrink-0 w-10 h-10 rounded-full flex items-center justify-center',
                                    getActionStyle(entry.action).bg
                                ]">
                                    <component :is="getActionStyle(entry.action).icon" :class="['w-5 h-5', getActionStyle(entry.action).text]" />
                                </div>

                                <!-- Content -->
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center justify-between">
                                        <p class="text-sm font-medium text-gray-900 dark:text-white">
                                            {{ entry.action_label }}
                                        </p>
                                        <p class="text-sm text-gray-500 dark:text-gray-400">
                                            {{ entry.created_at_formatted }}
                                        </p>
                                    </div>

                                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                        <span v-if="entry.user">by {{ entry.user.name }}</span>
                                        <span v-else>by System</span>
                                    </p>

                                    <!-- Change Reason -->
                                    <p v-if="entry.change_reason" class="mt-2 text-sm text-gray-600 dark:text-gray-300 italic">
                                        "{{ entry.change_reason }}"
                                    </p>

                                    <!-- Changed Fields Summary -->
                                    <div v-if="entry.changed_fields && Object.keys(entry.changed_fields).length > 0" class="mt-3">
                                        <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-2">
                                            Changed Fields
                                        </p>
                                        <div class="flex flex-wrap gap-2">
                                            <span
                                                v-for="field in Object.keys(entry.changed_fields)"
                                                :key="field"
                                                class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300"
                                            >
                                                {{ formatFieldName(field) }}
                                            </span>
                                        </div>
                                    </div>

                                    <!-- Content Diff Toggle -->
                                    <div v-if="entry.previous_content || entry.new_content" class="mt-4">
                                        <button
                                            @click="toggleDiff(entry.id)"
                                            class="text-sm text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-300"
                                        >
                                            {{ expandedDiffs[entry.id] ? 'Hide content changes' : 'Show content changes' }}
                                        </button>

                                        <div v-if="expandedDiffs[entry.id]" class="mt-3 space-y-3">
                                            <!-- Previous Content -->
                                            <div v-if="entry.previous_content">
                                                <p class="text-xs font-medium text-red-600 dark:text-red-400 uppercase tracking-wider mb-1">
                                                    Previous Content
                                                </p>
                                                <pre class="bg-red-50 dark:bg-red-900/20 rounded-lg p-3 text-xs font-mono overflow-auto max-h-48 text-red-800 dark:text-red-200 whitespace-pre-wrap">{{ entry.previous_content }}</pre>
                                            </div>

                                            <!-- New Content -->
                                            <div v-if="entry.new_content">
                                                <p class="text-xs font-medium text-green-600 dark:text-green-400 uppercase tracking-wider mb-1">
                                                    New Content
                                                </p>
                                                <pre class="bg-green-50 dark:bg-green-900/20 rounded-lg p-3 text-xs font-mono overflow-auto max-h-48 text-green-800 dark:text-green-200 whitespace-pre-wrap">{{ entry.new_content }}</pre>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div v-else class="p-12 text-center">
                        <ClockIcon class="mx-auto h-12 w-12 text-gray-400" />
                        <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">No history yet</h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            Changes to this prompt will appear here.
                        </p>
                    </div>

                    <!-- Pagination -->
                    <div v-if="history.data && history.data.length > 0 && (history.prev_page_url || history.next_page_url)" class="px-6 py-4 border-t border-gray-200 dark:border-gray-700 flex items-center justify-between">
                        <div class="text-sm text-gray-500 dark:text-gray-400">
                            Showing {{ history.from }} to {{ history.to }} of {{ history.total }} entries
                        </div>
                        <div class="flex space-x-2">
                            <Link
                                v-if="history.prev_page_url"
                                :href="history.prev_page_url"
                                class="px-3 py-1 text-sm bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-gray-600"
                            >
                                Previous
                            </Link>
                            <Link
                                v-if="history.next_page_url"
                                :href="history.next_page_url"
                                class="px-3 py-1 text-sm bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-gray-600"
                            >
                                Next
                            </Link>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import {
    ArrowLeftIcon,
    ClockIcon,
    PlusCircleIcon,
    PencilSquareIcon,
    TrashIcon,
    CheckCircleIcon,
    XCircleIcon,
    DocumentDuplicateIcon,
    ArrowPathIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps({
    prompt: Object,
    history: Object,
});

const expandedDiffs = ref({});

function toggleDiff(entryId) {
    expandedDiffs.value[entryId] = !expandedDiffs.value[entryId];
}

function formatFieldName(field) {
    return field
        .replace(/_/g, ' ')
        .replace(/\b\w/g, l => l.toUpperCase());
}

function getActionStyle(action) {
    const styles = {
        created: {
            bg: 'bg-green-100 dark:bg-green-900/30',
            text: 'text-green-600 dark:text-green-400',
            icon: PlusCircleIcon,
        },
        updated: {
            bg: 'bg-blue-100 dark:bg-blue-900/30',
            text: 'text-blue-600 dark:text-blue-400',
            icon: PencilSquareIcon,
        },
        deleted: {
            bg: 'bg-red-100 dark:bg-red-900/30',
            text: 'text-red-600 dark:text-red-400',
            icon: TrashIcon,
        },
        activated: {
            bg: 'bg-emerald-100 dark:bg-emerald-900/30',
            text: 'text-emerald-600 dark:text-emerald-400',
            icon: CheckCircleIcon,
        },
        deactivated: {
            bg: 'bg-gray-100 dark:bg-gray-700',
            text: 'text-gray-600 dark:text-gray-400',
            icon: XCircleIcon,
        },
        duplicated: {
            bg: 'bg-purple-100 dark:bg-purple-900/30',
            text: 'text-purple-600 dark:text-purple-400',
            icon: DocumentDuplicateIcon,
        },
        restored: {
            bg: 'bg-amber-100 dark:bg-amber-900/30',
            text: 'text-amber-600 dark:text-amber-400',
            icon: ArrowPathIcon,
        },
    };

    return styles[action] || {
        bg: 'bg-gray-100 dark:bg-gray-700',
        text: 'text-gray-600 dark:text-gray-400',
        icon: ClockIcon,
    };
}
</script>
