<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_intake_queue', function (Blueprint $table) {
            $table->uuid('quick_location_id')->nullable()->after('quick_location');
            $table->uuid('quick_bin_id')->nullable()->after('quick_location_id');

            $table->foreign('quick_location_id')
                ->references('id')->on('inventory_locations')
                ->nullOnDelete();

            $table->foreign('quick_bin_id')
                ->references('id')->on('warehouse_bins')
                ->nullOnDelete();

            $table->index('quick_location_id');
            $table->index('quick_bin_id');
        });

        // Backfill: resolve legacy freetext quick_location -> InventoryLocation by code/name within org
        DB::statement(<<<'SQL'
            UPDATE product_intake_queue piq
            SET quick_location_id = il.id
            FROM inventory_locations il
            WHERE piq.quick_location_id IS NULL
              AND piq.quick_location IS NOT NULL
              AND il.organization_id = piq.organization_id
              AND il.is_active = TRUE
              AND (
                LOWER(il.code) = LOWER(piq.quick_location)
                OR LOWER(il.name) = LOWER(piq.quick_location)
              )
        SQL);

        // Backfill: resolve legacy freetext quick_location -> WarehouseBin by code (when string matches a bin code in active org locations)
        DB::statement(<<<'SQL'
            UPDATE product_intake_queue piq
            SET quick_bin_id = wb.id
            FROM warehouse_bins wb
            JOIN warehouse_zones wz ON wz.id = wb.zone_id
            JOIN inventory_locations il ON il.id = wz.location_id
            WHERE piq.quick_bin_id IS NULL
              AND piq.quick_location IS NOT NULL
              AND il.organization_id = piq.organization_id
              AND il.is_active = TRUE
              AND wb.is_active = TRUE
              AND LOWER(wb.code) = LOWER(piq.quick_location)
        SQL);

        // NOTE: products.warehouse_location_id points at the legacy `warehouse_locations` table (bigint PK,
        // unused — zero rows). It is NOT compatible with `inventory_locations` (UUID). The pick path is
        // fixed by reading from inventory_levels directly, so no Product backfill is needed here.
    }

    public function down(): void
    {
        Schema::table('product_intake_queue', function (Blueprint $table) {
            $table->dropForeign(['quick_location_id']);
            $table->dropForeign(['quick_bin_id']);
            $table->dropIndex(['quick_location_id']);
            $table->dropIndex(['quick_bin_id']);
            $table->dropColumn(['quick_location_id', 'quick_bin_id']);
        });
    }
};
