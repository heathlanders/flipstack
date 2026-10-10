<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->string('scope')->default('organization'); // platform, organization, team
            $table->boolean('is_system')->default(false); // System roles can't be deleted
            $table->json('permissions');
            $table->timestamps();

            $table->unique(['slug', 'scope']);
            $table->index('scope');
        });

        Schema::create('user_roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organization_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('team_id')->nullable()->constrained()->cascadeOnDelete();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'role_id', 'organization_id', 'team_id']);
            $table->index(['user_id', 'organization_id']);
        });

        Schema::create('organization_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invited_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('email');
            $table->json('roles'); // Array of role IDs
            $table->string('token')->unique();
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();

            $table->index(['email', 'organization_id']);
            $table->index('token');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_invitations');
        Schema::dropIfExists('user_roles');
        Schema::dropIfExists('roles');
    }
};
