<script setup>
import { ref, computed, watch, onMounted, onUnmounted, nextTick } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import { useCommandPalette } from '@/Composables/useCommandPalette'
import { useKeyboardShortcuts } from '@/Composables/useKeyboardShortcuts'

const props = defineProps({
    modelValue: {
        type: Boolean,
        default: false
    }
})

const emit = defineEmits(['update:modelValue', 'open-quick-intake'])

const page = usePage()
const { getShortcutDisplay, isMac } = useKeyboardShortcuts()

// Command palette state
const isOpen = ref(props.modelValue)
const query = ref('')
const selectedIndex = ref(0)
const isLoading = ref(false)
const searchResults = ref([])
const recentItems = ref([])
const inputRef = ref(null)

// Constants
const RECENT_ITEMS_KEY = 'command_palette_recent'
const MAX_RECENT_ITEMS = 10

// Navigation commands
const navigationCommands = [
    { id: 'nav-dashboard', type: 'navigation', icon: 'tachometer-alt', label: 'Dashboard', description: 'Go to main dashboard', route: 'dashboard' },
    { id: 'nav-products', type: 'navigation', icon: 'cube', label: 'Products', description: 'View all products', route: 'inventory.products.index' },
    { id: 'nav-categories', type: 'navigation', icon: 'tags', label: 'Categories', description: 'Manage product categories', route: 'inventory.categories.index' },
    { id: 'nav-suppliers', type: 'navigation', icon: 'truck', label: 'Suppliers', description: 'View suppliers', route: 'admin.suppliers.index' },
    { id: 'nav-locations', type: 'navigation', icon: 'map-marker-alt', label: 'Locations', description: 'Inventory locations', route: 'inventory.locations.index' },
    { id: 'nav-intake', type: 'navigation', icon: 'inbox', label: 'Intake Queue', description: 'Product intake queue', route: 'intake-queue.index' },
    { id: 'nav-users', type: 'navigation', icon: 'users', label: 'Users', description: 'Manage users', route: 'users.index' },
    { id: 'nav-teams', type: 'navigation', icon: 'user-friends', label: 'Teams', description: 'Manage teams', route: 'teams.index' },
    { id: 'nav-settings', type: 'navigation', icon: 'cog', label: 'Settings', description: 'Organization settings', route: 'organization.settings' },
    { id: 'nav-profile', type: 'navigation', icon: 'user', label: 'Profile', description: 'Your profile settings', route: 'profile.show' },
]

// Action commands
const actionCommands = computed(() => [
    { id: 'action-create-product', type: 'action', icon: 'plus', label: 'Create new product', description: 'Add a new product to inventory', shortcut: getShortcutDisplay('n', { meta: true }), route: 'inventory.products.create' },
    { id: 'action-quick-intake', type: 'action', icon: 'barcode', label: 'Open quick intake', description: 'Scan products into inventory', shortcut: getShortcutDisplay('i', { meta: true }), action: 'openQuickIntake' },
    { id: 'action-create-listing', type: 'action', icon: 'shopping-cart', label: 'Create eBay listing', description: 'List a product on eBay' },
    { id: 'action-export-products', type: 'action', icon: 'download', label: 'Export products', description: 'Export product data to CSV', route: 'inventory.products.export' },
])

// All commands
const allCommands = computed(() => [...actionCommands.value, ...navigationCommands])

// Filtered results based on query
const filteredCommands = computed(() => {
    if (!query.value.trim()) {
        return actionCommands.value.slice(0, 5)
    }

    const q = query.value.toLowerCase()
    return allCommands.value.filter(cmd =>
        cmd.label.toLowerCase().includes(q) ||
        cmd.description?.toLowerCase().includes(q)
    ).slice(0, 8)
})

// Navigation results
const filteredNavigation = computed(() => {
    if (!query.value.trim()) return []

    const q = query.value.toLowerCase()
    return navigationCommands.filter(cmd =>
        cmd.label.toLowerCase().includes(q)
    ).slice(0, 5)
})

// All results grouped
const groupedResults = computed(() => {
    const groups = []

    // Add filtered commands
    if (filteredCommands.value.length > 0) {
        groups.push({
            label: query.value ? 'Commands' : 'Quick Actions',
            items: filteredCommands.value
        })
    }

    // Add search results
    if (searchResults.value.length > 0) {
        const products = searchResults.value.filter(r => r.type === 'product')
        const suppliers = searchResults.value.filter(r => r.type === 'supplier')

        if (products.length > 0) {
            groups.push({
                label: `Products (${products.length})`,
                items: products.slice(0, 5).map(p => ({
                    ...p,
                    id: `product-${p.id}`,
                    icon: 'cube',
                    label: p.name,
                    description: p.sku,
                    url: `/inventory/products/${p.id}`
                }))
            })
        }

        if (suppliers.length > 0) {
            groups.push({
                label: `Suppliers (${suppliers.length})`,
                items: suppliers.slice(0, 3).map(s => ({
                    ...s,
                    id: `supplier-${s.id}`,
                    icon: 'truck',
                    label: s.name,
                    description: s.email,
                    url: `/admin/suppliers/${s.id}`
                }))
            })
        }
    }

    // Add navigation if query matches
    if (filteredNavigation.value.length > 0) {
        groups.push({
            label: 'Navigation',
            items: filteredNavigation.value
        })
    }

    // Add recent items if no query
    if (!query.value && recentItems.value.length > 0) {
        groups.push({
            label: 'Recent',
            items: recentItems.value.slice(0, 5)
        })
    }

    return groups
})

// Flat list for keyboard navigation
const flatResults = computed(() => {
    return groupedResults.value.reduce((acc, group) => [...acc, ...group.items], [])
})

// Load recent items
const loadRecentItems = () => {
    try {
        const stored = localStorage.getItem(RECENT_ITEMS_KEY)
        if (stored) {
            recentItems.value = JSON.parse(stored)
        }
    } catch (e) {
        console.warn('Failed to load recent items:', e)
    }
}

// Save to recent
const addToRecent = (item) => {
    if (!item?.id) return

    const filtered = recentItems.value.filter(r => r.id !== item.id)
    recentItems.value = [{ ...item, timestamp: Date.now() }, ...filtered].slice(0, MAX_RECENT_ITEMS)

    try {
        localStorage.setItem(RECENT_ITEMS_KEY, JSON.stringify(recentItems.value))
    } catch (e) {
        console.warn('Failed to save recent items:', e)
    }
}

// Search for entities
let searchTimeout = null
const performSearch = async (searchQuery) => {
    if (!searchQuery || searchQuery.length < 2) {
        searchResults.value = []
        return
    }

    isLoading.value = true

    try {
        const response = await fetch(`/api/omni-search?q=${encodeURIComponent(searchQuery)}`, {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
            },
            credentials: 'same-origin'
        })

        if (response.ok) {
            const data = await response.json()
            searchResults.value = data.results || []
        }
    } catch (e) {
        console.error('Search failed:', e)
        searchResults.value = []
    } finally {
        isLoading.value = false
    }
}

// Execute command
const executeCommand = (item) => {
    if (!item) return

    addToRecent(item)
    close()

    if (item.action === 'openQuickIntake') {
        emit('open-quick-intake')
    } else if (item.route) {
        router.visit(route(item.route))
    } else if (item.url) {
        router.visit(item.url)
    }
}

// Open palette
const open = () => {
    isOpen.value = true
    emit('update:modelValue', true)
    query.value = ''
    selectedIndex.value = 0
    searchResults.value = []
    loadRecentItems()

    nextTick(() => {
        inputRef.value?.focus()
    })
}

// Close palette
const close = () => {
    isOpen.value = false
    emit('update:modelValue', false)
    query.value = ''
    selectedIndex.value = 0
}

// Keyboard navigation
const handleKeyDown = (event) => {
    switch (event.key) {
        case 'ArrowDown':
            event.preventDefault()
            if (selectedIndex.value < flatResults.value.length - 1) {
                selectedIndex.value++
            }
            break
        case 'ArrowUp':
            event.preventDefault()
            if (selectedIndex.value > 0) {
                selectedIndex.value--
            }
            break
        case 'Enter':
            event.preventDefault()
            const item = flatResults.value[selectedIndex.value]
            if (item) {
                executeCommand(item)
            }
            break
        case 'Escape':
            event.preventDefault()
            close()
            break
    }
}

// Watch query for search
watch(query, (newQuery) => {
    selectedIndex.value = 0

    if (searchTimeout) {
        clearTimeout(searchTimeout)
    }

    if (newQuery.length >= 2) {
        searchTimeout = setTimeout(() => {
            performSearch(newQuery)
        }, 300)
    } else {
        searchResults.value = []
    }
})

// Watch external modelValue
watch(() => props.modelValue, (newValue) => {
    if (newValue !== isOpen.value) {
        if (newValue) {
            open()
        } else {
            close()
        }
    }
})

// Global keyboard shortcut for Cmd+K
const handleGlobalKeyDown = (event) => {
    const isMeta = event.metaKey || event.ctrlKey
    if (isMeta && event.key === 'k') {
        event.preventDefault()
        if (isOpen.value) {
            close()
        } else {
            open()
        }
    }
}

onMounted(() => {
    document.addEventListener('keydown', handleGlobalKeyDown)
    loadRecentItems()
})

onUnmounted(() => {
    document.removeEventListener('keydown', handleGlobalKeyDown)
})

// Expose open/close for parent components
defineExpose({ open, close })
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="duration-200 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="duration-150 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="isOpen"
                class="fixed inset-0 z-50 overflow-y-auto"
                @click="close"
            >
                <!-- Backdrop -->
                <div class="fixed inset-0 bg-black/50 backdrop-blur-sm"></div>

                <!-- Modal Container -->
                <div class="flex min-h-full items-start justify-center p-4 pt-[15vh]">
                    <Transition
                        enter-active-class="duration-200 ease-out"
                        enter-from-class="opacity-0 scale-95"
                        enter-to-class="opacity-100 scale-100"
                        leave-active-class="duration-150 ease-in"
                        leave-from-class="opacity-100 scale-100"
                        leave-to-class="opacity-0 scale-95"
                    >
                        <div
                            v-if="isOpen"
                            class="relative w-full max-w-xl bg-white dark:bg-slate-800 rounded-xl shadow-2xl overflow-hidden"
                            @click.stop
                        >
                            <!-- Search Input -->
                            <div class="flex items-center px-4 border-b border-gray-200 dark:border-slate-700">
                                <svg class="w-5 h-5 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                </svg>
                                <input
                                    ref="inputRef"
                                    v-model="query"
                                    type="text"
                                    placeholder="Search or type a command..."
                                    @keydown="handleKeyDown"
                                    class="flex-1 px-4 py-4 bg-transparent text-gray-900 dark:text-white placeholder-gray-400 focus:outline-none text-base"
                                />
                                <div v-if="isLoading" class="flex-shrink-0">
                                    <svg class="w-5 h-5 text-indigo-500 animate-spin" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                </div>
                                <kbd
                                    v-else
                                    class="hidden sm:inline-flex items-center px-2 py-1 text-xs font-medium text-gray-400 bg-gray-100 dark:bg-slate-700 rounded"
                                >
                                    ESC
                                </kbd>
                            </div>

                            <!-- Results -->
                            <div class="max-h-96 overflow-y-auto">
                                <div v-if="groupedResults.length > 0" class="py-2">
                                    <template v-for="(group, groupIndex) in groupedResults" :key="group.label">
                                        <!-- Group Header -->
                                        <div class="px-4 py-2 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                            {{ group.label }}
                                        </div>

                                        <!-- Group Items -->
                                        <div>
                                            <button
                                                v-for="(item, itemIndex) in group.items"
                                                :key="item.id"
                                                @click="executeCommand(item)"
                                                @mouseenter="selectedIndex = groupedResults.slice(0, groupIndex).reduce((acc, g) => acc + g.items.length, 0) + itemIndex"
                                                :class="[
                                                    'w-full flex items-center gap-3 px-4 py-2.5 text-left transition-colors',
                                                    flatResults.indexOf(item) === selectedIndex
                                                        ? 'bg-indigo-50 dark:bg-indigo-900/30'
                                                        : 'hover:bg-gray-50 dark:hover:bg-slate-700/50'
                                                ]"
                                            >
                                                <!-- Icon -->
                                                <div :class="[
                                                    'flex-shrink-0 w-9 h-9 flex items-center justify-center rounded-lg',
                                                    flatResults.indexOf(item) === selectedIndex
                                                        ? 'bg-indigo-100 dark:bg-indigo-800/50 text-indigo-600 dark:text-indigo-400'
                                                        : 'bg-gray-100 dark:bg-slate-700 text-gray-500 dark:text-gray-400'
                                                ]">
                                                    <i :class="['fas', `fa-${item.icon}`]"></i>
                                                </div>

                                                <!-- Content -->
                                                <div class="flex-1 min-w-0">
                                                    <div class="text-sm font-medium text-gray-900 dark:text-white truncate">
                                                        {{ item.label }}
                                                    </div>
                                                    <div v-if="item.description" class="text-xs text-gray-500 dark:text-gray-400 truncate">
                                                        {{ item.description }}
                                                    </div>
                                                </div>

                                                <!-- Shortcut Badge -->
                                                <kbd
                                                    v-if="item.shortcut"
                                                    class="hidden sm:inline-flex items-center px-2 py-1 text-xs font-medium text-gray-400 bg-gray-100 dark:bg-slate-700 rounded"
                                                >
                                                    {{ item.shortcut }}
                                                </kbd>

                                                <!-- Arrow indicator for selected -->
                                                <svg
                                                    v-if="flatResults.indexOf(item) === selectedIndex"
                                                    class="w-4 h-4 text-gray-400 flex-shrink-0"
                                                    fill="none"
                                                    stroke="currentColor"
                                                    viewBox="0 0 24 24"
                                                >
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6" />
                                                </svg>
                                            </button>
                                        </div>
                                    </template>
                                </div>

                                <!-- Empty State -->
                                <div v-else-if="query.length >= 2 && !isLoading" class="px-4 py-8 text-center">
                                    <svg class="w-12 h-12 mx-auto mb-3 text-gray-300 dark:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    <p class="text-sm text-gray-500 dark:text-gray-400">
                                        No results for "<span class="font-medium">{{ query }}</span>"
                                    </p>
                                </div>
                            </div>

                            <!-- Footer -->
                            <div class="px-4 py-3 border-t border-gray-200 dark:border-slate-700 bg-gray-50 dark:bg-slate-750">
                                <div class="flex items-center justify-between text-xs text-gray-400">
                                    <div class="flex items-center gap-4">
                                        <span class="flex items-center gap-1">
                                            <kbd class="px-1.5 py-0.5 bg-gray-200 dark:bg-slate-600 rounded text-gray-600 dark:text-gray-300">
                                                <svg class="w-3 h-3 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7" />
                                                </svg>
                                            </kbd>
                                            <kbd class="px-1.5 py-0.5 bg-gray-200 dark:bg-slate-600 rounded text-gray-600 dark:text-gray-300">
                                                <svg class="w-3 h-3 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                                </svg>
                                            </kbd>
                                            Navigate
                                        </span>
                                        <span class="flex items-center gap-1">
                                            <kbd class="px-1.5 py-0.5 bg-gray-200 dark:bg-slate-600 rounded text-gray-600 dark:text-gray-300">Enter</kbd>
                                            Select
                                        </span>
                                        <span class="flex items-center gap-1">
                                            <kbd class="px-1.5 py-0.5 bg-gray-200 dark:bg-slate-600 rounded text-gray-600 dark:text-gray-300">ESC</kbd>
                                            Close
                                        </span>
                                    </div>
                                    <div class="flex items-center gap-1">
                                        <kbd class="px-1.5 py-0.5 bg-gray-200 dark:bg-slate-600 rounded text-gray-600 dark:text-gray-300">
                                            {{ isMac ? 'Cmd' : 'Ctrl' }}+K
                                        </kbd>
                                        Toggle
                                    </div>
                                </div>
                            </div>
                        </div>
                    </Transition>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
/* Custom scrollbar */
.overflow-y-auto::-webkit-scrollbar {
    width: 6px;
}

.overflow-y-auto::-webkit-scrollbar-track {
    background: transparent;
}

.overflow-y-auto::-webkit-scrollbar-thumb {
    background-color: rgba(156, 163, 175, 0.5);
    border-radius: 3px;
}

.dark .overflow-y-auto::-webkit-scrollbar-thumb {
    background-color: rgba(75, 85, 99, 0.5);
}
</style>
