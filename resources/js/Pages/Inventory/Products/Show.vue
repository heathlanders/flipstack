<template>
    <AppLayout :title="product.name">
        <template #header>
            <div class="flex justify-between items-center">
                <div class="flex items-center space-x-4">
                    <Link
                        :href="route('inventory.products.index')"
                        class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                        </svg>
                    </Link>
                    <div>
                        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                            {{ product.name }}
                        </h2>
                        <p class="text-sm text-gray-500 dark:text-gray-400">SKU: {{ product.sku }}</p>
                    </div>
                </div>
                <div class="flex items-center space-x-3">
                    <span :class="statusBadgeClass">
                        {{ product.status_label }}
                    </span>
                    <Link
                        v-if="permissions.can_edit"
                        :href="route('inventory.products.edit', product.id)"
                        class="inline-flex items-center px-4 py-2 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md font-semibold text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest shadow-sm hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150"
                    >
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                        </svg>
                        Edit
                    </Link>
                    <button
                        v-if="permissions.can_delete"
                        type="button"
                        @click="confirmArchive"
                        :disabled="isLoadingImpact"
                        class="inline-flex items-center px-4 py-2 bg-red-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-red-700 focus:bg-red-700 active:bg-red-900 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 transition ease-in-out duration-150"
                    >
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4" />
                        </svg>
                        {{ isLoadingImpact ? 'Loading...' : 'Archive' }}
                    </button>
                </div>
            </div>
        </template>

        <div class="py-6">
            <!-- Toast Notification -->
            <transition
                enter-active-class="transition ease-out duration-300"
                enter-from-class="opacity-0 translate-y-2"
                enter-to-class="opacity-100 translate-y-0"
                leave-active-class="transition ease-in duration-200"
                leave-from-class="opacity-100 translate-y-0"
                leave-to-class="opacity-0 translate-y-2"
            >
                <div v-if="toast.show" class="fixed bottom-6 right-6 z-50">
                    <div
                        class="flex items-center gap-2 px-4 py-3 rounded-lg shadow-lg text-sm font-medium text-white"
                        :class="toast.type === 'error' ? 'bg-red-600' : 'bg-emerald-600'"
                    >
                        <svg v-if="toast.type !== 'error'" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        {{ toast.message }}
                        <button @click="toast.show = false" class="ml-2 hover:opacity-75">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                </div>
            </transition>

            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <!-- Archived Banner -->
                <div v-if="isArchived" class="mb-6 rounded-lg border border-amber-300 bg-amber-50 dark:bg-amber-900/20 dark:border-amber-700 p-4">
                    <div class="flex items-center">
                        <svg class="w-5 h-5 text-amber-600 dark:text-amber-400 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4" />
                        </svg>
                        <div class="flex-1">
                            <p class="text-sm font-medium text-amber-800 dark:text-amber-300">
                                This product is archived. It is hidden from listings and marketplace sync.
                            </p>
                        </div>
                        <div class="flex items-center space-x-2 ml-4">
                            <button
                                v-if="permissions.can_restore"
                                @click="restoreProduct"
                                :disabled="isProcessing"
                                class="inline-flex items-center px-3 py-1.5 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-widest hover:bg-gray-50 dark:hover:bg-gray-600 transition"
                            >
                                Restore
                            </button>
                            <button
                                v-if="permissions.can_force_delete"
                                @click="confirmForceDelete"
                                :disabled="isProcessing"
                                class="inline-flex items-center px-3 py-1.5 bg-red-600 border border-transparent rounded-md text-xs font-semibold text-white uppercase tracking-widest hover:bg-red-700 transition"
                            >
                                Permanently Delete
                            </button>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <!-- Main Content (2/3 width) -->
                    <div class="lg:col-span-2 space-y-6">
                        <!-- Marketplace Listings Card -->
                        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm">
                            <div class="p-6">
                                <div class="flex items-center justify-between mb-4">
                                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Marketplace Listings</h3>
                                    <div v-if="permissions.can_manage_marketplace && !isArchived" class="relative" ref="crossListDropdownRef">
                                        <button
                                            @click="showCrossListMenu = !showCrossListMenu"
                                            class="inline-flex items-center px-3 py-1.5 bg-indigo-600 text-white text-xs font-semibold rounded-md hover:bg-indigo-700 transition"
                                        >
                                            <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                            </svg>
                                            List on Marketplace
                                        </button>
                                        <div
                                            v-if="showCrossListMenu"
                                            class="absolute right-0 mt-1 w-56 bg-white dark:bg-gray-700 rounded-lg shadow-lg border border-gray-200 dark:border-gray-600 z-10"
                                        >
                                            <div class="py-1">
                                                <button
                                                    v-if="!hasActiveEbayListing"
                                                    @click="crossListTo('ebay')"
                                                    class="w-full text-left px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-600 flex items-center"
                                                >
                                                    <span class="w-2 h-2 rounded-full bg-blue-500 mr-3"></span>
                                                    List on eBay
                                                </button>
                                                <div v-else class="px-4 py-2 text-sm text-gray-400 dark:text-gray-500 flex items-center">
                                                    <span class="w-2 h-2 rounded-full bg-blue-500 mr-3 opacity-40"></span>
                                                    Already on eBay
                                                </div>
                                                <button
                                                    v-if="!hasActiveShopifyListing"
                                                    @click="crossListTo('shopify')"
                                                    class="w-full text-left px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-600 flex items-center"
                                                >
                                                    <span class="w-2 h-2 rounded-full bg-green-500 mr-3"></span>
                                                    List on Shopify
                                                </button>
                                                <div v-else class="px-4 py-2 text-sm text-gray-400 dark:text-gray-500 flex items-center">
                                                    <span class="w-2 h-2 rounded-full bg-green-500 mr-3 opacity-40"></span>
                                                    Already on Shopify
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- eBay Listings -->
                                <div v-if="hasEbayListings" class="space-y-3 mb-4">
                                    <div
                                        v-for="listing in product.ebay_listings"
                                        :key="listing.id"
                                        class="flex items-center justify-between p-3 rounded-lg border border-gray-200 dark:border-gray-700 hover:border-blue-300 dark:hover:border-blue-600 transition"
                                    >
                                        <div class="flex items-center gap-3 min-w-0">
                                            <span class="w-2 h-2 rounded-full bg-blue-500 flex-shrink-0"></span>
                                            <div class="min-w-0">
                                                <div class="flex items-center gap-2">
                                                    <span class="text-sm font-medium text-gray-900 dark:text-white">eBay</span>
                                                    <span
                                                        class="inline-flex px-1.5 py-0.5 rounded text-xs font-medium"
                                                        :class="marketplaceStatusClass(listing.status_color)"
                                                    >
                                                        {{ listing.status_label }}
                                                    </span>
                                                </div>
                                                <div class="flex items-center gap-3 mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                                                    <span v-if="listing.listed_price">${{ listing.listed_price }}</span>
                                                    <span v-if="listing.available_quantity != null">Qty: {{ listing.available_quantity }}</span>
                                                    <span v-if="listing.last_synced_at_human">Synced {{ listing.last_synced_at_human }}</span>
                                                    <span v-if="listing.credential_label" class="text-gray-400">{{ listing.credential_label }}</span>
                                                </div>
                                                <div v-if="listing.status === 'error' && listing.last_error_message" class="mt-1 text-xs text-red-600 dark:text-red-400 truncate max-w-md">
                                                    {{ listing.last_error_message }}
                                                </div>
                                            </div>
                                        </div>
                                        <div class="flex items-center gap-2 flex-shrink-0">
                                            <a
                                                v-if="listing.ebay_url"
                                                :href="listing.ebay_url"
                                                target="_blank"
                                                class="px-2 py-1 text-xs font-medium text-blue-700 dark:text-blue-400 bg-blue-50 dark:bg-blue-900/20 rounded hover:bg-blue-100 dark:hover:bg-blue-900/40"
                                                title="View on eBay"
                                            >
                                                View
                                            </a>
                                            <button
                                                v-if="listing.status === 'published' || listing.status === 'out_of_stock'"
                                                @click="syncListing('ebay', listing.id)"
                                                :disabled="syncing[listing.id]"
                                                class="px-2 py-1 text-xs font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-700 rounded hover:bg-gray-200 dark:hover:bg-gray-600 disabled:opacity-50"
                                            >
                                                {{ syncing[listing.id] ? '...' : 'Sync' }}
                                            </button>
                                            <button
                                                v-if="listing.status === 'error'"
                                                @click="retryListing('ebay', listing.id)"
                                                :disabled="syncing[listing.id]"
                                                class="px-2 py-1 text-xs font-medium text-red-700 dark:text-red-400 bg-red-50 dark:bg-red-900/20 rounded hover:bg-red-100 dark:hover:bg-red-900/40 disabled:opacity-50"
                                            >
                                                {{ syncing[listing.id] ? '...' : 'Retry' }}
                                            </button>
                                            <button
                                                v-if="!hasActiveShopifyListing && (listing.status === 'published' || listing.status === 'out_of_stock')"
                                                @click="crossListTo('shopify')"
                                                class="px-2 py-1 text-xs font-medium text-green-700 dark:text-green-400 bg-green-50 dark:bg-green-900/20 rounded hover:bg-green-100 dark:hover:bg-green-900/40"
                                                title="Cross-list to Shopify"
                                            >
                                                Cross-list
                                            </button>
                                            <Link
                                                :href="route('marketplaces.ebay.logs')"
                                                class="px-2 py-1 text-xs text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200"
                                                title="View sync logs"
                                            >
                                                Logs
                                            </Link>
                                        </div>
                                    </div>
                                </div>

                                <!-- Shopify Listings -->
                                <div v-if="hasShopifyListings" class="space-y-3 mb-4">
                                    <div
                                        v-for="listing in product.shopify_listings"
                                        :key="listing.id"
                                        class="flex items-center justify-between p-3 rounded-lg border border-gray-200 dark:border-gray-700 hover:border-green-300 dark:hover:border-green-600 transition"
                                    >
                                        <div class="flex items-center gap-3 min-w-0">
                                            <span class="w-2 h-2 rounded-full bg-green-500 flex-shrink-0"></span>
                                            <div class="min-w-0">
                                                <div class="flex items-center gap-2">
                                                    <span class="text-sm font-medium text-gray-900 dark:text-white">Shopify</span>
                                                    <span
                                                        class="inline-flex px-1.5 py-0.5 rounded text-xs font-medium"
                                                        :class="marketplaceStatusClass(listing.status_color)"
                                                    >
                                                        {{ listing.status_label }}
                                                    </span>
                                                </div>
                                                <div class="flex items-center gap-3 mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                                                    <span v-if="listing.listed_price">${{ listing.listed_price }}</span>
                                                    <span v-if="listing.inventory_quantity != null">Qty: {{ listing.inventory_quantity }}</span>
                                                    <span v-if="listing.last_synced_at_human">Synced {{ listing.last_synced_at_human }}</span>
                                                    <span v-if="listing.credential_label" class="text-gray-400">{{ listing.credential_label }}</span>
                                                </div>
                                                <div v-if="listing.status === 'error' && listing.last_error_message" class="mt-1 text-xs text-red-600 dark:text-red-400 truncate max-w-md">
                                                    {{ listing.last_error_message }}
                                                </div>
                                            </div>
                                        </div>
                                        <div class="flex items-center gap-2 flex-shrink-0">
                                            <a
                                                v-if="listing.shopify_url"
                                                :href="listing.shopify_url"
                                                target="_blank"
                                                class="px-2 py-1 text-xs font-medium text-green-700 dark:text-green-400 bg-green-50 dark:bg-green-900/20 rounded hover:bg-green-100 dark:hover:bg-green-900/40"
                                                title="View on Shopify"
                                            >
                                                View
                                            </a>
                                            <button
                                                v-if="['active', 'created', 'pending', 'draft'].includes(listing.status)"
                                                @click="syncListing('shopify', listing.id)"
                                                :disabled="syncing[listing.id]"
                                                class="px-2 py-1 text-xs font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-700 rounded hover:bg-gray-200 dark:hover:bg-gray-600 disabled:opacity-50"
                                            >
                                                {{ syncing[listing.id] ? '...' : 'Sync' }}
                                            </button>
                                            <button
                                                v-if="listing.status === 'error'"
                                                @click="retryListing('shopify', listing.id)"
                                                :disabled="syncing[listing.id]"
                                                class="px-2 py-1 text-xs font-medium text-red-700 dark:text-red-400 bg-red-50 dark:bg-red-900/20 rounded hover:bg-red-100 dark:hover:bg-red-900/40 disabled:opacity-50"
                                            >
                                                {{ syncing[listing.id] ? '...' : 'Retry' }}
                                            </button>
                                            <button
                                                v-if="!hasActiveEbayListing && ['active', 'created'].includes(listing.status)"
                                                @click="crossListTo('ebay')"
                                                class="px-2 py-1 text-xs font-medium text-blue-700 dark:text-blue-400 bg-blue-50 dark:bg-blue-900/20 rounded hover:bg-blue-100 dark:hover:bg-blue-900/40"
                                                title="Cross-list to eBay"
                                            >
                                                Cross-list
                                            </button>
                                            <Link
                                                :href="route('marketplaces.shopify.logs')"
                                                class="px-2 py-1 text-xs text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200"
                                                title="View sync logs"
                                            >
                                                Logs
                                            </Link>
                                        </div>
                                    </div>
                                </div>

                                <!-- Sync Status -->
                                <div v-if="marketplaceSyncStatus && Object.keys(marketplaceSyncStatus).length > 0" class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                                    <h4 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase mb-2">Sync Status</h4>
                                    <div class="flex flex-wrap gap-3">
                                        <div v-for="(status, platform) in marketplaceSyncStatus" :key="platform" class="flex items-center gap-1.5 text-xs">
                                            <span class="w-1.5 h-1.5 rounded-full" :class="status.syncs ? 'bg-green-500' : 'bg-gray-400'"></span>
                                            <span class="text-gray-700 dark:text-gray-300 capitalize">{{ platform }}</span>
                                            <span class="text-gray-400 dark:text-gray-500">{{ status.label }}</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Empty State -->
                                <div v-if="!hasEbayListings && !hasShopifyListings" class="text-center py-6">
                                    <svg class="w-10 h-10 mx-auto text-gray-300 dark:text-gray-600 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" />
                                    </svg>
                                    <p class="text-sm text-gray-500 dark:text-gray-400 mb-1">Not listed on any marketplace</p>
                                    <p v-if="permissions.can_manage_marketplace && !isArchived" class="text-xs text-gray-400 dark:text-gray-500">
                                        Use the "List on Marketplace" button above to get started
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Image Gallery Card -->
                        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm">
                            <div class="p-6">
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Images</h3>

                                <div v-if="product.featured_image_url || hasImages" class="space-y-4">
                                    <!-- Featured Image -->
                                    <div class="relative aspect-video bg-gray-100 dark:bg-gray-700 rounded-lg overflow-hidden">
                                        <img
                                            :src="selectedImage || product.featured_image_url || product.images?.[0]?.url"
                                            :alt="product.name"
                                            class="w-full h-full object-contain"
                                            :class="{ 'opacity-40': imageBusy }"
                                        />
                                        <div v-if="imageBusy" class="absolute inset-0 flex items-center justify-center">
                                            <svg class="w-8 h-8 animate-spin text-indigo-500" fill="none" viewBox="0 0 24 24">
                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                                            </svg>
                                        </div>
                                        <span
                                            v-if="currentImage && currentImage.bg_removed"
                                            class="absolute top-2 left-2 px-2 py-0.5 text-xs font-medium rounded bg-gray-900/70 text-white"
                                        >
                                            Background removed
                                        </span>
                                    </div>

                                    <!-- Image edit toolbar -->
                                    <div v-if="canEditImages && currentImage" class="flex flex-wrap items-center gap-2">
                                        <button
                                            type="button"
                                            @click="openReprocessModal"
                                            :disabled="imageBusy"
                                            class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-md border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 disabled:opacity-50"
                                        >
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" /></svg>
                                            Reprocess{{ selectedImageIds.length > 1 ? ` (${selectedImageIds.length})` : '' }}
                                        </button>
                                        <button
                                            type="button"
                                            @click="showRevertModal = true"
                                            :disabled="imageBusy || !currentImage.has_original"
                                            :title="!currentImage.has_original ? 'No original is tracked for this image' : 'Restore the untouched original'"
                                            class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-md border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 disabled:opacity-50"
                                        >
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 10h10a5 5 0 010 10h-1m-9-10l4-4m-4 4l4 4" /></svg>
                                            Revert
                                        </button>
                                        <button
                                            type="button"
                                            @click="replaceInput?.click()"
                                            :disabled="imageBusy"
                                            class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-md border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 disabled:opacity-50"
                                        >
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14M4 8h16" /></svg>
                                            Replace
                                        </button>
                                        <button
                                            v-if="hasAnyListing"
                                            type="button"
                                            @click="syncImagesToMarketplaces"
                                            :disabled="imageBusy"
                                            title="Push the current images to the marketplaces this product is listed on"
                                            class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-md border border-indigo-300 dark:border-indigo-600 text-indigo-700 dark:text-indigo-300 bg-indigo-50 dark:bg-indigo-900/30 hover:bg-indigo-100 dark:hover:bg-indigo-900/50 disabled:opacity-50"
                                        >
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4" /></svg>
                                            Sync to marketplaces
                                        </button>
                                        <input ref="replaceInput" type="file" accept="image/*" class="hidden" @change="onReplaceFile" />
                                    </div>

                                    <!-- Selection hint + select-all (only when multiple images) -->
                                    <div v-if="canEditImages && hasImages && product.images.length > 1" class="flex items-center justify-between text-xs text-gray-500 dark:text-gray-400">
                                        <span>Check photos to reprocess several at once. None checked = the photo shown above.</span>
                                        <button type="button" class="text-indigo-600 dark:text-indigo-400 hover:underline whitespace-nowrap ml-2" @click="toggleSelectAll">
                                            {{ allSelected ? 'Clear all' : 'Select all' }}
                                        </button>
                                    </div>

                                    <!-- Thumbnail Strip -->
                                    <div v-if="hasImages && product.images.length > 1" class="flex gap-2 overflow-x-auto pb-2">
                                        <div
                                            v-for="(image, index) in product.images"
                                            :key="image.id || index"
                                            class="relative w-16 h-16 flex-shrink-0"
                                        >
                                            <button
                                                @click="selectedImage = image.url"
                                                :class="[
                                                    'w-16 h-16 rounded-md overflow-hidden border-2 transition-colors',
                                                    selectedImage === image.url
                                                        ? 'border-indigo-500'
                                                        : 'border-transparent hover:border-gray-300 dark:hover:border-gray-600'
                                                ]"
                                            >
                                                <img
                                                    :src="image.url"
                                                    :alt="image.alt_text || `${product.name} image ${index + 1}`"
                                                    class="w-full h-full object-cover"
                                                />
                                            </button>
                                            <label
                                                v-if="canEditImages"
                                                class="absolute top-0.5 left-0.5 bg-white/90 dark:bg-gray-900/90 rounded p-0.5 cursor-pointer"
                                                @click.stop
                                            >
                                                <input
                                                    type="checkbox"
                                                    :value="image.id"
                                                    v-model="selectedImageIds"
                                                    class="w-3.5 h-3.5 rounded border-gray-300 dark:border-gray-600 text-indigo-600 cursor-pointer"
                                                />
                                            </label>
                                        </div>
                                    </div>
                                </div>

                                <div v-else class="aspect-video bg-gray-100 dark:bg-gray-700 rounded-lg flex items-center justify-center">
                                    <div class="text-center text-gray-400">
                                        <svg class="w-16 h-16 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                        </svg>
                                        <p class="text-sm">No images available</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Product Details Card -->
                        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm">
                            <div class="p-6">
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Product Details</h3>

                                <dl class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Name</dt>
                                        <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ product.name }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">SKU</dt>
                                        <dd class="mt-1 text-sm text-gray-900 dark:text-white font-mono">{{ product.sku }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Barcode</dt>
                                        <dd class="mt-1 text-sm text-gray-900 dark:text-white font-mono">{{ product.barcode || 'N/A' }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Type</dt>
                                        <dd class="mt-1 text-sm text-gray-900 dark:text-white capitalize">{{ product.type || 'Simple' }}</dd>
                                    </div>
                                </dl>
                            </div>
                        </div>

                        <!-- Part-Out Warning Banner -->
                        <div v-if="product.is_parted_out" class="bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-lg shadow-sm">
                            <div class="p-4 flex items-center space-x-3">
                                <svg class="w-5 h-5 text-amber-600 dark:text-amber-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z" />
                                </svg>
                                <div>
                                    <p class="text-sm font-medium text-amber-800 dark:text-amber-200">This product has been fully parted out</p>
                                    <p class="text-xs text-amber-600 dark:text-amber-400 mt-1">Available quantity is 0. Consider ending any active marketplace listings.</p>
                                </div>
                            </div>
                        </div>

                        <!-- Part-Out Origin Card (for components created from part-out) -->
                        <div v-if="product.parted_from_product_id" class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg shadow-sm">
                            <div class="p-4">
                                <h3 class="text-sm font-semibold text-blue-800 dark:text-blue-200 mb-2">Part-Out Origin</h3>
                                <p class="text-sm text-blue-700 dark:text-blue-300">
                                    This product was created from a part-out work order.
                                    <Link
                                        v-if="product.parted_from_product"
                                        :href="route('inventory.products.show', product.parted_from_product_id)"
                                        class="font-medium underline hover:text-blue-900 dark:hover:text-blue-100"
                                    >
                                        View parent product: {{ product.parted_from_product.name }}
                                    </Link>
                                </p>
                                <p v-if="product.part_out_work_order" class="text-xs text-blue-600 dark:text-blue-400 mt-1">
                                    Work Order: {{ product.part_out_work_order.work_order_number }}
                                    | Allocated Cost: ${{ Number(product.cost_price || 0).toFixed(2) }}
                                </p>
                            </div>
                        </div>

                        <!-- Component Breakdown Card (for products with AI-identified components) -->
                        <div v-if="product.component_breakdown && product.component_breakdown.length > 0" class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700">
                            <!-- Header -->
                            <div class="p-6 border-b border-gray-200 dark:border-gray-700">
                                <div class="flex items-start justify-between">
                                    <div>
                                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Internal Components</h3>
                                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                            {{ product.component_breakdown.length }} components across {{ componentCategories.length }} categories -- identified by AI analysis
                                        </p>
                                    </div>
                                    <div class="flex items-center space-x-2 flex-shrink-0">
                                        <Link
                                            :href="route('inventory.part-out.templates.create', { product_id: product.id })"
                                            class="inline-flex items-center px-3 py-2 text-sm font-medium text-indigo-700 bg-indigo-50 border border-indigo-200 rounded-lg hover:bg-indigo-100 dark:text-indigo-300 dark:bg-indigo-900/30 dark:border-indigo-700 dark:hover:bg-indigo-900/50 transition"
                                        >
                                            <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                                            Create Template
                                        </Link>
                                        <Link
                                            :href="route('inventory.part-out.work-orders.create', { product_id: product.id })"
                                            class="inline-flex items-center px-3 py-2 text-sm font-medium text-white bg-teal-600 border border-transparent rounded-lg hover:bg-teal-700 transition"
                                        >
                                            <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.121 14.121L19 19m-7-7l7-7m-7 7l-2.879 2.879M12 12L9.121 9.121m0 5.758a3 3 0 10-4.243 4.243 3 3 0 004.243-4.243zm0-5.758a3 3 0 10-4.243-4.243 3 3 0 004.243 4.243z" /></svg>
                                            Part Out Now
                                        </Link>
                                    </div>
                                </div>
                            </div>

                            <!-- Component Cards Grid -->
                            <div class="p-6">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div
                                        v-for="(comp, idx) in product.component_breakdown"
                                        :key="idx"
                                        class="relative rounded-lg border border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-700/50 overflow-hidden hover:shadow-md transition-shadow"
                                    >
                                        <!-- Category color accent bar -->
                                        <div class="absolute left-0 top-0 bottom-0 w-1" :class="getCategoryAccentColor(comp.category)"></div>

                                        <div class="pl-4 pr-4 py-4">
                                            <div class="flex items-start justify-between">
                                                <div class="flex-1 min-w-0">
                                                    <!-- Category badge -->
                                                    <span
                                                        class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold mb-2"
                                                        :class="getCategoryBadgeClasses(comp.category)"
                                                    >
                                                        {{ comp.category || 'Component' }}
                                                    </span>

                                                    <!-- Component name -->
                                                    <h4 class="text-sm font-semibold text-gray-900 dark:text-white leading-tight">{{ comp.name }}</h4>

                                                    <!-- Manufacturer -->
                                                    <p v-if="comp.manufacturer" class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">{{ comp.manufacturer }}</p>

                                                    <!-- Part number -->
                                                    <p v-if="comp.part_number" class="mt-1 text-xs text-gray-400 dark:text-gray-500 font-mono">{{ comp.part_number }}</p>

                                                    <!-- Specs summary -->
                                                    <div v-if="comp.specs && Object.keys(comp.specs).length > 0" class="mt-2 flex flex-wrap gap-1">
                                                        <span
                                                            v-for="(val, key) in comp.specs"
                                                            :key="key"
                                                            class="inline-flex items-center px-1.5 py-0.5 rounded text-xs bg-white dark:bg-gray-600 text-gray-600 dark:text-gray-300 border border-gray-200 dark:border-gray-500"
                                                        >
                                                            {{ val }}
                                                        </span>
                                                    </div>
                                                </div>

                                                <!-- Quantity badge -->
                                                <div class="ml-3 flex-shrink-0">
                                                    <span class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-gray-200 dark:bg-gray-600 text-sm font-bold text-gray-700 dark:text-gray-200">
                                                        {{ comp.quantity }}
                                                    </span>
                                                    <p class="text-center text-xs text-gray-400 dark:text-gray-500 mt-0.5">qty</p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Footer summary -->
                                <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700 flex items-center justify-between">
                                    <p class="text-sm text-gray-500 dark:text-gray-400">
                                        {{ totalComponentQuantity }} total components across {{ componentCategories.length }} categories
                                    </p>
                                    <Link
                                        :href="route('inventory.part-out.index')"
                                        class="text-sm text-teal-600 dark:text-teal-400 hover:text-teal-800 dark:hover:text-teal-300 font-medium"
                                    >
                                        Part-Out Hub
                                        <svg class="inline w-4 h-4 ml-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                                    </Link>
                                </div>
                            </div>
                        </div>

                        <!-- Parted Out Components Card (for parent products) -->
                        <div v-if="product.parted_out_components && product.parted_out_components.length > 0" class="bg-white dark:bg-gray-800 rounded-lg shadow-sm">
                            <div class="p-6">
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Extracted Components</h3>
                                <p class="text-sm text-gray-500 dark:text-gray-400 mb-3">Products created from parting out this unit:</p>
                                <div class="space-y-2">
                                    <Link
                                        v-for="comp in product.parted_out_components"
                                        :key="comp.id"
                                        :href="route('inventory.products.show', comp.id)"
                                        class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-700/50 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700"
                                    >
                                        <div>
                                            <p class="text-sm font-medium text-gray-900 dark:text-white">{{ comp.name }}</p>
                                            <p class="text-xs text-gray-500 dark:text-gray-400">SKU: {{ comp.sku }} | Cost: ${{ Number(comp.cost_price || 0).toFixed(2) }}</p>
                                        </div>
                                        <div class="text-right">
                                            <p class="text-sm font-medium text-gray-900 dark:text-white">${{ Number(comp.selling_price || 0).toFixed(2) }}</p>
                                            <p class="text-xs text-gray-500 dark:text-gray-400">Qty: {{ comp.available_quantity || 0 }}</p>
                                        </div>
                                    </Link>
                                </div>
                            </div>
                        </div>

                        <!-- Description Card -->
                        <div v-if="product.short_description || product.description" class="bg-white dark:bg-gray-800 rounded-lg shadow-sm">
                            <div class="p-6">
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Description</h3>

                                <div v-if="product.short_description" class="mb-4">
                                    <h4 class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">Short Description</h4>
                                    <p class="text-sm text-gray-900 dark:text-white">{{ product.short_description }}</p>
                                </div>

                                <div v-if="product.description">
                                    <h4 class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">Full Description</h4>
                                    <div class="text-sm text-gray-900 dark:text-white prose dark:prose-invert max-w-none" v-html="product.description"></div>
                                </div>
                            </div>
                        </div>

                        <!-- Physical Properties Card -->
                        <div v-if="hasPhysicalProperties" class="bg-white dark:bg-gray-800 rounded-lg shadow-sm">
                            <div class="p-6">
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Physical Properties</h3>

                                <dl class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div v-if="product.weight">
                                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Weight</dt>
                                        <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ product.weight }} lbs</dd>
                                    </div>
                                    <div v-if="product.dimensions">
                                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Dimensions (L x W x H)</dt>
                                        <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ product.dimensions }}</dd>
                                    </div>
                                    <div v-if="product.shipping_class">
                                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Shipping Class</dt>
                                        <dd class="mt-1 text-sm text-gray-900 dark:text-white capitalize">{{ product.shipping_class }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Requires Shipping</dt>
                                        <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ product.requires_shipping ? 'Yes' : 'No' }}</dd>
                                    </div>
                                </dl>
                            </div>
                        </div>

                        <!-- Supplier / Owner Card -->
                        <div v-if="hasSuppliers" class="bg-white dark:bg-gray-800 rounded-lg shadow-sm">
                            <div class="p-6">
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Supplier</h3>
                                <div class="space-y-3">
                                    <div
                                        v-for="s in product.suppliers"
                                        :key="s.id"
                                        class="border border-gray-200 dark:border-gray-700 rounded-lg p-3"
                                    >
                                        <div class="flex items-start justify-between gap-3">
                                            <div class="min-w-0">
                                                <div class="flex items-center gap-2">
                                                    <span class="text-sm font-mono font-semibold text-gray-900 dark:text-white">{{ s.code || '(no code)' }}</span>
                                                    <span v-if="s.is_consignor" class="text-xs px-1.5 py-0.5 rounded bg-violet-100 text-violet-700 dark:bg-violet-900/30 dark:text-violet-300">Consignor</span>
                                                    <span v-if="s.status && s.status !== 'active'" class="text-xs px-1.5 py-0.5 rounded bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300">{{ s.status }}</span>
                                                </div>
                                                <div v-if="s.name && s.name !== s.code" class="text-sm text-gray-600 dark:text-gray-400 mt-0.5">{{ s.name }}</div>
                                                <div v-if="s.supplier_sku" class="text-xs font-mono text-gray-500 dark:text-gray-400 mt-1 break-all">
                                                    Original SKU: {{ s.supplier_sku }}
                                                </div>
                                            </div>
                                            <div v-if="s.cost_price && Number(s.cost_price) > 0" class="text-right shrink-0">
                                                <div class="text-sm font-semibold text-gray-900 dark:text-white">${{ Number(s.cost_price).toFixed(2) }}</div>
                                                <div class="text-xs text-gray-500 dark:text-gray-400">cost</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Warehouse Location Card -->
                        <div v-if="hasInventoryLevels || hasParsedWarehouse" class="bg-white dark:bg-gray-800 rounded-lg shadow-sm">
                            <div class="p-6">
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Warehouse Location</h3>

                                <!-- Inventory levels (location + bin + zone) -->
                                <div v-if="hasInventoryLevels" class="space-y-3 mb-4">
                                    <div
                                        v-for="level in product.inventory_levels"
                                        :key="level.id"
                                        class="border border-gray-200 dark:border-gray-700 rounded-lg p-3 flex items-center justify-between gap-4"
                                    >
                                        <div class="text-sm">
                                            <div class="font-medium text-gray-900 dark:text-white">
                                                {{ level.location_name || 'Unassigned location' }}
                                            </div>
                                            <div v-if="level.bin" class="text-xs text-gray-600 dark:text-gray-400 mt-0.5">
                                                <span v-if="level.bin.zone">{{ level.bin.zone.code }} &middot; </span>
                                                Bin <span class="font-mono">{{ level.bin.code }}</span>
                                                <span v-if="level.bin.name"> &mdash; {{ level.bin.name }}</span>
                                            </div>
                                            <div v-else class="text-xs text-amber-600 dark:text-amber-400 mt-0.5">
                                                No bin assigned
                                            </div>
                                        </div>
                                        <div class="text-right">
                                            <div class="text-sm font-semibold text-gray-900 dark:text-white">{{ level.quantity_on_hand ?? 0 }}</div>
                                            <div class="text-xs text-gray-500 dark:text-gray-400">on hand</div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Parsed-from-SKU breadcrumb -->
                                <div v-if="hasParsedWarehouse" class="border-t border-gray-200 dark:border-gray-700 pt-3">
                                    <div class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-2">Parsed from SKU</div>
                                    <dl class="grid grid-cols-2 gap-x-4 gap-y-2 text-sm">
                                        <template v-for="(value, key) in product.parsed_warehouse_location" :key="key">
                                            <dt class="text-gray-500 dark:text-gray-400 capitalize">{{ formatWarehouseKey(key) }}</dt>
                                            <dd class="text-gray-900 dark:text-white font-mono">{{ value }}</dd>
                                        </template>
                                    </dl>
                                </div>
                            </div>
                        </div>

                        <!-- Inventory Movements Card -->
                        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm">
                            <div class="p-6">
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Recent Inventory Movements</h3>

                                <div v-if="hasMovements" class="overflow-x-auto">
                                    <table class="w-full">
                                        <thead>
                                            <tr class="border-b border-gray-200 dark:border-gray-700">
                                                <th class="text-left py-2 text-xs font-semibold text-gray-600 dark:text-gray-400 uppercase">Type</th>
                                                <th class="text-right py-2 text-xs font-semibold text-gray-600 dark:text-gray-400 uppercase">Quantity</th>
                                                <th class="text-left py-2 text-xs font-semibold text-gray-600 dark:text-gray-400 uppercase">Location</th>
                                                <th class="text-left py-2 text-xs font-semibold text-gray-600 dark:text-gray-400 uppercase">Date</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                            <tr v-for="movement in recentMovements" :key="movement.id">
                                                <td class="py-2 text-sm text-gray-900 dark:text-white capitalize">
                                                    {{ formatMovementType(movement.type) }}
                                                </td>
                                                <td class="py-2 text-right text-sm font-medium" :class="movement.quantity > 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'">
                                                    {{ movement.quantity > 0 ? '+' : '' }}{{ movement.quantity }}
                                                </td>
                                                <td class="py-2 text-sm text-gray-600 dark:text-gray-400">
                                                    {{ movement.location?.name || 'N/A' }}
                                                </td>
                                                <td class="py-2 text-sm text-gray-600 dark:text-gray-400">
                                                    {{ formatDate(movement.created_at) }}
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>

                                <p v-else class="text-sm text-gray-500 dark:text-gray-400">No inventory movements recorded</p>
                            </div>
                        </div>
                    </div>

                    <!-- Sidebar (1/3 width) -->
                    <div class="space-y-6">
                        <!-- Stock Status Card -->
                        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm">
                            <div class="p-6">
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Stock Status</h3>

                                <div class="text-center mb-4">
                                    <div class="text-4xl font-bold text-gray-900 dark:text-white">
                                        {{ product.available_quantity ?? 0 }}
                                    </div>
                                    <div class="text-sm text-gray-500 dark:text-gray-400">units available</div>
                                </div>

                                <div class="flex justify-center mb-4">
                                    <span :class="stockStatusBadgeClass">
                                        {{ stockStatusLabel }}
                                    </span>
                                </div>

                                <div class="text-sm text-gray-600 dark:text-gray-400 text-center mb-4">
                                    Low stock threshold: {{ product.low_stock_threshold }} units
                                </div>

                                <button
                                    v-if="permissions.can_edit && !isArchived"
                                    @click="showAdjustStockModal = true"
                                    class="w-full px-3 py-2 text-sm font-medium text-indigo-700 dark:text-indigo-400 bg-indigo-50 dark:bg-indigo-900/20 rounded-lg hover:bg-indigo-100 dark:hover:bg-indigo-900/40 transition"
                                >
                                    Adjust Stock
                                </button>
                            </div>
                        </div>

                        <!-- Labeling Card -->
                        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm">
                            <div class="p-6">
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Labeling</h3>

                                <div class="space-y-3 text-sm">
                                    <div class="flex justify-between items-center">
                                        <span class="text-gray-500 dark:text-gray-400">Status</span>
                                        <span class="text-gray-900 dark:text-white">{{ product.labeling_status || 'Not tracked' }}</span>
                                    </div>
                                    <div class="flex justify-between items-center">
                                        <span class="text-gray-500 dark:text-gray-400">Labels printed</span>
                                        <span class="text-gray-900 dark:text-white">{{ product.labels_printed_count ?? 0 }}</span>
                                    </div>
                                    <div class="flex justify-between items-center">
                                        <span class="text-gray-500 dark:text-gray-400">Last printed</span>
                                        <span class="text-gray-900 dark:text-white" :title="product.last_label_printed_at">
                                            {{ product.last_label_printed_at_human || 'Never' }}
                                        </span>
                                    </div>
                                    <div v-if="product.last_label_printed_by" class="flex justify-between items-center">
                                        <span class="text-gray-500 dark:text-gray-400">Last printed by</span>
                                        <span class="text-gray-900 dark:text-white">{{ product.last_label_printed_by.name }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Pricing Card -->
                        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm">
                            <div class="p-6">
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Pricing</h3>

                                <div class="space-y-3">
                                    <div class="flex justify-between items-center">
                                        <span class="text-sm text-gray-500 dark:text-gray-400">Cost Price</span>
                                        <span class="text-sm font-medium text-gray-900 dark:text-white">{{ product.formatted_cost_price || 'N/A' }}</span>
                                    </div>
                                    <div class="flex justify-between items-center">
                                        <span class="text-sm text-gray-500 dark:text-gray-400">Selling Price</span>
                                        <span class="text-lg font-bold text-gray-900 dark:text-white">{{ product.formatted_selling_price || 'N/A' }}</span>
                                    </div>
                                    <div class="flex justify-between items-center">
                                        <span class="text-sm text-gray-500 dark:text-gray-400">MSRP</span>
                                        <span class="text-sm font-medium text-gray-900 dark:text-white">{{ product.formatted_msrp || 'N/A' }}</span>
                                    </div>
                                    <div v-if="product.profit_margin !== null" class="flex justify-between items-center pt-3 border-t border-gray-200 dark:border-gray-700">
                                        <span class="text-sm text-gray-500 dark:text-gray-400">Profit Margin</span>
                                        <span :class="['text-sm font-bold', product.profit_margin >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400']">
                                            {{ product.profit_margin }}%
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Classification Card -->
                        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm">
                            <div class="p-6">
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Classification</h3>

                                <div class="space-y-3">
                                    <div class="flex justify-between items-center">
                                        <span class="text-sm text-gray-500 dark:text-gray-400">Category</span>
                                        <span class="text-sm text-gray-900 dark:text-white">{{ product.category?.name || 'Uncategorized' }}</span>
                                    </div>
                                    <div class="flex justify-between items-center">
                                        <span class="text-sm text-gray-500 dark:text-gray-400">Brand</span>
                                        <span class="text-sm text-gray-900 dark:text-white">{{ product.brand?.name || 'No Brand' }}</span>
                                    </div>
                                    <div v-if="product.condition_label" class="flex justify-between items-center">
                                        <span class="text-sm text-gray-500 dark:text-gray-400">Condition</span>
                                        <span class="text-sm text-gray-900 dark:text-white">{{ product.condition_label }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Timestamps Card -->
                        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm">
                            <div class="p-6">
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Record Info</h3>

                                <div class="space-y-3">
                                    <div v-if="product.creator" class="flex justify-between items-center">
                                        <span class="text-sm text-gray-500 dark:text-gray-400">Created By</span>
                                        <span class="text-sm text-gray-900 dark:text-white">{{ product.creator.name }}</span>
                                    </div>
                                    <div class="flex justify-between items-center">
                                        <span class="text-sm text-gray-500 dark:text-gray-400">Created</span>
                                        <span class="text-sm text-gray-900 dark:text-white">{{ product.created_at_formatted || formatDate(product.created_at) }}</span>
                                    </div>
                                    <div class="flex justify-between items-center">
                                        <span class="text-sm text-gray-500 dark:text-gray-400">Updated</span>
                                        <span class="text-sm text-gray-900 dark:text-white">{{ product.updated_at_formatted || formatDate(product.updated_at) }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Archive Confirmation Modal (with Impact Summary) -->
        <ConfirmationModal :show="showArchiveModal" @close="showArchiveModal = false">
            <template #title>
                Archive Product
            </template>

            <template #content>
                <div class="space-y-4">
                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        Archiving "<span class="font-semibold">{{ product.name }}</span>" will hide it from your inventory and end all marketplace listings. You can restore it later.
                    </p>

                    <!-- Impact Summary -->
                    <div v-if="impactData" class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-4 space-y-3">
                        <h4 class="text-sm font-semibold text-gray-900 dark:text-white">Impact Summary</h4>

                        <!-- Active Listings Warning -->
                        <div v-if="impactData.has_active_listings" class="flex items-start space-x-2 text-amber-700 dark:text-amber-400 bg-amber-50 dark:bg-amber-900/20 rounded-md p-3">
                            <svg class="w-5 h-5 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                            </svg>
                            <div class="text-sm">
                                <p class="font-medium">{{ impactData.total_active_listings }} active marketplace listing(s) will be ended</p>
                                <ul class="mt-1 text-xs space-y-0.5">
                                    <li v-if="impactData.active_ebay_listings">eBay: {{ impactData.active_ebay_listings }} listing(s)</li>
                                    <li v-if="impactData.active_shopify_listings">Shopify: {{ impactData.active_shopify_listings }} listing(s)</li>
                                    <li v-if="impactData.active_amazon_listings">Amazon: {{ impactData.active_amazon_listings }} listing(s)</li>
                                </ul>
                            </div>
                        </div>

                        <!-- Pending Orders Warning -->
                        <div v-if="impactData.has_pending_orders" class="flex items-start space-x-2 text-red-700 dark:text-red-400 bg-red-50 dark:bg-red-900/20 rounded-md p-3">
                            <svg class="w-5 h-5 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                            </svg>
                            <p class="text-sm font-medium">{{ impactData.pending_order_items }} pending order item(s) reference this product</p>
                        </div>

                        <!-- Bundle Membership Warning -->
                        <div v-if="impactData.is_bundle_member" class="flex items-start space-x-2 text-blue-700 dark:text-blue-400 bg-blue-50 dark:bg-blue-900/20 rounded-md p-3">
                            <svg class="w-5 h-5 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                            </svg>
                            <p class="text-sm font-medium">Part of {{ impactData.bundle_memberships }} bundle(s)</p>
                        </div>

                        <!-- Data counts -->
                        <div class="grid grid-cols-3 gap-3 text-center">
                            <div class="bg-white dark:bg-gray-800 rounded-md p-2">
                                <p class="text-lg font-semibold text-gray-900 dark:text-white">{{ impactData.image_count }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">Images</p>
                            </div>
                            <div class="bg-white dark:bg-gray-800 rounded-md p-2">
                                <p class="text-lg font-semibold text-gray-900 dark:text-white">{{ impactData.identifier_count }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">Identifiers</p>
                            </div>
                            <div class="bg-white dark:bg-gray-800 rounded-md p-2">
                                <p class="text-lg font-semibold text-gray-900 dark:text-white">{{ impactData.inventory_movement_count }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">Movements</p>
                            </div>
                        </div>
                    </div>

                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        All data is preserved and can be restored. Marketplace listings will need to be re-created after restoration.
                    </p>
                </div>
            </template>

            <template #footer>
                <SecondaryButton @click="showArchiveModal = false">
                    Cancel
                </SecondaryButton>

                <DangerButton
                    class="ms-3"
                    :class="{ 'opacity-25': isProcessing }"
                    :disabled="isProcessing"
                    @click="archiveProduct"
                >
                    {{ isProcessing ? 'Archiving...' : 'Archive Product' }}
                </DangerButton>
            </template>
        </ConfirmationModal>

        <!-- Force Delete Confirmation Modal -->
        <ConfirmationModal :show="showForceDeleteModal" @close="showForceDeleteModal = false">
            <template #title>
                Permanently Delete Product
            </template>

            <template #content>
                <div class="space-y-3">
                    <p class="text-sm text-red-600 dark:text-red-400 font-semibold">
                        This action is irreversible.
                    </p>
                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        Permanently deleting "<span class="font-semibold">{{ product.name }}</span>" will remove all associated data including images, identifiers, inventory records, and marketplace listing history.
                    </p>
                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        Order history referencing this product will be preserved.
                    </p>
                </div>
            </template>

            <template #footer>
                <SecondaryButton @click="showForceDeleteModal = false">
                    Cancel
                </SecondaryButton>

                <DangerButton
                    class="ms-3"
                    :class="{ 'opacity-25': isProcessing }"
                    :disabled="isProcessing"
                    @click="forceDeleteProduct"
                >
                    {{ isProcessing ? 'Deleting...' : 'Permanently Delete' }}
                </DangerButton>
            </template>
        </ConfirmationModal>

        <!-- Stock Adjustment Modal -->
        <ConfirmationModal :show="showAdjustStockModal" @close="showAdjustStockModal = false">
            <template #title>
                Adjust Stock
            </template>

            <template #content>
                <div class="space-y-4">
                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        Current available: <span class="font-semibold">{{ product.available_quantity ?? 0 }}</span> units
                    </p>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Mode</label>
                        <div class="flex gap-2">
                            <button
                                type="button"
                                @click="adjustForm.mode = 'set'"
                                :class="[
                                    'flex-1 px-3 py-2 text-sm font-medium rounded-lg border transition',
                                    adjustForm.mode === 'set'
                                        ? 'bg-indigo-50 dark:bg-indigo-900/30 border-indigo-300 dark:border-indigo-600 text-indigo-700 dark:text-indigo-400'
                                        : 'bg-white dark:bg-gray-700 border-gray-300 dark:border-gray-600 text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-600'
                                ]"
                            >
                                Set to
                            </button>
                            <button
                                type="button"
                                @click="adjustForm.mode = 'adjust'"
                                :class="[
                                    'flex-1 px-3 py-2 text-sm font-medium rounded-lg border transition',
                                    adjustForm.mode === 'adjust'
                                        ? 'bg-indigo-50 dark:bg-indigo-900/30 border-indigo-300 dark:border-indigo-600 text-indigo-700 dark:text-indigo-400'
                                        : 'bg-white dark:bg-gray-700 border-gray-300 dark:border-gray-600 text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-600'
                                ]"
                            >
                                Add / Remove
                            </button>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            {{ adjustForm.mode === 'set' ? 'New quantity' : 'Quantity (+/-)' }}
                        </label>
                        <input
                            v-model.number="adjustForm.quantity"
                            type="number"
                            :min="adjustForm.mode === 'set' ? 0 : undefined"
                            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100"
                            :placeholder="adjustForm.mode === 'set' ? 'e.g. 10' : 'e.g. 5 or -3'"
                        />
                        <p v-if="adjustForm.quantity != null && adjustForm.quantity !== ''" class="mt-1 text-xs" :class="computedNewStock >= (product.available_quantity || 0) ? 'text-green-600' : 'text-red-600'">
                            New available will be: {{ computedNewStock }}
                        </p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Reason</label>
                        <select
                            v-model="adjustForm.reason"
                            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100"
                        >
                            <option value="">Select a reason...</option>
                            <option value="Manual count correction">Manual count correction</option>
                            <option value="Received new stock">Received new stock</option>
                            <option value="Damaged/defective">Damaged/defective</option>
                            <option value="Returned to supplier">Returned to supplier</option>
                            <option value="Lost/missing">Lost/missing</option>
                            <option value="Found during audit">Found during audit</option>
                            <option value="Testing">Testing</option>
                        </select>
                    </div>

                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        This will update inventory and push the new quantity to all connected marketplaces.
                    </p>
                </div>
            </template>

            <template #footer>
                <SecondaryButton @click="showAdjustStockModal = false">
                    Cancel
                </SecondaryButton>

                <button
                    class="ms-3 inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150 disabled:opacity-25"
                    :disabled="!adjustForm.quantity || !adjustForm.reason || adjustingStock"
                    @click="submitAdjustStock"
                >
                    {{ adjustingStock ? 'Adjusting...' : 'Adjust Stock' }}
                </button>
            </template>
        </ConfirmationModal>

        <!-- Image Reprocess Modal -->
        <ConfirmationModal :show="showReprocessModal" @close="showReprocessModal = false">
            <template #title>
                Reprocess {{ reprocessTargetIds.length > 1 ? `${reprocessTargetIds.length} Images` : 'Image' }}
            </template>

            <template #content>
                <div class="space-y-4">
                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        Re-run background removal on
                        {{ reprocessTargetIds.length > 1 ? `${reprocessTargetIds.length} selected photos` : 'this photo' }}.
                        "Keep more of product" restores erased areas and is the safest choice
                        when part of the product was cut off.
                    </p>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Strength</label>
                        <div class="grid grid-cols-1 gap-2">
                            <button
                                v-for="opt in [
                                    { value: 'conservative', label: 'Keep more of product', hint: 'Restores erased areas (recommended)' },
                                    { value: 'balanced', label: 'Balanced', hint: 'Clean default removal' },
                                    { value: 'aggressive', label: 'Trim tighter', hint: 'Removes halos/fringing more aggressively' },
                                ]"
                                :key="opt.value"
                                type="button"
                                @click="reprocessForm.strength = opt.value"
                                :class="[
                                    'text-left px-3 py-2 rounded-lg border transition',
                                    reprocessForm.strength === opt.value
                                        ? 'bg-indigo-50 dark:bg-indigo-900/30 border-indigo-300 dark:border-indigo-600'
                                        : 'bg-white dark:bg-gray-700 border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-600'
                                ]"
                            >
                                <span class="block text-sm font-medium text-gray-900 dark:text-gray-100">{{ opt.label }}</span>
                                <span class="block text-xs text-gray-500 dark:text-gray-400">{{ opt.hint }}</span>
                            </button>
                        </div>
                    </div>

                    <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                        <input v-model="reprocessForm.remove_bg" type="checkbox" class="rounded border-gray-300 dark:border-gray-600 text-indigo-600" />
                        Remove background (uncheck to only trim &amp; center on white)
                    </label>

                    <button type="button" class="text-xs text-indigo-600 dark:text-indigo-400 hover:underline" @click="showAdvancedImageOptions = !showAdvancedImageOptions">
                        {{ showAdvancedImageOptions ? 'Hide' : 'Show' }} advanced options
                    </button>

                    <div v-if="showAdvancedImageOptions" class="space-y-3 border-t border-gray-200 dark:border-gray-700 pt-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Model</label>
                            <select v-model="reprocessForm.model" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 text-sm">
                                <option value="">Auto (profile default)</option>
                                <option value="isnet-general-use">isnet-general-use</option>
                                <option value="u2net">u2net</option>
                                <option value="u2netp">u2netp</option>
                                <option value="silueta">silueta</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Padding: {{ reprocessForm.padding_pct }}%</label>
                            <input v-model.number="reprocessForm.padding_pct" type="range" min="0" max="50" class="w-full" />
                        </div>
                        <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                            <input v-model="reprocessForm.center" type="checkbox" class="rounded border-gray-300 dark:border-gray-600 text-indigo-600" />
                            Center &amp; crop product on a square canvas
                        </label>
                    </div>
                </div>
            </template>

            <template #footer>
                <SecondaryButton @click="showReprocessModal = false">Cancel</SecondaryButton>
                <button
                    class="ms-3 inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition disabled:opacity-25"
                    :disabled="imageBusy"
                    @click="submitReprocess"
                >
                    {{ imageBusy ? 'Processing...' : 'Reprocess' }}
                </button>
            </template>
        </ConfirmationModal>

        <!-- Image Revert Modal -->
        <ConfirmationModal :show="showRevertModal" @close="showRevertModal = false">
            <template #title>
                Revert to Original
            </template>
            <template #content>
                <p class="text-sm text-gray-600 dark:text-gray-400">
                    This restores the untouched original photo (before any background removal).
                    You can reprocess it again afterwards.
                </p>
            </template>
            <template #footer>
                <SecondaryButton @click="showRevertModal = false">Cancel</SecondaryButton>
                <button
                    class="ms-3 inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition disabled:opacity-25"
                    :disabled="imageBusy"
                    @click="submitRevert"
                >
                    {{ imageBusy ? 'Reverting...' : 'Revert' }}
                </button>
            </template>
        </ConfirmationModal>
    </AppLayout>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { router, Link, usePage } from '@inertiajs/vue3'
import axios from 'axios'
import AppLayout from '@/Layouts/AppLayout.vue'
import ConfirmationModal from '@/Components/ConfirmationModal.vue'
import SecondaryButton from '@/Components/SecondaryButton.vue'
import DangerButton from '@/Components/DangerButton.vue'

const props = defineProps({
    product: {
        type: Object,
        required: true
    },
    permissions: {
        type: Object,
        default: () => ({})
    },
    isArchived: {
        type: Boolean,
        default: false
    },
    marketplaceSyncStatus: {
        type: Object,
        default: () => ({})
    },
    credentials: {
        type: Array,
        default: () => []
    }
})

const showArchiveModal = ref(false)
const showForceDeleteModal = ref(false)
const isProcessing = ref(false)
const isLoadingImpact = ref(false)
const toast = ref({ show: false, message: '', type: 'success' })
const showAdjustStockModal = ref(false)
const adjustingStock = ref(false)
const adjustForm = ref({ quantity: null, reason: '', mode: 'set' })

const showToast = (message, type = 'success') => {
    toast.value = { show: true, message, type }
    setTimeout(() => { toast.value.show = false }, 4000)
}
const impactData = ref(null)
const selectedImage = ref(null)
const showCrossListMenu = ref(false)
const crossListDropdownRef = ref(null)
const syncing = ref({})

// ---- Per-image background-removal editing ----
const imageBusy = ref(false)
const showReprocessModal = ref(false)
const showRevertModal = ref(false)
const showAdvancedImageOptions = ref(false)
const replaceInput = ref(null)
const reprocessForm = ref({ strength: 'conservative', model: '', padding_pct: 5, center: true, remove_bg: true })
// Checked photos for batch reprocess (by image id).
const selectedImageIds = ref([])

// The image the single-image actions (revert/replace) act on: the selected
// thumbnail, else the first.
const currentImage = computed(() => {
    const imgs = props.product.images || []
    if (!imgs.length) return null
    return imgs.find((i) => i.url === selectedImage.value) || imgs[0]
})

// Which images Reprocess will act on: the checked set, or the current photo
// when nothing is checked.
const reprocessTargetIds = computed(() => {
    if (selectedImageIds.value.length) return selectedImageIds.value
    return currentImage.value ? [currentImage.value.id] : []
})

const allImageIds = computed(() => (props.product.images || []).map((i) => i.id))
const allSelected = computed(() => allImageIds.value.length > 0 && selectedImageIds.value.length === allImageIds.value.length)
const toggleSelectAll = () => {
    selectedImageIds.value = allSelected.value ? [] : [...allImageIds.value]
}

const canEditImages = computed(() => Boolean(props.permissions?.can_edit) && !props.isArchived)

const flashToast = (page, fallback) => {
    const flash = page?.props?.flash
    if (flash?.error) {
        showToast(flash.error, 'error')
    } else {
        showToast(flash?.success || fallback)
    }
}

const openReprocessModal = () => {
    reprocessForm.value = { strength: 'conservative', model: '', padding_pct: 5, center: true, remove_bg: true }
    showAdvancedImageOptions.value = false
    showReprocessModal.value = true
}

const submitReprocess = () => {
    const ids = reprocessTargetIds.value
    if (!ids.length) return
    const payload = {
        image_ids: ids,
        strength: reprocessForm.value.strength,
        padding_pct: reprocessForm.value.padding_pct,
        center: reprocessForm.value.center,
        remove_bg: reprocessForm.value.remove_bg,
    }
    if (reprocessForm.value.model) payload.model = reprocessForm.value.model

    router.post(route('inventory.products.images.reprocess-bulk', props.product.id), payload, {
        preserveScroll: true,
        onStart: () => { imageBusy.value = true },
        onSuccess: (page) => {
            showReprocessModal.value = false
            selectedImageIds.value = []
            selectedImage.value = null
            flashToast(page, 'Images reprocessed')
        },
        onError: () => showToast('Reprocess failed', 'error'),
        onFinish: () => { imageBusy.value = false },
    })
}

const submitRevert = () => {
    if (!currentImage.value) return
    router.post(route('inventory.products.images.revert', { product: props.product.id, image: currentImage.value.id }), {}, {
        preserveScroll: true,
        onStart: () => { imageBusy.value = true },
        onSuccess: (page) => { showRevertModal.value = false; selectedImage.value = null; flashToast(page, 'Reverted to original') },
        onError: () => showToast('Revert failed', 'error'),
        onFinish: () => { imageBusy.value = false },
    })
}

const onReplaceFile = (event) => {
    const file = event.target.files?.[0]
    if (!file || !currentImage.value) return
    router.post(route('inventory.products.images.replace', { product: props.product.id, image: currentImage.value.id }), { image: file }, {
        preserveScroll: true,
        forceFormData: true,
        onStart: () => { imageBusy.value = true },
        onSuccess: (page) => { selectedImage.value = null; flashToast(page, 'Image replaced') },
        onError: () => showToast('Replace failed', 'error'),
        onFinish: () => { imageBusy.value = false; event.target.value = '' },
    })
}

// Close dropdown when clicking outside
const handleClickOutside = (event) => {
    if (crossListDropdownRef.value && !crossListDropdownRef.value.contains(event.target)) {
        showCrossListMenu.value = false
    }
}

onMounted(() => {
    document.addEventListener('click', handleClickOutside)
})

onUnmounted(() => {
    document.removeEventListener('click', handleClickOutside)
})

// Marketplace computed
const hasEbayListings = computed(() => {
    return props.product.ebay_listings && props.product.ebay_listings.length > 0
})

const hasShopifyListings = computed(() => {
    return props.product.shopify_listings && props.product.shopify_listings.length > 0
})

const hasAnyListing = computed(() => hasEbayListings.value || hasShopifyListings.value)

const syncImagesToMarketplaces = () => {
    router.post(route('inventory.products.images.sync-marketplaces', props.product.id), {}, {
        preserveScroll: true,
        onStart: () => { imageBusy.value = true },
        onSuccess: (page) => flashToast(page, 'Image sync to marketplaces queued'),
        onError: () => showToast('Sync failed', 'error'),
        onFinish: () => { imageBusy.value = false },
    })
}

const hasActiveEbayListing = computed(() => {
    if (!props.product.ebay_listings) return false
    return props.product.ebay_listings.some(l => ['published', 'out_of_stock', 'pending', 'inventory_created', 'offer_created'].includes(l.status))
})

const hasActiveShopifyListing = computed(() => {
    if (!props.product.shopify_listings) return false
    return props.product.shopify_listings.some(l => ['active', 'created', 'pending', 'draft'].includes(l.status))
})

const marketplaceStatusClass = (color) => {
    const colors = {
        green: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-400',
        yellow: 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400',
        red: 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400',
        blue: 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400',
        orange: 'bg-orange-100 text-orange-800 dark:bg-orange-900/30 dark:text-orange-400',
        gray: 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400',
    }
    return colors[color] || colors.gray
}

// Marketplace actions
const crossListTo = (target) => {
    showCrossListMenu.value = false
    const source = target === 'shopify' ? 'ebay' : 'shopify'
    router.visit(route('marketplaces.cross-list.preview', {
        product_id: props.product.id,
        source: source,
        target: target,
    }))
}

const syncListing = (platform, listingId) => {
    syncing.value[listingId] = true
    const onDone = (page) => {
        delete syncing.value[listingId]
        const flash = page?.props?.flash || page?.props?.jetstream?.flash || {}
        if (flash.success || flash.banner) {
            showToast(flash.success || flash.banner || `${platform} sync queued`)
        } else {
            showToast(`${platform} sync queued`)
        }
    }
    const onError = (errors) => {
        delete syncing.value[listingId]
        const msg = Object.values(errors).flat().join(', ')
        showToast(msg || `${platform} sync failed`, 'error')
    }
    if (platform === 'ebay') {
        router.post(route('marketplaces.ebay.listings.update', listingId), { update_type: 'both' }, {
            preserveScroll: true,
            onSuccess: onDone,
            onError: onError,
            onFinish: () => { delete syncing.value[listingId] }
        })
    } else if (platform === 'shopify') {
        router.post(route('marketplaces.shopify.products.sync', props.product.id), {}, {
            preserveScroll: true,
            onSuccess: onDone,
            onError: onError,
            onFinish: () => { delete syncing.value[listingId] }
        })
    }
}

const retryListing = (platform, listingId) => {
    syncing.value[listingId] = true
    const onDone = () => {
        delete syncing.value[listingId]
        showToast(`${platform} retry queued`)
    }
    const onError = (errors) => {
        delete syncing.value[listingId]
        const msg = Object.values(errors).flat().join(', ')
        showToast(msg || `${platform} retry failed`, 'error')
    }
    if (platform === 'ebay') {
        router.post(route('marketplaces.ebay.listings.retry', listingId), {}, {
            preserveScroll: true,
            onSuccess: onDone,
            onError: onError,
            onFinish: () => { delete syncing.value[listingId] }
        })
    } else if (platform === 'shopify') {
        router.post(route('marketplaces.shopify.products.sync', props.product.id), {}, {
            preserveScroll: true,
            onSuccess: onDone,
            onError: onError,
            onFinish: () => { delete syncing.value[listingId] }
        })
    }
}

// Stock adjustment
const computedNewStock = computed(() => {
    const current = props.product.available_quantity || 0
    const qty = adjustForm.value.quantity
    if (qty == null || qty === '') return current
    return adjustForm.value.mode === 'set' ? qty : current + qty
})

const submitAdjustStock = () => {
    adjustingStock.value = true
    const current = props.product.available_quantity || 0
    const rawQty = adjustForm.value.quantity
    // Convert "set to" into a delta for the backend
    const delta = adjustForm.value.mode === 'set' ? (rawQty - current) : rawQty

    if (delta === 0) {
        showToast('No change needed', 'error')
        adjustingStock.value = false
        return
    }

    const newStock = computedNewStock.value
    router.post(route('inventory.products.adjust-stock', props.product.id), {
        quantity: delta,
        reason: adjustForm.value.reason,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            showAdjustStockModal.value = false
            showToast(`Stock updated to ${newStock}. Marketplace sync queued.`)
            adjustForm.value = { quantity: null, reason: '', mode: 'set' }
        },
        onError: (errors) => {
            const msg = Object.values(errors).flat().join(', ')
            showToast(msg || 'Failed to adjust stock', 'error')
        },
        onFinish: () => {
            adjustingStock.value = false
        }
    })
}

// Existing computed properties
const statusBadgeClass = computed(() => {
    const baseClasses = 'px-3 py-1 text-sm font-medium rounded-full'
    switch (props.product.status) {
        case 'active':
            return `${baseClasses} bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400`
        case 'draft':
            return `${baseClasses} bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300`
        case 'inactive':
            return `${baseClasses} bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400`
        case 'archived':
            return `${baseClasses} bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400`
        default:
            return `${baseClasses} bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300`
    }
})

const stockStatusBadgeClass = computed(() => {
    const baseClasses = 'px-3 py-1 text-sm font-medium rounded-full'
    switch (props.product.stock_status_color) {
        case 'green':
            return `${baseClasses} bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400`
        case 'yellow':
            return `${baseClasses} bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400`
        case 'red':
            return `${baseClasses} bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400`
        case 'orange':
            return `${baseClasses} bg-orange-100 text-orange-800 dark:bg-orange-900/30 dark:text-orange-400`
        case 'blue':
            return `${baseClasses} bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400`
        default:
            return `${baseClasses} bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300`
    }
})

const stockStatusLabel = computed(() => {
    const status = props.product.stock_status
    const labels = {
        'in_stock': 'In Stock',
        'low_stock': 'Low Stock',
        'out_of_stock': 'Out of Stock',
        'backorder': 'On Backorder',
        'overstocked': 'Overstocked'
    }
    return labels[status] || 'Unknown'
})

const hasImages = computed(() => {
    return props.product.images && props.product.images.length > 0
})

const hasPhysicalProperties = computed(() => {
    return props.product.weight || props.product.dimensions || props.product.shipping_class
})

const hasMovements = computed(() => {
    return props.product.inventory_movements && props.product.inventory_movements.length > 0
})

const recentMovements = computed(() => {
    if (!props.product.inventory_movements) return []
    return props.product.inventory_movements.slice(0, 10)
})

const hasInventoryLevels = computed(() => {
    return Array.isArray(props.product.inventory_levels) && props.product.inventory_levels.length > 0
})

const hasSuppliers = computed(() => {
    return Array.isArray(props.product.suppliers) && props.product.suppliers.length > 0
})

const hasParsedWarehouse = computed(() => {
    const parsed = props.product.parsed_warehouse_location
    return parsed && Object.keys(parsed).length > 0
})

const formatWarehouseKey = (key) => {
    return String(key).replace(/_/g, ' ')
}

// Component breakdown helpers
const componentCategories = computed(() => {
    if (!props.product.component_breakdown) return []
    return [...new Set(props.product.component_breakdown.map(c => c.category || 'Other'))]
})

const totalComponentQuantity = computed(() => {
    if (!props.product.component_breakdown) return 0
    return props.product.component_breakdown.reduce((sum, c) => sum + (c.quantity || 0), 0)
})

const categoryColorPalette = [
    { accent: 'bg-blue-500', badge: 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300' },
    { accent: 'bg-violet-500', badge: 'bg-violet-100 text-violet-800 dark:bg-violet-900/40 dark:text-violet-300' },
    { accent: 'bg-amber-500', badge: 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300' },
    { accent: 'bg-emerald-500', badge: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300' },
    { accent: 'bg-cyan-500', badge: 'bg-cyan-100 text-cyan-800 dark:bg-cyan-900/40 dark:text-cyan-300' },
    { accent: 'bg-rose-500', badge: 'bg-rose-100 text-rose-800 dark:bg-rose-900/40 dark:text-rose-300' },
    { accent: 'bg-indigo-500', badge: 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900/40 dark:text-indigo-300' },
    { accent: 'bg-slate-500', badge: 'bg-slate-100 text-slate-800 dark:bg-slate-900/40 dark:text-slate-300' },
]

const getCategoryColorIndex = (category) => {
    const str = (category || 'Other').toLowerCase()
    let hash = 0
    for (let i = 0; i < str.length; i++) hash = str.charCodeAt(i) + ((hash << 5) - hash)
    return Math.abs(hash) % categoryColorPalette.length
}

const getCategoryAccentColor = (category) => categoryColorPalette[getCategoryColorIndex(category)].accent

const getCategoryBadgeClasses = (category) => categoryColorPalette[getCategoryColorIndex(category)].badge

// Methods
const formatMovementType = (type) => {
    const types = {
        'purchase': 'Purchase',
        'sale': 'Sale',
        'adjustment': 'Adjustment',
        'transfer_in': 'Transfer In',
        'transfer_out': 'Transfer Out',
        'return': 'Return',
        'damaged': 'Damaged',
        'lost': 'Lost',
        'found': 'Found'
    }
    return types[type] || type.replace(/_/g, ' ')
}

const formatDate = (date) => {
    if (!date) return 'N/A'
    return new Date(date).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    })
}

const confirmArchive = async () => {
    isLoadingImpact.value = true
    try {
        const response = await axios.get(route('inventory.products.deletion-impact', props.product.id))
        impactData.value = response.data
        showArchiveModal.value = true
    } catch (error) {
        // Fallback: show modal without impact data
        impactData.value = null
        showArchiveModal.value = true
    } finally {
        isLoadingImpact.value = false
    }
}

const archiveProduct = () => {
    isProcessing.value = true
    router.delete(route('inventory.products.destroy', props.product.id), {
        onSuccess: () => {
            showArchiveModal.value = false
        },
        onFinish: () => {
            isProcessing.value = false
        }
    })
}

const confirmForceDelete = () => {
    showForceDeleteModal.value = true
}

const forceDeleteProduct = () => {
    isProcessing.value = true
    router.delete(route('inventory.products.force-destroy', props.product.id), {
        onSuccess: () => {
            showForceDeleteModal.value = false
        },
        onFinish: () => {
            isProcessing.value = false
        }
    })
}

const restoreProduct = () => {
    isProcessing.value = true
    router.post(route('inventory.products.restore', props.product.id), {}, {
        onFinish: () => {
            isProcessing.value = false
        }
    })
}
</script>
