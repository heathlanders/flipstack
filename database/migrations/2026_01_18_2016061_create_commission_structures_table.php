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
        Schema::create('commission_structures', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('organization_id');
            $table->uuid('supplier_id')->nullable()->comment('NULL = default org structure');
            $table->uuid('category_id')->nullable()->comment('NULL = applies to all categories');
            $table->string('name', 255);
            $table->string('code', 50)->nullable();
            $table->text('description')->nullable();

            // Commission Type
            $table->enum('type', [
                'percentage',
                'flat_fee',
                'tiered',
                'category_based',
                'hybrid',
                'volume_based',
                'custom',
            ])->default('percentage');

            // Base Commission Settings
            $table->decimal('base_percentage', 5, 2)->nullable()->comment('Base commission percentage');
            $table->decimal('flat_fee_amount', 10, 2)->nullable()->comment('Fixed dollar amount');
            $table->decimal('minimum_commission', 10, 2)->nullable()->comment('Minimum commission per item');
            $table->decimal('maximum_commission', 10, 2)->nullable()->comment('Maximum commission cap');

            // Advanced Settings (JSONB for flexibility)
            $table->jsonb('tier_adjustments')->nullable()->comment('Per-tier commission adjustments');
            $table->jsonb('category_overrides')->nullable()->comment('Category-specific rules');
            $table->jsonb('volume_bonuses')->nullable()->comment('Volume-based bonuses');
            $table->jsonb('price_tiers')->nullable()->comment('Price-based tier rules');
            $table->jsonb('condition_adjustments')->nullable()->comment('Product condition adjustments');
            $table->jsonb('custom_rules')->nullable()->comment('Complex custom logic');
            $table->jsonb('exclusions')->nullable()->comment('Excluded categories/conditions');

            // Validity Period
            $table->boolean('is_active')->default(true);
            $table->timestamp('effective_date')->nullable();
            $table->timestamp('expires_at')->nullable();

            // Priority for rule application
            $table->integer('priority')->default(0)->comment('Higher priority takes precedence');

            // Audit
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('organization_id')
                ->references('id')
                ->on('organizations')
                ->onDelete('cascade');

            $table->foreign('supplier_id')
                ->references('id')
                ->on('suppliers')
                ->onDelete('cascade');

            $table->foreign('category_id')
                ->references('id')
                ->on('categories')
                ->onDelete('set null');

            $table->foreign('created_by')
                ->references('id')
                ->on('users')
                ->onDelete('set null');

            $table->foreign('updated_by')
                ->references('id')
                ->on('users')
                ->onDelete('set null');

            $table->index(['organization_id', 'is_active']);
            $table->index(['supplier_id', 'is_active']);
            $table->index(['organization_id', 'type']);
            $table->index(['effective_date', 'expires_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('commission_structures');
    }
};
