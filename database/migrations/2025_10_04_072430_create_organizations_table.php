<?php

// database/migrations/2024_01_01_000001_create_organizations_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('subdomain')->unique()->nullable();
            $table->string('email');
            $table->string('phone')->nullable();
            $table->text('description')->nullable();
            $table->json('settings')->nullable();
            $table->json('branding')->nullable(); // Logo, colors, etc.
            $table->string('timezone')->default('UTC');
            $table->string('currency', 3)->default('USD');
            $table->string('status')->default('active'); // active, suspended, trial
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('suspended_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'deleted_at']);
            $table->index('subdomain');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organizations');
    }
};
