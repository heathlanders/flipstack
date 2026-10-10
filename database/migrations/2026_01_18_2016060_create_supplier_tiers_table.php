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
        Schema::create('supplier_tiers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('organization_id');
            $table->string('tier_name', 50);
            $table->string('tier_code', 20)->unique();
            $table->text('description')->nullable();
            $table->decimal('min_performance_score', 5, 2)->default(0);
            $table->decimal('max_performance_score', 5, 2)->default(100);
            $table->decimal('commission_adjustment', 5, 2)->default(0)->comment('Percentage adjustment to base commission');
            $table->string('settlement_frequency', 50)->default('monthly');
            $table->integer('settlement_day')->nullable()->comment('Day of week/month for settlement');
            $table->jsonb('benefits')->nullable()->comment('JSON array of tier benefits');
            $table->jsonb('restrictions')->nullable()->comment('JSON array of tier restrictions');
            $table->jsonb('requirements')->nullable()->comment('Requirements to maintain tier');
            $table->integer('minimum_volume')->default(0)->comment('Minimum monthly volume requirement');
            $table->integer('evaluation_period_days')->default(30);
            $table->string('color_code', 7)->nullable()->comment('Hex color for UI display');
            $table->string('icon')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->boolean('auto_promotion_enabled')->default(true);
            $table->boolean('auto_demotion_enabled')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('organization_id')
                ->references('id')
                ->on('organizations')
                ->onDelete('cascade');

            $table->unique(['organization_id', 'tier_code']);
            $table->index(['organization_id', 'sort_order']);
            $table->index(['organization_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('supplier_tiers');
    }
};
