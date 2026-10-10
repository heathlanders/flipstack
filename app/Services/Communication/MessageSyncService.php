<?php

namespace App\Services\Communication;

use App\Models\Communication\MarketplaceMessage;
use App\Models\Customer\Customer;
use App\Models\Ebay\EbayListing;
use App\Models\Integration\IntegrationCredential;
use App\Models\User\User;
use App\Notifications\Communication\NewMessageNotification;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class MessageSyncService
{
    /**
     * Sync messages from marketplace APIs for an organization.
     */
    public function syncForOrganization(string $orgId): int
    {
        $credentials = IntegrationCredential::where('organization_id', $orgId)
            ->where('platform', 'ebay')
            ->where('is_active', true)
            ->get();

        if ($credentials->isEmpty()) {
            Log::info('MessageSyncService: no active eBay credentials', [
                'organization_id' => $orgId,
            ]);

            return 0;
        }

        $totalSynced = 0;

        foreach ($credentials as $credential) {
            try {
                $totalSynced += $this->syncEbayMessages($credential, $orgId);
            } catch (\Exception $e) {
                Log::error('MessageSyncService: eBay sync failed for credential', [
                    'organization_id' => $orgId,
                    'credential_id' => $credential->id,
                    'error' => $e->getMessage(),
                ]);

                app(\App\Services\NotificationService::class)->dispatchEvent(
                    eventKey: 'communication.message.sync_failed',
                    organizationId: (int) $orgId,
                    args: ['ebay', $e->getMessage()],
                    relatedId: 'ebay:'.$credential->id,
                );
            }
        }

        return $totalSynced;
    }

    /**
     * Sync messages from a single eBay credential.
     */
    public function syncEbayMessages(IntegrationCredential $credential, string $orgId): int
    {
        $service = new EbayMessageService($credential);

        // Determine sync start time - last sync or 30 days ago
        $config = $credential->config ?? [];
        $since = isset($config['last_message_sync_at'])
            ? Carbon::parse($config['last_message_sync_at'])
            : now()->subDays(30);

        $messages = $service->fetchMessages($since);

        if ($messages->isEmpty()) {
            // Only update sync time if we've synced before (not on first empty run)
            if (isset($config['last_message_sync_at'])) {
                $this->updateLastSyncTime($credential);
            }

            return 0;
        }

        $synced = 0;
        $errors = 0;
        $newMessages = [];

        foreach ($messages as $msgData) {
            try {
                $message = $this->upsertMessage($msgData, $orgId, $credential);
                if ($message) {
                    $synced++;
                    if ($message->direction === MarketplaceMessage::DIRECTION_INBOUND) {
                        $newMessages[] = $message;
                    }
                }
            } catch (\Exception $e) {
                $errors++;
                Log::error('MessageSyncService: failed to upsert message', [
                    'organization_id' => $orgId,
                    'credential_id' => $credential->id,
                    'marketplace_message_id' => $msgData['marketplace_message_id'] ?? null,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->updateLastSyncTime($credential);

        // Notify organization users about new inbound messages
        if (! empty($newMessages)) {
            $this->notifyNewMessages($newMessages, $orgId);
        }

        Log::info('MessageSyncService: eBay sync completed', [
            'organization_id' => $orgId,
            'credential_id' => $credential->id,
            'synced' => $synced,
            'errors' => $errors,
            'total_fetched' => $messages->count(),
        ]);

        return $synced;
    }

    /**
     * Upsert a message record. Returns true if a new record was created.
     */
    protected function upsertMessage(array $msgData, string $orgId, IntegrationCredential $credential): ?MarketplaceMessage
    {
        $existing = MarketplaceMessage::where('organization_id', $orgId)
            ->where('marketplace', MarketplaceMessage::MARKETPLACE_EBAY)
            ->where('marketplace_message_id', $msgData['marketplace_message_id'])
            ->exists();

        if ($existing) {
            return null;
        }

        $attributes = [
            'organization_id' => $orgId,
            'marketplace' => MarketplaceMessage::MARKETPLACE_EBAY,
            'marketplace_message_id' => $msgData['marketplace_message_id'],
            'marketplace_thread_id' => $msgData['marketplace_thread_id'] ?: null,
            'marketplace_item_id' => $msgData['marketplace_item_id'] ?: null,
            'message_type' => $msgData['message_type'] ?? null,
            'direction' => $msgData['direction'],
            'subject' => $msgData['subject'],
            'body' => $msgData['body'],
            'sender_name' => $msgData['sender_name'],
            'sender_id' => $msgData['sender_id'],
            'status' => MarketplaceMessage::STATUS_UNREAD,
            'received_at' => $msgData['received_at'],
        ];

        // Auto-link to product via eBay listing
        if (! empty($msgData['marketplace_item_id'])) {
            $listing = EbayListing::where('integration_credential_id', $credential->id)
                ->where('ebay_listing_id', $msgData['marketplace_item_id'])
                ->first();

            if ($listing && $listing->product_id) {
                $attributes['product_id'] = $listing->product_id;
            }
        }

        // Auto-link to customer
        if (! empty($msgData['sender_id'])) {
            $customer = Customer::where('organization_id', $orgId)
                ->where('marketplace_source', 'ebay')
                ->where('marketplace_customer_id', $msgData['sender_id'])
                ->first();

            if ($customer) {
                $attributes['customer_id'] = $customer->id;
            }
        }

        return MarketplaceMessage::create($attributes);
    }

    /**
     * Notify organization users about new inbound messages.
     */
    protected function notifyNewMessages(array $messages, string $orgId): void
    {
        try {
            $users = User::where('organization_id', $orgId)->get();

            if ($users->isEmpty()) {
                return;
            }

            foreach ($messages as $message) {
                Notification::send($users, new NewMessageNotification($message));
            }
        } catch (\Exception $e) {
            Log::error('MessageSyncService: failed to send message notifications', [
                'organization_id' => $orgId,
                'message_count' => count($messages),
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Update the last sync timestamp on the credential config.
     */
    protected function updateLastSyncTime(IntegrationCredential $credential): void
    {
        $config = $credential->config ?? [];
        $config['last_message_sync_at'] = now()->toIso8601String();
        $credential->update(['config' => $config]);
    }

    /**
     * Get inbox statistics for an organization.
     */
    public function getInboxStats(string $orgId): array
    {
        $baseQuery = MarketplaceMessage::where('organization_id', $orgId);

        $unread = (clone $baseQuery)->where('status', MarketplaceMessage::STATUS_UNREAD)->count();
        $total = (clone $baseQuery)->whereNot('status', MarketplaceMessage::STATUS_ARCHIVED)->count();

        $byMarketplace = (clone $baseQuery)
            ->whereNot('status', MarketplaceMessage::STATUS_ARCHIVED)
            ->selectRaw("marketplace, COUNT(*) as count, SUM(CASE WHEN status = 'unread' THEN 1 ELSE 0 END) as unread_count")
            ->groupBy('marketplace')
            ->get()
            ->keyBy('marketplace')
            ->map(fn ($row) => [
                'total' => (int) $row->count,
                'unread' => (int) $row->unread_count,
            ])
            ->toArray();

        return [
            'unread' => $unread,
            'total' => $total,
            'by_marketplace' => $byMarketplace,
        ];
    }

    /**
     * Mark a message as read.
     */
    public function markAsRead(MarketplaceMessage $message): void
    {
        if ($message->status === MarketplaceMessage::STATUS_UNREAD) {
            $message->update([
                'status' => MarketplaceMessage::STATUS_READ,
                'read_at' => now(),
            ]);
        }
    }

    /**
     * Archive a message.
     */
    public function archive(MarketplaceMessage $message): void
    {
        $message->update([
            'status' => MarketplaceMessage::STATUS_ARCHIVED,
        ]);
    }
}
