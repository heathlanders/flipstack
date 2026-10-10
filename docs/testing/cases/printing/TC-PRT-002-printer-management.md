# Printer Management Test Cases

## Document ID: TC-PRT-002

**Domain:** Printing
**Related Test Plan:** `docs/testing/plans/printing-test-plan.md`
**Last Updated:** 2026-04-15

---

### TC-PRT-002-01: List printers

| Field | Value |
|-------|-------|
| **ID** | TC-PRT-002-01 |
| **Priority** | P0 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |

**Preconditions:**
1. Authenticated user with an organization
2. Multiple OrganizationPrinter records exist for the organization

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | GET `printing.printers.index` | 200 response, renders Printing/Printers/Index with printers list |
| 2 | Verify printer data | Each printer includes name, connection_string, driver, status, is_active, is_default |
| 3 | Verify stats | Stats object: total, active, online, offline, network, usb counts |
| 4 | Filter by search term | Printers filtered by name or connection_string (ILIKE match) |
| 5 | Filter by status=online | Only online printers returned |
| 6 | Filter by driver=zebra_zpl | Only Zebra printers returned |
| 7 | Sort by name ascending (default) | Printers sorted alphabetically |
| 8 | Sort by last_used_at descending | Most recently used printer first |

**Notes:**
- Organization scoping: printers filtered by organization_id derived from authenticated user
- Allowed sort fields: name, status, driver, last_used_at, total_jobs, created_at
- Driver options: zebra_zpl, dymo_label, brother_ptouch, generic_escp, pdf

---

### TC-PRT-002-02: Add printer with connection details

| Field | Value |
|-------|-------|
| **ID** | TC-PRT-002-02 |
| **Priority** | P0 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |

**Preconditions:**
1. Authenticated user with an organization

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | GET `printing.printers.create` | 200 response, form with templates, driver_options, connection_types |
| 2 | POST `printing.printers.store` with name, printer_type=network, connection_string=192.168.1.50:9100, driver=zebra_zpl | Redirect to printers.index with "Printer created successfully" flash |
| 3 | Verify OrganizationPrinter record | Created with correct organization_id, name, printer_type, connection_string, driver |
| 4 | POST with is_default=true | Existing default printers have is_default unset; new printer set as default |
| 5 | POST with optional default_template_id | Template association stored on printer |
| 6 | POST without required name | Validation error: name is required |
| 7 | POST with invalid driver value | Validation error: driver must be one of zebra_zpl, dymo_label, brother_ptouch, generic_escp, pdf |
| 8 | POST with invalid printer_type | Validation error: printer_type must be network, usb, or cups |

**Notes:**
- Connection types: network (IP address), usb (device path), cups (CUPS printer name)

---

### TC-PRT-002-03: Update printer settings

| Field | Value |
|-------|-------|
| **ID** | TC-PRT-002-03 |
| **Priority** | P1 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |

**Preconditions:**
1. Authenticated user
2. An existing OrganizationPrinter for the user's organization

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | GET `printing.printers.edit` for the printer | 200 response, renders edit form with printer data, templates, driver_options |
| 2 | PUT `printing.printers.update` with changed name and connection_string | Redirect to printers.show with "Printer updated successfully" flash |
| 3 | Verify record | Printer name and connection_string updated in database |
| 4 | PUT with is_default=true (was not default) | Other printers in org have is_default unset, this printer set to default |
| 5 | PUT with print_settings JSON | Custom print settings stored on printer record |
| 6 | PUT with invalid data | Validation errors, no update applied |

---

### TC-PRT-002-04: Delete printer

| Field | Value |
|-------|-------|
| **ID** | TC-PRT-002-04 |
| **Priority** | P1 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |

**Preconditions:**
1. Authenticated user
2. An existing OrganizationPrinter with no active (pending/processing) print jobs

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | DELETE `printing.printers.destroy` for printer with no active jobs | Redirect to printers.index with "Printer deleted successfully" flash |
| 2 | Verify deletion | OrganizationPrinter record removed from database |
| 3 | DELETE for printer with active print jobs | Error returned: "Cannot delete printer with N active job(s)." |
| 4 | Attempt to delete a printer from another organization | 404 (org-scoped findOrFail) |

**Notes:**
- Active jobs check: PrintJobs with status in (pending, processing) prevent deletion
- Deletion is logged: printer_id and organization_id recorded

---

### TC-PRT-002-05: Set default printer per location

| Field | Value |
|-------|-------|
| **ID** | TC-PRT-002-05 |
| **Priority** | P2 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |

**Preconditions:**
1. Authenticated user
2. Multiple OrganizationPrinters exist for the organization
3. Printer A is currently the default (is_default=true)

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | PUT update Printer B with is_default=true | Printer B becomes default |
| 2 | Verify Printer A | is_default changed to false (only one default per org) |
| 3 | Verify label printing | Printing without explicit printer_id now uses Printer B |
| 4 | Create new printer with is_default=true | Previous default unset, new printer is default |
| 5 | No default printer set | Label printing falls back to organization's getDefaultPrinter logic |

**Notes:**
- Default printer is enforced at the organization level: setting one as default unsets all others in the same org

---

### TC-PRT-002-06: Printer status check

| Field | Value |
|-------|-------|
| **ID** | TC-PRT-002-06 |
| **Priority** | P2 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |

**Preconditions:**
1. Authenticated user
2. An existing OrganizationPrinter with known connection details

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | GET `printing.printers.show` for a printer | 200 response with printer details including status (online/offline) and success_rate |
| 2 | Verify recent jobs | Up to 20 most recent PrintJobs displayed with template, product, creator, status |
| 3 | Verify stats display | Stats section shows total, active, online, offline counts for the organization |
| 4 | Printer with status=online | Displayed as online in the UI |
| 5 | Printer with status=offline | Displayed as offline; print jobs may still be queued but will fail until printer is online |

**Notes:**
- Printer discovery is available at `printing.printers.discovery` for detecting network printers
- Discovery settings include timeout, IP ranges, and port scanning (9100, 515, 631)
