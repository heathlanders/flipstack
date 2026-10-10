# TC-API-007: User & Team Endpoints

| Field            | Value                                              |
|------------------|----------------------------------------------------|
| Document ID      | TC-API-007                                         |
| Domain           | API Endpoints                                      |
| Related Test Plan| TP-API (api-endpoints-test-plan.md)                |
| Last Updated     | 2026-04-15                                         |

---

## TC-API-007-01: GET /api/users List

| Field         | Value                     |
|---------------|---------------------------|
| Priority      | P0                        |
| Type          | Feature                   |
| Platform      | API                       |
| Automated     | Yes                       |

**Preconditions:** User authenticated with admin permissions. Multiple users in organization.

| Step | Action                              | Expected Result                                      |
|------|-------------------------------------|------------------------------------------------------|
| 1    | GET /api/users                      | 200 response with user list                          |
| 2    | Verify user fields                  | id, name, email, role, team, created_at per user     |
| 3    | Verify org scoping                  | Only users from current organization returned        |
| 4    | Non-admin user requests             | 403 response or limited fields returned              |
| 5    | Verify pagination                   | Response supports paginated navigation               |

---

## TC-API-007-02: POST /api/users Create

| Field         | Value                     |
|---------------|---------------------------|
| Priority      | P0                        |
| Type          | Feature                   |
| Platform      | API                       |
| Automated     | Yes                       |

**Preconditions:** User has admin permissions. Valid role and team exist.

| Step | Action                              | Expected Result                                      |
|------|-------------------------------------|------------------------------------------------------|
| 1    | POST /api/users with name, email, role_id | 201 response with created user                |
| 2    | Verify user belongs to org          | New user associated with current organization        |
| 3    | Verify invitation sent              | Invitation email dispatched to new user              |
| 4    | POST with existing email            | 422 response with duplicate email error              |
| 5    | POST without admin permissions      | 403 response                                        |
| 6    | POST with invalid role_id           | 422 response with validation error                   |

---

## TC-API-007-03: GET /api/roles List

| Field         | Value                     |
|---------------|---------------------------|
| Priority      | P1                        |
| Type          | Feature                   |
| Platform      | API                       |
| Automated     | Yes                       |

**Preconditions:** Roles configured for the organization.

| Step | Action                              | Expected Result                                      |
|------|-------------------------------------|------------------------------------------------------|
| 1    | GET /api/roles                      | 200 response with role list                          |
| 2    | Verify role fields                  | id, name, permissions array per role                 |
| 3    | Verify org scoping                  | Only roles for current org returned                  |
| 4    | Verify system roles included        | Default roles (Owner, Admin) present                 |
| 5    | Verify custom roles included        | Organization-created roles present                   |

---

## TC-API-007-04: POST /api/roles Create With Permissions

| Field         | Value                     |
|---------------|---------------------------|
| Priority      | P1                        |
| Type          | Feature                   |
| Platform      | API                       |
| Automated     | Yes                       |

**Preconditions:** User has admin permissions.

| Step | Action                              | Expected Result                                      |
|------|-------------------------------------|------------------------------------------------------|
| 1    | POST /api/roles with name and permissions array | 201 response with created role          |
| 2    | Verify permissions saved            | Role has specified permissions attached               |
| 3    | Verify org scoping                  | Role belongs to current organization                 |
| 4    | POST with duplicate role name       | 422 response with uniqueness error                   |
| 5    | POST with invalid permission keys   | 422 response with validation error                   |
| 6    | POST without admin permissions      | 403 response                                        |

---

## TC-API-007-05: GET /api/teams List

| Field         | Value                     |
|---------------|---------------------------|
| Priority      | P1                        |
| Type          | Feature                   |
| Platform      | API                       |
| Automated     | Yes                       |

**Preconditions:** Teams exist in the organization.

| Step | Action                              | Expected Result                                      |
|------|-------------------------------------|------------------------------------------------------|
| 1    | GET /api/teams                      | 200 response with team list                          |
| 2    | Verify team fields                  | id, name, member_count per team                      |
| 3    | Verify org scoping                  | Only teams from current org returned                 |
| 4    | No teams exist                      | 200 response with empty array                        |
| 5    | Verify current user's team flagged  | User's active team identified in response            |

---

## TC-API-007-06: POST /api/teams Create

| Field         | Value                     |
|---------------|---------------------------|
| Priority      | P1                        |
| Type          | Feature                   |
| Platform      | API                       |
| Automated     | Yes                       |

**Preconditions:** User has team creation permissions.

| Step | Action                              | Expected Result                                      |
|------|-------------------------------------|------------------------------------------------------|
| 1    | POST /api/teams with name           | 201 response with created team                       |
| 2    | Verify team belongs to org          | Team associated with current organization            |
| 3    | Verify creator as owner             | Creating user added as team owner                    |
| 4    | POST with duplicate name            | 422 response with uniqueness error                   |
| 5    | POST without permissions            | 403 response                                        |

---

## TC-API-007-07: POST /api/teams/{id}/join

| Field         | Value                     |
|---------------|---------------------------|
| Priority      | P2                        |
| Type          | Feature                   |
| Platform      | API                       |
| Automated     | Yes                       |

**Preconditions:** Team exists. User has invitation or open join policy.

| Step | Action                              | Expected Result                                      |
|------|-------------------------------------|------------------------------------------------------|
| 1    | POST /api/teams/{id}/join           | 200 response                                        |
| 2    | Verify membership created           | User added to team membership                        |
| 3    | Verify default role assigned        | User given default team member role                  |
| 4    | POST when already a member          | 422 response (already a member)                      |
| 5    | POST for team in another org        | 404 response                                        |

---

## TC-API-007-08: GET /api/organization/stats

| Field         | Value                     |
|---------------|---------------------------|
| Priority      | P1                        |
| Type          | Feature                   |
| Platform      | API                       |
| Automated     | Yes                       |

**Preconditions:** User authenticated. Organization has users, teams, and products.

| Step | Action                              | Expected Result                                      |
|------|-------------------------------------|------------------------------------------------------|
| 1    | GET /api/organization/stats         | 200 response with organization statistics            |
| 2    | Verify user count                   | Total users in organization                          |
| 3    | Verify team count                   | Total teams in organization                          |
| 4    | Verify product count                | Total products in organization                       |
| 5    | Verify org scoping                  | Stats reflect only current organization data         |
