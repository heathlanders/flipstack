<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('order_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('location_id')->constrained('inventory_locations')->cascadeOnDelete();
            $table->string('shipment_number')->unique();
            $table->string('status', 50)->default('pending'); // pending, label_purchased, packed, shipped, in_transit, out_for_delivery, delivered, failed, returned_to_sender, cancelled
            $table->string('carrier', 50)->nullable();
            $table->string('service', 100)->nullable();
            $table->string('tracking_number', 100)->nullable();
            $table->string('tracking_url', 500)->nullable();
            $table->string('easypost_shipment_id')->nullable();
            $table->string('easypost_tracker_id')->nullable();
            $table->string('label_url', 500)->nullable();
            $table->string('label_format', 10)->default('PDF');
            $table->timestamp('label_purchased_at')->nullable();
            $table->decimal('label_cost', 8, 2)->nullable();
            $table->decimal('insurance_cost', 8, 2)->default(0);
            $table->decimal('total_shipping_cost', 8, 2)->nullable();
            $table->date('estimated_delivery_date')->nullable();
            $table->date('actual_delivery_date')->nullable();
            $table->string('ship_from_name');
            $table->string('ship_from_address_line1');
            $table->string('ship_from_city', 100);
            $table->string('ship_from_state', 100);
            $table->string('ship_from_postal_code', 20);
            $table->string('ship_from_country', 100)->default('US');
            $table->decimal('package_weight_oz', 8, 2)->nullable();
            $table->decimal('package_length', 6, 2)->nullable();
            $table->decimal('package_width', 6, 2)->nullable();
            $table->decimal('package_height', 6, 2)->nullable();
            $table->boolean('tracking_uploaded_to_marketplace')->default(false);
            $table->timestamp('tracking_uploaded_at')->nullable();
            $table->foreignId('packed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('packed_at')->nullable();
            $table->foreignId('shipped_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('shipped_at')->nullable();
            $table->json('marketplace_data')->nullable();
            $table->timestamps();

            $table->index('order_id');
            $table->index('tracking_number');
            $table->index('easypost_shipment_id');
            $table->index('status');
            $table->index('carrier');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipments');
    }
};
