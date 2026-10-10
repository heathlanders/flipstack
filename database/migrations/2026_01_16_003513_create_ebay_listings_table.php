<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Maps local products (UUID) to eBay inventory items and offers.
     * This is the core tracking table that links your products table to eBay.
     */
    public function up(): void
    {
        Schema::create('ebay_listings', function (Blueprint $table) {
            $table->id(); // bigint to match your existing pattern

            // Link to your existing products table (UUID-based)
            $table->uuid('product_id');
            $table->foreign('product_id')
                ->references('id')
                ->on('products')
                ->onDelete('cascade');

            // Link to integration credentials (bigint)
            $table->unsignedBigInteger('integration_credential_id');
            $table->foreign('integration_credential_id')
                ->references('id')
                ->on('integration_credentials')
                ->onDelete('cascade');

            // Multi-tenant: Team ownership (matches your existing pattern)
            $table->unsignedBigInteger('team_id');
            $table->foreign('team_id')
                ->references('id')
                ->on('teams')
                ->onDelete('cascade');

            // eBay Inventory API identifiers
            $table->string('ebay_sku', 50);              // SKU used in eBay Inventory API
            $table->string('ebay_offer_id')->nullable(); // eBay Offer ID after creation
            $table->string('ebay_listing_id')->nullable(); // eBay Item ID after publishing

            // Listing status tracking
            $table->string('status', 30)->default('pending');
            // Valid statuses: pending, inventory_created, offer_created, published,
            //                 ended, out_of_stock, error, deleted

            // Listing details (cached from eBay for quick reference)
            $table->string('ebay_category_id')->nullable();
            $table->string('ebay_category_name')->nullable();
            $table->decimal('listed_price', 12, 2)->nullable();
            $table->string('currency', 3)->default('USD');
            $table->integer('listed_quantity')->nullable();
            $table->integer('available_quantity')->nullable();
            $table->integer('sold_quantity')->default(0);

            // Listing format and condition
            $table->string('listing_format', 20)->default('FIXED_PRICE'); // FIXED_PRICE, AUCTION
            $table->string('condition', 50)->default('NEW');
            $table->text('condition_description')->nullable();

            // eBay listing URLs (for quick access)
            $table->string('ebay_listing_url', 500)->nullable();

            // Sync tracking
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamp('last_inventory_update_at')->nullable();
            $table->timestamp('last_price_update_at')->nullable();
            $table->timestamp('listing_start_date')->nullable();
            $table->timestamp('listing_end_date')->nullable();

            // Error tracking
            $table->text('last_error_message')->nullable();
            $table->string('last_error_code', 50)->nullable();
            $table->timestamp('last_error_at')->nullable();
            $table->integer('consecutive_errors')->default(0);

            // Data snapshots (for detecting changes and debugging)
            $table->string('product_data_hash', 64)->nullable(); // MD5/SHA hash for change detection
            $table->jsonb('last_sync_payload')->nullable();      // What was sent to eBay
            $table->jsonb('last_ebay_response')->nullable();     // What eBay returned

            // Audit fields
            $table->timestamps();
            $table->softDeletes();

            // Indexes for common queries
            $table->unique(['product_id', 'integration_credential_id'], 'ebay_product_credential_unique');
            $table->unique(['ebay_sku', 'integration_credential_id'], 'ebay_sku_credential_unique');
            $table->index(['team_id', 'status'], 'ebay_team_status_idx');
            $table->index('ebay_listing_id', 'ebay_listing_id_idx');
            $table->index('ebay_offer_id', 'ebay_offer_id_idx');
            $table->index(['status', 'last_synced_at'], 'ebay_status_sync_idx');
            $table->index(['status', 'consecutive_errors'], 'ebay_status_errors_idx');
        });

        // Add comment to table (PostgreSQL)
        DB::statement("COMMENT ON TABLE ebay_listings IS 'Maps local products to eBay inventory items and offers, tracking sync status and listing details'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ebay_listings');
    }
};
