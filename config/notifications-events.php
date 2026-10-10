<?php

/**
 * Notification event registry.
 *
 * Each entry maps an event_key to:
 *  - class: BaseNotification subclass to instantiate
 *  - priority: default priority constant value
 *  - category: default category constant value
 *  - description: human-readable label for the rules UI
 *  - default_recipients: fallback recipients used when no NotificationRule
 *    row exists for this org/event_key. Format matches NotificationRuleRecipient.
 *  - dedup_minutes: default suppression window (rule may override)
 */

return [
    'events' => [

        'ebay.listing.sync_failed' => [
            'class' => \App\Notifications\Ebay\ListingSyncFailedNotification::class,
            'priority' => 'high',
            'category' => 'platform',
            'description' => 'eBay listing sync failed after all retries',
            'default_recipients' => [
                ['type' => 'role', 'value' => 'organization_owner', 'step' => 0],
                ['type' => 'role', 'value' => 'admin', 'step' => 0],
            ],
            'dedup_minutes' => 30,
        ],

        'shopify.product.sync_failed' => [
            'class' => \App\Notifications\Shopify\ProductSyncFailedNotification::class,
            'priority' => 'high',
            'category' => 'platform',
            'description' => 'Shopify product sync failed',
            'default_recipients' => [
                ['type' => 'role', 'value' => 'organization_owner', 'step' => 0],
                ['type' => 'role', 'value' => 'admin', 'step' => 0],
            ],
            'dedup_minutes' => 30,
        ],

        'intake.item.failed' => [
            'class' => \App\Notifications\Intake\IntakeItemFailedNotification::class,
            'priority' => 'high',
            'category' => 'inventory',
            'description' => 'Intake queue item failed processing',
            'default_recipients' => [
                ['type' => 'role', 'value' => 'organization_owner', 'step' => 0],
                ['type' => 'role', 'value' => 'admin', 'step' => 0],
            ],
            'dedup_minutes' => 0,
        ],

        'pricing.rule.error' => [
            'class' => \App\Notifications\Pricing\RepricingRuleErrorNotification::class,
            'priority' => 'high',
            'category' => 'financial',
            'description' => 'Repricing rule encountered an error',
            'default_recipients' => [
                ['type' => 'role', 'value' => 'organization_owner', 'step' => 0],
                ['type' => 'role', 'value' => 'admin', 'step' => 0],
            ],
            'dedup_minutes' => 60,
        ],

        'communication.message.sync_failed' => [
            'class' => \App\Notifications\Communication\MessageSyncFailedNotification::class,
            'priority' => 'high',
            'category' => 'customer',
            'description' => 'Marketplace message sync failed',
            'default_recipients' => [
                ['type' => 'role', 'value' => 'organization_owner', 'step' => 0],
                ['type' => 'role', 'value' => 'admin', 'step' => 0],
            ],
            'dedup_minutes' => 30,
        ],

        'procurement.po.created' => [
            'class' => \App\Notifications\Procurement\PoCreatedNotification::class,
            'priority' => 'medium',
            'category' => 'financial',
            'description' => 'Purchase order created',
            'default_recipients' => [
                ['type' => 'role', 'value' => 'organization_owner', 'step' => 0],
                ['type' => 'role', 'value' => 'admin', 'step' => 0],
            ],
            'dedup_minutes' => 0,
        ],

        'procurement.po.submitted' => [
            'class' => \App\Notifications\Procurement\PoSubmittedNotification::class,
            'priority' => 'medium',
            'category' => 'financial',
            'description' => 'Purchase order submitted to supplier',
            'default_recipients' => [
                ['type' => 'role', 'value' => 'organization_owner', 'step' => 0],
                ['type' => 'role', 'value' => 'admin', 'step' => 0],
            ],
            'dedup_minutes' => 0,
        ],

        'procurement.po.received' => [
            'class' => \App\Notifications\Procurement\PoReceivedNotification::class,
            'priority' => 'medium',
            'category' => 'inventory',
            'description' => 'Purchase order received (full or partial)',
            'default_recipients' => [
                ['type' => 'role', 'value' => 'organization_owner', 'step' => 0],
                ['type' => 'role', 'value' => 'admin', 'step' => 0],
            ],
            'dedup_minutes' => 0,
        ],

        'procurement.po.overdue' => [
            'class' => \App\Notifications\Procurement\PoOverdueNotification::class,
            'priority' => 'high',
            'category' => 'financial',
            'description' => 'Purchase order past expected delivery date',
            'default_recipients' => [
                ['type' => 'role', 'value' => 'organization_owner', 'step' => 0],
                ['type' => 'role', 'value' => 'admin', 'step' => 0],
            ],
            'dedup_minutes' => 1440,
        ],

        'fulfillment.order.shipped' => [
            'class' => \App\Notifications\Fulfillment\OrderShippedNotification::class,
            'priority' => 'low',
            'category' => 'orders',
            'description' => 'Order marked shipped (internal)',
            'default_recipients' => [
                ['type' => 'role', 'value' => 'organization_owner', 'step' => 0],
            ],
            'dedup_minutes' => 0,
        ],

        'fulfillment.order.shipped_to_buyer' => [
            'class' => \App\Notifications\Customer\OrderShippedToBuyer::class,
            'priority' => 'medium',
            'category' => 'customer',
            'description' => 'Shipping confirmation sent to buyer',
            'default_recipients' => [
                ['type' => 'customer', 'value' => 'order.buyer_email', 'step' => 0],
            ],
            'dedup_minutes' => 0,
        ],

        'fulfillment.return.approved' => [
            'class' => \App\Notifications\Fulfillment\ReturnApprovedNotification::class,
            'priority' => 'medium',
            'category' => 'orders',
            'description' => 'Return request approved (internal)',
            'default_recipients' => [
                ['type' => 'role', 'value' => 'organization_owner', 'step' => 0],
                ['type' => 'role', 'value' => 'admin', 'step' => 0],
            ],
            'dedup_minutes' => 0,
        ],

        'fulfillment.return.approved_to_buyer' => [
            'class' => \App\Notifications\Customer\ReturnApprovedToBuyer::class,
            'priority' => 'medium',
            'category' => 'customer',
            'description' => 'Return approval sent to buyer',
            'default_recipients' => [
                ['type' => 'customer', 'value' => 'order.buyer_email', 'step' => 0],
            ],
            'dedup_minutes' => 0,
        ],

        'fulfillment.return.received' => [
            'class' => \App\Notifications\Fulfillment\ReturnReceivedNotification::class,
            'priority' => 'medium',
            'category' => 'orders',
            'description' => 'Returned package received at warehouse',
            'default_recipients' => [
                ['type' => 'role', 'value' => 'organization_owner', 'step' => 0],
                ['type' => 'role', 'value' => 'admin', 'step' => 0],
            ],
            'dedup_minutes' => 0,
        ],

        'fulfillment.refund.processed' => [
            'class' => \App\Notifications\Fulfillment\RefundProcessedNotification::class,
            'priority' => 'medium',
            'category' => 'financial',
            'description' => 'Refund processed (internal)',
            'default_recipients' => [
                ['type' => 'role', 'value' => 'organization_owner', 'step' => 0],
                ['type' => 'role', 'value' => 'admin', 'step' => 0],
            ],
            'dedup_minutes' => 0,
        ],

        'fulfillment.refund.processed_to_buyer' => [
            'class' => \App\Notifications\Customer\RefundProcessedToBuyer::class,
            'priority' => 'medium',
            'category' => 'customer',
            'description' => 'Refund confirmation sent to buyer',
            'default_recipients' => [
                ['type' => 'customer', 'value' => 'order.buyer_email', 'step' => 0],
            ],
            'dedup_minutes' => 0,
        ],

        'fulfillment.routing.failed' => [
            'class' => \App\Notifications\Fulfillment\FulfillmentRoutingFailedNotification::class,
            'priority' => 'high',
            'category' => 'orders',
            'description' => 'Auto-routing could not select fulfillment location',
            'default_recipients' => [
                ['type' => 'role', 'value' => 'organization_owner', 'step' => 0],
                ['type' => 'role', 'value' => 'admin', 'step' => 0],
            ],
            'dedup_minutes' => 60,
        ],

        'analytics.health_score.alert' => [
            'class' => \App\Notifications\Analytics\HealthScoreAlertNotification::class,
            'priority' => 'high',
            'category' => 'financial',
            'description' => 'Business health score dimension breached threshold',
            'default_recipients' => [
                ['type' => 'role', 'value' => 'organization_owner', 'step' => 0],
            ],
            'dedup_minutes' => 1440,
        ],

        'analytics.stockout.warning' => [
            'class' => \App\Notifications\Analytics\StockoutWarningNotification::class,
            'priority' => 'medium',
            'category' => 'inventory',
            'description' => 'Forecast predicts stockout within configured window',
            'default_recipients' => [
                ['type' => 'role', 'value' => 'organization_owner', 'step' => 0],
                ['type' => 'role', 'value' => 'admin', 'step' => 0],
            ],
            'dedup_minutes' => 1440,
        ],

        'analytics.reorder.suggestions' => [
            'class' => \App\Notifications\Analytics\ReorderSuggestionNotification::class,
            'priority' => 'low',
            'category' => 'inventory',
            'description' => 'New reorder suggestions generated (daily digest)',
            'default_recipients' => [
                ['type' => 'role', 'value' => 'organization_owner', 'step' => 0],
            ],
            'dedup_minutes' => 1440,
        ],

        'pricing.guard.hit' => [
            'class' => \App\Notifications\Pricing\PricingGuardHitNotification::class,
            'priority' => 'low',
            'category' => 'financial',
            'description' => 'Repricing engine clamped a proposed price by floor/ceiling/cost guard',
            'default_recipients' => [
                ['type' => 'role', 'value' => 'organization_owner', 'step' => 0],
            ],
            'dedup_minutes' => 1440,
        ],

        'security.login.threshold_breached' => [
            'class' => \App\Notifications\Security\FailedLoginThresholdNotification::class,
            'priority' => 'high',
            'category' => 'system',
            'description' => 'Account locked after repeated failed login attempts',
            'default_recipients' => [
                ['type' => 'role', 'value' => 'organization_owner', 'step' => 0],
                ['type' => 'role', 'value' => 'admin', 'step' => 0],
            ],
            'dedup_minutes' => 60,
        ],

        'security.login.new_device' => [
            'class' => \App\Notifications\Security\NewDeviceLoginNotification::class,
            'priority' => 'medium',
            'category' => 'system',
            'description' => 'User signed in from a new IP / user agent',
            'default_recipients' => [
                ['type' => 'role', 'value' => 'organization_owner', 'step' => 0],
            ],
            'dedup_minutes' => 1440,
        ],

        'security.role.changed' => [
            'class' => \App\Notifications\Security\RoleChangedNotification::class,
            'priority' => 'medium',
            'category' => 'system',
            'description' => 'A user role was assigned or removed',
            'default_recipients' => [
                ['type' => 'role', 'value' => 'organization_owner', 'step' => 0],
            ],
            'dedup_minutes' => 60,
        ],

    ],

    'defaults' => [
        'dedup_minutes' => 60,
    ],
];
