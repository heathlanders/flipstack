<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable'); // ✅ This already creates the index!
            $table->json('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            // Custom additions for notification system
            $table->string('priority', 20)->default('medium');
            $table->string('category', 50)->nullable();
            $table->foreignId('tenant_id')->nullable()->constrained('organizations')->onDelete('cascade');
            $table->text('action_url')->nullable();
            $table->timestamp('expires_at')->nullable();

            // Indexes for performance
            // ❌ REMOVED: $table->index(['notifiable_type', 'notifiable_id']);
            $table->index('tenant_id');
            $table->index('read_at');
            $table->index('category');
            $table->index('priority');
            $table->index('created_at');
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
