<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('oauth_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('cascade'); // Null = team-level token
            $table->foreignId('integration_credential_id')->constrained()->onDelete('cascade');
            $table->string('platform'); // 'ebay', 'amazon', etc.
            $table->string('environment'); // 'sandbox' or 'production'
            $table->text('access_token'); // Encrypted
            $table->text('refresh_token')->nullable(); // Encrypted
            $table->timestamp('expires_at')->nullable();
            $table->json('scopes')->nullable(); // OAuth scopes granted
            $table->json('metadata')->nullable(); // Platform-specific data (e.g., eBay user ID)
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_refreshed_at')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            // A team can have one team-level token per platform/environment
            // A user can have one user-level token per platform/environment
            $table->unique(['team_id', 'user_id', 'platform', 'environment'], 'oauth_tokens_unique');
            $table->index(['team_id', 'platform', 'environment']);
            $table->index(['expires_at', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('oauth_tokens');
    }
};
