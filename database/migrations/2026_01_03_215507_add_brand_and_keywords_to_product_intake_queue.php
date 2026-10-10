<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_intake_queue', function (Blueprint $table) {
            $table->string('brand', 100)->nullable()->after('barcode');
            $table->json('keywords')->nullable()->after('brand');
            $table->index('brand');
        });
    }

    public function down(): void
    {
        Schema::table('product_intake_queue', function (Blueprint $table) {
            $table->dropIndex(['brand']);
            $table->dropColumn(['brand', 'keywords']);
        });
    }
};
