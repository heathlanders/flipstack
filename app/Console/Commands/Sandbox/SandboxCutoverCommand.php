<?php

namespace App\Console\Commands\Sandbox;

use App\Models\Integration\IntegrationCredential;
use App\Models\Integration\OAuthToken;
use App\Services\Amazon\AmazonConfigService;
use App\Services\EbayConfigService;
use App\Services\Shopify\ShopifyConfigService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SandboxCutoverCommand extends Command
{
    protected $signature = 'sandbox:cutover
        {--dry-run : Show what would be deleted without modifying the database}
        {--confirm : Required to actually delete}';

    protected $description = 'Drop stale eBay/Shopify/Amazon credentials whose environment does not match the current ConfigService env, so the next OAuth re-creates them with the correct env.';

    public function handle(EbayConfigService $ebay, ShopifyConfigService $shopify, AmazonConfigService $amazon): int
    {
        if (config('app.env') === 'production') {
            $this->error('Refusing to run on production. This command is for develop/staging only.');

            return Command::FAILURE;
        }

        $expected = [
            'ebay' => $ebay->getEnvironment(),
            'shopify' => $shopify->getEnvironment(),
            'amazon' => $amazon->getEnvironment(),
        ];

        $this->line('Current expected environment per platform:');
        foreach ($expected as $platform => $env) {
            $this->line("  - $platform: <info>$env</info>");
        }

        $rows = [];
        foreach ($expected as $platform => $env) {
            $credCount = IntegrationCredential::where('platform', $platform)
                ->where('environment', '!=', $env)->count();
            $tokenCount = OAuthToken::where('platform', $platform)
                ->where('environment', '!=', $env)->count();
            $rows[] = [$platform, "!= $env", $credCount, $tokenCount];
        }
        $this->table(['Platform', 'Filter', 'Credentials', 'OAuth Tokens'], $rows);

        $totalCreds = array_sum(array_column($rows, 2));
        $totalTokens = array_sum(array_column($rows, 3));

        if ($totalCreds === 0 && $totalTokens === 0) {
            $this->info('Nothing to clean. All credentials match the current env.');

            return Command::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->warn('Dry run only. No changes made.');

            return Command::SUCCESS;
        }

        if (! $this->option('confirm')) {
            $this->error('Pass --confirm to actually delete, or --dry-run to preview.');

            return Command::FAILURE;
        }

        $deletedCreds = 0;
        $deletedTokens = 0;
        DB::transaction(function () use ($expected, &$deletedCreds, &$deletedTokens) {
            foreach ($expected as $platform => $env) {
                $deletedTokens += OAuthToken::where('platform', $platform)
                    ->where('environment', '!=', $env)->delete();
                $deletedCreds += IntegrationCredential::where('platform', $platform)
                    ->where('environment', '!=', $env)->delete();
            }
        });

        $this->info("Deleted $deletedCreds credentials and $deletedTokens oauth tokens.");
        $this->line('Reconnect each marketplace via OAuth on develop to recreate them with the correct env.');

        return Command::SUCCESS;
    }
}
