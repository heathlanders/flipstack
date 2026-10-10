# TC-MOB-006: Barcode Scanning

| Field            | Value                                              |
|------------------|----------------------------------------------------|
| Document ID      | TC-MOB-006                                         |
| Domain           | Mobile App                                         |
| Related Test Plan| TP-MOB (mobile-app-test-plan.md)                   |
| Last Updated     | 2026-04-15                                         |

---

## TC-MOB-006-01: Camera Opens for Scanning

| Field         | Value                     |
|---------------|---------------------------|
| Priority      | P0                        |
| Type          | Integration               |
| Platform      | iOS, Android              |
| Automated     | No                        |

**Preconditions:** App installed with camera permission not yet granted.

| Step | Action                              | Expected Result                                      |
|------|-------------------------------------|------------------------------------------------------|
| 1    | Tap Scan button from any screen     | Camera permission dialog appears (first time)        |
| 2    | Grant permission                    | Camera viewfinder opens with scan overlay            |
| 3    | Deny permission                     | Informative message explaining camera requirement    |
| 4    | Verify overlay                      | Scan target area indicator visible on screen         |
| 5    | Tap close/cancel                    | Scanner closes, returns to previous screen           |

---

## TC-MOB-006-02: Scans UPC Barcode Successfully

| Field         | Value                     |
|---------------|---------------------------|
| Priority      | P0                        |
| Type          | Integration               |
| Platform      | iOS, Android              |
| Automated     | No                        |

**Preconditions:** Camera scanner open. Physical product with UPC barcode available.

| Step | Action                              | Expected Result                                      |
|------|-------------------------------------|------------------------------------------------------|
| 1    | Point camera at UPC barcode         | Scanner detects and decodes barcode                  |
| 2    | Barcode recognized                  | Haptic feedback and visual confirmation              |
| 3    | API lookup triggered                | GET /api/products/barcode-lookup?code={upc}          |
| 4    | Product found                       | Product detail screen displayed                      |
| 5    | Verify decoded value                | Correct UPC number captured and sent to API          |

---

## TC-MOB-006-03: Quick Scan Creates Intake Queue Item

| Field         | Value                     |
|---------------|---------------------------|
| Priority      | P0                        |
| Type          | Integration               |
| Platform      | iOS, Android              |
| Automated     | No                        |

**Preconditions:** Quick Scan mode enabled. Camera scanner open.

| Step | Action                              | Expected Result                                      |
|------|-------------------------------------|------------------------------------------------------|
| 1    | Select Quick Scan mode              | Scanner configured for rapid entry                   |
| 2    | Scan barcode                        | Barcode decoded, intake item creation triggered      |
| 3    | POST to intake queue                | Item created in product intake queue                 |
| 4    | Success indicator shown             | Brief confirmation toast, scanner stays open         |
| 5    | Scan next barcode immediately       | Second item created without leaving scanner          |
| 6    | Verify queue count                  | Badge or counter increments with each scan           |

---

## TC-MOB-006-04: Scan With Photo Capture

| Field         | Value                     |
|---------------|---------------------------|
| Priority      | P1                        |
| Type          | Integration               |
| Platform      | iOS, Android              |
| Automated     | No                        |

**Preconditions:** Camera scanner open. Photo capture enabled in scan settings.

| Step | Action                              | Expected Result                                      |
|------|-------------------------------------|------------------------------------------------------|
| 1    | Scan barcode                        | Barcode decoded successfully                         |
| 2    | Photo capture prompt appears        | Option to take product photo displayed               |
| 3    | Tap capture photo                   | Camera switches to photo mode                        |
| 4    | Take photo                          | Photo attached to scanned product data               |
| 5    | Submit with photo                   | Photo uploaded alongside product/intake data         |
| 6    | Skip photo                          | Submission proceeds without photo attachment         |

---

## TC-MOB-006-05: Scan Result Lookup Matches Existing Product

| Field         | Value                     |
|---------------|---------------------------|
| Priority      | P0                        |
| Type          | Integration               |
| Platform      | iOS, Android              |
| Automated     | Yes                       |

**Preconditions:** Product with known barcode exists in inventory.

| Step | Action                              | Expected Result                                      |
|------|-------------------------------------|------------------------------------------------------|
| 1    | Scan barcode matching product       | API lookup returns product match                     |
| 2    | Match result displayed              | Product name, SKU, image, current quantity shown     |
| 3    | Tap to view product                 | Navigates to full product detail screen              |
| 4    | Verify quantity displayed           | Available quantity matches inventory records         |
| 5    | Multiple matches                    | List of matching products shown for selection        |

---

## TC-MOB-006-06: Offline Scan Queues for Later Sync

| Field         | Value                     |
|---------------|---------------------------|
| Priority      | P1                        |
| Type          | Integration               |
| Platform      | iOS, Android              |
| Automated     | No                        |

**Preconditions:** Device in airplane mode or no network connectivity.

| Step | Action                              | Expected Result                                      |
|------|-------------------------------------|------------------------------------------------------|
| 1    | Enable airplane mode                | Device loses network connectivity                    |
| 2    | Open scanner and scan barcode       | Barcode decoded locally                              |
| 3    | API call fails                      | Scan data saved to local queue                       |
| 4    | Offline indicator shown             | Badge showing queued items count                     |
| 5    | Restore network connectivity        | App detects connection restored                      |
| 6    | Queued scans sync automatically     | Pending items submitted to API, queue clears         |
