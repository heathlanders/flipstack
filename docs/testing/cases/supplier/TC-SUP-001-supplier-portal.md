# Supplier Portal Test Cases

## Document ID: TC-SUP-001

**Domain:** Supplier
**Related Test Plan:** `docs/testing/plans/supplier-test-plan.md`
**Last Updated:** 2026-04-15

---

### TC-SUP-001-01: Supplier dashboard loads

| Field | Value |
|-------|-------|
| **ID** | TC-SUP-001-01 |
| **Priority** | P0 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |

**Preconditions:**
1. Authenticated user with supplier_id set (or matching Supplier via email/user_id)
2. Supplier middleware passes (user is an active supplier)
3. Supplier has completed onboarding

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | GET `supplier.dashboard` | 200 response, renders supplier dashboard page |
| 2 | Verify data | Dashboard includes supplier info with currentTier, commissionStructure, and organization loaded |
| 3 | Verify recent settlements | Up to 5 most recent settlements with items loaded, ordered by settlement_period_end descending |
| 4 | Verify performance metrics | Performance metrics loaded for the supplier |
| 5 | Non-supplier user accesses `supplier.dashboard` | Redirect to main dashboard with error (supplier middleware blocks) |
| 6 | User with no matching supplier record | Redirect to dashboard with "Supplier account not found" error |

**Notes:**
- Supplier resolution: first checks user.supplier_id, then falls back to Supplier matching by email or user_id
- Routes protected by `supplier` middleware in addition to `auth:sanctum`

---

### TC-SUP-001-02: Onboarding step 1 - business info

| Field | Value |
|-------|-------|
| **ID** | TC-SUP-001-02 |
| **Priority** | P0 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |

**Preconditions:**
1. Authenticated supplier user
2. SupplierOnboarding record exists with status=in_progress, current_step=1

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | GET `supplier.onboarding.index` | 200 response, renders Supplier/Onboarding with current_step=1 |
| 2 | POST `supplier.onboarding.step1` with valid data: business_name, business_type=llc, address, city, state, zip_code, country, agree_to_terms | Redirect to onboarding.index with success flash |
| 3 | Verify Supplier record updated | company_name, business_type, address fields updated on Supplier model |
| 4 | Verify step data stored | step1_data populated with business details and terms_agreed_at |
| 5 | Verify step advance | Onboarding advances to step 2 |
| 6 | POST without agree_to_terms | Validation error: agree_to_terms is required and must be accepted |
| 7 | POST with invalid business_type | Validation error: must be one of individual, llc, corporation, partnership |

---

### TC-SUP-001-03: Onboarding step 2 - documents

| Field | Value |
|-------|-------|
| **ID** | TC-SUP-001-03 |
| **Priority** | P1 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |

**Preconditions:**
1. Authenticated supplier user at onboarding step 2
2. Step 1 completed

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | POST `supplier.onboarding.step2` with document files (type: business_license, tax_documents) | Files stored in suppliers/{id}/documents on private disk |
| 2 | Verify step2_data | Contains documents map with path, original_name, uploaded_at per document |
| 3 | Upload at least 2 required document types | Step marked complete, progress calculated, advances to step 3 |
| 4 | Upload only 1 document | Step not yet complete (requires at least 2) |
| 5 | POST with file exceeding 10MB | Validation error: max file size 10240 KB |
| 6 | POST with invalid file type (e.g., .exe) | Validation error: mimes must be pdf, jpg, jpeg, png |

**Notes:**
- Document verification flags (business_license_verified, etc.) are set to false on upload; admin reviews later

---

### TC-SUP-001-04: Onboarding step 3 - commission structure

| Field | Value |
|-------|-------|
| **ID** | TC-SUP-001-04 |
| **Priority** | P1 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |

**Preconditions:**
1. Authenticated supplier user at onboarding step 3
2. Active CommissionStructure records exist for the supplier's organization

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Verify onboarding page | Commission structures and tiers displayed for selection |
| 2 | POST `supplier.onboarding.step3` with valid commission_structure_id, settlement_preference=monthly, payment_method=bank_transfer, bank_details | Redirect with success flash |
| 3 | Verify Supplier updated | commission_structure_id, settlement_schedule, payment_method set on Supplier model |
| 4 | Verify step data | step3_data stores commission structure name, settlement preference, payment method |
| 5 | POST with commission_structure_id from different organization | 404 (firstOrFail on org-scoped query) |
| 6 | POST with payment_method=paypal but no paypal_email | Validation error: paypal_email required when payment_method is paypal |

---

### TC-SUP-001-05: Onboarding step 4 - notification preferences

| Field | Value |
|-------|-------|
| **ID** | TC-SUP-001-05 |
| **Priority** | P2 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |

**Preconditions:**
1. Authenticated supplier user at onboarding step 4

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | POST `supplier.onboarding.step4` with notification_preferences (email, sms, push booleans) and tutorial_completed=true | Redirect with success flash |
| 2 | Verify step data | step4_data stores notification preferences and tutorial_completed_at timestamp |
| 3 | Verify onboarding record | portal_credentials_sent=true, tutorial_completed=true |
| 4 | POST without tutorial_completed | Validation error: tutorial_completed is required and must be accepted |

---

### TC-SUP-001-06: Onboarding step 5 - first product

| Field | Value |
|-------|-------|
| **ID** | TC-SUP-001-06 |
| **Priority** | P1 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |

**Preconditions:**
1. Authenticated supplier user at onboarding step 5

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | POST `supplier.onboarding.step5` with submission_type=sample_product and product_data | Step completed, onboarding marked complete |
| 2 | POST with submission_type=acknowledge (no product data) | Step completed, onboarding marked complete |
| 3 | Verify onboarding complete | SupplierOnboarding status set to completed, redirect to supplier.dashboard |
| 4 | Verify flash message | "Congratulations! Your onboarding is complete. Welcome to the platform!" |
| 5 | POST with submission_type=sample_product but no product_data | Validation error: product_data required when submission_type is sample_product |

---

### TC-SUP-001-07: Onboarding progress tracking

| Field | Value |
|-------|-------|
| **ID** | TC-SUP-001-07 |
| **Priority** | P1 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |

**Preconditions:**
1. Authenticated supplier user with onboarding partially completed

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | GET `supplier.onboarding.status` | JSON response with status, current_step, completion_percentage, steps |
| 2 | Verify completion_percentage | Calculated based on completed steps (e.g., 2/5 = 40%) |
| 3 | Verify progressSteps | Array of step objects with completion status for each |
| 4 | GET status for supplier with no onboarding record | JSON: status=not_started, current_step=1, completion_percentage=0 |
| 5 | Verify onboarding auto-start | First visit to onboarding.index changes status from not_started to in_progress |

---

### TC-SUP-001-08: Skip documentation

| Field | Value |
|-------|-------|
| **ID** | TC-SUP-001-08 |
| **Priority** | P2 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |

**Preconditions:**
1. Authenticated supplier user at onboarding step 2

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | POST `supplier.onboarding.skip-docs` | Redirect to onboarding.index with success flash |
| 2 | Verify step2 status | step2_documentation_complete=true, step2_completed_at set |
| 3 | Verify step2_data | Contains skipped=true and skipped_at timestamp |
| 4 | Verify progress | Completion percentage recalculated, onboarding advances to step 3 |
| 5 | Verify messaging | "Documentation step skipped. You can upload documents later." |

**Notes:**
- Skip documentation is intended for demo/development scenarios
- Suppliers can upload documents later via settings after onboarding is complete
