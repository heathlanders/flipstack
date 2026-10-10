<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('package_presets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->enum('type', ['box', 'envelope', 'soft_pack', 'custom'])->default('box');
            $table->decimal('length', 6, 2);
            $table->decimal('width', 6, 2);
            $table->decimal('height', 6, 2);
            $table->decimal('weight_oz', 8, 2)->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index('organization_id');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('package_presets');
    }
};
