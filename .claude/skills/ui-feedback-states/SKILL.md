---
name: ui-feedback-states
description: Skeleton loading screens, empty states, error states, success feedback, page transitions, list animations, confirmation dialogs, and micro-interactions
user-invocable: true
---

# UI Feedback & States

Reference the **ui-design-system** skill for all color tokens, typography, spacing, and border/shadow standards.

---

## Loading States

### Skeleton Screens (Primary Pattern)
Use skeleton screens instead of spinners for page and section loads. Match the shape and layout of the content being loaded.

**Skeleton building blocks:**
```html
<!-- Text line -->
<div class="h-4 w-3/4 bg-gray-200 rounded animate-pulse"></div>

<!-- Short text -->
<div class="h-3 w-1/2 bg-gray-200 rounded animate-pulse"></div>

<!-- Circle (avatar) -->
<div class="w-10 h-10 bg-gray-200 rounded-full animate-pulse"></div>

<!-- Rectangle (image) -->
<div class="w-16 h-16 bg-gray-200 rounded-lg animate-pulse"></div>

<!-- Badge -->
<div class="h-5 w-16 bg-gray-200 rounded-full animate-pulse"></div>
```

**Stat card skeleton:**
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

**Table row skeleton:**
```html
<tr class="animate-pulse">
  <td class="px-5 py-3.5"><div class="h-4 w-32 bg-gray-200 rounded"></div></td>
  <td class="px-5 py-3.5"><div class="h-4 w-20 bg-gray-200 rounded"></div></td>
  <td class="px-5 py-3.5"><div class="h-4 w-16 bg-gray-200 rounded"></div></td>
  <td class="px-5 py-3.5"><div class="h-5 w-16 bg-gray-200 rounded-full"></div></td>
</tr>
```
Render 5-8 skeleton rows to simulate a loaded table.

**Card grid skeleton:**
```html
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
  <div v-for="i in 6" :key="i" class="bg-white border border-gray-200 shadow-sm rounded-xl p-5 animate-pulse">
    <div class="h-40 bg-gray-200 rounded-lg mb-4"></div>
    <div class="space-y-2">
      <div class="h-4 w-3/4 bg-gray-200 rounded"></div>
      <div class="h-3 w-1/2 bg-gray-200 rounded"></div>
    </div>
  </div>
</div>
```

### Button Loading State
```html
<button disabled class="px-4 py-2 text-sm font-medium text-white bg-primary-600 rounded-lg shadow-sm opacity-75 pointer-events-none">
  <i class="fas fa-spinner fa-spin w-4 h-4 mr-1.5"></i>
  Saving...
</button>
```

Rules:
- Replace the button's leading icon with `fa-spinner fa-spin`
- Add `opacity-75 pointer-events-none`
- Optionally change text to indicate action ("Save" -> "Saving...")
- Never disable the entire form -- only disable the submit button

### Inline Spinner
For small inline loading indicators (e.g., fetching a dropdown's options):
```html
<div class="flex items-center gap-2 text-sm text-gray-500">
  <i class="fas fa-spinner fa-spin w-4 h-4"></i>
  <span>Loading options...</span>
</div>
```

### Full Page Loading
For initial page loads before any content is ready:
```html
<div class="flex items-center justify-center min-h-[400px]">
  <div class="flex flex-col items-center gap-3">
    <i class="fas fa-spinner fa-spin w-6 h-6 text-primary-500"></i>
    <p class="text-sm text-gray-500">Loading...</p>
  </div>
</div>
```

---

## Empty States

### Standard Empty State
```html
<div class="flex flex-col items-center justify-center py-12 px-4 text-center">
  <div class="p-3 rounded-xl bg-gray-50 mb-4">
    <i class="fas fa-inbox w-10 h-10 text-gray-300"></i>
  </div>
  <h3 class="text-base font-semibold text-gray-900">No products yet</h3>
  <p class="text-sm text-gray-500 mt-1 max-w-sm">Get started by adding your first product to the catalog.</p>
  <button class="mt-5 px-4 py-2 text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 rounded-lg shadow-sm">
    <i class="fas fa-plus w-3.5 h-3.5 mr-1.5"></i>Add Product
  </button>
</div>
```

### Filter No Results
```html
<div class="flex flex-col items-center justify-center py-12 px-4 text-center">
  <i class="fas fa-search w-10 h-10 text-gray-300 mb-4"></i>
  <h3 class="text-base font-semibold text-gray-900">No results found</h3>
  <p class="text-sm text-gray-500 mt-1 max-w-sm">Try adjusting your search or filter criteria.</p>
  <button class="mt-4 text-sm font-medium text-primary-600 hover:text-primary-700" @click="clearFilters">
    Clear all filters
  </button>
</div>
```

### Permission-Based Empty State
When the user lacks permission to see or create items:
```html
<div class="flex flex-col items-center justify-center py-12 px-4 text-center">
  <div class="p-3 rounded-xl bg-gray-50 mb-4">
    <i class="fas fa-lock w-10 h-10 text-gray-300"></i>
  </div>
  <h3 class="text-base font-semibold text-gray-900">Access restricted</h3>
  <p class="text-sm text-gray-500 mt-1 max-w-sm">You don't have permission to view this content. Contact your administrator.</p>
</div>
```

### Empty State Icons by Domain
| Domain | Icon |
|--------|------|
| Products | `fa-box-open` |
| Orders | `fa-receipt` |
| Inventory | `fa-warehouse` |
| Suppliers | `fa-truck` |
| Listings | `fa-store` |
| Activity/History | `fa-clock-rotate-left` |
| Search results | `fa-search` |
| Notifications | `fa-bell-slash` |
| Generic | `fa-inbox` |

---

## Error States

### Inline Field Error
Shown below the input (see ui-forms-inputs for full field group pattern):
```html
<p class="text-xs text-red-600 flex items-center gap-1 mt-1.5">
  <i class="fas fa-circle-exclamation w-3 h-3 flex-shrink-0"></i>
  This field is required.
</p>
```

### Form-Level Error Banner
Shown at the top of a form when submission fails:
```html
<div class="rounded-lg bg-red-50 border border-red-200 p-4">
  <div class="flex gap-3">
    <i class="fas fa-circle-exclamation w-5 h-5 text-red-500 flex-shrink-0 mt-0.5"></i>
    <div>
      <h3 class="text-sm font-medium text-red-800">There were errors with your submission</h3>
      <ul class="mt-2 text-sm text-red-700 list-disc list-inside space-y-1">
        <li>Product name is required.</li>
        <li>Price must be greater than 0.</li>
      </ul>
    </div>
  </div>
</div>
```

### Page-Level Error
For when an entire page fails to load:
```html
<div class="flex flex-col items-center justify-center py-16 px-4 text-center">
  <div class="p-3 rounded-xl bg-red-50 mb-4">
    <i class="fas fa-triangle-exclamation w-10 h-10 text-red-400"></i>
  </div>
  <h3 class="text-base font-semibold text-gray-900">Something went wrong</h3>
  <p class="text-sm text-gray-500 mt-1 max-w-sm">We couldn't load this page. Please try again.</p>
  <button class="mt-5 px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 hover:bg-gray-50 rounded-lg shadow-sm" @click="reload">
    <i class="fas fa-rotate-right w-3.5 h-3.5 mr-1.5"></i>Try Again
  </button>
</div>
```

### Network Error Toast
```html
<div class="fixed bottom-4 right-4 z-50 max-w-sm w-full bg-white border border-gray-200 shadow-xl rounded-xl p-4">
  <div class="flex gap-3">
    <div class="p-1.5 rounded-lg bg-red-100">
      <i class="fas fa-wifi w-4 h-4 text-red-600"></i>
    </div>
    <div class="flex-1">
      <p class="text-sm font-medium text-gray-900">Connection lost</p>
      <p class="text-xs text-gray-500 mt-0.5">Changes will be saved when you're back online.</p>
    </div>
    <button class="text-gray-400 hover:text-gray-600 self-start">
      <i class="fas fa-times w-4 h-4"></i>
    </button>
  </div>
</div>
```

### Partial Load Failure
When one section of a page fails but others succeed:
```html
<div class="bg-white border border-gray-200 shadow-sm rounded-xl p-5">
  <div class="flex flex-col items-center py-6 text-center">
    <i class="fas fa-circle-exclamation w-6 h-6 text-gray-300 mb-2"></i>
    <p class="text-sm text-gray-500">Failed to load this section.</p>
    <button class="mt-2 text-xs font-medium text-primary-600 hover:text-primary-700" @click="retrySection">
      Retry
    </button>
  </div>
</div>
```

---

## Success Feedback

### Toast Notification
```html
<div class="fixed bottom-4 right-4 z-50 max-w-sm w-full bg-white border border-gray-200 shadow-xl rounded-xl p-4 animate-slide-up">
  <div class="flex gap-3">
    <div class="p-1.5 rounded-lg bg-emerald-100">
      <i class="fas fa-check w-4 h-4 text-emerald-600"></i>
    </div>
    <div class="flex-1">
      <p class="text-sm font-medium text-gray-900">Product saved</p>
      <p class="text-xs text-gray-500 mt-0.5">Changes have been saved successfully.</p>
    </div>
    <button class="text-gray-400 hover:text-gray-600 self-start">
      <i class="fas fa-times w-4 h-4"></i>
    </button>
  </div>
</div>
```

Auto-dismiss after 5 seconds. Stack multiple toasts vertically with `gap-3`.

### Inline Success Check
After saving an inline edit:
```html
<Transition
  enter-active-class="transition-opacity duration-300"
  enter-from-class="opacity-0"
  leave-active-class="transition-opacity duration-300 delay-1000"
  leave-from-class="opacity-100"
  leave-to-class="opacity-0"
>
  <i v-if="justSaved" class="fas fa-check w-4 h-4 text-emerald-500"></i>
</Transition>
```

### Flash on Saved Element
Briefly highlight a saved row or card:
```css
@keyframes flash-save {
  0% { background-color: rgb(236 253 245); } /* emerald-50 */
  100% { background-color: transparent; }
}
.flash-save {
  animation: flash-save 1.5s ease-out;
}
```

---

## Transitions

### Inertia Page Transitions
```javascript
// In app.js or layout
import { router } from '@inertiajs/vue3'

// Add to layout
const pageTransition = ref('slide-up')
```

```html
<Transition
  enter-active-class="transition-all duration-200 ease-out"
  enter-from-class="opacity-0 translate-y-2"
  enter-to-class="opacity-100 translate-y-0"
  leave-active-class="transition-all duration-150 ease-in"
  leave-from-class="opacity-100"
  leave-to-class="opacity-0"
  mode="out-in"
>
  <slot />
</Transition>
```

### List Add/Remove (TransitionGroup)
```html
<TransitionGroup
  enter-active-class="transition-all duration-200 ease-out"
  enter-from-class="opacity-0 translate-y-2"
  enter-to-class="opacity-100 translate-y-0"
  leave-active-class="transition-all duration-150 ease-in absolute w-full"
  leave-from-class="opacity-100"
  leave-to-class="opacity-0 -translate-y-1"
  move-class="transition-transform duration-200 ease-out"
>
  <div v-for="item in items" :key="item.id">
    <!-- item content -->
  </div>
</TransitionGroup>
```

### Modal Enter/Leave
```html
<!-- Backdrop -->
<Transition
  enter-active-class="transition-opacity duration-200 ease-out"
  enter-from-class="opacity-0"
  enter-to-class="opacity-100"
  leave-active-class="transition-opacity duration-150 ease-in"
  leave-from-class="opacity-100"
  leave-to-class="opacity-0"
>
  <div v-if="open" class="fixed inset-0 bg-gray-900/50 backdrop-blur-sm"></div>
</Transition>

<!-- Panel -->
<Transition
  enter-active-class="transition-all duration-200 ease-out"
  enter-from-class="opacity-0 scale-95"
  enter-to-class="opacity-100 scale-100"
  leave-active-class="transition-all duration-150 ease-in"
  leave-from-class="opacity-100 scale-100"
  leave-to-class="opacity-0 scale-95"
>
  <div v-if="open" class="...modal-panel..."></div>
</Transition>
```

### Accordion / Expand-Collapse
```html
<Transition
  enter-active-class="transition-all duration-200 ease-out overflow-hidden"
  enter-from-class="max-h-0 opacity-0"
  enter-to-class="max-h-screen opacity-100"
  leave-active-class="transition-all duration-150 ease-in overflow-hidden"
  leave-from-class="max-h-screen opacity-100"
  leave-to-class="max-h-0 opacity-0"
>
  <div v-if="expanded">
    <!-- collapsible content -->
  </div>
</Transition>
```

---

## Confirmation Dialogs

### Destructive Confirmation (Delete, etc.)
```html
<div class="fixed inset-0 z-50 flex items-center justify-center p-4">
  <!-- Backdrop -->
  <div class="fixed inset-0 bg-gray-900/50 backdrop-blur-sm"></div>

  <!-- Dialog -->
  <div class="relative bg-white rounded-xl shadow-xl max-w-md w-full p-6">
    <div class="flex flex-col items-center text-center">
      <div class="p-3 rounded-xl bg-red-50 mb-4">
        <i class="fas fa-triangle-exclamation w-8 h-8 text-red-500"></i>
      </div>
      <h3 class="text-lg font-semibold text-gray-900">Delete Product?</h3>
      <p class="text-sm text-gray-500 mt-2">This action cannot be undone. All data associated with this product will be permanently removed.</p>
    </div>
    <div class="flex gap-3 mt-6">
      <button @click="close" class="flex-1 px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 hover:bg-gray-50 rounded-lg shadow-sm">
        Cancel
      </button>
      <button @click="confirm" class="flex-1 px-4 py-2 text-sm font-medium text-white bg-red-600 hover:bg-red-700 rounded-lg shadow-sm">
        Delete
      </button>
    </div>
  </div>
</div>
```

### Typed Confirmation (for high-risk operations)
Add a text input that requires typing the item name:
```html
<div class="mt-4 space-y-2">
  <p class="text-sm text-gray-600">Type <span class="font-mono font-medium text-gray-900">Widget Pro</span> to confirm:</p>
  <input type="text" v-model="confirmText"
    class="block w-full rounded-lg border-gray-300 text-sm focus:border-red-500 focus:ring-4 focus:ring-red-500/20"
    placeholder="Type product name..." />
</div>
<button :disabled="confirmText !== 'Widget Pro'" ...>Delete</button>
```

### Standard Confirmation
```html
<!-- Same structure as destructive, but with blue/primary icon and primary button -->
<div class="p-3 rounded-xl bg-blue-50 mb-4">
  <i class="fas fa-circle-question w-8 h-8 text-blue-500"></i>
</div>
<!-- Primary action button instead of red -->
<button class="... bg-primary-600 hover:bg-primary-700 ...">Confirm</button>
```

### Inline Confirmation
Replace the action button temporarily:
```html
<!-- Before click -->
<button @click="showConfirm = true" class="text-sm text-red-600 hover:text-red-700">Delete</button>

<!-- After click (replaces button) -->
<div v-if="showConfirm" class="flex items-center gap-2">
  <span class="text-sm text-gray-600">Are you sure?</span>
  <button @click="doDelete" class="text-sm font-medium text-red-600 hover:text-red-700">Yes, delete</button>
  <button @click="showConfirm = false" class="text-sm text-gray-500 hover:text-gray-700">Cancel</button>
</div>
```

---

## Alert Banners

### Page-Level Alert (below header, above content)
```html
<!-- Info -->
<div class="rounded-lg bg-blue-50 border border-blue-200 p-4 flex items-start gap-3">
  <i class="fas fa-circle-info w-5 h-5 text-blue-500 flex-shrink-0 mt-0.5"></i>
  <div class="flex-1">
    <p class="text-sm font-medium text-blue-800">New feature available</p>
    <p class="text-sm text-blue-700 mt-0.5">You can now sync inventory across all marketplaces automatically.</p>
  </div>
  <button class="text-blue-400 hover:text-blue-600 flex-shrink-0">
    <i class="fas fa-times w-4 h-4"></i>
  </button>
</div>

<!-- Warning -->
<div class="rounded-lg bg-amber-50 border border-amber-200 p-4 flex items-start gap-3">
  <i class="fas fa-triangle-exclamation w-5 h-5 text-amber-500 flex-shrink-0 mt-0.5"></i>
  <div class="flex-1">
    <p class="text-sm font-medium text-amber-800">eBay token expires soon</p>
    <p class="text-sm text-amber-700 mt-0.5">Re-authorize your eBay account to avoid sync interruptions.</p>
  </div>
  <a href="#" class="text-sm font-medium text-amber-800 hover:text-amber-900 whitespace-nowrap">Re-authorize</a>
</div>

<!-- Error -->
<div class="rounded-lg bg-red-50 border border-red-200 p-4 flex items-start gap-3">
  <i class="fas fa-circle-exclamation w-5 h-5 text-red-500 flex-shrink-0 mt-0.5"></i>
  <div class="flex-1">
    <p class="text-sm font-medium text-red-800">Sync failed</p>
    <p class="text-sm text-red-700 mt-0.5">3 products failed to sync to eBay. Check the error log for details.</p>
  </div>
</div>

<!-- Success -->
<div class="rounded-lg bg-emerald-50 border border-emerald-200 p-4 flex items-start gap-3">
  <i class="fas fa-circle-check w-5 h-5 text-emerald-500 flex-shrink-0 mt-0.5"></i>
  <div class="flex-1">
    <p class="text-sm font-medium text-emerald-800">Import complete</p>
    <p class="text-sm text-emerald-700 mt-0.5">24 products were successfully imported from Shopify.</p>
  </div>
</div>
```

---

## Toast Notification System

The app uses an event-driven toast system (see `NotificationToast.vue`). Toasts are dispatched via `CustomEvent` and rendered via a container that teleports to `<body>`.

### Dispatching a Toast
```javascript
window.dispatchEvent(new CustomEvent('show-notification-toast', {
  detail: {
    type: 'success',       // success | error | warning | info
    title: 'Product saved',
    message: 'Changes have been saved successfully.',
    duration: 5000,        // ms, default 5000
    action: {              // optional
      label: 'View',
      url: '/products/123'
    }
  }
}))
```

### Toast Container
Position: `fixed top-4 right-4 z-50`. Stack multiple toasts vertically with `space-y-2`. Use `pointer-events-none` on container, `pointer-events-auto` on individual toasts.

### Toast Anatomy
```html
<div class="max-w-md bg-white dark:bg-gray-800 rounded-lg shadow-lg border border-gray-200 overflow-hidden pointer-events-auto animate-slide-up">
  <div class="p-4 flex gap-3">
    <!-- Type icon -->
    <div class="p-1.5 rounded-lg" :class="typeClasses">
      <i :class="typeIcon" class="w-4 h-4"></i>
    </div>
    <!-- Content -->
    <div class="flex-1 min-w-0">
      <p class="text-sm font-medium text-gray-900">{{ title }}</p>
      <p v-if="message" class="text-xs text-gray-500 mt-0.5">{{ message }}</p>
      <button v-if="action" @click="router.visit(action.url)" class="text-xs font-medium text-primary-600 hover:text-primary-700 mt-1">
        {{ action.label }}
      </button>
    </div>
    <!-- Dismiss -->
    <button @click="dismiss" class="text-gray-400 hover:text-gray-600 self-start">
      <i class="fas fa-times w-4 h-4"></i>
    </button>
  </div>
  <!-- Auto-dismiss progress bar -->
  <div class="h-1 bg-gray-200">
    <div class="h-full rounded-full transition-all ease-linear" :class="typeBarColor"
      :style="{ width: progressPercent + '%', transitionDuration: duration + 'ms' }"></div>
  </div>
</div>
```

### Type Mappings
| Type | Icon | Container | Progress Bar |
|------|------|-----------|-------------|
| success | `fa-check` | `bg-emerald-100 text-emerald-600` | `bg-emerald-500` |
| error | `fa-circle-exclamation` | `bg-red-100 text-red-600` | `bg-red-500` |
| warning | `fa-triangle-exclamation` | `bg-amber-100 text-amber-600` | `bg-amber-500` |
| info | `fa-circle-info` | `bg-blue-100 text-blue-600` | `bg-blue-500` |

### Auto-Dismiss
- Default duration: 5 seconds
- Progress bar animates from 100% to 0% width
- Pause timer on hover (optional enhancement)
- Remove from DOM after exit transition completes

---

## Real-Time / WebSocket Patterns

For live data updates using Laravel Echo and Pusher. See `WorkflowIndicators.vue` for a working example.

### Polling + Pusher Hybrid
```javascript
// In Vue setup
const data = ref(initialData)
let pollInterval = null

// Primary: Pusher for instant updates
onMounted(() => {
  if (window.Echo) {
    window.Echo.private(`organization.${orgId}`)
      .listen('.DataUpdated', (event) => {
        data.value = event.data
      })
  }

  // Fallback: polling at longer interval
  pollInterval = setInterval(fetchData, 60000)
})

onUnmounted(() => {
  clearInterval(pollInterval)
  if (window.Echo) {
    window.Echo.leave(`organization.${orgId}`)
  }
})
```

### Live Update Animation
When data changes via WebSocket, briefly highlight the changed value:
```html
<span :class="{ 'animate-bounce-in text-primary-600': justUpdated }" class="transition-colors duration-500">
  {{ value }}
</span>
```

### Pulse Indicator for Urgency
For items requiring immediate attention:
```html
<span class="relative inline-flex items-center">
  <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75"></span>
  <span class="relative inline-flex items-center px-2 py-0.5 text-xs font-bold rounded-full bg-red-100 text-red-800">
    {{ urgentCount }}
  </span>
</span>
```

Use `animate-pulse` (slower) for moderate urgency, `animate-ping` for critical.

---

## Workflow / Urgency Indicators

Count badges with urgency-based coloring for workflow navigation items. See `WorkflowIndicators.vue`.

### Urgency Levels
```javascript
function getUrgency(count, thresholds) {
  if (count >= thresholds.high) return 'high'
  if (count >= thresholds.moderate) return 'moderate'
  return 'normal'
}
```

### Urgency Styling
| Level | Badge Classes | Animation |
|-------|--------------|-----------|
| Normal | `bg-gray-100 text-gray-700` | none |
| Moderate | `bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400` | none |
| High | `bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400` | `animate-pulse` |

### Indicator Button Pattern
```html
<button @click="navigate(item)"
  :class="[urgencyClasses(item), item.count === 0 ? 'opacity-50' : '']"
  class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border text-sm font-medium transition-colors">
  <i :class="item.icon" class="w-3.5 h-3.5"></i>
  <span class="hidden sm:inline">{{ item.label }}</span>
  <span class="tabular-nums">{{ item.count }}</span>
</button>
```

## Audit Logging

Presentation-only skill -- no audit writes from UI primitives. Toast/snackbar success states should NOT be confused with audit -- audit is the durable record, toasts are ephemeral feedback.

Spec: `docs/AUDIT_SYSTEM.md`. When a UI component is wired to a controller mutation, the controller (not the component) is responsible for emitting the audit row.
