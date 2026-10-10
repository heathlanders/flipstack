# TC-MOB-005: Marketplace

| Field            | Value                                              |
|------------------|----------------------------------------------------|
| Document ID      | TC-MOB-005                                         |
| Domain           | Mobile App                                         |
| Related Test Plan| TP-MOB (mobile-app-test-plan.md)                   |
| Last Updated     | 2026-04-15                                         |

---

## TC-MOB-005-01: Marketplace Status Page Shows Connections

| Field         | Value                     |
|---------------|---------------------------|
| Priority      | P0                        |
| Type          | Integration               |
| Platform      | iOS, Android              |
| Automated     | Yes                       |

**Preconditions:** User authenticated. At least one marketplace integration configured.

| Step | Action                              | Expected Result                                      |
|------|-------------------------------------|------------------------------------------------------|
| 1    | Navigate to Marketplace screen      | API call to fetch marketplace connections            |
| 2    | Page renders                        | List of configured marketplaces displayed            |
| 3    | Verify connection card content      | Marketplace name, status indicator, last sync time   |
| 4    | Connected marketplace               | Green badge with "Connected" label                   |
| 5    | No marketplaces configured          | Empty state with setup instructions                  |

---

## TC-MOB-005-02: eBay Status Shows Listing Stats

| Field         | Value                     |
|---------------|---------------------------|
| Priority      | P0                        |
| Type          | Integration               |
| Platform      | iOS, Android              |
| Automated     | Yes                       |

**Preconditions:** eBay integration active with listings.

| Step | Action                              | Expected Result                                      |
|------|-------------------------------------|------------------------------------------------------|
| 1    | Tap eBay connection card            | GET /api/ebay/status and /api/ebay/stats called      |
| 2    | eBay detail screen renders          | Active listings count, total revenue shown           |
| 3    | Verify listing breakdown            | Active, ended, and error listing counts displayed    |
| 4    | Verify sync status                  | Last sync timestamp and sync health indicator        |

---

## TC-MOB-005-03: Shopify Connection Status

| Field         | Value                     |
|---------------|---------------------------|
| Priority      | P1                        |
| Type          | Integration               |
| Platform      | iOS, Android              |
| Automated     | Yes                       |

**Preconditions:** Shopify integration configured.

| Step | Action                              | Expected Result                                      |
|------|-------------------------------------|------------------------------------------------------|
| 1    | Tap Shopify connection card         | Shopify status API called                            |
| 2    | Detail screen renders               | Store name, product count, sync status shown         |
| 3    | Verify collection info              | Number of synced collections displayed               |
| 4    | Verify inventory sync status        | Last inventory push timestamp shown                  |

---

## TC-MOB-005-04: Marketplace Data Refreshes on Pull

| Field         | Value                     |
|---------------|---------------------------|
| Priority      | P1                        |
| Type          | Integration               |
| Platform      | iOS, Android              |
| Automated     | Yes                       |

**Preconditions:** Marketplace status page loaded.

| Step | Action                              | Expected Result                                      |
|------|-------------------------------------|------------------------------------------------------|
| 1    | Pull down on marketplace screen     | Refresh indicator appears                            |
| 2    | Release                             | API re-fetched for all connections                   |
| 3    | Data updates                        | Stats and timestamps reflect latest values           |
| 4    | Loading state                       | Skeleton or spinner shown during refresh             |

---

## TC-MOB-005-05: Disconnected State Shown

| Field         | Value                     |
|---------------|---------------------------|
| Priority      | P1                        |
| Type          | Widget                    |
| Platform      | iOS, Android              |
| Automated     | Yes                       |

**Preconditions:** A marketplace integration has expired or invalid credentials.

| Step | Action                              | Expected Result                                      |
|------|-------------------------------------|------------------------------------------------------|
| 1    | Navigate to Marketplace screen      | Page loads with disconnected marketplace             |
| 2    | Disconnected card renders           | Red/orange badge with "Disconnected" label           |
| 3    | Verify reconnect option             | "Reconnect" or "Update Credentials" action visible   |
| 4    | Tap reconnect                       | Navigates to web app or shows instructions           |

---

## TC-MOB-005-06: Error State Handled

| Field         | Value                     |
|---------------|---------------------------|
| Priority      | P2                        |
| Type          | Widget                    |
| Platform      | iOS, Android              |
| Automated     | Yes                       |

**Preconditions:** Marketplace API endpoint returns error.

| Step | Action                              | Expected Result                                      |
|------|-------------------------------------|------------------------------------------------------|
| 1    | Navigate to Marketplace screen      | API call fails                                       |
| 2    | Error state renders                 | Error message displayed, not a crash                 |
| 3    | Retry button visible                | User can tap to retry                                |
| 4    | Retry succeeds                      | Page loads normally with marketplace data            |
