<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('amazon_listings', function (Blueprint $table) {
            $table->id();

            $table->uuid('product_id');
            $table->foreign('product_id')
                ->references('id')
                ->on('products')
                ->onDelete('cascade');

            $table->unsignedBigInteger('integration_credential_id');
            $table->foreign('integration_credential_id')
                ->references('id')
                ->on('integration_credentials')
                ->onDelete('cascade');

            $table->unsignedBigInteger('team_id');
            $table->foreign('team_id')
                ->references('id')
                ->on('teams')
                ->onDelete('cascade');

            // Amazon identifiers
            $table->string('asin', 10)->nullable();
            $table->string('fnsku', 20)->nullable();
            $table->string('amazon_sku', 50);
            $table->string('amazon_product_type', 100)->nullable();

            // ASIN matching
            $table->string('asin_match_source', 20)->nullable(); // gtin, search, manual, created
            $table->decimal('asin_match_confidence', 5, 2)->nullable();

            // Fulfillment
            $table->string('fulfillment_channel', 10)->default('FBM'); // FBM or FBA

            // Listing status tracking
            $table->string('status', 30)->default('pending');
            $table->string('amazon_status', 20)->nullable(); // Active, Inactive, Incomplete
            $table->text('suppressed_reason')->nullable();

            // Listing details
            $table->decimal('listed_price', 12, 2)->nullable();
            $table->decimal('minimum_price', 12, 2)->nullable();
            $table->decimal('maximum_price', 12, 2)->nullable();
            $table->decimal('buy_box_price', 12, 2)->nullable();
            $table->string('currency', 3)->default('USD');
            $table->integer('inventory_quantity')->nullable();
            $table->integer('fba_quantity')->nullable();

            // Change detection
            $table->string('product_data_hash', 64)->nullable();
            $table->jsonb('last_sync_payload')->nullable();
            $table->jsonb('last_amazon_response')->nullable();

            // Feed tracking
            $table->string('last_feed_id', 50)->nullable();
            $table->string('last_feed_status', 20)->nullable();

            // Sync tracking
            $table->timestamp('last_synced_at')->nullable();
            $table->string('sync_direction_lock', 10)->nullable();

            // Error tracking
            $table->text('last_error_message')->nullable();
            $table->string('last_error_code', 50)->nullable();
            $table->timestamp('last_error_at')->nullable();
            $table->integer('consecutive_errors')->default(0);

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->unique(['product_id', 'integration_credential_id'], 'amazon_product_credential_unique');
            $table->unique(['amazon_sku', 'integration_credential_id'], 'amazon_sku_credential_unique');
            $table->index(['team_id', 'status'], 'amazon_team_status_idx');
            $table->index('asin', 'amazon_asin_idx');
            $table->index(['status', 'last_synced_at'], 'amazon_status_sync_idx');
            $table->index(['status', 'consecutive_errors'], 'amazon_status_errors_idx');
            $table->index('fulfillment_channel', 'amazon_fulfillment_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('amazon_listings');
    }
};
