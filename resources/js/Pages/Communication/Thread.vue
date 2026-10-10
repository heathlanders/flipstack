<script setup>
import { ref } from 'vue'
import { router, Head, Link, useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { route } from 'ziggy-js'

const props = defineProps({
    message: { type: Object, required: true },
    threadMessages: { type: Array, default: () => [] },
})

const replyForm = useForm({
    body: '',
})

const formatDateTime = (date) => {
    if (!date) return '-'
    return new Date(date).toLocaleString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    })
}

const marketplaceColor = (marketplace) => {
    const colors = {
        ebay: 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300',
        shopify: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300',
        amazon: 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-300',
    }
    return colors[marketplace] || 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300'
}

function sendReply() {
    replyForm.post(route('messages.reply', props.message.id), {
        preserveScroll: true,
        onSuccess: () => {
            replyForm.reset('body')
        },
    })
}

function markAsRead() {
    router.post(route('messages.read', props.message.id), {}, { preserveScroll: true })
}

function archiveMessage() {
    router.post(route('messages.archive', props.message.id))
}
</script>

<template>
    <Head :title="message.subject || 'Message Thread'" />
    <AppLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-3">
                    <Link
                        :href="route('messages.inbox')"
                        class="text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300"
                    >
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                        </svg>
                    </Link>
                    <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200">
                        {{ message.subject || 'Message Thread' }}
                    </h2>
                </div>
                <div class="flex items-center space-x-2">
                    <button
                        v-if="message.status === 'unread'"
                        @click="markAsRead"
                        class="inline-flex items-center px-3 py-1.5 border border-gray-300 dark:border-gray-600 text-sm font-medium rounded-md text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700"
                    >
                        Mark Read
                    </button>
                    <button
                        @click="archiveMessage"
                        class="inline-flex items-center px-3 py-1.5 border border-gray-300 dark:border-gray-600 text-sm font-medium rounded-md text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700"
                    >
                        Archive
                    </button>
                </div>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
                <!-- Message Meta -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4 mb-6">
                    <div class="flex items-center justify-between flex-wrap gap-2">
                        <div class="flex items-center space-x-3">
                            <span :class="[
                                'inline-flex items-center px-2.5 py-0.5 rounded text-xs font-medium',
                                marketplaceColor(message.marketplace)
                            ]">
                                {{ message.marketplace }}
                            </span>
                            <span v-if="message.customer" class="text-sm text-gray-700 dark:text-gray-300">
                                From:
                                <Link
                                    v-if="message.customer_id"
                                    :href="route('customers.show', message.customer_id)"
                                    class="text-indigo-600 dark:text-indigo-400 hover:underline"
                                >
                                    {{ message.customer.name || message.sender_name }}
                                </Link>
                                <span v-else>{{ message.sender_name || 'Unknown' }}</span>
                            </span>
                            <span v-else class="text-sm text-gray-700 dark:text-gray-300">
                                From: {{ message.sender_name || 'Unknown' }}
                            </span>
                        </div>
                        <div class="flex items-center space-x-3">
                            <Link
                                v-if="message.order_id && message.order"
                                :href="route('fulfillment.orders.show', message.order_id)"
                                class="inline-flex items-center px-2.5 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600"
                            >
                                Order #{{ message.order.order_number || message.order_id }}
                            </Link>
                            <Link
                                v-if="message.product"
                                :href="route('inventory.products.show', message.product.id)"
                                class="inline-flex items-center px-2.5 py-0.5 rounded text-xs font-medium bg-purple-100 text-purple-800 dark:bg-purple-700 dark:text-purple-300 hover:bg-purple-200 dark:hover:bg-purple-600"
                            >
                                Product: {{ message.product.name }} ({{ message.product.sku }})
                            </Link>
                            <span class="text-xs text-gray-500 dark:text-gray-400">
                                {{ formatDateTime(message.received_at || message.created_at) }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Thread Timeline -->
                <div class="space-y-4 mb-6">
                    <template v-for="msg in threadMessages" :key="msg.id">
                        <!-- Inbound message (left aligned) -->
                        <div v-if="msg.direction === 'inbound'" class="flex justify-start">
                            <div class="max-w-[80%]">
                                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
                                    <div class="flex items-center justify-between mb-2">
                                        <p class="text-sm font-medium text-gray-900 dark:text-white">
                                            {{ msg.sender_name || 'Buyer' }}
                                        </p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400 ml-4">
                                            {{ formatDateTime(msg.received_at || msg.created_at) }}
                                        </p>
                                    </div>
                                    <div class="text-sm text-gray-700 dark:text-gray-300 whitespace-pre-wrap">{{ msg.body }}</div>
                                </div>
                            </div>
                        </div>

                        <!-- Outbound message (right aligned) -->
                        <div v-else class="flex justify-end">
                            <div class="max-w-[80%]">
                                <div class="bg-indigo-50 dark:bg-indigo-900/30 rounded-lg shadow p-4">
                                    <div class="flex items-center justify-between mb-2">
                                        <p class="text-sm font-medium text-indigo-900 dark:text-indigo-300">
                                            {{ msg.sender_name || 'You' }}
                                        </p>
                                        <p class="text-xs text-indigo-500 dark:text-indigo-400 ml-4">
                                            {{ formatDateTime(msg.received_at || msg.created_at) }}
                                        </p>
                                    </div>
                                    <div class="text-sm text-indigo-800 dark:text-indigo-200 whitespace-pre-wrap">{{ msg.body }}</div>
                                </div>
                            </div>
                        </div>

                        <!-- Replies to this message -->
                        <template v-if="msg.replies && msg.replies.length">
                            <div v-for="reply in msg.replies" :key="reply.id" class="flex justify-end">
                                <div class="max-w-[80%]">
                                    <div class="bg-indigo-50 dark:bg-indigo-900/30 rounded-lg shadow p-4">
                                        <div class="flex items-center justify-between mb-2">
                                            <p class="text-sm font-medium text-indigo-900 dark:text-indigo-300">
                                                {{ reply.sender?.name || 'You' }}
                                            </p>
                                            <div class="flex items-center space-x-2 ml-4">
                                                <span v-if="reply.status === 'failed'" class="text-xs text-red-500">Failed</span>
                                                <p class="text-xs text-indigo-500 dark:text-indigo-400">
                                                    {{ formatDateTime(reply.sent_at || reply.created_at) }}
                                                </p>
                                            </div>
                                        </div>
                                        <div class="text-sm text-indigo-800 dark:text-indigo-200 whitespace-pre-wrap">{{ reply.body }}</div>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </template>
                </div>

                <!-- Reply Form -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
                    <h3 class="text-sm font-medium text-gray-900 dark:text-white mb-3">Reply</h3>
                    <form @submit.prevent="sendReply">
                        <textarea
                            v-model="replyForm.body"
                            rows="4"
                            class="block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                            placeholder="Type your reply..."
                        ></textarea>
                        <p v-if="replyForm.errors.body" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ replyForm.errors.body }}</p>
                        <div class="mt-3 flex justify-end">
                            <button
                                type="submit"
                                :disabled="replyForm.processing || !replyForm.body.trim()"
                                :class="[
                                    'inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white',
                                    replyForm.processing || !replyForm.body.trim()
                                        ? 'bg-indigo-400 dark:bg-indigo-600 cursor-not-allowed'
                                        : 'bg-indigo-600 hover:bg-indigo-700 dark:bg-indigo-500 dark:hover:bg-indigo-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500'
                                ]"
                            >
                                <span v-if="replyForm.processing">Sending...</span>
                                <span v-else>Send Reply</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
