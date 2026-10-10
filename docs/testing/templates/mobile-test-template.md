# [Screen Name] Mobile Tests

## Document ID: TC-MOB-[NUMBER]

**Flutter Page:** `lib/features/[domain]/presentation/pages/[page].dart`
**Service:** `lib/data/services/[service].dart`
**API Endpoint:** `[METHOD] /api/[path]`
**Last Updated:** YYYY-MM-DD

---

## Widget Tests

Test that the widget tree renders correctly for each state.

| ID | Test | Widget Under Test | Expected | Status |
|----|------|-------------------|----------|--------|
| MOB-[DOM]-[N]-01 | Renders with data | `[PageWidget]` | All expected widgets present | Not started / Pass / Fail |
| MOB-[DOM]-[N]-02 | Renders empty state | `[PageWidget]` | Empty state message shown | |
| MOB-[DOM]-[N]-03 | Renders loading state | `[PageWidget]` | Loading indicator visible | |
| MOB-[DOM]-[N]-04 | Renders error state | `[PageWidget]` | Error message with retry button | |

---

## Interaction Tests

| ID | Scenario | Steps | Expected | Status |
|----|----------|-------|----------|--------|
| MOB-[DOM]-[N]-10 | Tap primary action | 1. Render screen 2. Tap button | [Expected navigation or state change] | |
| MOB-[DOM]-[N]-11 | Pull to refresh | 1. Render with data 2. Pull down | Loading indicator, fresh data | |
| MOB-[DOM]-[N]-12 | Form submission | 1. Fill fields 2. Tap submit | Success feedback, API called | |
| MOB-[DOM]-[N]-13 | Form validation | 1. Leave required field empty 2. Tap submit | Validation error shown | |

---

## Integration Tests

Full flow tests using `integration_test` package.

| ID | Scenario | Precondition | Steps | Expected | Status |
|----|----------|--------------|-------|----------|--------|
| MOB-[DOM]-[N]-20 | [Complete user flow] | [Auth state, data state] | [Step-by-step] | [End state] | |

---

## Offline Tests

| ID | Scenario | Expected | Status |
|----|----------|----------|--------|
| MOB-[DOM]-[N]-30 | No connectivity on load | Offline indicator, cached data displayed (if available) | |
| MOB-[DOM]-[N]-31 | Connectivity lost mid-use | Offline indicator, pending actions queued | |
| MOB-[DOM]-[N]-32 | Connectivity restored | Queued actions sync, indicator removed | |

---

## Device-Specific Tests

| ID | Device / Form Factor | Check | Expected | Status |
|----|---------------------|-------|----------|--------|
| MOB-[DOM]-[N]-40 | Small phone (5") | Layout fits without overflow | No render overflow errors | |
| MOB-[DOM]-[N]-41 | Large phone (6.7") | Layout uses space well | No excessive whitespace | |
| MOB-[DOM]-[N]-42 | Tablet (10") | Layout adapts | Appropriate use of screen real estate | |

---

## Riverpod Provider Tests

| ID | Provider | Test | Expected | Status |
|----|----------|------|----------|--------|
| MOB-[DOM]-[N]-50 | `[providerName]` | Initial state | [Expected initial value] | |
| MOB-[DOM]-[N]-51 | `[providerName]` | After API success | [Expected updated state] | |
| MOB-[DOM]-[N]-52 | `[providerName]` | After API failure | [Expected error state] | |

---

## Notes

- [Navigation dependencies (GoRouter)]
- [Shared widgets used]
- [Platform-specific behavior (Android vs iOS)]
