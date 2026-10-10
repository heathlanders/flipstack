<template>
    <AppLayout :title="`${user.name} — Activity`">
        <template #header>
            <div class="flex items-center space-x-4">
                <Link :href="route('users.show', user.id)" class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">
                    <i class="fas fa-arrow-left"></i>
                </Link>
                <div class="flex items-center space-x-3">
                    <img v-if="user.avatar" :src="user.avatar" :alt="user.name" class="h-10 w-10 rounded-full" />
                    <div>
                        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">{{ user.name }}</h2>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Activity log</p>
                    </div>
                </div>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-5xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm">
                    <div v-if="activities.data?.length" class="divide-y divide-gray-200 dark:divide-gray-700">
                        <div v-for="a in activities.data" :key="a.id" class="px-6 py-4 hover:bg-gray-50 dark:hover:bg-gray-700/50">
                            <div class="flex items-start justify-between">
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center space-x-2 mb-1">
                                        <span class="px-2 py-0.5 text-xs font-mono font-medium rounded bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300">
                                            {{ a.action }}
                                        </span>
                                        <span class="text-xs text-gray-500 dark:text-gray-400">{{ a.time_ago }}</span>
                                    </div>
                                    <p class="text-sm text-gray-900 dark:text-white">{{ a.description || '—' }}</p>
                                    <div v-if="a.metadata && Object.keys(a.metadata).length" class="mt-2">
                                        <details class="text-xs">
                                            <summary class="cursor-pointer text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200">Details</summary>
                                            <pre class="mt-1 p-2 bg-gray-50 dark:bg-gray-900 rounded text-gray-700 dark:text-gray-300 overflow-x-auto">{{ JSON.stringify(a.metadata, null, 2) }}</pre>
                                        </details>
                                    </div>
                                </div>
                                <div class="ml-4 text-right text-xs text-gray-500 dark:text-gray-400 flex-shrink-0">
                                    <div>{{ a.created_at }}</div>
                                    <div v-if="a.ip_address" class="font-mono mt-1">{{ a.ip_address }}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div v-else class="px-6 py-12 text-center text-sm text-gray-500 dark:text-gray-400">
                        <i class="fas fa-history text-3xl mb-2 block"></i>
                        No activity recorded for this user.
                    </div>

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
import { Link } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

defineProps({
    user: { type: Object, required: true },
    activities: { type: Object, required: true },
})
</script>
