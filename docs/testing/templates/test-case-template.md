# [Feature Area] Test Cases

## Document ID: TC-[DOMAIN]-[NUMBER]

**Domain:** [Domain Name]
**Related Test Plan:** `docs/testing/plans/[domain]-test-plan.md`
**Last Updated:** YYYY-MM-DD

---

### TC-[DOMAIN]-[NUM]-01: [Test Case Title]

| Field | Value |
|-------|-------|
| **ID** | TC-[DOMAIN]-[NUM]-01 |
| **Priority** | P0 / P1 / P2 / P3 |
| **Type** | Unit / Feature / Integration / E2E / UI |
| **Platform** | Web / Mobile / API |
| **Automated** | Yes / No / Partial |
| **Test File** | `tests/Feature/[Domain]/[File].php` or N/A |
| **Test Method** | `test_method_name()` or N/A |

**Preconditions:**

1. [Required state, data, or setup]
2. [User role or permissions needed]
3. [External service state]

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | [Action description] | [Expected outcome] |
| 2 | [Action description] | [Expected outcome] |
| 3 | [Action description] | [Expected outcome] |

**Test Data:**

```
[Specific data values, factory states, JSON payloads, etc.]
```

**Postconditions:**

- [State after test completes]
- [Database changes expected]
- [Side effects (jobs dispatched, events fired)]

**Notes:**

- [Edge cases to consider]
- [Related bugs or known issues]
- [Dependencies on other test cases]

---

### TC-[DOMAIN]-[NUM]-02: [Next Test Case Title]

<!-- Repeat the template block above for each test case -->
