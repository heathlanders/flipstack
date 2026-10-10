# TC-INV-009: Bulk Operations

| Field             | Value                                      |
|-------------------|--------------------------------------------|
| Document ID       | TC-INV-009                                 |
| Domain            | Inventory                                  |
| Related Test Plan | TP-INV-001 Product Management              |
| Last Updated      | 2026-04-15                                 |
| Controller        | BulkOperationsController                   |
| Routes            | inventory.bulk.*                           |

---

## TC-INV-009-01: Preview Bulk Operation

| Field          | Value                                                        |
|----------------|--------------------------------------------------------------|
| ID             | TC-INV-009-01                                                |
| Priority       | P1                                                           |
| Type           | Functional                                                   |
| Platform       | Web                                                          |
| Automated      | No                                                           |
| Test File      | --                                                           |

**Preconditions:**
- User is authenticated with inventory management permissions.
- At least 5 products exist in the organization.

**Test Steps:**

| Step | Action                                                  | Expected Result                                              |
|------|---------------------------------------------------------|--------------------------------------------------------------|
| 1    | POST inventory.bulk.preview with product_ids and operation type | Preview response is returned without modifying data.         |
| 2    | Verify affected products listed                         | Response includes the list of products that will be affected. |
| 3    | Verify changes previewed                                | Each product shows the before/after values for the operation. |
| 4    | Verify no database changes                              | Products remain unchanged after the preview call.            |

**Test Data:**
- product_ids: [product_1_uuid, product_2_uuid, product_3_uuid]
- operation: "status_update"
- parameters: {"status": "archived"}

**Postconditions:**
- No data is modified. Preview is read-only.

**Notes:**
- Preview allows users to verify bulk changes before committing.

---

## TC-INV-009-02: Bulk Status Update

| Field          | Value                                                        |
|----------------|--------------------------------------------------------------|
| ID             | TC-INV-009-02                                                |
| Priority       | P1                                                           |
| Type           | Functional                                                   |
| Platform       | Web                                                          |
| Automated      | No                                                           |
| Test File      | --                                                           |

**Preconditions:**
- User is authenticated with inventory management permissions.
- At least 3 products exist with status=active.

**Test Steps:**

| Step | Action                                                  | Expected Result                                              |
|------|---------------------------------------------------------|--------------------------------------------------------------|
| 1    | POST inventory.bulk.execute with product_ids, operation=status_update, parameters={status: "archived"} | Bulk status update job is dispatched.                        |
| 2    | Verify response                                         | Response includes job_id for progress tracking.              |
| 3    | Verify all selected products updated                    | Each product's status is changed from "active" to "archived". |
| 4    | Verify non-selected products unchanged                  | Products not in the selection retain their original status.  |
| 5    | Verify BulkOperationJob record created                  | A BulkOperationJob record tracks the operation.              |

**Test Data:**
- product_ids: [product_1_uuid, product_2_uuid, product_3_uuid]
- operation: "status_update"
- parameters: {"status": "archived"}

**Postconditions:**
- Selected products have status=archived.
- A BulkOperationJob record exists with completion status.

**Notes:**
- Valid status transitions should be enforced (e.g., cannot bulk-update to an invalid status).

---

## TC-INV-009-03: Bulk Price Update with Percentage

| Field          | Value                                                        |
|----------------|--------------------------------------------------------------|
| ID             | TC-INV-009-03                                                |
| Priority       | P1                                                           |
| Type           | Functional                                                   |
| Platform       | Web                                                          |
| Automated      | No                                                           |
| Test File      | --                                                           |

**Preconditions:**
- User is authenticated with inventory management permissions.
- At least 3 products exist with known prices.

**Test Steps:**

| Step | Action                                                  | Expected Result                                              |
|------|---------------------------------------------------------|--------------------------------------------------------------|
| 1    | POST inventory.bulk.execute with operation=price_update, parameters={type: "percentage", value: 10} | Bulk price increase of 10% is applied.                       |
| 2    | Verify price calculations                               | Product A ($50.00) becomes $55.00. Product B ($100.00) becomes $110.00. |
| 3    | Verify decimal handling                                  | Prices are rounded to 2 decimal places.                      |
| 4    | Test with negative percentage (decrease)                | POST with value=-15 decreases prices by 15%.                 |
| 5    | Verify price decrease calculation                       | Product A ($50.00) becomes $42.50.                           |

**Test Data:**
- Product A: price=$50.00
- Product B: price=$100.00
- Product C: price=$75.50
- Percentage increase: 10%

**Postconditions:**
- All selected products have updated prices reflecting the percentage change.

**Notes:**
- Consider whether price guards should apply to bulk price updates to prevent pricing below cost.

---

## TC-INV-009-04: Bulk Stock Adjustment Across Locations

| Field          | Value                                                        |
|----------------|--------------------------------------------------------------|
| ID             | TC-INV-009-04                                                |
| Priority       | P1                                                           |
| Type           | Functional                                                   |
| Platform       | Web                                                          |
| Automated      | No                                                           |
| Test File      | --                                                           |

**Preconditions:**
- User is authenticated with inventory management permissions.
- At least 3 products exist with inventory levels at a specific location.

**Test Steps:**

| Step | Action                                                  | Expected Result                                              |
|------|---------------------------------------------------------|--------------------------------------------------------------|
| 1    | POST inventory.bulk.execute with operation=stock_adjustment, parameters={location_id, adjustment: 5} | Stock is increased by 5 units for each selected product at the location. |
| 2    | Verify inventory levels updated                         | Each product's InventoryLevel at the specified location is incremented by 5. |
| 3    | Verify inventory movements created                      | An InventoryMovement record is created for each adjustment.  |
| 4    | Test with negative adjustment                           | POST with adjustment=-3 decreases stock by 3 units.         |
| 5    | Verify available_quantity updated                       | Product.available_quantity reflects the stock adjustment.    |

**Test Data:**
- Product A: current stock=10, after adjustment=15.
- Product B: current stock=20, after adjustment=25.
- location_id: existing location ID.
- adjustment: 5

**Postconditions:**
- Inventory levels and available quantities are updated for all selected products.

**Notes:**
- Negative adjustments should not allow stock to go below zero.

---

## TC-INV-009-05: Job Progress Tracking

| Field          | Value                                                        |
|----------------|--------------------------------------------------------------|
| ID             | TC-INV-009-05                                                |
| Priority       | P1                                                           |
| Type           | Functional                                                   |
| Platform       | Web                                                          |
| Automated      | No                                                           |
| Test File      | --                                                           |

**Preconditions:**
- A bulk operation has been dispatched and is in progress or completed.
- A valid job_id exists from a prior bulk execute response.

**Test Steps:**

| Step | Action                                                  | Expected Result                                              |
|------|---------------------------------------------------------|--------------------------------------------------------------|
| 1    | GET api.bulk.progress with job_id                       | Progress response is returned.                               |
| 2    | Verify progress fields                                  | Response includes: status, total_items, processed_items, failed_items, percentage_complete. |
| 3    | Poll progress during execution                          | processed_items increments as the job processes items.       |
| 4    | Verify completed status                                 | When all items are processed, status changes to "completed". |

**Test Data:**
- job_id from a previously dispatched bulk operation.

**Postconditions:**
- No data is modified. Progress endpoint is read-only.

**Notes:**
- Frontend typically polls this endpoint at regular intervals to update a progress bar.

---

## TC-INV-009-06: Job Errors Endpoint

| Field          | Value                                                        |
|----------------|--------------------------------------------------------------|
| ID             | TC-INV-009-06                                                |
| Priority       | P2                                                           |
| Type           | Functional                                                   |
| Platform       | Web                                                          |
| Automated      | No                                                           |
| Test File      | --                                                           |

**Preconditions:**
- A bulk operation has completed with at least 1 failed item.
- A valid job_id exists.

**Test Steps:**

| Step | Action                                                  | Expected Result                                              |
|------|---------------------------------------------------------|--------------------------------------------------------------|
| 1    | GET api.bulk.errors with job_id                         | Error list is returned.                                      |
| 2    | Verify error details                                    | Each error includes: product_id, product_sku, error_message. |
| 3    | Verify only failed items listed                         | Successfully processed items are not in the error list.      |
| 4    | Test with a job that had no errors                      | Returns an empty error list.                                 |

**Test Data:**
- job_id from a bulk operation where some items failed (e.g., stock adjustment below zero).

**Postconditions:**
- No data is modified. Error endpoint is read-only.

**Notes:**
- Error details help users identify and fix individual item issues after a bulk operation.

---

## TC-INV-009-07: Cancel Running Bulk Job

| Field          | Value                                                        |
|----------------|--------------------------------------------------------------|
| ID             | TC-INV-009-07                                                |
| Priority       | P2                                                           |
| Type           | Functional                                                   |
| Platform       | Web                                                          |
| Automated      | No                                                           |
| Test File      | --                                                           |

**Preconditions:**
- A bulk operation is currently in progress (status=processing).
- A valid job_id exists.

**Test Steps:**

| Step | Action                                                  | Expected Result                                              |
|------|---------------------------------------------------------|--------------------------------------------------------------|
| 1    | POST inventory.bulk.jobs.cancel with job_id             | Cancellation request is accepted.                            |
| 2    | Verify job status changes                               | BulkOperationJob status changes to "cancelled".              |
| 3    | Verify remaining items not processed                    | Items not yet processed when cancel was received are skipped. |
| 4    | Verify already-processed items retained                 | Changes to items processed before cancellation remain.       |
| 5    | Attempt to cancel a completed job                       | Returns error indicating job is already completed.           |

**Test Data:**
- job_id of a currently running bulk operation.

**Postconditions:**
- The job is marked as cancelled. Partial changes from already-processed items persist.

**Notes:**
- Cancellation is best-effort. Items currently being processed may complete before the cancellation takes effect.

---

## TC-INV-009-08: Retry Failed Bulk Job

| Field          | Value                                                        |
|----------------|--------------------------------------------------------------|
| ID             | TC-INV-009-08                                                |
| Priority       | P2                                                           |
| Type           | Functional                                                   |
| Platform       | Web                                                          |
| Automated      | No                                                           |
| Test File      | --                                                           |

**Preconditions:**
- A bulk operation has completed with failed items (status=completed_with_errors or similar).
- A valid job_id exists with at least 1 failed item.

**Test Steps:**

| Step | Action                                                  | Expected Result                                              |
|------|---------------------------------------------------------|--------------------------------------------------------------|
| 1    | POST inventory.bulk.jobs.retry with job_id              | A new bulk job is created targeting only the previously failed items. |
| 2    | Verify new job scope                                    | The retry job processes only the items that failed in the original run. |
| 3    | Verify new job_id returned                              | Response includes a new job_id for the retry operation.      |
| 4    | Verify previously successful items not reprocessed      | Items that succeeded in the original job are not affected.   |

**Test Data:**
- Original job_id with 10 total items: 8 succeeded, 2 failed.
- Retry job should target only the 2 failed items.

**Postconditions:**
- A new bulk operation job exists targeting only the previously failed items.

**Notes:**
- The underlying issue causing the failures should be resolved before retrying, otherwise the retry will fail with the same errors.
