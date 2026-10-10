# TC-FUL-005: Returns Lifecycle Test Cases

| Field              | Value                                           |
|--------------------|------------------------------------------------|
| **Document ID**    | TC-FUL-005                                      |
| **Domain**         | Fulfillment                                     |
| **Related Test Plan** | `docs/testing/plans/fulfillment-test-plan.md` |
| **Last Updated**   | 2026-04-15                                      |

## Overview

Test cases covering the full returns lifecycle: creation with RMA generation, approval workflow, receiving, inspection, restocking, refund processing, and denial. Validates status transitions and guards against invalid state changes.

**Controller:** `App\Http\Controllers\Fulfillment\ReturnController`
**Routes:** `fulfillment.returns.*`
**Model:** `App\Models\Fulfillment\ReturnRequest`

---

## TC-FUL-005-01: View Returns List with Status Filter and Search

| Field           | Value                                                        |
|-----------------|--------------------------------------------------------------|
| **ID**          | TC-FUL-005-01                                                |
| **Priority**    | P1                                                           |
| **Type**        | Feature                                                      |
| **Platform**    | Web                                                          |
| **Automated**   | No                                                           |
| **Test File**   | --                                                           |

### Preconditions

1. Authenticated user with fulfillment permissions belongs to an organization.
2. Organization has at least 5 ReturnRequest records spanning multiple statuses (requested, approved, received, refunded, denied).
3. At least 2 returns have RMA numbers containing a known substring for search testing.

### Test Steps

| Step | Action                                                                 | Expected Result                                                                                       |
|------|------------------------------------------------------------------------|-------------------------------------------------------------------------------------------------------|
| 1    | GET `fulfillment.returns.index` with no filters.                       | Page loads with paginated list of all returns for the organization. Each row shows RMA number, order reference, status badge, created date, and customer name. |
| 2    | Apply status filter `requested`.                                       | List shows only returns with status `requested`. Pagination updates accordingly.                      |
| 3    | Apply status filter `refunded`.                                        | List shows only returns with status `refunded`.                                                       |
| 4    | Enter a known RMA substring in the search field.                       | List filters to returns whose RMA number or order reference matches the search term.                  |
| 5    | Combine status filter `approved` with a search term that has no match. | List shows empty state with appropriate message.                                                      |
| 6    | Clear all filters.                                                     | Full unfiltered list is restored.                                                                     |

### Test Data

- ReturnRequest records with statuses: `requested`, `approved`, `shipped_back`, `received`, `inspected`, `refunded`, `denied`, `cancelled`.
- RMA numbers follow format `RMA-YYYYMMDD-XXXX` (e.g., `RMA-20260415-0001`).

### Postconditions

- No data is modified. Read-only operation.

### Notes

- Verify that returns are scoped to the current organization (no cross-tenant data leakage).
- Pagination should default to a reasonable page size (e.g., 15 or 25).

---

## TC-FUL-005-02: Create Return Generates RMA Number

| Field           | Value                                                        |
|-----------------|--------------------------------------------------------------|
| **ID**          | TC-FUL-005-02                                                |
| **Priority**    | P0                                                           |
| **Type**        | Feature                                                      |
| **Platform**    | Web                                                          |
| **Automated**   | No                                                           |
| **Test File**   | --                                                           |

### Preconditions

1. Authenticated user with fulfillment permissions.
2. Organization has at least one completed/shipped Order with order items.
3. No existing ReturnRequest for the order items being returned.

### Test Steps

| Step | Action                                                                                              | Expected Result                                                                                                          |
|------|-----------------------------------------------------------------------------------------------------|--------------------------------------------------------------------------------------------------------------------------|
| 1    | POST `fulfillment.returns.create` with valid payload: `order_id`, `items` array (each with `order_item_id`, `quantity`), and `reason` string. | Return 201/redirect. New ReturnRequest record created in database.                                                       |
| 2    | Inspect the created ReturnRequest record.                                                           | `rma_number` matches format `RMA-YYYYMMDD-XXXX` where YYYYMMDD is today's date and XXXX is a zero-padded sequential number. |
| 3    | Verify the `status` field.                                                                          | Status is `requested`.                                                                                                   |
| 4    | Verify `organization_id` on the created record.                                                     | Matches the authenticated user's organization.                                                                           |
| 5    | Verify associated return items.                                                                     | Each submitted order item has a corresponding return item record with correct quantity.                                  |
| 6    | Submit the same request again (duplicate).                                                          | Request is rejected or handled gracefully (no duplicate RMA for the same items).                                         |
| 7    | POST with missing `reason` field.                                                                   | Validation error returned. `reason` is required.                                                                         |
| 8    | POST with missing `order_id` or empty `items` array.                                                | Validation error returned. Both fields are required.                                                                     |
| 9    | POST with an `order_id` belonging to a different organization.                                      | 403 Forbidden or 404 Not Found. No cross-tenant creation.                                                                |

### Test Data

```json
{
    "order_id": "<valid-order-uuid>",
    "items": [
        {
            "order_item_id": "<valid-order-item-uuid>",
            "quantity": 1
        }
    ],
    "reason": "Item arrived damaged"
}
```

### Postconditions

- One new ReturnRequest record exists with status `requested` and a valid RMA number.
- Return items are linked to the correct order items.
- No inventory changes occur at this stage.

### Notes

- The RMA sequential number (XXXX) should be unique per day within the organization.
- UUID primary keys are used on the ReturnRequest model.

---

## TC-FUL-005-03: Approve Return Transitions Status

| Field           | Value                                                        |
|-----------------|--------------------------------------------------------------|
| **ID**          | TC-FUL-005-03                                                |
| **Priority**    | P1                                                           |
| **Type**        | Feature                                                      |
| **Platform**    | Web                                                          |
| **Automated**   | No                                                           |
| **Test File**   | --                                                           |

### Preconditions

1. Authenticated user with fulfillment permissions.
2. A ReturnRequest exists with status `requested` and a valid RMA number.

### Test Steps

| Step | Action                                                                          | Expected Result                                                                    |
|------|---------------------------------------------------------------------------------|------------------------------------------------------------------------------------|
| 1    | POST `fulfillment.returns.approve` with the ReturnRequest ID.                   | Response indicates success (200/redirect).                                         |
| 2    | Reload the ReturnRequest from the database.                                     | `status` is now `approved`.                                                        |
| 3    | Verify `approved_at` timestamp.                                                 | Set to the current timestamp.                                                      |
| 4    | Verify `approved_by` field.                                                     | Set to the authenticated user's ID.                                                |
| 5    | Attempt to approve the same return again.                                       | Request is rejected (return is no longer in `requested` status).                   |

### Test Data

- ReturnRequest in `requested` status with RMA `RMA-20260415-0001`.

### Postconditions

- ReturnRequest status is `approved`.
- `approved_at` and `approved_by` are populated.

### Notes

- Only returns in `requested` status can be approved. See TC-FUL-005-09 for invalid transition testing.

---

## TC-FUL-005-04: Receive Return Sets Metadata

| Field           | Value                                                        |
|-----------------|--------------------------------------------------------------|
| **ID**          | TC-FUL-005-04                                                |
| **Priority**    | P1                                                           |
| **Type**        | Feature                                                      |
| **Platform**    | Web                                                          |
| **Automated**   | No                                                           |
| **Test File**   | --                                                           |

### Preconditions

1. Authenticated user with fulfillment permissions.
2. A ReturnRequest exists with status `approved` (or `shipped_back` if that intermediate state is used).

### Test Steps

| Step | Action                                                                          | Expected Result                                                                         |
|------|---------------------------------------------------------------------------------|-----------------------------------------------------------------------------------------|
| 1    | POST `fulfillment.returns.receive` with the ReturnRequest ID.                   | Response indicates success (200/redirect).                                              |
| 2    | Reload the ReturnRequest from the database.                                     | `status` is now `received`.                                                             |
| 3    | Verify `received_at` timestamp.                                                 | Set to the current timestamp.                                                           |
| 4    | Verify `received_by` field.                                                     | Set to the authenticated user's ID.                                                     |
| 5    | Attempt to receive a return that is in `requested` status (not yet approved).   | Request is rejected with appropriate error (invalid status transition).                  |

### Test Data

- ReturnRequest in `approved` status.

### Postconditions

- ReturnRequest status is `received`.
- `received_at` and `received_by` fields are populated with current time and user ID.

### Notes

- The receive action represents physical receipt of the returned item at the warehouse.
- No inventory adjustment occurs at this step; inventory is updated upon restocking (TC-FUL-005-08).

---

## TC-FUL-005-05: Inspect Return Records Condition

| Field           | Value                                                        |
|-----------------|--------------------------------------------------------------|
| **ID**          | TC-FUL-005-05                                                |
| **Priority**    | P1                                                           |
| **Type**        | Feature                                                      |
| **Platform**    | Web                                                          |
| **Automated**   | No                                                           |
| **Test File**   | --                                                           |

### Preconditions

1. Authenticated user with fulfillment permissions.
2. A ReturnRequest exists with status `received`.

### Test Steps

| Step | Action                                                                                                                     | Expected Result                                                                              |
|------|----------------------------------------------------------------------------------------------------------------------------|----------------------------------------------------------------------------------------------|
| 1    | POST `fulfillment.returns.inspect` with payload: `condition` (e.g., "like_new"), `notes` (string), `restockable` (true).   | Response indicates success (200/redirect).                                                   |
| 2    | Reload the ReturnRequest from the database.                                                                                | `status` is now `inspected`.                                                                 |
| 3    | Verify `condition` field.                                                                                                  | Matches submitted value (e.g., `like_new`).                                                  |
| 4    | Verify `inspection_notes` field.                                                                                           | Matches submitted notes.                                                                     |
| 5    | Verify `restockable` field.                                                                                                | Set to `true`.                                                                               |
| 6    | POST inspect with `restockable` set to `false` on a different received return.                                             | Success. `restockable` is `false` on the record.                                             |
| 7    | POST inspect with missing `condition` field.                                                                               | Validation error. `condition` is required.                                                   |
| 8    | POST inspect with missing `restockable` field.                                                                             | Validation error. `restockable` boolean is required.                                         |
| 9    | POST inspect on a return not in `received` status.                                                                         | Request rejected (invalid status transition).                                                |

### Test Data

```json
{
    "condition": "like_new",
    "notes": "Item is in original packaging, no visible damage.",
    "restockable": true
}
```

### Postconditions

- ReturnRequest status is `inspected`.
- Condition, notes, and restockable flag are recorded.

### Notes

- Common condition values might include: `like_new`, `good`, `fair`, `damaged`, `defective`.
- The `restockable` flag determines whether the item can be returned to sellable inventory (see TC-FUL-005-08).

---

## TC-FUL-005-06: Refund Return Processes Refund

| Field           | Value                                                        |
|-----------------|--------------------------------------------------------------|
| **ID**          | TC-FUL-005-06                                                |
| **Priority**    | P0                                                           |
| **Type**        | Feature                                                      |
| **Platform**    | Web                                                          |
| **Automated**   | No                                                           |
| **Test File**   | --                                                           |

### Preconditions

1. Authenticated user with fulfillment permissions (and refund authorization if applicable).
2. A ReturnRequest exists with status `inspected`.
3. The associated order has payment information available.

### Test Steps

| Step | Action                                                                          | Expected Result                                                                             |
|------|---------------------------------------------------------------------------------|---------------------------------------------------------------------------------------------|
| 1    | POST `fulfillment.returns.refund` with the ReturnRequest ID.                    | Response indicates success (200/redirect).                                                  |
| 2    | Reload the ReturnRequest from the database.                                     | `status` is now `refunded`.                                                                 |
| 3    | Verify `refunded_at` timestamp.                                                 | Set to the current timestamp.                                                               |
| 4    | Verify refund amount.                                                           | Matches the expected refund amount based on returned items.                                  |
| 5    | Attempt to refund a return that is already `refunded`.                          | Request is rejected (already processed).                                                    |
| 6    | Attempt to refund a return in `requested` status (skipping inspection).         | Request is rejected (invalid status transition).                                            |

### Test Data

- ReturnRequest in `inspected` status with at least one return item.

### Postconditions

- ReturnRequest status is `refunded`.
- `refunded_at` is populated.
- If marketplace refund APIs are integrated, the refund is initiated on the originating marketplace.

### Notes

- This is a P0 test case because incorrect refund processing has direct financial impact.
- Verify that partial refunds are handled correctly if only some items from an order are returned.

---

## TC-FUL-005-07: Deny Return with Reason

| Field           | Value                                                        |
|-----------------|--------------------------------------------------------------|
| **ID**          | TC-FUL-005-07                                                |
| **Priority**    | P1                                                           |
| **Type**        | Feature                                                      |
| **Platform**    | Web                                                          |
| **Automated**   | No                                                           |
| **Test File**   | --                                                           |

### Preconditions

1. Authenticated user with fulfillment permissions.
2. A ReturnRequest exists with status `requested`.

### Test Steps

| Step | Action                                                                                              | Expected Result                                                            |
|------|-----------------------------------------------------------------------------------------------------|----------------------------------------------------------------------------|
| 1    | POST `fulfillment.returns.deny` with payload: `reason` (string, up to 500 characters).              | Response indicates success (200/redirect).                                 |
| 2    | Reload the ReturnRequest from the database.                                                         | `status` is now `denied`.                                                  |
| 3    | Verify `denial_reason` field.                                                                       | Matches the submitted reason string.                                       |
| 4    | POST deny with missing `reason` field.                                                              | Validation error. `reason` is required.                                    |
| 5    | POST deny with `reason` exceeding 500 characters.                                                   | Validation error. `reason` must not exceed 500 characters.                 |
| 6    | POST deny on a return already in `denied` status.                                                   | Request is rejected (already denied).                                      |
| 7    | POST deny on a return in `refunded` status.                                                         | Request is rejected (invalid status transition; cannot deny after refund). |

### Test Data

```json
{
    "reason": "Return request is outside the 30-day return window."
}
```

### Postconditions

- ReturnRequest status is `denied`.
- `denial_reason` is recorded.
- No inventory or financial changes occur.

### Notes

- The `reason` field has a maximum length of 500 characters.
- Denial should be possible from `requested` status. Verify whether denial is also allowed from other pre-refund statuses.

---

## TC-FUL-005-08: Restock Item After Inspection

| Field           | Value                                                        |
|-----------------|--------------------------------------------------------------|
| **ID**          | TC-FUL-005-08                                                |
| **Priority**    | P1                                                           |
| **Type**        | Feature                                                      |
| **Platform**    | Web                                                          |
| **Automated**   | No                                                           |
| **Test File**   | --                                                           |

### Preconditions

1. Authenticated user with fulfillment permissions.
2. A ReturnRequest exists with status `inspected` and `restockable` = `true`.
3. At least one valid InventoryLocation exists in the organization.
4. The product associated with the return items has an existing InventoryLevel record at the target location (or the system creates one on restock).

### Test Steps

| Step | Action                                                                                              | Expected Result                                                                                              |
|------|-----------------------------------------------------------------------------------------------------|--------------------------------------------------------------------------------------------------------------|
| 1    | POST `fulfillment.returns.restock` with payload: `location_id` (UUID of target inventory location). | Response indicates success (200/redirect).                                                                   |
| 2    | Verify InventoryLevel for the returned product at the specified location.                           | Quantity is incremented by the returned quantity.                                                             |
| 3    | Verify an InventoryMovement record was created.                                                     | Movement type is `return` (or equivalent), references the ReturnRequest, and shows the quantity added.       |
| 4    | Verify `Product.available_quantity` is updated.                                                     | Incremented by the restocked quantity (this is the cross-marketplace source of truth).                       |
| 5    | Verify PushInventoryToMarketplaces job is dispatched.                                               | Job is queued to sync the updated quantity to eBay/Shopify/Amazon.                                           |
| 6    | POST restock with missing `location_id`.                                                            | Validation error. `location_id` is required.                                                                 |
| 7    | POST restock with an invalid (non-existent) `location_id`.                                          | Validation error or 404.                                                                                     |
| 8    | POST restock on a return where `restockable` = `false`.                                             | Request is rejected. Non-restockable items cannot be returned to inventory.                                  |
| 9    | POST restock with a `location_id` from a different organization.                                    | 403 Forbidden or 404 Not Found. No cross-tenant inventory manipulation.                                     |

### Test Data

```json
{
    "location_id": "<valid-inventory-location-uuid>"
}
```

### Postconditions

- Inventory levels at the target location are increased by the returned quantity.
- An InventoryMovement record documents the restock.
- `Product.available_quantity` reflects the restocked inventory.
- Marketplace inventory sync job is dispatched.

### Notes

- Restocking is only valid for items marked `restockable` = `true` during inspection.
- The `location_id` must belong to the same organization as the return.
- This is the step where inventory actually changes; earlier steps (receive, inspect) do not modify inventory.

---

## TC-FUL-005-09: Invalid Status Transition Guard

| Field           | Value                                                        |
|-----------------|--------------------------------------------------------------|
| **ID**          | TC-FUL-005-09                                                |
| **Priority**    | P1                                                           |
| **Type**        | Negative                                                     |
| **Platform**    | Web                                                          |
| **Automated**   | No                                                           |
| **Test File**   | --                                                           |

### Preconditions

1. Authenticated user with fulfillment permissions.
2. ReturnRequest records exist in each of the following statuses: `approved`, `received`, `inspected`, `refunded`, `denied`, `cancelled`.

### Test Steps

| Step | Action                                                                        | Expected Result                                                             |
|------|-------------------------------------------------------------------------------|-----------------------------------------------------------------------------|
| 1    | POST `fulfillment.returns.approve` on a return with status `approved`.        | Rejected. Cannot approve an already-approved return.                        |
| 2    | POST `fulfillment.returns.approve` on a return with status `received`.        | Rejected. Cannot approve a return that has progressed past `requested`.     |
| 3    | POST `fulfillment.returns.approve` on a return with status `denied`.          | Rejected. Denied returns cannot be approved.                                |
| 4    | POST `fulfillment.returns.approve` on a return with status `refunded`.        | Rejected. Refunded returns cannot be re-approved.                           |
| 5    | POST `fulfillment.returns.approve` on a return with status `cancelled`.       | Rejected. Cancelled returns cannot be approved.                             |
| 6    | POST `fulfillment.returns.receive` on a return with status `requested`.       | Rejected. Must be approved before receiving.                                |
| 7    | POST `fulfillment.returns.inspect` on a return with status `approved`.        | Rejected. Must be received before inspection.                               |
| 8    | POST `fulfillment.returns.refund` on a return with status `requested`.        | Rejected. Must go through approval, receiving, and inspection first.        |
| 9    | POST `fulfillment.returns.refund` on a return with status `denied`.           | Rejected. Denied returns cannot be refunded.                                |

### Test Data

- One ReturnRequest per status listed in preconditions.

### Postconditions

- No status changes occur on any of the tested records.
- All records retain their original status.

### Notes

- Valid status transitions:
  - `requested` -> `approved` or `denied`
  - `approved` -> `shipped_back` (if tracked) or `received`
  - `shipped_back` -> `received`
  - `received` -> `inspected`
  - `inspected` -> `refunded` or `denied`
- Any transition outside this flow should be rejected with an appropriate error message.

---

## TC-FUL-005-10: Return Show Page Displays Details

| Field           | Value                                                        |
|-----------------|--------------------------------------------------------------|
| **ID**          | TC-FUL-005-10                                                |
| **Priority**    | P2                                                           |
| **Type**        | Feature                                                      |
| **Platform**    | Web                                                          |
| **Automated**   | No                                                           |
| **Test File**   | --                                                           |

### Preconditions

1. Authenticated user with fulfillment permissions.
2. A ReturnRequest exists with status `refunded` (so all lifecycle timestamps are populated).
3. The return has associated order, order items, and a timeline of status changes.

### Test Steps

| Step | Action                                                          | Expected Result                                                                                                      |
|------|-----------------------------------------------------------------|----------------------------------------------------------------------------------------------------------------------|
| 1    | GET `fulfillment.returns.show` with a valid ReturnRequest ID.   | Page loads successfully with return details.                                                                         |
| 2    | Verify RMA number is displayed.                                 | RMA number in format `RMA-YYYYMMDD-XXXX` is shown prominently.                                                      |
| 3    | Verify order information is displayed.                          | Order reference/number, customer name, and order date are visible.                                                   |
| 4    | Verify returned items list.                                     | Each returned item shows product name/SKU, quantity returned, and reason.                                            |
| 5    | Verify status timeline.                                         | Timeline shows each status transition with timestamp and acting user (e.g., requested, approved, received, inspected, refunded). |
| 6    | Verify current status badge.                                    | Status badge shows `refunded` with appropriate styling.                                                              |
| 7    | Verify inspection details are shown (if inspected).             | Condition, inspection notes, and restockable flag are displayed.                                                     |
| 8    | GET show page with a ReturnRequest ID from another organization.| 403 Forbidden or 404 Not Found. No cross-tenant access.                                                             |

### Test Data

- A fully-processed ReturnRequest (status `refunded`) with complete lifecycle history.

### Postconditions

- No data is modified. Read-only operation.

### Notes

- The show page serves as the central detail view for a return, used by fulfillment staff to review the full history.
- Action buttons (approve, receive, inspect, refund, deny) should only appear when the return is in a status that allows that action.
