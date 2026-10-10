<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_supplier')->default(false)->after('is_active');
            $table->string('supplier_status')->nullable()->after('is_supplier');
            $table->uuid('supplier_id')->nullable()->after('supplier_status');

            $table->foreign('supplier_id')
                ->references('id')
                ->on('suppliers')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['supplier_id']);
            $table->dropColumn(['is_supplier', 'supplier_status', 'supplier_id']);
        });
    }
};
