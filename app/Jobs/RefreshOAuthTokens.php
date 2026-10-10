<?php

namespace App\Jobs;

use App\Models\Integration\IntegrationCredential;
use App\Models\Integration\OAuthToken;
use App\Models\User\User;
use App\Notifications\System\OAuthTokenFailedNotification;
use App\Services\EbayOAuthService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RefreshOAuthTokens implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    public int $backoff = 60;

    public function __construct()
    {
        //
    }

    public function handle(): void
    {
        Log::info('Starting OAuth token refresh job');

        // Get all tokens that need refresh (active, has refresh token, expiring within 30 minutes)
        $tokens = OAuthToken::where('is_active', true)
            ->whereNotNull('refresh_token')
            ->where('expires_at', '<=', now()->addMinutes(30))
            ->get();

        Log::info('Found tokens to refresh', ['count' => $tokens->count()]);

        foreach ($tokens as $token) {
            try {
                $this->refreshToken($token);
            } catch (\Exception $e) {
                Log::error('Failed to refresh token', [
                    'token_id' => $token->id,
                    'platform' => $token->platform,
                    'error' => $e->getMessage(),
                ]);

                $credential = $token->integrationCredential;
                if ($credential) {
                    $admins = User::where('organization_id', $credential->organization_id)
                        ->whereHas('roles', fn ($q) => $q->whereIn('name', ['admin', 'owner']))
                        ->get();

                    $notification = new OAuthTokenFailedNotification($token->platform, $e->getMessage());
                    foreach ($admins as $admin) {
                        $admin->notify($notification);
                    }
                }
            }
        }

        Log::info('Completed OAuth token refresh job');
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('RefreshOAuthTokens job failed', [
            'error' => $exception->getMessage(),
        ]);
    }

    private function refreshToken(OAuthToken $token): void
    {
        $credential = $token->integrationCredential;

        if (! $credential || ! $credential->is_active) {
            Log::warning('Cannot refresh token - credential inactive or missing', [
                'token_id' => $token->id,
            ]);

            return;
        }

        match ($token->platform) {
            'ebay' => $this->refreshEbayToken($token, $credential),
            // Add other platforms here as they're implemented
            default => Log::warning('Unknown platform for token refresh', [
                'platform' => $token->platform,
                'token_id' => $token->id,
            ]),
        };
    }

    private function refreshEbayToken(OAuthToken $token, IntegrationCredential $credential): void
    {
        $service = new EbayOAuthService($credential);
        $service->refreshToken($token);

        Log::info('Successfully refreshed eBay token', [
            'token_id' => $token->id,
            'team_id' => $token->team_id,
        ]);

        withAuditActor(null, fn () => audit_event(
            action: 'credential.oauth.refreshed',
            description: "eBay OAuth token refreshed (token #{$token->id})",
            subject: $token,
            metadata: [
                'platform' => 'ebay',
                'team_id' => $token->team_id,
                'expires_at' => $token->fresh()->expires_at?->toIso8601String(),
            ],
            severity: 'info',
            category: 'security',
            organization: $credential->organization,
        ), ['source' => 'cron', 'job_class' => self::class]);
    }
}
