# Printing Test Plan
## Document ID: FLIPSTACK-TP-PRT-001
**Version:** 1.0 | **Last Updated:** 2026-04-15 | **Status:** Approved

## Scope
Verify label printing workflows, printer configuration management, and print template CRUD operations.

## Related Docs
| Resource | Path |
|----------|------|
| Feature docs | `docs/LABEL_PRINTING.md` |
| Existing tests | None |

## Features Under Test
| # | Feature | Controller@method | Route Name | Priority |
|---|---------|-------------------|------------|----------|
| 1 | Print label (product) | LabelPrintingController@printProduct | printing.product | High |
| 2 | Print label (batch) | LabelPrintingController@printBatch | printing.batch | High |
| 3 | Print label (bin) | LabelPrintingController@printBin | printing.bin | Medium |
| 4 | Printer list | PrinterController@index | printing.printers.index | Medium |
| 5 | Printer store | PrinterController@store | printing.printers.store | Medium |
| 6 | Printer update | PrinterController@update | printing.printers.update | Low |
| 7 | Printer delete | PrinterController@destroy | printing.printers.destroy | Low |
| 8 | Template list | TemplateController@index | printing.templates.index | Medium |
| 9 | Template store | TemplateController@store | printing.templates.store | Medium |
| 10 | Template update | TemplateController@update | printing.templates.update | Low |

## Test Cases
| ID | Title | File |
|----|-------|------|
| TC-PRT-001 | Label generation for products, batches, and bins | `tests/Feature/Printing/LabelPrintingTest.php` |
| TC-PRT-002 | Printer and template configuration CRUD | `tests/Feature/Printing/PrinterTemplateTest.php` |

## Coverage Goals
| Metric | Current | Target |
|--------|---------|--------|
| Feature test coverage | None | 85% route coverage |
| LabelPrintingController coverage | None | 80% |
| PrinterController coverage | None | 75% |
| TemplateController coverage | None | 75% |
