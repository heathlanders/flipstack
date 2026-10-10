---
name: ui-data-display
description: Data tables, stat/metric/KPI cards, status badges, progress indicators, activity feeds, and empty states for data views
user-invocable: true
---

# UI Data Display -- Tables, Stats, and Data-Dense Views

Reference the **ui-design-system** skill for all color tokens, typography, spacing, and border/shadow standards.

---

## Stat / Metric Cards

### Canonical Anatomy
Every stat card follows this structure:

```html
<div class="bg-white border border-gray-200 shadow-sm rounded-xl p-5">
  <div class="flex items-start justify-between">
    <div class="space-y-1">
      <!-- Overline label -->
      <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">Total Revenue</p>
      <!-- Value -->
      <p class="text-2xl font-bold text-gray-900 tabular-nums">$24,580</p>
      <!-- Trend (optional) -->
      <div class="flex items-center gap-1.5 text-xs font-medium text-emerald-600">
        <i class="fas fa-arrow-up w-3 h-3"></i>
        <span>12.5% from last month</span>
      </div>
    </div>
    <!-- Icon container -->
    <div class="p-2 rounded-lg bg-emerald-100">
      <i class="fas fa-dollar-sign w-5 h-5 text-emerald-600"></i>
    </div>
  </div>
</div>
```

### Accent Bar Variant
For visual grouping, add a top accent bar:
```html
<div class="bg-white border border-gray-200 shadow-sm rounded-xl overflow-hidden">
  <div class="h-1 bg-emerald-500"></div>
  <div class="p-5">
    <!-- content same as above without icon container -->
  </div>
</div>
```

### Trend Indicators
| Direction | Classes |
|-----------|---------|
| Up (positive) | `text-emerald-600` + `fa-arrow-up` |
| Down (negative) | `text-red-600` + `fa-arrow-down` |
| Up (negative, e.g., costs) | `text-red-600` + `fa-arrow-up` |
| Down (positive, e.g., errors) | `text-emerald-600` + `fa-arrow-down` |
| Neutral / unchanged | `text-gray-500` + `fa-minus` |

### Loading Skeleton
Replace content with pulse placeholders that match the layout:
```html
<div class="bg-white border border-gray-200 shadow-sm rounded-xl p-5 animate-pulse">
  <div class="flex items-start justify-between">
    <div class="space-y-2">
      <div class="h-3 w-24 bg-gray-200 rounded"></div>
      <div class="h-7 w-20 bg-gray-200 rounded"></div>
      <div class="h-3 w-32 bg-gray-200 rounded"></div>
    </div>
    <div class="w-9 h-9 bg-gray-200 rounded-lg"></div>
  </div>
</div>
```

### Responsive Grid
```html
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6">
  <!-- stat cards -->
</div>
```

---

## Data Tables

### Structure
```html
<div class="bg-white border border-gray-200 shadow-sm rounded-xl overflow-hidden">
  <!-- Optional table header with title and actions -->
  <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
    <h3 class="text-base font-semibold text-gray-900">Products</h3>
    <div class="flex items-center gap-2">
      <!-- table-level actions -->
    </div>
  </div>

  <div class="overflow-x-auto">
    <table class="min-w-full divide-y divide-gray-200">
      <thead>
        <tr class="bg-gray-50">
          <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
          <!-- more headers -->
        </tr>
      </thead>
      <tbody class="divide-y divide-gray-100">
        <tr class="hover:bg-gray-50 transition-colors duration-150 group">
          <td class="px-5 py-3.5 text-sm text-gray-900 whitespace-nowrap">...</td>
          <!-- more cells -->
        </tr>
      </tbody>
    </table>
  </div>

  <!-- Optional footer with pagination -->
  <div class="px-5 py-3 bg-gray-50 border-t border-gray-100 rounded-b-xl">
    <!-- pagination -->
  </div>
</div>
```

### Header Row
- Background: `bg-gray-50`
- Text: `text-xs font-medium text-gray-500 uppercase tracking-wider`
- Padding: `px-5 py-3`
- Sticky header (for long tables): add `sticky top-0 z-10` to `<thead>` and `bg-gray-50` to `<tr>`

### Row States
| State | Classes |
|-------|---------|
| Default | `bg-white` |
| Hover | `hover:bg-gray-50 transition-colors duration-150` |
| Selected | `bg-primary-50` |
| Disabled/Archived | `opacity-60` |

### Row Actions
Use icon buttons that appear on hover:
```html
<td class="px-5 py-3.5 text-right">
  <div class="flex items-center justify-end gap-1 opacity-0 group-hover:opacity-100 transition-opacity duration-150">
    <button class="p-1.5 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-lg">
      <i class="fas fa-pencil w-3.5 h-3.5"></i>
    </button>
    <button class="p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg">
      <i class="fas fa-trash w-3.5 h-3.5"></i>
    </button>
  </div>
</td>
```

### Cell Types

**Text cell:**
```html
<td class="px-5 py-3.5 text-sm text-gray-900">Product Name</td>
```

**Secondary text cell:**
```html
<td class="px-5 py-3.5 text-sm text-gray-500">Optional info</td>
```

**Numeric cell (right-aligned):**
```html
<td class="px-5 py-3.5 text-sm text-gray-900 text-right tabular-nums">1,234</td>
```

**Currency cell:**
```html
<td class="px-5 py-3.5 text-sm font-medium text-gray-900 text-right tabular-nums">$99.99</td>
```

**Avatar + name cell:**
```html
<td class="px-5 py-3.5">
  <div class="flex items-center gap-3">
    <img class="w-8 h-8 rounded-full" src="..." alt="" />
    <div>
      <p class="text-sm font-medium text-gray-900">Jane Doe</p>
      <p class="text-xs text-gray-500">jane@example.com</p>
    </div>
  </div>
</td>
```

**Image + text cell (products):**
```html
<td class="px-5 py-3.5">
  <div class="flex items-center gap-3">
    <img class="w-10 h-10 rounded-lg object-cover border border-gray-200" src="..." alt="" />
    <div>
      <p class="text-sm font-medium text-gray-900 truncate max-w-[200px]">Product Title</p>
      <p class="text-xs text-gray-500">SKU-12345</p>
    </div>
  </div>
</td>
```

### Sort Indicators
```html
<!-- Sortable header (inactive) -->
<th class="px-5 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:text-gray-700 group">
  Name
  <i class="fas fa-sort w-3 h-3 ml-1 text-gray-300 group-hover:text-gray-400"></i>
</th>

<!-- Sorted ascending (active) -->
<th class="px-5 py-3 text-left text-xs font-medium text-gray-900 uppercase tracking-wider cursor-pointer">
  Name
  <i class="fas fa-sort-up w-3 h-3 ml-1 text-primary-500"></i>
</th>

<!-- Sorted descending (active) -->
<th class="... text-gray-900 ...">
  Name
  <i class="fas fa-sort-down w-3 h-3 ml-1 text-primary-500"></i>
</th>
```

### Responsive Strategy
For tables with many columns:
1. **Primary approach**: Horizontal scroll with `overflow-x-auto` on wrapper
2. **Columns to hide on mobile**: Use `hidden sm:table-cell` or `hidden lg:table-cell`
3. **Never**: Collapse tables into cards on mobile (too complex, hard to maintain)

### Checkbox Column (for bulk actions)
```html
<th class="w-10 px-5 py-3">
  <input type="checkbox" class="rounded border-gray-300 text-primary-600 focus:ring-primary-500/20" />
</th>
```

### Bulk Action Bar
Show when items are selected, floating above the table:
```html
<div class="bg-primary-50 border border-primary-200 rounded-lg px-4 py-2.5 flex items-center justify-between">
  <span class="text-sm font-medium text-primary-800">3 items selected</span>
  <div class="flex items-center gap-2">
    <button class="px-3 py-1.5 text-xs font-medium text-primary-700 hover:bg-primary-100 rounded-lg">Export</button>
    <button class="px-3 py-1.5 text-xs font-medium text-red-700 hover:bg-red-100 rounded-lg">Delete</button>
  </div>
</div>
```

---

## Pagination

```html
<div class="flex items-center justify-between px-5 py-3 bg-gray-50 border-t border-gray-100">
  <!-- Results count -->
  <p class="text-sm text-gray-500">
    Showing <span class="font-medium text-gray-700">1</span> to <span class="font-medium text-gray-700">10</span> of <span class="font-medium text-gray-700">97</span> results
  </p>
  <!-- Page controls -->
  <nav class="flex items-center gap-1">
    <button class="px-2.5 py-1.5 text-sm text-gray-500 hover:bg-gray-200 rounded-lg disabled:opacity-50 disabled:cursor-not-allowed">
      <i class="fas fa-chevron-left w-3 h-3"></i>
    </button>
    <button class="px-3 py-1.5 text-sm font-medium text-white bg-primary-600 rounded-lg">1</button>
    <button class="px-3 py-1.5 text-sm text-gray-600 hover:bg-gray-200 rounded-lg">2</button>
    <button class="px-3 py-1.5 text-sm text-gray-600 hover:bg-gray-200 rounded-lg">3</button>
    <span class="px-2 text-gray-400">...</span>
    <button class="px-3 py-1.5 text-sm text-gray-600 hover:bg-gray-200 rounded-lg">10</button>
    <button class="px-2.5 py-1.5 text-sm text-gray-500 hover:bg-gray-200 rounded-lg">
      <i class="fas fa-chevron-right w-3 h-3"></i>
    </button>
  </nav>
</div>
```

---

## Status Badges

### Standard Badge Pattern
```html
<span class="inline-flex items-center gap-1 px-2 py-0.5 text-xs font-medium rounded-full ring-1 ring-inset {color-classes}">
  <span class="w-1.5 h-1.5 rounded-full bg-current"></span>
  Label
</span>
```

### Color Mappings

| Status | Ring + Text + BG |
|--------|-----------------|
| Active / Published / Paid | `bg-emerald-50 text-emerald-700 ring-emerald-600/20` |
| Pending / Draft / Processing | `bg-amber-50 text-amber-700 ring-amber-600/20` |
| Failed / Error / Cancelled | `bg-red-50 text-red-700 ring-red-600/20` |
| Info / New / Syncing | `bg-blue-50 text-blue-700 ring-blue-600/20` |
| Inactive / Archived / Disabled | `bg-gray-50 text-gray-600 ring-gray-500/20` |
| Featured / Premium | `bg-purple-50 text-purple-700 ring-purple-600/20` |

### Without Dot Indicator
For simple labels (categories, tags):
```html
<span class="inline-flex items-center px-2 py-0.5 text-xs font-medium rounded-full bg-gray-100 text-gray-700">
  Category
</span>
```

---

## Progress Indicators

### Progress Bar
```html
<div class="space-y-1">
  <div class="flex items-center justify-between text-xs">
    <span class="font-medium text-gray-700">Progress</span>
    <span class="text-gray-500 tabular-nums">75%</span>
  </div>
  <div class="h-2 bg-gray-200 rounded-full overflow-hidden">
    <div class="h-full bg-primary-500 rounded-full transition-all duration-300 ease-out" style="width: 75%"></div>
  </div>
</div>
```

### Step Progress
```html
<div class="flex items-center gap-2">
  <!-- Completed step -->
  <div class="w-8 h-8 rounded-full bg-primary-600 text-white flex items-center justify-center text-xs font-bold">
    <i class="fas fa-check w-3 h-3"></i>
  </div>
  <!-- Connector -->
  <div class="flex-1 h-0.5 bg-primary-600"></div>
  <!-- Active step -->
  <div class="w-8 h-8 rounded-full border-2 border-primary-600 text-primary-600 flex items-center justify-center text-xs font-bold">
    2
  </div>
  <!-- Connector -->
  <div class="flex-1 h-0.5 bg-gray-200"></div>
  <!-- Upcoming step -->
  <div class="w-8 h-8 rounded-full border-2 border-gray-300 text-gray-400 flex items-center justify-center text-xs font-bold">
    3
  </div>
</div>
```

---

## Activity Feeds

### Timeline Pattern
```html
<div class="flow-root">
  <ul class="-mb-8">
    <li class="relative pb-8">
      <!-- Connector line (hide on last item) -->
      <span class="absolute left-4 top-8 -ml-px h-full w-0.5 bg-gray-200"></span>
      <div class="relative flex items-start gap-3">
        <!-- Type icon -->
        <div class="flex-shrink-0 w-8 h-8 rounded-full bg-blue-100 flex items-center justify-center">
          <i class="fas fa-plus w-3 h-3 text-blue-600"></i>
        </div>
        <!-- Content -->
        <div class="flex-1 min-w-0">
          <p class="text-sm text-gray-900">
            <span class="font-medium">Jane Doe</span> created product <span class="font-medium">Widget Pro</span>
          </p>
          <p class="text-xs text-gray-500 mt-0.5">2 hours ago</p>
        </div>
      </div>
    </li>
    <!-- more items... last item: remove the connector <span> -->
  </ul>
</div>
```

### Event Type Icons
| Event | Icon | Container Color |
|-------|------|----------------|
| Created | `fa-plus` | `bg-blue-100 text-blue-600` |
| Updated | `fa-pencil` | `bg-amber-100 text-amber-600` |
| Deleted | `fa-trash` | `bg-red-100 text-red-600` |
| Comment | `fa-comment` | `bg-gray-100 text-gray-600` |
| Status change | `fa-arrow-right` | `bg-purple-100 text-purple-600` |
| System | `fa-gear` | `bg-gray-100 text-gray-500` |

---

## Empty States for Data Views

### Table Empty State
Replace `<tbody>` content:
```html
<tr>
  <td colspan="999" class="px-5 py-12 text-center">
    <div class="flex flex-col items-center">
      <div class="p-3 rounded-xl bg-gray-50 mb-3">
        <i class="fas fa-inbox w-8 h-8 text-gray-300"></i>
      </div>
      <p class="text-sm font-medium text-gray-900">No products yet</p>
      <p class="text-xs text-gray-500 mt-1 max-w-sm">Get started by adding your first product to the catalog.</p>
      <button class="mt-4 px-4 py-2 text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 rounded-lg shadow-sm">
        <i class="fas fa-plus w-3.5 h-3.5 mr-1.5"></i>Add Product
      </button>
    </div>
  </td>
</tr>
```

### Filter No Results
```html
<tr>
  <td colspan="999" class="px-5 py-12 text-center">
    <div class="flex flex-col items-center">
      <i class="fas fa-search w-8 h-8 text-gray-300 mb-3"></i>
      <p class="text-sm font-medium text-gray-900">No results found</p>
      <p class="text-xs text-gray-500 mt-1">Try adjusting your search or filter criteria.</p>
      <button class="mt-3 text-sm font-medium text-primary-600 hover:text-primary-700" @click="clearFilters">
        Clear all filters
      </button>
    </div>
  </td>
</tr>
```

---

## View Toggle (List / Grid)

Allow users to switch between table and card grid layouts. See `ViewToggle.vue` for the existing component.

### Toggle Button Group
```html
<div class="flex items-center bg-gray-100 dark:bg-gray-700 rounded-lg p-1">
  <button
    :class="[currentView === 'list' ? 'bg-white dark:bg-gray-600 text-primary-600 shadow-sm' : 'text-gray-500 hover:text-gray-700']"
    class="p-1.5 rounded transition-colors duration-150"
    title="List view"
    @click="$emit('change', 'list')">
    <svg class="w-4 h-4" ...><!-- list icon --></svg>
  </button>
  <button
    :class="[currentView === 'grid' ? 'bg-white dark:bg-gray-600 text-primary-600 shadow-sm' : 'text-gray-500 hover:text-gray-700']"
    class="p-1.5 rounded transition-colors duration-150"
    title="Grid view"
    @click="$emit('change', 'grid')">
    <svg class="w-4 h-4" ...><!-- grid icon --></svg>
  </button>
</div>
```

### Placement
Place in the filter bar / action toolbar, right-aligned alongside sort and action buttons. The `ActionToolbar.vue` component composes this automatically.

---

## Product Card (Grid View)

When the view toggle is set to grid, render products as cards instead of table rows.

### Card Anatomy
```html
<div class="bg-white border border-gray-200 shadow-sm rounded-xl overflow-hidden hover:border-gray-300 transition-colors duration-200 group">
  <!-- Image -->
  <div class="aspect-square bg-gray-100 relative overflow-hidden">
    <img :src="product.image" :alt="product.name" class="w-full h-full object-cover" />
    <!-- Status badge overlay (top-right) -->
    <span class="absolute top-2 right-2 inline-flex items-center px-2 py-0.5 text-xs font-medium rounded-full bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-600/20">
      Active
    </span>
    <!-- Hover actions overlay -->
    <div class="absolute inset-0 bg-black/0 group-hover:bg-black/10 transition-colors duration-200 flex items-end justify-center pb-3 opacity-0 group-hover:opacity-100">
      <div class="flex items-center gap-1.5">
        <button class="p-2 bg-white rounded-lg shadow-sm text-gray-600 hover:text-primary-600">
          <i class="fas fa-pencil w-3.5 h-3.5"></i>
        </button>
        <button class="p-2 bg-white rounded-lg shadow-sm text-gray-600 hover:text-primary-600">
          <i class="fas fa-eye w-3.5 h-3.5"></i>
        </button>
      </div>
    </div>
  </div>
  <!-- Content -->
  <div class="p-4 space-y-2">
    <h4 class="text-sm font-medium text-gray-900 truncate" :title="product.name">{{ product.name }}</h4>
    <p class="text-xs text-gray-500">{{ product.sku }}</p>
    <div class="flex items-center justify-between">
      <span class="text-sm font-semibold text-gray-900 tabular-nums">${{ product.price }}</span>
      <span class="text-xs text-gray-500 tabular-nums">{{ product.quantity }} in stock</span>
    </div>
  </div>
</div>
```

### Grid Layout
```html
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
  <!-- product cards -->
</div>
```

### Checkbox Selection (for bulk actions)
Add a checkbox in the top-left corner of the image area:
```html
<div class="absolute top-2 left-2 opacity-0 group-hover:opacity-100 transition-opacity" :class="{ 'opacity-100': selected }">
  <input type="checkbox" :checked="selected" @change="toggleSelect"
    class="rounded border-gray-300 text-primary-600 focus:ring-primary-500/20 shadow-sm" />
</div>
```

---

## Bulk Operations Flow

### Selection States

**Select all / partial selection in table header:**
```html
<th class="w-10 px-5 py-3">
  <input type="checkbox"
    :checked="allSelected"
    :indeterminate="someSelected && !allSelected"
    @change="toggleSelectAll"
    class="rounded border-gray-300 text-primary-600 focus:ring-primary-500/20" />
</th>
```

**Select-all banner (when current page is selected but more exist):**
```html
<div v-if="allOnPageSelected && totalCount > pageSize" class="bg-primary-50 border-b border-primary-100 px-5 py-2 text-center text-sm">
  <span class="text-primary-700">All {{ pageSize }} items on this page are selected.</span>
  <button v-if="!allItemsSelected" @click="selectAll" class="font-medium text-primary-600 hover:text-primary-700 ml-1">
    Select all {{ totalCount }} items
  </button>
  <button v-else @click="clearSelection" class="font-medium text-primary-600 hover:text-primary-700 ml-1">
    Clear selection
  </button>
</div>
```

### Bulk Action Bar
When items are selected, show the action bar (see existing pattern above). For pages with many bulk operations, use a **floating bottom panel** variant:

```html
<Transition
  enter-active-class="transition-all duration-200 ease-out"
  enter-from-class="translate-y-full opacity-0"
  enter-to-class="translate-y-0 opacity-100"
  leave-active-class="transition-all duration-150 ease-in"
  leave-from-class="translate-y-0 opacity-100"
  leave-to-class="translate-y-full opacity-0"
>
  <div v-if="selectedCount > 0" class="fixed bottom-6 left-1/2 -translate-x-1/2 z-40">
    <div class="flex items-center gap-4 bg-gray-900 text-white rounded-xl shadow-2xl px-5 py-3">
      <span class="text-sm font-medium">{{ selectedCount }} selected</span>
      <div class="w-px h-5 bg-gray-700"></div>
      <button class="text-sm font-medium hover:text-primary-300 transition-colors">Export</button>
      <button class="text-sm font-medium hover:text-primary-300 transition-colors">Edit</button>
      <button class="text-sm font-medium text-red-400 hover:text-red-300 transition-colors">Delete</button>
      <div class="w-px h-5 bg-gray-700"></div>
      <button @click="clearSelection" class="p-1 text-gray-400 hover:text-white">
        <i class="fas fa-times w-4 h-4"></i>
      </button>
    </div>
  </div>
</Transition>
```

### Batch Progress Tracking
For long-running bulk operations, show a progress modal or inline tracker:
```html
<div class="bg-white border border-gray-200 shadow-sm rounded-xl p-5 space-y-4">
  <div class="flex items-center justify-between">
    <h4 class="text-sm font-semibold text-gray-900">Bulk Export</h4>
    <span class="text-xs text-gray-500 tabular-nums">{{ completed }}/{{ total }}</span>
  </div>
  <div class="h-2 bg-gray-200 rounded-full overflow-hidden">
    <div class="h-full bg-primary-500 rounded-full transition-all duration-300" :style="{ width: `${percent}%` }"></div>
  </div>
  <div v-if="errors.length" class="text-xs text-red-600">
    <i class="fas fa-circle-exclamation w-3 h-3 mr-1"></i>{{ errors.length }} failed
  </div>
</div>
```

## Audit Logging

Presentation-only skill -- no audit writes from UI primitives. Activity feed and entity-timeline components in this skill RENDER `user_activity_logs` rows (via the Admin Audit Hub or per-entity drawers); they do not produce them.

Spec: `docs/AUDIT_SYSTEM.md`. When a UI component is wired to a controller mutation, the controller (not the component) is responsible for emitting the audit row.
