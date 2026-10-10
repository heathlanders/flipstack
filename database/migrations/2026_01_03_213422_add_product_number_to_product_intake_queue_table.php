<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_intake_queue', function (Blueprint $table) {
            // Add manufacturer_part_number first (if it doesn't exist)
            if (! Schema::hasColumn('product_intake_queue', 'manufacturer_part_number')) {
                $table->string('manufacturer_part_number', 255)->nullable()->after('barcode');
                $table->index('manufacturer_part_number');
            }

            // Add product_number after manufacturer_part_number
            if (! Schema::hasColumn('product_intake_queue', 'product_number')) {
                $table->string('product_number', 255)->nullable()->after('manufacturer_part_number');
                $table->index('product_number');
            }
        });
    }

    public function down(): void
    {
        Schema::table('product_intake_queue', function (Blueprint $table) {
            if (Schema::hasColumn('product_intake_queue', 'product_number')) {
                $table->dropIndex(['product_number']);
                $table->dropColumn('product_number');
            }

            if (Schema::hasColumn('product_intake_queue', 'manufacturer_part_number')) {
                $table->dropIndex(['manufacturer_part_number']);
                $table->dropColumn('manufacturer_part_number');
            }
        });
    }
};
