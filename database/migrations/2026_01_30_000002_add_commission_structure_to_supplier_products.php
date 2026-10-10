<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('supplier_products', function (Blueprint $table) {
            $table->uuid('commission_structure_id')->nullable()->after('is_active');
            $table->foreign('commission_structure_id')
                ->references('id')
                ->on('commission_structures')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('supplier_products', function (Blueprint $table) {
            $table->dropForeign(['commission_structure_id']);
            $table->dropColumn('commission_structure_id');
        });
    }
};
