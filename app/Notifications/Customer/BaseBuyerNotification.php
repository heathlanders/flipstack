<?php

namespace App\Notifications\Customer;

use App\Models\Organization\Organization;
use App\Models\Organization\OrganizationSettings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Base class for buyer-facing notifications.
 *
 * Buyer notifications are sent to anonymous email recipients (no User model)
 * so the standard NotificationPreferenceService channel resolver does not
 * apply. Channel selection is fixed to mail; org owners can disable buyer
 * notifications globally via OrganizationSettings.customer_notifications_enabled.
 */
abstract class BaseBuyerNotification extends Notification implements ShouldQueue
{
    use Queueable;

    abstract public function getOrganizationId(): int;

    abstract public function getMarketplace(): ?string;

    public function via($notifiable): array
    {
        if (! $this->buyerNotificationsEnabled()) {
            return [];
        }

        if ($this->isSuppressedByMarketplace()) {
            return [];
        }

        return ['mail'];
    }

    protected function buyerNotificationsEnabled(): bool
    {
        $value = OrganizationSettings::get(
            $this->getOrganizationId(),
            'customer_notifications_enabled',
            ['enabled' => true],
        );

        if (is_array($value)) {
            return (bool) ($value['enabled'] ?? true);
        }

        return (bool) $value;
    }

    /**
     * Marketplaces that already send their own buyer notifications. Suppress
     * by default — orgs can override per-event in their NotificationRule.
     */
    protected function isSuppressedByMarketplace(): bool
    {
        $marketplace = $this->getMarketplace();
        if (! $marketplace) {
            return false;
        }

        $value = OrganizationSettings::get(
            $this->getOrganizationId(),
            'customer_notifications_marketplace_overrides',
            [],
        );

        $overrides = is_array($value) ? $value : [];
        $defaultSuppressed = ['ebay', 'shopify', 'amazon'];

        if (array_key_exists($marketplace, $overrides)) {
            return ! (bool) $overrides[$marketplace];
        }

        return in_array(strtolower($marketplace), $defaultSuppressed, true);
    }

    protected function organization(): ?Organization
    {
        return Organization::find($this->getOrganizationId());
    }

    protected function brandingFromOrganization(): array
    {
        return OrganizationSettings::getBranding($this->getOrganizationId());
    }
}
