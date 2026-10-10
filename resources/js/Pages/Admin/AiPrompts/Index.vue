<template>
    <AppLayout
        title="AI Prompts Management"
        page-title="AI Prompts Management"
        :page-actions="pageActions"
    >

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <!-- Stats Overview -->
                <div class="grid grid-cols-1 md:grid-cols-5 gap-6 mb-8">
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-4">
                        <div class="text-sm text-gray-500 dark:text-gray-400">Total Prompts</div>
                        <div class="text-2xl font-bold text-gray-900 dark:text-white">{{ stats.total }}</div>
                    </div>
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-4">
                        <div class="text-sm text-gray-500 dark:text-gray-400">System</div>
                        <div class="text-2xl font-bold text-blue-600">{{ stats.system }}</div>
                    </div>
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-4">
                        <div class="text-sm text-gray-500 dark:text-gray-400">Custom</div>
                        <div class="text-2xl font-bold text-indigo-600">{{ stats.custom }}</div>
                    </div>
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-4">
                        <div class="text-sm text-gray-500 dark:text-gray-400">Active</div>
                        <div class="text-2xl font-bold text-green-600">{{ stats.active }}</div>
                    </div>
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-4">
                        <div class="text-sm text-gray-500 dark:text-gray-400">Stage 1</div>
                        <div class="text-2xl font-bold text-purple-600">{{ stats.by_type?.stage1 || 0 }}</div>
                    </div>
                </div>

                <!-- Filters -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-4 mb-6">
                    <div class="flex flex-wrap gap-4">
                        <input
                            v-model="localFilters.search"
                            type="text"
                            placeholder="Search prompts..."
                            class="border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md shadow-sm text-sm w-64"
                            @input="debounceSearch"
                        />

                        <select
                            v-model="localFilters.category"
                            @change="applyFilters"
                            class="border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md shadow-sm text-sm"
                        >
                            <option value="">All Categories</option>
                            <option v-for="(name, slug) in categories" :key="slug" :value="slug">
                                {{ name }}
                            </option>
                        </select>

                        <select
                            v-model="localFilters.type"
                            @change="applyFilters"
                            class="border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md shadow-sm text-sm"
                        >
                            <option value="">All Types</option>
                            <option v-for="(label, type) in prompt_types" :key="type" :value="type">
                                {{ label }}
                            </option>
                        </select>

                        <select
                            v-model="localFilters.status"
                            @change="applyFilters"
                            class="border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md shadow-sm text-sm"
                        >
                            <option value="">All Statuses</option>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>

                        <select
                            v-model="localFilters.scope"
                            @change="applyFilters"
                            class="border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md shadow-sm text-sm"
                        >
                            <option value="">All Scopes</option>
                            <option value="system">System</option>
                            <option value="custom">Custom</option>
                        </select>

                        <button
                            v-if="hasFilters"
                            @click="clearFilters"
                            class="text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400"
                        >
                            Clear filters
                        </button>
                    </div>
                </div>

                <!-- Bulk Actions Panel -->
                <Transition>
                    <div v-if="showBulkActions && selectedPrompts.length > 0" class="bg-indigo-50 dark:bg-indigo-900/30 rounded-lg p-4 mb-6">
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-indigo-700 dark:text-indigo-300">
                                {{ selectedPrompts.length }} prompt(s) selected
                            </span>
                            <div class="flex items-center space-x-4">
                                <button
                                    @click="bulkActivate"
                                    class="px-3 py-1.5 bg-green-600 text-white rounded-md text-sm hover:bg-green-700"
                                >
                                    Activate
                                </button>
                                <button
                                    @click="bulkDeactivate"
                                    class="px-3 py-1.5 bg-yellow-600 text-white rounded-md text-sm hover:bg-yellow-700"
                                >
                                    Deactivate
                                </button>
                                <button
                                    @click="exportSelected"
                                    class="px-3 py-1.5 bg-blue-600 text-white rounded-md text-sm hover:bg-blue-700"
                                >
                                    Export Selected
                                </button>
                                <button
                                    @click="clearSelection"
                                    class="text-sm text-gray-500 hover:text-gray-700"
                                >
                                    Clear
                                </button>
                            </div>
                        </div>
                    </div>
                </Transition>

                <!-- Prompts Table -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-900">
                            <tr>
                                <th class="w-8 px-4 py-3">
                                    <input
                                        type="checkbox"
                                        :checked="allSelected"
                                        @change="toggleSelectAll"
                                        class="rounded border-gray-300 dark:border-gray-600"
                                    />
                                </th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    Name
                                </th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    Category
                                </th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    Type
                                </th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    Status
                                </th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    Chars
                                </th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            <tr v-for="prompt in prompts.data" :key="prompt.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                <td class="px-4 py-4">
                                    <input
                                        type="checkbox"
                                        :checked="selectedPrompts.includes(prompt.id)"
                                        @change="toggleSelect(prompt.id)"
                                        :disabled="prompt.is_system"
                                        class="rounded border-gray-300 dark:border-gray-600"
                                    />
                                </td>
                                <td class="px-4 py-4">
                                    <div class="flex items-center">
                                        <div>
                                            <div class="text-sm font-medium text-gray-900 dark:text-white">
                                                {{ prompt.name }}
                                            </div>
                                            <div v-if="prompt.is_system" class="text-xs text-blue-600 dark:text-blue-400">
                                                System
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-4">
                                    <span class="text-sm text-gray-600 dark:text-gray-400">
                                        {{ prompt.category_display }}
                                    </span>
                                </td>
                                <td class="px-4 py-4">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium" :class="getTypeBadgeClass(prompt.prompt_type)">
                                        {{ prompt.prompt_type_label }}
                                    </span>
                                </td>
                                <td class="px-4 py-4">
                                    <span v-if="prompt.is_active" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                                        Active
                                    </span>
                                    <span v-else class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300">
                                        Inactive
                                    </span>
                                </td>
                                <td class="px-4 py-4 text-sm text-gray-500 dark:text-gray-400">
                                    {{ formatNumber(prompt.character_count) }}
                                </td>
                                <td class="px-4 py-4 text-right text-sm font-medium">
                                    <div class="flex justify-end items-center space-x-2">
                                        <Link
                                            :href="route('admin.ai-prompts.edit', prompt.id)"
                                            class="text-indigo-600 hover:text-indigo-900 dark:text-indigo-400"
                                        >
                                            {{ prompt.can_edit ? 'Edit' : 'View' }}
                                        </Link>
                                        <button
                                            v-if="can.create"
                                            @click="duplicatePrompt(prompt.id)"
                                            class="text-blue-600 hover:text-blue-900 dark:text-blue-400"
                                        >
                                            Duplicate
                                        </button>
                                        <button
                                            v-if="prompt.can_delete && can.delete"
                                            @click="confirmDelete(prompt)"
                                            class="text-red-600 hover:text-red-900 dark:text-red-400"
                                        >
                                            Delete
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <!-- Pagination -->
                    <div v-if="prompts.links" class="px-4 py-3 bg-gray-50 dark:bg-gray-900 border-t border-gray-200 dark:border-gray-700">
                        <nav class="flex items-center justify-between">
                            <div class="text-sm text-gray-500 dark:text-gray-400">
                                Showing {{ prompts.from }} to {{ prompts.to }} of {{ prompts.total }} prompts
                            </div>
                            <div class="flex space-x-1">
                                <Link
                                    v-for="link in prompts.links"
                                    :key="link.label"
                                    :href="link.url || '#'"
                                    :class="[
                                        'px-3 py-1 rounded-md text-sm',
                                        link.active ? 'bg-indigo-600 text-white' : 'bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-600',
                                        !link.url ? 'opacity-50 cursor-not-allowed' : ''
                                    ]"
                                    v-html="link.label"
                                />
                            </div>
                        </nav>
                    </div>
                </div>
            </div>
        </div>

        <!-- Delete Confirmation Modal -->
        <Modal :show="showDeleteModal" @close="showDeleteModal = false">
            <div class="p-6">
                <h3 class="text-lg font-medium text-gray-900 dark:text-white">Delete Prompt</h3>
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                    Are you sure you want to delete "{{ promptToDelete?.name }}"? This action cannot be undone.
                </p>
                <div class="mt-6 flex justify-end space-x-3">
                    <button
                        @click="showDeleteModal = false"
                        class="px-4 py-2 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600"
                    >
                        Cancel
                    </button>
                    <button
                        @click="deletePrompt"
                        class="px-4 py-2 bg-red-600 text-white rounded-md text-sm hover:bg-red-700"
                    >
                        Delete
                    </button>
                </div>
            </div>
        </Modal>
    </AppLayout>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Modal from '@/Components/Modal.vue';
import { PlusIcon, ArrowDownTrayIcon, ArrowUpTrayIcon } from '@heroicons/vue/24/outline';
import debounce from 'lodash/debounce';

const props = defineProps({
    prompts: Object,
    stats: Object,
    filters: Object,
    categories: Object,
    prompt_types: Object,
    can: Object,
});

const localFilters = ref({
    search: props.filters?.search || '',
    category: props.filters?.category || '',
    type: props.filters?.type || '',
    status: props.filters?.status || '',
    scope: props.filters?.scope || '',
});

const selectedPrompts = ref([]);
const showBulkActions = ref(false);
const showDeleteModal = ref(false);
const promptToDelete = ref(null);

const hasFilters = computed(() => {
    return Object.values(localFilters.value).some(v => v !== '');
});

const allSelected = computed(() => {
    const selectablePrompts = props.prompts.data.filter(p => !p.is_system);
    return selectablePrompts.length > 0 && selectablePrompts.every(p => selectedPrompts.value.includes(p.id));
});

const debounceSearch = debounce(() => {
    applyFilters();
}, 300);

function applyFilters() {
    router.get(route('admin.ai-prompts.index'), localFilters.value, {
        preserveState: true,
        preserveScroll: true,
    });
}

function clearFilters() {
    localFilters.value = { search: '', category: '', type: '', status: '', scope: '' };
    applyFilters();
}

function toggleSelect(id) {
    const index = selectedPrompts.value.indexOf(id);
    if (index > -1) {
        selectedPrompts.value.splice(index, 1);
    } else {
        selectedPrompts.value.push(id);
    }
}

function toggleSelectAll() {
    const selectablePrompts = props.prompts.data.filter(p => !p.is_system);
    if (allSelected.value) {
        selectedPrompts.value = [];
    } else {
        selectedPrompts.value = selectablePrompts.map(p => p.id);
    }
}

function clearSelection() {
    selectedPrompts.value = [];
    showBulkActions.value = false;
}

function bulkActivate() {
    router.post(route('admin.ai-prompts.bulk-update'), {
        ids: selectedPrompts.value,
        action: 'activate',
    }, {
        onSuccess: () => {
            clearSelection();
        },
    });
}

function bulkDeactivate() {
    router.post(route('admin.ai-prompts.bulk-update'), {
        ids: selectedPrompts.value,
        action: 'deactivate',
    }, {
        onSuccess: () => {
            clearSelection();
        },
    });
}

function exportPrompts() {
    window.location.href = route('admin.ai-prompts.export');
}

function exportSelected() {
    window.location.href = route('admin.ai-prompts.export', { ids: selectedPrompts.value.join(',') });
}

function importPrompts(event) {
    const file = event.target.files[0];
    if (!file) return;

    const formData = new FormData();
    formData.append('file', file);
    formData.append('conflict_resolution', 'skip');

    router.post(route('admin.ai-prompts.import'), formData, {
        forceFormData: true,
    });

    event.target.value = '';
}

function duplicatePrompt(id) {
    router.post(route('admin.ai-prompts.duplicate', id));
}

function confirmDelete(prompt) {
    promptToDelete.value = prompt;
    showDeleteModal.value = true;
}

function deletePrompt() {
    router.delete(route('admin.ai-prompts.destroy', promptToDelete.value.id), {
        onSuccess: () => {
            showDeleteModal.value = false;
            promptToDelete.value = null;
        },
    });
}

function getTypeBadgeClass(type) {
    const classes = {
        'stage1_identification': 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200',
        'stage2_research': 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
        'stage2_ebay_enrichment': 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200',
    };
    return classes[type] || 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300';
}

function formatNumber(num) {
    return new Intl.NumberFormat().format(num);
}

// Page actions for the toolbar
const pageActions = computed(() => {
    const actions = []

    if (selectedPrompts.value.length > 0) {
        actions.push({
            label: `Bulk Actions (${selectedPrompts.value.length})`,
            icon: 'fas fa-tasks',
            onClick: () => showBulkActions.value = !showBulkActions.value,
            variant: 'primary'
        })
    }

    actions.push({
        label: 'Export',
        icon: 'fas fa-download',
        onClick: exportPrompts,
        variant: 'secondary'
    })

    if (props.can.create) {
        actions.push({
            label: 'Create Prompt',
            icon: 'fas fa-plus',
            href: route('admin.ai-prompts.create'),
            variant: 'primary'
        })
    }

    return actions
})
</script>
