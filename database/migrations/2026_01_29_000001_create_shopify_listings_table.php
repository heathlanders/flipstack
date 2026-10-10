<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shopify_listings', function (Blueprint $table) {
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

            // Shopify identifiers
            $table->unsignedBigInteger('shopify_product_id')->nullable();
            $table->unsignedBigInteger('shopify_variant_id')->nullable();
            $table->unsignedBigInteger('shopify_inventory_item_id')->nullable();
            $table->string('shopify_sku', 50);
            $table->string('shopify_handle', 255)->nullable();

            // Listing status tracking
            $table->string('status', 30)->default('pending');

            // Listing details
            $table->decimal('listed_price', 12, 2)->nullable();
            $table->decimal('compare_at_price', 12, 2)->nullable();
            $table->string('currency', 3)->default('USD');
            $table->integer('inventory_quantity')->nullable();

            // Change detection
            $table->string('product_data_hash', 64)->nullable();
            $table->jsonb('last_sync_payload')->nullable();
            $table->jsonb('last_shopify_response')->nullable();

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
            $table->unique(['product_id', 'integration_credential_id'], 'shopify_product_credential_unique');
            $table->unique(['shopify_sku', 'integration_credential_id'], 'shopify_sku_credential_unique');
            $table->index(['team_id', 'status'], 'shopify_team_status_idx');
            $table->index('shopify_product_id', 'shopify_product_id_idx');
            $table->index(['status', 'last_synced_at'], 'shopify_status_sync_idx');
            $table->index(['status', 'consecutive_errors'], 'shopify_status_errors_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shopify_listings');
    }
};
