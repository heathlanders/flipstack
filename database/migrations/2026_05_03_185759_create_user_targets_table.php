<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_targets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();

            // Scope: 'user' (scope_id = users.id), 'role' (scope_id = roles.id),
            // 'team' (scope_id = teams.id), or 'organization' (scope_id null = applies to everyone)
            $table->string('scope_type', 20);
            $table->string('scope_id')->nullable();

            // Metric key — must match a column in workforce_performance_daily
            // (items_picked, items_packed, shipments_created, returns_processed,
            // pick_errors, pack_errors) or one of the derived rates.
            $table->string('metric', 50);

            // Period the target value applies to: 'daily', 'weekly', 'monthly'
            $table->string('period', 20)->default('daily');

            $table->decimal('target_value', 12, 2);

            $table->date('effective_from');
            $table->date('effective_to')->nullable();

            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'scope_type', 'metric']);
            $table->index(['organization_id', 'effective_from', 'effective_to']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_targets');
    }
};
