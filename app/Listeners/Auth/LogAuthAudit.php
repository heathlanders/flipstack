<?php

namespace App\Listeners\Auth;

use App\Models\User\User;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;

class LogAuthAudit
{
    public function login(Login $event): void
    {
        audit_event(
            action: 'auth.login',
            description: "User {$event->user->email} logged in",
            metadata: [
                'guard' => $event->guard,
                'remember' => $event->remember,
                'source' => 'web',
            ],
            severity: 'info',
            category: 'security',
            actor: $event->user,
        );
    }

    public function logout(Logout $event): void
    {
        if (! $event->user) {
            return;
        }
        audit_event(
            action: 'auth.logout',
            description: "User {$event->user->email} logged out",
            metadata: ['guard' => $event->guard, 'source' => 'web'],
            severity: 'info',
            category: 'security',
            actor: $event->user,
        );
    }

    public function failed(Failed $event): void
    {
        $email = is_array($event->credentials) ? ($event->credentials['email'] ?? null) : null;

        // Look up org via email so we can persist (org_id is NOT NULL on user_activity_logs).
        // Unknown emails are skipped here — track at infrastructure level (rate-limit logs) instead.
        $user = $email ? User::where('email', $email)->first() : null;
        $organization = $user?->organization;
        if (! $organization) {
            return;
        }

        audit_event(
            action: 'auth.failed_login',
            description: 'Failed login attempt'.($email ? " for {$email}" : ''),
            metadata: [
                'email' => $email,
                'guard' => $event->guard,
                'ip' => request()?->ip(),
                'source' => 'web',
            ],
            severity: 'warning',
            category: 'security',
            organization: $organization,
        );
    }

    public function lockout(Lockout $event): void
    {
        audit_security(
            action: 'auth.lockout',
            description: 'Authentication throttled (too many attempts)',
            metadata: ['source' => 'web', 'ip' => request()?->ip()],
        );
    }

    public function passwordReset(PasswordReset $event): void
    {
        audit_event(
            action: 'auth.password_reset',
            description: "Password reset for {$event->user->email}",
            metadata: ['source' => 'web'],
            severity: 'warning',
            category: 'security',
            actor: $event->user,
        );
    }
}
