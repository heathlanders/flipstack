# Product Part-Out (Disassembly) System

## Overview

The Part-Out system allows organizations to disassemble complete units (servers, computers, equipment) into individual components for higher-margin resale. It is architecturally the inverse of the Bundle system -- bundles combine products, part-outs decompose them.

A $500 Supermicro server can be parted out into RAM sticks ($300), HDDs ($400), CPUs ($200), etc., yielding significantly more total revenue than selling the whole unit.

---

## Architecture

### Key Files

| File | Purpose |
|------|---------|
| `app/Models/Inventory/PartOutTemplate.php` | Reusable disassembly definition |
| `app/Models/Inventory/PartOutTemplateItem.php` | Component slot within a template |
| `app/Models/Inventory/PartOutWorkOrder.php` | Specific disassembly execution |
| `app/Models/Inventory/PartOutWorkOrderItem.php` | Actual component extracted |
| `app/Services/Inventory/PartOutService.php` | Core business logic |
| `app/Services/Inventory/PartOutPricingService.php` | eBay market pricing |
| `app/Http/Controllers/Inventory/PartOutTemplateController.php` | Template CRUD |
| `app/Http/Controllers/Inventory/PartOutWorkOrderController.php` | Work order management |

---

## Data Model

### `part_out_templates`

Reusable definitions of what components come from a product type.

| Column | Type | Description |
|--------|------|-------------|
| `id` | uuid | Primary key |
| `organization_id` | bigint | FK to organizations |
| `name` | string | Template name (e.g., "Supermicro 1U Server Teardown") |
| `description` | text | Optional description |
| `source_product_id` | uuid | Optional reference product |
| `cost_allocation_method` | string | market_value, percentage, or even |
| `is_active` | boolean | Whether template is available for use |
| `times_used` | integer | Counter of work orders using this template |
| `created_by` | bigint | FK to users |

### `part_out_template_items`

Component slots within a template.

| Column | Type | Description |
|--------|------|-------------|
| `id` | uuid | Primary key |
| `template_id` | uuid | FK to part_out_templates |
| `component_product_id` | uuid | Optional pre-linked product |
| `name` | string | Component name |
| `expected_quantity` | integer | How many come from one unit |
| `cost_allocation_pct` | decimal | For percentage method |
| `part_number` | string | MPN from AI analysis |
| `manufacturer` | string | Component manufacturer |
| `category` | string | CPU, RAM, HDD, PSU, etc. |
| `suggested_selling_price` | decimal | From eBay market data |

### `part_out_work_orders`

A specific disassembly execution.

| Column | Type | Description |
|--------|------|-------------|
| `id` | uuid | Primary key |
| `organization_id` | bigint | FK to organizations |
| `template_id` | uuid | Optional template reference |
| `work_order_number` | string | Auto-generated (WO-YYYYMMDD-XXXX) |
| `source_product_id` | uuid | Parent product being disassembled |
| `source_quantity` | integer | Number of units (batch support) |
| `source_unit_cost` | decimal | Snapshot of cost at execution |
| `source_total_cost` | decimal | source_quantity * source_unit_cost |
| `remaining_parent_cost` | decimal | Cost retained by parent (barebone mode) |
| `cost_allocation_method` | string | How to distribute cost |
| `parent_continues` | boolean | True if parent stays active (barebone) |
| `parent_new_name` | string | Updated name for barebone parent |
| `status` | string | draft, in_progress, completed, cancelled |

### `part_out_work_order_items`

Actual components extracted in a work order.

| Column | Type | Description |
|--------|------|-------------|
| `id` | uuid | Primary key |
| `work_order_id` | uuid | FK to part_out_work_orders |
| `component_product_id` | uuid | Product created on execution |
| `name` | string | Component name |
| `part_number` | string | Component MPN |
| `quantity` | integer | Actual quantity extracted |
| `allocated_cost` | decimal | Total COGS allocated |
| `allocated_cost_per_unit` | decimal | Per-unit cost |
| `cost_allocation_pct` | decimal | Percentage used |

### Products table additions

| Column | Type | Description |
|--------|------|-------------|
| `is_parted_out` | boolean | True when product fully disassembled |
| `component_breakdown` | jsonb | AI-identified components from intake pipeline |
| `parted_from_product_id` | uuid | Links component back to parent product |
| `part_out_work_order_id` | uuid | Links to the WO that created this product |

---

## AI Component Identification

Component identification happens during the product intake pipeline, not at part-out time.

### How it works

1. When a `complete_unit` product is processed through `ProductResearchOrchestrator`, the Stage 2 Gemini prompt includes instructions to identify all internal sellable components.

2. Gemini returns a `component_breakdown` array in the enriched data:
```json
[
  {"name": "Intel Xeon Gold 6248", "part_number": "CD8069504194301", "manufacturer": "Intel", "quantity": 2, "category": "CPU", "specs": {"cores": 20, "speed": "2.5GHz"}},
  {"name": "Samsung 32GB DDR4 ECC", "part_number": "M393A4K40CB2-CVF", "manufacturer": "Samsung", "quantity": 8, "category": "RAM", "specs": {"capacity": "32GB"}}
]
```

3. When the product is created from the intake queue, `component_breakdown` is stored on the `products` table.

4. At part-out time, the UI reads this pre-existing data to auto-populate templates and work orders. Only market pricing is fetched fresh from eBay.

---

## Cost Allocation Methods

### Market Value (default)
Distributes parent cost proportionally by component market value.
```
item_allocation = (item_selling_price * item_qty) / sum(all_selling_price * qty) * distributable_cost
```

### Percentage
User sets explicit percentages per component. Must sum to 100%.

### Even Distribution
Divides cost evenly weighted by quantity.
```
item_allocation = distributable_cost * (item_qty / total_qty)
```

When `parent_continues = true`, `remaining_parent_cost` is subtracted before distribution:
```
distributable_cost = source_total_cost - remaining_parent_cost
```

---

## Work Order Execution Flow

When `executeWorkOrder()` is called:

1. **Transaction + Lock**: Wrapped in DB::transaction with lockForUpdate on source product
2. **Cost Allocation**: Calculate and set allocated_cost on each WO item
3. **Create Component Products**: For each WO item, create a new Product with:
   - `cost_price = allocated_cost_per_unit`
   - `parted_from_product_id = source_product_id`
   - `part_out_work_order_id = work_order_id`
   - `available_quantity = item_quantity * source_quantity`
4. **Inventory Movements**: Create `partout_consume` (negative) on source, `partout_produce` (positive) on each component
5. **Update Parent**: If `parent_continues`, adjust parent cost/name. Otherwise decrement qty and flag `is_parted_out`
6. **Finalize**: Set status=completed, increment template times_used

---

## Product Lineage

After part-out execution, the system maintains permanent parent-child links:

- **Component product** has `parted_from_product_id` pointing to the parent
- **Parent product** can query `partedOutComponents` to list all extracted components
- **Product Show page** displays lineage information for both parent and component products
- The work order provides the full audit trail linking parent to components

---

## Barebone Parent Scenario

When a complete unit has valuable components removed but the remaining unit (chassis, motherboard) is still sellable:

1. Set `parent_continues = true` on the work order
2. Set `remaining_parent_cost` to the cost retained by the parent
3. Optionally set `parent_new_name` (e.g., "Supermicro SYS-1029P-WTR Barebone")
4. On execution:
   - Parent product name is updated (if parent_new_name set)
   - Parent cost_price adjusted to remaining_parent_cost / source_quantity
   - Parent remains listed and sellable
   - Component products are created with allocated costs

---

## Hub Landing Page

The Part-Out Hub at `/inventory/part-out` serves as the central dashboard:
- 6 KPI cards: Templates, Active, Work Orders, In Progress, Components Created, ROI%
- Recent work orders with status badges and extraction progress
- Top templates by usage
- Recently created component products with parent lineage
- Quick action links to create templates and work orders
- Accessible via sidebar under Catalog > Part-Out

Controller: `PartOutDashboardController@index`

---

## Mobile Extraction Workflow

Picker/packers execute work orders on mobile devices via the Flutter app.

### Extraction Tracking

Work order items have `extraction_status` (pending/extracted/skipped):
- `extracted_at`, `extracted_by` -- who and when
- `extraction_notes` -- picker notes
- `barcode_verified`, `scanned_barcode` -- barcode scan verification

Work orders have `assigned_to` for picker assignment (set on start).

Skipped items are excluded from `executeWorkOrder()` -- no products created, cost redistributed.

### Mobile API Endpoints (`/api/part-out/`)

| Method | Route | Action |
|--------|-------|--------|
| GET | `/` | List work orders with stats |
| GET | `/{workOrder}` | Work order detail with items |
| POST | `/{workOrder}/start` | Start extraction (assign to user) |
| POST | `/items/{item}/extract` | Mark component extracted |
| POST | `/items/{item}/skip` | Skip with reason |
| POST | `/items/{item}/notes` | Update extraction notes |
| POST | `/items/{item}/scan-verify` | Verify barcode against part number |
| POST | `/{workOrder}/complete` | Execute work order (create products) |
| POST | `/{workOrder}/cancel` | Cancel and reset extraction |

### Flutter Module

- `mobile/lib/features/part_out/` -- pages, providers
- `mobile/lib/data/models/part_out_models.dart` -- 8 model classes
- `mobile/lib/data/services/part_out_service.dart` -- API service
- Routes: `/part-out` (list), `/part-out/:id` (active)

---

## Web API Endpoints

All routes are under `/inventory/part-out/` prefix with `inventory.part-out.` name prefix.

### Hub
| Method | Route | Action |
|--------|-------|--------|
| GET | `/` | Hub landing page with KPIs |

### Templates
| Method | Route | Action |
|--------|-------|--------|
| GET | `/templates` | List templates |
| GET | `/templates/create` | Create form |
| POST | `/templates` | Store template |
| POST | `/templates/from-product/{product}` | Auto-create from component_breakdown |
| GET | `/templates/{template}` | Show template |
| GET | `/templates/{template}/edit` | Edit form |
| PUT | `/templates/{template}` | Update template |
| DELETE | `/templates/{template}` | Delete template |
| POST | `/templates/{template}/duplicate` | Duplicate template |

### Work Orders
| Method | Route | Action |
|--------|-------|--------|
| GET | `/work-orders` | List work orders |
| GET | `/work-orders/create` | Create form |
| POST | `/work-orders` | Store work order |
| POST | `/work-orders/preview-cost` | Preview cost allocation (JSON) |
| POST | `/work-orders/refresh-pricing` | Refresh eBay pricing (JSON) |
| GET | `/work-orders/{workOrder}` | Show work order |
| POST | `/work-orders/{workOrder}/execute` | Execute work order |
| POST | `/work-orders/{workOrder}/cancel` | Cancel work order |
