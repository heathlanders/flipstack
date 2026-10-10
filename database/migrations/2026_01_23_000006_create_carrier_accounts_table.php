<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('carrier_accounts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('carrier', 50); // USPS, UPS, FedEx, DHL
            $table->string('account_name');
            $table->string('easypost_carrier_id')->nullable();
            $table->json('credentials')->nullable(); // encrypted at app level
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->index(['organization_id', 'carrier']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('carrier_accounts');
    }
};
