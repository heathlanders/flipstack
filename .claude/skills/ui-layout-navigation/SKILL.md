---
name: ui-layout-navigation
description: AppLayout shell, sidebar navigation, page headers, breadcrumbs, tab navigation, sticky toolbars, content widths, and responsive breakpoint strategy
user-invocable: true
---

# UI Layout & Navigation

Reference the **ui-design-system** skill for all color tokens, typography, spacing, and border/shadow standards.

---

## Sidebar Navigation

### Structure
```html
<nav class="flex flex-col h-full bg-white dark:bg-gray-950 border-r border-gray-200 dark:border-gray-800 w-64">
  <!-- Logo area -->
  <div class="px-4 py-5 border-b border-gray-100">
    <!-- App logo -->
  </div>

  <!-- Navigation sections -->
  <div class="flex-1 overflow-y-auto py-4 px-3 space-y-6">
    <!-- Section -->
    <div>
      <p class="px-3 mb-2 text-[10px] font-semibold text-gray-400 uppercase tracking-widest">Inventory</p>
      <ul class="space-y-0.5">
        <!-- Nav items -->
      </ul>
    </div>
  </div>

  <!-- Footer (user menu, collapse toggle) -->
  <div class="px-3 py-3 border-t border-gray-100">
    <!-- user avatar + name + dropdown -->
  </div>
</nav>
```

### Nav Item States

**Default:**
```html
<a href="#" class="flex items-center gap-3 px-3 py-2 text-sm font-medium text-gray-600 rounded-lg hover:bg-gray-100 hover:text-gray-900 transition-colors duration-150">
  <i class="fas fa-box w-4 h-4 text-gray-400"></i>
  <span>Products</span>
</a>
```

**Active:**
```html
<a href="#" class="flex items-center gap-3 px-3 py-2 text-sm font-medium text-primary-700 dark:text-primary-400 bg-primary-50 dark:bg-primary-500/10 rounded-lg">
  <i class="fas fa-box w-4 h-4 text-primary-500"></i>
  <span>Products</span>
</a>
```

**With badge/count:**
```html
<a href="#" class="flex items-center gap-3 px-3 py-2 text-sm font-medium text-gray-600 rounded-lg hover:bg-gray-100 hover:text-gray-900 transition-colors duration-150">
  <i class="fas fa-inbox w-4 h-4 text-gray-400"></i>
  <span class="flex-1">Intake Queue</span>
  <span class="inline-flex items-center px-1.5 py-0.5 text-[10px] font-bold rounded-full bg-primary-100 text-primary-700">12</span>
</a>
```

### Section Headers
```
text-[10px] font-semibold text-gray-400 uppercase tracking-widest px-3 mb-2
```
Use `tracking-widest` here (this is the one place it belongs -- tiny overline labels in the sidebar).

### Collapsed Sidebar
When collapsed to icon-only width (~64px):
- Hide text labels with `hidden` or `w-0 overflow-hidden`
- Show tooltip on hover with nav item name
- Center icons: `justify-center` on the nav item
- Section headers become horizontal dividers

### Mobile Sidebar
- Render as a full-screen overlay sheet (`fixed inset-0 z-50`)
- Background overlay: `bg-gray-900/50 backdrop-blur-sm`
- Sidebar slides in from the left: `transition-transform duration-300 ease-out`
- Close button in top-right corner of sidebar panel
- Close on backdrop click and Escape key

---

## Page Header

### Canonical Structure
```html
<div class="space-y-4">
  <!-- Breadcrumb (optional) -->
  <nav class="flex items-center gap-1.5 text-sm">
    <a href="#" class="text-gray-500 hover:text-gray-700 transition-colors">Products</a>
    <i class="fas fa-chevron-right w-3 h-3 text-gray-300"></i>
    <span class="text-gray-900 font-medium">Widget Pro</span>
  </nav>

  <!-- Title row -->
  <div class="flex items-center justify-between gap-4">
    <div>
      <h1 class="text-2xl font-bold text-gray-900">Products</h1>
      <p class="text-sm text-gray-500 mt-1">Manage your product catalog.</p> <!-- optional subtitle -->
    </div>
    <div class="flex items-center gap-3">
      <!-- Action buttons -->
      <button class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 hover:bg-gray-50 rounded-lg shadow-sm">
        Export
      </button>
      <button class="px-4 py-2 text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 rounded-lg shadow-sm">
        <i class="fas fa-plus w-3.5 h-3.5 mr-1.5"></i>Add Product
      </button>
    </div>
  </div>

  <!-- Tabs (optional, see Tab Navigation below) -->
</div>
```

### Back Link (for detail/show pages)
Replace breadcrumb with a simple back link:
```html
<a href="#" class="inline-flex items-center gap-1.5 text-sm text-gray-500 hover:text-gray-700 transition-colors">
  <i class="fas fa-arrow-left w-3.5 h-3.5"></i>
  Back to Products
</a>
```

### Breadcrumb Rules
- Max 3 levels (beyond that, use back link instead)
- Current page is `text-gray-900 font-medium` (not a link)
- Parent pages are `text-gray-500 hover:text-gray-700` links
- Separator: `fa-chevron-right w-3 h-3 text-gray-300`

---

## Tab Navigation

### Standard Tabs (below page header)
```html
<div class="border-b border-gray-200">
  <nav class="-mb-px flex gap-6 overflow-x-auto" aria-label="Tabs">
    <!-- Active tab -->
    <button class="whitespace-nowrap border-b-2 border-primary-500 px-1 py-3 text-sm font-medium text-primary-600">
      Overview
    </button>
    <!-- Inactive tab -->
    <button class="whitespace-nowrap border-b-2 border-transparent px-1 py-3 text-sm font-medium text-gray-500 hover:border-gray-300 hover:text-gray-700 transition-colors duration-150">
      Inventory
    </button>
    <!-- Tab with count -->
    <button class="whitespace-nowrap border-b-2 border-transparent px-1 py-3 text-sm font-medium text-gray-500 hover:border-gray-300 hover:text-gray-700 transition-colors duration-150 flex items-center gap-2">
      Listings
      <span class="inline-flex items-center px-1.5 py-0.5 text-[10px] font-bold rounded-full bg-gray-100 text-gray-600">5</span>
    </button>
  </nav>
</div>
```

### Tab Rules
- Active: `border-b-2 border-primary-500 text-primary-600`
- Inactive: `border-b-2 border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700`
- On mobile: horizontal scroll with `overflow-x-auto` and `whitespace-nowrap`
- Never wrap tabs to multiple lines
- Use `gap-6` between tabs for comfortable spacing

### Card-Internal Tabs
For tabs within a card (no page-level border):
```html
<div class="px-5 pt-4 border-b border-gray-100">
  <nav class="-mb-px flex gap-4">
    <!-- same tab items, slightly smaller gap -->
  </nav>
</div>
```

---

## Content Width Strategy

| Page Type | Width | Classes |
|-----------|-------|---------|
| Standard pages | 1280px | `max-w-7xl mx-auto` |
| Data-dense (full tables) | Full width | No max-width |
| Narrow forms | 768px | `max-w-3xl mx-auto` |
| Ultra-narrow (login, confirm) | 480px | `max-w-md mx-auto` |
| Settings | 1024px | `max-w-5xl mx-auto` |

---

## Responsive Breakpoint Strategy

| Breakpoint | Width | Layout Changes |
|-----------|-------|----------------|
| Default (mobile) | < 640px | Single column, stacked, hamburger menu |
| `sm` (640px) | >= 640px | Side-by-side form fields, two-column stat grid |
| `md` (768px) | >= 768px | Sidebar visible (desktop nav), wider cards |
| `lg` (1024px) | >= 1024px | Full layout with sidebar + content, three-column grids |
| `xl` (1280px) | >= 1280px | Wider content area, four-column stat grids |

### Responsive Patterns
- **Stack -> Grid**: `grid-cols-1 sm:grid-cols-2 lg:grid-cols-4`
- **Full -> Sidebar+Content**: `lg:grid-cols-[280px_1fr]`
- **Hidden on mobile**: `hidden sm:block` or `hidden lg:flex`
- **Mobile-only**: `sm:hidden`
- **Padding scale**: `px-4 sm:px-6 lg:px-8`

---

## Sticky Toolbar

For table toolbars (filter bar, bulk actions) that should stick when scrolling:

```html
<div class="sticky top-0 z-20 bg-white/95 backdrop-blur border-b border-gray-200 px-5 py-3">
  <!-- filter bar or bulk action bar -->
</div>
```

### Scroll Shadow
Add a subtle shadow when scrolled using IntersectionObserver:
```javascript
// In Vue setup
const toolbar = ref(null)
const isScrolled = ref(false)

onMounted(() => {
  const sentinel = document.createElement('div')
  toolbar.value.parentNode.insertBefore(sentinel, toolbar.value)
  const observer = new IntersectionObserver(([e]) => {
    isScrolled.value = !e.isIntersecting
  })
  observer.observe(sentinel)
})
```

```html
<div :class="['sticky top-0 z-20 ... transition-shadow duration-200', isScrolled ? 'shadow-sm' : '']">
```

---

## Command Palette

A keyboard-driven search/command interface (Cmd/Ctrl+K). See `CommandPalette.vue`.

### Structure
```html
<!-- Teleport to body for proper z-index stacking -->
<Teleport to="body">
  <div v-if="open" class="fixed inset-0 z-50">
    <!-- Backdrop -->
    <div class="fixed inset-0 bg-gray-900/50 backdrop-blur-sm" @click="close"></div>

    <!-- Dialog -->
    <div class="fixed inset-x-0 top-[20%] mx-auto max-w-xl">
      <div class="bg-white dark:bg-gray-800 rounded-xl shadow-2xl border border-gray-200 dark:border-gray-700 overflow-hidden">
        <!-- Search input -->
        <div class="flex items-center gap-3 px-4 py-3 border-b border-gray-200 dark:border-gray-700">
          <i class="fas fa-search w-5 h-5 text-gray-400"></i>
          <input ref="searchInput" v-model="query" type="text"
            class="flex-1 bg-transparent text-sm text-gray-900 dark:text-gray-100 placeholder-gray-400 border-0 focus:ring-0 p-0"
            placeholder="Search products, commands, pages..." />
          <kbd class="hidden sm:inline-flex items-center px-1.5 py-0.5 text-[10px] font-medium text-gray-400 bg-gray-100 dark:bg-gray-700 rounded">
            ESC
          </kbd>
        </div>

        <!-- Results -->
        <div class="max-h-96 overflow-y-auto py-2">
          <!-- Group -->
          <div v-for="group in groupedResults" :key="group.name">
            <p class="px-4 py-1.5 text-[10px] font-semibold text-gray-400 uppercase tracking-widest">{{ group.name }}</p>
            <div v-for="(item, i) in group.items" :key="item.id"
              :class="[activeIndex === i ? 'bg-primary-50 dark:bg-primary-900/20' : '']"
              class="flex items-center gap-3 px-4 py-2 cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700/50">
              <i :class="item.icon" class="w-4 h-4 text-gray-400"></i>
              <span class="text-sm text-gray-900 dark:text-gray-100 flex-1">{{ item.label }}</span>
              <span v-if="item.shortcut" class="text-xs text-gray-400">{{ item.shortcut }}</span>
            </div>
          </div>
        </div>

        <!-- Footer -->
        <div class="px-4 py-2 border-t border-gray-200 dark:border-gray-700 flex items-center gap-4 text-xs text-gray-400">
          <span><kbd class="font-mono">↑↓</kbd> navigate</span>
          <span><kbd class="font-mono">↵</kbd> select</span>
          <span><kbd class="font-mono">esc</kbd> close</span>
        </div>
      </div>
    </div>
  </div>
</Teleport>
```

### Keyboard Shortcuts
- **Cmd/Ctrl+K**: Toggle open
- **Arrow Up/Down**: Navigate results
- **Enter**: Execute selected item
- **Escape**: Close palette

### Result Groups
Standard groups in order: Quick Actions, Commands, Products (from search), Suppliers, Navigation, Recent Items.

### Recent Items
Store in `localStorage` (max 10 items). Show when query is empty as the default result group.

---

## Notification Dropdown

A bell icon with unread count badge that opens a notification list. See `NotificationBell.vue`.

### Structure
```html
<div class="relative">
  <!-- Bell trigger -->
  <button @click="toggle" class="relative p-2 text-gray-600 hover:text-gray-900 hover:bg-gray-100 rounded-lg transition-colors">
    <i class="fas fa-bell w-5 h-5"></i>
    <!-- Unread badge -->
    <span v-if="unreadCount > 0"
      class="absolute top-0 right-0 inline-flex items-center justify-center w-5 h-5 text-[10px] font-bold text-white bg-red-500 rounded-full ring-2 ring-white">
      {{ unreadCount > 99 ? '99+' : unreadCount }}
    </span>
  </button>

  <!-- Dropdown -->
  <div v-if="open" class="absolute right-0 z-50 mt-2 w-96 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl shadow-xl">
    <!-- Header -->
    <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between">
      <h3 class="text-sm font-semibold text-gray-900">Notifications</h3>
      <button v-if="unreadCount > 0" @click="markAllRead" class="text-xs font-medium text-primary-600 hover:text-primary-700">
        Mark all read
      </button>
    </div>
    <!-- List -->
    <div class="max-h-96 overflow-y-auto divide-y divide-gray-100">
      <div v-for="n in notifications" :key="n.id"
        :class="[n.read_at ? '' : 'bg-primary-50/30']"
        class="px-4 py-3 hover:bg-gray-50 cursor-pointer transition-colors">
        <div class="flex gap-3">
          <div class="p-1.5 rounded-lg" :class="priorityIconClasses(n.priority)">
            <i :class="n.icon" class="w-4 h-4"></i>
          </div>
          <div class="flex-1 min-w-0">
            <p class="text-sm text-gray-900" :class="{ 'font-medium': !n.read_at }">{{ n.title }}</p>
            <p class="text-xs text-gray-500 mt-0.5 truncate">{{ n.body }}</p>
            <p class="text-xs text-gray-400 mt-1">{{ relativeTime(n.created_at) }}</p>
          </div>
        </div>
      </div>
    </div>
    <!-- Footer -->
    <div class="px-4 py-2.5 border-t border-gray-100 text-center">
      <a href="/notifications" class="text-xs font-medium text-primary-600 hover:text-primary-700">View all notifications</a>
    </div>
  </div>
</div>
```

### Priority Colors
| Priority | Icon Container |
|----------|---------------|
| Critical | `bg-red-100 text-red-600` |
| High | `bg-amber-100 text-amber-600` |
| Medium | `bg-blue-100 text-blue-600` |
| Low | `bg-gray-100 text-gray-500` |

### Behavior
- Fetch notifications on mount and on each open
- Close on click-outside and Escape
- Mark individual as read on click (navigate to link)
- Mark all read via API call

---

## Theme Toggle (User Dropdown)

The user dropdown in `AppLayout.vue` includes a Light/Dark/System segmented control. It uses the `useTheme` composable from `resources/js/Composables/useTheme.js`.

- Appears between "API Tokens" and "Log Out" in the user dropdown
- Uses `@click.stop` on the container to prevent dropdown close on selection
- Active option: `bg-white dark:bg-gray-700 shadow-sm text-gray-900 dark:text-white`
- Inactive option: `text-gray-500 hover:text-gray-700 dark:hover:text-gray-300`
- Icons: Heroicons outline (sun, moon, computer-desktop) as inline SVGs
- Preference persists in `localStorage('theme')` and survives page refresh with no flash

---

## Sidebar Navigation Sections Reference

The sidebar organizes navigation into the following section groups. Each section uses the `text-[10px] font-semibold text-gray-400 uppercase tracking-widest` header style.

| Section Header | Nav Items |
|----------------|-----------|
| **Inventory** | Dashboard, Products, Intake Queue, Batches, Zones/Bins, Bundles |
| **Marketplace** | eBay Listings, Shopify Listings, Scheduled Listings, Cross-Listing |
| **Fulfillment** | Orders, Pick/Pack/Ship, Routing Rules, Shipping Rules, Returns |
| **Procurement** | Purchase Orders, Batches |
| **Sales** | Customers |
| **Analytics** | Profit & Loss, Forecasting |
| **Marketing** | Email Campaigns |
| **Messages** | Buyer Communication |
| **Pricing** | Repricing Rules |
| **Suppliers** | Supplier Portal, Product Submissions |
| **Settings** | Team, Integrations, Currency |

---

## Slide-Over Panel (Side Drawer)

For quick-edit panels, detail previews, or filters:

```html
<!-- Overlay -->
<div class="fixed inset-0 z-50 bg-gray-900/50 backdrop-blur-sm" @click="close"></div>

<!-- Panel -->
<div class="fixed inset-y-0 right-0 z-50 w-full max-w-md bg-white shadow-xl flex flex-col">
  <!-- Header -->
  <div class="flex items-center justify-between px-5 py-4 border-b border-gray-200">
    <h2 class="text-lg font-semibold text-gray-900">Panel Title</h2>
    <button @click="close" class="p-1.5 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-lg">
      <i class="fas fa-times w-4 h-4"></i>
    </button>
  </div>
  <!-- Body -->
  <div class="flex-1 overflow-y-auto p-5">
    <!-- content -->
  </div>
  <!-- Footer (optional) -->
  <div class="px-5 py-4 border-t border-gray-200 flex items-center justify-end gap-3">
    <button class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 hover:bg-gray-50 rounded-lg shadow-sm">Cancel</button>
    <button class="px-4 py-2 text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 rounded-lg shadow-sm">Save</button>
  </div>
</div>
```

### Transition
```html
<Transition
  enter-active-class="transition-transform duration-300 ease-out"
  enter-from-class="translate-x-full"
  enter-to-class="translate-x-0"
  leave-active-class="transition-transform duration-200 ease-in"
  leave-from-class="translate-x-0"
  leave-to-class="translate-x-full"
>
  <!-- panel -->
</Transition>
```

## Audit Logging

Presentation-only skill -- no audit writes from UI primitives. Admin sidebar has an "Audit Hub" entry under the Admin section pointing to `/admin/audit-hub` (manager-gated).

Spec: `docs/AUDIT_SYSTEM.md`. When a UI component is wired to a controller mutation, the controller (not the component) is responsible for emitting the audit row.
