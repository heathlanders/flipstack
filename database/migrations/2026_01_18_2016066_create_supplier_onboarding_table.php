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
        Schema::create('supplier_onboarding', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('supplier_id');
            $table->unsignedBigInteger('organization_id');

            // Overall Status
            $table->enum('status', [
                'not_started',
                'in_progress',
                'pending_review',
                'approved',
                'rejected',
                'completed',
                'cancelled',
            ])->default('not_started');

            // Step 1: Application
            $table->boolean('step1_application_complete')->default(false);
            $table->timestamp('step1_completed_at')->nullable();
            $table->jsonb('step1_data')->nullable();

            // Step 2: Documentation
            $table->boolean('step2_documentation_complete')->default(false);
            $table->timestamp('step2_completed_at')->nullable();
            $table->jsonb('step2_data')->nullable();
            $table->boolean('business_license_verified')->default(false);
            $table->boolean('tax_documents_verified')->default(false);
            $table->boolean('insurance_verified')->default(false);
            $table->boolean('identity_verified')->default(false);

            // Step 3: Commission Setup
            $table->boolean('step3_commission_complete')->default(false);
            $table->timestamp('step3_completed_at')->nullable();
            $table->jsonb('step3_data')->nullable();
            $table->uuid('assigned_commission_structure_id')->nullable();
            $table->string('settlement_preference', 50)->nullable();

            // Step 4: Portal Access
            $table->boolean('step4_portal_complete')->default(false);
            $table->timestamp('step4_completed_at')->nullable();
            $table->jsonb('step4_data')->nullable();
            $table->boolean('portal_credentials_sent')->default(false);
            $table->timestamp('portal_first_login')->nullable();
            $table->boolean('tutorial_completed')->default(false);

            // Step 5: First Submission
            $table->boolean('step5_submission_complete')->default(false);
            $table->timestamp('step5_completed_at')->nullable();
            $table->jsonb('step5_data')->nullable();
            $table->uuid('first_submission_id')->nullable();
            $table->boolean('submission_approved')->default(false);

            // Progress Tracking
            $table->integer('current_step')->default(1);
            $table->integer('completion_percentage')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();

            // Review Information
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_notes')->nullable();
            $table->text('rejection_reason')->nullable();

            // Assigned Handler
            $table->unsignedBigInteger('assigned_to')->nullable();
            $table->timestamp('assigned_at')->nullable();

            // Communication
            $table->integer('reminder_count')->default(0);
            $table->timestamp('last_reminder_sent_at')->nullable();
            $table->timestamp('next_reminder_at')->nullable();

            // Notes
            $table->text('internal_notes')->nullable();
            $table->jsonb('checklist_items')->nullable();

            $table->timestamps();

            $table->foreign('supplier_id')
                ->references('id')
                ->on('suppliers')
                ->onDelete('cascade');

            $table->foreign('organization_id')
                ->references('id')
                ->on('organizations')
                ->onDelete('cascade');

            $table->foreign('assigned_commission_structure_id')
                ->references('id')
                ->on('commission_structures')
                ->onDelete('set null');

            $table->foreign('reviewed_by')
                ->references('id')
                ->on('users')
                ->onDelete('set null');

            $table->foreign('assigned_to')
                ->references('id')
                ->on('users')
                ->onDelete('set null');

            $table->unique('supplier_id');
            $table->index(['organization_id', 'status']);
            $table->index(['assigned_to', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('supplier_onboarding');
    }
};
