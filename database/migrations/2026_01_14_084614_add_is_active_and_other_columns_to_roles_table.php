<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            // Add missing columns from your Role model
            if (! Schema::hasColumn('roles', 'is_active')) {
                $table->boolean('is_active')->default(true);
            }

            if (! Schema::hasColumn('roles', 'is_default')) {
                $table->boolean('is_default')->default(false);
            }

            if (! Schema::hasColumn('roles', 'color')) {
                $table->string('color')->nullable();
            }

            if (! Schema::hasColumn('roles', 'icon')) {
                $table->string('icon')->nullable();
            }

            if (! Schema::hasColumn('roles', 'level')) {
                $table->integer('level')->default(50);
            }

            if (! Schema::hasColumn('roles', 'metadata')) {
                $table->json('metadata')->nullable();
            }

            if (! Schema::hasColumn('roles', 'organization_id')) {
                $table->unsignedBigInteger('organization_id')->nullable();
            }
        });

        // Update existing roles to be active by default
        DB::table('roles')->update(['is_active' => true]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->dropColumn([
                'is_active',
                'is_default',
                'color',
                'icon',
                'level',
                'metadata',
                'organization_id',
            ]);
        });
    }
};
