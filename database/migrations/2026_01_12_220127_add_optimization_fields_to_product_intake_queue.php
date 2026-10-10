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
        Schema::table('product_intake_queue', function (Blueprint $table) {
            // Processing metadata
            $table->integer('processing_attempts')->default(0)->after('processing_status');
            $table->timestamp('last_processed_at')->nullable()->after('processing_attempts');
            $table->timestamp('enriched_at')->nullable()->after('last_processed_at');
            $table->timestamp('validated_at')->nullable()->after('enriched_at');

            // Quality metrics
            $table->integer('quality_score')->default(0)->after('ai_confidence_score');
            $table->json('quality_breakdown')->nullable()->after('quality_score');

            // Validation results
            $table->json('validation_errors')->nullable()->after('quality_breakdown');
            $table->json('missing_fields')->nullable()->after('validation_errors');

            // Data sources tracking
            $table->json('data_sources')->nullable()->after('missing_fields');
            $table->string('primary_source')->nullable()->after('data_sources');

            // Add index for filtering by status and quality
            $table->index(['processing_status', 'quality_score']);
            $table->index('last_processed_at');
        });
        // Create product data cache table for external API responses
        Schema::create('product_data_cache', function (Blueprint $table) {
            $table->id();
            $table->string('source'); // 'ebay', 'gemini', 'upc', etc.
            $table->string('identifier_type'); // 'upc', 'ean', 'manufacturer_part_number', etc.
            $table->string('identifier_value');
            $table->json('data');
            $table->timestamp('expires_at');
            $table->timestamps();

            // Unique constraint to prevent duplicates
            $table->unique(['source', 'identifier_type', 'identifier_value'], 'source_identifier_unique');

            // Index for cleanup queries
            $table->index('expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_intake_queue', function (Blueprint $table) {
            $table->dropIndex(['processing_status', 'quality_score']);
            $table->dropIndex(['last_processed_at']);

            $table->dropColumn([
                'processing_attempts',
                'last_processed_at',
                'enriched_at',
                'validated_at',
                'quality_score',
                'quality_breakdown',
                'validation_errors',
                'missing_fields',
                'data_sources',
                'primary_source',
            ]);
        });
        Schema::dropIfExists('product_data_cache');
    }
};
