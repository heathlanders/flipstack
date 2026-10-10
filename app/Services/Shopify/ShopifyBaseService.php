<?php

namespace App\Services\Shopify;

use App\Models\Integration\IntegrationCredential;
use App\Models\Shopify\ShopifySyncLog;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ShopifyBaseService
{
    protected IntegrationCredential $credential;

    protected ShopifyRateLimiter $rateLimiter;

    protected string $shopDomain;

    protected string $accessToken;

    protected string $apiVersion;

    public function __construct(IntegrationCredential $credential)
    {
        $this->credential = $credential;
        $this->rateLimiter = app(ShopifyRateLimiter::class);

        $config = $credential->config ?? [];
        $this->shopDomain = $config['shop_domain'] ?? '';
        $this->accessToken = $credential->getActiveShopifyToken()?->decrypted_access_token ?? '';
        $this->apiVersion = app(ShopifyConfigService::class)->getApiVersion();
    }

    protected function buildUrl(string $endpoint): string
    {
        return "https://{$this->shopDomain}/admin/api/{$this->apiVersion}/{$endpoint}.json";
    }

    protected function request(string $method, string $endpoint, array $data = [], ?ShopifySyncLog $syncLog = null): array
    {
        $url = $this->buildUrl($endpoint);

        $this->rateLimiter->waitIfNeeded($this->shopDomain);

        if ($syncLog) {
            $syncLog->setRequest($endpoint, $method, $data);
        }

        $startTime = microtime(true);

        try {
            $response = Http::withHeaders([
                'X-Shopify-Access-Token' => $this->accessToken,
                'Content-Type' => 'application/json',
            ])->{strtolower($method)}($url, $data);

            $elapsed = round((microtime(true) - $startTime) * 1000);

            // Record rate limit headers
            $this->rateLimiter->recordResponse(
                $this->shopDomain,
                $response->headers()
            );

            $body = $response->json() ?? [];

            if ($response->failed()) {
                $exception = ShopifyApiException::fromResponse(
                    $body,
                    $response->status(),
                    $response->headers()
                );

                if ($syncLog) {
                    $syncLog->completeError(
                        $exception->getMessage(),
                        $exception->getShopifyErrorCode(),
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

        } catch (ShopifyApiException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Shopify API request failed', [
                'endpoint' => $endpoint,
                'error' => $e->getMessage(),
            ]);

            if ($syncLog) {
                $syncLog->completeError($e->getMessage(), 'NETWORK_ERROR', [], null, true);
            }

            throw new ShopifyApiException(
                'Shopify API request failed: '.$e->getMessage(),
                null,
                'NETWORK_ERROR',
                [],
                null,
                $e
            );
        }
    }

    protected function get(string $endpoint, array $params = [], ?ShopifySyncLog $syncLog = null): array
    {
        $queryString = ! empty($params) ? '?'.http_build_query($params) : '';

        return $this->request('GET', $endpoint.$queryString, [], $syncLog);
    }

    protected function post(string $endpoint, array $data = [], ?ShopifySyncLog $syncLog = null): array
    {
        return $this->request('POST', $endpoint, $data, $syncLog);
    }

    protected function put(string $endpoint, array $data = [], ?ShopifySyncLog $syncLog = null): array
    {
        return $this->request('PUT', $endpoint, $data, $syncLog);
    }

    protected function delete(string $endpoint, ?ShopifySyncLog $syncLog = null): array
    {
        return $this->request('DELETE', $endpoint, [], $syncLog);
    }

    /**
     * Paginate through all results using cursor-based pagination (Link header).
     */
    protected function paginateAll(string $endpoint, string $resourceKey, array $params = []): array
    {
        $allResults = [];
        $params['limit'] = $params['limit'] ?? 250;
        $url = $this->buildUrl($endpoint).'?'.http_build_query($params);

        while ($url) {
            $this->rateLimiter->waitIfNeeded($this->shopDomain);

            $response = Http::withHeaders([
                'X-Shopify-Access-Token' => $this->accessToken,
                'Content-Type' => 'application/json',
            ])->get($url);

            $this->rateLimiter->recordResponse($this->shopDomain, $response->headers());

            if ($response->failed()) {
                throw ShopifyApiException::fromResponse(
                    $response->json() ?? [],
                    $response->status(),
                    $response->headers()
                );
            }

            $body = $response->json();
            $allResults = array_merge($allResults, $body[$resourceKey] ?? []);

            // Parse Link header for next page
            $url = $this->getNextPageUrl($response->headers());
        }

        return $allResults;
    }

    protected function getNextPageUrl(array $headers): ?string
    {
        $linkHeader = $headers['Link'][0] ?? null;

        if (! $linkHeader) {
            return null;
        }

        // Parse Link: <url>; rel="next"
        if (preg_match('/<([^>]+)>;\s*rel="next"/', $linkHeader, $matches)) {
            return $matches[1];
        }

        return null;
    }

    protected function createSyncLog(string $operation, array $attributes = []): ShopifySyncLog
    {
        return ShopifySyncLog::startOperation(
            $this->credential->id,
            $this->credential->team_id,
            $operation,
            $attributes
        );
    }
}
