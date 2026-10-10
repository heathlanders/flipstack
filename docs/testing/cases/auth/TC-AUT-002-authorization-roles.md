# Authorization and Roles Test Cases

## Document ID: TC-AUT-002

**Domain:** Auth
**Related Test Plan:** `docs/testing/plans/auth-test-plan.md`
**Last Updated:** 2026-04-15

---

### TC-AUT-002-01: Admin can access all routes

| Field | Value |
|-------|-------|
| **ID** | TC-AUT-002-01 |
| **Priority** | P0 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |

**Preconditions:**
1. Authenticated user with admin Role that has all permissions (including admin.access, inventory.view, fulfillment.manage, analytics.view, roles.view)

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | GET admin routes (e.g., admin.suppliers.index) | 200 response, page rendered |
| 2 | GET inventory routes (e.g., inventory.products.index) | 200 response |
| 3 | GET fulfillment routes (e.g., fulfillment orders) | 200 response |
| 4 | GET analytics routes (e.g., analytics dashboards) | 200 response |
| 5 | GET user management routes | 200 response |
| 6 | GET role management routes | 200 response |

---

### TC-AUT-002-02: Viewer cannot modify data

| Field | Value |
|-------|-------|
| **ID** | TC-AUT-002-02 |
| **Priority** | P0 |
| **Type** | Security |
| **Platform** | Web |
| **Automated** | No |

**Preconditions:**
1. Authenticated user with a Role that has only view permissions (inventory.view, analytics.view) and no edit/manage permissions

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | GET `inventory.products.index` | 200 response, products listed |
| 2 | POST `inventory.products.store` (create product) | 403 Forbidden |
| 3 | PUT `inventory.products.update` (update product) | 403 Forbidden |
| 4 | DELETE `inventory.products.destroy` | 403 Forbidden |
| 5 | GET analytics dashboards | 200 response (view permitted) |
| 6 | POST any write/modify endpoint | 403 Forbidden |

---

### TC-AUT-002-03: inventory.view permission gates product pages

| Field | Value |
|-------|-------|
| **ID** | TC-AUT-002-03 |
| **Priority** | P0 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |

**Preconditions:**
1. Two users: User A with inventory.view permission, User B without it

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | GET `inventory.products.index` as User A | 200 response, products page rendered |
| 2 | GET `inventory.products.show` as User A | 200 response, product detail rendered |
| 3 | GET `inventory.products.index` as User B | 403 Forbidden |
| 4 | GET `inventory.products.show` as User B | 403 Forbidden |

**Notes:**
- Permission check handled by CheckInventoryPermissions or CheckUserPermissions middleware

---

### TC-AUT-002-04: admin.access gates admin-only routes

| Field | Value |
|-------|-------|
| **ID** | TC-AUT-002-04 |
| **Priority** | P0 |
| **Type** | Security |
| **Platform** | Web |
| **Automated** | No |

**Preconditions:**
1. User A with admin.access permission
2. User B without admin.access permission

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | GET `admin.suppliers.index` as User A | 200 response |
| 2 | GET `admin.suppliers.index` as User B | 403 Forbidden |
| 3 | GET admin supplier submission review as User A | 200 response |
| 4 | GET admin supplier submission review as User B | 403 Forbidden |
| 5 | POST admin actions as User B | 403 Forbidden for all admin write endpoints |

---

### TC-AUT-002-05: fulfillment.manage gates order actions

| Field | Value |
|-------|-------|
| **ID** | TC-AUT-002-05 |
| **Priority** | P0 |
| **Type** | Security |
| **Platform** | Web |
| **Automated** | No |

**Preconditions:**
1. User A with fulfillment.manage permission
2. User B without fulfillment.manage permission
3. Orders exist for the organization

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | GET order list as User A | 200 response |
| 2 | POST pick/pack/ship actions as User A | Actions execute successfully |
| 3 | GET order list as User B | 403 Forbidden |
| 4 | POST pick/pack/ship actions as User B | 403 Forbidden |

---

### TC-AUT-002-06: analytics.view gates analytics pages

| Field | Value |
|-------|-------|
| **ID** | TC-AUT-002-06 |
| **Priority** | P1 |
| **Type** | Security |
| **Platform** | Web |
| **Automated** | No |

**Preconditions:**
1. User A with analytics.view permission
2. User B without analytics.view permission

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | GET profit/loss dashboard as User A | 200 response |
| 2 | GET Analytics Hub dashboards as User A | 200 response |
| 3 | GET forecasting page as User A | 200 response |
| 4 | GET any analytics route as User B | 403 Forbidden |

---

### TC-AUT-002-07: User without permission gets 403

| Field | Value |
|-------|-------|
| **ID** | TC-AUT-002-07 |
| **Priority** | P0 |
| **Type** | Negative |
| **Platform** | Web |
| **Automated** | No |

**Preconditions:**
1. Authenticated user with a Role containing zero permissions (empty permissions array)

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | GET any permission-gated route | 403 Forbidden response |
| 2 | Verify response | Error page or JSON with 403 status (not 500 or redirect loop) |
| 3 | GET dashboard route | Dashboard loads (dashboard access is not gated by granular permissions for base access) |
| 4 | Verify Role::hasPermission | Returns false for all permission strings |

**Notes:**
- Role::hasPermission checks `in_array($permission, $this->permissions ?? [])`
- Empty permissions array means no access to any gated feature

---

### TC-AUT-002-08: Permission changes take effect immediately

| Field | Value |
|-------|-------|
| **ID** | TC-AUT-002-08 |
| **Priority** | P1 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |

**Preconditions:**
1. Authenticated user with a custom Role
2. Admin user who can modify roles

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Verify user cannot access inventory.products.index (no inventory.view permission) | 403 Forbidden |
| 2 | Admin adds inventory.view to user's Role via Role::addPermission | Permission added to database |
| 3 | User refreshes or navigates to inventory.products.index | 200 response (access granted without re-login) |
| 4 | Admin removes inventory.view via Role::removePermission | Permission removed |
| 5 | User navigates to inventory.products.index again | 403 Forbidden (revocation immediate) |

**Notes:**
- Permissions are checked on each request from the database (via middleware), not cached in the session
- Role::syncPermissions can replace all permissions at once
