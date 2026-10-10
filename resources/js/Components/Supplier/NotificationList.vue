<template>
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700">
        <!-- Header -->
        <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-2">
                    <BellIcon class="w-5 h-5 text-gray-500 dark:text-gray-400" />
                    <h3 class="font-medium text-gray-900 dark:text-white">Notifications</h3>
                    <span 
                        v-if="unreadCount > 0"
                        class="px-2 py-0.5 text-xs font-medium bg-red-100 text-red-600 dark:bg-red-900 dark:text-red-200 rounded-full"
                    >
                        {{ unreadCount }}
                    </span>
                </div>
                <button 
                    v-if="unreadCount > 0"
                    @click="markAllRead"
                    class="text-sm text-indigo-600 dark:text-indigo-400 hover:underline"
                >
                    Mark all read
                </button>
            </div>
        </div>

        <!-- Notification List -->
        <div class="divide-y divide-gray-200 dark:divide-gray-700 max-h-96 overflow-y-auto">
            <TransitionGroup name="notification">
                <div 
                    v-for="notification in displayedNotifications" 
                    :key="notification.id"
                    :class="[
                        'px-4 py-3 hover:bg-gray-50 dark:hover:bg-gray-700 cursor-pointer transition-colors',
                        !notification.read_at && 'bg-indigo-50 dark:bg-indigo-900/20'
                    ]"
                    @click="handleNotificationClick(notification)"
                >
                    <div class="flex items-start space-x-3">
                        <div 
                            :class="getTypeColorClass(notification.type)"
                            class="flex-shrink-0 w-8 h-8 rounded-full flex items-center justify-center"
                        >
                            <component :is="getTypeIcon(notification.type)" class="w-4 h-4 text-white" />
                        </div>
                        <div class="flex-1 min-w-0">
                            <p 
                                :class="[
                                    'text-sm',
                                    notification.read_at 
                                        ? 'text-gray-600 dark:text-gray-400' 
                                        : 'text-gray-900 dark:text-white font-medium'
                                ]"
                            >
                                {{ notification.title }}
                            </p>
                            <p v-if="notification.message" class="text-xs text-gray-500 dark:text-gray-400 mt-0.5 line-clamp-2">
                                {{ notification.message }}
                            </p>
                            <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">
                                {{ formatTimeAgo(notification.created_at) }}
                            </p>
                        </div>
                        <button 
                            v-if="!notification.read_at"
                            @click.stop="markRead(notification)"
                            class="flex-shrink-0 p-1 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
                            title="Mark as read"
                        >
                            <CheckIcon class="w-4 h-4" />
                        </button>
                    </div>
                </div>
            </TransitionGroup>

            <!-- Empty State -->
            <div v-if="notifications.length === 0" class="px-4 py-8 text-center">
                <BellSlashIcon class="w-12 h-12 text-gray-300 dark:text-gray-600 mx-auto mb-3" />
                <p class="text-sm text-gray-500 dark:text-gray-400">No notifications</p>
            </div>
        </div>

        <!-- Footer -->
        <div v-if="notifications.length > 0" class="px-4 py-3 border-t border-gray-200 dark:border-gray-700">
            <div class="flex items-center justify-between">
                <button 
                    v-if="notifications.length > limit"
                    @click="showAll = !showAll"
                    class="text-sm text-indigo-600 dark:text-indigo-400 hover:underline"
                >
                    {{ showAll ? 'Show less' : `View all (${notifications.length})` }}
                </button>
                <Link 
                    v-if="viewAllLink"
                    :href="viewAllLink"
                    class="text-sm text-indigo-600 dark:text-indigo-400 hover:underline ml-auto"
                >
                    Manage notifications →
                </Link>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import { 
    BellIcon, 
    BellSlashIcon,
    CheckIcon,
    CheckCircleIcon,
    ExclamationTriangleIcon,
    InformationCircleIcon,
    CurrencyDollarIcon,
    ChartBarIcon,
    ArrowUpIcon,
    ArrowDownIcon,
    DocumentTextIcon,
    ClockIcon
} from '@heroicons/vue/24/outline';

const props = defineProps({
    notifications: {
        type: Array,
        default: () => []
    },
    limit: {
        type: Number,
        default: 5
    },
    viewAllLink: {
        type: String,
        default: null
    }
});

const emit = defineEmits(['mark-read', 'mark-all-read', 'click']);

const showAll = ref(false);

const unreadCount = computed(() => {
    return props.notifications.filter(n => !n.read_at).length;
});

const displayedNotifications = computed(() => {
    if (showAll.value) {
        return props.notifications;
    }
    return props.notifications.slice(0, props.limit);
});

function getTypeIcon(type) {
    const icons = {
        'tier_upgrade': ArrowUpIcon,
        'tier_downgrade': ArrowDownIcon,
        'settlement_ready': CurrencyDollarIcon,
        'settlement_paid': CheckCircleIcon,
        'performance_alert': ChartBarIcon,
        'performance_warning': ExclamationTriangleIcon,
        'document_required': DocumentTextIcon,
        'contract_expiring': ClockIcon,
        'info': InformationCircleIcon,
        'warning': ExclamationTriangleIcon,
        'success': CheckCircleIcon
    };
    return icons[type] || InformationCircleIcon;
}

function getTypeColorClass(type) {
    const colors = {
        'tier_upgrade': 'bg-green-500',
        'tier_downgrade': 'bg-red-500',
        'settlement_ready': 'bg-blue-500',
        'settlement_paid': 'bg-green-600',
        'performance_alert': 'bg-yellow-500',
        'performance_warning': 'bg-orange-500',
        'document_required': 'bg-purple-500',
        'contract_expiring': 'bg-amber-500',
        'info': 'bg-blue-400',
        'warning': 'bg-yellow-500',
        'success': 'bg-green-500'
    };
    return colors[type] || 'bg-gray-400';
}

function formatTimeAgo(date) {
    const now = new Date();
    const then = new Date(date);
    const diffMs = now - then;
    const diffSec = Math.floor(diffMs / 1000);
    const diffMin = Math.floor(diffSec / 60);
    const diffHours = Math.floor(diffMin / 60);
    const diffDays = Math.floor(diffHours / 24);
    
    if (diffSec < 60) return 'Just now';
    if (diffMin < 60) return `${diffMin}m ago`;
    if (diffHours < 24) return `${diffHours}h ago`;
    if (diffDays === 1) return 'Yesterday';
    if (diffDays < 7) return `${diffDays}d ago`;
    
    return then.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
}

function markRead(notification) {
    emit('mark-read', notification);
    
    if (route().has('supplier.notifications.mark-read')) {
        router.post(route('supplier.notifications.mark-read', notification.id), {}, {
            preserveScroll: true,
            preserveState: true
        });
    }
}

function markAllRead() {
    emit('mark-all-read');
    
    if (route().has('supplier.notifications.mark-all-read')) {
        router.post(route('supplier.notifications.mark-all-read'), {}, {
            preserveScroll: true,
            preserveState: true
        });
    }
}

function handleNotificationClick(notification) {
    if (!notification.read_at) {
        markRead(notification);
    }
    
    emit('click', notification);
    
    // Navigate to action URL if available
    if (notification.action_url) {
        router.visit(notification.action_url);
    }
}
</script>

<style scoped>
.notification-enter-active,
.notification-leave-active {
    transition: all 0.3s ease;
}
.notification-enter-from,
.notification-leave-to {
    opacity: 0;
    transform: translateX(-20px);
}
.line-clamp-2 {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
</style>
