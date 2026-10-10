<script setup>
import { ref, computed } from 'vue';
import { router, useForm, Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { route } from 'ziggy-js';

const props = defineProps({
    saved: { type: Array, default: () => [] },
    scheduled: { type: Array, default: () => [] },
    viewOptions: { type: Array, default: () => [] },
    frequencies: { type: Array, default: () => [] },
    formats: { type: Array, default: () => [] },
});

const tab = ref('saved');
const showSavedForm = ref(false);
const showScheduledForm = ref(false);
const editingScheduled = ref(null);

const savedForm = useForm({
    name: '',
    view: 'workforce_individual',
    filters: {},
    is_shared: false,
});

const scheduledForm = useForm({
    name: '',
    view: 'workforce_individual',
    filters: {},
    format: 'csv',
    frequency: 'weekly',
    day_of_period: 1,
    send_time: '07:00',
    recipients: [],
    recipientsRaw: '',
    is_active: true,
});

function submitSaved() {
    savedForm.post(route('analytics.reports.saved.store'), {
        onSuccess: () => { showSavedForm.value = false; savedForm.reset(); },
    });
}

function destroySaved(s) {
    if (!confirm(`Delete saved report "${s.name}"?`)) return;
    router.delete(route('analytics.reports.saved.destroy', s.id), { preserveScroll: true });
}

function openScheduledCreate() {
    editingScheduled.value = null;
    scheduledForm.reset();
    scheduledForm.frequency = 'weekly';
    scheduledForm.format = 'csv';
    scheduledForm.send_time = '07:00';
    scheduledForm.day_of_period = 1;
    showScheduledForm.value = true;
}

function openScheduledEdit(s) {
    editingScheduled.value = s;
    scheduledForm.name = s.name;
    scheduledForm.view = s.view;
    scheduledForm.filters = s.filters || {};
    scheduledForm.format = s.format;
    scheduledForm.frequency = s.frequency;
    scheduledForm.day_of_period = s.day_of_period ?? 1;
    scheduledForm.send_time = s.send_time;
    scheduledForm.recipients = s.recipients || [];
    scheduledForm.recipientsRaw = (s.recipients || []).join(', ');
    scheduledForm.is_active = s.is_active;
    showScheduledForm.value = true;
}

function submitScheduled() {
    scheduledForm.recipients = scheduledForm.recipientsRaw
        .split(',')
        .map(s => s.trim())
        .filter(Boolean);

    const opts = {
        onSuccess: () => { showScheduledForm.value = false; editingScheduled.value = null; scheduledForm.reset(); },
    };
    if (editingScheduled.value) {
        scheduledForm.put(route('analytics.reports.scheduled.update', editingScheduled.value.id), opts);
    } else {
        scheduledForm.post(route('analytics.reports.scheduled.store'), opts);
    }
}

function destroyScheduled(s) {
    if (!confirm(`Delete scheduled report "${s.name}"?`)) return;
    router.delete(route('analytics.reports.scheduled.destroy', s.id), { preserveScroll: true });
}

function exportNow(view, format = 'csv') {
    window.location = route('analytics.reports.export', { view, format });
}

const dayLabel = computed(() => (s) => {
    if (s.frequency === 'daily') return 'Every day';
    if (s.frequency === 'weekly') {
        const days = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
        return `Every ${days[s.day_of_period] ?? 'Mon'}`;
    }
    return `Day ${s.day_of_period} of month`;
});
</script>

<template>
    <Head title="Reports" />
    <AppLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200">Reports</h2>
        </template>

        <div class="py-6">
            <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
                <!-- Tabs -->
                <div class="border-b border-gray-200 dark:border-gray-700 flex gap-6 text-sm">
                    <button
                        @click="tab = 'saved'"
                        class="pb-3 border-b-2"
                        :class="tab === 'saved' ? 'border-indigo-600 text-indigo-600 dark:text-indigo-400' : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200'"
                    >Saved Reports ({{ saved.length }})</button>
                    <button
                        @click="tab = 'scheduled'"
                        class="pb-3 border-b-2"
                        :class="tab === 'scheduled' ? 'border-indigo-600 text-indigo-600 dark:text-indigo-400' : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200'"
                    >Scheduled Reports ({{ scheduled.length }})</button>
                </div>

                <!-- Saved Reports -->
                <div v-if="tab === 'saved'" class="space-y-4">
                    <div class="flex justify-end">
                        <button @click="showSavedForm = true" class="px-3 py-1.5 bg-indigo-600 text-white text-xs font-medium rounded-md hover:bg-indigo-700">+ Save New</button>
                    </div>

                    <form v-if="showSavedForm" @submit.prevent="submitSaved" class="bg-white dark:bg-gray-800 rounded-lg shadow p-5 space-y-3">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Name</label>
                                <input v-model="savedForm.name" type="text" required class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-sm" />
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">View</label>
                                <select v-model="savedForm.view" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-sm">
                                    <option v-for="opt in viewOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                                </select>
                            </div>
                            <div class="md:col-span-2">
                                <label class="inline-flex items-center text-xs">
                                    <input v-model="savedForm.is_shared" type="checkbox" class="rounded border-gray-300 dark:border-gray-600 mr-2" />
                                    Share with all managers in this org
                                </label>
                            </div>
                        </div>
                        <div class="flex justify-end gap-2">
                            <button type="button" @click="showSavedForm = false" class="px-3 py-1.5 text-xs text-gray-600 dark:text-gray-300">Cancel</button>
                            <button type="submit" :disabled="savedForm.processing" class="px-3 py-1.5 bg-indigo-600 text-white text-xs rounded-md hover:bg-indigo-700">Save</button>
                        </div>
                    </form>

                    <div v-if="!saved.length" class="bg-white dark:bg-gray-800 rounded-lg shadow p-12 text-center text-sm text-gray-500 dark:text-gray-400">
                        No saved reports yet.
                    </div>
                    <div v-for="s in saved" :key="s.id" class="bg-white dark:bg-gray-800 rounded-lg shadow p-4 flex items-center justify-between">
                        <div class="min-w-0 flex-1">
                            <div class="text-sm font-medium text-gray-900 dark:text-white truncate">{{ s.name }}<span v-if="s.is_shared" class="ml-2 text-xs text-emerald-600 dark:text-emerald-400">(shared)</span></div>
                            <div class="text-xs text-gray-500 dark:text-gray-400">{{ s.view_label }}</div>
                        </div>
                        <div class="flex items-center gap-2 ml-4">
                            <button @click="exportNow(s.view, 'csv')" class="text-xs text-indigo-600 dark:text-indigo-400 hover:underline">CSV</button>
                            <button @click="exportNow(s.view, 'xlsx')" class="text-xs text-indigo-600 dark:text-indigo-400 hover:underline">XLSX</button>
                            <button @click="exportNow(s.view, 'pdf')" class="text-xs text-indigo-600 dark:text-indigo-400 hover:underline">PDF</button>
                            <button v-if="s.is_owner" @click="destroySaved(s)" class="text-xs text-red-600 dark:text-red-400 hover:underline ml-2">Delete</button>
                        </div>
                    </div>
                </div>

                <!-- Scheduled Reports -->
                <div v-if="tab === 'scheduled'" class="space-y-4">
                    <div class="flex justify-end">
                        <button @click="openScheduledCreate" class="px-3 py-1.5 bg-indigo-600 text-white text-xs font-medium rounded-md hover:bg-indigo-700">+ New Schedule</button>
                    </div>

                    <form v-if="showScheduledForm" @submit.prevent="submitScheduled" class="bg-white dark:bg-gray-800 rounded-lg shadow p-5 space-y-3">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Name</label>
                                <input v-model="scheduledForm.name" type="text" required class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-sm" />
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">View</label>
                                <select v-model="scheduledForm.view" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-sm">
                                    <option v-for="opt in viewOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Format</label>
                                <select v-model="scheduledForm.format" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-sm">
                                    <option v-for="f in formats" :key="f" :value="f">{{ f.toUpperCase() }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Frequency</label>
                                <select v-model="scheduledForm.frequency" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-sm">
                                    <option v-for="f in frequencies" :key="f" :value="f">{{ f }}</option>
                                </select>
                            </div>
                            <div v-if="scheduledForm.frequency !== 'daily'">
                                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">
                                    {{ scheduledForm.frequency === 'weekly' ? 'Day of Week (0=Sun, 6=Sat)' : 'Day of Month (1-28)' }}
                                </label>
                                <input v-model.number="scheduledForm.day_of_period" type="number" :min="0" :max="scheduledForm.frequency === 'weekly' ? 6 : 28" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-sm" />
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Send Time (HH:MM UTC)</label>
                                <input v-model="scheduledForm.send_time" type="text" pattern="[0-9]{2}:[0-9]{2}" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-sm" />
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Recipients (comma-separated emails)</label>
                                <input v-model="scheduledForm.recipientsRaw" type="text" required class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-sm" />
                            </div>
                            <div class="md:col-span-2">
                                <label class="inline-flex items-center text-xs">
                                    <input v-model="scheduledForm.is_active" type="checkbox" class="rounded border-gray-300 dark:border-gray-600 mr-2" />
                                    Active
                                </label>
                            </div>
                        </div>
                        <div class="flex justify-end gap-2">
                            <button type="button" @click="showScheduledForm = false" class="px-3 py-1.5 text-xs text-gray-600 dark:text-gray-300">Cancel</button>
                            <button type="submit" :disabled="scheduledForm.processing" class="px-3 py-1.5 bg-indigo-600 text-white text-xs rounded-md hover:bg-indigo-700">{{ editingScheduled ? 'Save Changes' : 'Schedule' }}</button>
                        </div>
                    </form>

                    <div v-if="!scheduled.length" class="bg-white dark:bg-gray-800 rounded-lg shadow p-12 text-center text-sm text-gray-500 dark:text-gray-400">
                        No scheduled reports yet.
                    </div>
                    <div v-for="s in scheduled" :key="s.id" class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
                        <div class="flex items-start justify-between">
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-2">
                                    <span class="text-sm font-medium text-gray-900 dark:text-white">{{ s.name }}</span>
                                    <span v-if="!s.is_active" class="px-2 py-0.5 text-xs bg-gray-100 dark:bg-gray-700 rounded text-gray-500">Paused</span>
                                </div>
                                <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                    {{ s.view_label }} · {{ s.format.toUpperCase() }} · {{ dayLabel(s) }} at {{ s.send_time }}
                                </div>
                                <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                    To: {{ (s.recipients || []).join(', ') }}
                                </div>
                                <div class="text-xs text-gray-400 dark:text-gray-500 mt-1">
                                    Last sent: {{ s.last_sent_at || 'never' }} · Next: {{ s.next_due_at || '—' }}
                                </div>
                            </div>
                            <div class="flex items-center gap-2 ml-4">
                                <button @click="openScheduledEdit(s)" class="text-xs text-indigo-600 dark:text-indigo-400 hover:underline">Edit</button>
                                <button @click="destroyScheduled(s)" class="text-xs text-red-600 dark:text-red-400 hover:underline">Delete</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
