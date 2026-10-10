# TC-INV-008: Product Import and Export

| Field             | Value                                      |
|-------------------|--------------------------------------------|
| Document ID       | TC-INV-008                                 |
| Domain            | Inventory                                  |
| Related Test Plan | TP-INV-001 Product Management              |
| Last Updated      | 2026-04-15                                 |
| Controller        | ImportExportController                     |
| Routes            | inventory.import.*, inventory.export.*     |

---

## TC-INV-008-01: Download Import Template

| Field          | Value                                                        |
|----------------|--------------------------------------------------------------|
| ID             | TC-INV-008-01                                                |
| Priority       | P1                                                           |
| Type           | Functional                                                   |
| Platform       | Web                                                          |
| Automated      | No                                                           |
| Test File      | --                                                           |

**Preconditions:**
- User is authenticated with inventory management permissions.

**Test Steps:**

| Step | Action                                                  | Expected Result                                              |
|------|---------------------------------------------------------|--------------------------------------------------------------|
| 1    | GET inventory.import.template                           | CSV file download is initiated.                              |
| 2    | Verify file format                                      | Downloaded file is a valid CSV.                              |
| 3    | Verify column headers present                           | CSV contains expected headers: sku, title, description, price, cost_price, quantity, category, brand, condition, weight, etc. |
| 4    | Verify file is empty except for headers                 | No data rows are included (template only).                   |

**Test Data:**
- None required. Template is static.

**Postconditions:**
- No data is modified. A file is downloaded to the user's browser.

**Notes:**
- The template should include all importable fields as column headers to guide the user.

---

## TC-INV-008-02: Upload CSV File for Validation

| Field          | Value                                                        |
|----------------|--------------------------------------------------------------|
| ID             | TC-INV-008-02                                                |
| Priority       | P1                                                           |
| Type           | Functional                                                   |
| Platform       | Web                                                          |
| Automated      | No                                                           |
| Test File      | --                                                           |

**Preconditions:**
- User is authenticated with inventory management permissions.
- A valid CSV file is prepared with product data.

**Test Steps:**

| Step | Action                                                  | Expected Result                                              |
|------|---------------------------------------------------------|--------------------------------------------------------------|
| 1    | POST inventory.import.upload with CSV file              | File is accepted and stored temporarily for processing.      |
| 2    | Verify response                                         | Response includes upload_id and row count.                   |
| 3    | Verify file validation                                  | CSV structure is validated (correct headers, parseable data). |
| 4    | Test with invalid file type (e.g., .txt)                | Returns validation error about unsupported file format.      |
| 5    | Test with empty CSV (headers only)                      | Returns validation error or warning about no data rows.      |

**Test Data:**
- Valid CSV with 5 product rows.
- Invalid .txt file for negative test.
- Empty CSV with headers only.

**Postconditions:**
- Uploaded file is stored temporarily for subsequent validation and processing.

**Notes:**
- Maximum file size limits should be enforced and documented.

---

## TC-INV-008-03: Validate Import Column Mapping and Error Reporting

| Field          | Value                                                        |
|----------------|--------------------------------------------------------------|
| ID             | TC-INV-008-03                                                |
| Priority       | P1                                                           |
| Type           | Functional                                                   |
| Platform       | Web                                                          |
| Automated      | No                                                           |
| Test File      | --                                                           |

**Preconditions:**
- User is authenticated with inventory management permissions.
- A CSV file has been uploaded (TC-INV-008-02 completed).

**Test Steps:**

| Step | Action                                                  | Expected Result                                              |
|------|---------------------------------------------------------|--------------------------------------------------------------|
| 1    | POST inventory.import.validate with upload_id and column mapping | Validation runs on the uploaded data.                        |
| 2    | Verify column mapping                                   | CSV columns are mapped to product fields correctly.          |
| 3    | Verify row-level validation                             | Each row is validated for required fields, data types, and constraints. |
| 4    | Verify error report for invalid rows                    | Errors are returned per row with line number, field, and error message. |
| 5    | Verify valid rows count                                 | Response indicates how many rows passed validation.          |

**Test Data:**
- CSV with 5 rows: 3 valid, 2 invalid (missing required SKU, invalid price format).
- Column mapping: {"column_0": "sku", "column_1": "title", "column_2": "price"}

**Postconditions:**
- No products are created or modified. Validation is a preview step.

**Notes:**
- Required fields for import typically include: sku, title, and price at minimum.

---

## TC-INV-008-04: Process Import Creates and Updates Products

| Field          | Value                                                        |
|----------------|--------------------------------------------------------------|
| ID             | TC-INV-008-04                                                |
| Priority       | P0                                                           |
| Type           | Functional                                                   |
| Platform       | Web                                                          |
| Automated      | No                                                           |
| Test File      | --                                                           |

**Preconditions:**
- User is authenticated with inventory management permissions.
- A CSV file has been uploaded and validated (TC-INV-008-03 completed with valid rows).

**Test Steps:**

| Step | Action                                                  | Expected Result                                              |
|------|---------------------------------------------------------|--------------------------------------------------------------|
| 1    | POST inventory.import.process with upload_id            | Import processing begins.                                    |
| 2    | Verify products created                                 | New Product records are created for each valid row.          |
| 3    | Verify product fields populated                         | Each product has correct sku, title, price, cost_price, description, etc. |
| 4    | Verify organization scoping                             | All created products belong to the authenticated user's organization. |
| 5    | Verify import summary                                   | Response shows created count, skipped count, and error count. |

**Test Data:**
- 3 valid product rows in the validated CSV.

**Postconditions:**
- 3 new Product records exist in the database for the organization.

**Notes:**
- Import processing may be dispatched as a queued job for large files. The response should provide a way to track progress.

---

## TC-INV-008-05: Import with Update Existing by SKU

| Field          | Value                                                        |
|----------------|--------------------------------------------------------------|
| ID             | TC-INV-008-05                                                |
| Priority       | P1                                                           |
| Type           | Functional                                                   |
| Platform       | Web                                                          |
| Automated      | No                                                           |
| Test File      | --                                                           |

**Preconditions:**
- User is authenticated with inventory management permissions.
- Products with SKUs "SKU-001" and "SKU-002" already exist in the organization.
- A CSV file is uploaded containing rows with matching SKUs and updated field values.

**Test Steps:**

| Step | Action                                                  | Expected Result                                              |
|------|---------------------------------------------------------|--------------------------------------------------------------|
| 1    | POST inventory.import.process with upload_id and update_existing=true | Import processes with update mode enabled.                   |
| 2    | Verify existing products updated                        | Products matching by SKU have their fields updated (e.g., price, title). |
| 3    | Verify no duplicate products created                    | Product count for matching SKUs remains the same.            |
| 4    | Verify new SKUs still create new products               | Rows with non-matching SKUs create new Product records.      |

**Test Data:**
- CSV row 1: sku="SKU-001", price=29.99 (update existing).
- CSV row 2: sku="SKU-002", title="Updated Title" (update existing).
- CSV row 3: sku="SKU-NEW", title="New Product" (create new).

**Postconditions:**
- SKU-001 and SKU-002 are updated. SKU-NEW is created.

**Notes:**
- update_existing=false (default) should skip rows with existing SKUs or report them as errors.

---

## TC-INV-008-06: Import with Skip Errors Processes Good Rows

| Field          | Value                                                        |
|----------------|--------------------------------------------------------------|
| ID             | TC-INV-008-06                                                |
| Priority       | P1                                                           |
| Type           | Functional                                                   |
| Platform       | Web                                                          |
| Automated      | No                                                           |
| Test File      | --                                                           |

**Preconditions:**
- User is authenticated with inventory management permissions.
- A CSV file is uploaded containing a mix of valid and invalid rows.

**Test Steps:**

| Step | Action                                                  | Expected Result                                              |
|------|---------------------------------------------------------|--------------------------------------------------------------|
| 1    | POST inventory.import.process with upload_id and skip_errors=true | Import processes, skipping invalid rows.                     |
| 2    | Verify valid rows processed                             | Products are created for all valid rows.                     |
| 3    | Verify invalid rows skipped                             | Invalid rows are not imported.                               |
| 4    | Verify error report                                     | Response includes list of skipped rows with their errors.    |
| 5    | Test with skip_errors=false                             | Import aborts entirely if any row has errors.                |

**Test Data:**
- CSV with 5 rows: 3 valid, 2 invalid.
- skip_errors=true: 3 products created, 2 reported as skipped.

**Postconditions:**
- 3 new Product records from valid rows. Invalid rows are not imported.

**Notes:**
- skip_errors=false is the safer default for production use, ensuring data quality.

---

## TC-INV-008-07: Export Generates File with Selected Fields

| Field          | Value                                                        |
|----------------|--------------------------------------------------------------|
| ID             | TC-INV-008-07                                                |
| Priority       | P1                                                           |
| Type           | Functional                                                   |
| Platform       | Web                                                          |
| Automated      | No                                                           |
| Test File      | --                                                           |

**Preconditions:**
- User is authenticated with inventory management permissions.
- Organization has at least 5 products.

**Test Steps:**

| Step | Action                                                  | Expected Result                                              |
|------|---------------------------------------------------------|--------------------------------------------------------------|
| 1    | POST inventory.export.generate with format=csv and selected fields | Export job is queued or file is generated.                    |
| 2    | Verify export includes only selected fields             | Generated file contains columns only for the fields requested. |
| 3    | Verify all organization products included               | All products for the organization appear in the export.      |
| 4    | Test with format=xlsx                                   | XLSX file is generated with the same data.                   |
| 5    | Verify organization scoping                             | Only the authenticated user's organization products are exported. |

**Test Data:**
- Selected fields: ["sku", "title", "price", "quantity"]
- format: "csv"

**Postconditions:**
- Export file is generated and stored for download.

**Notes:**
- Large exports may be processed as a background job with download notification.

---

## TC-INV-008-08: Download Export File

| Field          | Value                                                        |
|----------------|--------------------------------------------------------------|
| ID             | TC-INV-008-08                                                |
| Priority       | P1                                                           |
| Type           | Functional                                                   |
| Platform       | Web                                                          |
| Automated      | No                                                           |
| Test File      | --                                                           |

**Preconditions:**
- User is authenticated with inventory management permissions.
- An export file has been generated (TC-INV-008-07 completed).

**Test Steps:**

| Step | Action                                                  | Expected Result                                              |
|------|---------------------------------------------------------|--------------------------------------------------------------|
| 1    | GET inventory.export.download with export_id            | File download is initiated.                                  |
| 2    | Verify file content                                     | Downloaded file contains the expected product data.          |
| 3    | Verify file format                                      | File matches the requested format (CSV or XLSX).             |
| 4    | Attempt download with invalid export_id                 | Returns 404 Not Found.                                       |
| 5    | Attempt download of another organization's export       | Returns 403 Forbidden or 404 Not Found.                      |

**Test Data:**
- Valid export_id from a completed export.

**Postconditions:**
- File is downloaded. No data is modified.

**Notes:**
- Export files should have a reasonable retention period before automatic cleanup.
