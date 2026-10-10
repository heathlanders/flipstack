# Team Management Test Cases

## Document ID: TC-AUT-003

**Domain:** Auth / Organization
**Related Test Plan:** `docs/testing/plans/auth-test-plan.md`
**Last Updated:** 2026-04-15

---

### TC-AUT-003-01: Create team

| Field | Value |
|-------|-------|
| **ID** | TC-AUT-003-01 |
| **Priority** | P0 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |

**Preconditions:**
1. Authenticated user with `create-teams` ability
2. User belongs to an organization

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | GET `teams.create` | 200 response, team creation form rendered |
| 2 | POST `teams.store` with valid team name | Redirect to teams.index or teams.show with success flash |
| 3 | Verify Team record | Team created with correct organization_id and name |
| 4 | Verify membership | Creating user added as team owner/admin |
| 5 | POST with empty name | Validation error: name is required |
| 6 | User without `create-teams` ability attempts POST | 403 Forbidden |

---

### TC-AUT-003-02: Invite user to team

| Field | Value |
|-------|-------|
| **ID** | TC-AUT-003-02 |
| **Priority** | P0 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |

**Preconditions:**
1. Authenticated user with `manage-team` ability for the target team
2. An existing team belonging to the user's organization

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | POST `teams.invite` with email and role | Invitation record created, invitation email sent |
| 2 | Verify invitation record | Contains team_id, email, assigned role |
| 3 | POST invite with already-member email | Error: user is already a member of this team |
| 4 | POST invite with invalid email format | Validation error |
| 5 | User without `manage-team` ability attempts invite | 403 Forbidden |

---

### TC-AUT-003-03: Accept invitation

| Field | Value |
|-------|-------|
| **ID** | TC-AUT-003-03 |
| **Priority** | P0 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |

**Preconditions:**
1. A pending team invitation with a valid token
2. Invited user either exists or is new

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | GET `/invitations/{token}/accept` | 200 response, acceptance form rendered with team and organization info |
| 2 | POST `/invitations/{token}/accept` for existing user (authenticated) | User added to team with assigned role, redirect to dashboard |
| 3 | POST for new user with registration data | User account created, added to organization and team, redirect to dashboard |
| 4 | Verify team membership | User appears in team members with correct role |
| 5 | GET with expired or invalid token | Error page or redirect with error message |
| 6 | POST accept for already-accepted invitation | Error: invitation already accepted |

**Notes:**
- Invitation routes are public (outside auth middleware) to support guest acceptance
- Routes: `invitations.accept` (GET) and `invitations.accept.store` (POST)

---

### TC-AUT-003-04: Remove member from team

| Field | Value |
|-------|-------|
| **ID** | TC-AUT-003-04 |
| **Priority** | P1 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |

**Preconditions:**
1. Authenticated user with `manage-team` ability for the team
2. Team has at least 2 members

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | POST `teams.remove-member` with target user | Member removed from team, redirect back with success |
| 2 | Verify membership | Removed user no longer in team's member list |
| 3 | Attempt to remove self | Handled appropriately (either prevented or user removed) |
| 4 | Attempt to remove last admin/owner | Error: cannot remove the last team owner |
| 5 | User without `manage-team` ability attempts removal | 403 Forbidden |

---

### TC-AUT-003-05: Update member role

| Field | Value |
|-------|-------|
| **ID** | TC-AUT-003-05 |
| **Priority** | P1 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |

**Preconditions:**
1. Authenticated user with `manage-team` ability
2. Team member with a current role assignment

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | PUT `teams.update-member-role` with team, user, and new role | Role updated, redirect back with success |
| 2 | Verify role change | Member's role in team updated in pivot/membership table |
| 3 | Attempt to change own role to non-admin | Handled appropriately (prevented if last admin) |
| 4 | PUT with invalid role value | Validation error |
| 5 | User without `manage-team` ability attempts role change | 403 Forbidden |

---

### TC-AUT-003-06: Team belongs to organization

| Field | Value |
|-------|-------|
| **ID** | TC-AUT-003-06 |
| **Priority** | P0 |
| **Type** | Security |
| **Platform** | Web |
| **Automated** | No |

**Preconditions:**
1. Two organizations (Org A, Org B) each with teams
2. User authenticated as Org A member

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | GET `teams.index` as Org A user | Only Org A teams listed |
| 2 | GET `teams.show` for an Org A team | 200 response |
| 3 | GET `teams.show` for an Org B team | 403 Forbidden (view-team ability check fails) |
| 4 | POST `teams.store` | New team created with Org A's organization_id |
| 5 | POST `teams.invite` on Org B team | 403 Forbidden (manage-team ability check) |

**Notes:**
- Team access is controlled by ability checks (can:view-team, can:manage-team) which verify the user's relationship to the team and organization
- Organization ID is never accepted from client input when creating teams
