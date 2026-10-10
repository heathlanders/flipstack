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
        Schema::create('supplier_notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('supplier_id');
            $table->unsignedBigInteger('organization_id');

            // Notification Content
            $table->string('title', 255);
            $table->text('message');
            $table->text('short_message')->nullable();

            // Type & Category
            $table->enum('type', [
                'info',
                'success',
                'warning',
                'error',
                'action_required',
            ])->default('info');

            $table->enum('category', [
                'tier_change',
                'settlement',
                'payment',
                'submission',
                'approval',
                'rejection',
                'contract',
                'document',
                'performance',
                'system',
                'reminder',
                'promotional',
            ])->default('system');

            // Priority
            $table->enum('priority', ['low', 'normal', 'high', 'urgent'])->default('normal');

            // Related Entity
            $table->string('related_type')->nullable()->comment('Model class name');
            $table->uuid('related_id')->nullable();

            // Action
            $table->string('action_url')->nullable();
            $table->string('action_text')->nullable();

            // Delivery Status
            $table->boolean('is_read')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->boolean('email_sent')->default(false);
            $table->timestamp('email_sent_at')->nullable();
            $table->boolean('sms_sent')->default(false);
            $table->timestamp('sms_sent_at')->nullable();
            $table->boolean('push_sent')->default(false);
            $table->timestamp('push_sent_at')->nullable();

            // Scheduling
            $table->timestamp('scheduled_for')->nullable();
            $table->timestamp('expires_at')->nullable();

            // Metadata
            $table->jsonb('data')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('supplier_id')
                ->references('id')
                ->on('suppliers')
                ->onDelete('cascade');

            $table->foreign('organization_id')
                ->references('id')
                ->on('organizations')
                ->onDelete('cascade');

            $table->index(['supplier_id', 'is_read']);
            $table->index(['supplier_id', 'category']);
            $table->index(['organization_id', 'type']);
            $table->index(['scheduled_for']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('supplier_notifications');
    }
};
