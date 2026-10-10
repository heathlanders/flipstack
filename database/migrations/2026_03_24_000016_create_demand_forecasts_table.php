<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('demand_forecasts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('organization_id');
            $table->uuid('product_id');
            $table->date('forecast_date');
            $table->integer('forecasted_quantity');
            $table->integer('actual_quantity')->nullable();
            $table->string('confidence_level', 10)->default('medium');
            $table->string('algorithm', 30)->default('moving_average');
            $table->jsonb('parameters')->default('{}');
            $table->timestamp('generated_at');
            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();

            $table->index(['organization_id', 'product_id']);
            $table->index('forecast_date');
        });

        Schema::create('reorder_suggestions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('organization_id');
            $table->uuid('product_id');
            $table->uuid('supplier_id')->nullable();
            $table->integer('suggested_quantity');
            $table->string('reason', 50);
            $table->date('estimated_stockout_date')->nullable();
            $table->string('priority', 10)->default('medium');
            $table->string('status', 20)->default('pending');
            $table->timestamp('accepted_at')->nullable();
            $table->uuid('purchase_order_id')->nullable();
            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
            $table->foreign('supplier_id')->references('id')->on('suppliers')->nullOnDelete();

            $table->index(['organization_id', 'status']);
            $table->index('priority');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reorder_suggestions');
        Schema::dropIfExists('demand_forecasts');
    }
};
