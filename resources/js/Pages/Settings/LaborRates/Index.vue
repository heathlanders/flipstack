<script setup>
import { ref, computed } from 'vue';
import { router, useForm, Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { route } from 'ziggy-js';
import { formatCurrency } from '@/Composables/useAnalyticsFormatters';

const props = defineProps({
    rates: { type: Array, default: () => [] },
    users: { type: Array, default: () => [] },
});

const showForm = ref(false);
const editing = ref(null);

const form = useForm({
    user_id: '',
    hourly_rate: 0,
    minutes_per_listing: 10,
    minutes_per_ticket: 5,
    effective_from: new Date().toISOString().slice(0, 10),
    effective_to: '',
    notes: '',
});

function openCreate() {
    editing.value = null;
    form.reset();
    form.effective_from = new Date().toISOString().slice(0, 10);
    form.minutes_per_listing = 10;
    form.minutes_per_ticket = 5;
    showForm.value = true;
}

function openEdit(rate) {
    editing.value = rate;
    form.user_id = rate.user_id;
    form.hourly_rate = rate.hourly_rate;
    form.minutes_per_listing = rate.minutes_per_listing;
    form.minutes_per_ticket = rate.minutes_per_ticket;
    form.effective_from = rate.effective_from || '';
    form.effective_to = rate.effective_to || '';
    form.notes = rate.notes || '';
    showForm.value = true;
}

function submit() {
    if (editing.value) {
        form.put(route('settings.labor-rates.update', editing.value.id), {
            onSuccess: () => { showForm.value = false; editing.value = null; },
        });
    } else {
        form.post(route('settings.labor-rates.store'), {
            onSuccess: () => { showForm.value = false; form.reset(); },
        });
    }
}

function destroy(rate) {
    if (!confirm(`Remove rate for ${rate.user_name} (effective ${rate.effective_from})?`)) return;
    router.delete(route('settings.labor-rates.destroy', rate.id), { preserveScroll: true });
}

const groupedByUser = computed(() => {
    const groups = {};
    for (const r of props.rates) {
        groups[r.user_id] ??= { user_name: r.user_name, rates: [] };
        groups[r.user_id].rates.push(r);
    }
    return groups;
});
</script>

<template>
    <Head title="Labor Rates" />
    <AppLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200">Labor Rates</h2>
                <button
                    @click="openCreate"
                    class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-md hover:bg-indigo-700"
                >
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
                    Set Rate
                </button>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
                <div class="bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-700 rounded-lg p-3 text-xs text-amber-800 dark:text-amber-300">
                    Rates are effective-dated. Cost analytics use the rate that overlaps the active analytics date range.
                    Minutes per listing/ticket are tunable estimates used when per-task tracking isn't available.
                </div>

                <div v-if="showForm" class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">{{ editing ? 'Edit Rate' : 'Set New Rate' }}</h3>
                    <form @submit.prevent="submit" class="space-y-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">User</label>
                                <select v-model="form.user_id" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-sm">
                                    <option value="">— Select —</option>
                                    <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }}</option>
                                </select>
                                <p v-if="form.errors.user_id" class="mt-1 text-xs text-red-500">{{ form.errors.user_id }}</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Hourly Rate ($)</label>
                                <input v-model.number="form.hourly_rate" type="number" step="0.01" min="0" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-sm" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Minutes per Listing <span class="text-gray-400">(estimate)</span></label>
                                <input v-model.number="form.minutes_per_listing" type="number" min="0" max="1440" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-sm" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Minutes per Ticket Reply <span class="text-gray-400">(estimate)</span></label>
                                <input v-model.number="form.minutes_per_ticket" type="number" min="0" max="1440" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-sm" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Effective From</label>
                                <input v-model="form.effective_from" type="date" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-sm" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Effective Until <span class="text-gray-400">(optional)</span></label>
                                <input v-model="form.effective_to" type="date" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-sm" />
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Notes</label>
                                <textarea v-model="form.notes" rows="2" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-sm"></textarea>
                            </div>
                        </div>
                        <div class="flex justify-end gap-2 pt-3 border-t border-gray-200 dark:border-gray-700">
                            <button type="button" @click="showForm = false" class="px-4 py-2 text-sm text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 rounded-md">Cancel</button>
                            <button type="submit" :disabled="form.processing" class="px-4 py-2 text-sm bg-indigo-600 text-white rounded-md hover:bg-indigo-700 disabled:opacity-50">
                                {{ form.processing ? 'Saving...' : (editing ? 'Save Changes' : 'Set Rate') }}
                            </button>
                        </div>
                    </form>
                </div>

                <div v-for="(group, userId) in groupedByUser" :key="userId" class="bg-white dark:bg-gray-800 rounded-lg shadow">
                    <div class="px-6 py-3 border-b border-gray-200 dark:border-gray-700">
                        <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wider">{{ group.user_name }}</h3>
                    </div>
                    <div class="divide-y divide-gray-100 dark:divide-gray-700/50">
                        <div v-for="r in group.rates" :key="r.id" class="px-6 py-3 flex items-center justify-between hover:bg-gray-50 dark:hover:bg-gray-700/30">
                            <div class="text-sm">
                                <div class="font-medium text-gray-900 dark:text-white">{{ formatCurrency(r.hourly_rate) }}/hr</div>
                                <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                    {{ r.effective_from }}<span v-if="r.effective_to"> → {{ r.effective_to }}</span><span v-else> → ongoing</span>
                                    <span class="ml-3">{{ r.minutes_per_listing }} min/listing · {{ r.minutes_per_ticket }} min/ticket</span>
                                </div>
                            </div>
                            <div class="flex items-center gap-2 ml-4 flex-shrink-0">
                                <button @click="openEdit(r)" class="text-xs text-indigo-600 dark:text-indigo-400 hover:underline">Edit</button>
                                <button @click="destroy(r)" class="text-xs text-red-600 dark:text-red-400 hover:underline">Delete</button>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-if="!rates.length" class="bg-white dark:bg-gray-800 rounded-lg shadow p-12 text-center text-sm text-gray-500 dark:text-gray-400">
                    No labor rates set yet. Click "Set Rate" to add one.
                </div>
            </div>
        </div>
    </AppLayout>
</template>
