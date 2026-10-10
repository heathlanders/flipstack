<?php

namespace App\Notifications\Ebay;

use App\Models\Catalog\ProductIntakeQueue;
use App\Notifications\BaseNotification;

class EbayListingCompleted extends BaseNotification
{
    public function __construct(
        protected ProductIntakeQueue $queueItem,
        protected array $result
    ) {}

    public function getPriority(): string
    {
        return $this->result['success'] ? self::PRIORITY_MEDIUM : self::PRIORITY_HIGH;
    }

    public function getCategory(): string
    {
        return self::CATEGORY_PLATFORM;
    }

    public function getTitle(): string
    {
        return $this->result['success']
            ? 'eBay Listing Created Successfully'
            : 'eBay Listing Failed';
    }

    public function getMessage(): string
    {
        if ($this->result['success']) {
            $listingId = $this->result['listing_id'] ?? 'Unknown';

            return "Product #{$this->queueItem->id} has been successfully listed on eBay (ID: {$listingId}).";
        }

        $error = $this->result['error'] ?? 'Unknown error';

        return "Failed to create eBay listing for product #{$this->queueItem->id}: {$error}";
    }

    public function getActionUrl(): ?string
    {
        if ($this->result['success'] && ! empty($this->result['listing_id'])) {
            // Link to eBay listing if successful
            $listingId = $this->result['listing_id'];
            $baseUrl = config('services.ebay.sandbox', true)
                ? 'https://www.sandbox.ebay.com'
                : 'https://www.ebay.com';

            return "{$baseUrl}/itm/{$listingId}";
        }

        // Link back to queue item for review/retry
        return route('intake-queue.show', $this->queueItem);
    }

    public function getActionText(): string
    {
        return $this->result['success'] ? 'View on eBay' : 'Review & Retry';
    }

    protected function getData(): array
    {
        return [
            'queue_item_id' => $this->queueItem->id,
            'listing_id' => $this->result['listing_id'] ?? null,
            'sku' => $this->result['sku'] ?? null,
            'error' => $this->result['error'] ?? null,
            'error_type' => $this->result['error_type'] ?? null,
            'attempt_number' => $this->result['attempt'] ?? 1,
            'product_name' => $this->getProductName(),
            'team_id' => $this->queueItem->team_id,
            'organization_id' => $this->queueItem->organization_id,
        ];
    }

    /**
     * Get a friendly product name for the notification
     */
    protected function getProductName(): string
    {
        // Try to get name from enriched data
        $enrichedData = $this->queueItem->enriched_data ?? [];
        $productName = $enrichedData['merged_product_fields']['product_identity']['title'] ?? null;

        if ($productName) {
            return $productName;
        }

        // Fallback to identifiers
        if ($this->queueItem->manufacturer_part_number) {
            return 'NCR '.$this->queueItem->manufacturer_part_number;
        }

        if ($this->queueItem->product_number) {
            return 'Product '.$this->queueItem->product_number;
        }

        if ($this->queueItem->barcode) {
            return 'Barcode '.$this->queueItem->barcode;
        }

        return 'Product #'.$this->queueItem->id;
    }

    /**
     * Customize email representation (if using email notifications)
     */
    public function toMail($notifiable): \Illuminate\Notifications\Messages\MailMessage
    {
        $mail = (new \Illuminate\Notifications\Messages\MailMessage)
            ->subject($this->getTitle())
            ->greeting('Hello '.$notifiable->name.',');

        if ($this->result['success']) {
            $mail->line($this->getMessage())
                ->line('You can view the listing on eBay using the link below.')
                ->action($this->getActionText(), $this->getActionUrl())
                ->line('Thank you for using our platform!');
        } else {
            $mail->line($this->getMessage())
                ->line('You can review the item and retry the listing creation.')
                ->action($this->getActionText(), $this->getActionUrl())
                ->line('If this problem persists, please contact support.');
        }

        return $mail;
    }

    /**
     * Customize Slack representation (if using Slack notifications)
     */
    public function toSlack($notifiable): \Illuminate\Notifications\Messages\SlackMessage
    {
        $slack = (new \Illuminate\Notifications\Messages\SlackMessage)
            ->content($this->getTitle());

        if ($this->result['success']) {
            $slack->success()
                ->attachment(function ($attachment) {
                    $attachment->title($this->getProductName())
                        ->content($this->getMessage())
                        ->action('View on eBay', $this->getActionUrl());
                });
        } else {
            $slack->error()
                ->attachment(function ($attachment) {
                    $attachment->title($this->getProductName())
                        ->content($this->getMessage())
                        ->action('Review Item', $this->getActionUrl());
                });
        }

        return $slack;
    }
}
