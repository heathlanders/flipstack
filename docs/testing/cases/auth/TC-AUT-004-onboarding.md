# Onboarding Test Cases

## Document ID: TC-AUT-004

**Domain:** Auth / Organization
**Related Test Plan:** `docs/testing/plans/auth-test-plan.md`
**Last Updated:** 2026-04-15

---

### TC-AUT-004-01: New user sees onboarding wizard

| Field | Value |
|-------|-------|
| **ID** | TC-AUT-004-01 |
| **Priority** | P0 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |

**Preconditions:**
1. Newly registered user who has not completed onboarding
2. No OnboardingProgress record exists for the user, or record has completed_at = null

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | GET `onboarding.index` | 200 response, renders Onboarding/Index |
| 2 | Verify page data | Contains: user info (id, name, email, avatar, primary_role), progress, current_step, steps list, organization |
| 3 | Verify steps list | Steps include: welcome, profile, teams, sku_prefix_setup, role_understanding, first_action, resources, complete |
| 4 | User who has completed onboarding visits `onboarding.index` | Redirect to dashboard |

---

### TC-AUT-004-02: Complete organization setup step

| Field | Value |
|-------|-------|
| **ID** | TC-AUT-004-02 |
| **Priority** | P0 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |

**Preconditions:**
1. Authenticated user in onboarding flow
2. Currently on the profile or teams step

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | POST `onboarding.complete-step` with step=welcome | OnboardingProgress record created/updated, welcome added to steps_completed array |
| 2 | Verify current_step advance | current_step moves to next step in sequence |
| 3 | POST complete-step with step=profile and data payload | Step data stored in OnboardingProgress.data under profile key |
| 4 | POST with invalid step name | Validation error: step must be one of defined values |
| 5 | Verify transaction | Step completion wrapped in DB transaction for atomicity |

**Notes:**
- OnboardingProgress uses firstOrCreate to handle first step completion
- Step data is stored as JSON in the data column keyed by step name

---

### TC-AUT-004-03: Connect first marketplace

| Field | Value |
|-------|-------|
| **ID** | TC-AUT-004-03 |
| **Priority** | P1 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |

**Preconditions:**
1. User in onboarding flow, on the first_action step
2. Organization does not have any IntegrationCredential records

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Onboarding wizard presents marketplace connection option | eBay and/or Shopify OAuth connection buttons displayed |
| 2 | User initiates eBay OAuth flow | Redirect to eBay authorization URL |
| 3 | Callback returns from eBay | IntegrationCredential created for the organization |
| 4 | POST `onboarding.complete-step` with step=first_action | Step marked complete, onboarding advances |
| 5 | User skips marketplace connection | Can proceed without connecting (not mandatory) |

**Notes:**
- Marketplace connection during onboarding uses the same OAuth flow as the settings page
- The first_action step may encompass multiple options (connect marketplace, add product, etc.)

---

### TC-AUT-004-04: Import first products

| Field | Value |
|-------|-------|
| **ID** | TC-AUT-004-04 |
| **Priority** | P1 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |

**Preconditions:**
1. User in onboarding flow, on the first_action step
2. Organization has an active marketplace connection (IntegrationCredential)

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Onboarding presents product import option | Import from marketplace or manual creation options shown |
| 2 | User triggers product import from connected marketplace | Import job dispatched, products begin syncing |
| 3 | Verify product creation | Products created with correct organization_id |
| 4 | POST `onboarding.complete-step` with step=first_action and import data | Step data recorded, step marked complete |

---

### TC-AUT-004-05: Onboarding progress tracked

| Field | Value |
|-------|-------|
| **ID** | TC-AUT-004-05 |
| **Priority** | P1 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |

**Preconditions:**
1. User has partially completed onboarding (e.g., 3 of 7 steps done)

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | GET `onboarding.index` | Progress object shows steps_completed with 3 entries |
| 2 | Verify current_step | Set to the next uncompleted step |
| 3 | Complete another step | steps_completed grows to 4, current_step advances |
| 4 | Verify no duplicates | Completing an already-completed step does not duplicate it in the array |
| 5 | Complete the final step (complete) | completed_at timestamp set, subsequent visits redirect to dashboard |

**Notes:**
- Progress is tracked in OnboardingProgress table: user_id, organization_id, steps_completed (JSON array), current_step, started_at, completed_at, data (JSON)

---

### TC-AUT-004-06: Skip onboarding marks complete

| Field | Value |
|-------|-------|
| **ID** | TC-AUT-004-06 |
| **Priority** | P1 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |

**Preconditions:**
1. User in onboarding flow at any step

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | POST `onboarding.skip` | Redirect to dashboard |
| 2 | Verify OnboardingProgress | completed_at set to current timestamp |
| 3 | User navigates to any authenticated route | Onboarding middleware no longer redirects to onboarding |
| 4 | POST `onboarding.complete` (finish endpoint) | Same result as skip: marks onboarding complete, redirects to dashboard |
| 5 | User returns to `onboarding.index` after skip | Redirected to dashboard (onboarding already complete) |

**Notes:**
- Skip and complete both use the onboarding middleware check: if OnboardingProgress.completed_at is set, user passes through
- Route names: `onboarding.skip` and `onboarding.complete`
