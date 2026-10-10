# Supplier Product Submissions Test Cases

## Document ID: TC-SUP-002

**Domain:** Supplier
**Related Test Plan:** `docs/testing/plans/supplier-test-plan.md`
**Last Updated:** 2026-04-15

---

### TC-SUP-002-01: Supplier submits product

| Field | Value |
|-------|-------|
| **ID** | TC-SUP-002-01 |
| **Priority** | P0 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |

**Preconditions:**
1. Authenticated supplier user with an active supplier account
2. Supplier has completed onboarding

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | GET `supplier.products.submit` | 200 response, renders Supplier/Products/Submit form |
| 2 | POST `supplier.products.store` with name, optional sku, barcode, cost_price, description | Redirect to supplier.products.submissions with "Product submitted for review" flash |
| 3 | Verify SupplierProductSubmission | Record created via SupplierUploadService::submit with supplier_id and organization_id |
| 4 | POST without name | Validation error: name is required |
| 5 | POST with cost_price exceeding 999999.99 | Validation error: max value exceeded |
| 6 | Non-supplier user attempts POST | Redirect to dashboard with error |

**Notes:**
- Validation: name required (max 255), sku optional (max 100), barcode optional (max 50), cost_price numeric (0-999999.99), description optional (max 5000)

---

### TC-SUP-002-02: Submission with photos

| Field | Value |
|-------|-------|
| **ID** | TC-SUP-002-02 |
| **Priority** | P1 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |

**Preconditions:**
1. Authenticated supplier user

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | POST `supplier.products.store` with name and 3 image files | Submission created, images stored at supplier-uploads/{supplier_id}/ on public disk |
| 2 | Verify image storage | Each image stored with unique path, paths included in submitted data |
| 3 | POST with 6 images (exceeding max 5) | Validation error: images max 5 files |
| 4 | POST with non-image file in images array | Validation error: images.* must be an image |
| 5 | POST with image exceeding 5MB | Validation error: max size 5120 KB per image |
| 6 | POST with no images | Submission created without images (images are optional) |

---

### TC-SUP-002-03: Admin reviews submission

| Field | Value |
|-------|-------|
| **ID** | TC-SUP-002-03 |
| **Priority** | P0 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |

**Preconditions:**
1. Authenticated admin user (non-supplier, with admin access)
2. SupplierProductSubmission exists with status=pending_review

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | GET admin submission list (admin.supplier-submissions.index) | 200 response, paginated list of submissions for the organization |
| 2 | Verify submission data | Each submission loaded with supplier (name, company_name) and reviewer relations |
| 3 | Verify stats | Submission stats returned from SupplierUploadService::getSubmissionStats |
| 4 | Filter by status=pending_review | Only pending submissions returned |
| 5 | GET admin review page for a submission | 200 response, renders Admin/SupplierSubmissions/Review with full submission and supplier details |

---

### TC-SUP-002-04: Approve submission creates product

| Field | Value |
|-------|-------|
| **ID** | TC-SUP-002-04 |
| **Priority** | P0 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |

**Preconditions:**
1. Authenticated admin user
2. A SupplierProductSubmission with status=pending_review

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | POST approve endpoint for the submission | Redirect to admin.supplier-submissions.index with "Submission approved and added to intake queue" flash |
| 2 | Verify submission status | Status changed to approved, reviewed_by set to admin user ID |
| 3 | Verify product creation | SupplierUploadService::approve adds product to intake queue for processing |
| 4 | Attempt to approve an already-approved submission | Handled gracefully (no duplicate product creation) |

---

### TC-SUP-002-05: Reject submission with reason

| Field | Value |
|-------|-------|
| **ID** | TC-SUP-002-05 |
| **Priority** | P0 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |

**Preconditions:**
1. Authenticated admin user
2. A SupplierProductSubmission with status=pending_review

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | POST reject endpoint with review_notes explaining rejection reason | Redirect to admin.supplier-submissions.index with "Submission rejected" flash |
| 2 | Verify submission status | Status changed to rejected, review_notes stored, reviewed_by set |
| 3 | POST reject without review_notes | Validation error: review_notes is required |
| 4 | POST reject with review_notes exceeding 2000 chars | Validation error: max 2000 characters |
| 5 | Verify supplier visibility | Rejected submission shows rejection reason when supplier views their submissions |

---

### TC-SUP-002-06: Supplier sees submission status

| Field | Value |
|-------|-------|
| **ID** | TC-SUP-002-06 |
| **Priority** | P1 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |

**Preconditions:**
1. Authenticated supplier user
2. Supplier has submitted multiple products (mix of pending_review, approved, rejected)

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | GET `supplier.products.submissions` | 200 response, paginated list of supplier's own submissions ordered by created_at descending |
| 2 | Verify scope | Only submissions for the authenticated supplier shown (not other suppliers') |
| 3 | Filter by status=approved | Only approved submissions returned |
| 4 | Filter by status=rejected | Only rejected submissions with review_notes visible |
| 5 | Filter by status=pending_review | Only pending submissions returned |
| 6 | Verify pagination | 20 submissions per page, query string preserved for filters |

**Notes:**
- Supplier submissions are scoped by supplier_id, not organization_id, so suppliers only see their own
- Valid filter values: pending_review, approved, rejected (invalid values are ignored)
