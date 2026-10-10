> **DEPRECATED:** This document is superseded by [FEATURE_MATRIX.md](./FEATURE_MATRIX.md) as of 2026-03-29.
> Kept for historical reference only.

# Mobile App Gap Analysis

Last updated: 2026-03-23

The Flutter mobile app (`mobile/`) covers approximately 90% of the web app's functionality (excluding desktop-only features). This document maps all gaps between the mobile and web apps, prioritized by mobile relevance.

## Current Coverage Summary

| Feature Area | Mobile Status | Coverage |
|---|---|---|
| Authentication | Full (login, register, 2FA, token refresh) | 100% |
| Fulfillment - Picking | Full (pick list, barcode scan verify, pick/short, complete) | 95% |
| Fulfillment - Orders | Full (list, detail, assign, hold/release) | 90% |
| Fulfillment - Packing | Full (pack queue, rates, label purchase, complete) | 90% |
| Fulfillment - Shipping | Full (ship queue, confirm, void label) | 90% |
| Fulfillment - Returns | Full (list, detail, approve, receive, inspect, restock) | 90% |
| Inventory Operations | Full (adjust, transfer, movements, levels) | 90% |
| Barcode Scanning & Intake | Scan, submit, duplicate check, scan-to-lookup, offline queue | 95% |
| Marketplace Status | Health, eBay stats/logs/listings, sync activity | 90% |
| Profile/Account | View, edit, password, sessions, 2FA info, delete account | 90% |
| Dashboard | All 4 roles (owner, buyer, lister, fulfillment) with role switcher | 90% |
| Notifications | List, read/unread, mark all read, delete, preferences | 80% |
| Label Printing | Print from product detail, packing, and shipping pages | 85% |
| Inventory - Product CRUD | List, view, edit, create, delete, image upload | 85% |
| Categories | Full CRUD (list, create, edit, delete) | 80% |
| Locations | Full CRUD (list, create, edit, delete) | 80% |
| eBay Integration | Stats, listings, sync logs via marketplace page | 80% |
| Shopify Integration | None | 0% |
| Inventory Analytics | Tabbed: Overview/ABC/Turnover, KPIs, stock health, top performers, movement history | 90% |
| Supplier Portal | None | 0% |
| Organization/Teams | None | 0% |

## Priority Tiers

### Tier 1: Mobile-First (Warehouse Floor)

These features are MORE natural on mobile than desktop:

1. **Picking workflow** - Scan-guided picking with barcode verification. Workers use phones in the warehouse. THE killer mobile feature.
2. **Packing workflow** - Scan items into boxes, verify counts, trigger label printing from a tablet at the pack station.
3. **Stock adjustments** - Cycle counts, receiving shipments, damaged goods recording. All happen on the warehouse floor.
4. **Barcode scan-to-lookup** - Currently scanning only feeds into intake. Need quick "scan -> show product details" mode.
5. **Inventory movements** - Recording stock movements while physically handling inventory.

### Tier 2: High Value (Quick Mobile Access)

Desktop-primary but high value for on-the-go access:

6. **Orders** - Quick order lookup, status checks, assign orders while away from desk.
7. **Shipping** - Confirm shipments, view tracking from the shipping area.
8. **Inventory alerts** - API already exists (`/api/v1/inventory/alerts/*`). Needs mobile UI + push notifications.
9. **All role dashboards** - Buyer, Lister, Fulfillment views (only Owner exists today).
10. **Returns** - Receive returned items, condition inspection with camera, approve/deny.

### Tier 3: Medium Value (Convenience)

11. **Label printing** - Trigger network prints from warehouse floor. API exists (`/api/v1/printing/*`).
12. **Categories CRUD** - Currently view-only. Add create/edit.
13. **Locations CRUD** - Currently view-only. Add create/edit/analytics.
14. **Inventory analytics** - KPI summary on mobile, full analysis on desktop.
15. **eBay/Shopify status** - Connection health, recent errors, listing counts.
16. **Notification enhancements** - Push notifications, preferences, delete.

### Tier 4: Desktop-Only

These should NOT be built for mobile:

- Supplier portal (unless suppliers need mobile access)
- Organization/team management
- Bulk operations (price, quantity, category)
- Import/export (CSV/Excel)
- Marketplace sync rules configuration
- Admin features (AI prompts, tiers, commissions)
- Listing templates & pricing rules
- Storefront

## Technical Debt

| Issue | Impact | Priority |
|---|---|---|
| ~~Hardcoded API URL~~ | ~~FIXED~~ -- uses `--dart-define=API_BASE_URL=` | ~~Critical~~ |
| No offline support | Unusable without internet | High |
| No push notifications | Users miss real-time alerts | High |
| No real-time updates | Dashboard/orders don't auto-refresh | Medium |
| No retry logic for API failures | Poor UX on flaky connections | Medium |
| No connection state monitoring | No offline indicator | Medium |

## Implementation Phases

### Phase 1: Warehouse Floor (Highest ROI)
- ~~Picking workflow with barcode verification~~ **DONE (2026-03-23)**
- ~~Stock adjustments (cycle counts, receiving)~~ **DONE (2026-03-23)**
- ~~Quick scan-to-lookup mode~~ **DONE (2026-03-23)**
- ~~Inventory alerts (API already exists)~~ **DONE (2026-03-23)**

### Phase 2: Fulfillment Completion
- ~~Orders view (list, details, assign)~~ **DONE (2026-03-23)**
- ~~Packing workflow~~ **DONE (2026-03-23)**
- ~~Shipping confirmation~~ **DONE (2026-03-23)**
- ~~Label printing (API already exists)~~ **DONE (2026-03-23)**

### Phase 3: Visibility & Monitoring
- ~~All role dashboards~~ **DONE (2026-03-23)**
- ~~Marketplace status summaries~~ **DONE (2026-03-23)**
- ~~Returns receiving~~ **DONE (2026-03-23)**
- ~~Notification enhancements (delete, mark all read)~~ **DONE (2026-03-23)**
- Push notifications (FCM/APNs)

### Phase 4: Infrastructure Polish
- ~~Environment-based API URL config~~ **DONE (2026-03-23)** -- uses `--dart-define=API_BASE_URL=`
- Offline support (local barcode queue with SQLite/Hive)
- FCM/APNs push notifications
- Analytics summary view
