<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('credential_id')->nullable()->constrained('integration_credentials')->nullOnDelete();
            $table->string('marketplace', 50); // ebay, amazon, direct
            $table->string('marketplace_order_id')->unique();
            $table->string('marketplace_status', 100)->nullable();
            $table->string('status', 50)->default('pending'); // pending, assigned, picking, picked, packing, packed, shipped, in_transit, delivered, cancelled, on_hold, return_initiated, returned
            $table->string('fulfillment_mode', 50)->default('org_fulfilled'); // org_fulfilled, supplier_dropship
            $table->foreignUuid('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->foreignUuid('location_id')->nullable()->constrained('inventory_locations')->nullOnDelete();
            $table->foreignId('assigned_picker_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_packer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('priority', 20)->default('standard'); // standard, high, urgent
            $table->string('buyer_username');
            $table->string('buyer_name');
            $table->string('buyer_email')->nullable();
            $table->string('buyer_phone', 50)->nullable();
            $table->string('shipping_address_name');
            $table->string('shipping_address_line1');
            $table->string('shipping_address_line2')->nullable();
            $table->string('shipping_city', 100);
            $table->string('shipping_state', 100);
            $table->string('shipping_postal_code', 20);
            $table->string('shipping_country', 100)->default('US');
            $table->decimal('subtotal', 10, 2)->default(0);
            $table->decimal('shipping_cost', 10, 2)->default(0);
            $table->decimal('tax_amount', 10, 2)->default(0);
            $table->decimal('marketplace_fees', 10, 2)->default(0);
            $table->decimal('total_amount', 10, 2)->default(0);
            $table->string('currency', 10)->default('USD');
            $table->unsignedInteger('items_count')->default(0);
            $table->timestamp('ship_by_date')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('order_notes')->nullable();
            $table->text('internal_notes')->nullable();
            $table->json('marketplace_data')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'status']);
            $table->index(['team_id', 'status']);
            $table->index(['credential_id', 'status']);
            $table->index(['location_id', 'status']);
            $table->index('ship_by_date');
            $table->index('assigned_picker_id');
            $table->index('assigned_packer_id');
            $table->index('priority');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
