<template>
    <AdminLayout title="Commission Structures">
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200">
                        Commission Structures
                    </h2>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                        Manage commission rates, tiers, and calculation rules
                    </p>
                </div>
                <button
                    @click="showCreateModal = true"
                    class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors"
                >
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                    </svg>
                    Create Structure
                </button>
            </div>
        </template>

        <!-- Stats Overview -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
            <div class="bg-white dark:bg-gray-800 rounded-lg p-4 border border-gray-200 dark:border-gray-700">
                <div class="text-sm text-gray-500 dark:text-gray-400">Active Structures</div>
                <div class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ stats.active_count }}</div>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-lg p-4 border border-gray-200 dark:border-gray-700">
                <div class="text-sm text-gray-500 dark:text-gray-400">Suppliers Using</div>
                <div class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ stats.suppliers_count }}</div>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-lg p-4 border border-gray-200 dark:border-gray-700">
                <div class="text-sm text-gray-500 dark:text-gray-400">Avg. Commission Rate</div>
                <div class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ stats.avg_rate }}%</div>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-lg p-4 border border-gray-200 dark:border-gray-700">
                <div class="text-sm text-gray-500 dark:text-gray-400">Total Commission (MTD)</div>
                <div class="text-2xl font-bold text-green-600">{{ formatCurrency(stats.mtd_commission) }}</div>
            </div>
        </div>

        <!-- Commission Type Cards -->
        <div class="grid grid-cols-1 lg:grid-cols-2 xl:grid-cols-3 gap-6">
            <div
                v-for="structure in structures"
                :key="structure.id"
                class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden"
            >
                <!-- Header -->
                <div class="p-4 border-b border-gray-200 dark:border-gray-700 flex items-start justify-between">
                    <div class="flex items-center gap-3">
                        <div :class="[
                            'w-10 h-10 rounded-lg flex items-center justify-center',
                            getTypeColor(structure.type)
                        ]">
                            <component :is="getTypeIcon(structure.type)" class="w-5 h-5" />
                        </div>
                        <div>
                            <h3 class="font-semibold text-gray-900 dark:text-gray-100">
                                {{ structure.name }}
                            </h3>
                            <span :class="[
                                'inline-flex items-center px-2 py-0.5 rounded text-xs font-medium',
                                structure.is_active
                                    ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400'
                                    : 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-400'
                            ]">
                                {{ structure.is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </div>
                    </div>
                    <div class="flex items-center gap-1">
                        <button
                            @click="editStructure(structure)"
                            class="p-1.5 text-gray-500 hover:text-gray-700 dark:hover:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 rounded transition-colors"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/>
                            </svg>
                        </button>
                        <button
                            v-if="!structure.is_default"
                            @click="toggleActive(structure)"
                            class="p-1.5 text-gray-500 hover:text-gray-700 dark:hover:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 rounded transition-colors"
                        >
                            <svg v-if="structure.is_active" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/>
                            </svg>
                            <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Content -->
                <div class="p-4">
                    <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                        {{ structure.description || 'No description provided' }}
                    </p>

                    <!-- Commission Details Based on Type -->
                    <div class="space-y-3">
                        <!-- Percentage Type -->
                        <template v-if="structure.type === 'percentage'">
                            <div class="flex justify-between items-center py-2 border-b border-gray-100 dark:border-gray-700">
                                <span class="text-sm text-gray-500 dark:text-gray-400">Base Rate</span>
                                <span class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                                    {{ structure.base_rate }}%
                                </span>
                            </div>
                        </template>

                        <!-- Tiered Type -->
                        <template v-if="structure.type === 'tiered'">
                            <div class="text-sm text-gray-500 dark:text-gray-400 mb-2">Tier Rates</div>
                            <div class="space-y-1">
                                <div
                                    v-for="(tier, index) in structure.tiers"
                                    :key="index"
                                    class="flex justify-between items-center text-sm"
                                >
                                    <span class="text-gray-600 dark:text-gray-400">
                                        {{ formatCurrency(tier.min) }} - {{ tier.max ? formatCurrency(tier.max) : '∞' }}
                                    </span>
                                    <span class="font-medium text-gray-900 dark:text-gray-100">
                                        {{ tier.rate }}%
                                    </span>
                                </div>
                            </div>
                        </template>

                        <!-- Flat Fee Type -->
                        <template v-if="structure.type === 'flat_fee'">
                            <div class="flex justify-between items-center py-2 border-b border-gray-100 dark:border-gray-700">
                                <span class="text-sm text-gray-500 dark:text-gray-400">Fee per Item</span>
                                <span class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                                    {{ formatCurrency(structure.flat_fee) }}
                                </span>
                            </div>
                        </template>

                        <!-- Hybrid Type -->
                        <template v-if="structure.type === 'hybrid'">
                            <div class="space-y-2">
                                <div class="flex justify-between items-center">
                                    <span class="text-sm text-gray-500 dark:text-gray-400">Base Rate</span>
                                    <span class="font-medium text-gray-900 dark:text-gray-100">{{ structure.base_rate }}%</span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="text-sm text-gray-500 dark:text-gray-400">+ Flat Fee</span>
                                    <span class="font-medium text-gray-900 dark:text-gray-100">{{ formatCurrency(structure.flat_fee) }}</span>
                                </div>
                            </div>
                        </template>

                        <!-- Category Based -->
                        <template v-if="structure.type === 'category_based'">
                            <div class="text-sm text-gray-500 dark:text-gray-400 mb-2">Category Rates</div>
                            <div class="space-y-1 max-h-32 overflow-y-auto">
                                <div
                                    v-for="(rate, category) in structure.category_rates"
                                    :key="category"
                                    class="flex justify-between items-center text-sm"
                                >
                                    <span class="text-gray-600 dark:text-gray-400 truncate">{{ category }}</span>
                                    <span class="font-medium text-gray-900 dark:text-gray-100 ml-2">{{ rate }}%</span>
                                </div>
                            </div>
                        </template>

                        <!-- Min/Max Caps -->
                        <div v-if="structure.min_rate || structure.max_rate" class="pt-2 border-t border-gray-100 dark:border-gray-700">
                            <div class="flex justify-between text-xs text-gray-500 dark:text-gray-400">
                                <span>Rate Caps</span>
                                <span>
                                    {{ structure.min_rate || 0 }}% - {{ structure.max_rate || '∞' }}%
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Footer -->
                <div class="px-4 py-3 bg-gray-50 dark:bg-gray-700/50 border-t border-gray-200 dark:border-gray-700">
                    <div class="flex justify-between items-center text-sm">
                        <span class="text-gray-500 dark:text-gray-400">
                            {{ structure.suppliers_count || 0 }} suppliers using
                        </span>
                        <button
                            @click="viewSuppliers(structure)"
                            class="text-indigo-600 dark:text-indigo-400 hover:underline"
                        >
                            View suppliers →
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Create/Edit Modal -->
        <Modal :show="showCreateModal || showEditModal" @close="closeModals" max-width="2xl">
            <div class="p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-6">
                    {{ showEditModal ? 'Edit Commission Structure' : 'Create Commission Structure' }}
                </h3>

                <form @submit.prevent="submitForm" class="space-y-6">
                    <!-- Basic Info -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Name *
                            </label>
                            <input
                                v-model="form.name"
                                type="text"
                                required
                                class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                placeholder="e.g., Standard Commission"
                            />
                            <p v-if="form.errors.name" class="mt-1 text-sm text-red-600">{{ form.errors.name }}</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Commission Type *
                            </label>
                            <select
                                v-model="form.type"
                                required
                                class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            >
                                <option value="percentage">Percentage</option>
                                <option value="tiered">Tiered</option>
                                <option value="flat_fee">Flat Fee</option>
                                <option value="hybrid">Hybrid (% + Fee)</option>
                                <option value="category_based">Category Based</option>
                            </select>
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
                            placeholder="Describe when this commission structure should be used..."
                        ></textarea>
                    </div>

                    <!-- Percentage Type Fields -->
                    <div v-if="form.type === 'percentage'" class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-4">
                        <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">Percentage Settings</h4>
                        <div>
                            <label class="block text-sm text-gray-600 dark:text-gray-400 mb-1">Base Rate (%)</label>
                            <input
                                v-model.number="form.base_rate"
                                type="number"
                                step="0.01"
                                min="0"
                                max="100"
                                class="w-32 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            />
                        </div>
                    </div>

                    <!-- Tiered Type Fields -->
                    <div v-if="form.type === 'tiered'" class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-4">
                        <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">Tier Configuration</h4>
                        <div class="space-y-2">
                            <div
                                v-for="(tier, index) in form.tiers"
                                :key="index"
                                class="flex items-center gap-2"
                            >
                                <div class="flex-1 grid grid-cols-3 gap-2">
                                    <input
                                        v-model.number="tier.min"
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        placeholder="Min"
                                        class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    />
                                    <input
                                        v-model.number="tier.max"
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        placeholder="Max (empty = ∞)"
                                        class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    />
                                    <div class="relative">
                                        <input
                                            v-model.number="tier.rate"
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            max="100"
                                            placeholder="Rate"
                                            class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 pr-8"
                                        />
                                        <span class="absolute right-3 top-2 text-gray-500">%</span>
                                    </div>
                                </div>
                                <button
                                    @click="removeTier(index)"
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
                            @click="addTier"
                            type="button"
                            class="mt-2 text-sm text-indigo-600 dark:text-indigo-400 hover:underline"
                        >
                            + Add Tier
                        </button>
                    </div>

                    <!-- Flat Fee Type Fields -->
                    <div v-if="form.type === 'flat_fee'" class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-4">
                        <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">Flat Fee Settings</h4>
                        <div>
                            <label class="block text-sm text-gray-600 dark:text-gray-400 mb-1">Fee per Item ($)</label>
                            <input
                                v-model.number="form.flat_fee"
                                type="number"
                                step="0.01"
                                min="0"
                                class="w-32 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            />
                        </div>
                    </div>

                    <!-- Hybrid Type Fields -->
                    <div v-if="form.type === 'hybrid'" class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-4">
                        <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">Hybrid Settings</h4>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm text-gray-600 dark:text-gray-400 mb-1">Base Rate (%)</label>
                                <input
                                    v-model.number="form.base_rate"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    max="100"
                                    class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                />
                            </div>
                            <div>
                                <label class="block text-sm text-gray-600 dark:text-gray-400 mb-1">Flat Fee ($)</label>
                                <input
                                    v-model.number="form.flat_fee"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                />
                            </div>
                        </div>
                    </div>

                    <!-- Category Based Type Fields -->
                    <div v-if="form.type === 'category_based'" class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-4">
                        <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">Category Rates</h4>
                        <div class="space-y-2">
                            <div
                                v-for="(category, index) in form.categories"
                                :key="index"
                                class="flex items-center gap-2"
                            >
                                <input
                                    v-model="category.name"
                                    type="text"
                                    placeholder="Category name"
                                    class="flex-1 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                />
                                <div class="relative w-24">
                                    <input
                                        v-model.number="category.rate"
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        max="100"
                                        placeholder="Rate"
                                        class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 pr-6"
                                    />
                                    <span class="absolute right-2 top-2 text-gray-500 text-sm">%</span>
                                </div>
                                <button
                                    @click="removeCategory(index)"
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
                            @click="addCategory"
                            type="button"
                            class="mt-2 text-sm text-indigo-600 dark:text-indigo-400 hover:underline"
                        >
                            + Add Category
                        </button>
                        <div class="mt-4">
                            <label class="block text-sm text-gray-600 dark:text-gray-400 mb-1">Default Rate (for uncategorized) (%)</label>
                            <input
                                v-model.number="form.base_rate"
                                type="number"
                                step="0.01"
                                min="0"
                                max="100"
                                class="w-32 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            />
                        </div>
                    </div>

                    <!-- Rate Caps -->
                    <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-4">
                        <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">Rate Caps (Optional)</h4>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm text-gray-600 dark:text-gray-400 mb-1">Minimum Rate (%)</label>
                                <input
                                    v-model.number="form.min_rate"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    max="100"
                                    class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                />
                            </div>
                            <div>
                                <label class="block text-sm text-gray-600 dark:text-gray-400 mb-1">Maximum Rate (%)</label>
                                <input
                                    v-model.number="form.max_rate"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    max="100"
                                    class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                />
                            </div>
                        </div>
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
                            <span class="text-sm text-gray-700 dark:text-gray-300">Set as default structure</span>
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
                            {{ showEditModal ? 'Update Structure' : 'Create Structure' }}
                        </button>
                    </div>
                </form>
            </div>
        </Modal>

        <!-- Suppliers Using Structure Modal -->
        <Modal :show="showSuppliersModal" @close="showSuppliersModal = false">
            <div class="p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">
                    Suppliers Using "{{ selectedStructure?.name }}"
                </h3>
                <div v-if="structureSuppliers.length === 0" class="text-gray-500 dark:text-gray-400 text-center py-8">
                    No suppliers are using this commission structure.
                </div>
                <div v-else class="space-y-2 max-h-96 overflow-y-auto">
                    <Link
                        v-for="supplier in structureSuppliers"
                        :key="supplier.id"
                        :href="route('admin.suppliers.show', supplier.id)"
                        class="flex items-center justify-between p-3 hover:bg-gray-50 dark:hover:bg-gray-700 rounded-lg transition-colors"
                    >
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-full bg-gray-200 dark:bg-gray-600 flex items-center justify-center">
                                <span class="text-sm font-medium text-gray-600 dark:text-gray-300">
                                    {{ supplier.name.charAt(0) }}
                                </span>
                            </div>
                            <div>
                                <div class="font-medium text-gray-900 dark:text-gray-100">{{ supplier.name }}</div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">{{ supplier.tier?.name }}</div>
                            </div>
                        </div>
                        <span class="text-sm text-gray-500 dark:text-gray-400">
                            {{ supplier.effective_rate }}%
                        </span>
                    </Link>
                </div>
                <div class="mt-4 flex justify-end">
                    <button
                        @click="showSuppliersModal = false"
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
import { ref, watch } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Modal from '@/Components/Admin/Modal.vue';

const props = defineProps({
    structures: Array,
    stats: Object,
});

const showCreateModal = ref(false);
const showEditModal = ref(false);
const showSuppliersModal = ref(false);
const selectedStructure = ref(null);
const structureSuppliers = ref([]);

const form = useForm({
    name: '',
    description: '',
    type: 'percentage',
    base_rate: 15,
    flat_fee: 0,
    min_rate: null,
    max_rate: null,
    tiers: [{ min: 0, max: 100, rate: 15 }, { min: 100, max: null, rate: 12 }],
    categories: [{ name: '', rate: 15 }],
    is_active: true,
    is_default: false,
});

function formatCurrency(amount) {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD',
    }).format(amount);
}

function getTypeColor(type) {
    const colors = {
        percentage: 'bg-blue-100 text-blue-600 dark:bg-blue-900/30 dark:text-blue-400',
        tiered: 'bg-purple-100 text-purple-600 dark:bg-purple-900/30 dark:text-purple-400',
        flat_fee: 'bg-green-100 text-green-600 dark:bg-green-900/30 dark:text-green-400',
        hybrid: 'bg-orange-100 text-orange-600 dark:bg-orange-900/30 dark:text-orange-400',
        category_based: 'bg-pink-100 text-pink-600 dark:bg-pink-900/30 dark:text-pink-400',
    };
    return colors[type] || colors.percentage;
}

function getTypeIcon(type) {
    // Return simple SVG icons inline
    return {
        template: `<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>`,
    };
}

function editStructure(structure) {
    selectedStructure.value = structure;
    form.name = structure.name;
    form.description = structure.description || '';
    form.type = structure.type;
    form.base_rate = structure.base_rate || 15;
    form.flat_fee = structure.flat_fee || 0;
    form.min_rate = structure.min_rate;
    form.max_rate = structure.max_rate;
    form.tiers = structure.tiers || [{ min: 0, max: 100, rate: 15 }];
    form.categories = structure.category_rates 
        ? Object.entries(structure.category_rates).map(([name, rate]) => ({ name, rate }))
        : [{ name: '', rate: 15 }];
    form.is_active = structure.is_active;
    form.is_default = structure.is_default;
    showEditModal.value = true;
}

function closeModals() {
    showCreateModal.value = false;
    showEditModal.value = false;
    selectedStructure.value = null;
    form.reset();
}

function submitForm() {
    const data = { ...form.data() };
    
    // Transform categories to object
    if (data.type === 'category_based') {
        data.category_rates = {};
        data.categories.forEach(cat => {
            if (cat.name) {
                data.category_rates[cat.name] = cat.rate;
            }
        });
        delete data.categories;
    }

    if (showEditModal.value) {
        form.put(route('admin.commission-structures.update', selectedStructure.value.id), {
            preserveScroll: true,
            onSuccess: () => closeModals(),
        });
    } else {
        form.post(route('admin.commission-structures.store'), {
            preserveScroll: true,
            onSuccess: () => closeModals(),
        });
    }
}

function toggleActive(structure) {
    router.patch(route('admin.commission-structures.toggle', structure.id), {}, {
        preserveScroll: true,
    });
}

function addTier() {
    const lastTier = form.tiers[form.tiers.length - 1];
    form.tiers.push({
        min: lastTier?.max || 0,
        max: null,
        rate: 10,
    });
}

function removeTier(index) {
    if (form.tiers.length > 1) {
        form.tiers.splice(index, 1);
    }
}

function addCategory() {
    form.categories.push({ name: '', rate: 15 });
}

function removeCategory(index) {
    if (form.categories.length > 1) {
        form.categories.splice(index, 1);
    }
}

async function viewSuppliers(structure) {
    selectedStructure.value = structure;
    // In real app, fetch from API
    structureSuppliers.value = structure.suppliers || [];
    showSuppliersModal.value = true;
}
</script>
