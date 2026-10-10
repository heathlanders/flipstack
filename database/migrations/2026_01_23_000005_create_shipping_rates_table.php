<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipping_rates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('order_id')->constrained()->cascadeOnDelete();
            $table->string('easypost_rate_id');
            $table->string('carrier', 50);
            $table->string('service', 100);
            $table->decimal('rate', 8, 2);
            $table->string('currency', 10)->default('USD');
            $table->unsignedInteger('estimated_days')->nullable();
            $table->date('delivery_date')->nullable();
            $table->boolean('delivery_guarantee')->default(false);
            $table->boolean('selected')->default(false);
            $table->timestamp('expires_at');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('order_id');
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipping_rates');
    }
};
