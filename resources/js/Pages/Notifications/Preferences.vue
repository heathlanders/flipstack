<template>
  <AppLayout title="Notification Preferences">
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
        Notification Preferences
      </h2>
    </template>

    <div class="py-12">
      <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
        <form @submit.prevent="submit">
          <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg">
            <!-- Global Channel Settings -->
            <div class="p-6 border-b border-gray-200 dark:border-gray-700">
              <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">
                Notification Channels
              </h3>
              <p class="text-sm text-gray-600 dark:text-gray-400 mb-6">
                Choose how you want to receive notifications globally
              </p>

              <div class="space-y-4">
                <label class="flex items-center">
                  <input
                    v-model="form.database_enabled"
                    type="checkbox"
                    class="rounded border-gray-300 text-blue-600 shadow-sm focus:ring-blue-500"
                  >
                  <span class="ml-3 text-sm">
                    <span class="font-medium text-gray-900 dark:text-gray-100">🔔 In-App Notifications</span>
                    <span class="block text-gray-500 dark:text-gray-400">Show notifications in the app (recommended)</span>
                  </span>
                </label>

                <label class="flex items-center">
                  <input
                    v-model="form.email_enabled"
                    type="checkbox"
                    class="rounded border-gray-300 text-blue-600 shadow-sm focus:ring-blue-500"
                  >
                  <span class="ml-3 text-sm">
                    <span class="font-medium text-gray-900 dark:text-gray-100">📧 Email Notifications</span>
                    <span class="block text-gray-500 dark:text-gray-400">Receive notifications via email</span>
                  </span>
                </label>

                <label class="flex items-center">
                  <input
                    v-model="form.push_enabled"
                    type="checkbox"
                    class="rounded border-gray-300 text-blue-600 shadow-sm focus:ring-blue-500"
                  >
                  <span class="ml-3 text-sm">
                    <span class="font-medium text-gray-900 dark:text-gray-100">🔔 Push Notifications</span>
                    <span class="block text-gray-500 dark:text-gray-400">Real-time browser and mobile notifications</span>
                  </span>
                </label>

                <label class="flex items-center">
                  <input
                    v-model="form.sms_enabled"
                    type="checkbox"
                    class="rounded border-gray-300 text-blue-600 shadow-sm focus:ring-blue-500"
                  >
                  <span class="ml-3 text-sm">
                    <span class="font-medium text-gray-900 dark:text-gray-100">📱 SMS Notifications</span>
                    <span class="block text-gray-500 dark:text-gray-400">Urgent notifications via text message</span>
                  </span>
                </label>
              </div>
            </div>

            <!-- Category-Specific Settings -->
            <div class="p-6 border-b border-gray-200 dark:border-gray-700">
              <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">
                Category Preferences
              </h3>
              <p class="text-sm text-gray-600 dark:text-gray-400 mb-6">
                Customize notifications for each category
              </p>

              <div class="space-y-6">
                <div v-for="(category, key) in categories" :key="key" class="border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                  <div class="flex items-center mb-3">
                    <span class="text-2xl mr-3">{{ category.icon }}</span>
                    <h4 class="text-base font-medium text-gray-900 dark:text-gray-100">{{ category.name }}</h4>
                  </div>
                  <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 ml-11">
                    <label class="flex items-center text-sm">
                      <input
                        v-model="form.category_preferences[key].database"
                        type="checkbox"
                        :disabled="!form.database_enabled"
                        class="rounded border-gray-300 text-blue-600 shadow-sm focus:ring-blue-500"
                      >
                      <span class="ml-2 text-gray-700 dark:text-gray-300">In-App</span>
                    </label>
                    <label class="flex items-center text-sm">
                      <input
                        v-model="form.category_preferences[key].email"
                        type="checkbox"
                        :disabled="!form.email_enabled"
                        class="rounded border-gray-300 text-blue-600 shadow-sm focus:ring-blue-500"
                      >
                      <span class="ml-2 text-gray-700 dark:text-gray-300">Email</span>
                    </label>
                    <label class="flex items-center text-sm">
                      <input
                        v-model="form.category_preferences[key].push"
                        type="checkbox"
                        :disabled="!form.push_enabled"
                        class="rounded border-gray-300 text-blue-600 shadow-sm focus:ring-blue-500"
                      >
                      <span class="ml-2 text-gray-700 dark:text-gray-300">Push</span>
                    </label>
                    <label class="flex items-center text-sm">
                      <input
                        v-model="form.category_preferences[key].sms"
                        type="checkbox"
                        :disabled="!form.sms_enabled"
                        class="rounded border-gray-300 text-blue-600 shadow-sm focus:ring-blue-500"
                      >
                      <span class="ml-2 text-gray-700 dark:text-gray-300">SMS</span>
                    </label>
                  </div>
                </div>
              </div>
            </div>

            <!-- Digest Settings -->
            <div class="p-6 border-b border-gray-200 dark:border-gray-700">
              <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">
                Digest Settings
              </h3>
              <p class="text-sm text-gray-600 dark:text-gray-400 mb-6">
                Group non-urgent notifications into a digest
              </p>

              <div class="space-y-4">
                <div>
                  <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                    Digest Frequency
                  </label>
                  <select
                    v-model="form.digest_frequency"
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-300"
                  >
                    <option value="realtime">Real-time (no digest)</option>
                    <option value="hourly">Hourly digest</option>
                    <option value="daily">Daily digest</option>
                    <option value="weekly">Weekly digest</option>
                  </select>
                </div>

                <div v-if="form.digest_frequency === 'daily'">
                  <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                    Daily Digest Time
                  </label>
                  <input
                    v-model="form.digest_time"
                    type="time"
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-300"
                  >
                </div>
              </div>
            </div>

            <!-- Quiet Hours -->
            <div class="p-6 border-b border-gray-200 dark:border-gray-700">
              <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">
                Quiet Hours
              </h3>
              <p class="text-sm text-gray-600 dark:text-gray-400 mb-6">
                Pause non-critical notifications during specific hours
              </p>

              <div class="space-y-4">
                <label class="flex items-center">
                  <input
                    v-model="form.quiet_hours_enabled"
                    type="checkbox"
                    class="rounded border-gray-300 text-blue-600 shadow-sm focus:ring-blue-500"
                  >
                  <span class="ml-3 text-sm font-medium text-gray-900 dark:text-gray-100">
                    Enable quiet hours
                  </span>
                </label>

                <div v-if="form.quiet_hours_enabled" class="grid grid-cols-2 gap-4 ml-7">
                  <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                      Start Time
                    </label>
                    <input
                      v-model="form.quiet_start"
                      type="time"
                      class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-300"
                    >
                  </div>
                  <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                      End Time
                    </label>
                    <input
                      v-model="form.quiet_end"
                      type="time"
                      class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-300"
                    >
                  </div>
                </div>
              </div>
            </div>

            <!-- Priority Filter -->
            <div class="p-6 border-b border-gray-200 dark:border-gray-700">
              <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">
                Priority Filter
              </h3>
              <p class="text-sm text-gray-600 dark:text-gray-400 mb-6">
                Only show notifications at or above this priority level
              </p>

              <select
                v-model="form.min_priority"
                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-300"
              >
                <option value="low">All notifications (Low and above)</option>
                <option value="medium">Medium priority and above</option>
                <option value="high">High priority and above</option>
                <option value="critical">Critical only</option>
              </select>
            </div>

            <!-- Actions -->
            <div class="p-6 bg-gray-50 dark:bg-gray-900">
              <div class="flex items-center justify-end space-x-3">
                <Link
                  :href="route('dashboard')"
                  class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 hover:text-gray-900 dark:hover:text-gray-100"
                >
                  Cancel
                </Link>
                <button
                  type="submit"
                  :disabled="form.processing"
                  class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50"
                >
                  <span v-if="!form.processing">Save Preferences</span>
                  <span v-else>Saving...</span>
                </button>
              </div>
            </div>
          </div>
        </form>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { useForm, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
  preferences: Object,
});

const categories = {
  orders: { name: 'Orders & Sales', icon: '📦' },
  inventory: { name: 'Inventory', icon: '📊' },
  financial: { name: 'Financial', icon: '💰' },
  platform: { name: 'Platform Integration', icon: '🔌' },
  customer: { name: 'Customer Service', icon: '👥' },
  system: { name: 'System Alerts', icon: '⚙️' },
};

const form = useForm({
  email_enabled: props.preferences.email_enabled,
  sms_enabled: props.preferences.sms_enabled,
  push_enabled: props.preferences.push_enabled,
  database_enabled: props.preferences.database_enabled,
  category_preferences: props.preferences.category_preferences || {},
  digest_frequency: props.preferences.digest_frequency || 'realtime',
  digest_time: props.preferences.digest_time || '09:00',
  quiet_hours_enabled: props.preferences.quiet_hours_enabled,
  quiet_start: props.preferences.quiet_start || '22:00',
  quiet_end: props.preferences.quiet_end || '08:00',
  min_priority: props.preferences.min_priority || 'low',
});

// Initialize category preferences if not set
Object.keys(categories).forEach(key => {
  if (!form.category_preferences[key]) {
    form.category_preferences[key] = {
      database: true,
      email: true,
      push: false,
      sms: false,
    };
  }
});

const submit = () => {
  form.post(route('notifications.preferences.update'), {
    preserveScroll: true,
  });
};
</script>