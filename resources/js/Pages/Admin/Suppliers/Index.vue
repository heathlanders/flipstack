<template>
    <AppLayout
        title="Supplier Management"
        page-title="Supplier Management"
        :page-actions="pageActions"
    >

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <!-- Stats Overview -->
                <div class="grid grid-cols-1 md:grid-cols-5 gap-6 mb-8">
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-4">
                        <div class="text-sm text-gray-500 dark:text-gray-400">Total Suppliers</div>
                        <div class="text-2xl font-bold text-gray-900 dark:text-white">{{ stats.total }}</div>
                    </div>
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-4">
                        <div class="text-sm text-gray-500 dark:text-gray-400">Active</div>
                        <div class="text-2xl font-bold text-green-600">{{ stats.active }}</div>
                    </div>
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-4">
                        <div class="text-sm text-gray-500 dark:text-gray-400">Pending</div>
                        <div class="text-2xl font-bold text-yellow-600">{{ stats.pending }}</div>
                    </div>
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-4">
                        <div class="text-sm text-gray-500 dark:text-gray-400">On Probation</div>
                        <div class="text-2xl font-bold text-red-600">{{ stats.probation }}</div>
                    </div>
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-4">
                        <div class="text-sm text-gray-500 dark:text-gray-400">Pending Settlements</div>
                        <div class="text-2xl font-bold text-indigo-600">{{ formatCurrency(stats.pending_settlements) }}</div>
                    </div>
                </div>

                <!-- Needs-review banner -->
                <div
                    v-if="needsReviewCount > 0 && filters.filter !== 'needs-review'"
                    class="mb-4 p-3 rounded-lg bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 flex items-center justify-between gap-3"
                >
                    <div class="flex items-center gap-2 text-sm text-amber-800 dark:text-amber-200">
                        <i class="fas fa-circle-exclamation"></i>
                        <span>
                            <strong>{{ needsReviewCount }}</strong>
                            {{ needsReviewCount === 1 ? 'supplier needs' : 'suppliers need' }} review &mdash; auto-generated from imports without full contact info.
                        </span>
                    </div>
                    <button
                        type="button"
                        @click="setFilter('needs-review')"
                        class="text-xs font-semibold text-amber-900 dark:text-amber-100 underline hover:no-underline whitespace-nowrap"
                    >
                        Show needs review
                    </button>
                </div>

                <!-- Filters -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-4 mb-6">
                    <div class="flex flex-wrap gap-4 items-end">
                        <input
                            v-model="filters.search"
                            type="text"
                            placeholder="Search suppliers..."
                            class="border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md shadow-sm text-sm w-64"
                            @input="debounceSearch"
                        />
                        
                        <select 
                            v-model="filters.status"
                            @change="applyFilters"
                            class="border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md shadow-sm text-sm"
                        >
                            <option value="">All Statuses</option>
                            <option value="pending">Pending Review</option>
                            <option value="onboarding">Onboarding</option>
                            <option value="active">Active</option>
                            <option value="suspended">Suspended</option>
                            <option value="inactive">Inactive</option>
                            <option value="terminated">Terminated</option>
                        </select>

                        <select 
                            v-model="filters.tier"
                            @change="applyFilters"
                            class="border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md shadow-sm text-sm"
                        >
                            <option value="">All Tiers</option>
                            <option v-for="tier in tiers" :key="tier.id" :value="tier.id">
                                {{ tier.name }}
                            </option>
                        </select>

                        <select 
                            v-model="filters.sort"
                            @change="applyFilters"
                            class="border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md shadow-sm text-sm"
                        >
                            <option value="name">Name</option>
                            <option value="created_at">Newest</option>
                            <option value="performance_score">Performance</option>
                            <option value="total_sales">Sales</option>
                        </select>

                        <button
                            v-if="hasFilters"
                            @click="clearFilters"
                            class="text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400"
                        >
                            Clear filters
                        </button>
                    </div>

                    <!-- Quick filter chips -->
                    <div class="mt-3 flex items-center gap-2 text-xs">
                        <span class="text-gray-500 dark:text-gray-400">Quick:</span>
                        <button
                            type="button"
                            @click="setFilter('')"
                            :class="[
                                'px-2 py-0.5 rounded-full border transition-colors',
                                (filters.filter || '') === ''
                                    ? 'bg-indigo-100 dark:bg-indigo-900/40 text-indigo-700 dark:text-indigo-300 border-indigo-300 dark:border-indigo-700'
                                    : 'border-gray-300 dark:border-gray-600 text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700'
                            ]"
                        >All</button>
                        <button
                            type="button"
                            @click="setFilter('needs-review')"
                            :class="[
                                'px-2 py-0.5 rounded-full border transition-colors',
                                filters.filter === 'needs-review'
                                    ? 'bg-amber-100 dark:bg-amber-900/40 text-amber-700 dark:text-amber-300 border-amber-300 dark:border-amber-700'
                                    : 'border-gray-300 dark:border-gray-600 text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700'
                            ]"
                        >Needs review<span v-if="needsReviewCount > 0" class="ml-1 opacity-70">({{ needsReviewCount }})</span></button>
                    </div>
                </div>

                <!-- Bulk Actions Panel -->
                <Transition>
                    <div v-if="canManageAdminConfig && showBulkActions && selectedSuppliers.length > 0" class="bg-indigo-50 dark:bg-indigo-900/30 rounded-lg p-4 mb-6">
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-indigo-700 dark:text-indigo-300">
                                {{ selectedSuppliers.length }} supplier(s) selected
                            </span>
                            <div class="flex items-center space-x-4">
                                <select 
                                    v-model="bulkAction.type"
                                    class="border-indigo-300 dark:border-indigo-600 dark:bg-gray-700 dark:text-white rounded-md shadow-sm text-sm"
                                >
                                    <option value="">Select action...</option>
                                    <option value="status">Change Status</option>
                                    <option value="tier">Change Tier</option>
                                    <option value="commission">Update Commission</option>
                                    <option value="notify">Send Notification</option>
                                </select>

                                <select 
                                    v-if="bulkAction.type === 'status'"
                                    v-model="bulkAction.value"
                                    class="border-indigo-300 dark:border-indigo-600 dark:bg-gray-700 dark:text-white rounded-md shadow-sm text-sm"
                                >
                                    <option value="">Select status...</option>
                                    <option value="active">Active</option>
                                    <option value="suspended">Suspended</option>
                                    <option value="inactive">Inactive</option>
                                </select>

                                <select 
                                    v-if="bulkAction.type === 'tier'"
                                    v-model="bulkAction.value"
                                    class="border-indigo-300 dark:border-indigo-600 dark:bg-gray-700 dark:text-white rounded-md shadow-sm text-sm"
                                >
                                    <option value="">Select tier...</option>
                                    <option v-for="tier in tiers" :key="tier.id" :value="tier.id">
                                        {{ tier.name }}
                                    </option>
                                </select>

                                <button 
                                    @click="executeBulkAction"
                                    :disabled="!bulkAction.type || !bulkAction.value"
                                    class="px-4 py-2 bg-indigo-600 text-white rounded-md text-sm font-medium hover:bg-indigo-700 disabled:opacity-50"
                                >
                                    Apply
                                </button>
                                <button 
                                    @click="clearSelection"
                                    class="px-4 py-2 text-indigo-600 dark:text-indigo-400 text-sm font-medium hover:underline"
                                >
                                    Clear
                                </button>
                            </div>
                        </div>
                    </div>
                </Transition>

                <!-- Suppliers Table -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-900">
                            <tr>
                                <th class="px-6 py-3 text-left">
                                    <input 
                                        type="checkbox"
                                        :checked="allSelected"
                                        @change="toggleAllSelection"
                                        class="rounded border-gray-300 dark:border-gray-600 text-indigo-600"
                                    />
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    Supplier
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    Status
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    Tier
                                </th>
                                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    Score
                                </th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    Total Sales
                                </th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    Pending
                                </th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                            <tr 
                                v-for="supplier in suppliers.data" 
                                :key="supplier.id"
                                :class="selectedSuppliers.includes(supplier.id) ? 'bg-indigo-50 dark:bg-indigo-900/20' : ''"
                                class="hover:bg-gray-50 dark:hover:bg-gray-700"
                            >
                                <td class="px-6 py-4">
                                    <input 
                                        type="checkbox"
                                        :checked="selectedSuppliers.includes(supplier.id)"
                                        @change="toggleSupplierSelection(supplier.id)"
                                        class="rounded border-gray-300 dark:border-gray-600 text-indigo-600"
                                    />
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0 h-10 w-10">
                                            <div class="h-10 w-10 rounded-full bg-gray-200 dark:bg-gray-600 flex items-center justify-center">
                                                <span class="text-sm font-medium text-gray-600 dark:text-gray-300">
                                                    {{ getInitials(supplier.display_name || supplier.name) }}
                                                </span>
                                            </div>
                                        </div>
                                        <div class="ml-4">
                                            <div class="flex items-center gap-2">
                                                <Link
                                                    :href="route('admin.suppliers.show', supplier.id)"
                                                    class="text-sm font-medium text-gray-900 dark:text-white hover:text-indigo-600 dark:hover:text-indigo-400"
                                                >
                                                    {{ supplier.display_name || supplier.name }}
                                                </Link>
                                                <span v-if="supplier.code && supplier.code !== (supplier.display_name || supplier.name)" class="text-xs font-mono text-gray-500 dark:text-gray-400">{{ supplier.code }}</span>
                                                <span v-if="supplier.is_auto_generated" class="text-xs px-1.5 py-0.5 rounded bg-gray-200 text-gray-700 dark:bg-gray-700 dark:text-gray-300" title="Auto-generated from import; needs review">Auto</span>
                                                <span v-if="supplier.is_consignor" class="text-xs px-1.5 py-0.5 rounded bg-violet-100 text-violet-700 dark:bg-violet-900/30 dark:text-violet-300">Consignor</span>
                                            </div>
                                            <div class="text-sm text-gray-500 dark:text-gray-400">
                                                {{ supplier.email }}
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <span :class="getStatusBadgeClass(supplier.supplier_status)" class="px-2 py-1 text-xs font-medium rounded-full">
                                        {{ formatStatus(supplier.supplier_status) }}
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <span
                                        v-if="supplier.current_tier"
                                        :class="getTierBadgeClass(supplier.current_tier.code)"
                                        class="px-2 py-1 text-xs font-medium rounded-full"
                                    >
                                        {{ supplier.current_tier.name }}
                                    </span>
                                    <span v-else class="text-gray-400 text-sm">N/A</span>
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <div class="flex items-center justify-center">
                                        <span :class="getScoreColor(supplier.cached_performance_score)" class="text-sm font-medium">
                                            {{ supplier.cached_performance_score != null ? Number(supplier.cached_performance_score).toFixed(1) + '%' : 'N/A' }}
                                        </span>
                                        <component 
                                            v-if="supplier.score_trend"
                                            :is="supplier.score_trend > 0 ? ArrowUpIcon : ArrowDownIcon"
                                            :class="supplier.score_trend > 0 ? 'text-green-500' : 'text-red-500'"
                                            class="w-4 h-4 ml-1"
                                        />
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-right text-sm text-gray-900 dark:text-white">
                                    {{ formatCurrency(supplier.total_sales_ytd) }}
                                </td>
                                <td class="px-6 py-4 text-right text-sm">
                                    <span v-if="supplier.pending_balance > 0" class="text-yellow-600 dark:text-yellow-400">
                                        {{ formatCurrency(supplier.pending_balance) }}
                                    </span>
                                    <span v-else class="text-gray-400">$0.00</span>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <div class="flex items-center justify-end space-x-2">
                                        <Link 
                                            :href="route('admin.suppliers.show', supplier.id)"
                                            class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
                                            title="View Details"
                                        >
                                            <EyeIcon class="w-5 h-5" />
                                        </Link>
                                        <button 
                                            @click="openEditModal(supplier)"
                                            class="text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-400"
                                            title="Edit"
                                        >
                                            <PencilSquareIcon class="w-5 h-5" />
                                        </button>
                                        <button
                                            v-if="canManageAdminConfig"
                                            @click="generateSettlement(supplier)"
                                            class="text-gray-400 hover:text-green-600 dark:hover:text-green-400"
                                            title="Generate Settlement"
                                        >
                                            <CurrencyDollarIcon class="w-5 h-5" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <!-- Pagination -->
                    <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700">
                        <Pagination :links="suppliers.links" />
                    </div>
                </div>
            </div>
        </div>

        <!-- Create Supplier Modal -->
        <Modal :show="showCreateModal" @close="closeCreateModal" max-width="2xl">
            <div class="p-6">
                <h2 class="text-lg font-medium text-gray-900 dark:text-white mb-6">
                    Add Supplier
                </h2>

                <div class="grid grid-cols-2 gap-4">
                    <div class="col-span-2">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Name *</label>
                        <input v-model="createForm.name" type="text" class="w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md shadow-sm" />
                        <p v-if="createErrors.name" class="mt-1 text-sm text-red-600">{{ createErrors.name }}</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Email</label>
                        <input v-model="createForm.email" type="email" class="w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md shadow-sm" />
                        <p v-if="createErrors.email" class="mt-1 text-sm text-red-600">{{ createErrors.email }}</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Phone</label>
                        <input v-model="createForm.phone" type="text" class="w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md shadow-sm" />
                    </div>

                    <div class="col-span-2">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Contact Name</label>
                        <input v-model="createForm.contact_name" type="text" class="w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md shadow-sm" />
                    </div>

                    <div class="col-span-2">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Address</label>
                        <input v-model="createForm.address" type="text" class="w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md shadow-sm" />
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">City</label>
                        <input v-model="createForm.city" type="text" class="w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md shadow-sm" />
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">State</label>
                        <input v-model="createForm.state" type="text" class="w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md shadow-sm" />
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Zip Code</label>
                        <input v-model="createForm.zip_code" type="text" class="w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md shadow-sm" />
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Country</label>
                        <input v-model="createForm.country" type="text" class="w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md shadow-sm" />
                    </div>

                    <div class="col-span-2">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Notes</label>
                        <textarea v-model="createForm.notes" rows="3" class="w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md shadow-sm"></textarea>
                    </div>
                </div>

                <div class="mt-6 flex justify-end space-x-3">
                    <button @click="closeCreateModal" class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-700 rounded-md hover:bg-gray-200 dark:hover:bg-gray-600">
                        Cancel
                    </button>
                    <button @click="submitCreateForm" class="px-4 py-2 text-sm font-medium text-white bg-indigo-600 rounded-md hover:bg-indigo-700">
                        Create Supplier
                    </button>
                </div>
            </div>
        </Modal>

        <!-- Edit Modal -->
        <Modal :show="showEditModal" @close="closeEditModal" max-width="3xl">
            <div class="p-6 max-h-[80vh] overflow-y-auto">
                <div class="flex items-center justify-between mb-6">
                    <h2 class="text-lg font-medium text-gray-900 dark:text-white">
                        Edit Supplier: {{ editingSupplier?.display_name || editingSupplier?.name }}
                    </h2>
                    <span v-if="editingSupplier?.is_auto_generated" class="text-xs px-2 py-0.5 rounded bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300">Auto-generated &mdash; needs review</span>
                </div>

                <!-- Identification -->
                <div class="mb-6">
                    <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wide mb-3">Identification</h3>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Code</label>
                            <input
                                :value="editingSupplier?.code || ''"
                                type="text"
                                readonly
                                class="w-full bg-gray-100 dark:bg-gray-900 border-gray-300 dark:border-gray-700 dark:text-gray-300 rounded-md shadow-sm font-mono text-sm cursor-not-allowed"
                            />
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Set automatically from SKU prefix on import.</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Display Name <span class="text-red-500">*</span></label>
                            <input v-model="editForm.name" type="text" required class="w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md shadow-sm" />
                        </div>
                        <div class="col-span-2">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Company / Legal Name</label>
                            <input v-model="editForm.company_name" type="text" placeholder="e.g. Amplio Marketplace" class="w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md shadow-sm" />
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">If set, displays everywhere. Leave blank to use Display Name.</p>
                        </div>
                        <div class="col-span-2">
                            <label class="flex items-center">
                                <input v-model="editForm.is_consignor" type="checkbox" class="rounded border-gray-300 text-violet-600 shadow-sm focus:ring-violet-500" />
                                <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">This supplier is a consignor</span>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Contact -->
                <div class="mb-6 pt-4 border-t border-gray-200 dark:border-gray-700">
                    <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wide mb-3">Contact</h3>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Contact Name</label>
                            <input v-model="editForm.contact_name" type="text" class="w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md shadow-sm" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Email</label>
                            <input v-model="editForm.email" type="email" class="w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md shadow-sm" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Phone</label>
                            <input v-model="editForm.phone" type="text" class="w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md shadow-sm" />
                        </div>
                        <div class="col-span-2">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Street Address</label>
                            <input v-model="editForm.address_line1" type="text" class="w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md shadow-sm" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">City</label>
                            <input v-model="editForm.city" type="text" class="w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md shadow-sm" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">State</label>
                            <input v-model="editForm.state" type="text" class="w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md shadow-sm" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Postal Code</label>
                            <input v-model="editForm.postal_code" type="text" class="w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md shadow-sm" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Country</label>
                            <input v-model="editForm.country" type="text" class="w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md shadow-sm" />
                        </div>
                    </div>
                </div>

                <!-- Business Terms -->
                <div class="mb-6 pt-4 border-t border-gray-200 dark:border-gray-700">
                    <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wide mb-3">Business Terms</h3>
                    <div class="grid grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Business Type</label>
                            <input v-model="editForm.business_type" type="text" placeholder="wholesaler / consignor" class="w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md shadow-sm" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Payment Terms (days)</label>
                            <input v-model.number="editForm.payment_terms" type="number" min="0" max="365" placeholder="30" class="w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md shadow-sm" />
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Days from invoice to payment due. 0 = COD.</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Currency</label>
                            <input v-model="editForm.currency" type="text" maxlength="10" placeholder="USD" class="w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md shadow-sm" />
                        </div>
                    </div>
                </div>

                <!-- Admin Configuration (Owner / Super Admin only) -->
                <div v-if="canManageAdminConfig" class="mb-6 pt-4 border-t border-gray-200 dark:border-gray-700">
                    <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wide mb-3">Admin Configuration</h3>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Status</label>
                            <select v-model="editForm.status" class="w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md shadow-sm">
                                <option value="pending">Pending Review</option>
                                <option value="onboarding">Onboarding</option>
                                <option value="active">Active</option>
                                <option value="suspended">Suspended</option>
                                <option value="inactive">Inactive</option>
                                <option value="terminated">Terminated</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Tier</label>
                            <select v-model="editForm.tier_id" class="w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md shadow-sm">
                                <option value="">&mdash;</option>
                                <option v-for="tier in tiers" :key="tier.id" :value="tier.id">{{ tier.name }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Commission Structure</label>
                            <select v-model="editForm.commission_structure_id" class="w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md shadow-sm">
                                <option value="">&mdash;</option>
                                <option v-for="structure in commissionStructures" :key="structure.id" :value="structure.id">{{ structure.name }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Settlement Frequency</label>
                            <select v-model="editForm.settlement_frequency" class="w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md shadow-sm">
                                <option value="weekly">Weekly</option>
                                <option value="bi_weekly">Bi-Weekly</option>
                                <option value="monthly">Monthly</option>
                                <option value="per_sale">Per Sale</option>
                            </select>
                        </div>
                        <div class="col-span-2">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Notes</label>
                            <textarea v-model="editForm.notes" rows="3" class="w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md shadow-sm"></textarea>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end space-x-3 pt-4 border-t border-gray-200 dark:border-gray-700">
                    <button
                        @click="closeEditModal"
                        class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-700 rounded-md hover:bg-gray-200 dark:hover:bg-gray-600"
                    >
                        Cancel
                    </button>
                    <button
                        @click="saveSupplier"
                        class="px-4 py-2 text-sm font-medium text-white bg-indigo-600 rounded-md hover:bg-indigo-700"
                    >
                        Save Changes
                    </button>
                </div>
            </div>
        </Modal>
    </AppLayout>
</template>

<script setup>
import { ref, reactive, computed } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Modal from '@/Components/Modal.vue';
import Pagination from '@/Components/Pagination.vue';
import {
    ArrowDownTrayIcon,
    EyeIcon,
    PencilSquareIcon,
    CurrencyDollarIcon,
    ArrowUpIcon,
    ArrowDownIcon,
    PlusIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps({
    suppliers: Object,
    tiers: Array,
    commissionStructures: Array,
    stats: Object,
    filters: { type: Object, default: () => ({}) },
    needsReviewCount: { type: Number, default: 0 },
    canManageAdminConfig: { type: Boolean, default: false },
});

const filters = reactive({
    search: props.filters?.search || '',
    status: props.filters?.status || '',
    tier: props.filters?.tier || '',
    sort: props.filters?.sort || 'name',
    filter: props.filters?.filter || '',
});

const needsReviewCount = computed(() => props.needsReviewCount || 0);

function setFilter(value) {
    filters.filter = value;
    applyFilters();
}

const selectedSuppliers = ref([]);
const showBulkActions = ref(false);
const bulkAction = reactive({
    type: '',
    value: ''
});

const showCreateModal = ref(false);
const createForm = reactive({
    name: '',
    email: '',
    phone: '',
    contact_name: '',
    address: '',
    city: '',
    state: '',
    zip_code: '',
    country: '',
    notes: '',
});
const createErrors = ref({});

const showEditModal = ref(false);
const editingSupplier = ref(null);
const editForm = reactive({
    status: '',
    tier_id: '',
    commission_structure_id: '',
    settlement_frequency: '',
    notes: '',
    name: '',
    company_name: '',
    contact_name: '',
    email: '',
    phone: '',
    address_line1: '',
    city: '',
    state: '',
    postal_code: '',
    country: '',
    business_type: '',
    payment_terms: '',
    currency: '',
    is_consignor: false,
});

let searchTimeout = null;

const hasFilters = computed(() => {
    return filters.search || filters.status || filters.tier;
});

const allSelected = computed(() => {
    return props.suppliers.data.length > 0 && 
           props.suppliers.data.every(s => selectedSuppliers.value.includes(s.id));
});

function formatCurrency(value) {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD'
    }).format(value || 0);
}

function formatStatus(status) {
    if (!status) return 'Unknown';
    return status.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
}

function getInitials(name) {
    if (!name) return '?';
    return name.split(' ').map(n => n[0]).join('').toUpperCase().slice(0, 2);
}

function getStatusBadgeClass(status) {
    const classes = {
        'pending': 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
        'onboarding': 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
        'active': 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
        'suspended': 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200',
        'inactive': 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300',
        'terminated': 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200'
    };
    return classes[status] || classes['inactive'];
}

function getTierBadgeClass(slug) {
    const classes = {
        'platinum': 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200',
        'gold': 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
        'silver': 'bg-gray-200 text-gray-800 dark:bg-gray-600 dark:text-gray-200',
        'bronze': 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200',
        'probation': 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200'
    };
    return classes[slug] || classes['silver'];
}

function getScoreColor(score) {
    if (!score) return 'text-gray-500';
    if (score >= 85) return 'text-green-600';
    if (score >= 70) return 'text-yellow-600';
    return 'text-red-600';
}

function debounceSearch() {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => applyFilters(), 300);
}

function applyFilters() {
    router.get(route('admin.suppliers.index'), filters, {
        preserveState: true,
        preserveScroll: true
    });
}

function clearFilters() {
    filters.search = '';
    filters.status = '';
    filters.tier = '';
    filters.sort = 'name';
    filters.filter = '';
    applyFilters();
}

function toggleSupplierSelection(id) {
    const index = selectedSuppliers.value.indexOf(id);
    if (index === -1) {
        selectedSuppliers.value.push(id);
    } else {
        selectedSuppliers.value.splice(index, 1);
    }
}

function toggleAllSelection() {
    if (allSelected.value) {
        selectedSuppliers.value = [];
    } else {
        selectedSuppliers.value = props.suppliers.data.map(s => s.id);
    }
}

function clearSelection() {
    selectedSuppliers.value = [];
    showBulkActions.value = false;
}

function executeBulkAction() {
    if (!bulkAction.type || !bulkAction.value || selectedSuppliers.value.length === 0) return;

    router.post(route('admin.suppliers.bulk-update'), {
        supplier_ids: selectedSuppliers.value,
        action: bulkAction.type,
        value: bulkAction.value
    }, {
        onSuccess: () => {
            clearSelection();
            bulkAction.type = '';
            bulkAction.value = '';
        }
    });
}

function openEditModal(supplier) {
    editingSupplier.value = supplier;
    editForm.status = supplier.supplier_status || '';
    editForm.tier_id = supplier.current_tier?.id || '';
    editForm.commission_structure_id = supplier.commission_structure?.id || '';
    editForm.settlement_frequency = supplier.settlement_schedule || 'monthly';
    editForm.notes = supplier.notes || '';
    editForm.name = supplier.name || '';
    editForm.company_name = supplier.company_name || '';
    editForm.contact_name = supplier.contact_name || '';
    editForm.email = supplier.email || '';
    editForm.phone = supplier.phone || '';
    editForm.address_line1 = supplier.address_line1 || '';
    editForm.city = supplier.city || '';
    editForm.state = supplier.state || '';
    editForm.postal_code = supplier.postal_code || '';
    editForm.country = supplier.country || '';
    editForm.business_type = supplier.business_type || '';
    editForm.payment_terms = supplier.payment_terms || '';
    editForm.currency = supplier.currency || '';
    editForm.is_consignor = Boolean(supplier.is_consignor);
    showEditModal.value = true;
}

function closeEditModal() {
    showEditModal.value = false;
    editingSupplier.value = null;
}

function saveSupplier() {
    router.put(route('admin.suppliers.update', editingSupplier.value.id), editForm, {
        onSuccess: () => closeEditModal()
    });
}

function generateSettlement(supplier) {
    if (confirm(`Generate settlement for ${supplier.display_name || supplier.name}?`)) {
        router.post(route('admin.suppliers.generate-settlement', supplier.id));
    }
}

function openCreateModal() {
    Object.keys(createForm).forEach(key => createForm[key] = '');
    createErrors.value = {};
    showCreateModal.value = true;
}

function closeCreateModal() {
    showCreateModal.value = false;
}

function submitCreateForm() {
    router.post(route('admin.suppliers.store'), createForm, {
        onSuccess: () => closeCreateModal(),
        onError: (errors) => createErrors.value = errors,
    });
}

function exportSuppliers() {
    window.location.href = route('admin.suppliers.export', filters);
}

// Page actions for the toolbar
const pageActions = computed(() => {
    const actions = []

    if (selectedSuppliers.value.length > 0) {
        actions.push({
            label: `Bulk Actions (${selectedSuppliers.value.length})`,
            icon: 'fas fa-tasks',
            onClick: () => showBulkActions.value = !showBulkActions.value,
            variant: 'primary'
        })
    }

    actions.push({
        label: 'Add Supplier',
        icon: 'fas fa-plus',
        onClick: openCreateModal,
        variant: 'primary'
    })

    actions.push({
        label: 'Export',
        icon: 'fas fa-download',
        onClick: exportSuppliers,
        variant: 'secondary'
    })

    return actions
})
</script>
