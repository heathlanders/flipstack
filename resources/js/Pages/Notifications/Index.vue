<template>
  <AppLayout title="Notifications">
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
        Notifications
      </h2>
    </template>

    <div class="py-12">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg">
          <!-- Filters -->
          <div class="p-6 border-b border-gray-200 dark:border-gray-700">
            <div class="flex flex-wrap items-center gap-4">
              <!-- Filter Tabs -->
              <div class="flex space-x-2">
                <button
                  @click="setFilter(null)"
                  :class="[
                    'px-4 py-2 rounded-lg text-sm font-medium transition',
                    !filters.filter 
                      ? 'bg-blue-600 text-white' 
                      : 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600'
                  ]"
                >
                  All
                </button>
                <button
                  @click="setFilter('unread')"
                  :class="[
                    'px-4 py-2 rounded-lg text-sm font-medium transition',
                    filters.filter === 'unread' 
                      ? 'bg-blue-600 text-white' 
                      : 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600'
                  ]"
                >
                  Unread
                  <span v-if="unreadCount > 0" class="ml-2 px-2 py-0.5 bg-white bg-opacity-20 rounded-full text-xs">
                    {{ unreadCount }}
                  </span>
                </button>
              </div>

              <!-- Category Filter -->
              <select
                v-model="selectedCategory"
                @change="setCategory"
                class="px-4 py-2 rounded-lg text-sm bg-gray-100 dark:bg-gray-700 border-0 text-gray-700 dark:text-gray-300"
              >
                <option :value="null">All Categories</option>
                <option value="orders">Orders</option>
                <option value="inventory">Inventory</option>
                <option value="financial">Financial</option>
                <option value="platform">Platform</option>
                <option value="customer">Customer</option>
                <option value="system">System</option>
              </select>

              <!-- Mark All as Read -->
              <button
                v-if="unreadCount > 0"
                @click="markAllAsRead"
                class="ml-auto px-4 py-2 text-sm font-medium text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300"
              >
                Mark all as read
              </button>
            </div>
          </div>

          <!-- Notifications List -->
          <div class="divide-y divide-gray-200 dark:divide-gray-700">
            <div
              v-for="notification in notifications.data"
              :key="notification.id"
              @click="handleNotificationClick(notification)"
              :class="[
                'p-6 hover:bg-gray-50 dark:hover:bg-gray-700 cursor-pointer transition border-l-4',
                !notification.read_at ? 'bg-blue-50 dark:bg-blue-900/10' : '',
                getPriorityBorderClass(notification.data?.priority)
              ]"
            >
              <div class="flex items-start space-x-4">
                <div class="flex-1 min-w-0">
                  <div class="flex items-start justify-between">
                    <div class="flex-1">
                      <div class="flex items-center space-x-2">
                        <h3 v-if="notification.data?.title" class="text-base font-semibold text-gray-900 dark:text-gray-100">
                          {{ notification.data.title }}
                        </h3>
                        <span
                          v-if="notification.data?.priority"
                          :class="[
                            'px-2 py-0.5 text-xs font-medium rounded-full',
                            getPriorityBadgeClass(notification.data.priority)
                          ]"
                        >
                          {{ notification.data.priority.toUpperCase() }}
                        </span>
                      </div>
                      <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                        {{ notification.data.message }}
                      </p>
                      <p class="mt-2 text-xs text-gray-500 dark:text-gray-500">
                        {{ formatTime(notification.created_at) }}
                      </p>
                    </div>

                    <!-- Unread Indicator -->
                    <div v-if="!notification.read_at" class="flex-shrink-0 ml-4">
                      <span class="inline-block w-3 h-3 bg-blue-600 rounded-full"></span>
                    </div>
                  </div>

                  <!-- Action Button -->
                  <div v-if="notification.data.action_url" class="mt-3">
                    <button
                      @click.stop="handleAction(notification)"
                      class="text-sm font-medium text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300"
                    >
                      {{ notification.data.action_text || 'View Details' }} →
                    </button>
                  </div>
                </div>

                <!-- Delete Button -->
                <button
                  @click.stop="deleteNotification(notification.id)"
                  class="flex-shrink-0 p-2 text-gray-400 hover:text-red-600 dark:hover:text-red-400 transition"
                  title="Delete notification"
                >
                  <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                  </svg>
                </button>
              </div>
            </div>

            <!-- Empty State -->
            <div v-if="notifications.data.length === 0" class="p-12 text-center">
              <svg class="w-16 h-16 mx-auto mb-4 text-gray-300 dark:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
              </svg>
              <p class="text-lg text-gray-500 dark:text-gray-400">
                {{ filters.filter === 'unread' ? 'No unread notifications' : 'No notifications yet' }}
              </p>
            </div>
          </div>

          <!-- Pagination -->
          <div v-if="notifications.data.length > 0" class="p-6 border-t border-gray-200 dark:border-gray-700">
            <div class="flex items-center justify-between">
              <div class="text-sm text-gray-500 dark:text-gray-400">
                Showing {{ notifications.from }} to {{ notifications.to }} of {{ notifications.total }} notifications
              </div>
              <div class="flex space-x-2">
                <Link
                  v-if="notifications.prev_page_url"
                  :href="notifications.prev_page_url"
                  preserve-state
                  class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-600"
                >
                  Previous
                </Link>
                <Link
                  v-if="notifications.next_page_url"
                  :href="notifications.next_page_url"
                  preserve-state
                  class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-600"
                >
                  Next
                </Link>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import axios from 'axios';

const props = defineProps({
  notifications: Object,
  unreadCount: Number,
  filters: Object,
});

const selectedCategory = ref(props.filters.category);

const setFilter = (filter) => {
  router.get(route('notifications.index'), { filter }, {
    preserveState: true,
    preserveScroll: true,
  });
};

const setCategory = () => {
  router.get(route('notifications.index'), { 
    filter: props.filters.filter,
    category: selectedCategory.value 
  }, {
    preserveState: true,
    preserveScroll: true,
  });
};

const markAllAsRead = async () => {
  try {
    await axios.post('/api/notifications/mark-all-read');
    router.reload({ only: ['notifications', 'unreadCount'] });
  } catch (error) {
    console.error('Failed to mark all as read:', error);
  }
};

const handleNotificationClick = async (notification) => {
  if (!notification.read_at) {
    try {
      await axios.post(`/api/notifications/${notification.id}/read`);
    } catch (error) {
      console.error('Failed to mark as read:', error);
    }
  }

  if (notification.data.action_url) {
    router.visit(notification.data.action_url);
  }
};

const handleAction = (notification) => {
  if (notification.data.action_url) {
    router.visit(notification.data.action_url);
  }
};

const deleteNotification = async (id) => {
  if (confirm('Are you sure you want to delete this notification?')) {
    try {
      await axios.delete(`/api/notifications/${id}`);
      router.reload({ only: ['notifications', 'unreadCount'] });
    } catch (error) {
      console.error('Failed to delete notification:', error);
    }
  }
};

const getPriorityBorderClass = (priority) => {
  const borders = {
    critical: 'border-l-red-500',
    high: 'border-l-orange-500',
    medium: 'border-l-blue-500',
    low: 'border-l-green-500',
  };
  return borders[priority] || 'border-l-gray-300 dark:border-l-gray-600';
};

const getPriorityBadgeClass = (priority) => {
  const classes = {
    critical: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
    high: 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200',
    medium: 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
    low: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
  };
  return classes[priority] || 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200';
};

const formatTime = (timestamp) => {
  const date = new Date(timestamp);
  const now = new Date();
  const diff = Math.floor((now - date) / 1000);

  if (diff < 60) return 'Just now';
  if (diff < 3600) return `${Math.floor(diff / 60)} minutes ago`;
  if (diff < 86400) return `${Math.floor(diff / 3600)} hours ago`;
  if (diff < 604800) return `${Math.floor(diff / 86400)} days ago`;
  
  return date.toLocaleString();
};
</script>