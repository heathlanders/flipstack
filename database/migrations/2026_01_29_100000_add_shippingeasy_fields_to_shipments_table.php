<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->string('shippingeasy_shipment_id')->nullable()->index();
            $table->string('shippingeasy_order_id')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->dropIndex(['shippingeasy_shipment_id']);
            $table->dropIndex(['shippingeasy_order_id']);
            $table->dropColumn(['shippingeasy_shipment_id', 'shippingeasy_order_id']);
        });
    }
};
