# Communication Test Plan
## Document ID: FLIPSTACK-TP-COM-001
**Version:** 1.0 | **Last Updated:** 2026-04-15 | **Status:** Approved

## Scope
Verify marketplace buyer message inbox, thread viewing, reply sending, read status tracking, and message archival.

## Related Docs
| Resource | Path |
|----------|------|
| Feature docs | `docs/COMMUNICATION.md` |
| Existing tests | `tests/Feature/MessageTest.php` |

## Features Under Test
| # | Feature | Controller@method | Route Name | Priority |
|---|---------|-------------------|------------|----------|
| 1 | Message inbox listing | MessageController@index | messages.index | High |
| 2 | Thread view | MessageController@show | messages.thread | High |
| 3 | Send reply | MessageController@reply | messages.reply | High |
| 4 | Mark as read | MessageController@markAsRead | messages.read | Medium |
| 5 | Archive message | MessageController@archive | messages.archive | Medium |

## Test Cases
| ID | Title | File |
|----|-------|------|
| TC-COM-001 | Marketplace message inbox, threads, replies, read/archive | `tests/Feature/Communication/MessageFlowTest.php` |

## Coverage Goals
| Metric | Current | Target |
|--------|---------|--------|
| Feature test coverage | Partial (MessageTest.php) | 90% route coverage |
| MarketplaceMessage model coverage | Low | 80% |
| MarketplaceMessageReply model coverage | Low | 80% |
