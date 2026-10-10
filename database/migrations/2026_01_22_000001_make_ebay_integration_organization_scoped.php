<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Make team_id nullable on integration_credentials
        Schema::table('integration_credentials', function (Blueprint $table) {
            $table->unsignedBigInteger('team_id')->nullable()->change();
        });

        // Make team_id nullable on oauth_tokens and add organization_id
        Schema::table('oauth_tokens', function (Blueprint $table) {
            $table->unsignedBigInteger('team_id')->nullable()->change();
            $table->unsignedBigInteger('organization_id')->nullable()->after('id');
            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
        });

        // Populate organization_id on integration_credentials from teams
        DB::statement('
            UPDATE integration_credentials
            SET organization_id = (
                SELECT teams.organization_id FROM teams WHERE teams.id = integration_credentials.team_id
            )
            WHERE integration_credentials.team_id IS NOT NULL
              AND integration_credentials.organization_id IS NULL
        ');

        // Populate organization_id on oauth_tokens from teams
        DB::statement('
            UPDATE oauth_tokens
            SET organization_id = (
                SELECT teams.organization_id FROM teams WHERE teams.id = oauth_tokens.team_id
            )
            WHERE oauth_tokens.team_id IS NOT NULL
              AND oauth_tokens.organization_id IS NULL
        ');

        // Drop old unique indexes and add new organization-scoped ones
        Schema::table('integration_credentials', function (Blueprint $table) {
            $table->dropUnique('integration_credentials_team_id_platform_environment_unique');
            $table->unique(['organization_id', 'platform', 'environment'], 'integration_credentials_org_platform_env_unique');
        });

        Schema::table('oauth_tokens', function (Blueprint $table) {
            $table->dropUnique('oauth_tokens_unique');
            $table->unique(['organization_id', 'user_id', 'platform', 'environment'], 'oauth_tokens_org_user_platform_env_unique');
        });
    }

    public function down(): void
    {
        Schema::table('oauth_tokens', function (Blueprint $table) {
            $table->dropUnique('oauth_tokens_org_user_platform_env_unique');
            $table->unique(['team_id', 'user_id', 'platform', 'environment'], 'oauth_tokens_unique');
            $table->dropForeign(['organization_id']);
            $table->dropColumn('organization_id');
            $table->unsignedBigInteger('team_id')->nullable(false)->change();
        });

        Schema::table('integration_credentials', function (Blueprint $table) {
            $table->dropUnique('integration_credentials_org_platform_env_unique');
            $table->unique(['team_id', 'platform', 'environment'], 'integration_credentials_team_id_platform_environment_unique');
            $table->unsignedBigInteger('team_id')->nullable(false)->change();
        });
    }
};
