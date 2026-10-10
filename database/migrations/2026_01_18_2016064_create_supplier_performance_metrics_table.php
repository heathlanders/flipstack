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
        Schema::create('supplier_performance_metrics', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('supplier_id');
            $table->unsignedBigInteger('organization_id');

            // Evaluation Period
            $table->timestamp('evaluation_period_start');
            $table->timestamp('evaluation_period_end');
            $table->string('period_type', 50)->default('monthly');

            // Submission Metrics
            $table->integer('total_submissions')->default(0);
            $table->integer('approved_submissions')->default(0);
            $table->integer('rejected_submissions')->default(0);
            $table->integer('pending_submissions')->default(0);
            $table->decimal('approval_rate', 5, 2)->default(0);

            // Sales Metrics
            $table->integer('total_items_listed')->default(0);
            $table->integer('total_items_sold')->default(0);
            $table->decimal('sell_through_rate', 5, 2)->default(0);
            $table->decimal('total_revenue', 12, 2)->default(0);
            $table->decimal('average_sale_price', 10, 2)->default(0);
            $table->decimal('average_days_to_sell', 8, 2)->nullable();

            // Return Metrics
            $table->integer('total_returns')->default(0);
            $table->decimal('return_rate', 5, 2)->default(0);
            $table->decimal('return_value', 12, 2)->default(0);

            // Quality Metrics
            $table->decimal('quality_score', 5, 2)->default(0)->comment('Based on AI analysis and manual review');
            $table->decimal('accuracy_score', 5, 2)->default(0)->comment('Description accuracy');
            $table->decimal('condition_accuracy', 5, 2)->default(0);
            $table->decimal('packaging_score', 5, 2)->default(0);

            // Compliance Metrics
            $table->decimal('compliance_score', 5, 2)->default(0);
            $table->integer('policy_violations')->default(0);
            $table->integer('late_submissions')->default(0);
            $table->integer('documentation_issues')->default(0);

            // Customer Satisfaction
            $table->decimal('customer_rating', 3, 2)->default(0);
            $table->integer('customer_complaints')->default(0);
            $table->integer('positive_feedback_count')->default(0);
            $table->integer('negative_feedback_count')->default(0);

            // Overall Score Calculation
            $table->decimal('overall_performance_score', 5, 2)->default(0);
            $table->jsonb('score_breakdown')->nullable()->comment('Detailed score components');
            $table->jsonb('score_weights')->nullable()->comment('Weights used for calculation');

            // Tier Information
            $table->uuid('current_tier_id')->nullable();
            $table->uuid('previous_tier_id')->nullable();
            $table->timestamp('tier_change_date')->nullable();
            $table->string('tier_change_reason', 255)->nullable();
            $table->boolean('tier_change_pending')->default(false);

            // Recommendations
            $table->jsonb('improvement_recommendations')->nullable();
            $table->jsonb('strengths')->nullable();
            $table->jsonb('weaknesses')->nullable();

            // Status
            $table->enum('status', [
                'draft',
                'calculated',
                'reviewed',
                'finalized',
            ])->default('draft');

            $table->unsignedBigInteger('calculated_by')->nullable();
            $table->timestamp('calculated_at')->nullable();
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();

            $table->timestamps();

            $table->foreign('supplier_id')
                ->references('id')
                ->on('suppliers')
                ->onDelete('cascade');

            $table->foreign('organization_id')
                ->references('id')
                ->on('organizations')
                ->onDelete('cascade');

            $table->foreign('current_tier_id')
                ->references('id')
                ->on('supplier_tiers')
                ->onDelete('set null');

            $table->foreign('previous_tier_id')
                ->references('id')
                ->on('supplier_tiers')
                ->onDelete('set null');

            $table->foreign('calculated_by')
                ->references('id')
                ->on('users')
                ->onDelete('set null');

            $table->foreign('reviewed_by')
                ->references('id')
                ->on('users')
                ->onDelete('set null');

            $table->unique(['supplier_id', 'evaluation_period_start', 'evaluation_period_end'], 'unique_supplier_period');
            $table->index(['organization_id', 'evaluation_period_start']);
            $table->index(['supplier_id', 'status']);
            $table->index(['overall_performance_score']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('supplier_performance_metrics');
    }
};
