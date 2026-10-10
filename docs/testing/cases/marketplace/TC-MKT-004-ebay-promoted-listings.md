# TC-MKT-004: eBay Promoted Listings

| Field              | Value                                                                 |
|--------------------|-----------------------------------------------------------------------|
| **Document ID**    | TC-MKT-004                                                            |
| **Domain**         | Marketplace Integration - eBay Promoted Listings                      |
| **Related Test Plan** | [Feature Testing Plan - Phase 2](../../project_feature_testing_plan.md) |
| **Last Updated**   | 2026-04-15                                                            |

---

## TC-MKT-004-01: Create Standard Campaign

| Field          | Value                                                              |
|----------------|--------------------------------------------------------------------|
| **ID**         | TC-MKT-004-01                                                      |
| **Priority**   | High                                                               |
| **Type**       | Feature                                                            |
| **Platform**   | eBay                                                               |
| **Automated**  | Yes                                                                |
| **Test File**  | `tests/Feature/Ebay/EbayPromotedListingsTest.php`                  |

### Preconditions
- Organization has an active eBay IntegrationCredential with valid OAuthToken.
- User has permission to manage eBay promoted listings.
- At least one published EbayListing exists for the organization.

### Test Steps

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Navigate to eBay > Promoted Listings > Create Campaign. | Campaign creation form is displayed with campaign type selector. |
| 2 | Select campaign type "Standard (CPA)". | Form shows fields for campaign name, start date, and funding strategy (percentage ad rate). |
| 3 | Enter campaign name "Summer Sale 2026", set start date to today, set default ad rate to 5%. | Form fields populated and validated. |
| 4 | Submit the campaign creation form. | POST request sent to the create campaign endpoint. |
| 5 | Verify EbayPromotedCampaign record is created in the database. | Record exists with `campaign_name="Summer Sale 2026"`, `campaign_type="standard"`, `status="active"`, `default_ad_rate=5.0`, `organization_id` set correctly. |
| 6 | Verify eBay API is called to create the campaign. | eBay Sell Marketing API `createCampaign` called; `ebay_campaign_id` stored on the local record. |

### Test Data
- Campaign name: "Summer Sale 2026".
- Campaign type: standard.
- Default ad rate: 5%.
- Start date: current date.

### Postconditions
- EbayPromotedCampaign record exists with a valid `ebay_campaign_id`.
- Campaign status is `active`.

### Notes
- Standard (CPA) campaigns charge only when a buyer clicks the promoted listing and purchases within 30 days. The ad rate is a percentage of the final sale price.

---

## TC-MKT-004-02: Create Advanced Campaign

| Field          | Value                                                              |
|----------------|--------------------------------------------------------------------|
| **ID**         | TC-MKT-004-02                                                      |
| **Priority**   | High                                                               |
| **Type**       | Feature                                                            |
| **Platform**   | eBay                                                               |
| **Automated**  | Yes                                                                |
| **Test File**  | `tests/Feature/Ebay/EbayPromotedListingsTest.php`                  |

### Preconditions
- Organization has an active eBay IntegrationCredential.
- User has permission to manage eBay promoted listings.
- eBay seller account is eligible for Advanced campaigns (eBay Store subscription required).

### Test Steps

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Navigate to eBay > Promoted Listings > Create Campaign. | Campaign creation form displayed. |
| 2 | Select campaign type "Advanced (CPC)". | Form shows fields for campaign name, daily budget, start/end dates. |
| 3 | Enter campaign name "Q2 Push", set daily budget to $50.00, start date today, no end date. | Form fields populated and validated. |
| 4 | Submit the form. | POST request sent to create endpoint. |
| 5 | Verify EbayPromotedCampaign record is created. | Record exists with `campaign_type="advanced"`, `daily_budget=50.00`, `status="active"`. |
| 6 | Verify eBay API `createCampaign` is called with CPC funding strategy. | `ebay_campaign_id` stored; funding model set to cost-per-click. |

### Test Data
- Campaign name: "Q2 Push".
- Campaign type: advanced.
- Daily budget: $50.00.

### Postconditions
- EbayPromotedCampaign record with `campaign_type=advanced`.

### Notes
- Advanced campaigns use cost-per-click (CPC) billing and require keyword targeting (not covered in this test case). Keyword management is a separate feature.

---

## TC-MKT-004-03: Add Listing to Campaign with Ad Rate

| Field          | Value                                                              |
|----------------|--------------------------------------------------------------------|
| **ID**         | TC-MKT-004-03                                                      |
| **Priority**   | High                                                               |
| **Type**       | Feature                                                            |
| **Platform**   | eBay                                                               |
| **Automated**  | Yes                                                                |
| **Test File**  | `tests/Feature/Ebay/EbayPromotedListingsTest.php`                  |

### Preconditions
- An active EbayPromotedCampaign exists with `campaign_type=standard`.
- A published EbayListing exists that is not yet in any campaign.

### Test Steps

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Navigate to the campaign detail page. | Campaign details shown with an "Add Listings" button. |
| 2 | Click "Add Listings" and select the target EbayListing. | Listing selection modal appears showing eligible listings. |
| 3 | Set the ad rate for this listing to 7% (overriding the campaign default of 5%). | Ad rate field accepts the custom value. |
| 4 | Confirm the addition. | POST request sent to add the listing to the campaign. |
| 5 | Verify EbayPromotedListing record is created. | Record exists linking the EbayListing to the EbayPromotedCampaign with `ad_rate=7.0`. |
| 6 | Verify eBay API `createAdByListingId` is called. | API called with the `ebay_listing_id` and ad rate 7%. |

### Test Data
- Campaign: "Summer Sale 2026" (standard, default 5%).
- Listing ad rate override: 7%.

### Postconditions
- EbayPromotedListing record exists linking the campaign and listing.
- Listing is promoted on eBay with a 7% ad rate.

### Notes
- Individual listing ad rates override the campaign default. If no override is set, the campaign default applies.

---

## TC-MKT-004-04: Remove Listing from Campaign

| Field          | Value                                                              |
|----------------|--------------------------------------------------------------------|
| **ID**         | TC-MKT-004-04                                                      |
| **Priority**   | Medium                                                             |
| **Type**       | Feature                                                            |
| **Platform**   | eBay                                                               |
| **Automated**  | Yes                                                                |
| **Test File**  | `tests/Feature/Ebay/EbayPromotedListingsTest.php`                  |

### Preconditions
- An active EbayPromotedCampaign exists.
- An EbayPromotedListing record exists linking a listing to this campaign.

### Test Steps

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Navigate to the campaign detail page. | Campaign shows the promoted listing in the listings table. |
| 2 | Click "Remove" on the target listing row. | Confirmation dialog appears. |
| 3 | Confirm removal. | DELETE request sent to the remove endpoint. |
| 4 | Verify the EbayPromotedListing record is deleted. | Record no longer exists in the database. |
| 5 | Verify eBay API `deleteAd` is called. | API called to remove the ad from the campaign on eBay. |
| 6 | Verify the EbayListing itself is not affected. | EbayListing remains `status=published`. |

### Test Data
- Existing EbayPromotedListing record to be removed.

### Postconditions
- EbayPromotedListing record deleted.
- EbayListing unchanged.
- Listing no longer promoted on eBay.

### Notes
- Removing a listing from a campaign does not end the listing itself. The listing returns to organic-only visibility.

---

## TC-MKT-004-05: Pause, Resume, and End Campaign

| Field          | Value                                                              |
|----------------|--------------------------------------------------------------------|
| **ID**         | TC-MKT-004-05                                                      |
| **Priority**   | Medium                                                             |
| **Type**       | Feature                                                            |
| **Platform**   | eBay                                                               |
| **Automated**  | Yes                                                                |
| **Test File**  | `tests/Feature/Ebay/EbayPromotedListingsTest.php`                  |

### Preconditions
- An active EbayPromotedCampaign exists with at least one promoted listing.

### Test Steps

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Click "Pause" on the campaign. | PATCH request sent to update campaign status. |
| 2 | Verify local record updated. | EbayPromotedCampaign `status=paused`. |
| 3 | Verify eBay API `pauseCampaign` is called. | Campaign paused on eBay; listings stop being promoted. |
| 4 | Click "Resume" on the paused campaign. | PATCH request sent to update status. |
| 5 | Verify local record updated. | EbayPromotedCampaign `status=active`. |
| 6 | Verify eBay API `resumeCampaign` is called. | Campaign resumed; listings promoted again. |
| 7 | Click "End Campaign". | Confirmation dialog appears. |
| 8 | Confirm ending the campaign. | PATCH request sent. |
| 9 | Verify local record updated. | EbayPromotedCampaign `status=ended`, `ended_at` set. |
| 10 | Verify eBay API `endCampaign` is called. | Campaign permanently ended on eBay. |

### Test Data
- Campaign transitioning through: active -> paused -> active -> ended.

### Postconditions
- Campaign status is `ended`.
- Campaign cannot be restarted after ending (eBay restriction).

### Notes
- Ending a campaign is permanent. A new campaign must be created to resume promotion. Pausing is reversible.

---

## TC-MKT-004-06: Sync Stats Dispatches Job

| Field          | Value                                                              |
|----------------|--------------------------------------------------------------------|
| **ID**         | TC-MKT-004-06                                                      |
| **Priority**   | Medium                                                             |
| **Type**       | Feature                                                            |
| **Platform**   | eBay                                                               |
| **Automated**  | Yes                                                                |
| **Test File**  | `tests/Feature/Ebay/EbayPromotedListingsTest.php`                  |

### Preconditions
- An active EbayPromotedCampaign exists with promoted listings.
- The scheduled command or manual trigger for stats sync is available.
- eBay Sell Analytics API endpoint is mocked.

### Test Steps

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Trigger the promoted listings stats sync (scheduled or manual). | `SyncPromotedListingStats` job is dispatched. |
| 2 | Job calls eBay Sell Analytics API to fetch campaign performance data. | API returns impressions, clicks, cost, and sales data for each promoted listing. |
| 3 | Verify EbayPromotedListing records are updated with stats. | Fields updated: `impressions`, `clicks`, `total_cost`, `total_sales`, `stats_last_synced_at`. |
| 4 | Verify campaign-level aggregates are updated. | EbayPromotedCampaign `total_spend`, `total_impressions`, `total_clicks` updated. |

### Test Data
- Mock stats response: `{impressions: 1500, clicks: 45, totalCost: 12.50, sales: 3, revenue: 89.97}`.

### Postconditions
- Promoted listing and campaign stats reflect the latest eBay data.

### Notes
- Stats sync runs on a schedule (typically daily). The eBay Analytics API may have a 24-48 hour data delay.

---

## TC-MKT-004-07: Dashboard Stats Aggregation

| Field          | Value                                                              |
|----------------|--------------------------------------------------------------------|
| **ID**         | TC-MKT-004-07                                                      |
| **Priority**   | Medium                                                             |
| **Type**       | Feature                                                            |
| **Platform**   | eBay                                                               |
| **Automated**  | Yes                                                                |
| **Test File**  | `tests/Feature/Ebay/EbayPromotedListingsTest.php`                  |

### Preconditions
- Organization has 3 active campaigns with synced stats.
- Campaign A: spend $50, sales $200, clicks 100, impressions 5000.
- Campaign B: spend $30, sales $150, clicks 60, impressions 3000.
- Campaign C: spend $20, sales $80, clicks 40, impressions 2000.

### Test Steps

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Navigate to eBay > Promoted Listings dashboard. | Dashboard displays aggregated stats across all campaigns. |
| 2 | Verify total spend. | Total spend = $100 ($50 + $30 + $20). |
| 3 | Verify total sales. | Total sales = $430 ($200 + $150 + $80). |
| 4 | Verify overall ROAS (Return on Ad Spend). | ROAS = 4.30 ($430 / $100). |
| 5 | Verify click-through rate (CTR). | CTR = 2.0% (200 clicks / 10000 impressions). |
| 6 | Verify individual campaign rows are displayed. | Each campaign shown with its own spend, sales, ROAS, and CTR. |

### Test Data
- Three campaigns with the stats listed in preconditions.

### Postconditions
- Dashboard accurately reflects aggregated and per-campaign metrics.

### Notes
- ROAS calculation: total revenue from promoted sales divided by total ad spend. A ROAS above 1.0 indicates profitable promotion.

---

## TC-MKT-004-08: Organization Isolation (Cannot See Other Org's Campaigns)

| Field          | Value                                                              |
|----------------|--------------------------------------------------------------------|
| **ID**         | TC-MKT-004-08                                                      |
| **Priority**   | Critical                                                           |
| **Type**       | Security                                                           |
| **Platform**   | eBay                                                               |
| **Automated**  | Yes                                                                |
| **Test File**  | `tests/Feature/Ebay/EbayPromotedListingsTest.php`                  |

### Preconditions
- Organization A has 2 promoted campaigns.
- Organization B has 1 promoted campaign.
- User belongs to Organization A.

### Test Steps

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Authenticate as a user in Organization A. | User session established for Org A. |
| 2 | Request the promoted campaigns index endpoint. | API returns campaigns. |
| 3 | Verify only Organization A's campaigns are returned. | Response contains exactly 2 campaigns, both belonging to Org A. |
| 4 | Attempt to access Organization B's campaign by ID directly (e.g., GET `/ebay/promoted-campaigns/{orgB_campaign_id}`). | Request returns HTTP 403 Forbidden or HTTP 404 Not Found. |
| 5 | Attempt to add a listing from Org A to Org B's campaign. | Request returns HTTP 403 or 404. |
| 6 | Verify the promoted listings dashboard stats only include Org A's data. | Aggregated spend, sales, and ROAS reflect only Org A's campaigns. |

### Test Data
- Org A: 2 campaigns with known IDs.
- Org B: 1 campaign with a known ID.

### Postconditions
- No cross-organization data leakage.

### Notes
- Multi-tenancy isolation is enforced at the query level (organization scope on all campaign queries) and must also be enforced at the controller/policy level for direct access attempts.
