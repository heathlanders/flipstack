<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations - Add missing enhanced fields to product_intake_queue
     */
    public function up(): void
    {
        Schema::table('product_intake_queue', function (Blueprint $table) {
            // Add missing product identification fields
            $table->string('model_number')->nullable()->after('manufacturer_part_number');
            $table->string('series')->nullable()->after('model_number');

            // Add missing completeness field
            $table->text('missing_items_notes')->nullable()->after('completeness_data');

            // Add shipping classification fields
            $table->string('suggested_shipping_class', 50)->nullable()->after('height_inches');
            $table->decimal('estimated_shipping_cost', 10, 2)->nullable()->after('suggested_shipping_class');

            // Add enhanced scoring fields
            $table->decimal('completeness_score', 5, 2)->default(0)->after('quality_score');
            $table->json('confidence_breakdown')->nullable()->after('ai_confidence_score');

            // Add supplier priority tracking
            $table->boolean('supplier_priority_boost')->default(false)->after('supplier_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_intake_queue', function (Blueprint $table) {
            $table->dropColumn([
                'model_number',
                'series',
                'missing_items_notes',
                'suggested_shipping_class',
                'estimated_shipping_cost',
                'completeness_score',
                'confidence_breakdown',
                'supplier_priority_boost',
            ]);
        });
    }
};
