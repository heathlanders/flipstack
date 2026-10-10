<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add index on manufacturer_part_number in product_intake_queue for better performance
        Schema::table('product_intake_queue', function (Blueprint $table) {
            $table->index('manufacturer_part_number');
        });

        // Add comments for clarity
        DB::statement('COMMENT ON COLUMN products.mpn IS \'Manufacturer Part Number - consolidated from various sources including NCR, Dell, HP, etc.\'');
        DB::statement('COMMENT ON COLUMN product_intake_queue.manufacturer_part_number IS \'Manufacturer Part Number from any brand - Dell, NCR, HP, Lenovo, etc.\'');
    }

    public function down(): void
    {
        Schema::table('product_intake_queue', function (Blueprint $table) {
            $table->dropIndex(['manufacturer_part_number']);
        });

        DB::statement('COMMENT ON COLUMN products.mpn IS NULL');
        DB::statement('COMMENT ON COLUMN product_intake_queue.manufacturer_part_number IS NULL');
    }
};
