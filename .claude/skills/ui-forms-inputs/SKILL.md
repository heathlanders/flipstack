---
name: ui-forms-inputs
description: Text inputs, selects, textareas, checkboxes, form layouts, field groups, inline validation, filter bars, wizard/stepper flows, and inline editing
user-invocable: true
---

# UI Forms & Inputs

Reference the **ui-design-system** skill for all color tokens, typography, spacing, and border/shadow standards.

---

## Input Standards

### Base Input
```html
<input type="text"
  class="block w-full rounded-lg border-gray-300 text-sm text-gray-900 placeholder-gray-400
         focus:border-primary-500 focus:ring-4 focus:ring-primary-500/20
         disabled:bg-gray-100 disabled:text-gray-500 disabled:cursor-not-allowed
         dark:bg-gray-800 dark:border-gray-600 dark:text-gray-100 dark:placeholder-gray-500
         dark:focus:border-primary-400 dark:focus:ring-primary-400/20"
  placeholder="Enter value..." />
```

### Input States

| State | Border | Ring | Background | Text |
|-------|--------|------|-----------|------|
| Default | `border-gray-300` | none | `bg-white` | `text-gray-900` |
| Focus | `border-primary-500` | `ring-4 ring-primary-500/20` | `bg-white` | `text-gray-900` |
| Error | `border-red-500` | `ring-4 ring-red-500/20` | `bg-white` | `text-gray-900` |
| Success | `border-emerald-500` | `ring-4 ring-emerald-500/20` | `bg-white` | `text-gray-900` |
| Disabled | `border-gray-200` | none | `bg-gray-100` | `text-gray-500` |
| Readonly | `border-gray-200` | none | `bg-gray-50` | `text-gray-700` |

### Select
```html
<select class="block w-full rounded-lg border-gray-300 text-sm text-gray-900
               focus:border-primary-500 focus:ring-4 focus:ring-primary-500/20">
  <option value="" disabled>Select option...</option>
  <option value="1">Option One</option>
</select>
```

### Textarea
```html
<textarea rows="4"
  class="block w-full rounded-lg border-gray-300 text-sm text-gray-900 placeholder-gray-400
         focus:border-primary-500 focus:ring-4 focus:ring-primary-500/20 resize-y"
  placeholder="Enter description..."></textarea>
```

### Checkbox
```html
<label class="flex items-center gap-2 cursor-pointer">
  <input type="checkbox"
    class="rounded border-gray-300 text-primary-600
           focus:ring-4 focus:ring-primary-500/20 focus:ring-offset-0" />
  <span class="text-sm text-gray-700">Enable feature</span>
</label>
```

### Radio
```html
<label class="flex items-center gap-2 cursor-pointer">
  <input type="radio" name="group"
    class="border-gray-300 text-primary-600
           focus:ring-4 focus:ring-primary-500/20 focus:ring-offset-0" />
  <span class="text-sm text-gray-700">Option A</span>
</label>
```

### Toggle Switch
```html
<button type="button" role="switch" :aria-checked="enabled"
  :class="[enabled ? 'bg-primary-600' : 'bg-gray-200']"
  class="relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-4 focus:ring-primary-500/20"
  @click="enabled = !enabled">
  <span :class="[enabled ? 'translate-x-5' : 'translate-x-0']"
    class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"></span>
</button>
```

---

## Input with Leading Icon

```html
<div class="relative">
  <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
    <i class="fas fa-search w-4 h-4 text-gray-400"></i>
  </div>
  <input type="text" class="block w-full rounded-lg border-gray-300 pl-10 text-sm ..." placeholder="Search..." />
</div>
```

## Input with Trailing Action

```html
<div class="relative">
  <input type="text" class="block w-full rounded-lg border-gray-300 pr-10 text-sm ..." />
  <button class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 hover:text-gray-600">
    <i class="fas fa-times w-4 h-4"></i>
  </button>
</div>
```

## Input with Prefix/Suffix Text

```html
<div class="flex rounded-lg shadow-sm">
  <span class="inline-flex items-center rounded-l-lg border border-r-0 border-gray-300 bg-gray-50 px-3 text-sm text-gray-500">$</span>
  <input type="text" class="block w-full rounded-none rounded-r-lg border-gray-300 text-sm focus:border-primary-500 focus:ring-4 focus:ring-primary-500/20" />
</div>
```

---

## Field Group Pattern

Every form field follows this composable structure:

```html
<div class="space-y-1.5">
  <!-- Label -->
  <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
    Field Name
    <span class="text-red-500 ml-0.5">*</span> <!-- required indicator -->
  </label>

  <!-- Input (any type from above) -->
  <input type="text" class="block w-full rounded-lg border-gray-300 text-sm ..." />

  <!-- Help text (optional, shown when no error) -->
  <p class="text-xs text-gray-500">Brief guidance about this field.</p>

  <!-- Error message (replaces help text when present) -->
  <p class="text-xs text-red-600 flex items-center gap-1">
    <i class="fas fa-circle-exclamation w-3 h-3"></i>
    This field is required.
  </p>
</div>
```

### Error State on the Input
When showing an error, also update the input classes:
```
border-red-500 focus:border-red-500 focus:ring-4 focus:ring-red-500/20
```

### Success State (post-validation)
```
border-emerald-500 focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/20
```

---

## Form Layouts

### Single-Column Card Form
Standard create/edit forms:
```html
<div class="bg-white border border-gray-200 shadow-sm rounded-xl">
  <div class="px-5 py-4 border-b border-gray-100">
    <h3 class="text-base font-semibold text-gray-900">Product Details</h3>
    <p class="text-xs text-gray-500 mt-0.5">Basic information about the product.</p>
  </div>
  <div class="p-5 space-y-5">
    <!-- field groups -->
  </div>
</div>
```

### Two-Column Settings Layout
For settings pages with description + form side by side:
```html
<div class="grid grid-cols-1 lg:grid-cols-[280px_1fr] gap-x-8 gap-y-6">
  <!-- Left: description -->
  <div>
    <h3 class="text-base font-semibold text-gray-900">General Settings</h3>
    <p class="text-sm text-gray-500 mt-1">Configure your organization's basic settings.</p>
  </div>
  <!-- Right: form card -->
  <div class="bg-white border border-gray-200 shadow-sm rounded-xl p-5 space-y-5">
    <!-- field groups -->
  </div>
</div>
```

### Sectioned Form with Dividers
Break long forms into logical groups:
```html
<div class="bg-white border border-gray-200 shadow-sm rounded-xl divide-y divide-gray-100">
  <div class="p-5 space-y-5">
    <h4 class="text-sm font-semibold text-gray-900">Basic Info</h4>
    <!-- fields -->
  </div>
  <div class="p-5 space-y-5">
    <h4 class="text-sm font-semibold text-gray-900">Pricing</h4>
    <!-- fields -->
  </div>
</div>
```

### Field Grid
Place related fields side by side:
```html
<div class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-4">
  <!-- field groups -->
</div>
```

For three fields in a row (e.g., city/state/zip):
```html
<div class="grid grid-cols-1 sm:grid-cols-3 gap-x-4 gap-y-4">
  <!-- field groups -->
</div>
```

---

## Form Actions / Footer

### Standard Form Footer
```html
<div class="flex items-center justify-end gap-3 px-5 py-4 bg-gray-50 border-t border-gray-100 rounded-b-xl">
  <button type="button" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 hover:bg-gray-50 rounded-lg shadow-sm">
    Cancel
  </button>
  <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 rounded-lg shadow-sm">
    Save Changes
  </button>
</div>
```

### Sticky Form Footer
For long forms, pin the actions to the bottom:
```html
<div class="sticky bottom-0 z-10 flex items-center justify-end gap-3 px-5 py-4 bg-white/95 backdrop-blur border-t border-gray-200 rounded-b-xl">
  <!-- same buttons -->
</div>
```

---

## Filter Bar

For list/index pages above tables:

```html
<div class="flex flex-wrap items-end gap-3">
  <!-- Search -->
  <div class="flex-1 min-w-[200px]">
    <div class="relative">
      <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
        <i class="fas fa-search w-4 h-4 text-gray-400"></i>
      </div>
      <input type="text" class="block w-full rounded-lg border-gray-300 pl-10 text-sm ..." placeholder="Search products..." />
    </div>
  </div>

  <!-- Filter select -->
  <select class="rounded-lg border-gray-300 text-sm text-gray-700 focus:border-primary-500 focus:ring-4 focus:ring-primary-500/20">
    <option value="">All Statuses</option>
    <option>Active</option>
    <option>Draft</option>
  </select>

  <!-- Active filter count + clear -->
  <div v-if="activeFilterCount > 0" class="flex items-center gap-2">
    <span class="inline-flex items-center px-2 py-0.5 text-xs font-medium rounded-full bg-primary-100 text-primary-700">
      {{ activeFilterCount }} active
    </span>
    <button class="text-xs font-medium text-gray-500 hover:text-gray-700" @click="clearFilters">
      Clear all
    </button>
  </div>
</div>
```

---

## Wizard / Stepper Flow

### Step Indicator (Horizontal)
```html
<nav class="flex items-center justify-center">
  <ol class="flex items-center gap-0">
    <!-- Completed step -->
    <li class="flex items-center">
      <div class="flex items-center gap-2">
        <div class="w-8 h-8 rounded-full bg-primary-600 text-white flex items-center justify-center">
          <i class="fas fa-check w-3.5 h-3.5"></i>
        </div>
        <span class="text-sm font-medium text-primary-600 hidden sm:inline">Details</span>
      </div>
      <div class="w-12 sm:w-20 h-0.5 bg-primary-600 mx-2"></div>
    </li>

    <!-- Active step -->
    <li class="flex items-center">
      <div class="flex items-center gap-2">
        <div class="w-8 h-8 rounded-full border-2 border-primary-600 text-primary-600 flex items-center justify-center text-sm font-bold">
          2
        </div>
        <span class="text-sm font-medium text-primary-600 hidden sm:inline">Pricing</span>
      </div>
      <div class="w-12 sm:w-20 h-0.5 bg-gray-200 mx-2"></div>
    </li>

    <!-- Upcoming step -->
    <li class="flex items-center">
      <div class="flex items-center gap-2">
        <div class="w-8 h-8 rounded-full border-2 border-gray-300 text-gray-400 flex items-center justify-center text-sm font-bold">
          3
        </div>
        <span class="text-sm font-medium text-gray-400 hidden sm:inline">Review</span>
      </div>
    </li>
  </ol>
</nav>
```

### Wizard Content Area
```html
<div class="min-h-[300px]">
  <!-- Step content renders here. Use min-height to prevent layout jumping. -->
  <transition name="slide-fade" mode="out-in">
    <component :is="currentStepComponent" />
  </transition>
</div>
```

### Wizard Footer
```html
<div class="flex items-center justify-between pt-5 border-t border-gray-200">
  <button v-if="currentStep > 1" @click="prevStep"
    class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 hover:bg-gray-50 rounded-lg shadow-sm">
    <i class="fas fa-arrow-left w-3.5 h-3.5 mr-1.5"></i>Back
  </button>
  <div v-else></div>
  <button @click="nextStep"
    class="px-4 py-2 text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 rounded-lg shadow-sm">
    {{ isLastStep ? 'Submit' : 'Continue' }}
    <i v-if="!isLastStep" class="fas fa-arrow-right w-3.5 h-3.5 ml-1.5"></i>
  </button>
</div>
```

---

## Inline Editing

### Click-to-Edit Pattern
```html
<!-- Display mode -->
<div v-if="!editing" @click="startEditing" class="group cursor-pointer flex items-center gap-2 py-1 px-2 -mx-2 rounded-lg hover:bg-gray-100">
  <span class="text-sm text-gray-900">{{ value }}</span>
  <i class="fas fa-pencil w-3 h-3 text-gray-400 opacity-0 group-hover:opacity-100 transition-opacity"></i>
</div>

<!-- Edit mode -->
<div v-else class="flex items-center gap-2">
  <input type="text" v-model="editValue" ref="editInput"
    class="block w-full rounded-lg border-gray-300 text-sm focus:border-primary-500 focus:ring-4 focus:ring-primary-500/20"
    @keydown.enter="save" @keydown.escape="cancel" @blur="save" />
</div>
```

### Rules
- Focus the input immediately on entering edit mode (`nextTick` + `$refs.editInput.focus()`)
- Save on blur and Enter
- Cancel on Escape (revert to original value)
- Show a brief success indicator after saving (green check that fades)

---

## File Upload

### Drop Zone
```html
<div class="border-2 border-dashed border-gray-300 rounded-xl p-6 text-center hover:border-primary-400 hover:bg-primary-50/30 transition-colors duration-200 cursor-pointer"
  :class="{ 'border-primary-500 bg-primary-50/50': isDragging }">
  <div class="flex flex-col items-center">
    <div class="p-3 rounded-xl bg-gray-50 mb-3">
      <i class="fas fa-cloud-arrow-up w-8 h-8 text-gray-400"></i>
    </div>
    <p class="text-sm font-medium text-gray-900">Drop files here or click to browse</p>
    <p class="text-xs text-gray-500 mt-1">PNG, JPG, WebP up to 10MB</p>
  </div>
</div>
```

### File List Item
```html
<div class="flex items-center gap-3 p-3 bg-gray-50 rounded-lg">
  <i class="fas fa-file-image w-5 h-5 text-gray-400"></i>
  <div class="flex-1 min-w-0">
    <p class="text-sm font-medium text-gray-900 truncate">product-photo.jpg</p>
    <p class="text-xs text-gray-500">2.4 MB</p>
  </div>
  <button class="p-1 text-gray-400 hover:text-red-500">
    <i class="fas fa-times w-4 h-4"></i>
  </button>
</div>
```

---

## Advanced Filter Patterns

The basic filter bar above handles simple search + select. For pages with many filterable dimensions, use the **FilterPopover** pattern (see `FilterPopover.vue`).

### Filter Popover

A button that opens a popover panel with multiple filter types:

```html
<!-- Trigger button -->
<button @click="showFilters = !showFilters"
  class="inline-flex items-center gap-2 px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 hover:bg-gray-50 rounded-lg shadow-sm">
  <i class="fas fa-filter w-3.5 h-3.5 text-gray-400"></i>
  Filters
  <span v-if="activeFilterCount" class="inline-flex items-center justify-center w-5 h-5 text-[10px] font-bold rounded-full bg-primary-100 text-primary-700">
    {{ activeFilterCount }}
  </span>
</button>

<!-- Popover panel -->
<div v-if="showFilters" class="absolute z-40 mt-2 w-80 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl shadow-xl">
  <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between">
    <h4 class="text-sm font-semibold text-gray-900">Filters</h4>
    <button v-if="hasActiveFilters" @click="clearAll" class="text-xs font-medium text-gray-500 hover:text-gray-700">Clear all</button>
  </div>
  <div class="p-4 space-y-4 max-h-96 overflow-y-auto">
    <!-- Filter items by type (see below) -->
  </div>
  <div class="px-4 py-3 border-t border-gray-100 flex justify-end">
    <button @click="apply" class="px-3 py-1.5 text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 rounded-lg">Apply</button>
  </div>
</div>
```

### Filter Input Types

**Select filter:**
```html
<div class="space-y-1.5">
  <label class="block text-xs font-medium text-gray-700">Status</label>
  <select class="block w-full rounded-lg border-gray-300 text-sm">
    <option value="">All</option>
    <option>Active</option>
    <option>Draft</option>
  </select>
</div>
```

**Multiselect filter (checkbox list):**
```html
<div class="space-y-1.5">
  <label class="block text-xs font-medium text-gray-700">Categories</label>
  <div class="space-y-1 max-h-40 overflow-y-auto">
    <label v-for="option in options" class="flex items-center gap-2 py-1 cursor-pointer">
      <input type="checkbox" :value="option.value" v-model="selected"
        class="rounded border-gray-300 text-primary-600 focus:ring-primary-500/20" />
      <span class="text-sm text-gray-700 flex-1">{{ option.label }}</span>
      <span class="text-xs text-gray-400 tabular-nums">{{ option.count }}</span>
    </label>
  </div>
</div>
```

**Range filter (min/max):**
```html
<div class="space-y-1.5">
  <label class="block text-xs font-medium text-gray-700">Price Range</label>
  <div class="grid grid-cols-2 gap-2">
    <input type="number" v-model="min" placeholder="Min" class="block w-full rounded-lg border-gray-300 text-sm" />
    <input type="number" v-model="max" placeholder="Max" class="block w-full rounded-lg border-gray-300 text-sm" />
  </div>
</div>
```

**Date range filter:**
```html
<div class="space-y-1.5">
  <label class="block text-xs font-medium text-gray-700">Date Range</label>
  <div class="grid grid-cols-2 gap-2">
    <input type="date" v-model="from" class="block w-full rounded-lg border-gray-300 text-sm" />
    <input type="date" v-model="to" class="block w-full rounded-lg border-gray-300 text-sm" />
  </div>
</div>
```

**Boolean filter (radio):**
```html
<div class="space-y-1.5">
  <label class="block text-xs font-medium text-gray-700">In Stock</label>
  <div class="flex items-center gap-4">
    <label class="flex items-center gap-1.5 cursor-pointer">
      <input type="radio" :value="null" v-model="value" class="border-gray-300 text-primary-600" />
      <span class="text-sm text-gray-700">All</span>
    </label>
    <label class="flex items-center gap-1.5 cursor-pointer">
      <input type="radio" :value="true" v-model="value" class="border-gray-300 text-primary-600" />
      <span class="text-sm text-gray-700">Yes</span>
    </label>
    <label class="flex items-center gap-1.5 cursor-pointer">
      <input type="radio" :value="false" v-model="value" class="border-gray-300 text-primary-600" />
      <span class="text-sm text-gray-700">No</span>
    </label>
  </div>
</div>
```

---

## Date Range Picker

For date-based filtering with preset ranges:

```html
<div class="relative">
  <button @click="open = !open"
    class="inline-flex items-center gap-2 px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 hover:bg-gray-50 rounded-lg shadow-sm">
    <i class="fas fa-calendar w-3.5 h-3.5 text-gray-400"></i>
    <span>{{ displayLabel }}</span>
    <i class="fas fa-chevron-down w-3 h-3 text-gray-400"></i>
  </button>

  <div v-if="open" class="absolute z-40 mt-2 w-72 bg-white border border-gray-200 rounded-xl shadow-xl">
    <!-- Presets -->
    <div class="p-2 border-b border-gray-100">
      <button v-for="preset in presets" :key="preset.key"
        @click="applyPreset(preset)"
        :class="[activePreset === preset.key ? 'bg-primary-50 text-primary-700' : 'text-gray-700 hover:bg-gray-50']"
        class="block w-full text-left px-3 py-1.5 text-sm rounded-lg transition-colors">
        {{ preset.label }}
      </button>
    </div>
    <!-- Custom range -->
    <div class="p-3 space-y-3">
      <div class="grid grid-cols-2 gap-2">
        <div class="space-y-1">
          <label class="text-xs font-medium text-gray-500">From</label>
          <input type="date" v-model="from" class="block w-full rounded-lg border-gray-300 text-sm" />
        </div>
        <div class="space-y-1">
          <label class="text-xs font-medium text-gray-500">To</label>
          <input type="date" v-model="to" class="block w-full rounded-lg border-gray-300 text-sm" />
        </div>
      </div>
      <button @click="applyCustom" class="w-full px-3 py-1.5 text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 rounded-lg">
        Apply
      </button>
    </div>
  </div>
</div>
```

### Standard Presets
```javascript
const presets = [
  { key: 'today', label: 'Today' },
  { key: '7d', label: 'Last 7 days' },
  { key: '30d', label: 'Last 30 days' },
  { key: '90d', label: 'Last 90 days' },
  { key: 'ytd', label: 'Year to date' },
  { key: 'all', label: 'All time' },
]
```

---

## Import / Field Mapping Wizard

For CSV import flows where users map source columns to system fields:

### Step 1: Upload
Use the standard drop zone pattern from File Upload section above.

### Step 2: Field Mapping
```html
<div class="space-y-3">
  <div class="grid grid-cols-[1fr_auto_1fr] gap-3 items-center text-xs font-medium text-gray-500 uppercase tracking-wider px-1">
    <span>CSV Column</span>
    <span></span>
    <span>System Field</span>
  </div>
  <div v-for="col in csvColumns" :key="col" class="grid grid-cols-[1fr_auto_1fr] gap-3 items-center">
    <!-- Source column (read-only) -->
    <div class="px-3 py-2 bg-gray-50 rounded-lg text-sm text-gray-700 truncate">{{ col }}</div>
    <!-- Arrow -->
    <i class="fas fa-arrow-right w-4 h-4 text-gray-300"></i>
    <!-- Target field selector -->
    <select v-model="mapping[col]" class="block w-full rounded-lg border-gray-300 text-sm">
      <option value="">Skip this column</option>
      <option v-for="field in systemFields" :value="field.key">{{ field.label }}</option>
    </select>
  </div>
</div>
```

### Step 3: Preview & Confirm
Show a preview table with the first 3-5 rows mapped to system fields, then a summary of how many rows will be imported. Use the wizard footer pattern (Back / Import N Items).

## Audit Logging

Presentation-only skill -- no audit writes from UI primitives. Forms submit to controllers; the controller emits the audit row when the mutation completes successfully. Do not emit audit from client-side form events.

Spec: `docs/AUDIT_SYSTEM.md`. When a UI component is wired to a controller mutation, the controller (not the component) is responsible for emitting the audit row.
