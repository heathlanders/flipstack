<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketplace_fees', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('organization_id');
            $table->uuid('order_id')->nullable();
            $table->uuid('order_item_id')->nullable();
            $table->string('listing_type', 50)->nullable();
            $table->uuid('listing_id')->nullable();
            $table->string('marketplace', 30);
            $table->string('fee_type', 50);
            $table->decimal('amount', 12, 2)->default(0);
            $table->string('currency', 5)->default('USD');
            $table->string('description')->nullable();
            $table->string('marketplace_fee_id')->nullable();
            $table->date('fee_date')->nullable();
            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->foreign('order_id')->references('id')->on('orders')->onDelete('set null');

            $table->index(['organization_id', 'marketplace', 'fee_date']);
            $table->index(['order_id']);
            $table->index(['listing_type', 'listing_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_fees');
    }
};
