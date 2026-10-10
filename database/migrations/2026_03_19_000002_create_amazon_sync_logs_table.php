<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('amazon_sync_logs', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('integration_credential_id');
            $table->foreign('integration_credential_id')
                ->references('id')
                ->on('integration_credentials')
                ->onDelete('cascade');

            $table->unsignedBigInteger('amazon_listing_id')->nullable();
            $table->foreign('amazon_listing_id')
                ->references('id')
                ->on('amazon_listings')
                ->onDelete('set null');

            $table->uuid('product_id')->nullable();
            $table->foreign('product_id')
                ->references('id')
                ->on('products')
                ->onDelete('set null');

            $table->unsignedBigInteger('team_id');
            $table->foreign('team_id')
                ->references('id')
                ->on('teams')
                ->onDelete('cascade');

            // Operation details
            $table->string('operation', 50);
            $table->string('direction', 10)->default('outbound');

            // Amazon identifiers
            $table->string('amazon_sku', 50)->nullable();
            $table->string('asin', 10)->nullable();
            $table->string('feed_id', 50)->nullable();

            // API request details
            $table->string('api_endpoint', 255)->nullable();
            $table->string('http_method', 10)->nullable();
            $table->jsonb('request_payload')->nullable();

            // API response details
            $table->integer('http_status_code')->nullable();
            $table->jsonb('response_payload')->nullable();
            $table->integer('response_time_ms')->nullable();

            // Result status
            $table->string('status', 20);
            $table->string('error_code', 50)->nullable();
            $table->text('error_message')->nullable();
            $table->jsonb('error_details')->nullable();

            // Batch operations
            $table->string('batch_id', 36)->nullable();
            $table->integer('batch_sequence')->nullable();

            // Retry tracking
            $table->integer('retry_count')->default(0);
            $table->timestamp('next_retry_at')->nullable();
            $table->boolean('is_retryable')->default(false);

            // User who initiated
            $table->unsignedBigInteger('initiated_by')->nullable();
            $table->foreign('initiated_by')
                ->references('id')
                ->on('users')
                ->onDelete('set null');

            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            // Indexes
            $table->index(['team_id', 'created_at'], 'amazon_sync_team_date_idx');
            $table->index(['integration_credential_id', 'operation'], 'amazon_sync_credential_op_idx');
            $table->index(['amazon_listing_id', 'created_at'], 'amazon_sync_listing_date_idx');
            $table->index(['status', 'created_at'], 'amazon_sync_status_date_idx');
            $table->index(['batch_id', 'batch_sequence'], 'amazon_sync_batch_idx');
            $table->index(['operation', 'status', 'created_at'], 'amazon_sync_op_status_idx');
            $table->index(['is_retryable', 'next_retry_at'], 'amazon_sync_retry_queue_idx');
            $table->index('feed_id', 'amazon_sync_feed_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('amazon_sync_logs');
    }
};
