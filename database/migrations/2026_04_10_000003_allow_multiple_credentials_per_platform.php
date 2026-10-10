<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. integration_credentials: drop unique constraint, add non-unique index
        Schema::table('integration_credentials', function (Blueprint $table) {
            $table->dropUnique('integration_credentials_org_platform_env_unique');
            $table->index(['organization_id', 'platform', 'environment'], 'integration_credentials_org_platform_env_index');
        });

        // Backfill NULL names with descriptive defaults
        DB::statement("
            UPDATE integration_credentials
            SET name = COALESCE(
                NULLIF(seller_username, ''),
                NULLIF(config->>'shop_domain', ''),
                INITCAP(platform) || ' ' || INITCAP(environment)
            )
            WHERE name IS NULL
        ");

        // 2. oauth_tokens: drop broad unique, add credential-scoped partial unique
        Schema::table('oauth_tokens', function (Blueprint $table) {
            $table->dropUnique('oauth_tokens_org_user_platform_env_unique');
        });

        // One active token per credential per user (partial unique index)
        DB::statement('
            CREATE UNIQUE INDEX oauth_tokens_credential_user_active_unique
            ON oauth_tokens (integration_credential_id, user_id)
            WHERE is_active = true
        ');

        // Also handle org-level tokens (user_id IS NULL) - one active per credential
        DB::statement('
            CREATE UNIQUE INDEX oauth_tokens_credential_org_active_unique
            ON oauth_tokens (integration_credential_id)
            WHERE is_active = true AND user_id IS NULL
        ');

        // 3. ebay_listings: enforce one active listing per product across all eBay accounts
        Schema::table('ebay_listings', function (Blueprint $table) {
            $table->dropUnique('ebay_product_credential_unique');
        });

        DB::statement("
            CREATE UNIQUE INDEX ebay_listings_product_active_unique
            ON ebay_listings (product_id)
            WHERE status NOT IN ('deleted', 'ended')
        ");

        // 4. shopify_listings: same pattern
        Schema::table('shopify_listings', function (Blueprint $table) {
            $table->dropUnique('shopify_product_credential_unique');
        });

        DB::statement("
            CREATE UNIQUE INDEX shopify_listings_product_active_unique
            ON shopify_listings (product_id)
            WHERE status NOT IN ('deleted', 'archived')
        ");

        // 5. amazon_listings: same pattern
        Schema::table('amazon_listings', function (Blueprint $table) {
            $table->dropUnique('amazon_product_credential_unique');
        });

        DB::statement("
            CREATE UNIQUE INDEX amazon_listings_product_active_unique
            ON amazon_listings (product_id)
            WHERE status NOT IN ('deleted')
        ");
    }

    public function down(): void
    {
        // Reverse listing constraints
        DB::statement('DROP INDEX IF EXISTS amazon_listings_product_active_unique');
        Schema::table('amazon_listings', function (Blueprint $table) {
            $table->unique(['product_id', 'integration_credential_id'], 'amazon_product_credential_unique');
        });

        DB::statement('DROP INDEX IF EXISTS shopify_listings_product_active_unique');
        Schema::table('shopify_listings', function (Blueprint $table) {
            $table->unique(['product_id', 'integration_credential_id'], 'shopify_product_credential_unique');
        });

        DB::statement('DROP INDEX IF EXISTS ebay_listings_product_active_unique');
        Schema::table('ebay_listings', function (Blueprint $table) {
            $table->unique(['product_id', 'integration_credential_id'], 'ebay_product_credential_unique');
        });

        // Reverse oauth_tokens constraints
        DB::statement('DROP INDEX IF EXISTS oauth_tokens_credential_org_active_unique');
        DB::statement('DROP INDEX IF EXISTS oauth_tokens_credential_user_active_unique');
        Schema::table('oauth_tokens', function (Blueprint $table) {
            $table->unique(['organization_id', 'user_id', 'platform', 'environment'], 'oauth_tokens_org_user_platform_env_unique');
        });

        // Reverse integration_credentials constraints
        Schema::table('integration_credentials', function (Blueprint $table) {
            $table->dropIndex('integration_credentials_org_platform_env_index');
            $table->unique(['organization_id', 'platform', 'environment'], 'integration_credentials_org_platform_env_unique');
        });
    }
};
