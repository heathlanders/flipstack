<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_kpi_snapshots', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('organization_id');
            $table->date('snapshot_date');
            $table->string('category');
            $table->jsonb('metrics')->default('{}');
            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();

            $table->unique(['organization_id', 'snapshot_date', 'category']);
            $table->index(['organization_id', 'category', 'snapshot_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_kpi_snapshots');
    }
};
