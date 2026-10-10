<template>
    <AppLayout :title="isNew ? 'Create AI Prompt' : 'Edit AI Prompt'">
        <template #header>
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-4">
                    <Link :href="route('admin.ai-prompts.index')" class="text-gray-500 hover:text-gray-700 dark:text-gray-400">
                        <ArrowLeftIcon class="w-5 h-5" />
                    </Link>
                    <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                        {{ isNew ? 'Create AI Prompt' : 'Edit AI Prompt' }}
                    </h2>
                    <span v-if="prompt?.is_system" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">
                        System (Read-only)
                    </span>
                </div>
                <div v-if="!isNew && prompt?.can_edit" class="flex items-center space-x-4">
                    <Link :href="route('admin.ai-prompts.history', prompt.id)" class="text-gray-600 hover:text-gray-900 dark:text-gray-400">
                        View History
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                    <!-- Main Form -->
                    <div class="lg:col-span-2 space-y-6">
                        <!-- Basic Info -->
                        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Basic Information</h3>

                            <div class="space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Name</label>
                                    <input
                                        v-model="form.name"
                                        type="text"
                                        :disabled="prompt?.is_system"
                                        class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md shadow-sm disabled:opacity-50"
                                    />
                                    <p v-if="form.errors.name" class="mt-1 text-sm text-red-600">{{ form.errors.name }}</p>
                                </div>

                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Category</label>
                                        <select
                                            v-model="form.category_slug"
                                            :disabled="prompt?.is_system"
                                            class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md shadow-sm disabled:opacity-50"
                                        >
                                            <option value="">Select category...</option>
                                            <option v-for="(name, slug) in categories" :key="slug" :value="slug">
                                                {{ name }}
                                            </option>
                                        </select>
                                        <p v-if="form.errors.category_slug" class="mt-1 text-sm text-red-600">{{ form.errors.category_slug }}</p>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Prompt Type</label>
                                        <select
                                            v-model="form.prompt_type"
                                            :disabled="prompt?.is_system"
                                            class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md shadow-sm disabled:opacity-50"
                                        >
                                            <option value="">Select type...</option>
                                            <option v-for="(label, type) in prompt_types" :key="type" :value="type">
                                                {{ label }}
                                            </option>
                                        </select>
                                        <p v-if="form.errors.prompt_type" class="mt-1 text-sm text-red-600">{{ form.errors.prompt_type }}</p>
                                    </div>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Description</label>
                                    <textarea
                                        v-model="form.description"
                                        rows="2"
                                        :disabled="prompt?.is_system"
                                        class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md shadow-sm disabled:opacity-50"
                                    />
                                </div>

                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Priority</label>
                                        <input
                                            v-model.number="form.priority"
                                            type="number"
                                            min="0"
                                            max="1000"
                                            :disabled="prompt?.is_system"
                                            class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md shadow-sm disabled:opacity-50"
                                        />
                                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Higher priority prompts are used first</p>
                                    </div>

                                    <div class="flex items-center pt-6">
                                        <label class="flex items-center">
                                            <input
                                                v-model="form.is_active"
                                                type="checkbox"
                                                :disabled="prompt?.is_system"
                                                class="rounded border-gray-300 dark:border-gray-600 text-indigo-600 shadow-sm disabled:opacity-50"
                                            />
                                            <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Active</span>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Prompt Content -->
                        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                            <div class="flex items-center justify-between mb-4">
                                <h3 class="text-lg font-medium text-gray-900 dark:text-white">Prompt Content</h3>
                                <div class="flex items-center space-x-4">
                                    <span class="text-sm text-gray-500 dark:text-gray-400">
                                        {{ form.prompt_content?.length || 0 }} characters
                                    </span>
                                    <select
                                        v-if="isNew"
                                        @change="applyTemplate($event.target.value)"
                                        class="text-sm border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md"
                                    >
                                        <option value="">Load template...</option>
                                        <option v-for="(template, key) in templates" :key="key" :value="key">
                                            {{ template.name }}
                                        </option>
                                    </select>
                                </div>
                            </div>

                            <!-- Variable Toolbar -->
                            <div class="mb-4 p-3 bg-gray-50 dark:bg-gray-900 rounded-lg">
                                <p class="text-xs text-gray-500 dark:text-gray-400 mb-2">Click to insert variable:</p>
                                <div class="flex flex-wrap gap-2">
                                    <button
                                        v-for="(desc, variable) in variables"
                                        :key="variable"
                                        type="button"
                                        @click="insertVariable(variable)"
                                        :disabled="prompt?.is_system"
                                        class="inline-flex items-center px-2 py-1 text-xs font-mono bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded hover:bg-indigo-50 dark:hover:bg-indigo-900/30 disabled:opacity-50"
                                        :title="desc"
                                    >
                                        {<span class="text-indigo-600 dark:text-indigo-400">{{ variable }}</span>}
                                    </button>
                                </div>
                            </div>

                            <textarea
                                ref="promptTextarea"
                                v-model="form.prompt_content"
                                rows="20"
                                :disabled="prompt?.is_system"
                                class="block w-full font-mono text-sm border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md shadow-sm disabled:opacity-50"
                                placeholder="Enter your prompt content here..."
                            />
                            <p v-if="form.errors.prompt_content" class="mt-1 text-sm text-red-600">{{ form.errors.prompt_content }}</p>

                            <!-- Variable Status -->
                            <div v-if="prompt?.undefined_variables?.length > 0" class="mt-2 p-2 bg-yellow-50 dark:bg-yellow-900/30 rounded-lg">
                                <p class="text-sm text-yellow-700 dark:text-yellow-400">
                                    Warning: Undefined variables found: {{ prompt.undefined_variables.join(', ') }}
                                </p>
                            </div>
                        </div>

                        <!-- Change Reason (for updates) -->
                        <div v-if="!isNew && prompt?.can_edit" class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Change Reason (optional)</label>
                            <input
                                v-model="form.change_reason"
                                type="text"
                                placeholder="Describe why you made these changes..."
                                class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md shadow-sm"
                            />
                        </div>

                        <!-- Actions -->
                        <div class="flex items-center justify-between">
                            <Link :href="route('admin.ai-prompts.index')" class="text-gray-600 hover:text-gray-900 dark:text-gray-400">
                                Cancel
                            </Link>
                            <button
                                v-if="!prompt?.is_system"
                                @click="savePrompt"
                                :disabled="form.processing"
                                class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 disabled:opacity-50"
                            >
                                {{ form.processing ? 'Saving...' : (isNew ? 'Create Prompt' : 'Save Changes') }}
                            </button>
                        </div>
                    </div>

                    <!-- Sidebar - Preview & Test -->
                    <div class="space-y-6">
                        <!-- Preview Panel -->
                        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                            <div class="flex items-center justify-between mb-4">
                                <h3 class="text-lg font-medium text-gray-900 dark:text-white">Preview</h3>
                                <button
                                    @click="previewPrompt"
                                    :disabled="previewLoading || !form.prompt_content"
                                    class="text-sm text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 disabled:opacity-50"
                                >
                                    {{ previewLoading ? 'Loading...' : 'Refresh' }}
                                </button>
                            </div>

                            <div v-if="previewResult" class="bg-gray-50 dark:bg-gray-900 rounded-lg p-4 text-sm font-mono max-h-96 overflow-auto whitespace-pre-wrap">
                                {{ previewResult }}
                            </div>
                            <div v-else class="text-sm text-gray-500 dark:text-gray-400 italic">
                                Click "Refresh" to preview with sample data
                            </div>
                        </div>

                        <!-- Test Panel -->
                        <div v-if="!isNew" class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                            <div class="flex items-center justify-between mb-4">
                                <h3 class="text-lg font-medium text-gray-900 dark:text-white">Test with API</h3>
                                <button
                                    @click="testPrompt"
                                    :disabled="testLoading || !form.prompt_content"
                                    class="text-sm text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 disabled:opacity-50"
                                >
                                    {{ testLoading ? 'Testing...' : 'Run Test' }}
                                </button>
                            </div>

                            <div v-if="testResult" class="space-y-2">
                                <div v-if="testResult.success" class="bg-green-50 dark:bg-green-900/30 rounded-lg p-3">
                                    <p class="text-sm text-green-700 dark:text-green-400 font-medium">Test successful!</p>
                                </div>
                                <div v-else class="bg-red-50 dark:bg-red-900/30 rounded-lg p-3">
                                    <p class="text-sm text-red-700 dark:text-red-400">{{ testResult.error }}</p>
                                </div>
                                <div v-if="testResult.response" class="bg-gray-50 dark:bg-gray-900 rounded-lg p-4 text-xs font-mono max-h-64 overflow-auto whitespace-pre-wrap">
                                    {{ testResult.response }}
                                </div>
                            </div>
                            <div v-else class="text-sm text-gray-500 dark:text-gray-400 italic">
                                Test the prompt with Gemini API (rate limited)
                            </div>
                        </div>

                        <!-- Sample Data -->
                        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Sample Data</h3>
                            <div class="space-y-2 text-sm">
                                <div v-for="(value, key) in sample_data" :key="key" class="flex">
                                    <span class="font-medium text-gray-600 dark:text-gray-400 w-32 flex-shrink-0">{{ key }}:</span>
                                    <span class="text-gray-900 dark:text-white truncate">{{ value }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Recent History -->
                        <div v-if="!isNew && prompt?.recent_history?.length > 0" class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Recent Changes</h3>
                            <div class="space-y-3">
                                <div v-for="entry in prompt.recent_history.slice(0, 5)" :key="entry.id" class="flex items-start text-sm">
                                    <div class="flex-shrink-0 w-20 text-gray-500 dark:text-gray-400">{{ entry.created_at }}</div>
                                    <div>
                                        <span class="font-medium text-gray-900 dark:text-white">{{ entry.action_label }}</span>
                                        <span v-if="entry.user" class="text-gray-500 dark:text-gray-400"> by {{ entry.user }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { ref, watch } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { ArrowLeftIcon } from '@heroicons/vue/24/outline';
import axios from 'axios';

const props = defineProps({
    prompt: Object,
    categories: Object,
    prompt_types: Object,
    variables: Object,
    templates: Object,
    sample_data: Object,
    is_new: Boolean,
});

const isNew = props.is_new;

const form = useForm({
    name: props.prompt?.name || '',
    category_slug: props.prompt?.category_slug || '',
    prompt_type: props.prompt?.prompt_type || '',
    description: props.prompt?.description || '',
    prompt_content: props.prompt?.prompt_content || '',
    variable_definitions: props.prompt?.variable_definitions || null,
    is_active: props.prompt?.is_active ?? true,
    priority: props.prompt?.priority || 0,
    change_reason: '',
});

const promptTextarea = ref(null);
const previewResult = ref('');
const previewLoading = ref(false);
const testResult = ref(null);
const testLoading = ref(false);

function insertVariable(variable) {
    const textarea = promptTextarea.value;
    if (!textarea) return;

    const start = textarea.selectionStart;
    const end = textarea.selectionEnd;
    const text = form.prompt_content;
    const insertion = `{${variable}}`;

    form.prompt_content = text.substring(0, start) + insertion + text.substring(end);

    // Restore cursor position
    setTimeout(() => {
        textarea.focus();
        textarea.setSelectionRange(start + insertion.length, start + insertion.length);
    }, 0);
}

function applyTemplate(templateKey) {
    if (!templateKey || !props.templates[templateKey]) return;
    form.prompt_content = props.templates[templateKey].content;
}

async function previewPrompt() {
    if (!props.prompt?.id && !form.prompt_content) return;

    previewLoading.value = true;
    try {
        // For new prompts, just do client-side rendering
        if (isNew) {
            previewResult.value = renderPromptLocally(form.prompt_content, props.sample_data);
        } else {
            const response = await axios.post(route('admin.ai-prompts.preview', props.prompt.id), {
                sample_data: props.sample_data,
            });
            previewResult.value = response.data.rendered;
        }
    } catch (error) {
        previewResult.value = 'Error loading preview';
    } finally {
        previewLoading.value = false;
    }
}

function renderPromptLocally(content, data) {
    let result = content;
    for (const [key, value] of Object.entries(data)) {
        result = result.replace(new RegExp(`\\{${key}\\}`, 'g'), value);
    }
    return result;
}

async function testPrompt() {
    if (!props.prompt?.id) return;

    testLoading.value = true;
    testResult.value = null;
    try {
        const response = await axios.post(route('admin.ai-prompts.test', props.prompt.id), {
            sample_data: props.sample_data,
        });
        testResult.value = response.data;
    } catch (error) {
        testResult.value = {
            success: false,
            error: error.response?.data?.error || 'Test failed',
        };
    } finally {
        testLoading.value = false;
    }
}

function savePrompt() {
    if (isNew) {
        form.post(route('admin.ai-prompts.store'));
    } else {
        form.put(route('admin.ai-prompts.update', props.prompt.id));
    }
}

// Auto-preview on content change
watch(() => form.prompt_content, () => {
    if (form.prompt_content) {
        previewResult.value = renderPromptLocally(form.prompt_content, props.sample_data);
    }
}, { immediate: true });
</script>
