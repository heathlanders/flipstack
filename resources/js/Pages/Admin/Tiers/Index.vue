<template>
    <AdminLayout title="Tier Management">
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200">
                        Supplier Tier Management
                    </h2>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                        Configure tier levels, requirements, and benefits
                    </p>
                </div>
                <button
                    @click="showCreateModal = true"
                    class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors"
                >
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                    </svg>
                    Create Tier
                </button>
            </div>
        </template>

        <!-- Tier Distribution Overview -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
            <!-- Distribution Chart -->
            <div class="lg:col-span-2 bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">
                    Supplier Distribution by Tier
                </h3>
                <div class="space-y-4">
                    <div v-for="tier in tiers" :key="tier.id">
                        <div class="flex justify-between items-center mb-1">
                            <div class="flex items-center gap-2">
                                <span
                                    class="w-3 h-3 rounded-full"
                                    :style="{ backgroundColor: tier.color }"
                                ></span>
                                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">
                                    {{ tier.name }}
                                </span>
                            </div>
                            <span class="text-sm text-gray-500 dark:text-gray-400">
                                {{ tier.suppliers_count }} suppliers ({{ getPercentage(tier.suppliers_count) }}%)
                            </span>
                        </div>
                        <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                            <div
                                class="h-2 rounded-full transition-all duration-500"
                                :style="{
                                    width: getPercentage(tier.suppliers_count) + '%',
                                    backgroundColor: tier.color
                                }"
                            ></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Stats -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">
                    Quick Stats
                </h3>
                <div class="space-y-4">
                    <div class="flex justify-between items-center pb-3 border-b border-gray-100 dark:border-gray-700">
                        <span class="text-gray-500 dark:text-gray-400">Total Suppliers</span>
                        <span class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ stats.total_suppliers }}</span>
                    </div>
                    <div class="flex justify-between items-center pb-3 border-b border-gray-100 dark:border-gray-700">
                        <span class="text-gray-500 dark:text-gray-400">Pending Evaluation</span>
                        <span class="text-2xl font-bold text-yellow-600">{{ stats.pending_evaluation }}</span>
                    </div>
                    <div class="flex justify-between items-center pb-3 border-b border-gray-100 dark:border-gray-700">
                        <span class="text-gray-500 dark:text-gray-400">Promotions (30d)</span>
                        <span class="text-2xl font-bold text-green-600">{{ stats.promotions_30d }}</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-gray-500 dark:text-gray-400">Demotions (30d)</span>
                        <span class="text-2xl font-bold text-red-600">{{ stats.demotions_30d }}</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tiers Configuration -->
        <div class="space-y-4">
            <div
                v-for="tier in sortedTiers"
                :key="tier.id"
                class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden"
            >
                <div class="p-6">
                    <div class="flex items-start justify-between">
                        <!-- Tier Info -->
                        <div class="flex items-start gap-4">
                            <div
                                class="w-12 h-12 rounded-xl flex items-center justify-center text-white text-lg font-bold"
                                :style="{ backgroundColor: tier.color }"
                            >
                                {{ tier.level }}
                            </div>
                            <div>
                                <div class="flex items-center gap-3">
                                    <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                                        {{ tier.name }}
                                    </h3>
                                    <span
                                        v-if="tier.is_default"
                                        class="px-2 py-0.5 bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400 text-xs rounded-full"
                                    >
                                        Default
                                    </span>
                                    <span
                                        :class="[
                                            'px-2 py-0.5 text-xs rounded-full',
                                            tier.is_active
                                                ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400'
                                                : 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-400'
                                        ]"
                                    >
                                        {{ tier.is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </div>
                                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                                    {{ tier.description || 'No description provided' }}
                                </p>
                            </div>
                        </div>

                        <!-- Actions -->
                        <div class="flex items-center gap-2">
                            <button
                                @click="editTier(tier)"
                                class="p-2 text-gray-500 hover:text-gray-700 dark:hover:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition-colors"
                            >
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/>
                                </svg>
                            </button>
                            <button
                                v-if="!tier.is_default && tier.suppliers_count === 0"
                                @click="deleteTier(tier)"
                                class="p-2 text-red-500 hover:text-red-700 hover:bg-red-50 dark:hover:bg-red-900/30 rounded-lg transition-colors"
                            >
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                </svg>
                            </button>
                        </div>
                    </div>

                    <!-- Tier Details Grid -->
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mt-6">
                        <!-- Score Requirements -->
                        <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-4">
                            <h4 class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-2">
                                Score Requirements
                            </h4>
                            <div class="space-y-1">
                                <div class="flex justify-between">
                                    <span class="text-sm text-gray-600 dark:text-gray-400">Minimum</span>
                                    <span class="font-semibold text-gray-900 dark:text-gray-100">
                                        {{ tier.min_score }}
                                    </span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-sm text-gray-600 dark:text-gray-400">Maximum</span>
                                    <span class="font-semibold text-gray-900 dark:text-gray-100">
                                        {{ tier.max_score || '∞' }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Commission Adjustment -->
                        <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-4">
                            <h4 class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-2">
                                Commission Adjustment
                            </h4>
                            <div class="text-2xl font-bold" :class="[
                                tier.commission_adjustment < 0 ? 'text-green-600' : 
                                tier.commission_adjustment > 0 ? 'text-red-600' : 'text-gray-900 dark:text-gray-100'
                            ]">
                                {{ tier.commission_adjustment > 0 ? '+' : '' }}{{ tier.commission_adjustment }}%
                            </div>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                {{ tier.commission_adjustment < 0 ? 'Lower rate benefit' : tier.commission_adjustment > 0 ? 'Higher rate penalty' : 'No adjustment' }}
                            </p>
                        </div>

                        <!-- Benefits -->
                        <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-4">
                            <h4 class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-2">
                                Benefits
                            </h4>
                            <ul class="space-y-1">
                                <li
                                    v-for="(benefit, index) in (tier.benefits || []).slice(0, 3)"
                                    :key="index"
                                    class="text-sm text-gray-600 dark:text-gray-400 flex items-center gap-1"
                                >
                                    <svg class="w-3 h-3 text-green-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                    </svg>
                                    <span class="truncate">{{ benefit }}</span>
                                </li>
                            </ul>
                            <button
                                v-if="(tier.benefits || []).length > 3"
                                @click="viewAllBenefits(tier)"
                                class="text-xs text-indigo-600 dark:text-indigo-400 mt-1 hover:underline"
                            >
                                +{{ (tier.benefits || []).length - 3 }} more
                            </button>
                        </div>

                        <!-- Restrictions -->
                        <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-4">
                            <h4 class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-2">
                                Restrictions
                            </h4>
                            <ul class="space-y-1">
                                <li
                                    v-for="(restriction, index) in (tier.restrictions || []).slice(0, 3)"
                                    :key="index"
                                    class="text-sm text-gray-600 dark:text-gray-400 flex items-center gap-1"
                                >
                                    <svg class="w-3 h-3 text-red-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                                    </svg>
                                    <span class="truncate">{{ restriction }}</span>
                                </li>
                            </ul>
                            <span
                                v-if="!(tier.restrictions || []).length"
                                class="text-sm text-gray-400 dark:text-gray-500"
                            >
                                No restrictions
                            </span>
                        </div>
                    </div>

                    <!-- Suppliers in Tier -->
                    <div class="mt-6 pt-6 border-t border-gray-200 dark:border-gray-700">
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-gray-500 dark:text-gray-400">
                                <strong class="text-gray-900 dark:text-gray-100">{{ tier.suppliers_count }}</strong> 
                                suppliers in this tier
                            </span>
                            <Link
                                :href="route('admin.suppliers.index', { tier: tier.id })"
                                class="text-sm text-indigo-600 dark:text-indigo-400 hover:underline"
                            >
                                View suppliers →
                            </Link>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Create/Edit Tier Modal -->
        <Modal :show="showCreateModal || showEditModal" @close="closeModals" max-width="2xl">
            <div class="p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-6">
                    {{ showEditModal ? 'Edit Tier' : 'Create New Tier' }}
                </h3>

                <form @submit.prevent="submitForm" class="space-y-6">
                    <!-- Basic Info -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Tier Name *
                            </label>
                            <input
                                v-model="form.name"
                                type="text"
                                required
                                class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                placeholder="e.g., Gold"
                            />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Display Level *
                            </label>
                            <input
                                v-model.number="form.level"
                                type="number"
                                min="1"
                                required
                                class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                placeholder="1 = highest"
                            />
                            <p class="text-xs text-gray-500 mt-1">Lower number = higher tier</p>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Description
                        </label>
                        <textarea
                            v-model="form.description"
                            rows="2"
                            class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            placeholder="Describe this tier..."
                        ></textarea>
                    </div>

                    <!-- Styling -->
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Color
                            </label>
                            <div class="flex items-center gap-2">
                                <input
                                    v-model="form.color"
                                    type="color"
                                    class="w-10 h-10 rounded cursor-pointer border-0"
                                />
                                <input
                                    v-model="form.color"
                                    type="text"
                                    class="flex-1 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    placeholder="#FFD700"
                                />
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Icon (emoji)
                            </label>
                            <input
                                v-model="form.icon"
                                type="text"
                                class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                placeholder="🥇"
                            />
                        </div>
                    </div>

                    <!-- Score Requirements -->
                    <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-4">
                        <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">
                            Score Requirements
                        </h4>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm text-gray-600 dark:text-gray-400 mb-1">
                                    Minimum Score
                                </label>
                                <input
                                    v-model.number="form.min_score"
                                    type="number"
                                    min="0"
                                    max="100"
                                    class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                />
                            </div>
                            <div>
                                <label class="block text-sm text-gray-600 dark:text-gray-400 mb-1">
                                    Maximum Score
                                </label>
                                <input
                                    v-model.number="form.max_score"
                                    type="number"
                                    min="0"
                                    max="100"
                                    class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    placeholder="Leave empty for no max"
                                />
                            </div>
                        </div>
                    </div>

                    <!-- Commission Adjustment -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Commission Rate Adjustment (%)
                        </label>
                        <div class="flex items-center gap-2">
                            <input
                                v-model.number="form.commission_adjustment"
                                type="number"
                                step="0.5"
                                min="-10"
                                max="10"
                                class="w-32 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            />
                            <span class="text-sm text-gray-500 dark:text-gray-400">
                                {{ form.commission_adjustment < 0 ? 'discount' : form.commission_adjustment > 0 ? 'surcharge' : 'no change' }}
                            </span>
                        </div>
                        <p class="text-xs text-gray-500 mt-1">
                            Negative values reduce commission (benefit), positive values increase it (penalty)
                        </p>
                    </div>

                    <!-- Benefits -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Benefits
                        </label>
                        <div class="space-y-2">
                            <div
                                v-for="(benefit, index) in form.benefits"
                                :key="index"
                                class="flex items-center gap-2"
                            >
                                <input
                                    v-model="form.benefits[index]"
                                    type="text"
                                    class="flex-1 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    placeholder="Enter benefit..."
                                />
                                <button
                                    @click="form.benefits.splice(index, 1)"
                                    type="button"
                                    class="p-2 text-red-500 hover:bg-red-50 dark:hover:bg-red-900/30 rounded"
                                >
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                    </svg>
                                </button>
                            </div>
                        </div>
                        <button
                            @click="form.benefits.push('')"
                            type="button"
                            class="mt-2 text-sm text-indigo-600 dark:text-indigo-400 hover:underline"
                        >
                            + Add Benefit
                        </button>
                    </div>

                    <!-- Restrictions -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Restrictions
                        </label>
                        <div class="space-y-2">
                            <div
                                v-for="(restriction, index) in form.restrictions"
                                :key="index"
                                class="flex items-center gap-2"
                            >
                                <input
                                    v-model="form.restrictions[index]"
                                    type="text"
                                    class="flex-1 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    placeholder="Enter restriction..."
                                />
                                <button
                                    @click="form.restrictions.splice(index, 1)"
                                    type="button"
                                    class="p-2 text-red-500 hover:bg-red-50 dark:hover:bg-red-900/30 rounded"
                                >
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                    </svg>
                                </button>
                            </div>
                        </div>
                        <button
                            @click="form.restrictions.push('')"
                            type="button"
                            class="mt-2 text-sm text-indigo-600 dark:text-indigo-400 hover:underline"
                        >
                            + Add Restriction
                        </button>
                    </div>

                    <!-- Options -->
                    <div class="flex items-center gap-6">
                        <label class="flex items-center gap-2">
                            <input
                                v-model="form.is_active"
                                type="checkbox"
                                class="rounded border-gray-300 dark:border-gray-600 text-indigo-600 focus:ring-indigo-500"
                            />
                            <span class="text-sm text-gray-700 dark:text-gray-300">Active</span>
                        </label>
                        <label class="flex items-center gap-2">
                            <input
                                v-model="form.is_default"
                                type="checkbox"
                                class="rounded border-gray-300 dark:border-gray-600 text-indigo-600 focus:ring-indigo-500"
                            />
                            <span class="text-sm text-gray-700 dark:text-gray-300">Default tier for new suppliers</span>
                        </label>
                    </div>

                    <!-- Actions -->
                    <div class="flex justify-end gap-3 pt-4 border-t border-gray-200 dark:border-gray-700">
                        <button
                            @click="closeModals"
                            type="button"
                            class="px-4 py-2 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-md transition-colors"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 disabled:opacity-50 transition-colors"
                        >
                            {{ showEditModal ? 'Update Tier' : 'Create Tier' }}
                        </button>
                    </div>
                </form>
            </div>
        </Modal>

        <!-- Benefits Modal -->
        <Modal :show="showBenefitsModal" @close="showBenefitsModal = false">
            <div class="p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">
                    {{ selectedTier?.name }} Benefits
                </h3>
                <ul class="space-y-2">
                    <li
                        v-for="(benefit, index) in selectedTier?.benefits || []"
                        :key="index"
                        class="flex items-center gap-2 text-gray-700 dark:text-gray-300"
                    >
                        <svg class="w-4 h-4 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                        </svg>
                        {{ benefit }}
                    </li>
                </ul>
                <div class="mt-4 flex justify-end">
                    <button
                        @click="showBenefitsModal = false"
                        class="px-4 py-2 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-md transition-colors"
                    >
                        Close
                    </button>
                </div>
            </div>
        </Modal>
    </AdminLayout>
</template>

<script setup>
import { ref, computed } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Modal from '@/Components/Admin/Modal.vue';

const props = defineProps({
    tiers: Array,
    stats: Object,
});

const showCreateModal = ref(false);
const showEditModal = ref(false);
const showBenefitsModal = ref(false);
const selectedTier = ref(null);

const form = useForm({
    name: '',
    level: 1,
    description: '',
    color: '#6366F1',
    icon: '⭐',
    min_score: 0,
    max_score: null,
    commission_adjustment: 0,
    benefits: [''],
    restrictions: [],
    is_active: true,
    is_default: false,
});

const sortedTiers = computed(() => {
    return [...props.tiers].sort((a, b) => a.level - b.level);
});

const totalSuppliers = computed(() => {
    return props.tiers.reduce((sum, tier) => sum + (tier.suppliers_count || 0), 0);
});

function getPercentage(count) {
    if (totalSuppliers.value === 0) return 0;
    return Math.round((count / totalSuppliers.value) * 100);
}

function editTier(tier) {
    selectedTier.value = tier;
    form.name = tier.name;
    form.level = tier.level;
    form.description = tier.description || '';
    form.color = tier.color || '#6366F1';
    form.icon = tier.icon || '⭐';
    form.min_score = tier.min_score || 0;
    form.max_score = tier.max_score;
    form.commission_adjustment = tier.commission_adjustment || 0;
    form.benefits = tier.benefits?.length ? [...tier.benefits] : [''];
    form.restrictions = tier.restrictions?.length ? [...tier.restrictions] : [];
    form.is_active = tier.is_active;
    form.is_default = tier.is_default;
    showEditModal.value = true;
}

function closeModals() {
    showCreateModal.value = false;
    showEditModal.value = false;
    selectedTier.value = null;
    form.reset();
}

function submitForm() {
    // Filter out empty benefits/restrictions
    const data = {
        ...form.data(),
        benefits: form.benefits.filter(b => b.trim()),
        restrictions: form.restrictions.filter(r => r.trim()),
    };

    if (showEditModal.value) {
        form.transform(() => data).put(route('admin.tiers.update', selectedTier.value.id), {
            preserveScroll: true,
            onSuccess: () => closeModals(),
        });
    } else {
        form.transform(() => data).post(route('admin.tiers.store'), {
            preserveScroll: true,
            onSuccess: () => closeModals(),
        });
    }
}

function deleteTier(tier) {
    if (confirm(`Delete tier "${tier.name}"? This cannot be undone.`)) {
        router.delete(route('admin.tiers.destroy', tier.id), {
            preserveScroll: true,
        });
    }
}

function viewAllBenefits(tier) {
    selectedTier.value = tier;
    showBenefitsModal.value = true;
}
</script>
