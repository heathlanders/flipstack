<script setup>
import { ref, computed } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

const props = defineProps({
  rules: { type: Array, default: () => [] },
  registry: { type: Array, default: () => [] },
})

const showEditor = ref(false)
const editingEventKey = ref(null)

const form = useForm({
  event_key: '',
  active: true,
  priority_override: null,
  channel_overrides: null,
  escalation_minutes: null,
  dedup_minutes: null,
  description: '',
  recipients: [],
})

const rulesByEvent = computed(() => {
  const map = {}
  for (const r of props.rules) map[r.event_key] = r
  return map
})

const recipientTypes = [
  { value: 'user', label: 'Specific user (ID)' },
  { value: 'role', label: 'Role (slug)' },
  { value: 'external_email', label: 'External email' },
  { value: 'supplier', label: 'Supplier (ID)' },
  { value: 'customer', label: 'Customer (context path)' },
]

const priorityOptions = [
  { value: null, label: 'Use default' },
  { value: 'low', label: 'Low' },
  { value: 'medium', label: 'Medium' },
  { value: 'high', label: 'High' },
  { value: 'critical', label: 'Critical' },
]

function startEdit(eventKey) {
  const existing = rulesByEvent.value[eventKey]
  const registryEntry = props.registry.find(r => r.event_key === eventKey)
  editingEventKey.value = eventKey

  form.event_key = eventKey
  form.active = existing?.active ?? true
  form.priority_override = existing?.priority_override ?? null
  form.channel_overrides = existing?.channel_overrides ?? null
  form.escalation_minutes = existing?.escalation_minutes ?? null
  form.dedup_minutes = existing?.dedup_minutes ?? registryEntry?.default_dedup_minutes ?? null
  form.description = existing?.description ?? registryEntry?.description ?? ''
  form.recipients = existing?.recipients?.map(r => ({
    recipient_type: r.recipient_type,
    recipient_value: r.recipient_value,
    escalation_step: r.escalation_step ?? 0,
  })) ?? (registryEntry?.default_recipients ?? []).map(d => ({
    recipient_type: d.type,
    recipient_value: d.value,
    escalation_step: d.step ?? 0,
  }))

  showEditor.value = true
}

function addRecipient() {
  form.recipients.push({ recipient_type: 'role', recipient_value: '', escalation_step: 0 })
}

function removeRecipient(idx) {
  form.recipients.splice(idx, 1)
}

function save() {
  form.post(route('notifications.rules.store'), {
    preserveScroll: true,
    onSuccess: () => {
      showEditor.value = false
      editingEventKey.value = null
    },
  })
}

function destroy(ruleId) {
  if (!confirm('Remove this rule? Defaults will apply.')) return
  router.delete(route('notifications.rules.destroy', ruleId), { preserveScroll: true })
}
</script>

<template>
  <AppLayout title="Notification Rules">
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
        Notification Routing Rules
      </h2>
    </template>

    <div class="py-8">
      <div class="max-w-6xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
          <p class="text-sm text-gray-600 dark:text-gray-400 mb-6">
            Define who receives each notification event for your organization.
            Rules override the registry defaults shown in gray.
          </p>

          <div v-if="!registry.length" class="text-center py-8 text-gray-500">
            No notification events registered yet.
          </div>

          <table v-else class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
            <thead>
              <tr class="text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                <th class="px-4 py-2">Event</th>
                <th class="px-4 py-2">Recipients</th>
                <th class="px-4 py-2">Dedup</th>
                <th class="px-4 py-2">Escalation</th>
                <th class="px-4 py-2">Status</th>
                <th class="px-4 py-2"></th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
              <tr v-for="entry in registry" :key="entry.event_key">
                <td class="px-4 py-3">
                  <div class="font-mono text-sm">{{ entry.event_key }}</div>
                  <div class="text-xs text-gray-500">{{ entry.description }}</div>
                </td>
                <td class="px-4 py-3 text-sm">
                  <div v-if="rulesByEvent[entry.event_key]?.recipients?.length">
                    <div v-for="r in rulesByEvent[entry.event_key].recipients" :key="r.id" class="text-xs">
                      <span class="font-medium">{{ r.recipient_type }}</span>: {{ r.recipient_value }}
                      <span v-if="r.escalation_step" class="text-gray-400">(step {{ r.escalation_step }})</span>
                    </div>
                  </div>
                  <div v-else class="text-xs text-gray-400 italic">
                    Defaults: {{ entry.default_recipients.length }} recipient(s)
                  </div>
                </td>
                <td class="px-4 py-3 text-sm">
                  {{ rulesByEvent[entry.event_key]?.dedup_minutes ?? entry.default_dedup_minutes ?? '-' }}m
                </td>
                <td class="px-4 py-3 text-sm">
                  {{ rulesByEvent[entry.event_key]?.escalation_minutes ? rulesByEvent[entry.event_key].escalation_minutes + 'm' : '-' }}
                </td>
                <td class="px-4 py-3 text-sm">
                  <span v-if="rulesByEvent[entry.event_key]" :class="rulesByEvent[entry.event_key].active ? 'text-green-600' : 'text-gray-400'">
                    {{ rulesByEvent[entry.event_key].active ? 'Custom' : 'Disabled' }}
                  </span>
                  <span v-else class="text-gray-400">Default</span>
                </td>
                <td class="px-4 py-3 text-right space-x-2">
                  <button class="text-blue-600 text-sm hover:underline" @click="startEdit(entry.event_key)">Edit</button>
                  <button
                    v-if="rulesByEvent[entry.event_key]"
                    class="text-red-600 text-sm hover:underline"
                    @click="destroy(rulesByEvent[entry.event_key].id)"
                  >Reset</button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <div v-if="showEditor" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
      <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-2xl w-full p-6 max-h-[90vh] overflow-y-auto">
        <h3 class="text-lg font-semibold mb-4">{{ form.event_key }}</h3>

        <div class="space-y-4">
          <label class="flex items-center">
            <input v-model="form.active" type="checkbox" class="rounded">
            <span class="ml-2 text-sm">Active</span>
          </label>

          <div>
            <label class="block text-xs font-medium text-gray-700 mb-1">Priority override</label>
            <select v-model="form.priority_override" class="w-full rounded border-gray-300 text-sm">
              <option v-for="o in priorityOptions" :key="o.label" :value="o.value">{{ o.label }}</option>
            </select>
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-xs font-medium text-gray-700 mb-1">Dedup minutes</label>
              <input v-model.number="form.dedup_minutes" type="number" min="0" class="w-full rounded border-gray-300 text-sm" placeholder="default">
            </div>
            <div>
              <label class="block text-xs font-medium text-gray-700 mb-1">Escalation minutes</label>
              <input v-model.number="form.escalation_minutes" type="number" min="1" class="w-full rounded border-gray-300 text-sm" placeholder="none">
            </div>
          </div>

          <div>
            <div class="flex items-center justify-between mb-2">
              <label class="block text-xs font-medium text-gray-700">Recipients</label>
              <button type="button" class="text-xs text-blue-600 hover:underline" @click="addRecipient">+ Add</button>
            </div>
            <div v-for="(r, idx) in form.recipients" :key="idx" class="flex gap-2 mb-2">
              <select v-model="r.recipient_type" class="rounded border-gray-300 text-sm flex-shrink-0">
                <option v-for="t in recipientTypes" :key="t.value" :value="t.value">{{ t.label }}</option>
              </select>
              <input v-model="r.recipient_value" type="text" class="flex-1 rounded border-gray-300 text-sm" placeholder="value">
              <input v-model.number="r.escalation_step" type="number" min="0" max="10" class="w-16 rounded border-gray-300 text-sm" title="step">
              <button type="button" class="text-red-600 text-sm" @click="removeRecipient(idx)">×</button>
            </div>
          </div>
        </div>

        <div class="mt-6 flex justify-end gap-2">
          <button type="button" class="px-4 py-2 text-sm rounded bg-gray-200" @click="showEditor = false">Cancel</button>
          <button type="button" class="px-4 py-2 text-sm rounded bg-blue-600 text-white" :disabled="form.processing" @click="save">Save</button>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
