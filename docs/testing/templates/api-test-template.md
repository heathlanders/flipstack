# [API Group] Endpoint Tests

## Document ID: TC-API-[NUMBER]

**Base URL:** `/api/[prefix]`
**Authentication:** Sanctum token / Public
**Related Test Plan:** `docs/testing/plans/api-endpoints-test-plan.md`
**Last Updated:** YYYY-MM-DD

---

### Endpoint: [METHOD] [PATH]

| Field | Value |
|-------|-------|
| **Route Name** | `api.[name]` |
| **Controller** | `App\Http\Controllers\Api\[Controller]@[method]` |
| **Middleware** | `auth:sanctum`, `[others]` |
| **Permissions** | [Required permissions or roles] |

**Request Parameters:**

| Parameter | Location | Type | Required | Description | Constraints |
|-----------|----------|------|----------|-------------|-------------|
| [param] | path / query / body | string / int / bool / array | Yes / No | [Description] | [min, max, enum, etc.] |

**Request Body Example:**

```json
{
}
```

**Success Response (200):**

```json
{
    "success": true,
    "data": {}
}
```

**Error Responses:**

| Code | Condition | Response Body |
|------|-----------|---------------|
| 401 | Missing or invalid token | `{"message": "Unauthenticated."}` |
| 403 | Insufficient permissions | `{"message": "Forbidden."}` |
| 404 | Resource not found | `{"message": "Not found."}` |
| 422 | Validation failure | `{"message": "...", "errors": {}}` |

**Test Cases:**

| ID | Scenario | Method | Expected | Automated |
|----|----------|--------|----------|-----------|
| TC-API-[N]-01 | Happy path with valid data | `test_[endpoint]_success` | 200 + correct payload | Yes / No |
| TC-API-[N]-02 | Unauthenticated request | `test_[endpoint]_unauthenticated` | 401 | Yes / No |
| TC-API-[N]-03 | Wrong organization scope | `test_[endpoint]_wrong_org` | 403 or empty | Yes / No |
| TC-API-[N]-04 | Invalid input | `test_[endpoint]_validation` | 422 + error details | Yes / No |
| TC-API-[N]-05 | Resource not found | `test_[endpoint]_not_found` | 404 | Yes / No |

**Notes:**

- [Rate limiting considerations]
- [Pagination behavior]
- [Multi-tenant scoping details]

---

### Endpoint: [METHOD] [NEXT PATH]

<!-- Repeat the template block above for each endpoint -->
