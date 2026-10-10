<?php

namespace App\Services\Amazon;

use App\Models\Amazon\AmazonSyncLog;
use App\Models\Integration\IntegrationCredential;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AmazonBaseService
{
    protected IntegrationCredential $credential;

    protected AmazonRateLimiter $rateLimiter;

    protected string $spApiEndpoint;

    protected string $accessToken;

    protected string $marketplaceId;

    public function __construct(IntegrationCredential $credential)
    {
        $this->credential = $credential;
        $this->rateLimiter = app(AmazonRateLimiter::class);

        $config = $credential->config ?? [];
        $amazonConfig = app(AmazonConfigService::class);

        $this->spApiEndpoint = $amazonConfig->getSpApiEndpoint();
        $this->marketplaceId = $config['amazon_marketplace_id'] ?? $amazonConfig->getMarketplaceId();
        $this->accessToken = $credential->getActiveAmazonToken()?->decrypted_access_token ?? '';
    }

    protected function buildUrl(string $endpoint): string
    {
        return rtrim($this->spApiEndpoint, '/').'/'.ltrim($endpoint, '/');
    }

    protected function request(string $method, string $endpoint, array $data = [], ?AmazonSyncLog $syncLog = null): array
    {
        $url = $this->buildUrl($endpoint);

        $this->rateLimiter->waitIfNeeded((string) $this->credential->id, $endpoint);

        if ($syncLog) {
            $syncLog->setRequest($endpoint, $method, $data);
        }

        $startTime = microtime(true);

        try {
            $request = Http::withHeaders([
                'x-amz-access-token' => $this->accessToken,
                'Content-Type' => 'application/json',
            ]);

            if (strtolower($method) === 'get') {
                $response = $request->get($url, $data);
            } else {
                $response = $request->{strtolower($method)}($url, $data);
            }

            $elapsed = round((microtime(true) - $startTime) * 1000);

            $this->rateLimiter->recordResponse(
                (string) $this->credential->id,
                $endpoint,
                $response->headers()
            );

            $body = $response->json() ?? [];

            if ($response->failed()) {
                $exception = AmazonApiException::fromResponse(
                    $body,
                    $response->status(),
                    $response->headers()
                );

                if ($syncLog) {
                    $syncLog->completeError(
                        $exception->getMessage(),
                        $exception->getAmazonErrorCode(),
                        $exception->getErrorDetails(),
                        $response->status(),
                        $exception->isRetryable()
                    );
                }

                throw $exception;
            }

            if ($syncLog) {
                $syncLog->completeSuccess($body, $response->status());
            }

            return $body;

        } catch (AmazonApiException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Amazon SP-API request failed', [
                'endpoint' => $endpoint,
                'error' => $e->getMessage(),
            ]);

            if ($syncLog) {
                $syncLog->completeError($e->getMessage(), 'NETWORK_ERROR', [], null, true);
            }

            throw new AmazonApiException(
                'Amazon SP-API request failed: '.$e->getMessage(),
                null,
                'NETWORK_ERROR',
                [],
                null,
                $e
            );
        }
    }

    protected function get(string $endpoint, array $params = [], ?AmazonSyncLog $syncLog = null): array
    {
        return $this->request('GET', $endpoint, $params, $syncLog);
    }

    protected function post(string $endpoint, array $data = [], ?AmazonSyncLog $syncLog = null): array
    {
        return $this->request('POST', $endpoint, $data, $syncLog);
    }

    protected function put(string $endpoint, array $data = [], ?AmazonSyncLog $syncLog = null): array
    {
        return $this->request('PUT', $endpoint, $data, $syncLog);
    }

    protected function patch(string $endpoint, array $data = [], ?AmazonSyncLog $syncLog = null): array
    {
        return $this->request('PATCH', $endpoint, $data, $syncLog);
    }

    protected function delete(string $endpoint, ?AmazonSyncLog $syncLog = null): array
    {
        return $this->request('DELETE', $endpoint, [], $syncLog);
    }

    protected function createSyncLog(string $operation, array $attributes = []): AmazonSyncLog
    {
        return AmazonSyncLog::startOperation(
            $this->credential->id,
            $this->credential->team_id,
            $operation,
            $attributes
        );
    }
}
