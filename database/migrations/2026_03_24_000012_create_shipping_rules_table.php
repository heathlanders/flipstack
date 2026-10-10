<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipping_rules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('organization_id');
            $table->string('name', 255);
            $table->integer('priority')->default(0);
            $table->jsonb('conditions')->default('{}');
            $table->string('action_carrier', 50);
            $table->string('action_service', 100);
            $table->uuid('action_package_preset_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('organization_id')
                ->references('id')
                ->on('organizations')
                ->cascadeOnDelete();

            $table->foreign('action_package_preset_id')
                ->references('id')
                ->on('package_presets')
                ->nullOnDelete();

            $table->index(['organization_id', 'is_active', 'priority']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipping_rules');
    }
};
