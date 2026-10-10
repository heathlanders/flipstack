<script setup>
import { ref, computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import {
    BellIcon,
    CheckCircleIcon,
    ExclamationTriangleIcon,
    InformationCircleIcon,
    XCircleIcon,
    CheckIcon,
    TrashIcon,
    FunnelIcon,
    ArrowPathIcon,
} from '@heroicons/vue/24/outline';
import { BellAlertIcon } from '@heroicons/vue/24/solid';

const props = defineProps({
    notifications: Object,
    counts: Object,
    categories: Object,
    filters: Object,
});

const activeFilter = ref(props.filters.read || 'all');
const selectedCategory = ref(props.filters.category || '');
const loading = ref(false);

const typeIcons = {
    info: InformationCircleIcon,
    success: CheckCircleIcon,
    warning: ExclamationTriangleIcon,
    error: XCircleIcon,
    action_required: BellAlertIcon,
};

const typeColors = {
    info: 'text-blue-500 bg-blue-100 dark:bg-blue-900/30',
    success: 'text-green-500 bg-green-100 dark:bg-green-900/30',
    warning: 'text-yellow-500 bg-yellow-100 dark:bg-yellow-900/30',
    error: 'text-red-500 bg-red-100 dark:bg-red-900/30',
    action_required: 'text-purple-500 bg-purple-100 dark:bg-purple-900/30',
};

const categoryLabels = {
    settlement: 'Settlements',
    tier_change: 'Tier Changes',
    approval: 'Approvals',
    payment: 'Payments',
    rejection: 'Rejections',
    system: 'System',
};

const filterTabs = [
    { id: 'all', label: 'All', count: props.counts.all },
    { id: 'unread', label: 'Unread', count: props.counts.unread },
    { id: 'urgent', label: 'Urgent', count: props.counts.urgent },
];

const formatDate = (date) => {
    if (!date) return '';
    const d = new Date(date);
    const now = new Date();
    const diff = Math.floor((now - d) / 1000);

    if (diff < 60) return 'Just now';
    if (diff < 3600) return `${Math.floor(diff / 60)}m ago`;
    if (diff < 86400) return `${Math.floor(diff / 3600)}h ago`;
    if (diff < 604800) return `${Math.floor(diff / 86400)}d ago`;

    return d.toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: d.getFullYear() !== now.getFullYear() ? 'numeric' : undefined,
    });
};

const applyFilters = () => {
    const params = {};
    if (activeFilter.value !== 'all') {
        if (activeFilter.value === 'urgent') {
            params.priority = 'urgent';
        } else {
            params.read = activeFilter.value;
        }
    }
    if (selectedCategory.value) {
        params.category = selectedCategory.value;
    }

    router.get(route('supplier.notifications.index'), params, {
        preserveState: true,
        preserveScroll: true,
    });
};

const markAsRead = (notification) => {
    if (notification.is_read) return;

    router.post(route('supplier.notifications.read', notification.id), {}, {
        preserveScroll: true,
        onSuccess: () => {
            notification.is_read = true;
        },
    });
};

const markAllAsRead = () => {
    loading.value = true;
    router.post(route('supplier.notifications.read-all'), {}, {
        preserveScroll: true,
        onFinish: () => {
            loading.value = false;
        },
    });
};

const deleteNotification = (notification) => {
    if (!confirm('Delete this notification?')) return;

    router.delete(route('supplier.notifications.destroy', notification.id), {
        preserveScroll: true,
    });
};

const handleNotificationClick = (notification) => {
    markAsRead(notification);
    if (notification.action_url) {
        router.visit(notification.action_url);
    }
};
</script>

<template>
    <Head title="Notifications" />

    <AppLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-3">
                    <BellIcon class="w-6 h-6 text-gray-500" />
                    <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                        Notifications
                    </h2>
                    <span v-if="counts.unread > 0" class="px-2 py-0.5 text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200 rounded-full">
                        {{ counts.unread }} unread
                    </span>
                </div>
                <button
                    v-if="counts.unread > 0"
                    @click="markAllAsRead"
                    :disabled="loading"
                    class="inline-flex items-center px-4 py-2 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md font-semibold text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest shadow-sm hover:bg-gray-50 dark:hover:bg-gray-600 disabled:opacity-50"
                >
                    <CheckIcon class="w-4 h-4 mr-2" />
                    Mark all as read
                </button>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
                <!-- Filter Tabs -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 mb-6">
                    <div class="flex flex-wrap items-center justify-between p-4 gap-4">
                        <!-- Status Tabs -->
                        <div class="flex space-x-1 bg-gray-100 dark:bg-gray-700 rounded-lg p-1">
                            <button
                                v-for="tab in filterTabs"
                                :key="tab.id"
                                @click="activeFilter = tab.id; applyFilters()"
                                :class="[
                                    'px-4 py-2 text-sm font-medium rounded-md transition-colors',
                                    activeFilter === tab.id
                                        ? 'bg-white dark:bg-gray-600 text-gray-900 dark:text-white shadow-sm'
                                        : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white'
                                ]"
                            >
                                {{ tab.label }}
                                <span v-if="tab.count > 0" class="ml-1 text-xs text-gray-500 dark:text-gray-400">
                                    ({{ tab.count }})
                                </span>
                            </button>
                        </div>

                        <!-- Category Filter -->
                        <div class="flex items-center space-x-2">
                            <FunnelIcon class="w-4 h-4 text-gray-400" />
                            <select
                                v-model="selectedCategory"
                                @change="applyFilters"
                                class="text-sm border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md"
                            >
                                <option value="">All Categories</option>
                                <option v-for="(count, cat) in categories" :key="cat" :value="cat">
                                    {{ categoryLabels[cat] || cat }} ({{ count }})
                                </option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Notifications List -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
                    <ul v-if="notifications.data.length > 0" class="divide-y divide-gray-200 dark:divide-gray-700">
                        <li
                            v-for="notification in notifications.data"
                            :key="notification.id"
                            :class="[
                                'relative hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors',
                                !notification.is_read ? 'bg-blue-50/50 dark:bg-blue-900/10' : ''
                            ]"
                        >
                            <div class="flex items-start p-4">
                                <!-- Unread indicator -->
                                <div v-if="!notification.is_read" class="absolute left-0 top-0 bottom-0 w-1 bg-blue-500"></div>

                                <!-- Icon -->
                                <div
                                    :class="[
                                        'flex-shrink-0 w-10 h-10 rounded-full flex items-center justify-center mr-4',
                                        typeColors[notification.type] || 'text-gray-500 bg-gray-100 dark:bg-gray-700'
                                    ]"
                                >
                                    <component
                                        :is="typeIcons[notification.type] || BellIcon"
                                        class="w-5 h-5"
                                    />
                                </div>

                                <!-- Content -->
                                <div class="flex-1 min-w-0">
                                    <div
                                        @click="handleNotificationClick(notification)"
                                        class="cursor-pointer"
                                    >
                                        <div class="flex items-center justify-between">
                                            <p :class="[
                                                'text-sm font-medium',
                                                !notification.is_read
                                                    ? 'text-gray-900 dark:text-white'
                                                    : 'text-gray-700 dark:text-gray-300'
                                            ]">
                                                {{ notification.title }}
                                            </p>
                                            <span class="text-xs text-gray-500 dark:text-gray-400 whitespace-nowrap ml-4">
                                                {{ formatDate(notification.created_at) }}
                                            </span>
                                        </div>
                                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400 line-clamp-2">
                                            {{ notification.message }}
                                        </p>
                                        <div class="mt-2 flex items-center space-x-3">
                                            <span class="text-xs px-2 py-0.5 rounded-full bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-400">
                                                {{ categoryLabels[notification.category] || notification.category }}
                                            </span>
                                            <span v-if="notification.priority === 'urgent'" class="text-xs px-2 py-0.5 rounded-full bg-red-100 dark:bg-red-900 text-red-700 dark:text-red-300">
                                                Urgent
                                            </span>
                                            <span v-if="notification.action_url" class="text-xs text-indigo-600 dark:text-indigo-400 hover:underline">
                                                View Details
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Actions -->
                                <div class="flex-shrink-0 ml-4 flex items-center space-x-2">
                                    <button
                                        v-if="!notification.is_read"
                                        @click.stop="markAsRead(notification)"
                                        class="p-1 text-gray-400 hover:text-green-600 dark:hover:text-green-400 rounded"
                                        title="Mark as read"
                                    >
                                        <CheckIcon class="w-5 h-5" />
                                    </button>
                                    <button
                                        @click.stop="deleteNotification(notification)"
                                        class="p-1 text-gray-400 hover:text-red-600 dark:hover:text-red-400 rounded"
                                        title="Delete"
                                    >
                                        <TrashIcon class="w-5 h-5" />
                                    </button>
                                </div>
                            </div>
                        </li>
                    </ul>

                    <!-- Empty State -->
                    <div v-else class="p-12 text-center">
                        <BellIcon class="w-12 h-12 text-gray-300 dark:text-gray-600 mx-auto mb-4" />
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-2">
                            No notifications
                        </h3>
                        <p class="text-gray-500 dark:text-gray-400">
                            {{ activeFilter === 'unread' ? 'You\'re all caught up!' : 'You don\'t have any notifications yet.' }}
                        </p>
                    </div>

                    <!-- Pagination -->
                    <div v-if="notifications.data.length > 0 && notifications.last_page > 1" class="px-4 py-4 border-t border-gray-200 dark:border-gray-700">
                        <Pagination :links="notifications.links" />
                    </div>
                </div>

                <!-- Notification Preferences Link -->
                <div class="mt-6 text-center">
                    <Link
                        :href="route('supplier.notifications.preferences')"
                        class="text-sm text-indigo-600 dark:text-indigo-400 hover:underline"
                    >
                        Manage notification preferences
                    </Link>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
.line-clamp-2 {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
</style>
