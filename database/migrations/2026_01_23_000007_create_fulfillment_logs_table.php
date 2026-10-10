<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fulfillment_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('action', 100);
            $table->string('loggable_type')->nullable();
            $table->uuid('loggable_id')->nullable();
            $table->foreignUuid('order_id')->nullable()->constrained()->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamp('created_at');

            $table->index(['organization_id', 'created_at']);
            $table->index('order_id');
            $table->index(['user_id', 'created_at']);
            $table->index('action');
            $table->index(['loggable_type', 'loggable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fulfillment_logs');
    }
};
