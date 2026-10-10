---
name: ui-design-system
description: Core design tokens, color system, typography scale, spacing, borders, shadows, transitions, icons, dark mode, and responsive rules -- foundation for all UI skills
user-invocable: true
---

# UI Design System -- Foundation Design Tokens

This skill defines the canonical design tokens for the entire application. All other UI skills reference these tokens. When building or modifying any UI component, follow these standards exactly.

---

## Color System

### Primary Palette
Use `primary-*` (configured in tailwind.config.js) for all brand actions. **Never use raw `indigo-*`** -- always use `primary-*` so theming works.

| Token | Usage |
|-------|-------|
| `primary-50` | Active nav background, selected row, subtle highlight |
| `primary-100` | Icon containers, hover backgrounds |
| `primary-500` | Links, focus rings, active borders, interactive icons |
| `primary-600` | Primary buttons, active tab borders |
| `primary-700` | Primary button hover, active nav text |
| `primary-800` | Primary button active/pressed |

### Department/Feature Colors
Assign a consistent color per domain area for icon containers and accent bars:

| Domain | Color | Example |
|--------|-------|---------|
| Products/Catalog | violet | `bg-violet-100 text-violet-600` |
| Inventory | emerald | `bg-emerald-100 text-emerald-600` |
| Orders/Fulfillment | blue | `bg-blue-100 text-blue-600` |
| Suppliers | amber | `bg-amber-100 text-amber-600` |
| eBay | sky | `bg-sky-100 text-sky-600` |
| Shopify | green | `bg-green-100 text-green-600` |
| Analytics | purple | `bg-purple-100 text-purple-600` |
| Settings | gray | `bg-gray-100 text-gray-600` |

### Semantic Colors

| Semantic | Tailwind Family | Icon Container | Badge BG | Text |
|----------|-----------------|----------------|----------|------|
| Success | emerald | `bg-emerald-100 text-emerald-600` | `bg-emerald-50 text-emerald-700 ring-emerald-600/20` | `text-emerald-600` |
| Warning | amber | `bg-amber-100 text-amber-600` | `bg-amber-50 text-amber-700 ring-amber-600/20` | `text-amber-600` |
| Error | red | `bg-red-100 text-red-600` | `bg-red-50 text-red-700 ring-red-600/20` | `text-red-600` |
| Info | blue | `bg-blue-100 text-blue-600` | `bg-blue-50 text-blue-700 ring-blue-600/20` | `text-blue-600` |

### Surface Hierarchy
Layer surfaces to create depth without heavy shadows:

| Level | Background | Usage |
|-------|-----------|-------|
| Page | `bg-gray-50` (dark: `bg-gray-950`) | Page background |
| Card | `bg-white` (dark: `bg-gray-900`) | Cards, panels, modals |
| Nested | `bg-gray-50` (dark: `bg-gray-800/50`) | Sections within cards |
| Inset | `bg-gray-100` (dark: `bg-gray-700`) | Code blocks, disabled inputs |

### Dark Mode
The app uses class-based dark mode (`darkMode: 'class'` in Tailwind config). The `dark` class is toggled on `<html>` by the `useTheme` composable.

Use Tailwind's `dark:` prefix. Map all surfaces, borders, and text:
- Page background: `bg-gray-50` -> `dark:bg-gray-950` (near-black, not gray-900)
- Card surfaces: `bg-white` -> `dark:bg-gray-900`
- Borders: `border-gray-200` -> `dark:border-gray-700` (or `dark:border-gray-800` for subtle)
- Muted text: `text-gray-500` -> `dark:text-gray-400`
- Headings: `text-gray-900` -> `dark:text-gray-100`
- Primary palette uses indigo-based `primary-*` tokens in both modes

### Theme Toggle
The app supports Light / Dark / System modes via the `useTheme` composable (`resources/js/Composables/useTheme.js`):
- `theme` ref: `'light'` | `'dark'` | `'system'` (persisted in `localStorage` key `'theme'`)
- `resolvedTheme` computed: the actual applied theme after resolving system preference
- `setTheme(value)`: updates localStorage and applies/removes `dark` class on `<html>`
- System mode tracks OS preference changes live via `matchMedia`
- A synchronous inline script in `app.blade.php` prevents flash of wrong theme (FOWT)
- The toggle UI is a segmented control in the user dropdown (AppLayout.vue)

---

## Typography Scale

Use these exact class combinations. Do not deviate.

| Role | Classes | Example Usage |
|------|---------|---------------|
| Page Title | `text-2xl font-bold text-gray-900 dark:text-gray-100` | Page `<h1>` |
| Section Heading | `text-lg font-semibold text-gray-900 dark:text-gray-100` | Card titles, section dividers |
| Card Title | `text-base font-semibold text-gray-900 dark:text-gray-100` | Card header text |
| Subtitle | `text-sm font-medium text-gray-700 dark:text-gray-300` | Card subtitles, secondary headings |
| Label | `text-sm font-medium text-gray-700 dark:text-gray-300` | Form labels, table headers |
| Body | `text-sm text-gray-600 dark:text-gray-400` | Paragraph text, descriptions |
| Caption | `text-xs text-gray-500 dark:text-gray-400` | Help text, timestamps, secondary info |
| Overline | `text-xs font-medium text-gray-500 uppercase tracking-wider` | Stat card labels, section overlines |
| KPI / Metric | `text-2xl font-bold text-gray-900 tabular-nums` | Dashboard numbers, stat values |
| Small Metric | `text-lg font-semibold text-gray-900 tabular-nums` | Inline metrics, card stats |

### Typography Rules
- **tabular-nums**: Always apply to numbers that may change or align in columns
- **truncate**: Apply to single-line text that may overflow (add `title` attr for tooltip)
- **line-clamp-2/3**: Use for multi-line descriptions that need capping
- Headings never use `uppercase tracking-widest` (that's reserved for overlines only)

---

## Spacing System

### Page-Level
```
Page padding:    px-4 sm:px-6 lg:px-8
Page max-width:  max-w-7xl mx-auto (standard) | full-width (data tables) | max-w-3xl (narrow forms)
Page top:        py-6 sm:py-8
```

### Section-Level
```
Between sections:     space-y-6 (standard) | space-y-8 (major sections)
Between related:      space-y-4 (tight grouping)
```

### Card-Level
```
Card padding:         p-5 (standard) | p-4 (compact/nested) | p-6 (hero/featured)
Card header:          px-5 py-4 border-b border-gray-100 (when header is separate)
Card footer:          px-5 py-3 bg-gray-50 border-t border-gray-100 rounded-b-xl
```

### Grid Gaps
```
Card grids:     gap-6
Compact grids:  gap-4
Form fields:    gap-x-6 gap-y-4
Stat cards:     gap-4 sm:gap-6
```

---

## Border & Shadow Hierarchy

| Level | Usage | Classes |
|-------|-------|---------|
| 0 - Flat | Page sections, inline groups | No border, no shadow |
| 1 - Card | Cards, panels, form containers | `border border-gray-200 shadow-sm rounded-xl` |
| 2 - Elevated | Modals, slide-overs, drawers | `shadow-lg rounded-xl` (border optional) |
| 3 - Popover | Dropdowns, tooltips, popovers | `shadow-xl rounded-lg ring-1 ring-black/5` |

### Card Standard
Every card uses: `bg-white border border-gray-200 shadow-sm rounded-xl`

Never use:
- `shadow` alone without `border` (looks floating/disconnected)
- `rounded-2xl` on cards (too round -- standardize on `rounded-xl`)
- `rounded-lg` mixed with `rounded-xl` for cards (pick one: `rounded-xl`)
- `rounded-lg` is for inputs, buttons, badges, inner elements only

---

## Transition Standards

| Context | Classes |
|---------|---------|
| Default (color, opacity) | `transition-colors duration-150` |
| Card hover | `transition-all duration-200` |
| Transform (scale, translate) | `transition-transform duration-200 ease-out` |
| Opacity fade | `transition-opacity duration-150` |
| Size/height changes | `transition-all duration-200 ease-in-out` |

### Rules
- Only apply `hover:scale-*` to icons or small interactive elements, never to cards or containers
- Use `will-change-transform` sparingly and only when jank is observed
- Prefer `ease-out` for entrances, `ease-in` for exits, `ease-in-out` for symmetric

---

## Icon Rules

### Library
Use **Font Awesome 6** (already installed). Use the Vue component `<font-awesome-icon>` or raw `<i>` tags as established in the codebase.

### Sizing by Context

| Context | Size Class | FA Size |
|---------|-----------|---------|
| Inline with text | `w-4 h-4` | `fa-sm` |
| Button icon | `w-4 h-4` | -- |
| Stat card icon | `w-5 h-5` | -- |
| Empty state hero | `w-12 h-12` | `fa-2x` |
| Page header icon | `w-6 h-6` | `fa-lg` |

### Icon Container Pattern
For stat cards and feature icons, wrap in a colored container:
```html
<div class="p-2 rounded-lg bg-{color}-100">
  <i class="fas fa-{icon} w-5 h-5 text-{color}-600"></i>
</div>
```

For larger hero contexts (empty states):
```html
<div class="p-3 rounded-xl bg-{color}-50">
  <i class="fas fa-{icon} w-8 h-8 text-{color}-400"></i>
</div>
```

---

## Focus Ring Standard

Replace Jetstream's default thin indigo ring everywhere:

```
/* Standard focus ring */
focus:outline-none focus:ring-4 focus:ring-primary-500/20 focus:border-primary-500

/* For dark backgrounds */
focus:outline-none focus:ring-4 focus:ring-white/20

/* Keyboard-only focus (prefer when supported) */
focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-primary-500/20
```

---

## Button Standards

### Variants

| Variant | Classes |
|---------|---------|
| Primary | `bg-primary-600 text-white hover:bg-primary-700 active:bg-primary-800 shadow-sm` |
| Secondary | `bg-white text-gray-700 border border-gray-300 hover:bg-gray-50 active:bg-gray-100 shadow-sm` |
| Danger | `bg-red-600 text-white hover:bg-red-700 active:bg-red-800 shadow-sm` |
| Ghost | `text-gray-600 hover:text-gray-900 hover:bg-gray-100` |
| Link | `text-primary-600 hover:text-primary-700 underline-offset-2 hover:underline` |

### Sizes

| Size | Classes |
|------|---------|
| sm | `px-3 py-1.5 text-xs font-medium rounded-lg` |
| md (default) | `px-4 py-2 text-sm font-medium rounded-lg` |
| lg | `px-5 py-2.5 text-base font-medium rounded-lg` |

### Rules
- **Never** use `uppercase tracking-widest` on buttons (Jetstream default -- override it)
- Always include `font-medium`
- Always include `shadow-sm` on primary/secondary/danger (not on ghost/link)
- Disabled: `opacity-50 cursor-not-allowed pointer-events-none`
- Loading: swap leading icon to spinner, add `opacity-75 pointer-events-none`

---

## Key Anti-Patterns (NEVER DO)

1. **`uppercase tracking-widest` on buttons** -- looks AI-generated/template. Use `font-medium` with normal casing.
2. **Bare cards with only `shadow`** -- always pair with `border border-gray-200`.
3. **Raw `indigo-*`** -- use `primary-*` for all brand colors.
4. **Mixed border radius** -- `rounded-xl` for cards/modals, `rounded-lg` for inputs/buttons/badges. Never `rounded-2xl`.
5. **Heavy shadows on cards** -- use `shadow-sm` for cards, reserve `shadow-lg`+ for elevated overlays.
6. **Inline styles** -- use Tailwind classes exclusively.
7. **`text-black`** -- use `text-gray-900`. Pure black is too harsh.
8. **Inconsistent spacing** -- follow the spacing system above, don't eyeball padding values.
9. **`hover:shadow-lg` on cards** -- prefer subtle `hover:border-gray-300` or `hover:shadow` transitions.
10. **Missing dark mode** -- every new surface, border, and text color needs a `dark:` variant.

---

## Department Color Tokens

`tailwind.config.js` defines flat `department.*` colors for role-based UI (nav highlights, role badges, workflow indicators):

| Token | Hex | Tailwind Family Equivalent | Usage |
|-------|-----|---------------------------|-------|
| `department-buying` | `#10b981` | emerald-500 | Buying team badges, nav accent |
| `department-listing` | `#3b82f6` | blue-500 | Listing team badges, nav accent |
| `department-fulfillment` | `#f59e0b` | amber-500 | Fulfillment team badges, nav accent |
| `department-support` | `#ef4444` | red-500 | Support team badges, nav accent |
| `department-admin` | `#8b5cf6` | violet-500 | Admin team badges, nav accent |

These are **flat hex values** (no shade scale). For backgrounds and lighter variants, use the equivalent Tailwind family (e.g., `bg-emerald-100` for buying backgrounds). Use the `department-*` tokens for solid accents and exact brand matching.

**Note:** The safelist in `tailwind.config.js` includes `bg-buying-*`, `text-buying-*`, etc. -- these reference the department scale but JIT may still purge them. Prefer standard Tailwind families for backgrounds/text.

---

## Custom Animations

`tailwind.config.js` defines three custom animations. Use these instead of ad-hoc transition classes for entrance effects:

| Class | Duration | Effect | Usage |
|-------|----------|--------|-------|
| `animate-fade-in` | 0.5s ease-in-out | Opacity 0 to 1 | Page section entrance, lazy-loaded content |
| `animate-slide-up` | 0.3s ease-out | translateY(10px) + opacity 0 to 0 | Toast appearance, card entrance, bottom-sheet reveal |
| `animate-bounce-in` | 0.6s ease-out | scale(0.9) to scale(1) | Badge pop-in, notification count update, celebration |

Combine with delay utilities for staggered entrance:
```html
<div class="animate-slide-up" style="animation-delay: 100ms">First card</div>
<div class="animate-slide-up" style="animation-delay: 200ms">Second card</div>
```

---

## Font Family

The app uses **Inter** as the primary sans-serif font (configured in `tailwind.config.js`). Do not override `font-sans` or add competing font-family declarations. Inter is loaded via Bunny Fonts in the main layout (`app.blade.php`).

## Audit Logging

Presentation-only skill -- no audit writes from UI primitives. Color tokens for severity badges: red (critical/error), yellow (warning), blue (info), gray (debug). Match `UserActivityLog::getSeverityColor()` server-side mappings.

Spec: `docs/AUDIT_SYSTEM.md`. When a UI component is wired to a controller mutation, the controller (not the component) is responsible for emitting the audit row.
