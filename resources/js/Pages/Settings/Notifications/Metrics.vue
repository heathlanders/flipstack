<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'

defineProps({
  totals: { type: Object, default: () => ({}) },
  by_channel: { type: Array, default: () => [] },
  daily_trend: { type: Array, default: () => [] },
  window_days: { type: Number, default: 30 },
})

function pct(v) {
  return v != null ? `${v}%` : '–'
}
</script>

<template>
  <AppLayout title="Notification Metrics">
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
        Notification Delivery Metrics
      </h2>
    </template>

    <div class="py-8">
      <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <p class="text-sm text-gray-600 dark:text-gray-400">
          Last {{ window_days }} days. Sourced from <code>notification_logs</code>.
        </p>

        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
          <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-4">
            <div class="text-xs text-gray-500 uppercase">Total sent</div>
            <div class="text-2xl font-semibold mt-1">{{ totals.total ?? 0 }}</div>
          </div>
          <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-4">
            <div class="text-xs text-gray-500 uppercase">Delivery rate</div>
            <div class="text-2xl font-semibold mt-1 text-green-600">{{ pct(totals.delivery_rate) }}</div>
          </div>
          <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-4">
            <div class="text-xs text-gray-500 uppercase">Open rate</div>
            <div class="text-2xl font-semibold mt-1 text-blue-600">{{ pct(totals.open_rate) }}</div>
          </div>
          <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-4">
            <div class="text-xs text-gray-500 uppercase">Failure rate</div>
            <div class="text-2xl font-semibold mt-1 text-red-600">{{ pct(totals.failure_rate) }}</div>
          </div>
        </div>

        <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
          <h3 class="font-semibold mb-4">By channel</h3>
          <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
            <thead>
              <tr class="text-left text-xs uppercase text-gray-500">
                <th class="px-3 py-2">Channel</th>
                <th class="px-3 py-2 text-right">Total</th>
                <th class="px-3 py-2 text-right">Sent</th>
                <th class="px-3 py-2 text-right">Delivered</th>
                <th class="px-3 py-2 text-right">Opened</th>
                <th class="px-3 py-2 text-right">Clicked</th>
                <th class="px-3 py-2 text-right">Failed</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-gray-700 text-sm">
              <tr v-for="row in by_channel" :key="row.channel">
                <td class="px-3 py-2 font-medium">{{ row.channel }}</td>
                <td class="px-3 py-2 text-right">{{ row.total }}</td>
                <td class="px-3 py-2 text-right">{{ row.sent }}</td>
                <td class="px-3 py-2 text-right">{{ row.delivered }}</td>
                <td class="px-3 py-2 text-right">{{ row.opened }}</td>
                <td class="px-3 py-2 text-right">{{ row.clicked }}</td>
                <td class="px-3 py-2 text-right text-red-600">{{ row.failed }}</td>
              </tr>
              <tr v-if="!by_channel.length">
                <td colspan="7" class="px-3 py-6 text-center text-gray-400 italic">No data in window</td>
              </tr>
            </tbody>
          </table>
        </div>

        <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
          <h3 class="font-semibold mb-4">Daily volume</h3>
          <div v-if="!daily_trend.length" class="text-center text-gray-400 italic py-8">No data in window</div>
          <table v-else class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
            <thead>
              <tr class="text-left text-xs uppercase text-gray-500">
                <th class="px-3 py-2">Day</th>
                <th class="px-3 py-2 text-right">Total</th>
                <th class="px-3 py-2 text-right">Failed</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-gray-700 text-sm">
              <tr v-for="row in daily_trend" :key="row.day">
                <td class="px-3 py-2">{{ row.day }}</td>
                <td class="px-3 py-2 text-right">{{ row.total }}</td>
                <td class="px-3 py-2 text-right text-red-600">{{ row.failed }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
