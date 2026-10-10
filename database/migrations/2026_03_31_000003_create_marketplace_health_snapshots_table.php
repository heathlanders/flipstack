<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketplace_health_snapshots', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('organization_id');
            $table->string('marketplace');
            $table->date('snapshot_date');
            $table->integer('total_listings')->default(0);
            $table->integer('active_listings')->default(0);
            $table->integer('error_listings')->default(0);
            $table->integer('sync_attempts')->default(0);
            $table->integer('sync_successes')->default(0);
            $table->integer('sync_failures')->default(0);
            $table->integer('avg_response_time_ms')->nullable();
            $table->integer('p95_response_time_ms')->nullable();
            $table->decimal('total_revenue', 12, 2)->default(0);
            $table->decimal('total_fees', 12, 2)->default(0);
            $table->integer('orders_imported')->default(0);
            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();

            $table->unique(['organization_id', 'marketplace', 'snapshot_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_health_snapshots');
    }
};
