# TC-MKT-010: Scheduled Listings

| Field             | Value                                      |
|-------------------|--------------------------------------------|
| Document ID       | TC-MKT-010                                 |
| Domain            | Marketplace                                |
| Related Test Plan | TP-MKT-003 Cross-Marketplace Operations    |
| Last Updated      | 2026-04-15                                 |
| Controller        | ScheduledListingController                 |
| Routes            | marketplaces.scheduled-listings.*          |

---

## TC-MKT-010-01: View Scheduled Listings Index

| Field          | Value                                                        |
|----------------|--------------------------------------------------------------|
| ID             | TC-MKT-010-01                                                |
| Priority       | P1                                                           |
| Type           | Functional                                                   |
| Platform       | Web                                                          |
| Automated      | Yes                                                          |
| Test File      | tests/Feature/ScheduledListingTest.php                       |

**Preconditions:**
- User is authenticated with marketplace management permissions.
- Organization has at least 3 scheduled listings in various statuses (pending, published, cancelled).

**Test Steps:**

| Step | Action                                                  | Expected Result                                              |
|------|---------------------------------------------------------|--------------------------------------------------------------|
| 1    | Navigate to GET marketplaces.scheduled-listings.index   | Page loads with Inertia response containing scheduled listings. |
| 2    | Verify listing details shown                            | Each listing shows product name, marketplace, scheduled_at, status. |
| 3    | Verify status indicators                                | Pending, published, cancelled, and failed statuses are visually distinct. |
| 4    | Verify pagination                                       | Listings are paginated if more than the page limit.          |
| 5    | Verify organization scoping                             | Only the current organization's scheduled listings are shown. |

**Test Data:**
- 3+ scheduled listings: 1 pending, 1 published, 1 cancelled.

**Postconditions:**
- No data is modified.

**Notes:**
- Scheduled listings should default to sorted by scheduled_at ascending (soonest first).

---

## TC-MKT-010-02: Schedule Listing for Future Date

| Field          | Value                                                        |
|----------------|--------------------------------------------------------------|
| ID             | TC-MKT-010-02                                                |
| Priority       | P1                                                           |
| Type           | Functional                                                   |
| Platform       | Web                                                          |
| Automated      | No                                                           |
| Test File      | --                                                           |

**Preconditions:**
- User is authenticated with marketplace management permissions.
- A product exists that is not yet listed on the target marketplace.
- An active integration credential exists for the target marketplace.

**Test Steps:**

| Step | Action                                                  | Expected Result                                              |
|------|---------------------------------------------------------|--------------------------------------------------------------|
| 1    | POST marketplaces.scheduled-listings.store with product_id, marketplace, scheduled_at, listing_data | Scheduled listing is created.                                |
| 2    | Verify response                                         | Redirect or success response with the created scheduled listing. |
| 3    | Verify database record                                  | ScheduledListing record exists with status=pending, correct scheduled_at. |
| 4    | Verify listing_data stored                              | The listing_data JSON contains all necessary listing fields (title, price, description, etc.). |
| 5    | Verify scheduled_at is in the future                    | Validation rejects scheduled_at in the past.                 |

**Test Data:**
- product_id: existing product UUID.
- marketplace: "ebay"
- scheduled_at: tomorrow at 10:00 AM UTC.
- listing_data: {"title": "Test Product", "price": 29.99, "quantity": 5, "condition": "New"}

**Postconditions:**
- One new ScheduledListing record with status=pending.

**Notes:**
- The scheduled_at time should account for timezone. The scheduler processes due listings based on UTC time.

---

## TC-MKT-010-03: Cancel Scheduled Listing

| Field          | Value                                                        |
|----------------|--------------------------------------------------------------|
| ID             | TC-MKT-010-03                                                |
| Priority       | P1                                                           |
| Type           | Functional                                                   |
| Platform       | Web                                                          |
| Automated      | No                                                           |
| Test File      | --                                                           |

**Preconditions:**
- User is authenticated with marketplace management permissions.
- A scheduled listing exists with status=pending and a future scheduled_at.

**Test Steps:**

| Step | Action                                                  | Expected Result                                              |
|------|---------------------------------------------------------|--------------------------------------------------------------|
| 1    | POST marketplaces.scheduled-listings.cancel with listing ID | Scheduled listing is cancelled.                              |
| 2    | Verify response                                         | Success response confirming cancellation.                    |
| 3    | Verify database record                                  | ScheduledListing status changed to "cancelled".              |
| 4    | Verify listing not published at scheduled time          | After the original scheduled_at passes, no listing is created on the marketplace. |
| 5    | Attempt to cancel an already-published listing          | Returns error indicating listing has already been published. |

**Test Data:**
- Pending scheduled listing ID.
- Already-published listing ID for negative test.

**Postconditions:**
- The scheduled listing has status=cancelled and will not be processed.

**Notes:**
- Cancelled listings remain in the database for historical reference but are excluded from processing.

---

## TC-MKT-010-04: Due Listings Processed by Scheduler Service

| Field          | Value                                                        |
|----------------|--------------------------------------------------------------|
| ID             | TC-MKT-010-04                                                |
| Priority       | P1                                                           |
| Type           | Functional                                                   |
| Platform       | Backend                                                      |
| Automated      | No                                                           |
| Test File      | --                                                           |

**Preconditions:**
- At least 2 scheduled listings exist with status=pending and scheduled_at in the past (i.e., they are due).
- At least 1 scheduled listing exists with status=pending and scheduled_at in the future (should not be processed).

**Test Steps:**

| Step | Action                                                  | Expected Result                                              |
|------|---------------------------------------------------------|--------------------------------------------------------------|
| 1    | Invoke ListingSchedulerService@processDueListings       | Due listings are identified and processed.                   |
| 2    | Verify due listings published                           | Each due listing's status changes to "published" upon successful publication. |
| 3    | Verify marketplace listing created                      | The corresponding marketplace listing (EbayListing or ShopifyListing) is created. |
| 4    | Verify future listings untouched                        | Scheduled listings with future scheduled_at remain in "pending" status. |
| 5    | Verify cancelled listings skipped                       | Cancelled listings are not processed regardless of scheduled_at. |

**Test Data:**
- Listing A: status=pending, scheduled_at=1 hour ago (should be processed).
- Listing B: status=pending, scheduled_at=2 hours ago (should be processed).
- Listing C: status=pending, scheduled_at=tomorrow (should not be processed).
- Listing D: status=cancelled, scheduled_at=1 hour ago (should not be processed).

**Postconditions:**
- Listings A and B have status=published. Listings C and D are unchanged.

**Notes:**
- ListingSchedulerService is typically invoked by the Laravel scheduler (cron) at regular intervals.

---

## TC-MKT-010-05: Failed Listing Publication Sets Error Status

| Field          | Value                                                        |
|----------------|--------------------------------------------------------------|
| ID             | TC-MKT-010-05                                                |
| Priority       | P1                                                           |
| Type           | Functional                                                   |
| Platform       | Backend                                                      |
| Automated      | No                                                           |
| Test File      | --                                                           |

**Preconditions:**
- A scheduled listing exists with status=pending and scheduled_at in the past.
- The listing will fail to publish (e.g., invalid listing data, expired credential, marketplace API error).

**Test Steps:**

| Step | Action                                                  | Expected Result                                              |
|------|---------------------------------------------------------|--------------------------------------------------------------|
| 1    | Invoke ListingSchedulerService@processDueListings with a listing that will fail | Publication attempt is made and fails.                       |
| 2    | Verify status set to failed                             | ScheduledListing status changes to "failed".                 |
| 3    | Verify error_message recorded                           | The error_message field contains a descriptive error (e.g., "eBay API returned 400: Invalid item specifics"). |
| 4    | Verify other due listings still processed               | A failure on one listing does not prevent processing of other due listings. |

**Test Data:**
- Listing with invalid listing_data (e.g., missing required eBay item specifics).

**Postconditions:**
- The failed listing has status=failed with an error_message.
- Other due listings are processed independently.

**Notes:**
- Failed listings should be visible in the index view with their error messages to help users diagnose and fix issues.

---

## TC-MKT-010-06: Delete Scheduled Listing

| Field          | Value                                                        |
|----------------|--------------------------------------------------------------|
| ID             | TC-MKT-010-06                                                |
| Priority       | P2                                                           |
| Type           | Functional                                                   |
| Platform       | Web                                                          |
| Automated      | No                                                           |
| Test File      | --                                                           |

**Preconditions:**
- User is authenticated with marketplace management permissions.
- A scheduled listing exists (any status).

**Test Steps:**

| Step | Action                                                  | Expected Result                                              |
|------|---------------------------------------------------------|--------------------------------------------------------------|
| 1    | DELETE marketplaces.scheduled-listings.destroy with listing ID | Scheduled listing is deleted.                                |
| 2    | Verify response                                         | Redirect to scheduled listings index with success message.   |
| 3    | Verify database                                         | ScheduledListing record no longer exists.                    |
| 4    | Attempt to delete a published listing                   | Consider whether published listings should be deletable (record keeping). |

**Test Data:**
- Scheduled listing ID.

**Postconditions:**
- The ScheduledListing record is removed from the database.

**Notes:**
- Deleting a pending listing is equivalent to cancelling and then removing the record. Consider whether cancel should be preferred over delete for pending listings to maintain an audit trail.
