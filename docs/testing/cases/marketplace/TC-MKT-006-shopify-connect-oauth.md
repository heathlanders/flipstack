# TC-MKT-006: Shopify OAuth Connection

| Field              | Value                                                                 |
|--------------------|-----------------------------------------------------------------------|
| **Document ID**    | TC-MKT-006                                                            |
| **Domain**         | Marketplace Integration - Shopify OAuth                               |
| **Related Test Plan** | [Feature Testing Plan - Phase 1](../../project_feature_testing_plan.md) |
| **Last Updated**   | 2026-04-15                                                            |

---

## TC-MKT-006-01: Install Redirects to Shopify Auth URL with Correct Scopes

| Field          | Value                                                          |
|----------------|----------------------------------------------------------------|
| **ID**         | TC-MKT-006-01                                                  |
| **Priority**   | Critical                                                       |
| **Type**       | Integration                                                    |
| **Platform**   | Shopify                                                        |
| **Automated**  | Yes                                                            |
| **Test File**  | `tests/Feature/Shopify/ShopifyOAuthTest.php`                   |

### Preconditions
- User is authenticated and belongs to an organization.
- No existing Shopify IntegrationCredential for the organization.
- Shopify app credentials (API key, API secret) are configured in environment variables.
- User provides a valid Shopify store domain (e.g., `my-store.myshopify.com`).

### Test Steps

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Navigate to Settings > Integrations > Shopify. | Shopify integration card is displayed with a "Connect" button and a store domain input. |
| 2 | Enter the store domain `my-store.myshopify.com` and click "Connect". | Application validates the domain format. |
| 3 | Verify the user is redirected to Shopify's OAuth authorization URL. | Redirect URL is `https://my-store.myshopify.com/admin/oauth/authorize`. |
| 4 | Verify query parameters are correct. | URL contains: `client_id` (Shopify API key), `scope` with required permissions, `redirect_uri` (callback URL), `state` (CSRF token). |
| 5 | Verify the requested scopes include all necessary permissions. | Scopes include: `read_products`, `write_products`, `read_inventory`, `write_inventory`, `read_orders`, `write_orders`, `read_locations`. |
| 6 | Verify the state parameter is stored in the session. | Session contains the state token for validation on callback. |

### Test Data
- Store domain: `my-store.myshopify.com`.
- Expected scopes: `read_products,write_products,read_inventory,write_inventory,read_orders,write_orders,read_locations`.

### Postconditions
- No database records created yet.
- Session stores state and shop domain.

### Notes
- Shopify requires the store domain to construct the authorization URL. The domain must be validated to prevent SSRF attacks (must match `*.myshopify.com` pattern).

---

## TC-MKT-006-02: Callback Exchanges Code for Offline Token

| Field          | Value                                                          |
|----------------|----------------------------------------------------------------|
| **ID**         | TC-MKT-006-02                                                  |
| **Priority**   | Critical                                                       |
| **Type**       | Integration                                                    |
| **Platform**   | Shopify                                                        |
| **Automated**  | Yes                                                            |
| **Test File**  | `tests/Feature/Shopify/ShopifyOAuthTest.php`                   |

### Preconditions
- User initiated the OAuth flow and granted permissions on Shopify.
- Shopify redirects back with `code`, `shop`, `state`, `hmac`, and `timestamp` parameters.
- Session contains the matching state parameter.
- Shopify token endpoint is mocked to return an access token.

### Test Steps

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Shopify redirects to the callback URL with query parameters. | Application receives the callback. |
| 2 | Application validates the `state` parameter. | State matches the session value. |
| 3 | Application verifies the `hmac` parameter using the Shopify API secret. | HMAC is valid; request is authentic. |
| 4 | Application sends a POST to `https://{shop}/admin/oauth/access_tokens` with the code. | Shopify returns `{"access_token": "shpat_xxxx", "scope": "read_products,write_products,..."}`. |
| 5 | Verify an IntegrationCredential is created. | Record exists with `platform=shopify`, `is_active=true`, encrypted access token, `config.shop_domain` set. |
| 6 | Verify an OAuthToken record is created. | `access_token` stored, `refresh_token=null` (Shopify offline tokens do not expire or use refresh tokens), `expires_at=null`. |
| 7 | User is redirected to integrations page with success message. | Flash message: "Shopify store connected successfully." |

### Test Data
- Mock Shopify token response: `{"access_token": "shpat_abc123def456", "scope": "read_products,write_products,read_inventory,write_inventory,read_orders,write_orders,read_locations"}`.

### Postconditions
- IntegrationCredential with `platform=shopify`, `is_active=true`.
- OAuthToken with access_token and no expiry (offline token).
- Session state cleared.

### Notes
- Shopify offline access tokens do not expire. They remain valid until the app is uninstalled from the store. There is no refresh flow. This differs significantly from eBay's token lifecycle.

---

## TC-MKT-006-03: Callback Registers Webhooks

| Field          | Value                                                          |
|----------------|----------------------------------------------------------------|
| **ID**         | TC-MKT-006-03                                                  |
| **Priority**   | Critical                                                       |
| **Type**       | Integration                                                    |
| **Platform**   | Shopify                                                        |
| **Automated**  | Yes                                                            |
| **Test File**  | `tests/Feature/Shopify/ShopifyOAuthTest.php`                   |

### Preconditions
- OAuth callback has successfully exchanged the code for an access token (TC-MKT-006-02 completed).
- IntegrationCredential and OAuthToken records exist.
- Shopify Admin API webhook registration endpoint is mocked.

### Test Steps

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | After token exchange, the application registers webhooks with the Shopify store. | POST requests sent to `https://{shop}/admin/api/2024-01/webhooks.json`. |
| 2 | Verify `orders/create` webhook is registered. | Webhook created with topic `orders/create` and the application's webhook endpoint URL. |
| 3 | Verify `products/update` webhook is registered. | Webhook created with topic `products/update`. |
| 4 | Verify `products/delete` webhook is registered. | Webhook created with topic `products/delete`. |
| 5 | Verify `app/uninstalled` webhook is registered. | Webhook created with topic `app/uninstalled` for cleanup when the merchant uninstalls the app. |
| 6 | Verify webhook registration results are stored. | IntegrationCredential `config.webhook_ids` contains the IDs of all registered webhooks for future cleanup. |

### Test Data
- Webhook endpoint: `https://flipstack.app/api/webhooks/shopify`.
- Topics: `orders/create`, `products/update`, `products/delete`, `app/uninstalled`.

### Postconditions
- Four webhooks registered with the Shopify store.
- Webhook IDs stored in IntegrationCredential config.

### Notes
- Webhook registration should be idempotent. If webhooks already exist (e.g., from a previous connection), they should be updated rather than duplicated.

---

## TC-MKT-006-04: Disconnect Deactivates Tokens and Credential

| Field          | Value                                                          |
|----------------|----------------------------------------------------------------|
| **ID**         | TC-MKT-006-04                                                  |
| **Priority**   | High                                                           |
| **Type**       | Feature                                                        |
| **Platform**   | Shopify                                                        |
| **Automated**  | Yes                                                            |
| **Test File**  | `tests/Feature/Shopify/ShopifyOAuthTest.php`                   |

### Preconditions
- Organization has an active Shopify IntegrationCredential with a valid OAuthToken.
- Webhooks are registered with the Shopify store.
- At least one ShopifyListing exists for the organization.

### Test Steps

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Navigate to Settings > Integrations > Shopify. | Shopify card shows "Connected" status with store domain and a "Disconnect" button. |
| 2 | Click "Disconnect" and confirm. | DELETE request sent to the disconnect endpoint. |
| 3 | Verify IntegrationCredential is deactivated. | `is_active=false`. |
| 4 | Verify OAuthToken is invalidated. | OAuthToken soft-deleted or marked inactive. |
| 5 | Verify webhooks are unregistered from the Shopify store. | DELETE requests sent to Shopify for each registered webhook ID stored in config. |
| 6 | Verify existing ShopifyListing records are preserved. | Records remain in the database but are no longer synced. |
| 7 | Shopify card reverts to "Connect" state. | "Connect" button displayed with store domain input. |

### Test Data
- Existing IntegrationCredential with `platform=shopify`, `is_active=true`.
- Webhook IDs in config for cleanup.

### Postconditions
- IntegrationCredential `is_active=false`.
- OAuthToken invalidated.
- Webhooks removed from the Shopify store.
- ShopifyListing records retained.

### Notes
- Unlike eBay disconnect, Shopify disconnect should attempt to clean up webhooks to avoid the Shopify store receiving webhook calls to a disconnected endpoint.

---

## TC-MKT-006-05: Callback with Invalid State Shows Error

| Field          | Value                                                          |
|----------------|----------------------------------------------------------------|
| **ID**         | TC-MKT-006-05                                                  |
| **Priority**   | High                                                           |
| **Type**       | Security                                                       |
| **Platform**   | Shopify                                                        |
| **Automated**  | Yes                                                            |
| **Test File**  | `tests/Feature/Shopify/ShopifyOAuthTest.php`                   |

### Preconditions
- User initiated the OAuth flow and a state parameter was stored in the session.
- The callback request contains a `state` parameter that does not match the session value (potential CSRF attack or session expiry).

### Test Steps

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Shopify redirects to callback with `state=tampered_value`. | Application receives the callback. |
| 2 | Application compares the received `state` with the session state. | Mismatch detected. |
| 3 | Verify no token exchange is attempted. | No POST request sent to Shopify's token endpoint. |
| 4 | Verify no IntegrationCredential or OAuthToken records are created. | Database unchanged. |
| 5 | User is redirected to the integrations page with an error. | Flash message: "Connection failed: invalid session. Please try again." |
| 6 | Verify the attempt is logged as a potential security event. | AuditLog or Laravel log entry records the mismatched state with the user's IP and timestamp. |

### Test Data
- Session state: `abc123_valid_state`.
- Callback state: `tampered_value`.

### Postconditions
- No database changes.
- Security event logged.

### Notes
- State mismatch can occur legitimately if the user's session expired during the OAuth flow. The error message should not alarm the user but should be logged for security monitoring.
