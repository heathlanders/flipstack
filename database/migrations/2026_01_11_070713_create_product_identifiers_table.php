<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('product_identifiers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('product_id');
            $table->string('type', 100)->index(); // 'upc', 'ean', 'asin', 'mpn', 'serial', etc.
            $table->string('value', 500)->index(); // The actual identifier value
            $table->boolean('is_primary')->default(false)->index(); // Which identifier is the "main" one
            $table->boolean('verified')->default(false); // Has this been confirmed accurate?
            $table->string('source', 100)->nullable(); // 'barcode_scan', 'manual_entry', 'api_lookup', 'ebay_import'
            $table->integer('confidence_score')->nullable(); // 0-100 confidence from AI/API
            $table->jsonb('metadata')->nullable(); // Extra data specific to identifier type
            $table->bigInteger('created_by')->unsigned()->nullable();
            $table->timestamps();

            // Foreign key constraints
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');

            // Unique constraint to prevent duplicate identifiers for same product
            $table->unique(['product_id', 'type', 'value'], 'unique_product_identifier');

            // Additional indexes for performance
            $table->index(['type', 'value'], 'idx_type_value_lookup');
            $table->index(['is_primary'], 'idx_primary_identifiers');
            $table->index(['verified'], 'idx_verified_identifiers');
            $table->index(['source'], 'idx_identifier_source');
            $table->index(['confidence_score'], 'idx_confidence_score');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_identifiers');
    }
};
