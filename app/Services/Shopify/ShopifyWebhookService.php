<?php

namespace App\Services\Shopify;

use App\Models\Shopify\ShopifySyncLog;
use Illuminate\Support\Facades\Log;

class ShopifyWebhookService extends ShopifyBaseService
{
    protected array $requiredTopics = [
        'products/create',
        'products/update',
        'products/delete',
        'orders/create',
        'orders/updated',
        'inventory_levels/update',
        'app/uninstalled',
    ];

    public function registerWebhooks(): array
    {
        $results = [];

        foreach ($this->requiredTopics as $topic) {
            try {
                $result = $this->registerWebhook($topic);
                $results[$topic] = $result;
            } catch (ShopifyApiException $e) {
                // Webhook may already exist
                if ($e->getHttpStatusCode() === 422) {
                    Log::info('Shopify webhook already registered', ['topic' => $topic]);
                    $results[$topic] = ['status' => 'already_exists'];
                } else {
                    Log::error('Failed to register Shopify webhook', [
                        'topic' => $topic,
                        'error' => $e->getMessage(),
                    ]);
                    $results[$topic] = ['status' => 'error', 'error' => $e->getMessage()];
                }
            }
        }

        return $results;
    }

    protected function registerWebhook(string $topic): array
    {
        $syncLog = $this->createSyncLog(ShopifySyncLog::OP_REGISTER_WEBHOOK, [
            'shopify_sku' => $topic,
        ]);

        $response = $this->post('webhooks', [
            'webhook' => [
                'topic' => $topic,
                'address' => url('/shopify/webhooks'),
                'format' => 'json',
            ],
        ], $syncLog);

        return $response['webhook'] ?? [];
    }

    public function getWebhooks(): array
    {
        $response = $this->get('webhooks');

        return $response['webhooks'] ?? [];
    }

    public function deleteWebhook(int $webhookId): void
    {
        $this->delete("webhooks/{$webhookId}");
    }
}
