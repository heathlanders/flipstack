<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('event_key');
            $table->boolean('active')->default(true);
            $table->string('priority_override')->nullable();
            $table->json('channel_overrides')->nullable();
            $table->unsignedInteger('escalation_minutes')->nullable();
            $table->unsignedInteger('dedup_minutes')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'event_key']);
            $table->index(['organization_id', 'active']);
        });

        Schema::create('notification_rule_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rule_id')->constrained('notification_rules')->cascadeOnDelete();
            $table->string('recipient_type');
            $table->string('recipient_value');
            $table->unsignedInteger('escalation_step')->default(0);
            $table->timestamps();

            $table->index(['rule_id', 'escalation_step']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_rule_recipients');
        Schema::dropIfExists('notification_rules');
    }
};
