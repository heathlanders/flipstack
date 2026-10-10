<template>
  <teleport to="body">
    <div class="fixed top-4 right-4 z-50 space-y-2 pointer-events-none">
      <transition-group
        enter-active-class="transition ease-out duration-300"
        enter-from-class="transform translate-x-full opacity-0"
        enter-to-class="transform translate-x-0 opacity-100"
        leave-active-class="transition ease-in duration-200"
        leave-from-class="transform translate-x-0 opacity-100"
        leave-to-class="transform translate-x-full opacity-0"
      >
        <div
          v-for="toast in toasts"
          :key="toast.id"
          class="max-w-md bg-white dark:bg-gray-800 rounded-lg shadow-lg ring-1 ring-black ring-opacity-5 dark:ring-gray-700 overflow-hidden pointer-events-auto"
        >
          <div class="p-4">
            <div class="flex items-start">
              <div class="flex-1">
                <p class="text-sm font-medium text-gray-900 dark:text-gray-100">
                  {{ toast.notification.data.title }}
                </p>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                  {{ toast.notification.data.message }}
                </p>
                <div class="mt-3 flex space-x-3">
                  <button
                    v-if="toast.notification.data.action_url"
                    @click="handleAction(toast)"
                    class="text-sm font-medium text-blue-600 hover:text-blue-500 dark:text-blue-400 dark:hover:text-blue-300"
                  >
                    {{ toast.notification.data.action_text || 'View' }}
                  </button>
                  <button
                    @click="removeToast(toast.id)"
                    class="text-sm font-medium text-gray-700 hover:text-gray-500 dark:text-gray-300 dark:hover:text-gray-400"
                  >
                    Dismiss
                  </button>
                </div>
              </div>

              <!-- Close Button -->
              <div class="ml-4 flex-shrink-0">
                <button 
                  @click="removeToast(toast.id)" 
                  class="text-gray-400 hover:text-gray-500 dark:text-gray-500 dark:hover:text-gray-400"
                >
                  <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                    <path 
                      fill-rule="evenodd" 
                      d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" 
                      clip-rule="evenodd" 
                    />
                  </svg>
                </button>
              </div>
            </div>
          </div>

          <!-- Progress Bar -->
          <div class="h-1 bg-gray-200 dark:bg-gray-700">
            <div 
              class="h-full transition-all duration-100"
              :class="getPriorityBarColor(toast.notification.data.priority)"
              :style="{ width: `${toast.progress}%` }"
            ></div>
          </div>
        </div>
      </transition-group>
    </div>
  </teleport>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { router } from '@inertiajs/vue3';

const toasts = ref([]);
let nextId = 0;

const TOAST_DURATION = 5000; // 5 seconds

onMounted(() => {
  // Listen for toast events
  window.addEventListener('show-notification-toast', handleShowToast);
});

const handleShowToast = (event) => {
  const notification = event.detail;
  addToast(notification);
};

const addToast = (notification) => {
  const id = nextId++;
  const toast = {
    id,
    notification,
    progress: 100,
  };

  toasts.value.push(toast);

  // Start progress bar animation
  const interval = 50;
  const step = (100 / TOAST_DURATION) * interval;
  
  const progressTimer = setInterval(() => {
    toast.progress -= step;
    if (toast.progress <= 0) {
      clearInterval(progressTimer);
      removeToast(id);
    }
  }, interval);

  // Auto-remove after duration
  setTimeout(() => {
    removeToast(id);
  }, TOAST_DURATION);
};

const removeToast = (id) => {
  const index = toasts.value.findIndex(t => t.id === id);
  if (index > -1) {
    toasts.value.splice(index, 1);
  }
};

const handleAction = (toast) => {
  if (toast.notification.data.action_url) {
    router.visit(toast.notification.data.action_url);
  }
  removeToast(toast.id);
};

const getPriorityBarColor = (priority) => {
  const colors = {
    critical: 'bg-red-600',
    high: 'bg-orange-600',
    medium: 'bg-blue-600',
    low: 'bg-green-600',
  };
  return colors[priority] || 'bg-blue-600';
};
</script>