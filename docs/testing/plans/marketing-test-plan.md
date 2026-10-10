# Marketing Test Plan
## Document ID: FLIPSTACK-TP-MRK-001
**Version:** 1.0 | **Last Updated:** 2026-04-15 | **Status:** Approved

## Scope
Verify email campaign CRUD, toggle activation, statistics display, and template preview rendering.

## Related Docs
| Resource | Path |
|----------|------|
| Feature docs | `docs/EMAIL_CAMPAIGNS.md` |
| Existing tests | `tests/Feature/EmailCampaignTest.php` |

## Features Under Test
| # | Feature | Controller@method | Route Name | Priority |
|---|---------|-------------------|------------|----------|
| 1 | Campaign listing | EmailCampaignController@index | marketing.campaigns.index | High |
| 2 | Create campaign form | EmailCampaignController@create | marketing.campaigns.create | High |
| 3 | Store campaign | EmailCampaignController@store | marketing.campaigns.store | High |
| 4 | Edit campaign | EmailCampaignController@edit | marketing.campaigns.edit | Medium |
| 5 | Update campaign | EmailCampaignController@update | marketing.campaigns.update | Medium |
| 6 | Delete campaign | EmailCampaignController@destroy | marketing.campaigns.destroy | Medium |
| 7 | Toggle active/inactive | EmailCampaignController@toggle | marketing.campaigns.toggle | High |
| 8 | Campaign statistics | EmailCampaignController@stats | marketing.campaigns.stats | Medium |
| 9 | Template preview | EmailCampaignController@preview | marketing.campaigns.preview | Low |

## Test Cases
| ID | Title | File |
|----|-------|------|
| TC-MRK-001 | Email campaign CRUD, toggle, stats, and preview | `tests/Feature/Marketing/EmailCampaignFlowTest.php` |

## Coverage Goals
| Metric | Current | Target |
|--------|---------|--------|
| Feature test coverage | Partial (EmailCampaignTest.php) | 90% route coverage |
| EmailCampaign model coverage | Low | 80% |
| EmailCampaignSend model coverage | None | 70% |
