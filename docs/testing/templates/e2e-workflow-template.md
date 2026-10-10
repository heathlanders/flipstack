# [Workflow Name] End-to-End Test

## Document ID: TC-E2E-[NUMBER]

**Systems Involved:** [Web, Mobile, API, eBay, Shopify, Queue, etc.]
**Business Process:** [Brief description of the real-world workflow]
**Last Updated:** YYYY-MM-DD

---

## 1. Workflow Overview

<!-- Describe the full business process from start to finish.
     A diagram (ASCII or Mermaid) is helpful here. -->

```
[Step 1] --> [Step 2] --> [Step 3] --> ... --> [End State]
```

**Trigger:** [What initiates this workflow]
**End State:** [What the final state looks like when successful]

---

## 2. Preconditions

| # | Requirement | Setup Method |
|---|-------------|--------------|
| 1 | Organization with active subscription | Factory / Seeder |
| 2 | [Connected marketplace account] | [Mock / Sandbox] |
| 3 | [Existing data requirements] | [Factory / Manual] |
| 4 | [User role/permissions] | [Factory] |

---

## 3. Test Scenario

| Step | Actor | System | Action | Expected Result | Verification Method |
|------|-------|--------|--------|-----------------|---------------------|
| 1 | [User/System/Cron/Webhook] | [Web/Mobile/API/Queue] | [Specific action taken] | [What should happen] | [How to verify: DB query, UI check, API call] |
| 2 | | | | | |
| 3 | | | | | |
| 4 | | | | | |

---

## 4. Data Flow Verification

Check system state at critical points in the workflow.

| Checkpoint | After Step | Expected State | Verification Query/Check |
|------------|-----------|----------------|--------------------------|
| [Name] | Step N | [Expected DB/UI/API state] | `[SQL query or artisan command or UI path]` |
| | | | |

---

## 5. Error Scenarios

| ID | Error Point | Error Condition | Expected Recovery | Verified |
|----|-------------|-----------------|-------------------|----------|
| E2E-[N]-ERR-01 | Step X | [What goes wrong] | [How system recovers] | [ ] |
| E2E-[N]-ERR-02 | Step Y | [External service down] | [Retry / queue / fallback] | [ ] |

---

## 6. Performance Expectations

| Metric | Acceptable | Measured |
|--------|-----------|----------|
| End-to-end completion time | [e.g., < 5 minutes] | |
| Queue job processing time | [e.g., < 30 seconds] | |
| API response times | [e.g., < 500ms] | |

---

## 7. Cleanup / Rollback

<!-- Steps to reset the test environment after execution -->

| Step | Action |
|------|--------|
| 1 | [Reset database state or specific records] |
| 2 | [Cancel any external marketplace actions] |
| 3 | [Clear queue / cache] |

---

## Notes

- [Timing dependencies between steps]
- [External system SLAs or availability windows]
- [Known flaky areas in this workflow]
