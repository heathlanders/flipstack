<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saved_reports', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->string('name');
            // view = analytics page key: workforce, fulfillment, executive, compensation, user_detail, etc.
            $table->string('view', 50);
            $table->jsonb('filters')->nullable();
            $table->boolean('is_shared')->default(false);
            $table->timestamps();

            $table->index(['organization_id', 'owner_id']);
            $table->index(['organization_id', 'view']);
        });

        Schema::create('scheduled_reports', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->string('name');
            $table->string('view', 50);
            $table->jsonb('filters')->nullable();
            // 'csv', 'xlsx', 'pdf'
            $table->string('format', 10)->default('csv');
            // 'daily', 'weekly', 'monthly'
            $table->string('frequency', 20)->default('weekly');
            // 0-6 for weekly (0=Sunday), 1-31 for monthly, ignored for daily
            $table->unsignedTinyInteger('day_of_period')->nullable();
            // HH:MM in org timezone (defaults to UTC); job is dispatched in a wider window so this is approximate
            $table->string('send_time', 5)->default('07:00');
            $table->jsonb('recipients');
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_sent_at')->nullable();
            $table->timestamp('next_due_at')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'is_active']);
            $table->index('next_due_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scheduled_reports');
        Schema::dropIfExists('saved_reports');
    }
};
