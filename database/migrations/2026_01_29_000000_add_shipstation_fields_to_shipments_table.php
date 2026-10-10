<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->string('shipping_provider')->default('easypost')->after('status');
            $table->string('shipstation_shipment_id')->nullable()->after('easypost_tracker_id')->index();
            $table->string('shipstation_order_id')->nullable()->after('shipstation_shipment_id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->dropColumn(['shipping_provider', 'shipstation_shipment_id', 'shipstation_order_id']);
        });
    }
};
