<script setup>
import { ref, computed, onMounted } from 'vue'
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import ApplicationMark from '@/Components/ApplicationMark.vue'
import Banner from '@/Components/Banner.vue'
import Dropdown from '@/Components/Dropdown.vue'
import DropdownLink from '@/Components/DropdownLink.vue'
import NotificationBell from '@/Components/NotificationBell.vue'
import NotificationToast from '@/Components/NotificationToast.vue'
import CommandPalette from '@/Components/CommandPalette.vue'
import QuickIntakePanel from '@/Components/QuickIntakePanel.vue'
import OmniSearch from '@/Components/OmniSearch.vue'
import { useTheme } from '@/Composables/useTheme'

const { theme, setTheme } = useTheme()

const props = defineProps({
    title: String,
    pageTitle: String,
    pageDescription: String,
    pageActions: {
        type: Array,
        default: () => []
    }
})

const page = usePage()

const showingNavigationDropdown = ref(false)
const sidebarCollapsed = ref(false)
const showingPlatformStatus = ref(true)
const showCommandPalette = ref(false)
const showQuickIntake = ref(false)

// Expanded sections state
const expandedSections = ref({
    catalog: false,
    warehouse: false,
    purchasing: false,
    fulfillment: false,
    analytics: false,
    marketplaces: false,
    settings: false,
    supplier: true
})

// Queue counts from shared Inertia props, with live refresh
const queueCounts = ref({
    intake: page.props.queueCounts?.intake ?? 0,
    fulfillment: page.props.queueCounts?.fulfillment ?? 0,
    labeling: page.props.queueCounts?.labeling ?? 0,
})

const fetchQueueCounts = async () => {
    try {
        const response = await fetch(route('web.workflow-counts'), {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        })
        if (response.ok) {
            const data = await response.json()
            queueCounts.value.intake = data.intake_queue ?? queueCounts.value.intake
            queueCounts.value.fulfillment = data.fulfillment ?? queueCounts.value.fulfillment
            queueCounts.value.labeling = data.labeling ?? queueCounts.value.labeling
        }
    } catch {
        // Silently fail - keep existing counts
    }
}

onMounted(() => {
    fetchQueueCounts()
    // Refresh queue counts every 60 seconds
    setInterval(fetchQueueCounts, 60000)

    // Auto-expand the section containing the active route
    filteredNavigationSections.value.forEach(section => {
        if (section.expandable && section.items?.some(item => route().current(item.route + '*'))) {
            expandedSections.value[section.key] = true
        }
    })
})

// Marketplace connections from shared Inertia data
const marketplaceConnections = computed(() => page.props.marketplaces || [])

// Computed marketplace summary
const marketplaceSummary = computed(() => {
    const total = marketplaceConnections.value.length
    const connected = marketplaceConnections.value.filter(m => m.status === 'connected' && !m.hasErrors).length
    const errors = marketplaceConnections.value.filter(m => m.hasErrors || m.status === 'disconnected').length
    return { total, connected, errors }
})

// Visible queues based on user role
const visibleQueues = computed(() => {
    const isAdmin = userRoles.value.includes('super_admin') ||
        userRoles.value.includes('organization_owner') ||
        userRoles.value.includes('admin')

    const queues = []

    // Intake queue - visible to admin, buyer roles
    if (isAdmin || userRoles.value.includes('buyer') || hasPermission('intake_queue.view')) {
        queues.push({ key: 'intake', label: 'Intake', count: queueCounts.value.intake, route: 'intake-queue.index' })
    }

    // Fulfillment queue - visible to admin, fulfillment roles
    if (isAdmin || userRoles.value.includes('fulfillment') || hasPermission('fulfillment.view')) {
        queues.push({ key: 'fulfillment', label: 'Fulfillment', count: queueCounts.value.fulfillment, route: 'dashboard' })
    }

    // Labeling queue - visible to admin, inventory roles
    if (isAdmin || hasPermission('inventory.view')) {
        queues.push({ key: 'labeling', label: 'Labeling', count: queueCounts.value.labeling, route: 'inventory.labeling.index' })
    }

    return queues
})

// Get user permissions and roles from Inertia shared data
const userPermissions = computed(() => page.props.auth?.permissions || [])
const userRoles = computed(() => page.props.auth?.roles || [])

// Check if user has a specific permission
const hasPermission = (permission) => {
    if (!permission) return true

    // Super admin, admin and owner roles have all permissions
    if (userRoles.value.includes('super_admin') ||
        userRoles.value.includes('organization_owner') ||
        userRoles.value.includes('admin')) {
        return true
    }

    // Check for wildcard permissions (e.g., 'inventory.*' matches 'inventory.view')
    const permissionParts = permission.split('.')
    const wildcardPermission = permissionParts[0] + '.*'

    return userPermissions.value.includes(permission) ||
           userPermissions.value.includes(wildcardPermission) ||
           userPermissions.value.includes('*')
}

// Check if user has any of the specified permissions
const hasAnyPermission = (permissions) => {
    if (!permissions || permissions.length === 0) return true
    return permissions.some(p => hasPermission(p))
}

// Check if user has a specific role
const hasRole = (role) => {
    if (!role) return true
    if (Array.isArray(role)) {
        return role.some(r => userRoles.value.includes(r))
    }
    return userRoles.value.includes(role)
}

// GROUPED NAVIGATION with permissions
const navigationSections = ref([
    {
        name: 'Core',
        items: [
            { name: 'Dashboard', route: 'dashboard', icon: 'fas fa-tachometer-alt' },
        ]
    },
    {
        name: 'Catalog',
        key: 'catalog',
        icon: 'fas fa-cube',
        expandable: true,
        permission: 'products.view',
        items: [
            { name: 'Products', route: 'inventory.products.index', icon: 'fas fa-cube', permission: 'products.view' },
            { name: 'Categories', route: 'inventory.categories.index', icon: 'fas fa-tags', permission: 'inventory.view' },
            { name: 'Bundles', route: 'inventory.bundles.index', icon: 'fas fa-boxes', permission: 'inventory.view' },
            { name: 'Part-Out', route: 'inventory.part-out.index', icon: 'fas fa-screwdriver-wrench', permission: 'inventory.view' },
            { name: 'Templates', route: 'inventory.templates.index', icon: 'fas fa-file-alt', permission: 'inventory.view' },
            { name: 'Intake Queue', route: 'intake-queue.index', icon: 'fas fa-inbox', permission: 'intake.view' },
            { name: 'Import / Export', route: 'inventory.import.index', icon: 'fas fa-file-import', permission: 'inventory.view' },
            { name: 'Bulk Operations', route: 'inventory.bulk.index', icon: 'fas fa-tasks', permission: 'inventory.edit' },
        ]
    },
    {
        name: 'Warehouse',
        key: 'warehouse',
        icon: 'fas fa-warehouse',
        expandable: true,
        permission: 'inventory.view',
        items: [
            { name: 'Overview', route: 'inventory.dashboard', icon: 'fas fa-chart-line', permission: 'inventory.view' },
            { name: 'Locations', route: 'inventory.locations.index', icon: 'fas fa-map-marker-alt', permission: 'inventory.view' },
            { name: 'Warehouse Layout', route: 'inventory.warehouse.zones.index', icon: 'fas fa-th', permission: 'inventory.view' },
            { name: 'Movements', route: 'inventory.movements.index', icon: 'fas fa-exchange-alt', permission: 'inventory.view' },
            { name: 'Batches', route: 'inventory.batches.index', icon: 'fas fa-layer-group', permission: 'inventory.view' },
            { name: 'Printing', route: 'printing.dashboard', icon: 'fas fa-print', permission: 'inventory.view' },
            { name: 'Labeling', route: 'inventory.labeling.index', icon: 'fas fa-tags', permission: 'inventory.view' },
        ]
    },
    {
        name: 'Purchasing',
        key: 'purchasing',
        icon: 'fas fa-file-invoice',
        expandable: true,
        permission: 'inventory.view',
        items: [
            { name: 'Purchase Orders', route: 'procurement.purchase-orders.index', icon: 'fas fa-file-invoice-dollar', permission: 'inventory.view' },
            { name: 'Suppliers', route: 'admin.suppliers.index', icon: 'fas fa-truck', roles: ['super_admin', 'organization_owner', 'admin', 'manager'] },
            { name: 'Customers', route: 'customers.index', icon: 'fas fa-users', permission: 'inventory.view' },
            { name: 'Pricing', route: 'inventory.pricing.index', icon: 'fas fa-dollar-sign', permission: 'inventory.edit' },
            { name: 'Repricing Rules', route: 'pricing.repricing.index', icon: 'fas fa-sync-alt', permission: 'inventory.edit' },
        ]
    },
    {
        name: 'Fulfillment',
        key: 'fulfillment',
        icon: 'fas fa-shipping-fast',
        expandable: true,
        permission: 'fulfillment.view',
        items: [
            { name: 'Orders', route: 'fulfillment.orders.index', icon: 'fas fa-clipboard-list', permission: 'fulfillment.view' },
            { name: 'Pick', route: 'fulfillment.pick.index', icon: 'fas fa-hand-pointer', permission: 'fulfillment.pick' },
            { name: 'Pack', route: 'fulfillment.pack.index', icon: 'fas fa-box', permission: 'fulfillment.pack' },
            { name: 'Ship', route: 'fulfillment.ship.index', icon: 'fas fa-truck', permission: 'fulfillment.ship' },
            { name: 'Returns', route: 'fulfillment.returns.index', icon: 'fas fa-undo', permission: 'fulfillment.manage_returns' },
            { name: 'Messages', route: 'messages.inbox', icon: 'fas fa-envelope', permission: 'fulfillment.view' },
            { name: 'Routing Rules', route: 'fulfillment.routing.index', icon: 'fas fa-route', permission: 'fulfillment.manage' },
            { name: 'Shipping Rules', route: 'fulfillment.shipping-rules.index', icon: 'fas fa-ruler', permission: 'fulfillment.manage' },
            { name: 'Settings', route: 'fulfillment.settings.index', icon: 'fas fa-cog', permission: 'fulfillment.manage' },
        ]
    },
    {
        name: 'Marketplaces',
        key: 'marketplaces',
        icon: 'fas fa-store',
        expandable: true,
        permission: 'integrations.view',
        roles: ['super_admin', 'organization_owner', 'admin'],
        items: [
            { name: 'Hub', route: 'marketplaces.hub.index', icon: 'fas fa-th-large', permission: 'integrations.view' },
            { name: 'eBay', route: 'marketplaces.ebay.index', icon: 'fas fa-shopping-cart', permission: 'integrations.view' },
            { name: 'Shopify', route: 'marketplaces.shopify.index', icon: 'fas fa-shopping-bag', permission: 'integrations.view' },
            { name: 'Amazon', route: 'marketplaces.amazon.index', icon: 'fab fa-amazon', permission: 'integrations.view' },
            { name: 'Scheduled Listings', route: 'marketplaces.scheduled-listings.index', icon: 'fas fa-clock', permission: 'integrations.view' },
            { name: 'Cross-List', route: 'marketplaces.cross-list.preview', icon: 'fas fa-copy', permission: 'integrations.view' },
            { name: 'Promoted Listings', route: 'marketplaces.ebay.promoted.dashboard', icon: 'fas fa-bullhorn', permission: 'integrations.view' },
            { name: 'Email Campaigns', route: 'marketing.campaigns.index', icon: 'fas fa-paper-plane', permission: 'integrations.view', roles: ['super_admin', 'organization_owner', 'admin'] },
        ]
    },
    {
        name: 'Analytics',
        key: 'analytics',
        icon: 'fas fa-chart-bar',
        expandable: true,
        permission: 'analytics.view',
        items: [
            { name: 'Analytics Hub', route: 'analytics.hub.executive', icon: 'fas fa-tachometer-alt', permission: 'analytics.view' },
            { name: 'Profit & Loss', route: 'analytics.profit-loss.dashboard', icon: 'fas fa-dollar-sign', permission: 'analytics.view' },
            { name: 'Inventory Analytics', route: 'inventory.analytics.dashboard', icon: 'fas fa-chart-pie', permission: 'analytics.view' },
            { name: 'Forecasting', route: 'analytics.forecasting.dashboard', icon: 'fas fa-chart-line', permission: 'analytics.view' },
            { name: 'Reorder Suggestions', route: 'analytics.forecasting.suggestions', icon: 'fas fa-clipboard-check', permission: 'analytics.view' },
        ]
    },
    {
        name: 'Settings',
        key: 'settings',
        icon: 'fas fa-cog',
        expandable: true,
        permissions: ['organization.view', 'users.view'],
        items: [
            { name: 'Organization', route: 'organization.settings', icon: 'fas fa-building', permission: 'organization.view' },
            { name: 'Currency', route: 'settings.currency.index', icon: 'fas fa-coins', permission: 'organization.view' },
            { name: 'Users', route: 'users.index', icon: 'fas fa-user', permission: 'users.view', roles: ['super_admin', 'organization_owner', 'admin'] },
            { name: 'Teams', route: 'teams.index', icon: 'fas fa-user-friends', permission: 'teams.view', roles: ['super_admin', 'organization_owner', 'admin'] },
            { name: 'Roles', route: 'roles.index', icon: 'fas fa-user-tag', permission: 'roles.view', roles: ['super_admin', 'organization_owner', 'admin'] },
            { name: 'Invitations', route: 'organization.invitations.index', icon: 'fas fa-envelope', permission: 'users.create', roles: ['super_admin', 'organization_owner', 'admin'] },
            { name: 'AI Prompts', route: 'admin.ai-prompts.index', icon: 'fas fa-robot', permission: 'ai_prompts.view', roles: ['super_admin', 'organization_owner', 'admin'] },
            { name: 'Supplier Submissions', route: 'admin.supplier-submissions.index', icon: 'fas fa-inbox', roles: ['super_admin', 'organization_owner', 'admin'] },
            { name: 'Audit Hub', route: 'admin.audit-hub.index', icon: 'fas fa-history', roles: ['super_admin', 'organization_owner', 'admin', 'manager'] },
            { name: 'Security Log', route: 'admin.audit-hub.security', icon: 'fas fa-shield-alt', roles: ['super_admin', 'organization_owner', 'admin', 'manager'] },
            { name: 'Onboarding', route: 'onboarding.index', icon: 'fas fa-rocket', permission: 'organization.view' },
        ]
    },
    {
        name: 'Supplier Portal',
        key: 'supplier',
        icon: 'fas fa-handshake',
        expandable: true,
        supplierOnly: true,
        items: [
            { name: 'Dashboard', route: 'supplier.dashboard', icon: 'fas fa-tachometer-alt' },
            { name: 'Performance', route: 'supplier.performance.index', icon: 'fas fa-chart-line' },
            { name: 'Settlements', route: 'supplier.settlements.index', icon: 'fas fa-file-invoice-dollar' },
            { name: 'Submit Product', route: 'supplier.products.submit', icon: 'fas fa-upload' },
            { name: 'My Submissions', route: 'supplier.products.submissions', icon: 'fas fa-list-alt' },
            { name: 'Documents', route: 'supplier.documents.index', icon: 'fas fa-folder-open' },
            { name: 'Notifications', route: 'supplier.notifications.index', icon: 'fas fa-bell' },
            { name: 'Settings', route: 'supplier.settings', icon: 'fas fa-cog' },
        ]
    }
])

// Check if user is a supplier
const isSupplier = computed(() => page.props.auth?.is_supplier ?? false)

// Filter navigation based on permissions
const filteredNavigationSections = computed(() => {
    return navigationSections.value
        .filter(section => {
            // Supplier-only sections: show only for suppliers
            if (section.supplierOnly) {
                return isSupplier.value
            }
            // For supplier users, only show Core and Supplier Portal sections
            if (isSupplier.value && section.name !== 'Core') {
                return false
            }
            // Check section-level permissions (plural, OR logic)
            if (section.permissions && !hasAnyPermission(section.permissions)) {
                if (section.roles && hasRole(section.roles)) {
                    return true
                }
                return false
            }
            // Check section-level permission (singular)
            if (section.permission && !hasPermission(section.permission)) {
                // Also check if user has required role
                if (section.roles && hasRole(section.roles)) {
                    return true
                }
                return false
            }
            // Check section-level role requirement
            if (section.roles && !hasRole(section.roles)) {
                return false
            }
            return true
        })
        .map(section => ({
            ...section,
            items: (section.items || []).filter(item => {
                // Check item-level permission
                if (item.permission && !hasPermission(item.permission)) {
                    // Also check if user has required role
                    if (item.roles && hasRole(item.roles)) {
                        return true
                    }
                    return false
                }
                // Check item-level role requirement
                if (item.roles && !hasRole(item.roles)) {
                    return false
                }
                return true
            })
        }))
        .filter(section => section.items.length > 0) // Remove empty sections
})

const toggleSection = (sectionKey) => {
    expandedSections.value[sectionKey] = !expandedSections.value[sectionKey]
}

const switchTeam = (team) => {
    router.put(route('current-team.update'), {
        team_id: team.id,
    }, {
        preserveState: false,
    })
}

const logout = () => {
    router.post(route('logout'))
}

// Check if current route matches section
const isSectionActive = (section) => {
    return section.items?.some(item => route().current(item.route + '*')) ?? false
}

// Get action button classes based on variant
const getActionClasses = (action) => {
    const baseClasses = 'inline-flex items-center gap-2 px-3 py-2 rounded-lg text-sm font-medium transition-colors'

    const variantClasses = {
        primary: 'bg-blue-600 hover:bg-blue-700 text-white',
        secondary: 'bg-gray-100 hover:bg-gray-200 dark:bg-gray-700 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-300 border border-gray-300 dark:border-gray-600',
        success: 'bg-emerald-600 hover:bg-emerald-700 text-white',
        danger: 'bg-red-600 hover:bg-red-700 text-white',
        warning: 'bg-yellow-600 hover:bg-yellow-700 text-white'
    }

    return `${baseClasses} ${variantClasses[action.variant] || variantClasses.secondary}`
}
</script>

<template>
    <div>
        <Head :title="title" />

        <Banner />

        <div class="min-h-screen bg-gray-50 dark:bg-gray-950">
            <!-- Status Bar -->
            <div v-if="showingPlatformStatus && (visibleQueues.length > 0 || marketplaceConnections.length > 0)" class="bg-gray-950 dark:bg-gray-950 text-white px-6 py-1.5 text-xs">
                <div class="max-w-7xl mx-auto flex items-center justify-between">
                    <!-- Left: Queue Counts -->
                    <div class="flex items-center gap-4">
                        <Link
                            v-for="queue in visibleQueues"
                            :key="queue.key"
                            :href="route(queue.route)"
                            class="flex items-center gap-1.5 hover:text-white transition-colors"
                            :class="queue.count > 0 ? 'text-white' : 'text-gray-400'"
                        >
                            <span class="font-medium">{{ queue.count }}</span>
                            <span class="text-gray-300">{{ queue.label }}</span>
                        </Link>
                    </div>

                    <!-- Right: Marketplace Status + Dismiss -->
                    <div class="flex items-center gap-4">
                        <!-- Consolidated marketplace indicator with hover dropdown -->
                        <div v-if="marketplaceConnections.length > 0" class="relative group">
                            <div class="flex items-center gap-1.5 cursor-default">
                                <div
                                    class="w-2 h-2 rounded-full"
                                    :class="marketplaceSummary.errors > 0 ? 'bg-red-400' : 'bg-emerald-400'"
                                ></div>
                                <span v-if="marketplaceSummary.errors === 0" class="text-gray-300">
                                    {{ marketplaceSummary.connected }} marketplace{{ marketplaceSummary.connected !== 1 ? 's' : '' }} connected
                                </span>
                                <span v-else class="text-red-300">
                                    {{ marketplaceSummary.errors }} marketplace{{ marketplaceSummary.errors !== 1 ? 's' : '' }} disconnected
                                </span>
                            </div>

                            <!-- Hover dropdown -->
                            <div class="absolute right-0 top-full mt-2 w-52 bg-gray-700 rounded-lg shadow-lg border border-gray-600 py-1.5 opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all z-50">
                                <div class="px-3 py-1.5 text-[10px] uppercase tracking-wider text-gray-400 font-semibold">
                                    Marketplaces
                                </div>
                                <template v-for="mp in marketplaceConnections" :key="mp.name">
                                    <Link
                                        v-if="(mp.hasErrors || mp.status === 'disconnected') && hasPermission('integrations.manage')"
                                        :href="route('organization.settings')"
                                        class="flex items-center justify-between px-3 py-1.5 hover:bg-gray-600 transition-colors"
                                    >
                                        <span class="text-white text-xs">{{ mp.name }}</span>
                                        <div class="flex items-center gap-1.5">
                                            <span class="text-red-300 text-[10px]">Disconnected</span>
                                            <div class="w-1.5 h-1.5 rounded-full bg-red-400"></div>
                                        </div>
                                    </Link>
                                    <div
                                        v-else
                                        class="flex items-center justify-between px-3 py-1.5"
                                    >
                                        <div>
                                            <span class="text-white text-xs">{{ mp.name }}</span>
                                            <span v-if="mp.account_username || mp.seller_username" class="block text-gray-400 text-[10px] truncate max-w-[120px]">{{ mp.account_username || mp.seller_username }}</span>
                                        </div>
                                        <div class="flex items-center gap-1.5">
                                            <span class="text-emerald-300 text-[10px]">Connected</span>
                                            <div class="w-1.5 h-1.5 rounded-full bg-emerald-400"></div>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>

                        <button @click="showingPlatformStatus = false"
                                class="text-gray-500 hover:text-white transition-colors">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Main Navigation Header -->
            <nav class="bg-white dark:bg-gray-950 border-b border-gray-200/60 dark:border-gray-800">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div class="flex justify-between h-16">
                        <div class="flex items-center">
                            <!-- Logo -->
                            <div class="shrink-0 flex items-center">
                                <Link :href="route('dashboard')">
                                    <ApplicationMark class="block h-9 w-auto" />
                                </Link>
                            </div>

                            <!-- Sidebar Toggle -->
                            <button @click="sidebarCollapsed = !sidebarCollapsed"
                                    class="ml-4 p-2 rounded-lg text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
                                <i :class="sidebarCollapsed ? 'fas fa-bars' : 'fas fa-times'" class="text-sm"></i>
                            </button>

                            <!-- Page Title + Description -->
                            <div v-if="pageTitle" class="ml-6 hidden sm:block">
                                <h1 class="text-lg font-semibold text-gray-900 dark:text-white leading-tight">
                                    {{ pageTitle }}
                                </h1>
                                <p v-if="pageDescription" class="text-sm text-gray-500 dark:text-gray-400 leading-tight">
                                    {{ pageDescription }}
                                </p>
                            </div>
                        </div>

                        <!-- Right Side -->
                        <div class="flex items-center space-x-4">
                            <!-- Quick Actions -->
                            <div class="flex items-center space-x-2">
                                <Link v-if="hasPermission('products.create')"
                                      :href="route('intake-queue.create')"
                                      class="p-2 rounded-lg text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
                                    <i class="fas fa-plus text-sm"></i>
                                </Link>
                                <button
                                    @click="showCommandPalette = true"
                                    class="p-2 rounded-lg text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors"
                                    title="Search (Cmd+K)"
                                >
                                    <i class="fas fa-search text-sm"></i>
                                </button>

                                <!-- NOTIFICATION BELL - UPDATED -->
                                <NotificationBell
                                    :userId="$page.props.auth.user.id"
                                    :initialUnreadCount="0"
                                />
                            </div>

                            <!-- Teams Dropdown -->
                            <div v-if="$page.props.jetstream.hasTeamFeatures" class="relative">
                                <Dropdown align="right" width="60">
                                    <template #trigger>
                                        <span class="inline-flex rounded-md">
                                            <button type="button" class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-gray-500 dark:text-gray-400 bg-white dark:bg-gray-800 hover:text-gray-700 dark:hover:text-gray-300 focus:outline-none focus:bg-gray-50 dark:focus:bg-gray-700 active:bg-gray-50 dark:active:bg-gray-700 transition ease-in-out duration-150">
                                                {{ $page.props.auth.user.current_team?.name || 'No Team Selected' }}
                                                <svg class="ml-2 -mr-0.5 h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 15L12 18.75 15.75 15m-7.5-6L12 5.25 15.75 9" />
                                                </svg>
                                            </button>
                                        </span>
                                    </template>

                                    <template #content>
                                        <div class="w-60">
                                            <!-- Team Management -->
                                            <template v-if="$page.props.jetstream.hasTeamFeatures">
                                                <div class="block px-4 py-2 text-xs text-gray-400">
                                                    Manage Team
                                                </div>

                                                <!-- Team Settings -->
                                                <template v-if="$page.props.auth.user.current_team">
                                                    <DropdownLink :href="route('teams.show', $page.props.auth.user.current_team)">
                                                        Team Settings
                                                    </DropdownLink>
                                                </template>
                                                <template v-else>
                                                    <div class="px-4 py-2 text-sm text-gray-500">
                                                        No team selected
                                                    </div>
                                                </template>

                                                <DropdownLink v-if="$page.props.jetstream.canCreateTeams" :href="route('teams.create')">
                                                    Create New Team
                                                </DropdownLink>

                                                <!-- Team Switcher -->
                                                <template v-if="$page.props.auth.user.all_teams && $page.props.auth.user.all_teams.length > 1">
                                                    <div class="border-t border-gray-200 dark:border-gray-600"></div>

                                                    <div class="block px-4 py-2 text-xs text-gray-400">
                                                        Switch Teams
                                                    </div>

                                                    <template v-for="team in $page.props.auth.user.all_teams" :key="team.id">
                                                        <form @submit.prevent="switchTeam(team)">
                                                            <DropdownLink as="button">
                                                                <div class="flex items-center">
                                                                    <svg v-if="$page.props.auth.user.current_team && team.id == $page.props.auth.user.current_team.id" class="mr-2 h-5 w-5 text-green-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                                    </svg>

                                                                    <div>{{ team.name }}</div>
                                                                </div>
                                                            </DropdownLink>
                                                        </form>
                                                    </template>
                                                </template>
                                            </template>
                                        </div>
                                    </template>
                                </Dropdown>
                            </div>

                            <!-- User Dropdown -->
                            <div class="relative">
                                <Dropdown align="right" width="64">
                                    <template #trigger>
                                        <button v-if="$page.props.jetstream.managesProfilePhotos"
                                                class="flex text-sm border-2 border-transparent rounded-full focus:outline-none focus:border-gray-300 transition">
                                            <img class="h-8 w-8 rounded-full object-cover"
                                                 :src="$page.props.auth.user.profile_photo_url"
                                                 :alt="$page.props.auth.user.name">
                                        </button>
                                        <span v-else class="inline-flex rounded-md">
                                            <button type="button"
                                                    class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-gray-500 dark:text-gray-400 bg-white dark:bg-gray-800 hover:text-gray-700 dark:hover:text-gray-300 focus:outline-none focus:bg-gray-50 dark:focus:bg-gray-700 active:bg-gray-50 dark:active:bg-gray-700 transition ease-in-out duration-150">
                                                {{ $page.props.auth.user.name }}
                                                <svg class="ml-2 -mr-0.5 h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                                                </svg>
                                            </button>
                                        </span>
                                    </template>

                                    <template #content>
                                        <!-- Account Management -->
                                        <div class="block px-4 py-2 text-xs text-gray-400">
                                            Manage Account
                                        </div>

                                        <DropdownLink :href="route('profile.show')">
                                            Profile
                                        </DropdownLink>

                                        <DropdownLink v-if="$page.props.jetstream.hasApiFeatures" :href="route('api-tokens.index')">
                                            API Tokens
                                        </DropdownLink>

                                        <div class="border-t border-gray-200 dark:border-gray-600"></div>

                                        <!-- Theme Selector -->
                                        <div class="px-4 py-2" @click.stop>
                                            <div class="text-xs text-gray-400 mb-2">Theme</div>
                                            <div class="flex items-center gap-1 bg-gray-100 dark:bg-gray-800 rounded-lg p-1">
                                                <button
                                                    @click="setTheme('light')"
                                                    :class="[
                                                        'flex items-center gap-1.5 flex-1 justify-center px-2 py-1.5 text-xs font-medium rounded-md transition-all duration-150',
                                                        theme === 'light'
                                                            ? 'bg-white dark:bg-gray-700 shadow-sm text-gray-900 dark:text-white'
                                                            : 'text-gray-500 hover:text-gray-700 dark:hover:text-gray-300'
                                                    ]"
                                                >
                                                    <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v2.25m6.364.386l-1.591 1.591M21 12h-2.25m-.386 6.364l-1.591-1.591M12 18.75V21m-4.773-4.227l-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0z" />
                                                    </svg>
                                                    Light
                                                </button>
                                                <button
                                                    @click="setTheme('dark')"
                                                    :class="[
                                                        'flex items-center gap-1.5 flex-1 justify-center px-2 py-1.5 text-xs font-medium rounded-md transition-all duration-150',
                                                        theme === 'dark'
                                                            ? 'bg-white dark:bg-gray-700 shadow-sm text-gray-900 dark:text-white'
                                                            : 'text-gray-500 hover:text-gray-700 dark:hover:text-gray-300'
                                                    ]"
                                                >
                                                    <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M21.752 15.002A9.718 9.718 0 0118 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 003 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 009.002-5.998z" />
                                                    </svg>
                                                    Dark
                                                </button>
                                                <button
                                                    @click="setTheme('system')"
                                                    :class="[
                                                        'flex items-center gap-1.5 flex-1 justify-center px-2 py-1.5 text-xs font-medium rounded-md transition-all duration-150',
                                                        theme === 'system'
                                                            ? 'bg-white dark:bg-gray-700 shadow-sm text-gray-900 dark:text-white'
                                                            : 'text-gray-500 hover:text-gray-700 dark:hover:text-gray-300'
                                                    ]"
                                                >
                                                    <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 17.25v1.007a3 3 0 01-.879 2.122L7.5 21h9l-.621-.621A3 3 0 0115 18.257V17.25m6-12V15a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 15V5.25A2.25 2.25 0 015.25 3h13.5A2.25 2.25 0 0121 5.25z" />
                                                    </svg>
                                                    System
                                                </button>
                                            </div>
                                        </div>

                                        <div class="border-t border-gray-200 dark:border-gray-600"></div>

                                        <!-- Authentication -->
                                        <form @submit.prevent="logout">
                                            <DropdownLink as="button">
                                                Log Out
                                            </DropdownLink>
                                        </form>
                                    </template>
                                </Dropdown>
                            </div>
                        </div>
                    </div>
                </div>
            </nav>

            <!-- Sidebar -->
            <div class="flex">
                <aside :class="[
                    'bg-gray-50 dark:bg-gray-950 border-r border-gray-200/60 dark:border-gray-800 transition-all duration-300 ease-in-out',
                    sidebarCollapsed ? 'w-16' : 'w-64'
                ]">
                    <div class="h-full overflow-y-auto py-4">
                        <!-- IMPROVED GROUPED NAVIGATION with Permission Filtering -->
                        <nav class="space-y-2 px-3">
                            <template v-for="section in filteredNavigationSections" :key="section.name">
                                <!-- Section Header (for expandable sections) -->
                                <template v-if="section.expandable">
                                    <button
                                        @click="toggleSection(section.key)"
                                        :class="[
                                            isSectionActive(section)
                                                ? 'bg-gray-100 dark:bg-gray-700 text-gray-900 dark:text-white'
                                                : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white hover:bg-gray-50 dark:hover:bg-gray-700',
                                            'group flex items-center justify-between w-full px-3 py-2 text-sm font-medium rounded-lg transition-colors'
                                        ]">
                                        <div class="flex items-center">
                                            <i :class="[section.icon, sidebarCollapsed ? 'mr-0' : 'mr-3']" class="text-sm flex-shrink-0"></i>
                                            <span v-if="!sidebarCollapsed">{{ section.name }}</span>
                                        </div>
                                        <i
                                            v-if="!sidebarCollapsed"
                                            :class="expandedSections[section.key] ? 'fas fa-chevron-down' : 'fas fa-chevron-right'"
                                            class="text-xs transition-transform"></i>
                                    </button>

                                    <!-- Section Items (collapsible) -->
                                    <div v-if="expandedSections[section.key] && !sidebarCollapsed" class="ml-4 space-y-1">
                                        <Link
                                            v-for="item in section.items"
                                            :key="item.name"
                                            :href="route(item.route)"
                                            :class="[
                                                route().current(item.route + '*')
                                                    ? 'bg-primary-50 dark:bg-primary-500/10 text-primary-700 dark:text-primary-400 font-medium'
                                                    : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white hover:bg-gray-100 dark:hover:bg-gray-800/80',
                                                'group flex items-center px-3 py-2 text-sm rounded-lg transition-colors'
                                            ]">
                                            <i :class="[item.icon, 'mr-3']" class="text-xs flex-shrink-0"></i>
                                            <span>{{ item.name }}</span>
                                        </Link>
                                    </div>
                                </template>

                                <!-- Non-expandable section items -->
                                <template v-else>
                                    <Link
                                        v-for="item in section.items"
                                        :key="item.name"
                                        :href="route(item.route)"
                                        :class="[
                                            route().current(item.route + '*')
                                                ? 'bg-gray-100 dark:bg-gray-700 text-gray-900 dark:text-white'
                                                : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white hover:bg-gray-50 dark:hover:bg-gray-700',
                                            'group flex items-center px-3 py-2 text-sm font-medium rounded-lg transition-colors'
                                        ]">
                                        <i :class="[item.icon, sidebarCollapsed ? 'mr-0' : 'mr-3']" class="text-sm flex-shrink-0"></i>
                                        <span v-if="!sidebarCollapsed">{{ item.name }}</span>
                                    </Link>
                                </template>
                            </template>
                        </nav>
                    </div>
                </aside>

                <!-- Main Content -->
                <main class="flex-1 bg-gray-50 dark:bg-gray-950">
                    <!-- Dynamic Toolbar (Sticky) - Page actions and search -->
                    <div class="sticky top-0 z-30 bg-white/80 dark:bg-gray-950/80 backdrop-blur-sm border-b border-gray-200/60 dark:border-gray-800">
                        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                            <div class="flex items-center justify-between py-3 gap-4">
                                <!-- Left: Dynamic Page Actions -->
                                <div class="flex items-center gap-2 flex-shrink-0">
                                    <template v-for="action in pageActions" :key="action.label">
                                        <Link
                                            v-if="action.href"
                                            :href="action.href"
                                            :class="getActionClasses(action)"
                                        >
                                            <i v-if="action.icon" :class="action.icon" class="text-sm"></i>
                                            <span class="hidden sm:inline">{{ action.label }}</span>
                                        </Link>
                                        <button
                                            v-else
                                            @click="action.onClick"
                                            :class="getActionClasses(action)"
                                        >
                                            <i v-if="action.icon" :class="action.icon" class="text-sm"></i>
                                            <span class="hidden sm:inline">{{ action.label }}</span>
                                        </button>
                                    </template>
                                </div>

                                <!-- Right: OmniSearch -->
                                <div class="flex-shrink-0 w-72 lg:w-96">
                                    <OmniSearch
                                        placeholder="Search... (Cmd+K)"
                                        @select="(item) => {}"
                                        class="w-full"
                                    />
                                </div>
                            </div>

                        </div>
                    </div>

                    <!-- Page Content -->
                    <div class="py-6">
                        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                            <slot />
                        </div>
                    </div>
                </main>
            </div>
        </div>

        <!-- NOTIFICATION TOAST - ADDED -->
        <NotificationToast />

        <!-- Command Palette (Cmd+K) -->
        <CommandPalette
            v-model="showCommandPalette"
            @open-quick-intake="showQuickIntake = true"
        />

        <!-- Quick Intake Panel -->
        <QuickIntakePanel
            v-model="showQuickIntake"
            :suppliers="[]"
            :locations="[]"
        />
    </div>
</template>

