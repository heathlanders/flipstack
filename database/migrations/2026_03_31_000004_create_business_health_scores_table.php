<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_health_scores', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('organization_id');
            $table->date('score_date');
            $table->decimal('overall_score', 5, 2);
            $table->jsonb('dimensions')->default('{}');
            $table->jsonb('dimension_details')->default('{}');
            $table->jsonb('alerts')->default('[]');
            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();

            $table->unique(['organization_id', 'score_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_health_scores');
    }
};
