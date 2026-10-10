<script setup>
import { reactive, watch } from 'vue'
import { router, Head, Link } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { route } from 'ziggy-js'

const props = defineProps({
    messages: { type: Object, required: true },
    stats: { type: Object, default: () => ({}) },
    filters: { type: Object, default: () => ({}) },
})

const currentFilters = reactive({ ...props.filters })

const timeAgo = (date) => {
    if (!date) return '-'
    const now = new Date()
    const then = new Date(date)
    const seconds = Math.floor((now - then) / 1000)
    if (seconds < 60) return 'just now'
    const minutes = Math.floor(seconds / 60)
    if (minutes < 60) return minutes + 'm ago'
    const hours = Math.floor(minutes / 60)
    if (hours < 24) return hours + 'h ago'
    const days = Math.floor(hours / 24)
    if (days < 30) return days + 'd ago'
    return then.toLocaleDateString('en-US', { month: 'short', day: 'numeric' })
}

const truncate = (text, length = 120) => {
    if (!text) return ''
    return text.length > length ? text.substring(0, length) + '...' : text
}

const marketplaceColor = (marketplace) => {
    const colors = {
        ebay: 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300',
        shopify: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300',
        amazon: 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-300',
    }
    return colors[marketplace] || 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300'
}

function applyFilters() {
    router.get(route('messages.inbox'), currentFilters, { preserveState: true, preserveScroll: true })
}

function clearFilters() {
    Object.assign(currentFilters, { marketplace: '', status: '' })
    applyFilters()
}

function setFilter(key, value) {
    currentFilters[key] = currentFilters[key] === value ? '' : value
    applyFilters()
}

watch(() => currentFilters.marketplace, applyFilters)
watch(() => currentFilters.status, applyFilters)
</script>

<template>
    <Head title="Messages" />
    <AppLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200">Messages</h2>
        </template>

        <div class="py-6">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <!-- Stats Bar -->
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
                        <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Unread</p>
                        <p class="text-2xl font-bold text-red-600 dark:text-red-400 mt-1">{{ stats.unread || 0 }}</p>
                    </div>
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
                        <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Total Messages</p>
                        <p class="text-2xl font-bold text-gray-900 dark:text-white mt-1">{{ stats.total || 0 }}</p>
                    </div>
                    <template v-for="(data, marketplace) in (stats.by_marketplace || {})" :key="marketplace">
                        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
                            <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ marketplace }}</p>
                            <p class="text-2xl font-bold text-gray-900 dark:text-white mt-1">{{ data.total || 0 }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ data.unread || 0 }} unread</p>
                        </div>
                    </template>
                </div>

                <!-- Filter Tabs -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow mb-4">
                    <div class="border-b border-gray-200 dark:border-gray-700 px-4">
                        <nav class="flex space-x-4 -mb-px overflow-x-auto">
                            <button
                                @click="clearFilters()"
                                :class="[
                                    'py-3 px-2 text-sm font-medium border-b-2 whitespace-nowrap',
                                    !currentFilters.marketplace && !currentFilters.status
                                        ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400'
                                        : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300'
                                ]"
                            >
                                All
                            </button>
                            <button
                                @click="setFilter('status', 'unread')"
                                :class="[
                                    'py-3 px-2 text-sm font-medium border-b-2 whitespace-nowrap',
                                    currentFilters.status === 'unread'
                                        ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400'
                                        : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300'
                                ]"
                            >
                                Unread
                            </button>
                            <button
                                @click="setFilter('marketplace', 'ebay')"
                                :class="[
                                    'py-3 px-2 text-sm font-medium border-b-2 whitespace-nowrap',
                                    currentFilters.marketplace === 'ebay'
                                        ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400'
                                        : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300'
                                ]"
                            >
                                eBay
                            </button>
                            <button
                                @click="setFilter('marketplace', 'shopify')"
                                :class="[
                                    'py-3 px-2 text-sm font-medium border-b-2 whitespace-nowrap',
                                    currentFilters.marketplace === 'shopify'
                                        ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400'
                                        : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300'
                                ]"
                            >
                                Shopify
                            </button>
                            <button
                                @click="setFilter('marketplace', 'amazon')"
                                :class="[
                                    'py-3 px-2 text-sm font-medium border-b-2 whitespace-nowrap',
                                    currentFilters.marketplace === 'amazon'
                                        ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400'
                                        : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300'
                                ]"
                            >
                                Amazon
                            </button>
                        </nav>
                    </div>
                </div>

                <!-- Message List -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden">
                    <div v-if="messages.data && messages.data.length === 0" class="p-12 text-center">
                        <svg class="mx-auto h-12 w-12 text-gray-400 dark:text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75" />
                        </svg>
                        <h3 class="mt-4 text-lg font-medium text-gray-900 dark:text-white">No messages</h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Messages from marketplace buyers will appear here.</p>
                    </div>

                    <ul v-else class="divide-y divide-gray-200 dark:divide-gray-700">
                        <li v-for="msg in messages.data" :key="msg.id">
                            <Link
                                :href="route('messages.thread', msg.id)"
                                class="block hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors"
                            >
                                <div class="px-4 py-4 sm:px-6">
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center min-w-0 flex-1">
                                            <!-- Unread indicator -->
                                            <span
                                                v-if="msg.status === 'unread'"
                                                class="flex-shrink-0 w-2.5 h-2.5 rounded-full bg-blue-500 mr-3"
                                            ></span>
                                            <span v-else class="flex-shrink-0 w-2.5 h-2.5 mr-3"></span>

                                            <div class="min-w-0 flex-1">
                                                <div class="flex items-center gap-2">
                                                    <p :class="[
                                                        'text-sm truncate',
                                                        msg.status === 'unread'
                                                            ? 'font-bold text-gray-900 dark:text-white'
                                                            : 'font-medium text-gray-700 dark:text-gray-300'
                                                    ]">
                                                        {{ msg.sender_name || 'Unknown Sender' }}
                                                    </p>
                                                    <span :class="[
                                                        'inline-flex items-center px-2 py-0.5 rounded text-xs font-medium',
                                                        marketplaceColor(msg.marketplace)
                                                    ]">
                                                        {{ msg.marketplace }}
                                                    </span>
                                                    <span v-if="msg.status === 'replied'" class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-emerald-100 text-emerald-800 dark:bg-emerald-900 dark:text-emerald-300">
                                                        replied
                                                    </span>
                                                    <Link
                                                        v-if="msg.product"
                                                        :href="route('inventory.products.show', msg.product.id)"
                                                        class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-300 hover:bg-purple-200 dark:hover:bg-purple-800"
                                                        @click.stop
                                                    >
                                                        {{ msg.product.sku || msg.product.name }}
                                                    </Link>
                                                </div>
                                                <p :class="[
                                                    'text-sm mt-0.5',
                                                    msg.status === 'unread'
                                                        ? 'font-semibold text-gray-800 dark:text-gray-200'
                                                        : 'text-gray-600 dark:text-gray-400'
                                                ]">
                                                    {{ msg.subject || '(no subject)' }}
                                                </p>
                                                <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5 truncate">
                                                    {{ truncate(msg.body) }}
                                                </p>
                                            </div>
                                        </div>
                                        <div class="ml-4 flex-shrink-0 text-right">
                                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                                {{ timeAgo(msg.received_at || msg.created_at) }}
                                            </p>
                                            <p v-if="msg.order" class="text-xs text-gray-400 dark:text-gray-500 mt-1">
                                                Order #{{ msg.order.order_number || msg.order.id }}
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </Link>
                        </li>
                    </ul>

                    <!-- Pagination -->
                    <div v-if="messages.links && messages.last_page > 1" class="border-t border-gray-200 dark:border-gray-700 px-4 py-3 flex items-center justify-between">
                        <div class="flex-1 flex justify-between sm:hidden">
                            <Link
                                v-if="messages.prev_page_url"
                                :href="messages.prev_page_url"
                                class="relative inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 text-sm font-medium rounded-md text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700"
                            >
                                Previous
                            </Link>
                            <Link
                                v-if="messages.next_page_url"
                                :href="messages.next_page_url"
                                class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 text-sm font-medium rounded-md text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700"
                            >
                                Next
                            </Link>
                        </div>
                        <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                            <p class="text-sm text-gray-700 dark:text-gray-300">
                                Showing <span class="font-medium">{{ messages.from }}</span> to <span class="font-medium">{{ messages.to }}</span> of <span class="font-medium">{{ messages.total }}</span> messages
                            </p>
                            <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px">
                                <template v-for="(link, idx) in messages.links" :key="idx">
                                    <Link
                                        v-if="link.url"
                                        :href="link.url"
                                        :class="[
                                            'relative inline-flex items-center px-4 py-2 border text-sm font-medium',
                                            link.active
                                                ? 'z-10 bg-indigo-50 dark:bg-indigo-900 border-indigo-500 text-indigo-600 dark:text-indigo-300'
                                                : 'bg-white dark:bg-gray-800 border-gray-300 dark:border-gray-600 text-gray-500 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700',
                                            idx === 0 ? 'rounded-l-md' : '',
                                            idx === messages.links.length - 1 ? 'rounded-r-md' : '',
                                        ]"
                                        v-html="link.label"
                                        preserve-state
                                        preserve-scroll
                                    />
                                    <span
                                        v-else
                                        :class="[
                                            'relative inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-sm font-medium text-gray-400 dark:text-gray-500 cursor-default',
                                            idx === 0 ? 'rounded-l-md' : '',
                                            idx === messages.links.length - 1 ? 'rounded-r-md' : '',
                                        ]"
                                        v-html="link.label"
                                    />
                                </template>
                            </nav>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
