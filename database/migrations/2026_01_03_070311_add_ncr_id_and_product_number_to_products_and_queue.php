<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('manufacturer_part_number')->nullable()->after('mpn');
            $table->string('product_number')->nullable()->after('manufacturer_part_number');

            $table->index('manufacturer_part_number');
            $table->index('product_number');
        });

        Schema::table('product_intake_queue', function (Blueprint $table) {
            $table->string('manufacturer_part_number')->nullable()->after('barcode');
            $table->string('product_number')->nullable()->after('manufacturer_part_number');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['manufacturer_part_number']);
            $table->dropIndex(['product_number']);
            $table->dropColumn(['manufacturer_part_number', 'product_number']);
        });

        Schema::table('product_intake_queue', function (Blueprint $table) {
            $table->dropColumn(['manufacturer_part_number', 'product_number']);
        });
    }
};
