<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_reconciliation_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organization_id');
            $table->uuid('product_id');
            $table->string('marketplace', 20);
            $table->unsignedBigInteger('integration_credential_id');
            $table->integer('expected_qty');
            $table->integer('listing_qty');
            $table->integer('discrepancy');
            $table->boolean('auto_corrected')->default(false);
            $table->boolean('resolved')->default(false);
            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
            $table->foreign('integration_credential_id')->references('id')->on('integration_credentials')->cascadeOnDelete();

            $table->index(['organization_id', 'resolved', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_reconciliation_logs');
    }
};
