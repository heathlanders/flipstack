<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('organization_id')->after('id')->nullable()->constrained()->nullOnDelete();
            $table->string('employee_id')->nullable()->after('email');
            $table->string('phone')->nullable()->after('email_verified_at');
            $table->string('department')->nullable()->after('phone');
            $table->string('job_title')->nullable()->after('department');
            $table->date('hire_date')->nullable()->after('job_title');
            $table->json('preferences')->nullable()->after('remember_token');
            $table->json('notification_settings')->nullable()->after('preferences');
            $table->string('avatar_url')->nullable()->after('profile_photo_path');
            $table->timestamp('last_login_at')->nullable()->after('updated_at');
            $table->string('last_login_ip')->nullable()->after('last_login_at');
            $table->boolean('mfa_enabled')->default(false)->after('last_login_ip');
            $table->text('mfa_secret')->nullable()->after('mfa_enabled');
            $table->json('mfa_recovery_codes')->nullable()->after('mfa_secret');
            $table->boolean('is_active')->default(true)->after('mfa_recovery_codes');
            $table->timestamp('password_expires_at')->nullable()->after('is_active');
            $table->softDeletes();

            $table->index(['organization_id', 'is_active', 'deleted_at']);
            $table->index(['email', 'organization_id']);
            $table->unique(['employee_id', 'organization_id']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['organization_id']);
            $table->dropIndex(['organization_id', 'is_active', 'deleted_at']);
            $table->dropIndex(['email', 'organization_id']);
            $table->dropUnique(['employee_id', 'organization_id']);
            $table->dropColumn([
                'organization_id', 'employee_id', 'phone', 'department', 'job_title',
                'hire_date', 'preferences', 'notification_settings', 'avatar_url',
                'last_login_at', 'last_login_ip', 'mfa_enabled', 'mfa_secret',
                'mfa_recovery_codes', 'is_active', 'password_expires_at',
            ]);
            $table->dropSoftDeletes();
        });
    }
};
