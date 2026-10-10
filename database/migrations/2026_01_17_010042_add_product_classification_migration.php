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
            // Add product classification fields
            $table->string('product_category')->nullable()->after('organization_id')
                ->comment('Product category for AI research optimization');
            $table->string('product_type')->nullable()->after('product_category')
                ->comment('Product type (complete unit, part, accessory, etc.)');

            // Add index for filtering by category/type combinations
            $table->index(['product_category', 'product_type'], 'idx_product_classification');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_intake_queue', function (Blueprint $table) {
            $table->dropIndex('idx_product_classification');
            $table->dropColumn(['product_category', 'product_type']);
        });
    }
};
