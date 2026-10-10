<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketplace_messages', function (Blueprint $table) {
            $table->uuid('product_id')->nullable()->after('order_id');
            $table->foreign('product_id')->references('id')->on('products')->nullOnDelete();
            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::table('marketplace_messages', function (Blueprint $table) {
            $table->dropForeign(['product_id']);
            $table->dropIndex(['product_id']);
            $table->dropColumn('product_id');
        });
    }
};
