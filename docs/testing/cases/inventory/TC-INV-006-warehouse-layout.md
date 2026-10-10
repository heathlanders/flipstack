# TC-INV-006: Warehouse Layout Management

| Field             | Value                                      |
|-------------------|--------------------------------------------|
| Document ID       | TC-INV-006                                 |
| Domain            | Inventory (Warehouse)                      |
| Related Test Plan | TP-INV-003 Warehouse Management            |
| Last Updated      | 2026-04-15                                 |
| Controller        | ZoneController                             |
| Routes            | inventory.warehouse.*                      |

---

## TC-INV-006-01: View Zones for Location

| Field          | Value                                                        |
|----------------|--------------------------------------------------------------|
| ID             | TC-INV-006-01                                                |
| Priority       | P1                                                           |
| Type           | Functional                                                   |
| Platform       | Web                                                          |
| Automated      | No                                                           |
| Test File      | --                                                           |

**Preconditions:**
- User is authenticated with inventory management permissions.
- An InventoryLocation exists with at least 2 WarehouseZone records.

**Test Steps:**

| Step | Action                                                  | Expected Result                                              |
|------|---------------------------------------------------------|--------------------------------------------------------------|
| 1    | Navigate to GET inventory.warehouse.zones.index with location_id | Page loads with list of zones for the specified location.     |
| 2    | Verify zone details displayed                           | Each zone shows name, type, and bin count.                   |
| 3    | Verify only zones for selected location shown           | Zones from other locations are not displayed.                |

**Test Data:**
- Location: "Main Warehouse"
- Zone A: "Receiving Area" (type: receiving)
- Zone B: "Storage Area" (type: storage)

**Postconditions:**
- No data is modified.

**Notes:**
- Zone types include: receiving, storage, picking, shipping, returns.

---

## TC-INV-006-02: Create Zone

| Field          | Value                                                        |
|----------------|--------------------------------------------------------------|
| ID             | TC-INV-006-02                                                |
| Priority       | P1                                                           |
| Type           | Functional                                                   |
| Platform       | Web                                                          |
| Automated      | No                                                           |
| Test File      | --                                                           |

**Preconditions:**
- User is authenticated with inventory management permissions.
- An InventoryLocation exists.

**Test Steps:**

| Step | Action                                                  | Expected Result                                              |
|------|---------------------------------------------------------|--------------------------------------------------------------|
| 1    | POST inventory.warehouse.zones.store with name and type | Zone is created for the location.                            |
| 2    | Verify response                                         | Redirect to zones index with success message.                |
| 3    | Verify database record                                  | WarehouseZone record exists with correct name, type, and location_id. |
| 4    | Test each valid zone type                               | Zone can be created with type: receiving, storage, picking, shipping, or returns. |
| 5    | Verify validation rejects invalid type                  | POST with type="invalid" returns validation error.           |

**Test Data:**
- name: "New Receiving Zone"
- type: "receiving"
- location_id: existing location ID

**Postconditions:**
- One new WarehouseZone record exists.

**Notes:**
- Zone names should be unique within a location.

---

## TC-INV-006-03: Update Zone Name and Type

| Field          | Value                                                        |
|----------------|--------------------------------------------------------------|
| ID             | TC-INV-006-03                                                |
| Priority       | P2                                                           |
| Type           | Functional                                                   |
| Platform       | Web                                                          |
| Automated      | No                                                           |
| Test File      | --                                                           |

**Preconditions:**
- User is authenticated with inventory management permissions.
- An existing WarehouseZone record exists.

**Test Steps:**

| Step | Action                                                  | Expected Result                                              |
|------|---------------------------------------------------------|--------------------------------------------------------------|
| 1    | PUT inventory.warehouse.zones.update with new name and type | Zone is updated.                                             |
| 2    | Verify response                                         | Redirect with success message.                               |
| 3    | Verify database record                                  | WarehouseZone name and type reflect the updated values.      |
| 4    | Verify bins within zone unaffected                      | Existing bins remain associated with the zone.               |

**Test Data:**
- Updated name: "Renamed Storage Zone"
- Updated type: "picking"

**Postconditions:**
- The WarehouseZone record is updated.

**Notes:**
- Changing a zone's type does not affect the bins or products stored within it.

---

## TC-INV-006-04: Delete Zone with No Bins

| Field          | Value                                                        |
|----------------|--------------------------------------------------------------|
| ID             | TC-INV-006-04                                                |
| Priority       | P1                                                           |
| Type           | Functional                                                   |
| Platform       | Web                                                          |
| Automated      | No                                                           |
| Test File      | --                                                           |

**Preconditions:**
- User is authenticated with inventory management permissions.
- An existing WarehouseZone exists with zero associated WarehouseBin records.

**Test Steps:**

| Step | Action                                                  | Expected Result                                              |
|------|---------------------------------------------------------|--------------------------------------------------------------|
| 1    | DELETE inventory.warehouse.zones.destroy with zone ID   | Zone is deleted.                                             |
| 2    | Verify response                                         | Redirect to zones index with success message.                |
| 3    | Verify database                                         | WarehouseZone record no longer exists.                       |
| 4    | Attempt to delete a zone that has bins                  | Returns validation error preventing deletion.                |

**Test Data:**
- Zone with 0 bins (deletable).
- Zone with 5 bins (should fail deletion).

**Postconditions:**
- The empty zone is removed from the database.
- The zone with bins remains intact.

**Notes:**
- Zones with bins must have all bins removed or reassigned before the zone can be deleted.

---

## TC-INV-006-05: Generate Bins for Zone

| Field          | Value                                                        |
|----------------|--------------------------------------------------------------|
| ID             | TC-INV-006-05                                                |
| Priority       | P1                                                           |
| Type           | Functional                                                   |
| Platform       | Web                                                          |
| Automated      | No                                                           |
| Test File      | --                                                           |

**Preconditions:**
- User is authenticated with inventory management permissions.
- An existing WarehouseZone exists with no bins.

**Test Steps:**

| Step | Action                                                  | Expected Result                                              |
|------|---------------------------------------------------------|--------------------------------------------------------------|
| 1    | POST inventory.warehouse.zones.generate-bins with aisles=2, racks=3, shelves=4 | Bins are generated for the zone.                             |
| 2    | Verify total bin count                                  | 2 * 3 * 4 = 24 WarehouseBin records created.                |
| 3    | Verify bin codes auto-generated                         | Each bin has a unique code (e.g., "A01-R01-S01").            |
| 4    | Verify barcodes auto-generated                          | Each bin has an auto-generated barcode value.                |
| 5    | Verify bin fields populated                             | Each bin has aisle, rack, shelf values set correctly.        |

**Test Data:**
- aisles: 2
- racks: 3
- shelves: 4
- Expected: 24 bins with codes like A01-R01-S01 through A02-R03-S04.

**Postconditions:**
- 24 new WarehouseBin records exist, all associated with the zone.

**Notes:**
- Bin generation is a bulk operation. The barcode format should be scannable by the mobile app.

---

## TC-INV-006-06: View Bins with Zone Filter and Search

| Field          | Value                                                        |
|----------------|--------------------------------------------------------------|
| ID             | TC-INV-006-06                                                |
| Priority       | P1                                                           |
| Type           | Functional                                                   |
| Platform       | Web                                                          |
| Automated      | No                                                           |
| Test File      | --                                                           |

**Preconditions:**
- User is authenticated with inventory management permissions.
- Multiple WarehouseBin records exist across at least 2 zones.

**Test Steps:**

| Step | Action                                                  | Expected Result                                              |
|------|---------------------------------------------------------|--------------------------------------------------------------|
| 1    | Navigate to GET inventory.warehouse.bins.index          | Page loads with paginated list of all bins.                  |
| 2    | Apply zone filter                                       | Only bins belonging to the selected zone are displayed.      |
| 3    | Enter search term matching a bin code                   | Bin list filters to matching results.                        |
| 4    | Clear filters                                           | Full bin list is restored.                                   |

**Test Data:**
- Zone A: 10 bins.
- Zone B: 15 bins.
- Search term: "A01" (partial code match).

**Postconditions:**
- No data is modified.

**Notes:**
- Search should match against bin code and barcode fields.

---

## TC-INV-006-07: Create Individual Bin

| Field          | Value                                                        |
|----------------|--------------------------------------------------------------|
| ID             | TC-INV-006-07                                                |
| Priority       | P2                                                           |
| Type           | Functional                                                   |
| Platform       | Web                                                          |
| Automated      | No                                                           |
| Test File      | --                                                           |

**Preconditions:**
- User is authenticated with inventory management permissions.
- An existing WarehouseZone exists.

**Test Steps:**

| Step | Action                                                  | Expected Result                                              |
|------|---------------------------------------------------------|--------------------------------------------------------------|
| 1    | POST inventory.warehouse.bins.store with code, aisle, rack, shelf, max_capacity | Bin is created in the specified zone.                        |
| 2    | Verify response                                         | Redirect with success message.                               |
| 3    | Verify database record                                  | WarehouseBin record exists with correct code, aisle, rack, shelf, max_capacity, and zone_id. |
| 4    | Verify validation rejects duplicate code within zone    | POST with an existing bin code in the same zone returns validation error. |

**Test Data:**
- code: "CUSTOM-BIN-01"
- aisle: "A"
- rack: "1"
- shelf: "3"
- max_capacity: 50
- zone_id: existing zone ID

**Postconditions:**
- One new WarehouseBin record exists.

**Notes:**
- Individual bin creation is used for custom bin layouts that do not follow the standard grid pattern.

---

## TC-INV-006-08: Delete Bin

| Field          | Value                                                        |
|----------------|--------------------------------------------------------------|
| ID             | TC-INV-006-08                                                |
| Priority       | P2                                                           |
| Type           | Functional                                                   |
| Platform       | Web                                                          |
| Automated      | No                                                           |
| Test File      | --                                                           |

**Preconditions:**
- User is authenticated with inventory management permissions.
- An existing WarehouseBin record exists with no products assigned to it.

**Test Steps:**

| Step | Action                                                  | Expected Result                                              |
|------|---------------------------------------------------------|--------------------------------------------------------------|
| 1    | DELETE inventory.warehouse.bins.destroy with bin ID     | Bin is deleted.                                              |
| 2    | Verify response                                         | Redirect with success message.                               |
| 3    | Verify database                                         | WarehouseBin record no longer exists.                        |
| 4    | Attempt to delete a bin with products assigned          | Returns error preventing deletion or moves products first.   |

**Test Data:**
- Empty bin (deletable).
- Bin with assigned products (should fail or require confirmation).

**Postconditions:**
- The empty bin is removed from the database.

**Notes:**
- Consider whether bins with historical product assignments (but currently empty) should be deletable.
