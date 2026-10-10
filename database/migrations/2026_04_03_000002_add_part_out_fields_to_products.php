<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('is_parted_out')->default(false)->after('is_bundle');
            $table->jsonb('component_breakdown')->nullable()->after('is_parted_out');
            $table->uuid('parted_from_product_id')->nullable()->after('component_breakdown');
            $table->uuid('part_out_work_order_id')->nullable()->after('parted_from_product_id');

            $table->foreign('parted_from_product_id')->references('id')->on('products')->onDelete('set null');
            $table->foreign('part_out_work_order_id')->references('id')->on('part_out_work_orders')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['parted_from_product_id']);
            $table->dropForeign(['part_out_work_order_id']);
            $table->dropColumn([
                'is_parted_out',
                'component_breakdown',
                'parted_from_product_id',
                'part_out_work_order_id',
            ]);
        });
    }
};
