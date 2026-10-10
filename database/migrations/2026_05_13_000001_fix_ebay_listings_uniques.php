<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The original ebay_listings unique constraints assumed one EbayListing row
 * per (product, credential) and per (ebay_sku, credential). Neither holds:
 *
 * - eBay allows multiple listings to share the same Custom label (SKU).
 * - Custom-SKU parsers strip many raw SKUs down to one canonical product
 *   SKU, so a single product legitimately has multiple eBay listings.
 *
 * The real unique identifier is (ebay_listing_id, credential). Drop the two
 * wrong-shaped uniques and add the correct one.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Drop the misshapen constraints only if they exist. Different envs
        // diverged on which were actually created.
        foreach (['ebay_product_credential_unique', 'ebay_sku_credential_unique'] as $name) {
            if ($this->constraintExists('ebay_listings', $name)) {
                Schema::table('ebay_listings', fn (Blueprint $t) => $t->dropUnique($name));
            }
        }

        if (! $this->constraintExists('ebay_listings', 'ebay_listing_id_credential_unique')) {
            Schema::table('ebay_listings', function (Blueprint $t) {
                $t->unique(
                    ['ebay_listing_id', 'integration_credential_id'],
                    'ebay_listing_id_credential_unique'
                );
            });
        }
    }

    public function down(): void
    {
        if ($this->constraintExists('ebay_listings', 'ebay_listing_id_credential_unique')) {
            Schema::table('ebay_listings', fn (Blueprint $t) => $t->dropUnique('ebay_listing_id_credential_unique'));
        }
    }

    private function constraintExists(string $table, string $name): bool
    {
        $rows = \DB::select(
            'SELECT 1 FROM pg_constraint WHERE conrelid = ?::regclass AND conname = ?',
            [$table, $name]
        );

        return ! empty($rows);
    }
};
