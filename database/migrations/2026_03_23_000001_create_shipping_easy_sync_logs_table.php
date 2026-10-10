<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipping_easy_sync_logs', function (Blueprint $table) {
            $table->id();

            // Organization scoping
            $table->unsignedBigInteger('organization_id');
            $table->foreign('organization_id')
                ->references('id')
                ->on('organizations')
                ->onDelete('cascade');

            // Optional link to integration credential
            $table->unsignedBigInteger('integration_credential_id')->nullable();
            $table->foreign('integration_credential_id')
                ->references('id')
                ->on('integration_credentials')
                ->onDelete('set null');

            // Optional links to fulfillment records
            $table->uuid('order_id')->nullable();
            $table->foreign('order_id')
                ->references('id')
                ->on('orders')
                ->onDelete('set null');

            $table->uuid('shipment_id')->nullable();
            $table->foreign('shipment_id')
                ->references('id')
                ->on('shipments')
                ->onDelete('set null');

            // ShippingEasy-specific IDs
            $table->string('shippingeasy_order_id')->nullable();
            $table->string('shippingeasy_shipment_id')->nullable();

            // Operation details
            $table->string('operation', 50); // create_order, purchase_label, void_label, get_rates, get_orders, get_carriers, webhook_received, cancel_order
            $table->string('direction', 10)->default('outbound'); // outbound (to SE), inbound (from SE/webhook)

            // API request details
            $table->string('api_endpoint', 255)->nullable();
            $table->string('http_method', 10)->nullable();
            $table->jsonb('request_payload')->nullable();

            // API response details
            $table->integer('http_status_code')->nullable();
            $table->jsonb('response_payload')->nullable();
            $table->integer('response_time_ms')->nullable();

            // Result status
            $table->string('status', 20); // success, error, warning, pending
            $table->string('error_code', 50)->nullable();
            $table->text('error_message')->nullable();
            $table->jsonb('error_details')->nullable();

            // For batch operations
            $table->string('batch_id', 36)->nullable();
            $table->integer('batch_sequence')->nullable();

            // Retry tracking
            $table->integer('retry_count')->default(0);
            $table->timestamp('next_retry_at')->nullable();
            $table->boolean('is_retryable')->default(false);

            // User who initiated (if manual)
            $table->unsignedBigInteger('initiated_by')->nullable();
            $table->foreign('initiated_by')
                ->references('id')
                ->on('users')
                ->onDelete('set null');

            // Timestamps
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            // Indexes
            $table->index(['organization_id', 'created_at'], 'se_sync_org_date_idx');
            $table->index(['organization_id', 'operation'], 'se_sync_org_operation_idx');
            $table->index(['order_id', 'created_at'], 'se_sync_order_date_idx');
            $table->index(['shipment_id', 'created_at'], 'se_sync_shipment_date_idx');
            $table->index(['status', 'created_at'], 'se_sync_status_date_idx');
            $table->index(['operation', 'status', 'created_at'], 'se_sync_operation_status_idx');
            $table->index(['batch_id', 'batch_sequence'], 'se_sync_batch_idx');
            $table->index(['is_retryable', 'next_retry_at'], 'se_sync_retry_queue_idx');
            $table->index('shippingeasy_order_id', 'se_sync_se_order_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipping_easy_sync_logs');
    }
};
