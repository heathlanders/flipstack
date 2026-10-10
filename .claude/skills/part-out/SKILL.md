---
name: part-out
description: Product disassembly - part-out templates, work orders, cost allocation, component product creation, and inventory tracking
user-invocable: false
---

# Product Part-Out (Disassembly)

## Key Files

| Area | File | Purpose |
|------|------|---------|
| **Models** | `app/Models/Inventory/PartOutTemplate.php` | Reusable disassembly definition with cost allocation method |
| | `app/Models/Inventory/PartOutTemplateItem.php` | Component slot with part number, manufacturer, market price |
| | `app/Models/Inventory/PartOutWorkOrder.php` | Specific disassembly execution with status tracking |
| | `app/Models/Inventory/PartOutWorkOrderItem.php` | Actual component extracted with allocated cost |
| **Services** | `app/Services/Inventory/PartOutService.php` | Template CRUD, work order CRUD, execution, cost allocation |
| | `app/Services/Inventory/PartOutPricingService.php` | eBay market pricing for components |
| **Controllers** | `app/Http/Controllers/Inventory/PartOutDashboardController.php` | Hub landing page with KPIs and stats |
| | `app/Http/Controllers/Inventory/PartOutTemplateController.php` | Template management |
| | `app/Http/Controllers/Inventory/PartOutWorkOrderController.php` | Work order management + execution |
| | `app/Http/Controllers/Api/MobilePartOutController.php` | Mobile API (9 endpoints for extraction workflow) |
| **Routes** | `routes/inventory.php` (part-out prefix) | Hub, template, and work order web routes |
| | `routes/api.php` (part-out prefix) | Mobile API routes for extraction tracking |
| **Pages** | `resources/js/Pages/Inventory/PartOut/Index.vue` | Hub landing page with KPI cards, recent WOs, templates |
| | `resources/js/Pages/Inventory/PartOut/Templates/` | Template CRUD pages |
| | `resources/js/Pages/Inventory/PartOut/WorkOrders/` | Work order CRUD pages |

## Work Order Statuses

| Status | Description | Can Execute | Can Cancel |
|--------|-------------|-------------|------------|
| `draft` | Created, not yet executed | Yes | Yes |
| `in_progress` | Execution started (mobile: extraction in progress) | Yes | Yes |
| `completed` | Execution finished, products created | No | No |
| `cancelled` | Cancelled before execution | No | No |

## Cost Allocation Methods

| Method | Constant | How It Works |
|--------|----------|-------------|
| Market Value | `market_value` | Distributes cost proportionally by `suggested_selling_price * quantity` |
| Percentage | `percentage` | User sets explicit `cost_allocation_pct` per item (must sum to 100%) |
| Even | `even` | Divides cost evenly weighted by quantity |

## Template vs Work Order

- **Template**: Reusable definition of what components come from a product type. Has `source_product_id` as optional reference. Contains `PartOutTemplateItem` rows defining expected components.
- **Work Order**: A specific execution instance. References a template (optional), a source product (required), and contains actual items to extract. On execution, auto-creates new Product records for each component.

## Product Lineage

Products created by part-out have these fields set:
- `parted_from_product_id` -- UUID of the parent product
- `part_out_work_order_id` -- UUID of the work order that created it

Query components of a product: `$product->partedOutComponents`
Query parent of a component: `$product->partedFromProduct`

## Parent Continues (Barebone Mode)

When `parent_continues = true` on a work order:
- Parent product is NOT consumed -- it continues to exist (e.g., as a barebone server)
- `remaining_parent_cost` is set -- portion of cost retained by parent
- `parent_new_name` optionally updates the parent's name
- Parent's `cost_price` is adjusted to `remaining_parent_cost / source_quantity`

When `parent_continues = false`:
- Parent's `available_quantity` is decremented
- `is_parted_out = true` is set when quantity reaches 0

## AI Component Identification

Component data comes from the intake pipeline, NOT from the part-out feature:
- `GeminiProductResearch` Stage 2 prompt identifies components for `complete_unit` products
- Stored in `product.component_breakdown` JSON field
- Part-out UI reads this to auto-populate templates and work orders
- Market pricing is refreshed at part-out time via `PartOutPricingService`

## Inventory Movement Types

| Type | Constant | Direction | When |
|------|----------|-----------|------|
| `partout_consume` | `TYPE_PARTOUT_CONSUME` | Negative | Source product inventory decremented |
| `partout_produce` | `TYPE_PARTOUT_PRODUCE` | Positive | Component product inventory incremented |

Both use `reference_type = 'part_out_work_order'` and `reference_id = work_order.id`.

## Mobile Extraction Tracking

Work order items have `extraction_status` for mobile picker/packer workflow:
- `pending` -- not yet processed
- `extracted` -- physically extracted, optionally barcode-verified
- `skipped` -- skipped with reason (component not present, damaged, etc.)

Additional fields: `extracted_at`, `extracted_by`, `extraction_notes`, `barcode_verified`, `scanned_barcode`

Work orders have `assigned_to` for picker assignment.

Skipped items are excluded from `executeWorkOrder()` -- no products created, cost redistributed across extracted items.

Mobile API: 9 endpoints at `/api/part-out/` (index, show, start, extract, skip, notes, scan-verify, complete, cancel)

Flutter: `mobile/lib/features/part_out/` with list page, active page (barcode scanner), providers, models, service

## Common Operations

### Create template from product's component breakdown
```php
$template = $partOutService->createTemplateFromProduct($product, $organizationId, $userId);
```

### Execute a work order (creates products + inventory movements)
```php
$workOrder = $partOutService->executeWorkOrder($workOrder, $userId);
```

### Refresh market pricing for components
```php
$pricing = $pricingService->priceComponents($product->component_breakdown, $organizationId);
```

## Audit Logging

| Action | Category | Severity | Metadata keys |
|--------|----------|----------|---------------|
| `part_out.template.created` | inventory | info | component_count |
| `part_out.template.updated` | inventory | info | changes |
| `part_out.work_order.created` | inventory | info | template_id, source_product_id |
| `part_out.work_order.started` | inventory | info | assigned_user_id |
| `part_out.work_order.completed` | inventory | info | components_produced, source_product_disassembled |
| `part_out.component.produced` | inventory | info | work_order_id, new_product_id, allocated_cost |

Trait: models in this feature use `LogsAudit` (`app/Traits/LogsAudit.php`) for auto CRUD diff. Domain lifecycle events emitted from `app/Services/Inventory/PartOutService`; `PartOutTemplate`, `PartOutTemplateItem`, `PartOutWorkOrder`, `PartOutWorkOrderItem` models (`LogsAudit`) via `UserActivityLog::log(action, desc, $user, $metadata, $severity, $category)`.

`part_out.work_order.completed` rolls into `UserProductivityDaily` if user opts to count part-outs toward listing throughput (configurable in aggregator).

Spec: `docs/AUDIT_SYSTEM.md`. Enforced rule: `.claude/rules/audit-logging.md`. Tests: `use Tests\Support\AssertsAudits;` then `$this->assertAudited('action', $user, [...])`.
