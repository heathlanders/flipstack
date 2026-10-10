<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_fulfillment_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('order_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('supplier_id')->constrained()->cascadeOnDelete();
            $table->string('status', 50)->default('pending'); // pending, accepted, processing, shipped, completed, rejected, escalated, cancelled
            $table->timestamp('sla_deadline');
            $table->string('tracking_number', 100)->nullable();
            $table->string('tracking_carrier', 50)->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->text('supplier_notes')->nullable();
            $table->string('escalation_reason')->nullable();
            $table->timestamp('escalated_at')->nullable();
            $table->timestamps();

            $table->index(['supplier_id', 'status']);
            $table->index('order_id');
            $table->index('sla_deadline');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_fulfillment_requests');
    }
};
