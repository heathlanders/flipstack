<script setup>
import { ref, computed } from 'vue';
import { router, useForm, Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { route } from 'ziggy-js';

const props = defineProps({
    targets: { type: Array, default: () => [] },
    metrics: { type: Array, default: () => [] },
    periods: { type: Array, default: () => [] },
    users: { type: Array, default: () => [] },
    roles: { type: Array, default: () => [] },
    teams: { type: Array, default: () => [] },
});

const showForm = ref(false);
const editing = ref(null);

const form = useForm({
    scope_type: 'user',
    scope_id: '',
    metric: 'items_picked',
    period: 'daily',
    target_value: 0,
    effective_from: new Date().toISOString().slice(0, 10),
    effective_to: '',
    notes: '',
});

const scopeOptions = computed(() => {
    if (form.scope_type === 'user') return props.users;
    if (form.scope_type === 'role') return props.roles;
    if (form.scope_type === 'team') return props.teams;
    return [];
});

function openCreate() {
    editing.value = null;
    form.reset();
    form.scope_type = 'user';
    form.metric = 'items_picked';
    form.period = 'daily';
    form.effective_from = new Date().toISOString().slice(0, 10);
    showForm.value = true;
}

function openEdit(target) {
    editing.value = target;
    form.scope_type = target.scope_type;
    form.scope_id = target.scope_id || '';
    form.metric = target.metric;
    form.period = target.period;
    form.target_value = target.target_value;
    form.effective_from = target.effective_from || '';
    form.effective_to = target.effective_to || '';
    form.notes = target.notes || '';
    showForm.value = true;
}

function submit() {
    if (editing.value) {
        form.put(route('settings.targets.update', editing.value.id), {
            onSuccess: () => { showForm.value = false; editing.value = null; },
        });
    } else {
        form.post(route('settings.targets.store'), {
            onSuccess: () => { showForm.value = false; form.reset(); },
        });
    }
}

function destroy(target) {
    if (!confirm(`Delete target for ${target.scope_label} (${target.metric}, ${target.period})?`)) return;
    router.delete(route('settings.targets.destroy', target.id), { preserveScroll: true });
}

const groupedTargets = computed(() => {
    const groups = { user: [], team: [], role: [], organization: [] };
    for (const t of props.targets) {
        if (groups[t.scope_type]) groups[t.scope_type].push(t);
    }
    return groups;
});

const scopeLabels = {
    user: 'Per-User Targets',
    team: 'Per-Team Targets',
    role: 'Per-Role Targets',
    organization: 'Org-Wide Targets',
};

function metricLabel(key) {
    return key.split('_').map(w => w.charAt(0).toUpperCase() + w.slice(1)).join(' ');
}
</script>

<template>
    <Head title="Performance Targets" />
    <AppLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200">Performance Targets</h2>
                <button
                    @click="openCreate"
                    class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-md hover:bg-indigo-700"
                >
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
                    New Target
                </button>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
                <!-- Form -->
                <div v-if="showForm" class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">
                        {{ editing ? 'Edit Target' : 'New Target' }}
                    </h3>
                    <form @submit.prevent="submit" class="space-y-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Scope</label>
                                <select v-model="form.scope_type" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-sm">
                                    <option value="user">User</option>
                                    <option value="team">Team</option>
                                    <option value="role">Role</option>
                                    <option value="organization">Whole Organization</option>
                                </select>
                            </div>
                            <div v-if="form.scope_type !== 'organization'">
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Target {{ form.scope_type }}</label>
                                <select v-model="form.scope_id" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-sm">
                                    <option value="">— Select —</option>
                                    <option v-for="opt in scopeOptions" :key="opt.id" :value="opt.id">{{ opt.name }}</option>
                                </select>
                                <p v-if="form.errors.scope_id" class="mt-1 text-xs text-red-500">{{ form.errors.scope_id }}</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Metric</label>
                                <select v-model="form.metric" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-sm">
                                    <option v-for="m in metrics" :key="m" :value="m">{{ metricLabel(m) }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Period</label>
                                <select v-model="form.period" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-sm">
                                    <option v-for="p in periods" :key="p" :value="p">{{ p }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Target Value</label>
                                <input
                                    v-model.number="form.target_value"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm text-sm"
                                />
                            </div>
                            <div></div>
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
                                {{ form.processing ? 'Saving...' : (editing ? 'Save Changes' : 'Create Target') }}
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Grouped lists -->
                <div v-for="(group, key) in groupedTargets" :key="key" class="bg-white dark:bg-gray-800 rounded-lg shadow">
                    <div class="px-6 py-3 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
                        <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wider">{{ scopeLabels[key] }}</h3>
                        <span class="text-xs text-gray-500 dark:text-gray-400">{{ group.length }}</span>
                    </div>
                    <div v-if="group.length" class="divide-y divide-gray-100 dark:divide-gray-700/50">
                        <div v-for="t in group" :key="t.id" class="px-6 py-3 flex items-center justify-between hover:bg-gray-50 dark:hover:bg-gray-700/30">
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-2 text-sm">
                                    <span class="font-medium text-gray-900 dark:text-white">{{ t.scope_label }}</span>
                                    <span class="text-gray-400">•</span>
                                    <span class="text-gray-700 dark:text-gray-300">{{ metricLabel(t.metric) }}</span>
                                    <span class="text-gray-400">•</span>
                                    <span class="text-gray-700 dark:text-gray-300 capitalize">{{ t.period }}</span>
                                </div>
                                <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                    <span class="font-mono">{{ t.target_value }}</span>
                                    <span class="ml-2">{{ t.effective_from }}<span v-if="t.effective_to"> → {{ t.effective_to }}</span><span v-else> → ongoing</span></span>
                                </div>
                            </div>
                            <div class="flex items-center gap-2 ml-4 flex-shrink-0">
                                <button @click="openEdit(t)" class="text-xs text-indigo-600 dark:text-indigo-400 hover:underline">Edit</button>
                                <button @click="destroy(t)" class="text-xs text-red-600 dark:text-red-400 hover:underline">Delete</button>
                            </div>
                        </div>
                    </div>
                    <div v-else class="px-6 py-6 text-center text-xs text-gray-500 dark:text-gray-400">
                        No {{ scopeLabels[key].toLowerCase() }} yet.
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
