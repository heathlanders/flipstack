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
        // Add deleted_at column to roles table if it doesn't exist
        if (Schema::hasTable('roles') && ! Schema::hasColumn('roles', 'deleted_at')) {
            Schema::table('roles', function (Blueprint $table) {
                $table->softDeletes();
            });
        }

        // Add deleted_at to other tables if they exist and need it
        if (Schema::hasTable('user_roles') && ! Schema::hasColumn('user_roles', 'deleted_at')) {
            Schema::table('user_roles', function (Blueprint $table) {
                $table->softDeletes();
            });
        }

        if (Schema::hasTable('permissions') && ! Schema::hasColumn('permissions', 'deleted_at')) {
            Schema::table('permissions', function (Blueprint $table) {
                $table->softDeletes();
            });
        }

        if (Schema::hasTable('role_permissions') && ! Schema::hasColumn('role_permissions', 'deleted_at')) {
            Schema::table('role_permissions', function (Blueprint $table) {
                $table->softDeletes();
            });
        }

        if (Schema::hasTable('organization_settings') && ! Schema::hasColumn('organization_settings', 'deleted_at')) {
            Schema::table('organization_settings', function (Blueprint $table) {
                $table->softDeletes();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('roles', 'deleted_at')) {
            Schema::table('roles', function (Blueprint $table) {
                $table->dropSoftDeletes();
            });
        }

        if (Schema::hasColumn('user_roles', 'deleted_at')) {
            Schema::table('user_roles', function (Blueprint $table) {
                $table->dropSoftDeletes();
            });
        }

        if (Schema::hasColumn('permissions', 'deleted_at')) {
            Schema::table('permissions', function (Blueprint $table) {
                $table->dropSoftDeletes();
            });
        }

        if (Schema::hasColumn('role_permissions', 'deleted_at')) {
            Schema::table('role_permissions', function (Blueprint $table) {
                $table->dropSoftDeletes();
            });
        }

        if (Schema::hasColumn('organization_settings', 'deleted_at')) {
            Schema::table('organization_settings', function (Blueprint $table) {
                $table->dropSoftDeletes();
            });
        }
    }
};
