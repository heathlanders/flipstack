<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Comprehensive sync log for tracking all eBay API operations.
     * Essential for debugging, auditing, and monitoring sync health.
     */
    public function up(): void
    {
        Schema::create('ebay_sync_logs', function (Blueprint $table) {
            $table->id();

            // Link to integration credentials
            $table->unsignedBigInteger('integration_credential_id');
            $table->foreign('integration_credential_id')
                ->references('id')
                ->on('integration_credentials')
                ->onDelete('cascade');

            // Optional link to specific listing (nullable for bulk operations)
            $table->unsignedBigInteger('ebay_listing_id')->nullable();
            $table->foreign('ebay_listing_id')
                ->references('id')
                ->on('ebay_listings')
                ->onDelete('set null');

            // Optional link to product (for quick filtering)
            $table->uuid('product_id')->nullable();
            $table->foreign('product_id')
                ->references('id')
                ->on('products')
                ->onDelete('set null');

            // Team for multi-tenant filtering
            $table->unsignedBigInteger('team_id');
            $table->foreign('team_id')
                ->references('id')
                ->on('teams')
                ->onDelete('cascade');

            // Operation details
            $table->string('operation', 50);  // create_inventory, update_inventory, create_offer,
            // update_offer, publish, end_listing, sync_quantity, etc.
            $table->string('direction', 10)->default('outbound'); // outbound (to eBay), inbound (from eBay)

            // What was affected
            $table->string('ebay_sku', 50)->nullable();
            $table->string('ebay_offer_id')->nullable();
            $table->string('ebay_item_id')->nullable();  // The actual eBay item number

            // API request details
            $table->string('api_endpoint', 255)->nullable();
            $table->string('http_method', 10)->nullable();
            $table->jsonb('request_payload')->nullable();    // What we sent (sanitized, no tokens)

            // API response details
            $table->integer('http_status_code')->nullable();
            $table->jsonb('response_payload')->nullable();   // What eBay returned
            $table->integer('response_time_ms')->nullable(); // Performance tracking

            // Result status
            $table->string('status', 20);  // success, error, warning, skipped
            $table->string('error_code', 50)->nullable();
            $table->text('error_message')->nullable();
            $table->jsonb('error_details')->nullable();      // Full error context from eBay

            // For batch operations
            $table->string('batch_id', 36)->nullable();      // UUID to group related operations
            $table->integer('batch_sequence')->nullable();   // Order within batch

            // Retry tracking
            $table->integer('retry_count')->default(0);
            $table->timestamp('next_retry_at')->nullable();
            $table->boolean('is_retryable')->default(false);

            // User who initiated (if manual operation)
            $table->unsignedBigInteger('initiated_by')->nullable();
            $table->foreign('initiated_by')
                ->references('id')
                ->on('users')
                ->onDelete('set null');

            // Timestamps
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            // Indexes for common queries
            $table->index(['team_id', 'created_at'], 'sync_team_date_idx');
            $table->index(['integration_credential_id', 'operation'], 'sync_credential_operation_idx');
            $table->index(['ebay_listing_id', 'created_at'], 'sync_listing_date_idx');
            $table->index(['status', 'created_at'], 'sync_status_date_idx');
            $table->index(['batch_id', 'batch_sequence'], 'sync_batch_idx');
            $table->index(['operation', 'status', 'created_at'], 'sync_operation_status_idx');
            $table->index('ebay_sku', 'sync_sku_idx');

            // For retry queue queries
            $table->index(['is_retryable', 'next_retry_at'], 'sync_retry_queue_idx');
        });

        // Add comment to table (PostgreSQL)
        // DB::statement("COMMENT ON TABLE ebay_sync_logs IS 'Audit trail for all eBay API operations - requests, responses, errors, and performance metrics'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ebay_sync_logs');
    }
};
