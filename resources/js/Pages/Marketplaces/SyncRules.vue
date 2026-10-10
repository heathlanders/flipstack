<script setup>
import { ref, computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import axios from 'axios';

const props = defineProps({
    rules: { type: Array, default: () => [] },
    categories: { type: Array, default: () => [] },
    brands: { type: Array, default: () => [] },
    orgUsers: { type: Array, default: () => [] },
    conditionOptions: { type: Object, default: () => ({}) },
});

const rules = ref([...props.rules]);
const showModal = ref(false);
const editingRule = ref(null);
const saving = ref(false);
const deleteConfirmId = ref(null);

const emptyForm = () => ({
    name: '',
    description: '',
    marketplace: 'ebay',
    action: 'include',
    priority: 10,
    conditions: [],
    is_active: true,
});

const formData = ref(emptyForm());

const marketplaceOptions = [
    { value: 'ebay', label: 'eBay' },
    { value: 'shopify', label: 'Shopify' },
    { value: '*', label: 'All Marketplaces' },
];

const fieldOptions = [
    { value: 'category_id', label: 'Category', type: 'category' },
    { value: 'condition', label: 'Condition', type: 'condition' },
    { value: 'selling_price', label: 'Selling Price', type: 'price' },
    { value: 'brand_id', label: 'Brand', type: 'brand' },
    { value: 'created_by', label: 'Product Owner', type: 'user' },
];

const operatorsByType = {
    category: [
        { value: 'in', label: 'is one of' },
        { value: 'not_in', label: 'is not one of' },
    ],
    condition: [
        { value: 'in', label: 'is one of' },
        { value: 'not_in', label: 'is not one of' },
    ],
    price: [
        { value: 'between', label: 'is between' },
        { value: 'greater_than', label: 'is greater than' },
        { value: 'less_than', label: 'is less than' },
    ],
    brand: [
        { value: 'in', label: 'is one of' },
        { value: 'not_in', label: 'is not one of' },
    ],
    user: [
        { value: 'in', label: 'is one of' },
        { value: 'not_in', label: 'is not one of' },
    ],
};

const conditionList = computed(() =>
    Object.entries(props.conditionOptions).map(([value, label]) => ({ value, label }))
);

function getFieldType(fieldValue) {
    return fieldOptions.find(f => f.value === fieldValue)?.type ?? 'price';
}

function getOperatorsForField(fieldValue) {
    return operatorsByType[getFieldType(fieldValue)] ?? [];
}

function addCondition() {
    formData.value.conditions.push({ field: 'category_id', operator: 'in', value: [] });
}

function removeCondition(index) {
    formData.value.conditions.splice(index, 1);
}

function onFieldChange(condition) {
    const type = getFieldType(condition.field);
    const ops = operatorsByType[type] ?? [];
    condition.operator = ops[0]?.value ?? 'in';
    if (type === 'price' && condition.operator === 'between') {
        condition.value = [0, 1000];
    } else if (type === 'price') {
        condition.value = 0;
    } else {
        condition.value = [];
    }
}

function toggleMultiSelect(condition, itemValue) {
    const arr = Array.isArray(condition.value) ? [...condition.value] : [];
    const idx = arr.indexOf(itemValue);
    if (idx === -1) {
        arr.push(itemValue);
    } else {
        arr.splice(idx, 1);
    }
    condition.value = arr;
}

function isSelected(condition, itemValue) {
    return Array.isArray(condition.value) && condition.value.includes(itemValue);
}

function openCreateModal() {
    editingRule.value = null;
    formData.value = emptyForm();
    showModal.value = true;
}

function openEditModal(rule) {
    editingRule.value = rule;
    formData.value = {
        name: rule.name,
        description: rule.description ?? '',
        marketplace: rule.marketplace,
        action: rule.action,
        priority: rule.priority,
        conditions: rule.conditions ? rule.conditions.map(c => ({ ...c, value: Array.isArray(c.value) ? [...c.value] : c.value })) : [],
        is_active: rule.is_active,
    };
    showModal.value = true;
}

function closeModal() {
    showModal.value = false;
    editingRule.value = null;
}

async function saveRule() {
    saving.value = true;
    try {
        if (editingRule.value) {
            const resp = await axios.put(route('marketplaces.sync-rules.update', editingRule.value.id), formData.value);
            const idx = rules.value.findIndex(r => r.id === editingRule.value.id);
            if (idx !== -1) rules.value[idx] = resp.data.rule;
        } else {
            const resp = await axios.post(route('marketplaces.sync-rules.store'), formData.value);
            rules.value.push(resp.data.rule);
            rules.value.sort((a, b) => a.priority - b.priority);
        }
        closeModal();
    } catch (e) {
        alert(e.response?.data?.message ?? 'Failed to save rule.');
    } finally {
        saving.value = false;
    }
}

async function deleteRule(id) {
    try {
        await axios.delete(route('marketplaces.sync-rules.destroy', id));
        rules.value = rules.value.filter(r => r.id !== id);
    } catch (e) {
        alert('Failed to delete rule.');
    } finally {
        deleteConfirmId.value = null;
    }
}

async function toggleActive(rule) {
    try {
        const resp = await axios.put(route('marketplaces.sync-rules.update', rule.id), {
            ...rule,
            is_active: !rule.is_active,
        });
        const idx = rules.value.findIndex(r => r.id === rule.id);
        if (idx !== -1) rules.value[idx] = resp.data.rule;
    } catch (e) {
        alert('Failed to update rule.');
    }
}

async function movePriority(rule, direction) {
    const idx = rules.value.findIndex(r => r.id === rule.id);
    const swapIdx = idx + direction;
    if (swapIdx < 0 || swapIdx >= rules.value.length) return;

    const a = rules.value[idx];
    const b = rules.value[swapIdx];

    // swap priorities
    const tmp = a.priority;
    a.priority = b.priority;
    b.priority = tmp;

    rules.value.sort((x, y) => x.priority - y.priority);

    try {
        await axios.post(route('marketplaces.sync-rules.reorder'), {
            rules: rules.value.map((r, i) => ({ id: r.id, priority: i * 10 })),
        });
        rules.value.forEach((r, i) => { r.priority = i * 10; });
    } catch (e) {
        alert('Failed to save order.');
    }
}

function conditionSummary(rule) {
    if (!rule.conditions || rule.conditions.length === 0) return 'No conditions (matches all)';
    return rule.conditions.map(c => {
        const fieldLabel = fieldOptions.find(f => f.value === c.field)?.label ?? c.field;
        const opLabel = Object.values(operatorsByType).flat().find(o => o.value === c.operator)?.label ?? c.operator;
        const val = Array.isArray(c.value) ? c.value.join(', ') : c.value;
        return `${fieldLabel} ${opLabel} ${val}`;
    }).join(' AND ');
}

function marketplaceLabel(marketplace) {
    return marketplaceOptions.find(m => m.value === marketplace)?.label ?? marketplace;
}
</script>

<template>
    <AppLayout title="Marketplace Sync Rules">
        <Head title="Marketplace Sync Rules" />

        <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
            <!-- Header -->
            <div class="flex items-center justify-between mb-6">
                <div>
                    <div class="flex items-center gap-2 mb-1">
                        <Link :href="route('marketplaces.hub.index')" class="text-sm text-gray-500 dark:text-gray-400 hover:underline">
                            Marketplace Hub
                        </Link>
                        <span class="text-gray-400">/</span>
                        <span class="text-sm text-gray-700 dark:text-gray-300">Sync Rules</span>
                    </div>
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Marketplace Sync Rules</h1>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                        Control which products sync to which marketplaces. Rules are evaluated in priority order; first match wins.
                    </p>
                </div>
                <button
                    @click="openCreateModal"
                    class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition"
                >
                    Create Rule
                </button>
            </div>

            <!-- Decision model info -->
            <div class="mb-6 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4 text-sm text-blue-800 dark:text-blue-300">
                <strong>How sync decisions work:</strong>
                Per-product override (if set) takes precedence, then rules are evaluated in priority order (lowest first),
                then the default is to sync to all marketplaces.
            </div>

            <!-- Rules list -->
            <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700">
                <div v-if="rules.length === 0" class="p-8 text-center text-gray-500 dark:text-gray-400">
                    No sync rules yet. Create one to start filtering which products go to which marketplaces.
                </div>

                <div v-else class="divide-y divide-gray-200 dark:divide-gray-700">
                    <div
                        v-for="(rule, idx) in rules"
                        :key="rule.id"
                        class="p-4 flex items-start gap-4"
                        :class="{ 'opacity-50': !rule.is_active }"
                    >
                        <!-- Priority controls -->
                        <div class="flex flex-col gap-1 pt-1 flex-shrink-0">
                            <button
                                @click="movePriority(rule, -1)"
                                :disabled="idx === 0"
                                class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 disabled:opacity-30"
                                title="Move up"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/>
                                </svg>
                            </button>
                            <span class="text-xs text-gray-400 text-center">{{ rule.priority }}</span>
                            <button
                                @click="movePriority(rule, 1)"
                                :disabled="idx === rules.length - 1"
                                class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 disabled:opacity-30"
                                title="Move down"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                </svg>
                            </button>
                        </div>

                        <!-- Rule details -->
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-3 mb-1">
                                <span class="font-medium text-gray-900 dark:text-white">{{ rule.name }}</span>
                                <!-- Marketplace badge -->
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400">
                                    {{ marketplaceLabel(rule.marketplace) }}
                                </span>
                                <!-- Action badge -->
                                <span
                                    class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium"
                                    :class="rule.action === 'include'
                                        ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-400'
                                        : 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400'"
                                >
                                    {{ rule.action === 'include' ? 'Include' : 'Exclude' }}
                                </span>
                                <span v-if="!rule.is_active" class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400">
                                    Inactive
                                </span>
                            </div>
                            <p class="text-sm text-gray-500 dark:text-gray-400 truncate">{{ conditionSummary(rule) }}</p>
                            <p v-if="rule.description" class="text-xs text-gray-400 dark:text-gray-500 mt-0.5">{{ rule.description }}</p>
                        </div>

                        <!-- Actions -->
                        <div class="flex items-center gap-2 flex-shrink-0">
                            <!-- Active toggle -->
                            <button
                                @click="toggleActive(rule)"
                                class="text-xs text-gray-500 dark:text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-400"
                            >
                                {{ rule.is_active ? 'Disable' : 'Enable' }}
                            </button>
                            <button
                                @click="openEditModal(rule)"
                                class="text-sm text-indigo-600 dark:text-indigo-400 hover:underline"
                            >
                                Edit
                            </button>
                            <button
                                v-if="deleteConfirmId !== rule.id"
                                @click="deleteConfirmId = rule.id"
                                class="text-sm text-red-600 dark:text-red-400 hover:underline"
                            >
                                Delete
                            </button>
                            <template v-else>
                                <button
                                    @click="deleteRule(rule.id)"
                                    class="text-sm text-red-700 font-semibold hover:underline"
                                >
                                    Confirm
                                </button>
                                <button
                                    @click="deleteConfirmId = null"
                                    class="text-sm text-gray-500 hover:underline"
                                >
                                    Cancel
                                </button>
                            </template>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Create/Edit Modal -->
        <div v-if="showModal" class="fixed inset-0 z-50 overflow-y-auto" aria-modal="true">
            <div class="flex items-start justify-center min-h-screen pt-8 px-4 pb-20">
                <div class="fixed inset-0 bg-black/50" @click="closeModal"></div>

                <div class="relative bg-white dark:bg-gray-800 rounded-lg shadow-xl w-full max-w-2xl">
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                            {{ editingRule ? 'Edit Rule' : 'Create Rule' }}
                        </h3>
                        <button @click="closeModal" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>

                    <div class="px-6 py-4 space-y-4">
                        <!-- Name -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Rule Name</label>
                            <input
                                v-model="formData.name"
                                type="text"
                                class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 text-sm"
                                placeholder="e.g. Exclude electronics from Shopify"
                            />
                        </div>

                        <!-- Description -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Description (optional)</label>
                            <textarea
                                v-model="formData.description"
                                rows="2"
                                class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 text-sm"
                            ></textarea>
                        </div>

                        <div class="grid grid-cols-3 gap-4">
                            <!-- Marketplace -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Marketplace</label>
                                <select
                                    v-model="formData.marketplace"
                                    class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 text-sm"
                                >
                                    <option v-for="opt in marketplaceOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                                </select>
                            </div>

                            <!-- Action -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Action</label>
                                <select
                                    v-model="formData.action"
                                    class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 text-sm"
                                >
                                    <option value="include">Include</option>
                                    <option value="exclude">Exclude</option>
                                </select>
                            </div>

                            <!-- Priority -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Priority (lower = first)</label>
                                <input
                                    v-model.number="formData.priority"
                                    type="number"
                                    min="0"
                                    class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 text-sm"
                                />
                            </div>
                        </div>

                        <!-- Conditions -->
                        <div>
                            <div class="flex items-center justify-between mb-2">
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Conditions (all must match)</label>
                                <button
                                    @click="addCondition"
                                    class="text-sm text-indigo-600 dark:text-indigo-400 hover:underline"
                                >
                                    + Add Condition
                                </button>
                            </div>

                            <div v-if="formData.conditions.length === 0" class="text-sm text-gray-400 dark:text-gray-500 italic">
                                No conditions — rule matches all products.
                            </div>

                            <div class="space-y-3">
                                <div
                                    v-for="(condition, idx) in formData.conditions"
                                    :key="idx"
                                    class="border border-gray-200 dark:border-gray-600 rounded-lg p-3 space-y-2"
                                >
                                    <div class="flex gap-3">
                                        <!-- Field -->
                                        <select
                                            v-model="condition.field"
                                            @change="onFieldChange(condition)"
                                            class="flex-1 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 text-sm"
                                        >
                                            <option v-for="f in fieldOptions" :key="f.value" :value="f.value">{{ f.label }}</option>
                                        </select>

                                        <!-- Operator -->
                                        <select
                                            v-model="condition.operator"
                                            class="flex-1 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 text-sm"
                                        >
                                            <option
                                                v-for="op in getOperatorsForField(condition.field)"
                                                :key="op.value"
                                                :value="op.value"
                                            >{{ op.label }}</option>
                                        </select>

                                        <button @click="removeCondition(idx)" class="text-red-500 hover:text-red-700 flex-shrink-0">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                            </svg>
                                        </button>
                                    </div>

                                    <!-- Value: Category multi-select -->
                                    <div v-if="getFieldType(condition.field) === 'category'" class="flex flex-wrap gap-1 max-h-32 overflow-y-auto border border-gray-200 dark:border-gray-600 rounded p-2">
                                        <label
                                            v-for="cat in categories"
                                            :key="cat.id"
                                            class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs cursor-pointer"
                                            :class="isSelected(condition, cat.id)
                                                ? 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900/40 dark:text-indigo-300'
                                                : 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300'"
                                        >
                                            <input type="checkbox" class="hidden" :checked="isSelected(condition, cat.id)" @change="toggleMultiSelect(condition, cat.id)" />
                                            {{ cat.name }}
                                        </label>
                                        <span v-if="categories.length === 0" class="text-gray-400 text-xs">No categories available.</span>
                                    </div>

                                    <!-- Value: Condition multi-select -->
                                    <div v-else-if="getFieldType(condition.field) === 'condition'" class="flex flex-wrap gap-1 max-h-32 overflow-y-auto border border-gray-200 dark:border-gray-600 rounded p-2">
                                        <label
                                            v-for="cond in conditionList"
                                            :key="cond.value"
                                            class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs cursor-pointer"
                                            :class="isSelected(condition, cond.value)
                                                ? 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900/40 dark:text-indigo-300'
                                                : 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300'"
                                        >
                                            <input type="checkbox" class="hidden" :checked="isSelected(condition, cond.value)" @change="toggleMultiSelect(condition, cond.value)" />
                                            {{ cond.label }}
                                        </label>
                                    </div>

                                    <!-- Value: Price between -->
                                    <div v-else-if="getFieldType(condition.field) === 'price' && condition.operator === 'between'" class="flex gap-2 items-center">
                                        <input
                                            :value="Array.isArray(condition.value) ? condition.value[0] : 0"
                                            @input="condition.value = [parseFloat($event.target.value) || 0, Array.isArray(condition.value) ? condition.value[1] : 1000]"
                                            type="number" min="0" step="0.01"
                                            class="w-28 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 text-sm"
                                            placeholder="Min"
                                        />
                                        <span class="text-gray-400">to</span>
                                        <input
                                            :value="Array.isArray(condition.value) ? condition.value[1] : 1000"
                                            @input="condition.value = [Array.isArray(condition.value) ? condition.value[0] : 0, parseFloat($event.target.value) || 0]"
                                            type="number" min="0" step="0.01"
                                            class="w-28 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 text-sm"
                                            placeholder="Max"
                                        />
                                    </div>

                                    <!-- Value: Price single value -->
                                    <div v-else-if="getFieldType(condition.field) === 'price'">
                                        <input
                                            v-model.number="condition.value"
                                            type="number" min="0" step="0.01"
                                            class="w-36 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 text-sm"
                                            placeholder="Price"
                                        />
                                    </div>

                                    <!-- Value: Brand multi-select -->
                                    <div v-else-if="getFieldType(condition.field) === 'brand'" class="flex flex-wrap gap-1 max-h-32 overflow-y-auto border border-gray-200 dark:border-gray-600 rounded p-2">
                                        <label
                                            v-for="brand in brands"
                                            :key="brand.id"
                                            class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs cursor-pointer"
                                            :class="isSelected(condition, brand.id)
                                                ? 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900/40 dark:text-indigo-300'
                                                : 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300'"
                                        >
                                            <input type="checkbox" class="hidden" :checked="isSelected(condition, brand.id)" @change="toggleMultiSelect(condition, brand.id)" />
                                            {{ brand.name }}
                                        </label>
                                        <span v-if="brands.length === 0" class="text-gray-400 text-xs">No brands available.</span>
                                    </div>

                                    <!-- Value: User multi-select -->
                                    <div v-else-if="getFieldType(condition.field) === 'user'" class="flex flex-wrap gap-1 max-h-32 overflow-y-auto border border-gray-200 dark:border-gray-600 rounded p-2">
                                        <label
                                            v-for="u in orgUsers"
                                            :key="u.id"
                                            class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs cursor-pointer"
                                            :class="isSelected(condition, u.id)
                                                ? 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900/40 dark:text-indigo-300'
                                                : 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300'"
                                        >
                                            <input type="checkbox" class="hidden" :checked="isSelected(condition, u.id)" @change="toggleMultiSelect(condition, u.id)" />
                                            {{ u.name }}
                                        </label>
                                        <span v-if="orgUsers.length === 0" class="text-gray-400 text-xs">No users available.</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Is Active -->
                        <div class="flex items-center gap-3">
                            <input
                                v-model="formData.is_active"
                                type="checkbox"
                                id="is_active"
                                class="rounded border-gray-300 text-indigo-600"
                            />
                            <label for="is_active" class="text-sm text-gray-700 dark:text-gray-300">Rule is active</label>
                        </div>
                    </div>

                    <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700 flex justify-end gap-3">
                        <button
                            @click="closeModal"
                            class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-gray-600 transition"
                        >
                            Cancel
                        </button>
                        <button
                            @click="saveRule"
                            :disabled="saving || !formData.name"
                            class="px-4 py-2 text-sm font-medium text-white bg-indigo-600 border border-transparent rounded-md hover:bg-indigo-700 disabled:opacity-50 transition"
                        >
                            {{ saving ? 'Saving...' : (editingRule ? 'Update Rule' : 'Create Rule') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
