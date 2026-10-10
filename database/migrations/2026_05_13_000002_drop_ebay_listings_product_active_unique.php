<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Drop the partial unique index ebay_listings_product_active_unique
 * (created 2026-04-10) which enforced "one active eBay listing per product."
 *
 * That invariant doesn't hold once custom-SKU parsers map many raw eBay SKUs
 * to a single canonical product (the AMP consignor pattern). The same product
 * legitimately has multiple concurrently-active eBay listings, each with its
 * own ebay_listing_id. Uniqueness on (ebay_listing_id, integration_credential_id)
 * — added in the prior migration this session — is the correct invariant.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('DROP INDEX IF EXISTS ebay_listings_product_active_unique');
    }

    public function down(): void
    {
        // Restore only if it doesn't exist already (idempotent).
        DB::statement("
            CREATE UNIQUE INDEX IF NOT EXISTS ebay_listings_product_active_unique
            ON ebay_listings (product_id)
            WHERE status NOT IN ('deleted', 'ended')
        ");
    }
};
