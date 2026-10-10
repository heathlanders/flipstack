<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('product_intake_queue', function (Blueprint $table) {
            $table->uuid('supplier_id')->nullable()->after('organization_id');
            $table->decimal('weight_lbs', 8, 2)->nullable()->after('quick_location');
            $table->decimal('length_inches', 8, 2)->nullable()->after('weight_lbs');
            $table->decimal('width_inches', 8, 2)->nullable()->after('length_inches');
            $table->decimal('height_inches', 8, 2)->nullable()->after('width_inches');
            $table->json('completeness_data')->nullable()->after('height_inches');
            $table->boolean('lookup_attempted')->default(false)->after('completeness_data');
            $table->json('existing_product_data')->nullable()->after('lookup_attempted');

            $table->foreign('supplier_id')->references('id')->on('suppliers');
            $table->index(['supplier_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_intake_queue', function (Blueprint $table) {
            //
        });
    }
};
