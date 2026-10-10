<?php

namespace App\Console\Commands\Sandbox;

use App\Models\Integration\IntegrationCredential;
use App\Models\Integration\OAuthToken;
use App\Services\Amazon\AmazonConfigService;
use App\Services\EbayConfigService;
use App\Services\Shopify\ShopifyConfigService;
use Illuminate\Console\Command;

class SandboxStatusCommand extends Command
{
    protected $signature = 'sandbox:status';

    protected $description = 'Report eBay + Shopify + Amazon sandbox/production config + credential counts for the current environment';

    public function handle(EbayConfigService $ebay, ShopifyConfigService $shopify, AmazonConfigService $amazon): int
    {
        $this->newLine();
        $this->line('<options=bold>Sandbox / Production Status</>');
        $this->line('App env: <comment>'.config('app.env').'</comment>');
        $this->line('App URL: <comment>'.config('app.url').'</comment>');

        $this->newLine();
        $this->line('<options=bold>eBay</>');
        $this->table(['Setting', 'Value'], [
            ['Environment', $ebay->getEnvironment()],
            ['use_sandbox', $this->bool(config('services.ebay.use_sandbox'))],
            ['client_id present', $this->present($ebay->getClientId())],
            ['client_secret present', $this->present($ebay->getClientSecret())],
            ['ru_name present', $this->present($ebay->getRuName())],
            ['ECN sandbox token', $this->present(config('services.ebay.sandbox_app_notification_token'))],
            ['ECN production token', $this->present(config('services.ebay.production_app_notification_token'))],
            ['ECN legacy token', $this->present(config('services.ebay.app_notification_token'))],
            ['API base URL', $ebay->getApiBaseUrl()],
            ['OAuth token URL', $ebay->getOAuthTokenUrl()],
        ]);

        $this->newLine();
        $this->line('<options=bold>Shopify</>');
        $this->table(['Setting', 'Value'], [
            ['Environment', $shopify->getEnvironment()],
            ['use_sandbox', $this->bool(config('services.shopify.use_sandbox'))],
            ['api_key present', $this->present($shopify->getApiKey())],
            ['api_secret present', $this->present($shopify->getApiSecret())],
            ['api_version', $shopify->getApiVersion()],
            ['redirect_uri', $shopify->getRedirectUri()],
        ]);

        $this->newLine();
        $this->line('<options=bold>Amazon</>');
        $this->table(['Setting', 'Value'], [
            ['Environment', $amazon->getEnvironment()],
            ['use_sandbox', $this->bool(config('services.amazon.use_sandbox'))],
            ['lwa_client_id present', $this->present($amazon->getLwaClientId())],
            ['lwa_client_secret present', $this->present($amazon->getLwaClientSecret())],
            ['SP-API endpoint', $amazon->getSpApiEndpoint()],
            ['marketplace_id', $amazon->getMarketplaceId()],
            ['region', $amazon->getRegion()],
            ['redirect_uri', $amazon->getRedirectUri()],
        ]);

        $this->newLine();
        $this->line('<options=bold>Credentials</>');
        $this->table(
            ['Platform', 'Environment', 'Active', 'Total'],
            $this->credentialCounts(),
        );

        $this->newLine();
        $this->line('<options=bold>OAuth Tokens</>');
        $this->table(
            ['Platform', 'Environment', 'Active', 'Total'],
            $this->oauthTokenCounts(),
        );

        $this->newLine();
        $expectedEbay = $ebay->getEnvironment();
        $expectedShopify = $shopify->getEnvironment();
        $expectedAmazon = $amazon->getEnvironment();
        $stalePlatforms = [];
        foreach (['ebay' => $expectedEbay, 'shopify' => $expectedShopify, 'amazon' => $expectedAmazon] as $platform => $expected) {
            $stale = IntegrationCredential::where('platform', $platform)
                ->where('environment', '!=', $expected)
                ->count();
            if ($stale > 0) {
                $stalePlatforms[] = "$platform ($stale rows in non-{$expected} env)";
            }
        }

        if (! empty($stalePlatforms)) {
            $this->warn('Stale credentials detected (env mismatch with current config):');
            foreach ($stalePlatforms as $line) {
                $this->warn('  - '.$line);
            }
            $this->line('Run <info>php artisan sandbox:cutover --dry-run</info> to preview cleanup, then <info>--confirm</info> to drop.');
        } else {
            $this->info('No stale credentials.');
        }

        return Command::SUCCESS;
    }

    protected function present(?string $value): string
    {
        return ! empty($value) ? '<info>set</info>' : '<error>missing</error>';
    }

    protected function bool($value): string
    {
        return $value ? '<info>true</info>' : '<comment>false</comment>';
    }

    protected function credentialCounts(): array
    {
        return IntegrationCredential::query()
            ->whereIn('platform', ['ebay', 'shopify', 'amazon'])
            ->selectRaw('platform, environment, SUM(CASE WHEN is_active THEN 1 ELSE 0 END) AS active_count, COUNT(*) AS total')
            ->groupBy('platform', 'environment')
            ->orderBy('platform')->orderBy('environment')
            ->get()
            ->map(fn ($r) => [$r->platform, $r->environment, (int) $r->active_count, (int) $r->total])
            ->all();
    }

    protected function oauthTokenCounts(): array
    {
        return OAuthToken::query()
            ->whereIn('platform', ['ebay', 'shopify', 'amazon'])
            ->selectRaw('platform, environment, SUM(CASE WHEN is_active THEN 1 ELSE 0 END) AS active_count, COUNT(*) AS total')
            ->groupBy('platform', 'environment')
            ->orderBy('platform')->orderBy('environment')
            ->get()
            ->map(fn ($r) => [$r->platform, $r->environment, (int) $r->active_count, (int) $r->total])
            ->all();
    }
}
