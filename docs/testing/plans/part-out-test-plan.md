# Part-Out Test Plan
## Document ID: FLIPSTACK-TP-CAT-002
**Version:** 1.0 | **Last Updated:** 2026-04-15 | **Status:** Approved

## Scope
Verify part-out template CRUD, work order lifecycle (draft -> in_progress -> completed/cancelled), component creation, and cost allocation.

## Related Docs
| Resource | Path |
|----------|------|
| Feature docs | `docs/PART_OUT_SYSTEM.md` |
| Existing tests | None |

## Features Under Test
| # | Feature | Controller@method | Route Name | Priority |
|---|---------|-------------------|------------|----------|
| 1 | Template listing | PartOutTemplateController@index | inventory.part-out.templates.index | High |
| 2 | Template store | PartOutTemplateController@store | inventory.part-out.templates.store | High |
| 3 | Template show | PartOutTemplateController@show | inventory.part-out.templates.show | Medium |
| 4 | Template update | PartOutTemplateController@update | inventory.part-out.templates.update | Medium |
| 5 | Template delete | PartOutTemplateController@destroy | inventory.part-out.templates.destroy | Low |
| 6 | Work order listing | PartOutWorkOrderController@index | inventory.part-out.work-orders.index | High |
| 7 | Work order create | PartOutWorkOrderController@store | inventory.part-out.work-orders.store | High |
| 8 | Work order show | PartOutWorkOrderController@show | inventory.part-out.work-orders.show | High |
| 9 | Start work order | PartOutWorkOrderController@start | inventory.part-out.work-orders.start | High |
| 10 | Complete work order | PartOutWorkOrderController@complete | inventory.part-out.work-orders.complete | High |
| 11 | Cancel work order | PartOutWorkOrderController@cancel | inventory.part-out.work-orders.cancel | Medium |

## Test Cases
| ID | Title | File |
|----|-------|------|
| TC-CAT-002 | Part-out template CRUD and work order lifecycle (draft/in_progress/completed/cancelled) | `tests/Feature/Catalog/PartOutFlowTest.php` |

## Coverage Goals
| Metric | Current | Target |
|--------|---------|--------|
| Feature test coverage | None | 85% route coverage |
| PartOutWorkOrder model coverage | None | 80% |
| PartOutTemplate model coverage | None | 80% |
| Cost allocation logic coverage | None | 90% |
