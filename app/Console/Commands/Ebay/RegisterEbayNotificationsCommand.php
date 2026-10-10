<?php

namespace App\Console\Commands\Ebay;

use App\Models\Integration\IntegrationCredential;
use App\Services\EbayConfigService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

class RegisterEbayNotificationsCommand extends Command
{
    protected $signature = 'ebay:register-notifications
                            {--credential= : IntegrationCredential ID (defaults to all active eBay credentials)}
                            {--topics=MARKETPLACE_ACCOUNT_DELETION : Comma-separated topic IDs}
                            {--name= : Destination name (defaults to app URL)}
                            {--dry-run : Print requests without firing}';

    protected $description = 'Register an ECN destination + topic subscriptions with eBay for the given credential.';

    public function handle(): int
    {
        $credentials = $this->resolveCredentials();
        if ($credentials->isEmpty()) {
            return Command::FAILURE;
        }

        $topics = collect(explode(',', (string) $this->option('topics')))
            ->map(fn ($t) => trim($t))
            ->filter()
            ->values();

        $dryRun = (bool) $this->option('dry-run');

        $exitCode = Command::SUCCESS;

        foreach ($credentials as $credential) {
            $this->line('');
            $this->info("Credential {$credential->id} (org {$credential->organization_id}, env {$credential->environment})");

            try {
                $this->registerForCredential($credential, $topics, $dryRun);
            } catch (Throwable $e) {
                $this->error('  failed: '.$e->getMessage());
                $exitCode = Command::FAILURE;
            }
        }

        return $exitCode;
    }

    protected function registerForCredential(IntegrationCredential $credential, $topics, bool $dryRun): void
    {
        $config = $credential->config ?? [];

        $token = $config['ebay_notification_token'] ?? null;
        if (! $token) {
            $token = Str::random(40);
            $this->line('  generated new ebay_notification_token');
        } else {
            $this->line('  reusing existing ebay_notification_token');
        }

        $endpoint = url('/api/webhooks/ebay/'.$credential->id);
        $name = $this->option('name') ?: ('FlipStack '.parse_url($endpoint, PHP_URL_HOST));

        $destinationPayload = [
            'name' => Str::limit($name, 50, ''),
            'status' => 'ENABLED',
            'endpoint' => [
                'endpointDetails' => [
                    'endpointURL' => $endpoint,
                    'verificationToken' => $token,
                ],
            ],
        ];

        $this->line('  POST /commerce/notification/v1/destination');
        $this->line('    endpoint: '.$endpoint);

        if ($dryRun) {
            $this->line('    [dry-run] payload: '.json_encode($destinationPayload));
        }

        $appToken = $dryRun ? null : $this->fetchAppToken($credential);

        $destinationId = $config['ebay_notification_destination_id'] ?? null;

        if (! $dryRun) {
            $response = $this->ebayCall($credential, $appToken, 'post', '/commerce/notification/v1/destination', $destinationPayload);
            $destinationId = $response['destinationId'] ?? $destinationId;
            $this->info('    -> destinationId='.($destinationId ?? 'unknown'));
        }

        $subscriptions = $config['ebay_notification_subscriptions'] ?? [];

        foreach ($topics as $topic) {
            $subscriptionPayload = [
                'topicId' => $topic,
                'status' => 'ENABLED',
                'destinationId' => $destinationId,
                'payload' => [
                    'format' => 'JSON',
                    'schemaVersion' => '1.0',
                ],
            ];

            $this->line("  POST /commerce/notification/v1/subscription [topic={$topic}]");

            if ($dryRun) {
                $this->line('    [dry-run] payload: '.json_encode($subscriptionPayload));

                continue;
            }

            try {
                $sub = $this->ebayCall($credential, $appToken, 'post', '/commerce/notification/v1/subscription', $subscriptionPayload);
                $subscriptionId = $sub['subscriptionId'] ?? null;
                $subscriptions[$topic] = $subscriptionId;
                $this->info("    -> subscriptionId={$subscriptionId}");
            } catch (Throwable $e) {
                $this->warn("    subscription failed for {$topic}: ".$e->getMessage());
            }
        }

        if ($dryRun) {
            return;
        }

        $config['ebay_notification_token'] = $token;
        $config['ebay_notification_destination_id'] = $destinationId;
        $config['ebay_notification_subscriptions'] = $subscriptions;
        $config['ebay_notification_endpoint'] = $endpoint;
        $config['ebay_notification_registered_at'] = now()->toIso8601String();

        $credential->update(['config' => $config]);

        $this->info('  saved notification config to credential');
    }

    protected function fetchAppToken(IntegrationCredential $credential): string
    {
        $config = app(EbayConfigService::class);

        $baseUrl = $credential->environment === 'sandbox'
            ? 'https://api.sandbox.ebay.com'
            : 'https://api.ebay.com';

        $response = Http::asForm()
            ->withBasicAuth($config->getClientId(), $config->getClientSecret())
            ->post($baseUrl.'/identity/v1/oauth2/token', [
                'grant_type' => 'client_credentials',
                'scope' => 'https://api.ebay.com/oauth/api_scope',
            ]);

        if ($response->failed()) {
            throw new \Exception('Failed to fetch eBay app token: '.$response->body());
        }

        return $response->json('access_token');
    }

    protected function ebayCall(IntegrationCredential $credential, string $appToken, string $method, string $endpoint, array $body): array
    {
        $baseUrl = $credential->environment === 'sandbox'
            ? 'https://api.sandbox.ebay.com'
            : 'https://api.ebay.com';

        $response = Http::withToken($appToken)
            ->withHeaders([
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])
            ->$method($baseUrl.$endpoint, $body);

        if ($response->failed()) {
            throw new \Exception('eBay API '.strtoupper($method).' '.$endpoint.' failed: '.$response->body());
        }

        return $response->json() ?? [];
    }

    /** @return \Illuminate\Support\Collection<int,IntegrationCredential> */
    protected function resolveCredentials()
    {
        if ($id = $this->option('credential')) {
            $credential = IntegrationCredential::find($id);
            if (! $credential || $credential->platform !== 'ebay') {
                $this->error("Credential {$id} not found or not eBay.");

                return collect();
            }

            return collect([$credential]);
        }

        $credentials = IntegrationCredential::where('platform', 'ebay')
            ->where('is_active', true)
            ->get();

        if ($credentials->isEmpty()) {
            $this->error('No active eBay credentials.');
        }

        return $credentials;
    }
}
