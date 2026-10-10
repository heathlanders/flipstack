# TC-API-001: Auth Endpoints

| Field            | Value                                              |
|------------------|----------------------------------------------------|
| Document ID      | TC-API-001                                         |
| Domain           | API Endpoints                                      |
| Related Test Plan| TP-API (api-endpoints-test-plan.md)                |
| Last Updated     | 2026-04-15                                         |

---

## TC-API-001-01: POST /api/auth/login Returns Token

| Field         | Value                     |
|---------------|---------------------------|
| Priority      | P0                        |
| Type          | Feature                   |
| Platform      | API                       |
| Automated     | Yes                       |

**Preconditions:** User account exists with known credentials.

| Step | Action                              | Expected Result                                      |
|------|-------------------------------------|------------------------------------------------------|
| 1    | POST /api/auth/login with valid email and password | 200 response                        |
| 2    | Verify response body                | JSON contains `token`, `user` object                 |
| 3    | Verify token is valid Sanctum token | Token can be used in Authorization header            |
| 4    | POST with wrong password            | 422 response with validation error                   |
| 5    | POST with non-existent email        | 422 response (no user enumeration difference)        |
| 6    | POST with empty body                | 422 response with required field errors              |

---

## TC-API-001-02: POST /api/auth/register Creates User

| Field         | Value                     |
|---------------|---------------------------|
| Priority      | P0                        |
| Type          | Feature                   |
| Platform      | API                       |
| Automated     | Yes                       |

**Preconditions:** Registration enabled. Email not already registered.

| Step | Action                              | Expected Result                                      |
|------|-------------------------------------|------------------------------------------------------|
| 1    | POST /api/auth/register with name, email, password, password_confirmation | 201 response |
| 2    | Verify response body                | JSON contains `token`, `user` object with new ID     |
| 3    | Verify user persisted               | User exists in database with correct attributes      |
| 4    | POST with existing email            | 422 response with "email already taken" error        |
| 5    | POST with weak password             | 422 response with password validation error          |
| 6    | POST with missing fields            | 422 response listing all required fields             |

---

## TC-API-001-03: POST /api/auth/logout Invalidates Token

| Field         | Value                     |
|---------------|---------------------------|
| Priority      | P0                        |
| Type          | Feature                   |
| Platform      | API                       |
| Automated     | Yes                       |

**Preconditions:** User authenticated with valid Sanctum token.

| Step | Action                              | Expected Result                                      |
|------|-------------------------------------|------------------------------------------------------|
| 1    | POST /api/auth/logout with Bearer token | 200 response                                    |
| 2    | Verify token deleted                | Token no longer exists in personal_access_tokens     |
| 3    | Use same token for another request  | 401 Unauthenticated response                        |
| 4    | POST /api/auth/logout without token | 401 response                                        |

---

## TC-API-001-04: GET /api/auth/profile Returns User Data

| Field         | Value                     |
|---------------|---------------------------|
| Priority      | P0                        |
| Type          | Feature                   |
| Platform      | API                       |
| Automated     | Yes                       |

**Preconditions:** User authenticated with valid token.

| Step | Action                              | Expected Result                                      |
|------|-------------------------------------|------------------------------------------------------|
| 1    | GET /api/auth/profile with Bearer token | 200 response                                    |
| 2    | Verify response body                | JSON contains id, name, email, organization data     |
| 3    | Verify organization scoping         | Only current organization data included              |
| 4    | Verify role information             | User's role and permissions included                 |
| 5    | GET without token                   | 401 response                                        |

---

## TC-API-001-05: POST /api/auth/refresh Rotates Token

| Field         | Value                     |
|---------------|---------------------------|
| Priority      | P1                        |
| Type          | Feature                   |
| Platform      | API                       |
| Automated     | Yes                       |

**Preconditions:** User authenticated with valid token.

| Step | Action                              | Expected Result                                      |
|------|-------------------------------------|------------------------------------------------------|
| 1    | POST /api/auth/refresh with Bearer token | 200 response                                   |
| 2    | Verify new token returned           | Response contains new `token` value                  |
| 3    | Verify old token invalidated        | Old token returns 401 on subsequent use              |
| 4    | Verify new token works              | New token successfully authenticates requests        |

---

## TC-API-001-06: Unauthenticated Request Returns 401

| Field         | Value                     |
|---------------|---------------------------|
| Priority      | P0                        |
| Type          | Feature                   |
| Platform      | API                       |
| Automated     | Yes                       |

**Preconditions:** No authentication token provided.

| Step | Action                              | Expected Result                                      |
|------|-------------------------------------|------------------------------------------------------|
| 1    | GET /api/products without token     | 401 response                                        |
| 2    | Verify response format              | JSON with "message": "Unauthenticated"              |
| 3    | GET /api/auth/profile without token | 401 response                                        |
| 4    | POST /api/products without token    | 401 response                                        |
| 5    | Use expired token                   | 401 response                                        |

---

## TC-API-001-07: Wrong Org Token Returns 403

| Field         | Value                     |
|---------------|---------------------------|
| Priority      | P0                        |
| Type          | Feature                   |
| Platform      | API                       |
| Automated     | Yes                       |

**Preconditions:** Two organizations exist. User authenticated to Org A.

| Step | Action                              | Expected Result                                      |
|------|-------------------------------------|------------------------------------------------------|
| 1    | GET /api/products scoped to Org B   | 403 Forbidden response                              |
| 2    | Verify no data leaked               | Response body contains no Org B data                 |
| 3    | GET resource belonging to Org B     | 403 response                                        |
| 4    | Verify audit log                    | Unauthorized access attempt logged                   |

---

## TC-API-001-08: POST /api/auth/two-factor-challenge Validates Code

| Field         | Value                     |
|---------------|---------------------------|
| Priority      | P1                        |
| Type          | Feature                   |
| Platform      | API                       |
| Automated     | Yes                       |

**Preconditions:** User has 2FA enabled. Login returned 2FA challenge requirement.

| Step | Action                              | Expected Result                                      |
|------|-------------------------------------|------------------------------------------------------|
| 1    | POST /api/auth/two-factor-challenge with valid TOTP code | 200 response with token       |
| 2    | Verify token works                  | Token authenticates subsequent requests              |
| 3    | POST with invalid code              | 422 response with invalid code error                 |
| 4    | POST with recovery code             | 200 response with token (recovery code consumed)     |
| 5    | POST with used recovery code        | 422 response                                        |
