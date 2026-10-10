# Label Printing Test Cases

## Document ID: TC-PRT-001

**Domain:** Printing
**Related Test Plan:** `docs/testing/plans/printing-test-plan.md`
**Last Updated:** 2026-04-15

---

### TC-PRT-001-01: Print single product label

| Field | Value |
|-------|-------|
| **ID** | TC-PRT-001-01 |
| **Priority** | P0 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |

**Preconditions:**
1. Authenticated user
2. An existing Product record
3. At least one active OrganizationPrinter with a default LabelTemplate configured

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | POST printSingle for a product with default quantity=1 | JSON response: { success: true, message: "Label print job queued" } |
| 2 | Verify print job | LabelPrintingService::printOnDemand called, PrintJob record created with job_type=on_demand |
| 3 | POST with quantity=5 | Print job queued for 5 labels |
| 4 | POST with quantity=0 | Validation error: min quantity is 1 |
| 5 | POST with quantity=101 | Validation error: max quantity is 100 |
| 6 | POST when no active printer exists | JSON response: { success: false, message: "Failed to queue print job" } |

**Notes:**
- Validation: quantity (int, 1-100), template_id (optional, exists in label_templates), printer_id (optional, exists in organization_printers)
- If template_id or printer_id not provided, organization defaults are used

---

### TC-PRT-001-02: Print batch labels for multiple products

| Field | Value |
|-------|-------|
| **ID** | TC-PRT-001-02 |
| **Priority** | P0 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |

**Preconditions:**
1. Authenticated user
2. Multiple Product records exist
3. Active printer and template configured

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | POST printBatch with product_ids array of 3 products, quantity=1 | JSON response: { success: true, total_jobs: 3, successful_jobs: 3 } |
| 2 | Verify jobs | LabelPrintingService::printBatch creates individual print jobs per product |
| 3 | POST with empty product_ids array | Validation error: product_ids is required |
| 4 | POST with invalid product_id in array | Validation error: product_ids.* must exist in products table |
| 5 | POST batch with one product failing | JSON: { success: true, total_jobs: 3, successful_jobs: 2 } (partial success) |
| 6 | POST with custom template_id and printer_id | Jobs created using specified template and printer |

---

### TC-PRT-001-03: Select printer for job

| Field | Value |
|-------|-------|
| **ID** | TC-PRT-001-03 |
| **Priority** | P1 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |

**Preconditions:**
1. Authenticated user
2. Multiple OrganizationPrinter records exist for the organization (e.g., one Zebra, one Dymo)

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | POST printSingle with printer_id for Zebra printer | Print job uses Zebra printer, not default |
| 2 | POST printSingle with printer_id for Dymo printer | Print job uses Dymo printer |
| 3 | POST printSingle without printer_id | Print job uses organization's default printer |
| 4 | POST with printer_id that does not exist | Validation error: printer_id must exist in organization_printers |

---

### TC-PRT-001-04: Select template for label

| Field | Value |
|-------|-------|
| **ID** | TC-PRT-001-04 |
| **Priority** | P1 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |

**Preconditions:**
1. Authenticated user
2. Multiple LabelTemplate records exist (e.g., small label, large label)

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | POST printSingle with template_id for small label template | Print job uses specified template for label layout |
| 2 | POST printSingle with template_id for large label template | Print job uses large template |
| 3 | POST printSingle without template_id | Organization's default label template used |
| 4 | POST with template_id that does not exist | Validation error: template_id must exist in label_templates |

---

### TC-PRT-001-05: Print job queued and tracked

| Field | Value |
|-------|-------|
| **ID** | TC-PRT-001-05 |
| **Priority** | P1 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |

**Preconditions:**
1. Authenticated user
2. Active printer and template available

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | POST printSingle for a product | Print job record created in database |
| 2 | Verify PrintJob record | Contains: organization_id, printer_id, template_id, product_id, job_type, quantity, label_data, created_by |
| 3 | Verify job dispatch | ProcessPrintJob queued for async processing |
| 4 | Verify job status tracking | PrintJob status transitions: pending -> processing -> completed (or failed) |
| 5 | GET print jobs list (printing.jobs.index) | Job appears in the list with current status |

**Notes:**
- Auto-print on SKU creation uses job_type=auto_sku; on-demand uses job_type=on_demand
- PrintJob can be viewed via printing.jobs.index and printing.jobs.show routes

---

### TC-PRT-001-06: Label includes SKU, barcode, price, name

| Field | Value |
|-------|-------|
| **ID** | TC-PRT-001-06 |
| **Priority** | P0 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |

**Preconditions:**
1. A Product with sku, name, and price set
2. Product has a barcode/UPC identifier

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Trigger label print for the product | LabelPrintingService::generateLabelData called for the product |
| 2 | Verify label_data contains product name | Product name included in label data |
| 3 | Verify label_data contains SKU | Product SKU included in label data |
| 4 | Verify label_data contains barcode | Barcode/UPC value included for barcode rendering |
| 5 | Verify label_data contains price | Product price included in label data |
| 6 | Product without SKU | Label renders without SKU field (graceful handling of missing optional fields) |

**Notes:**
- Label data is generated by LabelPrintingService::generateLabelData and stored on the PrintJob record
- Actual barcode rendering (Code 128, QR) depends on the printer driver (ZPL, Dymo, etc.)
