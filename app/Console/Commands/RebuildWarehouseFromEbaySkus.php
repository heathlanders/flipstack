<?php

namespace App\Console\Commands;

use App\Models\Catalog\Product;
use App\Models\Inventory\InventoryLevel;
use App\Models\Inventory\Supplier;
use App\Models\Organization\Organization;
use App\Services\EbaySkuLocationParser;
use App\Services\Inventory\WarehouseAutoBuilder;
use Illuminate\Console\Command;

class RebuildWarehouseFromEbaySkus extends Command
{
    protected $signature = 'ebay:rebuild-warehouse
        {--org= : Organization id to process (required unless --all is set)}
        {--all : Process every organization (use with care)}
        {--dry-run : Report what would change without writing}
        {--limit=0 : Limit how many products to process (0 = all)}
        {--chunk=500 : Chunk size when iterating products}
        {--reparse-sku : Re-parse the EbayListing raw SKU (when present) instead of trusting attributes.warehouse_*}';

    protected $description = 'Rebuild warehouse zones/bins + InventoryLevel.bin_id for products whose SKU was already parsed into attributes.warehouse_* at import time.';

    public function handle(WarehouseAutoBuilder $builder, EbaySkuLocationParser $parser): int
    {
        $orgFilter = $this->option('org');
        $all = (bool) $this->option('all');
        $dryRun = (bool) $this->option('dry-run');
        $limit = (int) $this->option('limit');
        $chunk = max(50, (int) $this->option('chunk'));
        $reparseSku = (bool) $this->option('reparse-sku');

        if (! $orgFilter && ! $all) {
            $this->error('Must specify --org={id} or --all.');

            return self::FAILURE;
        }

        $orgIds = $all
            ? Organization::pluck('id')->all()
            : [$orgFilter];

        $stats = [
            'products_seen' => 0,
            'with_warehouse_attrs' => 0,
            'reparsed_from_sku' => 0,
            'parsed_with_positional' => 0,
            'bins_assigned' => 0,
            'bins_changed' => 0,
            'attributes_updated' => 0,
            'consignors_tagged' => 0,
        ];

        foreach ($orgIds as $orgId) {
            $this->line("Processing org {$orgId}...");

            $warehouse = $builder->resolveMainWarehouse((string) $orgId);

            $baseQuery = fn () => Product::query()
                ->where('organization_id', $orgId)
                ->when($reparseSku, fn ($q) => $q->with(['ebayListings' => fn ($r) => $r->whereNotNull('ebay_sku')->select('id', 'product_id', 'ebay_sku')]))
                ->orderBy('id');

            $total = $limit > 0 ? min($limit, $baseQuery()->count()) : $baseQuery()->count();
            $this->line("  total to process: {$total}");

            // Manual offset pagination — avoids Eloquent chunk() conflicts with
            // explicit limit and avoids the symfony progress bar which hangs
            // under `docker exec` non-TTY sessions.
            $offset = 0;
            $logEvery = 250;
            while ($offset < $total) {
                $size = min($chunk, $total - $offset);
                $products = $baseQuery()->offset($offset)->limit($size)->get();

                foreach ($products as $product) {
                    $stats['products_seen']++;

                    $parsed = $this->parsedLocationFor($product, $parser, $reparseSku, $stats);

                    $hasPositional = ! empty($parsed['room'])
                        || ! empty($parsed['special_area'])
                        || ! empty($parsed['shelf'])
                        || ! empty($parsed['container_type'])
                        || ! empty($parsed['level']);

                    if (! $hasPositional && empty($parsed['consignor']) && empty($parsed['item_id'])) {
                        continue;
                    }

                    // Sync attributes.warehouse_* / consignor_* if reparse produced new fields
                    if ($reparseSku) {
                        $attrs = $product->attributes ?? [];
                        $attrsBefore = $attrs;

                        foreach (['room', 'shelf', 'container_type', 'container_name', 'level', 'special_area'] as $field) {
                            if (! empty($parsed[$field])) {
                                $attrs['warehouse_'.$field] = $parsed[$field];
                            }
                        }
                        if (! empty($parsed['consignor'])) {
                            $attrs['consignor_code'] = $parsed['consignor'];
                        }
                        if (! empty($parsed['item_id'])) {
                            $attrs['consignor_item_id'] = $parsed['item_id'];
                        }

                        if ($attrs !== $attrsBefore) {
                            $stats['attributes_updated']++;
                            if (! $dryRun) {
                                $product->update(['attributes' => $attrs]);
                            }
                        }
                    }

                    if (! empty($parsed['consignor'])) {
                        $stats['consignors_tagged']++;
                        if (! $dryRun) {
                            try {
                                Supplier::firstOrCreate(
                                    ['code' => $parsed['consignor'], 'organization_id' => $orgId],
                                    ['name' => $parsed['consignor'], 'supplier_status' => 'active', 'meta_data' => ['is_consignor' => true]]
                                );
                            } catch (\Throwable $e) {
                                // non-critical
                            }
                        }
                    }

                    if (! $hasPositional) {
                        continue;
                    }
                    $stats['parsed_with_positional']++;

                    if ($dryRun) {
                        $stats['bins_assigned']++;

                        continue;
                    }

                    $bin = $builder->buildFromParsedLocation($parsed, (string) $orgId, $warehouse->id);
                    if (! $bin) {
                        continue;
                    }

                    $level = InventoryLevel::firstOrCreate(
                        ['product_id' => $product->id, 'location_id' => $warehouse->id, 'organization_id' => $orgId],
                        ['quantity_on_hand' => 0, 'quantity_reserved' => 0, 'bin_id' => $bin->id]
                    );

                    if ($level->bin_id !== $bin->id) {
                        $level->update(['bin_id' => $bin->id]);
                        $stats['bins_changed']++;
                    }
                    $stats['bins_assigned']++;
                }

                $offset += $size;
                if ($stats['products_seen'] % $logEvery === 0 || $offset >= $total) {
                    $this->line("  processed {$stats['products_seen']}/{$total} (bins_assigned={$stats['bins_assigned']} attrs_updated={$stats['attributes_updated']})");
                }
            }
        }

        $this->info($dryRun ? 'Dry run complete.' : 'Backfill complete.');
        foreach ($stats as $k => $v) {
            $this->line(sprintf('  %-30s %d', $k, $v));
        }

        return self::SUCCESS;
    }

    /**
     * Build a parsed-location array for a product. Default source is the
     * attributes.warehouse_* fields populated at import time. With
     * --reparse-sku, fall through to a fresh parse of EbayListing.ebay_sku
     * (only useful when listing rows actually exist).
     */
    private function parsedLocationFor(Product $product, EbaySkuLocationParser $parser, bool $reparseSku, array &$stats): array
    {
        $attrs = $product->attributes ?? [];

        $fromAttrs = [
            'owner' => null,
            'consignor' => $attrs['consignor_code'] ?? null,
            'item_id' => $attrs['consignor_item_id'] ?? null,
            'product_sku' => null,
            'room' => $attrs['warehouse_room'] ?? null,
            'shelf' => $attrs['warehouse_shelf'] ?? null,
            'container_type' => $attrs['warehouse_container_type'] ?? null,
            'container_name' => $attrs['warehouse_container_name'] ?? null,
            'level' => $attrs['warehouse_level'] ?? null,
            'special_area' => $attrs['warehouse_special_area'] ?? null,
            'raw_location' => null,
        ];

        $hasAnyAttr = ! empty(array_filter($fromAttrs, fn ($v) => $v !== null));
        if ($hasAnyAttr) {
            $stats['with_warehouse_attrs']++;
        }

        if (! $reparseSku) {
            return $fromAttrs;
        }

        $rawSku = optional($product->ebayListings->first())->ebay_sku;
        if (empty($rawSku)) {
            return $fromAttrs;
        }

        $stats['reparsed_from_sku']++;

        return $parser->parse($rawSku);
    }
}
