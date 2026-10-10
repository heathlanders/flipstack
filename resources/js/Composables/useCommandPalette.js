import { ref, computed, watch, onMounted, onUnmounted } from 'vue'
import { router, usePage } from '@inertiajs/vue3'

const RECENT_ITEMS_KEY = 'command_palette_recent'
const MAX_RECENT_ITEMS = 10

/**
 * Composable for managing Command Palette state and actions
 */
export function useCommandPalette() {
    const page = usePage()

    // State
    const isOpen = ref(false)
    const query = ref('')
    const selectedIndex = ref(0)
    const isLoading = ref(false)
    const searchResults = ref([])
    const recentItems = ref([])

    // Navigation items
    const navigationCommands = computed(() => [
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
    ])

    // Quick action items
    const actionCommands = computed(() => [
        { id: 'action-create-product', type: 'action', icon: 'plus', label: 'Create new product', description: 'Add a new product to inventory', shortcut: 'Cmd+N', action: () => router.visit(route('inventory.products.create')) },
        { id: 'action-quick-intake', type: 'action', icon: 'barcode', label: 'Open quick intake', description: 'Scan products into inventory', shortcut: 'Cmd+I', action: 'openQuickIntake' },
        { id: 'action-create-listing', type: 'action', icon: 'shopping-cart', label: 'Create eBay listing', description: 'List a product on eBay', action: () => {} },
        { id: 'action-export-products', type: 'action', icon: 'download', label: 'Export products', description: 'Export product data to CSV', action: () => router.visit(route('inventory.products.export')) },
        { id: 'action-search-products', type: 'action', icon: 'search', label: 'Search products', description: 'Search across all products', action: 'focusProductSearch' },
    ])

    // All commands combined
    const allCommands = computed(() => [
        ...actionCommands.value,
        ...navigationCommands.value
    ])

    // Filtered results based on query
    const filteredCommands = computed(() => {
        if (!query.value.trim()) {
            return allCommands.value.slice(0, 8)
        }

        const q = query.value.toLowerCase()
        return allCommands.value.filter(cmd => {
            return cmd.label.toLowerCase().includes(q) ||
                cmd.description?.toLowerCase().includes(q)
        })
    })

    // All results (commands + search results)
    const allResults = computed(() => {
        const results = []

        // Add filtered commands
        if (filteredCommands.value.length > 0) {
            results.push({
                type: 'group',
                label: query.value ? 'Commands' : 'Quick Actions',
                items: filteredCommands.value.slice(0, 5)
            })
        }

        // Add search results if we have them
        if (searchResults.value.length > 0) {
            // Group by type
            const products = searchResults.value.filter(r => r.type === 'product')
            const suppliers = searchResults.value.filter(r => r.type === 'supplier')
            const orders = searchResults.value.filter(r => r.type === 'order')

            if (products.length > 0) {
                results.push({
                    type: 'group',
                    label: `Products (${products.length})`,
                    items: products.slice(0, 5)
                })
            }

            if (suppliers.length > 0) {
                results.push({
                    type: 'group',
                    label: `Suppliers (${suppliers.length})`,
                    items: suppliers.slice(0, 3)
                })
            }

            if (orders.length > 0) {
                results.push({
                    type: 'group',
                    label: `Orders (${orders.length})`,
                    items: orders.slice(0, 3)
                })
            }
        }

        // Add recent items if no query
        if (!query.value && recentItems.value.length > 0) {
            results.push({
                type: 'group',
                label: 'Recent',
                items: recentItems.value.slice(0, 5)
            })
        }

        // Add navigation if there's a query and matches
        if (query.value) {
            const navMatches = navigationCommands.value.filter(cmd =>
                cmd.label.toLowerCase().includes(query.value.toLowerCase())
            )
            if (navMatches.length > 0) {
                results.push({
                    type: 'group',
                    label: 'Navigation',
                    items: navMatches.slice(0, 5)
                })
            }
        }

        return results
    })

    // Flat list of all selectable items for keyboard navigation
    const flatResults = computed(() => {
        return allResults.value.reduce((acc, group) => {
            return [...acc, ...group.items]
        }, [])
    })

    // Load recent items from localStorage
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

    // Save item to recent
    const addToRecent = (item) => {
        if (!item || !item.id) return

        // Remove if already exists
        const filtered = recentItems.value.filter(r => r.id !== item.id)

        // Add to beginning
        recentItems.value = [
            { ...item, timestamp: Date.now() },
            ...filtered
        ].slice(0, MAX_RECENT_ITEMS)

        try {
            localStorage.setItem(RECENT_ITEMS_KEY, JSON.stringify(recentItems.value))
        } catch (e) {
            console.warn('Failed to save recent items:', e)
        }
    }

    // Clear recent items
    const clearRecent = () => {
        recentItems.value = []
        try {
            localStorage.removeItem(RECENT_ITEMS_KEY)
        } catch (e) {
            console.warn('Failed to clear recent items:', e)
        }
    }

    // Search for entities
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

    // Execute a command or navigate
    const executeCommand = (item) => {
        if (!item) return

        addToRecent(item)
        close()

        if (item.action) {
            if (typeof item.action === 'function') {
                item.action()
            } else if (item.action === 'openQuickIntake') {
                // Emit event for QuickIntakePanel
                window.dispatchEvent(new CustomEvent('open-quick-intake'))
            } else if (item.action === 'focusProductSearch') {
                // Focus on product search
                const searchInput = document.querySelector('[data-product-search]')
                if (searchInput) {
                    searchInput.focus()
                }
            }
        } else if (item.route) {
            router.visit(route(item.route))
        } else if (item.url) {
            router.visit(item.url)
        }
    }

    // Open the command palette
    const open = () => {
        isOpen.value = true
        query.value = ''
        selectedIndex.value = 0
        searchResults.value = []
        loadRecentItems()
    }

    // Close the command palette
    const close = () => {
        isOpen.value = false
        query.value = ''
        selectedIndex.value = 0
        searchResults.value = []
    }

    // Toggle the command palette
    const toggle = () => {
        if (isOpen.value) {
            close()
        } else {
            open()
        }
    }

    // Keyboard navigation
    const selectNext = () => {
        if (selectedIndex.value < flatResults.value.length - 1) {
            selectedIndex.value++
        }
    }

    const selectPrevious = () => {
        if (selectedIndex.value > 0) {
            selectedIndex.value--
        }
    }

    const executeSelected = () => {
        const item = flatResults.value[selectedIndex.value]
        if (item) {
            executeCommand(item)
        }
    }

    // Watch query for search
    let searchTimeout = null
    watch(query, (newQuery) => {
        selectedIndex.value = 0

        // Debounce search
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

    // Load recent items on mount
    onMounted(() => {
        loadRecentItems()
    })

    return {
        // State
        isOpen,
        query,
        selectedIndex,
        isLoading,

        // Computed
        allResults,
        flatResults,
        filteredCommands,

        // Methods
        open,
        close,
        toggle,
        selectNext,
        selectPrevious,
        executeSelected,
        executeCommand,
        performSearch,
        clearRecent
    }
}
