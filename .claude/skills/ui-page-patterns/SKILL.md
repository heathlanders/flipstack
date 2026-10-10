---
name: ui-page-patterns
description: Full page composition recipes for dashboards, list/index pages, detail/show pages, create/edit form pages, and settings pages
user-invocable: true
---

# UI Page Patterns -- Complete Page Recipes

This skill composes patterns from all other UI skills into full page recipes. Reference:
- **ui-design-system** for tokens (colors, typography, spacing, borders)
- **ui-data-display** for tables, stats, badges, empty states
- **ui-forms-inputs** for inputs, form layouts, filter bars, wizards
- **ui-layout-navigation** for page headers, tabs, content widths, responsive strategy
- **ui-feedback-states** for loading, empty, error, transitions

---

## Dashboard Page

### Structure
```
Page Shell (max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 sm:py-8)
  |
  +-- Alert Banner (optional -- warnings, announcements)
  |     spacing: mb-6
  |
  +-- Page Header
  |     h1: "Dashboard"
  |     subtitle: "Welcome back, {name}. Here's what's happening."
  |     actions: date range picker, refresh button
  |     spacing: mb-6
  |
  +-- Stat Grid
  |     grid: grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6
  |     cards: 4 KPI cards (revenue, orders, products, conversion)
  |     each: overline + value + trend + icon container
  |     spacing: mb-6
  |
  +-- Two-Column Content
  |     grid: grid-cols-1 lg:grid-cols-3 gap-6
  |     left (lg:col-span-2): chart card(s), recent orders table
  |     right (lg:col-span-1): activity feed, quick actions, alerts
  |
  +-- Quick Actions (optional)
        grid of shortcut cards or button group
```

### Loading State
- Render skeleton stat cards (4) in the grid
- Render skeleton chart area (rectangle pulse)
- Render skeleton activity feed (5 line items)

### Empty State (new account)
- Replace stat grid with a welcome card
- Show onboarding checklist (setup steps with progress)
- Quick actions prominent

---

## List / Index Page

### Structure
```
Page Shell (max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 sm:py-8)
  |
  +-- Page Header
  |     h1: "Products" (or resource name)
  |     actions: [Export (secondary), Add Product (primary)]
  |     spacing: mb-6
  |
  +-- Filter Bar
  |     flex flex-wrap items-end gap-3
  |     search input (flex-1 min-w-[200px]) + filter selects + active count + clear
  |     spacing: mb-4
  |
  +-- Bulk Action Bar (conditional, when items selected)
  |     bg-primary-50 border border-primary-200 rounded-lg px-4 py-2.5
  |     "N items selected" + action buttons
  |     spacing: mb-4
  |
  +-- Data Table Card
  |     bg-white border border-gray-200 shadow-sm rounded-xl overflow-hidden
  |     optional card header (title + table-level actions)
  |     table: sticky header, hover rows, action column
  |     footer: pagination (results count left, controls right)
  |
  +-- OR Card Grid (for visual items like products with images)
        grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6
        each card: image + title + meta + status badge + actions
```

### Table vs Card Grid Decision
- **Table**: Best for data with many comparable fields (inventory, orders, suppliers)
- **Card Grid**: Best for visual items (products with images, listings with thumbnails)
- Some pages offer a toggle between views (list/grid icon buttons in the filter bar)

### Loading State
- Filter bar renders immediately (interactive)
- Table: 6-8 skeleton rows
- Card grid: 6-8 skeleton cards

### Empty States
1. **No items exist**: icon + "No products yet" + "Add Product" button
2. **No filter results**: search icon + "No results found" + "Clear all filters" link
3. **Permission denied**: lock icon + "Access restricted" message

### Responsive Behavior
- Filter bar wraps naturally with `flex-wrap`
- Table scrolls horizontally, hide less important columns with `hidden lg:table-cell`
- Action buttons in header stack on mobile: `flex-col sm:flex-row`

---

## Detail / Show Page

### Structure
```
Page Shell (max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 sm:py-8)
  |
  +-- Back Link
  |     "< Back to Products" (text-sm text-gray-500 hover:text-gray-700)
  |     spacing: mb-4
  |
  +-- Header
  |     title: item name (text-2xl font-bold)
  |     status badge next to title
  |     actions: [Edit (secondary), Delete (danger ghost), More... (dropdown)]
  |     spacing: mb-6
  |
  +-- Two-Column Layout
       grid: grid-cols-1 lg:grid-cols-3 gap-6
       |
       +-- Main Content (lg:col-span-2)
       |     tabbed sections or stacked cards
       |     tab 1: Overview (key details in definition list or field grid)
       |     tab 2: Inventory (stock table)
       |     tab 3: Listings (marketplace cards)
       |     tab 4: History (activity feed)
       |
       +-- Sidebar (lg:col-span-1)
             sticky top-6 space-y-6
             summary card (key stats, status, dates)
             quick actions card
             related items card
```

### Definition List (for detail fields)
```html
<dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-4">
  <div>
    <dt class="text-xs font-medium text-gray-500 uppercase tracking-wider">SKU</dt>
    <dd class="mt-1 text-sm text-gray-900">SKU-12345</dd>
  </div>
  <div>
    <dt class="text-xs font-medium text-gray-500 uppercase tracking-wider">Category</dt>
    <dd class="mt-1 text-sm text-gray-900">Electronics</dd>
  </div>
</dl>
```

### Sidebar Summary Card
```html
<div class="bg-white border border-gray-200 shadow-sm rounded-xl p-5 space-y-4">
  <h3 class="text-sm font-semibold text-gray-900">Summary</h3>
  <div class="space-y-3">
    <div class="flex items-center justify-between">
      <span class="text-sm text-gray-500">Status</span>
      <span class="badge...">Active</span>
    </div>
    <div class="flex items-center justify-between">
      <span class="text-sm text-gray-500">Created</span>
      <span class="text-sm text-gray-900">Mar 15, 2026</span>
    </div>
    <!-- more rows -->
  </div>
</div>
```

### Loading State
- Back link and header render immediately
- Main content tabs: skeleton matching active tab's content
- Sidebar: skeleton summary card

---

## Create / Edit Form Page

### Structure
```
Page Shell (max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 sm:py-8)
  |
  +-- Back Link or Breadcrumb
  |     spacing: mb-4
  |
  +-- Page Header
  |     h1: "Create Product" or "Edit Product"
  |     no action buttons (form footer handles save)
  |     spacing: mb-6
  |
  +-- Form Body
  |     max-w-3xl (or full width for complex forms)
  |     space-y-6 between form cards
  |     |
  |     +-- Section Card 1: "Basic Info"
  |     |     bg-white border border-gray-200 shadow-sm rounded-xl
  |     |     header: px-5 py-4 border-b border-gray-100 (title + optional description)
  |     |     body: p-5 space-y-5
  |     |     fields: text, textarea, select
  |     |
  |     +-- Section Card 2: "Pricing"
  |     |     same card structure
  |     |     field grid: grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-4
  |     |
  |     +-- Section Card 3: "Images"
  |     |     drop zone + image preview grid
  |     |
  |     +-- More sections as needed...
  |
  +-- Form Footer (sticky)
        sticky bottom-0 z-10
        bg-white/95 backdrop-blur border-t border-gray-200
        px-5 py-4 flex items-center justify-between (or justify-end)
        left: unsaved changes indicator (optional)
        right: Cancel (secondary) + Save (primary)
```

### Unsaved Changes Indicator
```html
<div class="flex items-center gap-2 text-sm text-amber-600">
  <i class="fas fa-circle w-2 h-2"></i>
  <span>Unsaved changes</span>
</div>
```

### Form Validation Flow
1. Validate on blur for individual fields (show inline error immediately)
2. Validate all on submit
3. If errors: show form-level error banner at top + scroll to first error field
4. If success: redirect to detail page with success toast

### Loading State (Edit page)
- Page header renders immediately
- Form cards show skeleton inputs (gray rectangles matching input heights)
- Footer disabled until data loads

---

## Settings Page

### Structure
```
Page Shell (max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-6 sm:py-8)
  |
  +-- Page Header
  |     h1: "Settings"
  |     no actions
  |     spacing: mb-6
  |
  +-- Settings Layout
       grid: grid-cols-1 lg:grid-cols-[220px_1fr] gap-8
       |
       +-- Left: Section Nav (desktop sidebar, mobile tabs/accordion)
       |     sticky top-6
       |     nav links styled like sidebar items
       |     active: text-primary-700 bg-primary-50 rounded-lg
       |     default: text-gray-600 hover:text-gray-900 hover:bg-gray-100 rounded-lg
       |
       +-- Right: Content
             space-y-8 between sections
             |
             +-- Section: "General"
             |     two-column: description (left) + form card (right)
             |     (see ui-forms-inputs two-column settings layout)
             |     per-section save button in card footer
             |
             +-- Section: "Notifications"
             |     toggle settings with labels
             |
             +-- Section: "Integrations"
             |     integration cards with connect/disconnect
             |
             +-- Danger Zone (always last)
                   border-red-200 bg-red-50/30 rounded-xl p-5
                   "Delete Organization" with red button
                   requires typed confirmation dialog
```

### Settings Nav (Desktop)
```html
<nav class="space-y-0.5">
  <a href="#general" class="flex items-center gap-2 px-3 py-2 text-sm font-medium text-primary-700 bg-primary-50 rounded-lg">
    <i class="fas fa-gear w-4 h-4"></i>General
  </a>
  <a href="#notifications" class="flex items-center gap-2 px-3 py-2 text-sm font-medium text-gray-600 hover:text-gray-900 hover:bg-gray-100 rounded-lg transition-colors">
    <i class="fas fa-bell w-4 h-4 text-gray-400"></i>Notifications
  </a>
  <!-- more items -->
</nav>
```

### Settings Nav (Mobile)
On mobile (`lg:hidden`), show horizontal scrollable tabs instead of sidebar nav:
```html
<div class="lg:hidden border-b border-gray-200 mb-6">
  <nav class="-mb-px flex gap-4 overflow-x-auto">
    <!-- standard tab items from ui-layout-navigation -->
  </nav>
</div>
```

### Danger Zone
```html
<div class="border border-red-200 bg-red-50/30 rounded-xl p-5">
  <h3 class="text-base font-semibold text-red-900">Danger Zone</h3>
  <p class="text-sm text-red-700 mt-1">These actions are irreversible. Proceed with caution.</p>
  <div class="mt-4 pt-4 border-t border-red-200">
    <div class="flex items-center justify-between">
      <div>
        <p class="text-sm font-medium text-gray-900">Delete Organization</p>
        <p class="text-xs text-gray-500 mt-0.5">Permanently delete this organization and all its data.</p>
      </div>
      <button class="px-4 py-2 text-sm font-medium text-white bg-red-600 hover:bg-red-700 rounded-lg shadow-sm">
        Delete
      </button>
    </div>
  </div>
</div>
```

### Per-Section Save
Each settings section saves independently:
```html
<div class="bg-white border border-gray-200 shadow-sm rounded-xl">
  <div class="p-5 space-y-5">
    <!-- fields -->
  </div>
  <div class="px-5 py-3 bg-gray-50 border-t border-gray-100 rounded-b-xl flex justify-end">
    <button class="px-4 py-2 text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 rounded-lg shadow-sm">
      Save
    </button>
  </div>
</div>
```

---

## Page Composition Checklist

When building any page, verify:

- [ ] Page uses `max-w-{size} mx-auto px-4 sm:px-6 lg:px-8 py-6 sm:py-8`
- [ ] Cards use `bg-white border border-gray-200 shadow-sm rounded-xl`
- [ ] Page header follows canonical structure (breadcrumb/back > title + actions)
- [ ] Loading state uses skeletons (not spinners)
- [ ] Empty state has icon + heading + description + action
- [ ] Error state has retry action
- [ ] All numbers use `tabular-nums`
- [ ] All buttons use `font-medium` (no `uppercase tracking-widest`)
- [ ] Focus rings use `ring-4 ring-primary-500/20`
- [ ] Responsive: single column on mobile, multi-column on desktop
- [ ] Dark mode variants on all surfaces, borders, and text
- [ ] Transitions on interactive elements (`duration-150` default)
- [ ] Status badges use consistent color mapping from ui-data-display

---

## Analytics Dashboard Page

A specialized dashboard variant for data-heavy analytics views with charts, KPIs, and date filtering.

### Structure
```
Page Shell (max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 sm:py-8)
  |
  +-- Page Header
  |     h1: "Analytics" or "Inventory Analytics"
  |     actions: date range picker (see ui-forms-inputs), export button
  |     spacing: mb-6
  |
  +-- KPI Row
  |     grid: grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6
  |     cards: 4-6 stat cards with trends (see ui-data-display)
  |     spacing: mb-6
  |
  +-- Chart Grid
  |     grid: grid-cols-1 lg:grid-cols-2 gap-6
  |     each: card with header (title + legend/controls) + chart area
  |     chart area: min-h-[300px] for consistent sizing
  |     spacing: mb-6
  |
  +-- Data Table (optional)
        full-width card with the detailed breakdown data
        sortable columns, pagination
```

### Chart Card Pattern
```html
<div class="bg-white border border-gray-200 shadow-sm rounded-xl overflow-hidden">
  <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
    <div>
      <h3 class="text-base font-semibold text-gray-900">Stock Health</h3>
      <p class="text-xs text-gray-500 mt-0.5">Distribution by stock level</p>
    </div>
    <div class="flex items-center gap-2">
      <!-- Chart type toggle or legend items -->
    </div>
  </div>
  <div class="p-5">
    <div class="min-h-[300px]">
      <!-- Chart component renders here -->
    </div>
  </div>
</div>
```

### Full-Width Chart
For time-series or wide charts, use `lg:col-span-2` to span the full grid width.

### Loading State
- KPI row: skeleton stat cards
- Chart cards: skeleton with a gray rectangle matching chart area height
- Data table: skeleton rows

---

## Action Toolbar Recipe

The `ActionToolbar.vue` component composes search, filters, sort, view toggle, bulk actions, and results count into one integrated toolbar. Use this as the canonical toolbar for list/index pages.

### Structure
```
Sticky Toolbar (sticky top-0 z-30 bg-white/95 backdrop-blur shadow-sm)
  |
  +-- Primary Row (flex flex-col sm:flex-row items-center gap-3)
  |     |
  |     +-- Search Input (flex-1 max-w-md)
  |     |     leading icon, debounced, clear button
  |     |
  |     +-- Controls Group (flex items-center gap-2)
  |           filter popover button (with active count badge)
  |           sort dropdown
  |           view toggle (list/grid)
  |           secondary action buttons
  |           primary action button
  |
  +-- Bulk Action Row (conditional, when selectedCount > 0)
  |     bg-primary-50 px-4 py-2 rounded-lg
  |     "N items selected" + bulk action buttons + clear selection
  |
  +-- Results Count + Active Filters Summary
        text-xs text-gray-500: "Showing 1-25 of 142 products"
        active filter pills with remove buttons
```

### Results Summary with Filter Pills
```html
<div class="flex flex-wrap items-center gap-2 px-1 pt-2">
  <span class="text-xs text-gray-500">Showing {{ from }}-{{ to }} of {{ total }} results</span>
  <div v-for="filter in activeFilters" :key="filter.key" class="inline-flex items-center gap-1 px-2 py-0.5 text-xs bg-gray-100 text-gray-700 rounded-full">
    <span>{{ filter.label }}: {{ filter.value }}</span>
    <button @click="removeFilter(filter.key)" class="text-gray-400 hover:text-gray-600">
      <i class="fas fa-times w-2.5 h-2.5"></i>
    </button>
  </div>
  <button v-if="activeFilters.length > 1" @click="clearAll" class="text-xs font-medium text-primary-600 hover:text-primary-700">
    Clear all
  </button>
</div>
```

### Integration with List/Index Page
The action toolbar replaces the separate "Filter Bar" and "Bulk Action Bar" sections from the List/Index recipe. Place it directly below the page header:
```
Page Header (mb-6)
  |
  +-- ActionToolbar (mb-4)
  |
  +-- Data Table or Card Grid
```

### Loading State
The toolbar includes a subtle loading bar at the bottom edge:
```html
<div v-if="isLoading" class="absolute bottom-0 left-0 right-0 h-0.5 bg-primary-100 overflow-hidden">
  <div class="h-full w-1/3 bg-primary-500 animate-[slide_1s_ease-in-out_infinite]"></div>
</div>
```

## Audit Logging

Presentation-only skill -- no audit writes from UI primitives. Detail/show pages typically include an Activity Timeline tab/panel rendering `user_activity_logs` rows filtered by `model_type` + `model_id`. Use the `EntityTimeline.vue` partial from the Admin Audit Hub.

Spec: `docs/AUDIT_SYSTEM.md`. When a UI component is wired to a controller mutation, the controller (not the component) is responsible for emitting the audit row.
