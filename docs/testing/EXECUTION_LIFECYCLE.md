# Test Execution Lifecycle

**Document ID:** FLIPSTACK-EXEC-001
**Version:** 1.0
**Last Updated:** 2026-04-27

---

## 1. Purpose

Defines how a feature test cycle is planned, executed, tracked, and closed. The **GitHub Project v2 board** is the canonical execution tracker — markdown docs in this repo describe *what* to test; the project board records *what was tested, when, by whom, with what result*.

---

## 2. Roles

| Role | Responsibility |
|------|---------------|
| **QA Lead** | Owns the cycle. Creates run log, monitors board, signs off close. |
| **Tester** | Executes test cases, records results, files bugs. |
| **Engineering Owner** | Triages bugs, assigns fixes, approves close. |
| **Reviewer** | (Optional) Spot-checks evidence and verifies regressions before close. |

---

## 3. Artifacts

| Artifact | Location |
|----------|----------|
| Test plan | `docs/testing/plans/<domain>-test-plan.md` |
| Test case (what to test) | `docs/testing/cases/<domain>/TC-XXX-*.md` |
| Run log (what happened) | `docs/testing/runs/<YYYY-MM-DD>-<cycle-id>.md` (from `templates/test-execution-run.md`) |
| GitHub Project board | One per cycle, e.g. "FlipStack QA: Marketplace Sync v1" |
| Test-case issue | One GitHub issue per `TC-XXX` doc, label `test-case` |
| Bug issue | GitHub issue from `bug-report.yml` template, links to the test-case issue |
| Milestone | One per cycle, e.g. "Marketplace Sync v1" |

---

## 4. Cycle Lifecycle

```
┌──────────┐   ┌──────────────┐   ┌─────────────┐   ┌──────────────────┐   ┌──────────┐   ┌──────┐
│ Backlog  │ ─► Ready to Test ─► In Progress ─► Blocked / Bug Found ─► Verified ─► Done │
└──────────┘   └──────────────┘   └─────────────┘   └──────────────────┘   └──────────┘   └──────┘
```

### 4.1 Cycle Kickoff (QA Lead)

1. Identify the in-scope test cases (see this cycle's plan or §5 below).
2. Run `scripts/testing/seed-test-cycle.sh <cycle-id>` to create:
   - The GH Project (if it doesn't exist)
   - Labels and milestone for the cycle
   - One issue per in-scope test case
   - Project items in the **Backlog** column
3. Copy `docs/testing/templates/test-execution-run.md` to `docs/testing/runs/<YYYY-MM-DD>-<cycle-id>.md` and fill the header.
4. When the test environment is provisioned and seeded, move all in-scope items to **Ready to Test**.

### 4.2 Run (Tester)

For each test-case issue:

1. Move the project item to **In Progress** and assign yourself.
2. Open the linked TC doc; execute each sub-case in order.
3. For each sub-case, record `Pass / Fail / Blocked / Skipped` in the run log with evidence (screenshot, query result, log snippet).
4. If all sub-cases pass: set `Result=Pass`, move to **Verified**, comment on the issue with the run-log path.
5. If any sub-case fails: see §4.3.
6. If preconditions cannot be met: set `Result=Blocked`, move to **Blocked / Bug Found**, document why.

### 4.3 Failure (Tester + Engineering Owner)

1. Tester opens a new issue from the `bug-report` template, includes:
   - Failing TC sub-case ID (e.g., `TC-MKT-002-03`)
   - Steps to reproduce (already in the TC doc)
   - Actual vs. expected
   - Evidence (logs, screenshots)
   - Severity (P0/P1/P2/P3)
2. Tester adds `Blocked by #<bug-issue>` to the test-case issue body.
3. Tester moves the test-case project item to **Blocked / Bug Found**, sets `Result=Fail`.
4. Engineering Owner triages the bug, assigns to a developer, links the fixing PR.
5. When the fix is merged, the developer pings the tester to re-run the affected sub-case.
6. Tester re-executes the sub-case. If it now passes, update the run log and move the test-case item to **Verified**, set `Result=Pass`.

### 4.4 Cycle Close (QA Lead)

When every in-scope item is in **Verified** (or **Done**):

1. Confirm every filed bug is closed (or has a documented deferral with a follow-up issue).
2. QA Lead and Engineering Owner sign off in §5 of the run log.
3. Move all items to **Done**.
4. Close the milestone.
5. Archive the run log path under §6 of the run log (immutable history).
6. Update `docs/testing/COVERAGE_MATRIX.md` if any tests were newly automated.
7. If new gaps surfaced, create follow-up test-case docs (do **not** retrofit them into the closed cycle).

---

## 5. Cycle Catalog

| Cycle ID | Title | Scope | Run Log | Status |
|----------|-------|-------|---------|--------|
| `v1-marketplace-sync` | Marketplace Sync v1 | TC-MKT-002, 003, 007, 008, 009, 011, 012, 013 | `runs/2026-04-27-marketplace-sync-v1.md` | (TBD) |

Add new cycles to this table as they are kicked off.

---

## 6. Project Board Configuration

The seed script provisions:

- **Project name:** `FlipStack QA: <Cycle Title>`
- **Visibility:** Organization-readable (private to repo collaborators)
- **Status field** (single-select):
  - `Backlog` (default for new items)
  - `Ready to Test`
  - `In Progress`
  - `Blocked / Bug Found`
  - `Verified`
  - `Done`
- **Custom fields:**
  - `Priority` (single-select: P0 / P1 / P2 / P3)
  - `Test Type` (single-select: Unit / Feature / Integration / E2E / UI)
  - `Cycle` (text)
  - `Result` (single-select: Pass / Fail / Blocked / Skipped / Not Run)
- **Workflow rules:**
  - Auto-archive `Done` items after 30 days
  - Auto-add new issues with `cycle:<id>` label to the project
- **Default views:**
  - Board, grouped by `Status`, filtered by `Cycle = <cycle-id>`
  - Table, all fields, sorted by `Priority` then `Status`

---

## 7. Pass/Fail Criteria for the Cycle

| Outcome | Definition |
|---------|------------|
| **Pass** | 100% of P0 sub-cases Pass; ≥95% of P1 sub-cases Pass; all filed bugs closed or deferred with owner. |
| **Conditional Pass** | One or more P1 sub-cases Fail with documented deferral, no P0 failures. |
| **Fail** | Any P0 sub-case Fails without a verified fix; cycle cannot be released. |

---

## 8. References

- `MASTER_TEST_PLAN.md` — overall testing strategy (§13 links here).
- `REGRESSION_SUITE.md` — pre-deploy checklist; see §1 for board reference.
- `templates/test-execution-run.md` — run log template.
- `.github/ISSUE_TEMPLATE/test-case.yml` — test-case issue form.
- `.github/ISSUE_TEMPLATE/bug-report.yml` — bug issue form.
- `scripts/testing/seed-test-cycle.sh` — board provisioning + issue bulk-create.
