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
        Schema::table('suppliers', function (Blueprint $table) {
            // Company/Business Details
            $table->string('company_name', 255)->nullable()->after('name');
            $table->string('business_type', 100)->nullable()->after('company_name');
            $table->string('contact_name', 255)->nullable()->after('business_type');

            // Supplier Status (enum for workflow states)
            $table->string('supplier_status', 50)->default('pending')->after('is_preferred');
            $table->text('status_reason')->nullable()->after('supplier_status');
            $table->timestamp('status_changed_at')->nullable()->after('status_reason');
            $table->unsignedBigInteger('status_changed_by')->nullable()->after('status_changed_at');

            // Tier Relationship
            $table->uuid('current_tier_id')->nullable()->after('status_changed_by');
            $table->timestamp('tier_assigned_at')->nullable()->after('current_tier_id');
            $table->timestamp('tier_evaluation_due_at')->nullable()->after('tier_assigned_at');

            // Commission Structure Relationship
            $table->uuid('commission_structure_id')->nullable()->after('tier_evaluation_due_at');
            $table->decimal('custom_commission_rate', 5, 2)->nullable()->after('commission_structure_id');

            // Settlement Schedule
            $table->string('settlement_schedule', 50)->default('monthly')->after('custom_commission_rate');
            $table->integer('settlement_day')->nullable()->after('settlement_schedule');
            $table->date('next_settlement_date')->nullable()->after('settlement_day');

            // Financial Tracking
            $table->decimal('total_sales_ytd', 15, 2)->default(0)->after('next_settlement_date');
            $table->decimal('total_sales_all_time', 15, 2)->default(0)->after('total_sales_ytd');
            $table->decimal('total_commissions_paid', 15, 2)->default(0)->after('total_sales_all_time');
            $table->decimal('pending_balance', 15, 2)->default(0)->after('total_commissions_paid');
            $table->decimal('available_balance', 15, 2)->default(0)->after('pending_balance');

            // Performance Caching (for dashboard display without recalculation)
            $table->decimal('cached_performance_score', 5, 2)->nullable()->after('available_balance');
            $table->integer('cached_quality_score')->nullable()->after('cached_performance_score');
            $table->integer('cached_fulfillment_score')->nullable()->after('cached_quality_score');
            $table->integer('cached_volume_score')->nullable()->after('cached_fulfillment_score');
            $table->timestamp('performance_calculated_at')->nullable()->after('cached_volume_score');

            // Portal Access
            $table->unsignedBigInteger('user_id')->nullable()->after('performance_calculated_at');
            $table->boolean('portal_enabled')->default(false)->after('user_id');
            $table->timestamp('portal_activated_at')->nullable()->after('portal_enabled');
            $table->timestamp('last_portal_login_at')->nullable()->after('portal_activated_at');

            // Onboarding Status
            $table->boolean('onboarding_complete')->default(false)->after('last_portal_login_at');
            $table->timestamp('onboarding_completed_at')->nullable()->after('onboarding_complete');

            // Payment Information
            $table->string('payment_method', 50)->nullable()->after('onboarding_completed_at');
            $table->jsonb('payment_info')->nullable()->after('payment_method');
            $table->string('bank_account_last4', 4)->nullable()->after('payment_info');

            // Documents
            $table->boolean('w9_on_file')->default(false)->after('bank_account_last4');
            $table->boolean('contract_signed')->default(false)->after('w9_on_file');
            $table->date('contract_expires_at')->nullable()->after('contract_signed');

            // Foreign Keys
            $table->foreign('status_changed_by')
                ->references('id')
                ->on('users')
                ->onDelete('set null');

            $table->foreign('current_tier_id')
                ->references('id')
                ->on('supplier_tiers')
                ->onDelete('set null');

            $table->foreign('commission_structure_id')
                ->references('id')
                ->on('commission_structures')
                ->onDelete('set null');

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onDelete('set null');

            // Indexes for performance
            $table->index(['organization_id', 'supplier_status']);
            $table->index(['organization_id', 'current_tier_id']);
            $table->index(['organization_id', 'cached_performance_score']);
            $table->index('next_settlement_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            // Drop foreign keys first
            $table->dropForeign(['status_changed_by']);
            $table->dropForeign(['current_tier_id']);
            $table->dropForeign(['commission_structure_id']);
            $table->dropForeign(['user_id']);

            // Drop indexes
            $table->dropIndex(['organization_id', 'supplier_status']);
            $table->dropIndex(['organization_id', 'current_tier_id']);
            $table->dropIndex(['organization_id', 'cached_performance_score']);
            $table->dropIndex(['next_settlement_date']);

            // Drop columns
            $table->dropColumn([
                'company_name',
                'business_type',
                'contact_name',
                'supplier_status',
                'status_reason',
                'status_changed_at',
                'status_changed_by',
                'current_tier_id',
                'tier_assigned_at',
                'tier_evaluation_due_at',
                'commission_structure_id',
                'custom_commission_rate',
                'settlement_schedule',
                'settlement_day',
                'next_settlement_date',
                'total_sales_ytd',
                'total_sales_all_time',
                'total_commissions_paid',
                'pending_balance',
                'available_balance',
                'cached_performance_score',
                'cached_quality_score',
                'cached_fulfillment_score',
                'cached_volume_score',
                'performance_calculated_at',
                'user_id',
                'portal_enabled',
                'portal_activated_at',
                'last_portal_login_at',
                'onboarding_complete',
                'onboarding_completed_at',
                'payment_method',
                'payment_info',
                'bank_account_last4',
                'w9_on_file',
                'contract_signed',
                'contract_expires_at',
            ]);
        });
    }
};
