# TC-FUL-008: Tracking Upload Test Cases

| Field              | Value                                           |
|--------------------|------------------------------------------------|
| **Document ID**    | TC-FUL-008                                      |
| **Domain**         | Fulfillment                                     |
| **Related Test Plan** | `docs/testing/plans/fulfillment-test-plan.md` |
| **Last Updated**   | 2026-04-15                                      |

## Overview

Test cases for the tracking upload pipeline: when a shipment is confirmed, tracking information is dispatched to the originating marketplace (eBay, Shopify, Amazon) via dedicated jobs. Covers dispatch triggering, success/failure handling, retry behavior, webhook-driven status updates, and delivery confirmation.

**Services:** `App\Services\Fulfillment\TrackingService`
**Jobs:** `App\Jobs\Fulfillment\UploadTrackingToEbay`, `App\Jobs\Fulfillment\UploadTrackingToShopify`, `App\Jobs\Fulfillment\UploadTrackingToAmazon`
**Model:** `App\Models\Fulfillment\Shipment`

---

## TC-FUL-008-01: Ship Confirm Dispatches UploadTrackingToEbay

| Field           | Value                                                        |
|-----------------|--------------------------------------------------------------|
| **ID**          | TC-FUL-008-01                                                |
| **Priority**    | P0                                                           |
| **Type**        | Integration                                                  |
| **Platform**    | Web                                                          |
| **Automated**   | Yes                                                          |
| **Test File**   | `tests/Feature/Fulfillment/MultiMarketplaceTrackingTest.php` |

### Preconditions

1. Authenticated user with fulfillment permissions.
2. An Order exists with `marketplace` = `ebay` and a valid `marketplace_order_id` (eBay order reference).
3. The order has been picked and packed (status allows ship confirmation).
4. Valid eBay IntegrationCredential exists for the organization with active OAuth tokens.
5. Queue is configured for testing (sync driver or `Bus::fake()`).

### Test Steps

| Step | Action                                                                                                                                       | Expected Result                                                                                                                |
|------|----------------------------------------------------------------------------------------------------------------------------------------------|--------------------------------------------------------------------------------------------------------------------------------|
| 1    | Confirm shipment on the eBay order by calling the ship confirm action with `tracking_number` and `carrier` (e.g., `USPS`).                   | Shipment record created with `tracking_number`, `carrier`, and `shipped_at` timestamp.                                         |
| 2    | Verify that `UploadTrackingToEbay` job is dispatched.                                                                                        | Job is dispatched (or queued) with the correct Shipment ID and eBay order reference.                                           |
| 3    | Verify job payload contains required eBay API parameters.                                                                                    | Payload includes `tracking_number`, `carrier` (mapped to eBay carrier enum), and `marketplace_order_id`.                       |
| 4    | Simulate successful job execution (mock eBay Fulfillment API response).                                                                      | Job completes without exception. Shipment record updated (see TC-FUL-008-04).                                                 |
| 5    | Verify no `UploadTrackingToShopify` or `UploadTrackingToAmazon` job is dispatched.                                                           | Only the eBay tracking job is dispatched for an eBay order.                                                                   |

### Test Data

```php
$order = Order::factory()->create([
    'marketplace' => 'ebay',
    'marketplace_order_id' => 'eBay-ORDER-123456',
    'organization_id' => $organization->id,
]);

$shipmentData = [
    'tracking_number' => '9400111899223456789012',
    'carrier' => 'USPS',
];
```

### Postconditions

- Shipment record exists with tracking data.
- `UploadTrackingToEbay` job is dispatched exactly once.
- No other marketplace tracking jobs are dispatched.

### Notes

- The automated test uses `Bus::fake()` to assert job dispatch without actually calling the eBay API.
- eBay carrier mapping: the carrier string (e.g., `USPS`) must be mapped to eBay's accepted `ShippingCarrierCodeEnum` values.

---

## TC-FUL-008-02: Ship Confirm Dispatches UploadTrackingToShopify

| Field           | Value                                                        |
|-----------------|--------------------------------------------------------------|
| **ID**          | TC-FUL-008-02                                                |
| **Priority**    | P0                                                           |
| **Type**        | Integration                                                  |
| **Platform**    | Web                                                          |
| **Automated**   | Yes                                                          |
| **Test File**   | `tests/Feature/Fulfillment/MultiMarketplaceTrackingTest.php` |

### Preconditions

1. Authenticated user with fulfillment permissions.
2. An Order exists with `marketplace` = `shopify` and a valid `marketplace_order_id` (Shopify order ID).
3. The order has been picked and packed.
4. Valid Shopify IntegrationCredential exists for the organization.
5. Queue is configured for testing.

### Test Steps

| Step | Action                                                                                                                                       | Expected Result                                                                                                                  |
|------|----------------------------------------------------------------------------------------------------------------------------------------------|----------------------------------------------------------------------------------------------------------------------------------|
| 1    | Confirm shipment on the Shopify order with `tracking_number`, `carrier`, and optional `tracking_url`.                                        | Shipment record created with tracking data.                                                                                      |
| 2    | Verify that `UploadTrackingToShopify` job is dispatched.                                                                                     | Job is dispatched with the correct Shipment ID and Shopify order reference.                                                     |
| 3    | Verify job payload contains required Shopify API parameters.                                                                                 | Payload includes `tracking_number`, `tracking_company` (Shopify carrier name), `tracking_url`, and Shopify `fulfillment_id`.     |
| 4    | Simulate successful job execution (mock Shopify Fulfillment API response).                                                                   | Job completes. Shipment record updated.                                                                                          |
| 5    | Verify no `UploadTrackingToEbay` or `UploadTrackingToAmazon` job is dispatched.                                                              | Only the Shopify tracking job is dispatched.                                                                                    |

### Test Data

```php
$order = Order::factory()->create([
    'marketplace' => 'shopify',
    'marketplace_order_id' => 'shopify-order-789',
    'organization_id' => $organization->id,
]);

$shipmentData = [
    'tracking_number' => '1Z999AA10123456784',
    'carrier' => 'UPS',
    'tracking_url' => 'https://www.ups.com/track?tracknum=1Z999AA10123456784',
];
```

### Postconditions

- Shipment record exists with tracking data.
- `UploadTrackingToShopify` job is dispatched exactly once.

### Notes

- Shopify uses `tracking_company` (not `carrier`) in its Fulfillment API.
- The job must create a Shopify Fulfillment if one does not already exist, or update the existing one with tracking.

---

## TC-FUL-008-03: Ship Confirm Dispatches UploadTrackingToAmazon

| Field           | Value                                                        |
|-----------------|--------------------------------------------------------------|
| **ID**          | TC-FUL-008-03                                                |
| **Priority**    | P0                                                           |
| **Type**        | Integration                                                  |
| **Platform**    | Web                                                          |
| **Automated**   | Yes                                                          |
| **Test File**   | `tests/Feature/Fulfillment/MultiMarketplaceTrackingTest.php` |

### Preconditions

1. Authenticated user with fulfillment permissions.
2. An Order exists with `marketplace` = `amazon` and a valid `marketplace_order_id` (Amazon order reference).
3. The order has been picked and packed.
4. Valid Amazon IntegrationCredential exists for the organization.
5. Queue is configured for testing.

### Test Steps

| Step | Action                                                                                                                                       | Expected Result                                                                                                                |
|------|----------------------------------------------------------------------------------------------------------------------------------------------|--------------------------------------------------------------------------------------------------------------------------------|
| 1    | Confirm shipment on the Amazon order with `tracking_number` and `carrier`.                                                                   | Shipment record created with tracking data.                                                                                    |
| 2    | Verify that `UploadTrackingToAmazon` job is dispatched.                                                                                      | Job is dispatched with the correct Shipment ID and Amazon order reference.                                                    |
| 3    | Verify job payload contains required Amazon SP-API parameters.                                                                               | Payload includes `tracking_number`, `carrier_code` (Amazon carrier enum), and `marketplace_order_id`.                          |
| 4    | Simulate successful job execution (mock Amazon Feeds/Orders API response).                                                                   | Job completes. Shipment record updated.                                                                                       |
| 5    | Verify no `UploadTrackingToEbay` or `UploadTrackingToShopify` job is dispatched.                                                             | Only the Amazon tracking job is dispatched.                                                                                   |

### Test Data

```php
$order = Order::factory()->create([
    'marketplace' => 'amazon',
    'marketplace_order_id' => 'AMZ-114-1234567-8901234',
    'organization_id' => $organization->id,
]);

$shipmentData = [
    'tracking_number' => '794644790132',
    'carrier' => 'FedEx',
];
```

### Postconditions

- Shipment record exists with tracking data.
- `UploadTrackingToAmazon` job is dispatched exactly once.

### Notes

- Amazon carrier mapping: the carrier string must be mapped to Amazon's accepted `CarrierCode` values.
- Amazon may require order item IDs in the fulfillment confirmation payload.

---

## TC-FUL-008-04: Tracking Upload Sets Success Timestamps

| Field           | Value                                                        |
|-----------------|--------------------------------------------------------------|
| **ID**          | TC-FUL-008-04                                                |
| **Priority**    | P0                                                           |
| **Type**        | Feature                                                      |
| **Platform**    | Web                                                          |
| **Automated**   | Partial                                                      |
| **Test File**   | `tests/Feature/Fulfillment/MultiMarketplaceTrackingTest.php` |

### Preconditions

1. A Shipment record exists with `tracking_number` and `carrier` populated.
2. `tracking_uploaded_at` is `null` and `tracking_uploaded_to_marketplace` is `false`.
3. The corresponding marketplace tracking upload job (eBay, Shopify, or Amazon) is about to execute.

### Test Steps

| Step | Action                                                                                                          | Expected Result                                                                                                           |
|------|-----------------------------------------------------------------------------------------------------------------|---------------------------------------------------------------------------------------------------------------------------|
| 1    | Execute the marketplace tracking upload job (e.g., `UploadTrackingToEbay`) with a mocked successful API call.   | Job completes without exception.                                                                                          |
| 2    | Reload the Shipment record from the database.                                                                   | `tracking_uploaded_at` is set to the current timestamp.                                                                   |
| 3    | Verify `tracking_uploaded_to_marketplace` flag.                                                                 | Set to `true`.                                                                                                            |
| 4    | Execute the job again on the same shipment (idempotency check).                                                 | Job either skips (already uploaded) or completes successfully without creating a duplicate on the marketplace.             |
| 5    | Verify the Shipment record after the duplicate execution.                                                       | `tracking_uploaded_at` and `tracking_uploaded_to_marketplace` remain unchanged (no double-write).                         |

### Test Data

- A Shipment with:
  - `tracking_number`: `9400111899223456789012`
  - `carrier`: `USPS`
  - `tracking_uploaded_at`: `null`
  - `tracking_uploaded_to_marketplace`: `false`

### Postconditions

- `tracking_uploaded_at` is a valid timestamp.
- `tracking_uploaded_to_marketplace` is `true`.
- Marketplace API was called exactly once (or is idempotent on re-execution).

### Notes

- "Partial" automation: the automated test verifies the flag is set on job dispatch, but the actual API call success path may require integration testing or mocked responses.
- The `tracking_uploaded_to_marketplace` boolean is used by the retry mechanism (TC-FUL-008-10) to find failed uploads.

---

## TC-FUL-008-05: Tracking Upload Retries on Failure

| Field           | Value                                                        |
|-----------------|--------------------------------------------------------------|
| **ID**          | TC-FUL-008-05                                                |
| **Priority**    | P1                                                           |
| **Type**        | Feature                                                      |
| **Platform**    | Web                                                          |
| **Automated**   | No                                                           |
| **Test File**   | --                                                           |

### Preconditions

1. A Shipment record exists with tracking data.
2. The marketplace tracking upload job is configured with `$tries = 3` and `$backoff = 60` (or equivalent retry configuration).
3. The marketplace API is returning errors (simulated via mock or test mode).

### Test Steps

| Step | Action                                                                                                          | Expected Result                                                                                                                   |
|------|-----------------------------------------------------------------------------------------------------------------|-----------------------------------------------------------------------------------------------------------------------------------|
| 1    | Dispatch the tracking upload job. Marketplace API returns a 500 error.                                          | Job fails on first attempt. Released back to queue for retry.                                                                     |
| 2    | Wait for the first retry (~60 seconds backoff).                                                                 | Job retries (attempt 2). API still returns 500. Released back to queue.                                                           |
| 3    | Wait for the second retry (~60 seconds backoff).                                                                | Job retries (attempt 3). API still returns 500. Job permanently fails after 3 attempts.                                           |
| 4    | Verify the Shipment record after all retries exhausted.                                                         | `tracking_uploaded_at` remains `null`. `tracking_uploaded_to_marketplace` remains `false`.                                        |
| 5    | Verify the job appears in the `failed_jobs` table.                                                              | Failed job record exists with exception details from the last attempt.                                                            |
| 6    | Simulate the API recovering on the second attempt. Dispatch the job, first attempt fails, second succeeds.      | Job succeeds on attempt 2. `tracking_uploaded_at` is set. `tracking_uploaded_to_marketplace` is `true`.                           |

### Test Data

- Shipment with tracking data.
- Mocked marketplace API responses: 500 for failure, 200 for success.

### Postconditions

- After exhausting retries: shipment remains unuploaded, job is in `failed_jobs`.
- After retry success: shipment is marked as uploaded.

### Notes

- The `$backoff` property on the job class determines retry delay. Verify the actual value in the job class definition.
- With `$tries = 3`, the job executes at most 3 times (1 initial + 2 retries, or 3 attempts total depending on Laravel convention).
- The 60-second backoff may be exponential (60, 120, 240) depending on configuration.

---

## TC-FUL-008-06: Failed Tracking Upload Does Not Revert Shipment

| Field           | Value                                                        |
|-----------------|--------------------------------------------------------------|
| **ID**          | TC-FUL-008-06                                                |
| **Priority**    | P1                                                           |
| **Type**        | Negative                                                     |
| **Platform**    | Web                                                          |
| **Automated**   | No                                                           |
| **Test File**   | --                                                           |

### Preconditions

1. A Shipment record exists with `status` = `shipped`, `tracking_number`, `carrier`, and `shipped_at` all populated.
2. The tracking upload job has been dispatched and is about to fail (marketplace API error).

### Test Steps

| Step | Action                                                                                                          | Expected Result                                                                                                         |
|------|-----------------------------------------------------------------------------------------------------------------|-------------------------------------------------------------------------------------------------------------------------|
| 1    | Execute the tracking upload job. Marketplace API returns a 400/500 error. Job fails.                            | Job throws exception, moves to retry or failed state.                                                                   |
| 2    | Reload the Shipment record from the database.                                                                   | `status` remains `shipped`. NOT reverted to a prior status (e.g., `packed` or `ready`).                                 |
| 3    | Verify `shipped_at` timestamp.                                                                                  | Still populated. Not nulled out.                                                                                        |
| 4    | Verify `tracking_number` and `carrier`.                                                                         | Still populated. Not cleared.                                                                                           |
| 5    | Verify `tracking_uploaded_to_marketplace` flag.                                                                 | Remains `false` (upload did not succeed).                                                                               |
| 6    | Check Laravel log or notification system.                                                                       | Error is logged with the marketplace API error details (response code, message).                                        |

### Test Data

- Shipment in `shipped` status with tracking data.
- Mocked marketplace API returning 400 Bad Request or 500 Internal Server Error.

### Postconditions

- Shipment status and tracking data are unchanged.
- Only `tracking_uploaded_to_marketplace` reflects the failure (remains `false`).
- Error is logged for admin review.

### Notes

- The shipment represents a physical event (the package was shipped). Even if the marketplace API fails, the physical shipment has occurred and should not be reverted.
- The failed upload can be retried manually or via the automated retry mechanism (TC-FUL-008-10).

---

## TC-FUL-008-07: EasyPost Tracking Webhook Updates Shipment Status

| Field           | Value                                                        |
|-----------------|--------------------------------------------------------------|
| **ID**          | TC-FUL-008-07                                                |
| **Priority**    | P1                                                           |
| **Type**        | Integration                                                  |
| **Platform**    | Web                                                          |
| **Automated**   | No                                                           |
| **Test File**   | --                                                           |

### Preconditions

1. EasyPost webhook endpoint is configured and reachable.
2. A Shipment exists with `tracking_number` matching the EasyPost tracker.
3. EasyPost webhook secret is configured for signature verification.

### Test Steps

| Step | Action                                                                                                                            | Expected Result                                                                                                              |
|------|-----------------------------------------------------------------------------------------------------------------------------------|------------------------------------------------------------------------------------------------------------------------------|
| 1    | Simulate EasyPost webhook POST with event `tracker.updated`, status `in_transit`, for the shipment's tracking number.             | Webhook received (200 response). Shipment `tracking_status` updated to `in_transit`.                                         |
| 2    | Verify the Shipment record in the database.                                                                                       | `tracking_status` = `in_transit`. `tracking_status_updated_at` set to current timestamp.                                     |
| 3    | Simulate EasyPost webhook POST with event `tracker.updated`, status `out_for_delivery`.                                           | Shipment `tracking_status` updated to `out_for_delivery`.                                                                    |
| 4    | Simulate EasyPost webhook POST with event `tracker.updated`, status `delivered`.                                                  | Shipment `tracking_status` updated to `delivered`. Triggers delivery handling (see TC-FUL-008-09).                            |
| 5    | Simulate EasyPost webhook with an invalid signature.                                                                              | Webhook rejected (401 or 403). No shipment update occurs.                                                                    |
| 6    | Simulate EasyPost webhook for a tracking number that does not match any shipment.                                                 | Webhook accepted (200, to prevent retries) but no records are updated. Event logged for debugging.                            |

### Test Data

```json
{
    "description": "EasyPost tracker.updated event",
    "mode": "test",
    "object": "Event",
    "result": {
        "object": "Tracker",
        "tracking_code": "9400111899223456789012",
        "status": "in_transit",
        "carrier": "USPS",
        "tracking_details": [
            {
                "message": "Arrived at USPS Regional Facility",
                "status": "in_transit",
                "datetime": "2026-04-15T10:30:00Z"
            }
        ]
    }
}
```

### Postconditions

- Shipment tracking status reflects the latest EasyPost webhook event.
- Invalid or unmatched webhooks are handled gracefully without errors.

### Notes

- EasyPost tracker statuses: `unknown`, `pre_transit`, `in_transit`, `out_for_delivery`, `delivered`, `available_for_pickup`, `return_to_sender`, `failure`, `cancelled`, `error`.
- The webhook endpoint must verify the EasyPost signature to prevent spoofed updates.

---

## TC-FUL-008-08: ShippingEasy Webhook Updates Tracking with HMAC Verification

| Field           | Value                                                        |
|-----------------|--------------------------------------------------------------|
| **ID**          | TC-FUL-008-08                                                |
| **Priority**    | P1                                                           |
| **Type**        | Integration                                                  |
| **Platform**    | Web                                                          |
| **Automated**   | Yes                                                          |
| **Test File**   | `tests/Feature/Fulfillment/ShippingEasyWebhookTest.php`      |

### Preconditions

1. ShippingEasy webhook endpoint is configured.
2. ShippingEasy store API key is stored in IntegrationCredential for HMAC verification.
3. A Shipment exists that corresponds to the ShippingEasy shipment reference.

### Test Steps

| Step | Action                                                                                                                         | Expected Result                                                                                                           |
|------|--------------------------------------------------------------------------------------------------------------------------------|---------------------------------------------------------------------------------------------------------------------------|
| 1    | POST to ShippingEasy webhook endpoint with valid HMAC signature and payload containing `tracking_number` and `carrier`.        | Webhook accepted (200). Shipment record updated with tracking data from ShippingEasy.                                     |
| 2    | Verify HMAC signature validation.                                                                                              | Signature is computed using the store API key and matches the `X-SE-Signature` header.                                    |
| 3    | Verify Shipment record is updated.                                                                                             | `tracking_number` and `carrier` match the webhook payload. `tracking_status` updated if status info is included.          |
| 4    | POST with an invalid HMAC signature.                                                                                           | Webhook rejected (401 or 403). No shipment update occurs.                                                                |
| 5    | POST with a valid signature but no matching shipment in the database.                                                          | Webhook accepted (200) but no records updated. Event logged.                                                              |
| 6    | POST with a valid signature and a shipment that already has tracking uploaded to marketplace.                                   | Webhook accepted. Tracking data updated (ShippingEasy may provide updated carrier/tracking info).                         |

### Test Data

- ShippingEasy webhook payload with HMAC header.
- Store API key for HMAC computation.

### Postconditions

- Shipment tracking data reflects the ShippingEasy webhook payload (on valid requests).
- Invalid signatures are rejected without data modification.

### Notes

- The automated test in `ShippingEasyWebhookTest.php` covers HMAC verification and basic webhook processing.
- ShippingEasy integration details are documented in the project memory under "ShippingEasy Integration."

---

## TC-FUL-008-09: Delivery Confirmation Triggers handleDelivery

| Field           | Value                                                        |
|-----------------|--------------------------------------------------------------|
| **ID**          | TC-FUL-008-09                                                |
| **Priority**    | P1                                                           |
| **Type**        | Feature                                                      |
| **Platform**    | Web                                                          |
| **Automated**   | No                                                           |
| **Test File**   | --                                                           |

### Preconditions

1. A Shipment exists with `tracking_status` = `in_transit` and an associated Order.
2. The Order has `status` = `shipped` (or equivalent post-ship status).
3. A tracking webhook (EasyPost or ShippingEasy) delivers a `delivered` status update.

### Test Steps

| Step | Action                                                                                                  | Expected Result                                                                                                       |
|------|---------------------------------------------------------------------------------------------------------|-----------------------------------------------------------------------------------------------------------------------|
| 1    | Receive a tracking webhook with status `delivered` for the shipment.                                    | Shipment `tracking_status` updated to `delivered`.                                                                    |
| 2    | Verify the `handleDelivery` method (or equivalent) is triggered.                                        | Delivery handling logic executes.                                                                                     |
| 3    | Verify the associated Order status.                                                                     | Order `status` updated to `delivered` (or equivalent completed status).                                               |
| 4    | Verify `delivered_at` timestamp on the Shipment.                                                        | Set to the delivery timestamp from the webhook event (or current time).                                               |
| 5    | Verify `delivered_at` or equivalent on the Order.                                                       | Set to reflect the delivery event.                                                                                    |
| 6    | Receive a second `delivered` webhook for the same shipment (duplicate).                                 | No error. Idempotent handling: status remains `delivered`, no duplicate processing.                                    |
| 7    | Verify that delivery does not trigger for a multi-shipment order where only one shipment is delivered.   | Order status remains `shipped` (or `partially_delivered`) until all shipments are delivered (if applicable).           |

### Test Data

- Shipment in `in_transit` status with associated Order in `shipped` status.
- Webhook payload with `status: "delivered"` and a delivery timestamp.

### Postconditions

- Shipment is marked as delivered.
- Order status reflects the delivery.
- Duplicate delivery events are handled idempotently.

### Notes

- The `handleDelivery` method may also trigger notifications (e.g., email to the customer or internal notification).
- For multi-item orders with multiple shipments, the order should only be marked `delivered` when all shipments are delivered.

---

## TC-FUL-008-10: Retry Failed Tracking Uploads

| Field           | Value                                                        |
|-----------------|--------------------------------------------------------------|
| **ID**          | TC-FUL-008-10                                                |
| **Priority**    | P2                                                           |
| **Type**        | Feature                                                      |
| **Platform**    | Web                                                          |
| **Automated**   | No                                                           |
| **Test File**   | --                                                           |

### Preconditions

1. Multiple Shipment records exist where `tracking_uploaded_to_marketplace` = `false` and `shipped_at` is not null (i.e., the shipment was confirmed but tracking upload failed).
2. At least one shipment has a `shipped_at` timestamp older than the retry threshold (if any time-based filtering is applied).
3. The marketplace APIs are now available (the transient error that caused the original failure has been resolved).

### Test Steps

| Step | Action                                                                                                                              | Expected Result                                                                                                              |
|------|-------------------------------------------------------------------------------------------------------------------------------------|------------------------------------------------------------------------------------------------------------------------------|
| 1    | Invoke `retryFailedTrackingUploads` (via artisan command, scheduled task, or manual trigger).                                        | Method queries Shipment records where `tracking_uploaded_to_marketplace` = `false` and `shipped_at` is not null.             |
| 2    | Verify the correct shipments are identified.                                                                                        | Only shipments with failed tracking uploads are included. Shipments that were never shipped (no `shipped_at`) are excluded.  |
| 3    | Verify that a tracking upload job is dispatched for each identified shipment.                                                        | One `UploadTrackingToEbay`/`Shopify`/`Amazon` job per shipment, based on the order's marketplace.                            |
| 4    | Simulate successful execution of the retried jobs.                                                                                  | Each shipment's `tracking_uploaded_at` is set and `tracking_uploaded_to_marketplace` is set to `true`.                       |
| 5    | Run `retryFailedTrackingUploads` again.                                                                                             | No shipments are identified (all have been successfully uploaded). No jobs dispatched.                                       |
| 6    | Verify that shipments with `tracking_uploaded_to_marketplace` = `true` are excluded.                                                | Already-uploaded shipments are not re-dispatched.                                                                            |

### Test Data

- Shipments with `tracking_uploaded_to_marketplace` = `false`:
  - Shipment A: eBay order, shipped 2 hours ago, tracking upload failed.
  - Shipment B: Shopify order, shipped 30 minutes ago, tracking upload failed.
  - Shipment C: Amazon order, shipped 1 day ago, tracking upload failed.
- Shipments with `tracking_uploaded_to_marketplace` = `true`:
  - Shipment D: eBay order, successfully uploaded (should be excluded).

### Postconditions

- All previously failed tracking uploads are retried.
- Successfully retried shipments are marked as uploaded.
- No duplicate uploads occur for already-successful shipments.

### Notes

- This mechanism serves as a safety net for transient marketplace API failures (network timeouts, rate limits, temporary outages).
- The retry should be scheduled (e.g., via Laravel's task scheduler) to run periodically (e.g., every 15 minutes) or be available as a manual artisan command.
- Consider adding a maximum retry age (e.g., do not retry shipments older than 7 days) to avoid infinitely retrying permanently failed uploads.
