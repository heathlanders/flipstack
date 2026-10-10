<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('team_invitations', function (Blueprint $table) {
            // Enhanced invitation fields
            $table->unsignedBigInteger('organization_id')->nullable()->after('team_id');
            $table->unsignedBigInteger('invited_by')->nullable()->after('email');
            $table->string('token')->unique()->nullable()->after('role');
            $table->json('permissions')->nullable()->after('token');
            $table->timestamp('expires_at')->nullable()->after('permissions');
            $table->timestamp('accepted_at')->nullable()->after('expires_at');
            $table->timestamp('declined_at')->nullable()->after('accepted_at');
            $table->timestamp('cancelled_at')->nullable()->after('declined_at');
            $table->text('message')->nullable()->after('cancelled_at');
            $table->json('metadata')->nullable()->after('message');

            // Foreign key constraints
            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->foreign('invited_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down()
    {
        Schema::table('team_invitations', function (Blueprint $table) {
            $table->dropForeign(['organization_id']);
            $table->dropForeign(['invited_by']);

            $table->dropColumn([
                'organization_id', 'invited_by', 'token', 'permissions',
                'expires_at', 'accepted_at', 'declined_at', 'cancelled_at',
                'message', 'metadata',
            ]);
        });
    }
};
