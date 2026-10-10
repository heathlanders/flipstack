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
        Schema::table('products', function (Blueprint $table) {
            $table->string('internal_sku', 20)->nullable()->unique()->after('sku');
            $table->timestamp('internal_sku_generated_at')->nullable()->after('internal_sku');

            // Index for faster lookups by organization and internal SKU
            $table->index(['organization_id', 'internal_sku']);
            $table->index('internal_sku');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn([
                'internal_sku',
                'internal_sku_generated_at',
            ]);
        });
    }
};
