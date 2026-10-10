# Organization Settings Test Cases

## Document ID: TC-SET-002

**Domain:** Settings
**Related Test Plan:** `docs/testing/plans/settings-test-plan.md`
**Last Updated:** 2026-04-15

---

### TC-SET-002-01: View organization settings page

| Field | Value |
|-------|-------|
| **ID** | TC-SET-002-01 |
| **Priority** | P0 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |

**Preconditions:**
1. Authenticated user with `organization.manage` permission
2. Organization record exists with name, subdomain, and settings

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Send `GET` request to `route('organization.settings')` | Response status 200, Inertia renders organization settings page |
| 2 | Assert response contains organization name | Organization name displayed |
| 3 | Assert response contains organization subdomain | Current subdomain shown |
| 4 | Assert response contains organization branding data (logo URL, colors) | Branding settings loaded |
| 5 | Assert response contains SKU prefix setting | SKU prefix for product auto-numbering |
| 6 | Assert user without `organization.manage` permission is denied access | Response status 403 Forbidden |

**Notes:**
- Route: `GET /organization/settings` named `organization.settings`
- Protected by `can:organization.manage` middleware
- OrganizationController in `App\Http\Controllers\Organization\OrganizationController`
- Settings may include SKU prefix, default currency, timezone, and other org-level configuration

---

### TC-SET-002-02: Update organization name and details

| Field | Value |
|-------|-------|
| **ID** | TC-SET-002-02 |
| **Priority** | P0 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |

**Preconditions:**
1. Authenticated user with `organization.manage` permission
2. Organization exists with initial name (e.g., "Test Store")

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Send `PUT` request to `route('organization.update-settings')` with `name = 'Updated Store Name'` | Response redirects back with success |
| 2 | Refresh organization from database | `$organization->refresh()` |
| 3 | Assert organization name equals `'Updated Store Name'` | Name field persisted |
| 4 | Assert other organization fields not included in request remain unchanged | Partial update does not clear unsubmitted fields |
| 5 | Send `PUT` with empty name | Validation error returned (name is required) |
| 6 | Send `PUT` with name exceeding max length | Validation error for max length constraint |

**Notes:**
- Route: `PUT /organization/settings` named `organization.update-settings`
- Protected by `can:organization.manage` middleware
- The `updateSettings` method handles general organization details
- Separate routes exist for branding (`organization.update-branding`) and security (`organization.update-security`)

---

### TC-SET-002-03: Update branding (logo, colors)

| Field | Value |
|-------|-------|
| **ID** | TC-SET-002-03 |
| **Priority** | P2 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |

**Preconditions:**
1. Authenticated user with `organization.manage` permission
2. Organization exists with default or no branding

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Send `PUT` request to `route('organization.update-branding')` with logo file and primary color | Response redirects back with success |
| 2 | Assert organization branding data updated in database | Logo path and color values persisted |
| 3 | Assert logo file stored in expected storage path | File uploaded to organization storage directory |
| 4 | Send `PUT` with only color update (no logo) | Colors updated, existing logo preserved |
| 5 | Send `PUT` with invalid file type (non-image) | Validation error: logo must be an image file |
| 6 | Verify branding changes reflected on next page load | Settings page shows updated logo and colors |

**Notes:**
- Route: `PUT /organization/branding` named `organization.update-branding`
- Protected by `can:organization.manage` middleware
- Logo upload likely uses Laravel's file upload handling with storage disk
- Branding data may be stored in the `organizations` table or in a related `organization_settings` record
- Color values are typically stored as hex strings (e.g., `#1a2b3c`)

---

### TC-SET-002-04: Manage subdomain

| Field | Value |
|-------|-------|
| **ID** | TC-SET-002-04 |
| **Priority** | P1 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |

**Preconditions:**
1. Authenticated user with `organization.manage` permission
2. Organization exists with a current subdomain value
3. `EnsureOrganizationExists` middleware resolves organizations from subdomains

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | View current subdomain on organization settings page | Current subdomain displayed |
| 2 | Send `PUT` to update settings with `subdomain = 'new-store'` | Response redirects with success |
| 3 | Assert organization subdomain updated in database | `$organization->subdomain` equals `'new-store'` |
| 4 | Assert subdomain is unique across all organizations | Validation rejects duplicate subdomains |
| 5 | Attempt to set subdomain to a reserved word (e.g., 'api', 'admin', 'www') | Validation error: subdomain is reserved |
| 6 | Attempt to set subdomain with invalid characters (spaces, special chars) | Validation error: subdomain must be alphanumeric with hyphens only |

**Notes:**
- Subdomain is used for multi-tenant routing via `EnsureOrganizationExists` middleware
- Subdomain uniqueness must be enforced at the database level (unique constraint) and validation level
- Changing a subdomain affects all existing bookmarks and links for the organization
- Reserved subdomains should be blocked to prevent routing conflicts

---

### TC-SET-002-05: Only admin can access organization settings

| Field | Value |
|-------|-------|
| **ID** | TC-SET-002-05 |
| **Priority** | P0 |
| **Type** | Security |
| **Platform** | Web |
| **Automated** | No |

**Preconditions:**
1. Organization with multiple users: one with `organization.manage` permission, one without
2. Both users belong to the same organization

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Authenticate as user WITH `organization.manage` permission. GET settings page | Response status 200, page loads successfully |
| 2 | Authenticate as user WITHOUT `organization.manage` permission. GET settings page | Response status 403 Forbidden |
| 3 | As non-admin user: attempt PUT to `route('organization.update-settings')` | Response status 403 Forbidden |
| 4 | As non-admin user: attempt PUT to `route('organization.update-branding')` | Response status 403 Forbidden |
| 5 | As non-admin user: attempt PUT to `route('organization.update-security')` | Response status 403 Forbidden |
| 6 | Verify all four organization routes are protected by `can:organization.manage` middleware | Middleware applied in route definitions |

**Notes:**
- All organization routes use `->middleware('can:organization.manage')` in `routes/web.php`
- The `organization.manage` permission is typically granted to owner/admin roles
- Regular team members (buyer, lister, fulfillment, support) should not have this permission
- The middleware check happens before the controller method is invoked

---

### TC-SET-002-06: Settings persist across sessions

| Field | Value |
|-------|-------|
| **ID** | TC-SET-002-06 |
| **Priority** | P1 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |

**Preconditions:**
1. Authenticated user with `organization.manage` permission
2. Organization with known initial settings

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Update organization name to `'Persistent Store'` | Settings saved, redirect with success |
| 2 | Log out the user | Session ended |
| 3 | Log back in as the same user | New session established |
| 4 | Navigate to organization settings page | Page loads with `'Persistent Store'` as name |
| 5 | Assert all previously saved settings (name, branding, subdomain) are still present | Settings persisted in database, not session |
| 6 | Log in as a different admin user in the same organization | Same organization settings visible |

**Notes:**
- Organization settings are stored in the database (not in session or cookies)
- All users in the same organization see the same settings
- Settings updates take effect immediately for all users in the organization
- The OrganizationController loads settings from the organization model on each request
- Caching (if any) should be invalidated on settings update to prevent stale reads across users
