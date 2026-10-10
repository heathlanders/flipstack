# TC-API-008: Printing Endpoints

| Field            | Value                                              |
|------------------|----------------------------------------------------|
| Document ID      | TC-API-008                                         |
| Domain           | API Endpoints                                      |
| Related Test Plan| TP-API (api-endpoints-test-plan.md)                |
| Last Updated     | 2026-04-15                                         |

---

## TC-API-008-01: POST /api/printing/label/single

| Field         | Value                     |
|---------------|---------------------------|
| Priority      | P0                        |
| Type          | Feature                   |
| Platform      | API                       |
| Automated     | Yes                       |

**Preconditions:** User authenticated. Product exists with printable label data.

| Step | Action                              | Expected Result                                      |
|------|-------------------------------------|------------------------------------------------------|
| 1    | POST /api/printing/label/single with product_id and template_id | 200 response          |
| 2    | Verify label data returned          | Response contains label content (PDF or ZPL)         |
| 3    | Verify product data on label        | SKU, name, barcode embedded in label                 |
| 4    | POST with invalid product_id        | 404 response                                        |
| 5    | POST with invalid template_id       | 422 response with validation error                   |
| 6    | Verify org scoping                  | Cannot print labels for another org's products       |

---

## TC-API-008-02: POST /api/printing/label/batch

| Field         | Value                     |
|---------------|---------------------------|
| Priority      | P0                        |
| Type          | Feature                   |
| Platform      | API                       |
| Automated     | Yes                       |

**Preconditions:** Multiple products exist. Label template configured.

| Step | Action                              | Expected Result                                      |
|------|-------------------------------------|------------------------------------------------------|
| 1    | POST /api/printing/label/batch with product_ids array | 200 response                    |
| 2    | Verify batch label generated        | Combined label document with all products            |
| 3    | Verify product count matches        | Number of labels equals number of product_ids        |
| 4    | POST with empty array               | 422 response with validation error                   |
| 5    | POST with mix of valid and invalid IDs | 422 response listing invalid IDs                  |

---

## TC-API-008-03: GET /api/printing/printers List

| Field         | Value                     |
|---------------|---------------------------|
| Priority      | P1                        |
| Type          | Feature                   |
| Platform      | API                       |
| Automated     | Yes                       |

**Preconditions:** Printers configured for the organization.

| Step | Action                              | Expected Result                                      |
|------|-------------------------------------|------------------------------------------------------|
| 1    | GET /api/printing/printers          | 200 response with printer list                       |
| 2    | Verify printer fields               | id, name, type, status, connection_info per printer  |
| 3    | Verify org scoping                  | Only printers from current org returned              |
| 4    | No printers configured              | 200 response with empty array                        |
| 5    | Verify status accuracy              | Online/offline status reflects actual state          |

---

## TC-API-008-04: GET /api/printing/templates List

| Field         | Value                     |
|---------------|---------------------------|
| Priority      | P1                        |
| Type          | Feature                   |
| Platform      | API                       |
| Automated     | Yes                       |

**Preconditions:** Label templates configured.

| Step | Action                              | Expected Result                                      |
|------|-------------------------------------|------------------------------------------------------|
| 1    | GET /api/printing/templates         | 200 response with template list                      |
| 2    | Verify template fields              | id, name, label_size, format per template            |
| 3    | Verify org scoping                  | Only org templates and system defaults returned      |
| 4    | Verify default template flagged     | Default template identified in response              |
| 5    | No custom templates                 | System default templates still returned              |

---

## TC-API-008-05: GET /api/printing/jobs List

| Field         | Value                     |
|---------------|---------------------------|
| Priority      | P2                        |
| Type          | Feature                   |
| Platform      | API                       |
| Automated     | Yes                       |

**Preconditions:** Print jobs have been submitted previously.

| Step | Action                              | Expected Result                                      |
|------|-------------------------------------|------------------------------------------------------|
| 1    | GET /api/printing/jobs              | 200 response with print job history                  |
| 2    | Verify job fields                   | id, template, product_count, status, created_at      |
| 3    | Verify pagination                   | Response supports paginated navigation               |
| 4    | Verify status values                | Jobs show pending, completed, or failed status       |
| 5    | Verify org scoping                  | Only current org print jobs returned                 |

---

## TC-API-008-06: Printing Scoped to Org

| Field         | Value                     |
|---------------|---------------------------|
| Priority      | P0                        |
| Type          | Feature                   |
| Platform      | API                       |
| Automated     | Yes                       |

**Preconditions:** Two organizations exist with separate printing configurations.

| Step | Action                              | Expected Result                                      |
|------|-------------------------------------|------------------------------------------------------|
| 1    | Authenticate as Org A user          | Token scoped to Org A                                |
| 2    | GET /api/printing/printers          | Only Org A printers returned                         |
| 3    | POST /api/printing/label/single for Org B product | 404 response                       |
| 4    | Authenticate as Org B user          | Token scoped to Org B                                |
| 5    | GET /api/printing/printers          | Only Org B printers returned                         |
| 6    | Verify no cross-org data            | No Org A data accessible via Org B token             |
