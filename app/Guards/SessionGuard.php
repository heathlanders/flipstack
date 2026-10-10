<?php

namespace App\Guards;

use App\Models\User\LoginAttempt;
use App\Models\User\UserSession;
use Illuminate\Auth\SessionGuard as BaseSessionGuard;

class SessionGuard extends BaseSessionGuard
{
    public function attempt(array $credentials = [], $remember = false)
    {
        $email = $credentials['email'] ?? '';
        $this->logLoginAttempt($email, false, 'attempt_started');

        // Check for too many failed attempts
        if ($this->hasTooManyLoginAttempts($email)) {
            $this->logLoginAttempt($email, false, 'too_many_attempts');
            $this->dispatchFailedLoginThreshold($email);

            return false;
        }

        $result = parent::attempt($credentials, $remember);

        if ($result) {
            $this->logLoginAttempt($email, true);
            $isNewDevice = $this->isNewDevice($this->user());
            $this->createUserSession();
            $this->updateUserLoginInfo();

            if ($isNewDevice) {
                $this->dispatchNewDeviceLogin($this->user());
            }
        } else {
            $this->logLoginAttempt($email, false, 'invalid_credentials');
        }

        return $result;
    }

    private function isNewDevice($user): bool
    {
        if (! $user) {
            return false;
        }

        $userAgent = request()->userAgent();
        $ipAddress = request()->ip();

        return ! UserSession::where('user_id', $user->id)
            ->where(function ($q) use ($userAgent, $ipAddress) {
                $q->where('user_agent', $userAgent)
                    ->orWhere('ip_address', $ipAddress);
            })
            ->exists();
    }

    private function dispatchFailedLoginThreshold(string $email): void
    {
        $user = \App\Models\User\User::where('email', $email)->first();
        if (! $user || ! $user->organization_id) {
            return;
        }

        $count = LoginAttempt::forEmail($email)->failed()->recent(15)->count();

        app(\App\Services\NotificationService::class)->dispatchEvent(
            eventKey: 'security.login.threshold_breached',
            organizationId: (int) $user->organization_id,
            args: [$email, request()->ip() ?? 'unknown', $count],
            relatedId: $email.':'.(request()->ip() ?? 'unknown'),
        );
    }

    private function dispatchNewDeviceLogin($user): void
    {
        if (! $user || ! $user->organization_id) {
            return;
        }

        app(\App\Services\NotificationService::class)->dispatchEvent(
            eventKey: 'security.login.new_device',
            organizationId: (int) $user->organization_id,
            args: [
                (string) $user->id,
                (string) ($user->name ?? $user->email),
                request()->ip() ?? 'unknown',
                request()->userAgent(),
            ],
            relatedId: $user->id.':'.(request()->ip() ?? 'unknown'),
        );
    }

    public function logout()
    {
        if ($this->user()) {
            $this->logLoginAttempt($this->user()->email, true, 'logout');
            $this->clearUserSession();
        }

        parent::logout();
    }

    private function logLoginAttempt(string $email, bool $successful, ?string $reason = null): void
    {
        LoginAttempt::create([
            'email' => $email,
            'ip_address' => request()->ip(),
            'successful' => $successful,
            'failure_reason' => $successful ? null : $reason,
            'user_agent' => request()->userAgent(),
            'attempted_at' => now(),
        ]);
    }

    private function hasTooManyLoginAttempts(string $email): bool
    {
        $maxAttempts = 5;
        $timeWindow = 15; // minutes

        $recentFailedAttempts = LoginAttempt::forEmail($email)
            ->failed()
            ->recent($timeWindow)
            ->count();

        return $recentFailedAttempts >= $maxAttempts;
    }

    private function createUserSession(): void
    {
        if ($this->user()) {
            $session = UserSession::create([
                'id' => session()->getId(),
                'user_id' => $this->user()->id,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'payload' => base64_encode(serialize(session()->all())),
                'last_activity' => time(),
                'expires_at' => now()->addMinutes(config('session.lifetime')),
            ]);

            $session->markAsCurrent();
        }
    }

    private function clearUserSession(): void
    {
        UserSession::where('id', session()->getId())->delete();
    }

    private function updateUserLoginInfo(): void
    {
        if ($this->user()) {
            $this->user()->updateLastLogin();
        }
    }
}
