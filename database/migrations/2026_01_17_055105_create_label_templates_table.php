<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('label_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('name');
            $table->string('description', 500)->nullable();
            $table->decimal('width_mm', 6, 2);
            $table->decimal('height_mm', 6, 2);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->json('template_data');
            $table->integer('usage_count')->default(0);
            $table->timestamps();

            $table->index(['organization_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('label_templates');
    }
};
