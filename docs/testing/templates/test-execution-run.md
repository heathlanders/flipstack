# Test Execution Run

## Document ID: RUN-[CYCLE_ID]-[YYYY-MM-DD]

| Field | Value |
|-------|-------|
| **Cycle ID** | e.g., `v1-marketplace-sync` |
| **Run Date** | YYYY-MM-DD |
| **Tester** | Name / handle |
| **Build SHA** | Git commit short hash deployed to the test environment |
| **Environment** | `staging` / `production` / `local` |
| **Test Account** | Description (e.g., "eBay prod test seller + Shopify dev store") |
| **GH Project** | Link to project board |
| **Milestone** | Link to GH milestone |
| **Status** | `In Progress` / `Complete` / `Aborted` |

---

## 1. Scope

List each test-case issue tracked in this run.

| TC ID | Issue # | Doc | Owner |
|-------|---------|-----|-------|
| TC-MKT-XXX | #N | `docs/testing/cases/marketplace/TC-MKT-XXX-*.md` | tester |

---

## 2. Results — Sub-case Detail

One row per sub-case. Mirror the sub-case IDs from the linked TC doc.

| Sub-case ID | Title | Result | Bug Issue | Evidence | Notes |
|-------------|-------|--------|-----------|----------|-------|
| TC-MKT-XXX-01 | ... | Pass / Fail / Blocked / Skipped | #N (if failed) | screenshot / log link | |
| TC-MKT-XXX-02 | ... | | | | |

**Result legend:**
- **Pass** — actual matches expected for every step
- **Fail** — at least one step deviates from expected; bug filed
- **Blocked** — preconditions not met; environment/data/dependency issue
- **Skipped** — intentionally not run this cycle (note reason)

---

## 3. Bugs Filed

| Bug Issue | Title | Severity | Sub-case Source | Status |
|-----------|-------|----------|-----------------|--------|
| #N | ... | P0/P1/P2 | TC-MKT-XXX-NN | Open / In PR / Verified / Closed |

---

## 4. Cycle Summary

| Metric | Value |
|--------|-------|
| Sub-cases planned | |
| Pass | |
| Fail | |
| Blocked | |
| Skipped | |
| Pass rate | `<pass> / <planned>` |
| Bugs filed | |
| Bugs verified before close | |

---

## 5. Sign-off

| Role | Name | Date | Decision |
|------|------|------|----------|
| QA Lead | | | Approve / Reject |
| Engineering Owner | | | Approve / Reject |

---

## 6. Notes / Follow-ups

- [ ] Update `docs/testing/COVERAGE_MATRIX.md` with any newly automated tests
- [ ] Update `docs/testing/REGRESSION_SUITE.md` if a new gap was found
- [ ] File follow-up test cases for any deferred scenarios
- [ ] Close GH milestone once all bugs verified
