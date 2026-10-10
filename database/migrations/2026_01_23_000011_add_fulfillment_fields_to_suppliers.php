<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->string('fulfillment_mode', 50)->default('org_fulfilled')->after('is_active'); // org_fulfilled, supplier_dropship, hybrid
            $table->unsignedInteger('dropship_sla_hours')->default(48)->after('fulfillment_mode');
        });
    }

    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropColumn(['fulfillment_mode', 'dropship_sla_hours']);
        });
    }
};
