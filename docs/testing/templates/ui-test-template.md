# [Page Name] UI Tests

## Document ID: UI-[DOMAIN]-[NUMBER]

**Vue Component:** `resources/js/Pages/[Domain]/[Page].vue`
**Route:** `[laravel.route.name]`
**Controller:** `[Controller]@[method]`
**Last Updated:** YYYY-MM-DD

---

## Visual State Tests

| ID | State | Description | Verified | Screenshot |
|----|-------|-------------|----------|------------|
| UI-[DOM]-[N]-01 | Empty state | No data exists yet | [ ] | |
| UI-[DOM]-[N]-02 | Loaded state | Normal data display | [ ] | |
| UI-[DOM]-[N]-03 | Loading state | Skeleton or spinner while fetching | [ ] | |
| UI-[DOM]-[N]-04 | Error state | API error response shown | [ ] | |
| UI-[DOM]-[N]-05 | Permission denied | User lacks required permission | [ ] | |

---

## Interaction Tests

| ID | Interaction | Precondition | Expected Result | Verified |
|----|-------------|--------------|-----------------|----------|
| UI-[DOM]-[N]-10 | Click primary action button | User has permission | Action executes / modal opens | [ ] |
| UI-[DOM]-[N]-11 | Submit form with valid data | All required fields filled | Success feedback, data persisted | [ ] |
| UI-[DOM]-[N]-12 | Submit form with invalid data | Required field empty | Validation error displayed inline | [ ] |
| UI-[DOM]-[N]-13 | Delete with confirmation | Item exists | Confirmation dialog, then removal | [ ] |
| UI-[DOM]-[N]-14 | Search / filter | Data exists | Results filtered correctly | [ ] |
| UI-[DOM]-[N]-15 | Pagination | More items than page size | Navigation works, data loads | [ ] |
| UI-[DOM]-[N]-16 | Sort columns | Table displayed | Sort order changes, indicator shown | [ ] |

---

## Navigation Tests

| ID | Action | Expected | Verified |
|----|--------|----------|----------|
| UI-[DOM]-[N]-20 | Click breadcrumb | Navigate to parent page | [ ] |
| UI-[DOM]-[N]-21 | Click row / item link | Navigate to detail page | [ ] |
| UI-[DOM]-[N]-22 | Browser back button | Return to previous state | [ ] |

---

## Responsive Tests

| ID | Breakpoint | Expected Behavior | Verified |
|----|------------|-------------------|----------|
| UI-[DOM]-[N]-30 | Mobile (<640px) | [Layout changes, hamburger menu, stacked elements] | [ ] |
| UI-[DOM]-[N]-31 | Tablet (640-1024px) | [Adjusted grid, collapsed sidebar] | [ ] |
| UI-[DOM]-[N]-32 | Desktop (>1024px) | [Full layout with sidebar, multi-column] | [ ] |

---

## Accessibility Checks

| ID | Check | Expected | Verified |
|----|-------|----------|----------|
| UI-[DOM]-[N]-40 | Keyboard navigation | All interactive elements reachable via Tab | [ ] |
| UI-[DOM]-[N]-41 | Focus indicators | Visible focus ring on active element | [ ] |
| UI-[DOM]-[N]-42 | Form labels | All inputs have associated labels | [ ] |
| UI-[DOM]-[N]-43 | Color contrast | Text meets WCAG AA contrast ratio | [ ] |

---

## Notes

- [Component dependencies]
- [Shared components used]
- [Known UI issues]
