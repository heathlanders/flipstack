# [Marketplace/Service] Integration Tests

## Document ID: TC-INT-[NUMBER]

**Service Class:** `app/Services/[Service].php`
**Integration:** [eBay / Shopify / ShippingEasy / etc.]
**API Documentation:** [Link to external API docs]
**Last Updated:** YYYY-MM-DD

---

## 1. Connection / Authentication Tests

| ID | Scenario | Expected | Automated | Test File |
|----|----------|----------|-----------|-----------|
| INT-[SVC]-[N]-01 | OAuth flow initiation | Redirect to provider auth URL | | |
| INT-[SVC]-[N]-02 | OAuth callback with valid code | Token stored, credential created | | |
| INT-[SVC]-[N]-03 | OAuth callback with invalid code | Error handled, user notified | | |
| INT-[SVC]-[N]-04 | Token refresh (expired token) | New token obtained, stored | | |
| INT-[SVC]-[N]-05 | Token refresh failure | Credential marked invalid, user notified | | |

---

## 2. Data Sync Tests (Outbound)

| ID | Scenario | Payload Verified | Expected Response | Side Effects |
|----|----------|------------------|-------------------|--------------|
| INT-[SVC]-[N]-10 | Push new item | [Key fields to verify] | 200/201 + remote ID | [DB updates, logs] |
| INT-[SVC]-[N]-11 | Update existing item | [Changed fields] | 200 + updated data | [Sync log entry] |
| INT-[SVC]-[N]-12 | Delete / withdraw item | [Item identifier] | 200/204 | [Status updated locally] |
| INT-[SVC]-[N]-13 | Inventory quantity push | [Product ID, qty] | 200 | [Sync timestamp updated] |
| INT-[SVC]-[N]-14 | Price update push | [SKU, price, currency] | 200 | [Price log entry] |

---

## 3. Data Sync Tests (Inbound)

| ID | Scenario | Webhook/Poll | Payload | Expected |
|----|----------|-------------|---------|----------|
| INT-[SVC]-[N]-20 | New order received | Webhook / Poll | [Sample payload] | Order created, inventory reserved |
| INT-[SVC]-[N]-21 | Order cancellation | Webhook | [Sample payload] | Order cancelled, inventory released |
| INT-[SVC]-[N]-22 | Item update notification | Webhook | [Sample payload] | Local record updated |

---

## 4. Error Handling Tests

| ID | Error Scenario | API Response | Expected Behavior |
|----|---------------|-------------|-------------------|
| INT-[SVC]-[N]-30 | Rate limited (429) | `429 Too Many Requests` | Retry with backoff, job re-queued |
| INT-[SVC]-[N]-31 | Server error (500) | `500 Internal Server Error` | Retry up to max attempts, log error |
| INT-[SVC]-[N]-32 | Invalid credentials (401) | `401 Unauthorized` | Mark credential invalid, notify user |
| INT-[SVC]-[N]-33 | Resource not found (404) | `404 Not Found` | Log warning, skip item |
| INT-[SVC]-[N]-34 | Validation error (422) | `422 + error details` | Log details, mark item failed |
| INT-[SVC]-[N]-35 | Network timeout | Connection timeout | Retry, log timeout event |

---

## 5. Multi-Tenant Isolation Tests

| ID | Scenario | Expected |
|----|----------|----------|
| INT-[SVC]-[N]-40 | Sync uses correct org credentials | Only org's credential used, not another org's |
| INT-[SVC]-[N]-41 | Webhook routes to correct org | Webhook payload matched to correct organization |
| INT-[SVC]-[N]-42 | Cross-org data isolation | Org A's sync never touches Org B's data |

---

## 6. Mock Strategy

| External Call | Mock Method | Mock Location |
|--------------|-------------|---------------|
| [API endpoint] | `Http::fake()` | Test setUp / trait |
| [Webhook signature] | Manual HMAC generation | Test helper method |
| [OAuth redirect] | Mock response | `Http::fake()` |

**Sandbox environment:** [Available / Not available / URL]

---

## Notes

- [API rate limits and quotas]
- [Sandbox vs production differences]
- [Required API permissions / scopes]
- [Known API quirks or bugs]
