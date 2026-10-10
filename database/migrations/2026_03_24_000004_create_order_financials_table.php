<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_financials', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('organization_id');
            $table->uuid('order_id')->unique();
            $table->decimal('revenue', 12, 2)->default(0);
            $table->decimal('cost_of_goods', 12, 2)->default(0);
            $table->decimal('marketplace_fees', 12, 2)->default(0);
            $table->decimal('shipping_label_cost', 12, 2)->default(0);
            $table->decimal('shipping_charged', 12, 2)->default(0);
            $table->decimal('tax_collected', 12, 2)->default(0);
            $table->decimal('net_profit', 12, 2)->default(0);
            $table->decimal('profit_margin_pct', 8, 2)->default(0);
            $table->timestamp('calculated_at');
            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->foreign('order_id')->references('id')->on('orders')->onDelete('cascade');

            $table->index(['organization_id', 'calculated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_financials');
    }
};
