<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->onDelete('cascade');
            $table->string('category'); // e.g., 'general', 'notifications', 'warehouse', etc.
            $table->string('key');
            $table->text('value')->nullable();
            $table->string('type')->default('string'); // string, boolean, integer, json, encrypted
            $table->text('description')->nullable();
            $table->boolean('is_public')->default(false); // Can non-admin users see this?
            $table->timestamps();

            $table->unique(['team_id', 'category', 'key']);
            $table->index(['team_id', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_settings');
    }
};
