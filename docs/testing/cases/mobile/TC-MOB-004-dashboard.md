# TC-MOB-004: Dashboard

| Field            | Value                                              |
|------------------|----------------------------------------------------|
| Document ID      | TC-MOB-004                                         |
| Domain           | Mobile App                                         |
| Related Test Plan| TP-MOB (mobile-app-test-plan.md)                   |
| Last Updated     | 2026-04-15                                         |

---

## TC-MOB-004-01: Owner Dashboard Loads KPIs

| Field         | Value                     |
|---------------|---------------------------|
| Priority      | P0                        |
| Type          | Integration               |
| Platform      | iOS, Android              |
| Automated     | Yes                       |

**Preconditions:** User authenticated with Owner role.

| Step | Action                              | Expected Result                                      |
|------|-------------------------------------|------------------------------------------------------|
| 1    | Navigate to Dashboard               | GET /api/dashboard/owner called                      |
| 2    | KPI cards render                    | Revenue, orders, inventory value, profit displayed   |
| 3    | Verify numeric formatting           | Currency values formatted with symbol and decimals   |
| 4    | Verify date range                   | Dashboard shows current period data                  |
| 5    | Skeleton loading visible            | Loading placeholders shown while API responds        |

---

## TC-MOB-004-02: Dashboard Routes By Role

| Field         | Value                     |
|---------------|---------------------------|
| Priority      | P0                        |
| Type          | Integration               |
| Platform      | iOS, Android              |
| Automated     | Yes                       |

**Preconditions:** Users exist with different roles (Owner, Buyer, Lister, Fulfillment).

| Step | Action                              | Expected Result                                      |
|------|-------------------------------------|------------------------------------------------------|
| 1    | Login as Owner                      | Owner dashboard displayed with financial KPIs        |
| 2    | Login as Buyer                      | Buyer dashboard with procurement metrics             |
| 3    | Login as Lister                     | Lister dashboard with listing stats                  |
| 4    | Login as Fulfillment                | Fulfillment dashboard with pick/pack/ship queues     |
| 5    | Verify each shows role-specific data| No cross-role data leakage                           |

---

## TC-MOB-004-03: Alerts Display

| Field         | Value                     |
|---------------|---------------------------|
| Priority      | P1                        |
| Type          | Integration               |
| Platform      | iOS, Android              |
| Automated     | Yes                       |

**Preconditions:** Active alerts exist (low stock, sync errors, pending actions).

| Step | Action                              | Expected Result                                      |
|------|-------------------------------------|------------------------------------------------------|
| 1    | Dashboard loads                     | Alerts section visible below KPI cards               |
| 2    | Verify alert content                | Alert type icon, message, and timestamp shown        |
| 3    | Tap an alert                        | Navigates to relevant detail screen                  |
| 4    | No alerts exist                     | Alerts section hidden or shows empty state           |

---

## TC-MOB-004-04: Platform Health Status

| Field         | Value                     |
|---------------|---------------------------|
| Priority      | P1                        |
| Type          | Integration               |
| Platform      | iOS, Android              |
| Automated     | Yes                       |

**Preconditions:** eBay and/or Shopify integrations configured.

| Step | Action                              | Expected Result                                      |
|------|-------------------------------------|------------------------------------------------------|
| 1    | Dashboard loads                     | Platform health section visible                      |
| 2    | Verify marketplace status           | Connection status indicator per marketplace          |
| 3    | Connected marketplace               | Green status with last sync timestamp                |
| 4    | Disconnected marketplace            | Red status with reconnect prompt                     |

---

## TC-MOB-004-05: Business Metrics Refresh

| Field         | Value                     |
|---------------|---------------------------|
| Priority      | P1                        |
| Type          | Integration               |
| Platform      | iOS, Android              |
| Automated     | Yes                       |

**Preconditions:** Dashboard loaded with initial data.

| Step | Action                              | Expected Result                                      |
|------|-------------------------------------|------------------------------------------------------|
| 1    | Pull down on dashboard              | Pull-to-refresh indicator appears                    |
| 2    | Release                             | API re-fetched, loading state shown                  |
| 3    | New data arrives                    | KPI values update to reflect latest data             |
| 4    | Verify no duplicate entries         | Dashboard renders cleanly without duplicated cards   |

---

## TC-MOB-004-06: Dashboard Handles API Error Gracefully

| Field         | Value                     |
|---------------|---------------------------|
| Priority      | P1                        |
| Type          | Widget                    |
| Platform      | iOS, Android              |
| Automated     | Yes                       |

**Preconditions:** Dashboard API endpoint returns error (500 or network timeout).

| Step | Action                              | Expected Result                                      |
|------|-------------------------------------|------------------------------------------------------|
| 1    | Navigate to Dashboard               | API call fails with error                            |
| 2    | Error state renders                 | User-friendly error message displayed                |
| 3    | Retry button visible                | "Try Again" button shown                             |
| 4    | Tap retry                           | API re-called, dashboard loads if successful         |
| 5    | Verify no crash                     | App remains responsive, no unhandled exception       |
