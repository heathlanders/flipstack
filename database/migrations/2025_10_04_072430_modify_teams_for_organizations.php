<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->foreignId('organization_id')->after('id')->constrained()->cascadeOnDelete();
            $table->string('department')->nullable()->after('name');
            $table->json('settings')->nullable()->after('personal_team');
            $table->softDeletes();

            $table->index(['organization_id', 'deleted_at']);
        });
    }

    public function down(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->dropForeign(['organization_id']);
            $table->dropColumn(['organization_id', 'department', 'settings']);
            $table->dropSoftDeletes();
        });
    }
};
