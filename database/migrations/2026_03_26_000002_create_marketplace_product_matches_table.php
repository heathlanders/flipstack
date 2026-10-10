<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketplace_product_matches', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('bulk_operation_job_id');
            $table->uuid('product_id')->nullable();
            $table->string('source_marketplace', 20);
            $table->string('source_listing_id', 100);
            $table->string('source_variant_id', 100)->nullable();
            $table->string('source_inventory_item_id', 100)->nullable();
            $table->string('source_sku', 255)->nullable();
            $table->string('source_barcode', 255)->nullable();
            $table->string('source_title', 500);
            $table->decimal('source_price', 10, 2)->nullable();
            $table->integer('source_quantity')->nullable();
            $table->jsonb('source_data')->nullable();
            $table->string('match_type', 20)->nullable();
            $table->integer('confidence')->nullable();
            $table->string('status', 20)->default('pending');
            $table->unsignedBigInteger('integration_credential_id');
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('bulk_operation_job_id')->references('id')->on('bulk_operation_jobs')->cascadeOnDelete();
            $table->foreign('product_id')->references('id')->on('products')->nullOnDelete();
            $table->foreign('integration_credential_id')->references('id')->on('integration_credentials')->cascadeOnDelete();
            $table->foreign('reviewed_by')->references('id')->on('users')->nullOnDelete();

            $table->index(['organization_id', 'bulk_operation_job_id', 'status'], 'mpm_org_job_status_idx');
            $table->unique(['source_marketplace', 'source_listing_id', 'integration_credential_id'], 'mpm_source_credential_unique');
            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_product_matches');
    }
};
