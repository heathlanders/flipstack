<template>
  <div class="relative" v-click-outside="closeDropdown">
    <!-- Bell Icon with Badge -->
    <button 
      @click="toggleDropdown"
      class="relative p-2 text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-100 transition"
      aria-label="Notifications"
    >
      <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path 
          stroke-linecap="round" 
          stroke-linejoin="round" 
          stroke-width="2" 
          d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" 
        />
      </svg>
      
      <!-- Unread Badge -->
      <span 
        v-if="unreadCount > 0"
        class="absolute top-0 right-0 inline-flex items-center justify-center px-2 py-1 text-xs font-bold leading-none text-white transform translate-x-1/2 -translate-y-1/2 bg-red-600 rounded-full"
      >
        {{ unreadCount > 99 ? '99+' : unreadCount }}
      </span>
    </button>

    <!-- Dropdown -->
    <transition
      enter-active-class="transition ease-out duration-100"
      enter-from-class="transform opacity-0 scale-95"
      enter-to-class="transform opacity-100 scale-100"
      leave-active-class="transition ease-in duration-75"
      leave-from-class="transform opacity-100 scale-100"
      leave-to-class="transform opacity-0 scale-95"
    >
      <div 
        v-if="isOpen"
        class="absolute right-0 z-50 mt-2 w-96 bg-white dark:bg-gray-800 rounded-lg shadow-lg ring-1 ring-black ring-opacity-5 dark:ring-gray-700"
      >
        <!-- Header -->
        <div class="flex items-center justify-between px-4 py-3 border-b dark:border-gray-700">
          <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Notifications</h3>
          <button 
            @click="markAllAsRead"
            class="text-sm text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300"
            v-if="unreadCount > 0"
          >
            Mark all read
          </button>
        </div>

        <!-- Notifications List -->
        <div class="max-h-96 overflow-y-auto">
          <div
            v-for="notification in notifications"
            :key="notification.id"
            @click="handleNotificationClick(notification)"
            :class="[
              'px-4 py-3 border-b dark:border-gray-700 cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700 transition border-l-4',
              !notification.read_at ? 'bg-blue-50 dark:bg-blue-900/20' : '',
              getPriorityBorderClass(notification.data?.priority)
            ]"
          >
            <div class="flex items-start space-x-3">
              <div class="flex-1 min-w-0">
                <p v-if="notification.data?.title" class="text-sm font-medium text-gray-900 dark:text-gray-100">
                  {{ notification.data.title }}
                </p>
                <p class="text-sm text-gray-600 dark:text-gray-400" :class="{ 'mt-1': notification.data?.title }">
                  {{ notification.data?.message }}
                </p>
                <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">
                  {{ formatTime(notification.created_at) }}
                </p>
              </div>

              <div v-if="!notification.read_at" class="flex-shrink-0">
                <span class="inline-block w-2 h-2 bg-blue-600 rounded-full"></span>
              </div>
            </div>
          </div>

          <div v-if="notifications.length === 0" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">
            <svg class="w-12 h-12 mx-auto mb-3 text-gray-300 dark:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
            </svg>
            <p>No notifications yet</p>
          </div>
        </div>

        <!-- Footer -->
        <div class="px-4 py-3 bg-gray-50 dark:bg-gray-900 rounded-b-lg">
          <Link 
            :href="route('notifications.index')"
            class="block text-center text-sm text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300"
          >
            View all notifications
          </Link>
        </div>
      </div>
    </transition>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import axios from 'axios';

const props = defineProps({
  initialNotifications: {
    type: Array,
    default: () => []
  },
  initialUnreadCount: {
    type: Number,
    default: 0
  },
  userId: {
    type: Number,
    required: true
  }
});

const isOpen = ref(false);
const notifications = ref(props.initialNotifications);
const unreadCount = ref(props.initialUnreadCount);

// Fetch notifications on mount
onMounted(() => {
  fetchNotifications();
});

const fetchNotifications = async () => {
  try {
    const response = await axios.get('/notifications/api/recent');
    
    // Handle the actual API response format
    if (response.data.notifications) {
      notifications.value = response.data.notifications;
      unreadCount.value = response.data.unreadCount || 0;
    }
  } catch (error) {
    console.error('Failed to fetch notifications:', error);
  }
};

const toggleDropdown = () => {
  isOpen.value = !isOpen.value;
  
  // Refresh notifications when opening
  if (isOpen.value) {
    fetchNotifications();
  }
};

const closeDropdown = () => {
  isOpen.value = false;
};

const markAsRead = async (notificationId) => {
  try {
    await axios.post(`/notifications/api/${notificationId}/read`);
    
    // Update local state
    const notification = notifications.value.find(n => n.id === notificationId);
    if (notification && !notification.read_at) {
      notification.read_at = new Date().toISOString();
      unreadCount.value = Math.max(0, unreadCount.value - 1);
    }
  } catch (error) {
    console.error('Failed to mark notification as read:', error);
  }
};

const markAllAsRead = async () => {
  try {
    await axios.post('/notifications/api/mark-all-read');
    
    // Update all notifications to read
    notifications.value.forEach(n => {
      n.read_at = new Date().toISOString();
    });
    unreadCount.value = 0;
  } catch (error) {
    console.error('Failed to mark all as read:', error);
  }
};

const handleNotificationClick = async (notification) => {
  // Mark as read if unread
  if (!notification.read_at) {
    try {
      await axios.post(`/notifications/api/${notification.id}/read`);
      notification.read_at = new Date().toISOString();
      unreadCount.value = Math.max(0, unreadCount.value - 1);
    } catch (error) {
      console.error('Failed to mark as read:', error);
    }
  }
  
  // Navigate to action URL if provided (use relative path to avoid CORS)
  if (notification.data.action_url) {
    // Extract path from full URL if present
    let actionPath = notification.data.action_url;
    try {
      const url = new URL(notification.data.action_url);
      actionPath = url.pathname;
    } catch (e) {
      // Already a relative path
    }
    router.visit(actionPath);
  }
  
  closeDropdown();
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

const formatTime = (timestamp) => {
  const date = new Date(timestamp);
  const now = new Date();
  const diff = Math.floor((now - date) / 1000); // seconds

  if (diff < 60) return 'Just now';
  if (diff < 3600) return `${Math.floor(diff / 60)}m ago`;
  if (diff < 86400) return `${Math.floor(diff / 3600)}h ago`;
  if (diff < 604800) return `${Math.floor(diff / 86400)}d ago`;
  
  return date.toLocaleDateString();
};

const showToast = (notification) => {
  // Emit event for toast component to show
  window.dispatchEvent(new CustomEvent('show-notification-toast', {
    detail: notification
  }));
};

const playNotificationSound = () => {
  // Optional: play a subtle notification sound
  // const audio = new Audio('/sounds/notification.mp3');
  // audio.volume = 0.3;
  // audio.play().catch(() => {}); // Ignore errors
};

// Click outside directive
const vClickOutside = {
  beforeMount(el, binding) {
    el.clickOutsideEvent = event => {
      if (!(el === event.target || el.contains(event.target))) {
        binding.value();
      }
    };
    document.addEventListener('click', el.clickOutsideEvent);
  },
  unmounted(el) {
    document.removeEventListener('click', el.clickOutsideEvent);
  }
};
</script>