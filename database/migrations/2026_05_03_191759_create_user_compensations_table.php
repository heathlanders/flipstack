<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_compensations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->decimal('hourly_rate', 10, 2);

            // Optional per-task time estimates (only used when per-task tracking
            // isn't available). Defaults are reasonable starting points; admins
            // tune in settings.
            $table->unsignedInteger('minutes_per_listing')->default(10);
            $table->unsignedInteger('minutes_per_ticket')->default(5);

            $table->date('effective_from');
            $table->date('effective_to')->nullable();

            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'user_id', 'effective_from']);
            $table->index(['organization_id', 'effective_from', 'effective_to']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_compensations');
    }
};
