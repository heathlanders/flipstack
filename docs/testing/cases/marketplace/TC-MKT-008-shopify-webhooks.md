# TC-MKT-008: Shopify Webhooks

| Field              | Value                                                                 |
|--------------------|-----------------------------------------------------------------------|
| **Document ID**    | TC-MKT-008                                                            |
| **Domain**         | Marketplace Integration - Shopify Webhooks                            |
| **Related Test Plan** | [Feature Testing Plan - Phase 1](../../project_feature_testing_plan.md) |
| **Last Updated**   | 2026-04-15                                                            |

---

## TC-MKT-008-01: Valid HMAC Passes Verification

| Field          | Value                                                              |
|----------------|--------------------------------------------------------------------|
| **ID**         | TC-MKT-008-01                                                      |
| **Priority**   | Critical                                                           |
| **Type**       | Security                                                           |
| **Platform**   | Shopify                                                            |
| **Automated**  | Yes                                                                |
| **Test File**  | `tests/Feature/Shopify/ShopifyWebhookTest.php`                     |

### Preconditions
- Organization has an active Shopify IntegrationCredential.
- Shopify API secret is configured in environment variables.
- Webhook endpoint `POST /api/webhooks/shopify` is accessible without authentication middleware (webhooks come from Shopify servers, not authenticated users).

### Test Steps

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Construct a webhook payload with valid JSON body. | Payload represents a Shopify event (e.g., `orders/create`). |
| 2 | Compute the HMAC-SHA256 of the raw request body using the Shopify API secret. | HMAC value calculated. |
| 3 | Send POST to `/api/webhooks/shopify` with header `X-Shopify-Hmac-Sha256` set to the base64-encoded HMAC. | Request sent with valid HMAC. |
| 4 | Application verifies the HMAC. | Computed HMAC matches the header value. |
| 5 | Verify the request is accepted. | HTTP 200 response returned. |
| 6 | Verify the webhook is dispatched for processing. | `ProcessShopifyWebhook` job is queued. |

### Test Data
- Payload: `{"id": 820982911946154500, "email": "buyer@example.com", "created_at": "2026-04-15T10:00:00-04:00"}`.
- HMAC computed with: `hash_hmac('sha256', $rawBody, $shopifyApiSecret, true)` then base64-encoded.

### Postconditions
- Webhook accepted and job dispatched.

### Notes
- HMAC verification must use the raw request body (not parsed JSON) to ensure the signature matches exactly. Reading the body via `file_get_contents('php://input')` or Laravel's `$request->getContent()` is required.

---

## TC-MKT-008-02: Invalid HMAC Returns 401

| Field          | Value                                                              |
|----------------|--------------------------------------------------------------------|
| **ID**         | TC-MKT-008-02                                                      |
| **Priority**   | Critical                                                           |
| **Type**       | Security                                                           |
| **Platform**   | Shopify                                                            |
| **Automated**  | Yes                                                                |
| **Test File**  | `tests/Feature/Shopify/ShopifyWebhookTest.php`                     |

### Preconditions
- Webhook endpoint is accessible.
- Shopify API secret is configured.

### Test Steps

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Construct a webhook payload with valid JSON body. | Payload prepared. |
| 2 | Set `X-Shopify-Hmac-Sha256` header to an incorrect/tampered HMAC value. | Header contains a value that will not match the computed HMAC. |
| 3 | Send POST to `/api/webhooks/shopify`. | Request sent with invalid HMAC. |
| 4 | Application computes the expected HMAC and compares. | Mismatch detected. |
| 5 | Verify the request is rejected. | HTTP 401 Unauthorized response returned. |
| 6 | Verify no webhook job is dispatched. | `ProcessShopifyWebhook` job is NOT on the queue. |
| 7 | Verify the rejection is logged. | Log entry with the received HMAC, expected HMAC prefix, and source IP for security auditing. |

### Test Data
- Valid payload body.
- Tampered HMAC: `"invalid_hmac_value_abc123"`.

### Postconditions
- Request rejected; no processing occurs.
- Security event logged.

### Notes
- The comparison must use `hash_equals()` (constant-time comparison) to prevent timing attacks. Using `===` or `==` is vulnerable.

---

## TC-MKT-008-03: orders/create Topic Creates Order and Reserves Inventory

| Field          | Value                                                              |
|----------------|--------------------------------------------------------------------|
| **ID**         | TC-MKT-008-03                                                      |
| **Priority**   | Critical                                                           |
| **Type**       | Integration                                                        |
| **Platform**   | Shopify                                                            |
| **Automated**  | Yes                                                                |
| **Test File**  | `tests/Feature/Shopify/ShopifyWebhookTest.php`                     |

### Preconditions
- Valid HMAC (webhook passes verification).
- Shopify sends an `orders/create` webhook.
- Product exists in the system with `available_quantity = 10` and a ShopifyListing linked via `shopify_product_id` / `shopify_variant_id`.
- `X-Shopify-Topic` header is `orders/create`.

### Test Steps

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Webhook received with `X-Shopify-Topic: orders/create` and valid HMAC. | HTTP 200 returned immediately. |
| 2 | `ProcessShopifyWebhook` job is dispatched and processes the order payload. | Job parses the Shopify order data. |
| 3 | Job identifies the product(s) in the order via `variant_id` matching to ShopifyListing. | Product matched successfully. |
| 4 | Job calls `reserveInventoryForOrder()` on the matched product. | `Product.available_quantity` decremented by the ordered quantity. |
| 5 | Verify `available_quantity` is updated. | If order is for 2 units: `available_quantity` drops from 10 to 8. |
| 6 | Verify an Order record is created in the system. | Order exists with `source=shopify`, `external_order_id` set to the Shopify order ID, line items linked to products. |
| 7 | Verify `PushInventoryToMarketplaces` job is dispatched. | Job queued to push the new `available_quantity` to all connected marketplaces (including eBay). |

### Test Data
- Shopify order payload:
  ```json
  {
    "id": 820982911946154500,
    "order_number": 1001,
    "line_items": [{
      "variant_id": 44556677,
      "quantity": 2,
      "price": "59.99",
      "sku": "WH-100"
    }],
    "shipping_address": {
      "first_name": "John",
      "last_name": "Doe",
      "address1": "123 Main St",
      "city": "Portland",
      "province": "Oregon",
      "zip": "97201",
      "country": "US"
    }
  }
  ```

### Postconditions
- `Product.available_quantity` reduced by ordered quantity.
- Order record created with line items.
- Inventory push job dispatched to sync other marketplaces.

### Notes
- Inventory reservation happens immediately and synchronously within the `ProcessShopifyWebhook` job. The `PushInventoryToMarketplaces` job is dispatched asynchronously afterward. This ensures oversell prevention across marketplaces.

---

## TC-MKT-008-04: products/update Topic Updates Local Listing

| Field          | Value                                                              |
|----------------|--------------------------------------------------------------------|
| **ID**         | TC-MKT-008-04                                                      |
| **Priority**   | High                                                               |
| **Type**       | Integration                                                        |
| **Platform**   | Shopify                                                            |
| **Automated**  | Yes                                                                |
| **Test File**  | `tests/Feature/Shopify/ShopifyWebhookTest.php`                     |

### Preconditions
- Valid HMAC (webhook passes verification).
- `X-Shopify-Topic` header is `products/update`.
- ShopifyListing exists for the product referenced in the webhook payload.
- Product was updated directly on Shopify (e.g., merchant edited the title in Shopify admin).

### Test Steps

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Webhook received with `X-Shopify-Topic: products/update`. | HTTP 200 returned. |
| 2 | `ProcessShopifyWebhook` job processes the product update. | Job parses the Shopify product data. |
| 3 | Job matches the Shopify product to the local ShopifyListing via `shopify_product_id`. | ShopifyListing found. |
| 4 | Job updates the ShopifyListing metadata with the latest Shopify data. | `last_synced_at` updated. Relevant fields (status, price on Shopify) recorded. |
| 5 | Verify the local Product record is NOT automatically overwritten. | Product title, description, and price remain as set in the application. The webhook logs the Shopify-side change but does not auto-overwrite local data. |
| 6 | Verify a notification or flag is set if data diverges. | If the Shopify title differs from the local title, a `sync_diverged` flag or log entry is created for user review. |

### Test Data
- Shopify product update: title changed from "Wireless Headphones" to "Wireless Headphones - SALE" on Shopify.
- Local product title remains "Wireless Headphones".

### Postconditions
- ShopifyListing metadata updated.
- Local Product record unchanged (no auto-overwrite).
- Divergence flagged for review.

### Notes
- The application is the source of truth for product data. Shopify-side edits are tracked but not automatically synced back to prevent data conflicts. Users can review divergences and choose to accept or override.

---

## TC-MKT-008-05: products/delete Topic Marks Listing Deleted

| Field          | Value                                                              |
|----------------|--------------------------------------------------------------------|
| **ID**         | TC-MKT-008-05                                                      |
| **Priority**   | High                                                               |
| **Type**       | Integration                                                        |
| **Platform**   | Shopify                                                            |
| **Automated**  | Yes                                                                |
| **Test File**  | `tests/Feature/Shopify/ShopifyWebhookTest.php`                     |

### Preconditions
- Valid HMAC.
- `X-Shopify-Topic` header is `products/delete`.
- ShopifyListing exists for the deleted Shopify product.
- Product was deleted directly on Shopify admin.

### Test Steps

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Webhook received with `X-Shopify-Topic: products/delete`. | HTTP 200 returned. |
| 2 | `ProcessShopifyWebhook` job processes the deletion event. | Job parses the payload (contains only the product ID). |
| 3 | Job finds the ShopifyListing by `shopify_product_id`. | ShopifyListing matched. |
| 4 | Job updates the ShopifyListing status to `deleted`. | `status=deleted`, `deleted_at` set to current timestamp. |
| 5 | Verify the local Product record is NOT deleted. | Product remains in the database with all its data intact. |
| 6 | Verify `available_quantity` is NOT affected. | Product quantity unchanged; the product can still be listed on other marketplaces. |
| 7 | Verify the product detail page reflects the Shopify listing status. | Shopify section shows "Listing deleted on Shopify" with an option to re-list. |

### Test Data
- Shopify deletion payload: `{"id": 7890123456}`.
- ShopifyListing with `shopify_product_id: 7890123456`.

### Postconditions
- ShopifyListing `status=deleted`.
- Product record unchanged.
- Other marketplace listings unaffected.

### Notes
- Deleting a product on Shopify should not cascade to the local product or other marketplace listings. The user can re-list on Shopify at any time.

---

## TC-MKT-008-06: Unsubscribe Topic Handled Gracefully

| Field          | Value                                                              |
|----------------|--------------------------------------------------------------------|
| **ID**         | TC-MKT-008-06                                                      |
| **Priority**   | Medium                                                             |
| **Type**       | Integration                                                        |
| **Platform**   | Shopify                                                            |
| **Automated**  | Yes                                                                |
| **Test File**  | `tests/Feature/Shopify/ShopifyWebhookTest.php`                     |

### Preconditions
- Valid HMAC.
- `X-Shopify-Topic` header is `app/uninstalled`.
- Organization has an active Shopify IntegrationCredential.

### Test Steps

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Webhook received with `X-Shopify-Topic: app/uninstalled`. | HTTP 200 returned. |
| 2 | `ProcessShopifyWebhook` job processes the uninstall event. | Job identifies the organization by the shop domain in the payload. |
| 3 | Job deactivates the Shopify IntegrationCredential. | `is_active=false`. |
| 4 | Job invalidates the OAuthToken. | Token marked inactive (Shopify has already revoked it server-side). |
| 5 | Verify all ShopifyListing records for the organization are marked as `deleted`. | Bulk status update to `deleted` since the app no longer has access. |
| 6 | Verify a notification is sent to the organization owner. | NotificationLog created: "Your Shopify store has been disconnected. The app was uninstalled from the Shopify admin." |
| 7 | Verify the integrations page reflects the disconnection. | Shopify card shows "Disconnected" with a "Reconnect" option. |

### Test Data
- Shopify uninstall payload: `{"shop_id": 12345, "shop": "my-store.myshopify.com"}`.

### Postconditions
- IntegrationCredential inactive.
- OAuthToken invalidated.
- ShopifyListing records marked deleted.
- User notified.

### Notes
- The `app/uninstalled` webhook is critical for compliance with Shopify's partner requirements. The app must clean up credentials and stop making API calls after uninstall.

---

## TC-MKT-008-07: Webhook Dispatches ProcessShopifyWebhook Job Async

| Field          | Value                                                              |
|----------------|--------------------------------------------------------------------|
| **ID**         | TC-MKT-008-07                                                      |
| **Priority**   | High                                                               |
| **Type**       | Feature                                                            |
| **Platform**   | Shopify                                                            |
| **Automated**  | Yes                                                                |
| **Test File**  | `tests/Feature/Shopify/ShopifyWebhookTest.php`                     |

### Preconditions
- Webhook endpoint is accessible.
- Valid HMAC on the incoming request.
- Queue system (Redis) is operational.

### Test Steps

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Send a valid webhook POST to `/api/webhooks/shopify` with any supported topic. | Controller receives the request. |
| 2 | Controller validates the HMAC. | HMAC is valid. |
| 3 | Controller immediately returns HTTP 200 before processing the payload. | Response time is under 500ms (Shopify requires a response within 5 seconds or it marks the webhook as failed). |
| 4 | Verify `ProcessShopifyWebhook` job is dispatched to the queue. | Job exists on the Redis queue with the full payload, topic, and shop domain. |
| 5 | Verify the job payload includes all necessary context. | Job data contains: `topic` (from X-Shopify-Topic), `shop_domain` (from X-Shopify-Shop-Domain), `payload` (full request body). |
| 6 | Verify the controller does NOT process the webhook inline. | No database writes, API calls, or heavy processing occur within the HTTP request lifecycle. |
| 7 | Job processes asynchronously when a queue worker picks it up. | `ProcessShopifyWebhook::handle()` executes the topic-specific logic. |

### Test Data
- Any valid webhook payload with valid HMAC.
- Topic: `orders/create` (or any supported topic).

### Postconditions
- HTTP 200 returned immediately.
- Job queued for async processing.

### Notes
- Shopify retries webhooks that do not receive a 200 response within 5 seconds. By dispatching to a queue and returning immediately, the application avoids timeout-related retries. The `ProcessShopifyWebhook` job should be idempotent to handle potential duplicate deliveries from Shopify retries.
