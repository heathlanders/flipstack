<?php

namespace App\Console\Commands\Amazon;

use App\Models\Amazon\AmazonListing;
use App\Models\Amazon\AmazonSyncLog;
use App\Models\Integration\IntegrationCredential;
use App\Services\Amazon\AmazonConfigService;
use App\Services\Amazon\AmazonNotificationsService;
use App\Services\Amazon\AmazonOAuthService;
use Illuminate\Console\Command;
use Throwable;

/**
 * Operator-facing health check for the Amazon SP-API integration.
 *
 *   docker exec flipstack_app_1 php artisan amazon:health
 *
 * Use during production cutover and ongoing monitoring. Reports:
 *  - LWA / endpoint / sandbox config presence
 *  - Active credentials with token expiry
 *  - SNS destination + subscriptions per credential (when sns_arn set)
 *  - Listing + sync log counts (24h)
 */
class AmazonHealthCommand extends Command
{
    protected $signature = 'amazon:health {--credential= : Limit checks to one credential id} {--skip-api : Skip live SP-API calls}';

    protected $description = 'Health check for Amazon SP-API: config, credentials, subscriptions, recent sync activity.';

    public function handle(AmazonConfigService $config): int
    {
        $this->newLine();
        $this->line('<options=bold>Amazon SP-API Health</>');
        $this->line('App env: <comment>'.config('app.env').'</comment>');

        $this->newLine();
        $this->line('<options=bold>Configuration</>');
        $this->table(['Setting', 'Value'], [
            ['Environment', $config->getEnvironment()],
            ['use_sandbox', $this->bool(config('services.amazon.use_sandbox'))],
            ['lwa_client_id', $this->present($config->getLwaClientId())],
            ['lwa_client_secret', $this->present($config->getLwaClientSecret())],
            ['SP-API endpoint', $config->getSpApiEndpoint()],
            ['marketplace_id', $config->getMarketplaceId()],
            ['region', $config->getRegion()],
            ['redirect_uri', $config->getRedirectUri()],
            ['sns_arn', $this->present(config('services.amazon.sns_arn'))],
        ]);

        if (! $config->isConfigured()) {
            $this->error('Amazon not configured. Set AMAZON_LWA_CLIENT_ID + AMAZON_LWA_CLIENT_SECRET.');

            return Command::FAILURE;
        }

        $credentials = $this->loadCredentials();

        if ($credentials->isEmpty()) {
            $this->newLine();
            $this->warn('No active Amazon credentials. Connect a seller via OAuth on /marketplaces/amazon.');

            return Command::SUCCESS;
        }

        $this->newLine();
        $this->line('<options=bold>Credentials</>');
        $this->table(
            ['ID', 'Org', 'Name', 'Seller ID', 'Marketplace', 'Token expires', 'Auto-sync'],
            $credentials->map(fn ($c) => [
                $c->id,
                $c->organization_id,
                $c->name,
                $c->config['amazon_seller_id'] ?? '—',
                $c->config['amazon_marketplace_id'] ?? '—',
                $this->tokenExpiry($c),
                $c->auto_sync_enabled ? 'on' : 'off',
            ])->all()
        );

        $this->newLine();
        $this->line('<options=bold>Listings + Sync (24h)</>');
        $rows = [];
        foreach ($credentials as $c) {
            $rows[] = [
                $c->id,
                AmazonListing::where('integration_credential_id', $c->id)->count(),
                AmazonListing::where('integration_credential_id', $c->id)
                    ->where('status', AmazonListing::STATUS_ACTIVE)->count(),
                AmazonListing::where('integration_credential_id', $c->id)
                    ->where('status', AmazonListing::STATUS_ERROR)->count(),
                AmazonSyncLog::where('integration_credential_id', $c->id)
                    ->where('created_at', '>=', now()->subHours(24))->count(),
                AmazonSyncLog::where('integration_credential_id', $c->id)
                    ->where('created_at', '>=', now()->subHours(24))
                    ->whereIn('status', ['error', 'failed'])->count(),
            ];
        }
        $this->table(
            ['Cred', 'Listings', 'Active', 'Error', 'Sync ops 24h', 'Sync errors 24h'],
            $rows
        );

        if (! $this->option('skip-api')) {
            $this->newLine();
            $this->line('<options=bold>Live API verification</>');
            $this->verifyLive($credentials);
        }

        $snsArn = config('services.amazon.sns_arn');
        if (! empty($snsArn) && ! $this->option('skip-api')) {
            $this->newLine();
            $this->line('<options=bold>Notification subscriptions</>');
            $this->verifySubscriptions($credentials);
        } elseif (empty($snsArn)) {
            $this->newLine();
            $this->warn('AMAZON_SNS_ARN unset — notification subscriptions skipped.');
        }

        return Command::SUCCESS;
    }

    protected function loadCredentials()
    {
        $query = IntegrationCredential::where('platform', 'amazon')->where('is_active', true);

        if ($this->option('credential')) {
            $query->where('id', (int) $this->option('credential'));
        }

        return $query->get();
    }

    protected function tokenExpiry(IntegrationCredential $credential): string
    {
        $token = $credential->getActiveAmazonToken();
        if (! $token) {
            return '<error>no token</error>';
        }
        if (! $token->expires_at) {
            return '—';
        }
        $diff = now()->diffInMinutes($token->expires_at, false);

        if ($diff < 0) {
            return '<error>expired '.abs((int) $diff).'m ago</error>';
        }
        if ($diff < 10) {
            return '<comment>'.(int) $diff.'m</comment>';
        }

        return '<info>'.(int) $diff.'m</info>';
    }

    protected function verifyLive($credentials): void
    {
        $rows = [];
        foreach ($credentials as $c) {
            $service = new AmazonOAuthService;
            try {
                $ok = $service->verifyCredentials($c);
                $rows[] = [$c->id, $ok ? '<info>ok</info>' : '<error>failed</error>', '—'];
            } catch (Throwable $e) {
                $rows[] = [$c->id, '<error>error</error>', substr($e->getMessage(), 0, 80)];
            }
        }
        $this->table(['Cred', 'verifyCredentials', 'Error'], $rows);
    }

    protected function verifySubscriptions($credentials): void
    {
        $types = AmazonNotificationsService::NOTIFICATION_TYPES;
        $rows = [];

        foreach ($credentials as $c) {
            try {
                $service = new AmazonNotificationsService($c);
                $present = collect($types)->map(function ($type) use ($service) {
                    try {
                        $payload = $service->listSubscriptions($type);

                        return ! empty($payload);
                    } catch (Throwable) {
                        return false;
                    }
                });
                $rows[] = [
                    $c->id,
                    $present->filter()->count().' / '.count($types),
                    $present->filter()->count() === count($types) ? '<info>ok</info>' : '<comment>partial</comment>',
                ];
            } catch (Throwable $e) {
                $rows[] = [$c->id, '0 / '.count($types), '<error>'.substr($e->getMessage(), 0, 60).'</error>'];
            }
        }

        $this->table(['Cred', 'Subscribed types', 'Status'], $rows);
    }

    protected function present(?string $value): string
    {
        return ! empty($value) ? '<info>set</info>' : '<error>missing</error>';
    }

    protected function bool($value): string
    {
        return $value ? '<info>true</info>' : '<comment>false</comment>';
    }
}
