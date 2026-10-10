<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integration_credentials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->onDelete('cascade');
            $table->string('platform'); // 'ebay', 'amazon', 'newegg', etc.
            $table->string('environment')->default('production'); // 'sandbox' or 'production'
            $table->string('name')->nullable(); // User-friendly name
            $table->text('client_id'); // Encrypted
            $table->text('client_secret'); // Encrypted
            $table->json('config')->nullable(); // Platform-specific configuration
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_verified_at')->nullable();
            $table->timestamps();

            $table->unique(['team_id', 'platform', 'environment']);
            $table->index(['team_id', 'platform']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_credentials');
    }
};
