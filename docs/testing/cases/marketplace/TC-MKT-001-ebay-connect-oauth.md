# TC-MKT-001: eBay OAuth Connection

| Field              | Value                                                                 |
|--------------------|-----------------------------------------------------------------------|
| **Document ID**    | TC-MKT-001                                                            |
| **Domain**         | Marketplace Integration - eBay OAuth                                  |
| **Related Test Plan** | [Feature Testing Plan - Phase 1](../../project_feature_testing_plan.md) |
| **Last Updated**   | 2026-04-15                                                            |

---

## TC-MKT-001-01: Initiate OAuth Redirect

| Field          | Value                                          |
|----------------|------------------------------------------------|
| **ID**         | TC-MKT-001-01                                  |
| **Priority**   | Critical                                       |
| **Type**       | Integration                                    |
| **Platform**   | eBay                                           |
| **Automated**  | Yes                                            |
| **Test File**  | `tests/Feature/Ebay/EbayOAuthTest.php`         |

### Preconditions
- User is authenticated and belongs to an organization.
- No existing eBay IntegrationCredential for the organization.
- eBay application credentials (client_id, client_secret, ru_name) are configured in environment variables.

### Test Steps

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Navigate to Settings > Integrations > eBay. | eBay integration card is displayed with a "Connect" button. |
| 2 | Click the "Connect" button. | User is redirected to eBay's OAuth consent screen at `auth.ebay.com/oauth2/authorize`. |
| 3 | Verify the redirect URL query parameters. | URL contains correct `client_id`, `redirect_uri` (ru_name), `response_type=code`, and `scope` with required eBay scopes (e.g., `https://api.ebay.com/oauth/api_scope/sell.inventory`). |
| 4 | Verify a state parameter is included. | State parameter is present and stored in the user's session for CSRF protection. |

### Test Data
- eBay sandbox credentials configured in `.env.testing`.
- Expected scopes: `https://api.ebay.com/oauth/api_scope`, `https://api.ebay.com/oauth/api_scope/sell.inventory`, `https://api.ebay.com/oauth/api_scope/sell.account`, `https://api.ebay.com/oauth/api_scope/sell.fulfillment`.

### Postconditions
- No IntegrationCredential or OAuthToken records are created yet.
- Session contains the state parameter for validation on callback.

### Notes
- The redirect URL must use the production eBay auth endpoint for production credentials and sandbox for test credentials.

---

## TC-MKT-001-02: Callback with Valid Code Exchanges Token

| Field          | Value                                          |
|----------------|------------------------------------------------|
| **ID**         | TC-MKT-001-02                                  |
| **Priority**   | Critical                                       |
| **Type**       | Integration                                    |
| **Platform**   | eBay                                           |
| **Automated**  | Yes                                            |
| **Test File**  | `tests/Feature/Ebay/EbayOAuthTest.php`         |

### Preconditions
- User has been redirected to eBay and granted consent.
- A valid authorization code is returned by eBay.
- Session contains a valid state parameter matching the callback state.
- eBay token endpoint is mocked (or sandbox is used) to return a valid access_token and refresh_token.

### Test Steps

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | eBay redirects back to the application callback URL with `code` and `state` parameters. | Application receives the callback request. |
| 2 | Application validates the `state` parameter against the session. | State matches; processing continues. |
| 3 | Application sends a POST to eBay's token endpoint with the authorization code. | eBay returns `access_token`, `refresh_token`, `expires_in`, and `token_type`. |
| 4 | Verify an IntegrationCredential record is created for the organization. | Record exists with `platform=ebay`, `is_active=true`, encrypted credentials stored. |
| 5 | Verify an OAuthToken record is created linked to the IntegrationCredential. | Record exists with `access_token`, `refresh_token`, and `expires_at` set to current time plus `expires_in` seconds. |
| 6 | User is redirected to the integrations settings page with a success message. | Flash message confirms "eBay account connected successfully." |

### Test Data
- Mock eBay token response: `{"access_token": "v^1.1#i^1#...", "refresh_token": "v^1.1#r^1#...", "expires_in": 7200, "token_type": "User Access Token"}`.

### Postconditions
- IntegrationCredential record exists for the organization with `platform=ebay`.
- OAuthToken record exists with valid tokens and expiry.
- User session state parameter is cleared.

### Notes
- Token values must be stored encrypted in the database via IntegrationCredential accessors.

---

## TC-MKT-001-03: Callback with Invalid Code Shows Error

| Field          | Value                                          |
|----------------|------------------------------------------------|
| **ID**         | TC-MKT-001-03                                  |
| **Priority**   | High                                           |
| **Type**       | Integration                                    |
| **Platform**   | eBay                                           |
| **Automated**  | Yes                                            |
| **Test File**  | `tests/Feature/Ebay/EbayOAuthTest.php`         |

### Preconditions
- User initiated OAuth flow and was redirected to eBay.
- eBay returns an invalid or expired authorization code.
- Session contains a valid state parameter.

### Test Steps

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | eBay redirects back with an invalid `code` and valid `state`. | Application receives the callback request. |
| 2 | Application sends the invalid code to eBay's token endpoint. | eBay returns an error response (HTTP 400 with `invalid_grant`). |
| 3 | Application handles the error gracefully. | No IntegrationCredential or OAuthToken records are created. |
| 4 | User is redirected to the integrations settings page with an error message. | Flash message shows "Failed to connect eBay account. Please try again." |
| 5 | Verify error is logged. | Laravel log contains the eBay error response details. |

### Test Data
- Mock eBay error response: `{"error": "invalid_grant", "error_description": "the provided authorization grant code is invalid or expired"}`.

### Postconditions
- No IntegrationCredential or OAuthToken records exist for this organization + eBay.
- Error is logged for debugging.

### Notes
- The error message shown to the user must not expose eBay API error details.

---

## TC-MKT-001-04: Disconnect Revokes Token

| Field          | Value                                          |
|----------------|------------------------------------------------|
| **ID**         | TC-MKT-001-04                                  |
| **Priority**   | High                                           |
| **Type**       | Integration                                    |
| **Platform**   | eBay                                           |
| **Automated**  | Yes                                            |
| **Test File**  | `tests/Feature/Ebay/EbayOAuthTest.php`         |

### Preconditions
- Organization has an active eBay IntegrationCredential with a valid OAuthToken.
- User has permission to manage integrations.

### Test Steps

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Navigate to Settings > Integrations > eBay. | eBay integration card shows "Connected" status with a "Disconnect" button. |
| 2 | Click "Disconnect" and confirm the action. | Application sends a DELETE request to the disconnect endpoint. |
| 3 | Verify IntegrationCredential is deactivated. | `is_active` is set to `false` on the IntegrationCredential record. |
| 4 | Verify OAuthToken is invalidated. | OAuthToken record is soft-deleted or marked inactive. |
| 5 | Verify eBay listings are not deleted. | Existing EbayListing records remain in the database (status unchanged). |
| 6 | User sees the integration card revert to "Connect" state. | eBay card shows "Connect" button instead of "Disconnect". |

### Test Data
- Existing IntegrationCredential with `platform=ebay`, `is_active=true`.
- At least one EbayListing record linked to the organization.

### Postconditions
- IntegrationCredential `is_active=false`.
- OAuthToken invalidated.
- EbayListing records remain untouched.

### Notes
- Disconnecting should not end or remove listings on eBay itself. It only breaks the local connection.

---

## TC-MKT-001-05: Token Refresh Succeeds

| Field          | Value                                          |
|----------------|------------------------------------------------|
| **ID**         | TC-MKT-001-05                                  |
| **Priority**   | Critical                                       |
| **Type**       | Integration                                    |
| **Platform**   | eBay                                           |
| **Automated**  | Yes                                            |
| **Test File**  | `tests/Feature/Ebay/EbayOAuthTest.php`         |

### Preconditions
- Organization has an active eBay IntegrationCredential.
- OAuthToken exists with an `expires_at` in the past (token is expired).
- `refresh_token` is still valid.
- eBay token endpoint is mocked to return a new access_token.

### Test Steps

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | A scheduled job or service call triggers `RefreshOAuthTokens`. | Job detects the expired token and initiates refresh. |
| 2 | Application sends a POST to eBay's token endpoint with `grant_type=refresh_token` and the current refresh_token. | eBay returns a new `access_token` and updated `expires_in`. |
| 3 | Verify OAuthToken record is updated. | `access_token` is updated to the new value, `expires_at` is recalculated. |
| 4 | Verify the refresh_token is preserved or updated. | If eBay returns a new refresh_token, it is stored; otherwise the existing one is retained. |
| 5 | Verify subsequent API calls use the new token. | Next eBay API call uses the refreshed access_token from the database. |

### Test Data
- Expired OAuthToken: `expires_at` set to 1 hour in the past.
- Mock refresh response: `{"access_token": "v^1.1#new_token...", "expires_in": 7200, "token_type": "User Access Token"}`.

### Postconditions
- OAuthToken has a new `access_token` and a future `expires_at`.
- IntegrationCredential remains `is_active=true`.

### Notes
- eBay refresh tokens are valid for 18 months. The system should track refresh_token expiry separately and alert the user before it expires.

---

## TC-MKT-001-06: Token Refresh Failure Marks Credential Invalid

| Field          | Value                                          |
|----------------|------------------------------------------------|
| **ID**         | TC-MKT-001-06                                  |
| **Priority**   | Critical                                       |
| **Type**       | Integration                                    |
| **Platform**   | eBay                                           |
| **Automated**  | Yes                                            |
| **Test File**  | `tests/Feature/Ebay/EbayOAuthTest.php`         |

### Preconditions
- Organization has an active eBay IntegrationCredential.
- OAuthToken exists with an expired `access_token`.
- `refresh_token` is also expired or revoked.
- eBay token endpoint is mocked to return an error.

### Test Steps

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | `RefreshOAuthTokens` job runs and attempts to refresh the expired token. | Job sends refresh request to eBay. |
| 2 | eBay returns an error response (HTTP 400 with `invalid_grant`). | Application catches the error. |
| 3 | Verify IntegrationCredential is marked as requiring re-authentication. | `is_active` is set to `false` or a `needs_reauth` flag is set. |
| 4 | Verify a notification is sent to the organization owner. | NotificationLog record created with type indicating eBay re-authentication required. |
| 5 | Verify no further API calls are attempted with the invalid token. | Subsequent sync jobs check `is_active` and skip processing. |
| 6 | User sees a warning on the integrations page. | eBay card displays "Re-authentication required" with a "Reconnect" button. |

### Test Data
- Mock eBay error response: `{"error": "invalid_grant", "error_description": "the refresh token is expired or revoked"}`.

### Postconditions
- IntegrationCredential is inactive or flagged for re-authentication.
- Notification sent to the user.
- No further eBay API calls are made until re-authentication.

### Notes
- This scenario can occur when a user revokes the app's access from their eBay account settings or when the 18-month refresh token expires.
