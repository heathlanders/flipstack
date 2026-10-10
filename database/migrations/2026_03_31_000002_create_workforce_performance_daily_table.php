<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workforce_performance_daily', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('user_id');
            $table->date('performance_date');
            $table->integer('orders_picked')->default(0);
            $table->integer('items_picked')->default(0);
            $table->integer('pick_errors')->default(0);
            $table->integer('avg_pick_duration_seconds')->nullable();
            $table->integer('orders_packed')->default(0);
            $table->integer('items_packed')->default(0);
            $table->integer('pack_errors')->default(0);
            $table->integer('avg_pack_duration_seconds')->nullable();
            $table->integer('labels_purchased')->default(0);
            $table->integer('shipments_created')->default(0);
            $table->integer('returns_processed')->default(0);
            $table->integer('total_active_seconds')->default(0);
            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();

            $table->unique(['organization_id', 'user_id', 'performance_date']);
            $table->index(['organization_id', 'performance_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workforce_performance_daily');
    }
};
