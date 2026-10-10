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
        Schema::create('supplier_tier_history', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('supplier_id');
            $table->unsignedBigInteger('organization_id');

            // Tier Change
            $table->uuid('from_tier_id')->nullable();
            $table->uuid('to_tier_id');
            $table->enum('change_type', [
                'promotion',
                'demotion',
                'manual',
                'initial',
                'reset',
            ]);

            // Reason & Metrics
            $table->text('reason')->nullable();
            $table->uuid('performance_metric_id')->nullable();
            $table->decimal('performance_score', 5, 2)->nullable();
            $table->jsonb('metrics_snapshot')->nullable();

            // Process Information
            $table->boolean('is_automated')->default(true);
            $table->unsignedBigInteger('changed_by')->nullable();
            $table->timestamp('effective_date');

            // Communication
            $table->boolean('notification_sent')->default(false);
            $table->timestamp('notification_sent_at')->nullable();

            $table->timestamps();

            $table->foreign('supplier_id')
                ->references('id')
                ->on('suppliers')
                ->onDelete('cascade');

            $table->foreign('organization_id')
                ->references('id')
                ->on('organizations')
                ->onDelete('cascade');

            $table->foreign('from_tier_id')
                ->references('id')
                ->on('supplier_tiers')
                ->onDelete('set null');

            $table->foreign('to_tier_id')
                ->references('id')
                ->on('supplier_tiers')
                ->onDelete('cascade');

            $table->foreign('performance_metric_id')
                ->references('id')
                ->on('supplier_performance_metrics')
                ->onDelete('set null');

            $table->foreign('changed_by')
                ->references('id')
                ->on('users')
                ->onDelete('set null');

            $table->index(['supplier_id', 'effective_date']);
            $table->index(['organization_id', 'change_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('supplier_tier_history');
    }
};
