<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Supplier Workflow Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains all configuration options for the supplier workflow
    | system including tiers, commissions, settlements, and performance metrics.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Default Settings
    |--------------------------------------------------------------------------
    */

    'defaults' => [
        // Default tier for new suppliers
        'tier' => 'silver',

        // Default commission structure
        'commission_structure' => 'standard',

        // Default settlement frequency
        'settlement_frequency' => 'bi_weekly',

        // Default payment method
        'payment_method' => 'bank_transfer',
    ],

    /*
    |--------------------------------------------------------------------------
    | Tier Configuration
    |--------------------------------------------------------------------------
    */

    'tiers' => [
        // Enable automatic tier promotions/demotions
        'auto_promotion' => env('SUPPLIER_AUTO_PROMOTION', true),
        'auto_demotion' => env('SUPPLIER_AUTO_DEMOTION', true),

        // Evaluation period for tier changes (in days)
        'evaluation_period' => env('SUPPLIER_TIER_EVALUATION_PERIOD', 30),

        // Minimum time in tier before promotion/demotion (in days)
        'minimum_tenure' => env('SUPPLIER_TIER_MIN_TENURE', 30),

        // Number of consecutive periods required for tier change
        'consecutive_periods_required' => env('SUPPLIER_TIER_CONSECUTIVE_PERIODS', 2),

        // Grace period for score drops before demotion (in days)
        'demotion_grace_period' => env('SUPPLIER_DEMOTION_GRACE_PERIOD', 14),

        // Notification settings
        'notifications' => [
            'promotion' => true,
            'demotion' => true,
            'at_risk' => true,
            'at_risk_threshold' => 5, // Points from demotion threshold
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Commission Configuration
    |--------------------------------------------------------------------------
    */

    'commission' => [
        // Minimum commission rate (percentage)
        'min_rate' => env('SUPPLIER_MIN_COMMISSION', 5),

        // Maximum commission rate (percentage)
        'max_rate' => env('SUPPLIER_MAX_COMMISSION', 40),

        // Round commission amounts to this precision
        'precision' => 2,

        // Apply commission to returns
        'apply_to_returns' => true,

        // Refund commission on returns
        'refund_on_return' => true,

        // Commission calculation rounding mode
        'rounding_mode' => PHP_ROUND_HALF_UP,
    ],

    /*
    |--------------------------------------------------------------------------
    | Settlement Configuration
    |--------------------------------------------------------------------------
    */

    'settlements' => [
        // Minimum amount for settlement generation
        'minimum_amount' => env('SUPPLIER_SETTLEMENT_MINIMUM', 10.00),

        // Hold period for sales before settlement (in days)
        'hold_period' => env('SUPPLIER_SETTLEMENT_HOLD_PERIOD', 7),

        // Include pending sales in settlement calculations
        'include_pending' => false,

        // Auto-approve settlements under this amount
        'auto_approve_threshold' => env('SUPPLIER_SETTLEMENT_AUTO_APPROVE', 500.00),

        // Number of days to process approved settlements
        'processing_days' => env('SUPPLIER_SETTLEMENT_PROCESSING_DAYS', 3),

        // Settlement number format
        'number_format' => 'STL-{YEAR}{MONTH}-{SEQUENCE}',

        // Payment methods available
        'payment_methods' => [
            'bank_transfer' => [
                'name' => 'Bank Transfer (ACH)',
                'processing_days' => 3,
                'fee' => 0,
                'min_amount' => 0,
            ],
            'paypal' => [
                'name' => 'PayPal',
                'processing_days' => 1,
                'fee' => 0.25,
                'min_amount' => 0,
            ],
            'check' => [
                'name' => 'Check',
                'processing_days' => 7,
                'fee' => 0,
                'min_amount' => 50,
            ],
            'wire' => [
                'name' => 'Wire Transfer',
                'processing_days' => 1,
                'fee' => 25,
                'min_amount' => 1000,
            ],
        ],

        // Dispute settings
        'disputes' => [
            'enabled' => true,
            'window_days' => 30, // Days after settlement to dispute
            'auto_resolve_days' => 14, // Days to auto-resolve if no response
        ],

        // Tax document settings
        'tax' => [
            '1099_threshold' => 600, // US IRS reporting threshold
            'require_w9' => true,
            'withhold_without_w9' => true,
            'withholding_rate' => 24, // Backup withholding rate
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Performance Metrics Configuration
    |--------------------------------------------------------------------------
    */

    'performance' => [
        // Weights for overall score calculation
        'weights' => [
            'sales' => 0.30,          // 30%
            'quality' => 0.25,         // 25%
            'compliance' => 0.20,      // 20%
            'customer_satisfaction' => 0.25, // 25%
        ],

        // Metrics calculation frequency
        'calculation_frequency' => 'daily', // daily, weekly, monthly

        // Historical metrics retention (in days)
        'retention_days' => env('SUPPLIER_METRICS_RETENTION', 365),

        // Thresholds for metric categorization
        'thresholds' => [
            'excellent' => 90,
            'good' => 75,
            'fair' => 60,
            'poor' => 0,
        ],

        // Specific metric targets
        'targets' => [
            'approval_rate' => 85,
            'sell_through_rate' => 50,
            'return_rate' => 5, // Maximum
            'response_time_hours' => 24, // Maximum
            'customer_rating' => 4.5,
        ],

        // Benchmarking settings
        'benchmarking' => [
            'enabled' => true,
            'anonymized' => true,
            'percentile_groups' => [10, 25, 50, 75, 90],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Notification Configuration
    |--------------------------------------------------------------------------
    */

    'notifications' => [
        // Notification channels
        'channels' => ['database', 'mail'],

        // Email notifications
        'email' => [
            'settlement_generated' => true,
            'settlement_paid' => true,
            'tier_change' => true,
            'performance_report' => true,
            'performance_warning' => true,
            'contract_expiring' => true,
            'document_required' => true,
        ],

        // In-app notifications
        'database' => [
            'all' => true,
        ],

        // SMS notifications (if enabled)
        'sms' => [
            'settlement_paid' => false,
            'urgent_only' => true,
        ],

        // Digest settings
        'digest' => [
            'enabled' => true,
            'frequency' => 'weekly', // daily, weekly, monthly
            'day_of_week' => 1, // Monday
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Onboarding Configuration
    |--------------------------------------------------------------------------
    */

    'onboarding' => [
        // Required steps for onboarding
        'required_steps' => [
            'profile_complete',
            'bank_account_added',
            'w9_submitted',
            'terms_accepted',
            'first_product_submitted',
        ],

        // Optional steps
        'optional_steps' => [
            'photo_id_uploaded',
            'business_verification',
            'training_completed',
        ],

        // Onboarding timeout (in days)
        'timeout_days' => 30,

        // Reminder intervals (in days)
        'reminder_intervals' => [7, 14, 21, 28],
    ],

    /*
    |--------------------------------------------------------------------------
    | Document Configuration
    |--------------------------------------------------------------------------
    */

    'documents' => [
        // Maximum file size (in MB)
        'max_size' => 10,

        // Allowed file types
        'allowed_types' => ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx'],

        // Document types
        'types' => [
            'w9' => [
                'name' => 'W-9 Form',
                'required' => true,
                'expiration_days' => null,
            ],
            'photo_id' => [
                'name' => 'Photo ID',
                'required' => false,
                'expiration_days' => 1825, // 5 years
            ],
            'business_license' => [
                'name' => 'Business License',
                'required' => false,
                'expiration_days' => 365,
            ],
            'contract' => [
                'name' => 'Signed Contract',
                'required' => true,
                'expiration_days' => 365,
            ],
        ],

        // Storage settings
        'storage' => [
            'disk' => 's3',
            'path' => 'supplier-documents',
            'visibility' => 'private',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | API Configuration
    |--------------------------------------------------------------------------
    */

    'api' => [
        // Rate limiting
        'rate_limit' => [
            'per_minute' => 60,
            'per_day' => 10000,
        ],

        // API scopes
        'scopes' => [
            'supplier:read' => 'Read supplier profile and data',
            'supplier:write' => 'Update supplier profile',
            'settlements:read' => 'View settlements',
            'performance:read' => 'View performance metrics',
            'products:read' => 'View submitted products',
            'products:write' => 'Submit and update products',
        ],

        // Webhook events
        'webhooks' => [
            'settlement.created',
            'settlement.paid',
            'tier.changed',
            'performance.calculated',
            'product.approved',
            'product.rejected',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Portal Configuration
    |--------------------------------------------------------------------------
    */

    'portal' => [
        // Features enabled in supplier portal
        'features' => [
            'dashboard' => true,
            'performance' => true,
            'settlements' => true,
            'products' => true,
            'analytics' => true,
            'documents' => true,
            'support' => true,
        ],

        // Dashboard widgets
        'dashboard_widgets' => [
            'quick_stats',
            'tier_status',
            'performance_chart',
            'recent_settlements',
            'notifications',
            'quick_links',
        ],

        // Session timeout (in minutes)
        'session_timeout' => 120,

        // Theme options
        'themes' => ['light', 'dark', 'system'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Audit & Logging
    |--------------------------------------------------------------------------
    */

    'audit' => [
        // Enable activity logging
        'enabled' => true,

        // Events to log
        'events' => [
            'tier_change',
            'commission_change',
            'settlement_created',
            'settlement_paid',
            'status_change',
            'document_upload',
            'profile_update',
            'login',
        ],

        // Retention period (in days)
        'retention_days' => 365,

        // Log to separate channel
        'channel' => 'supplier-audit',
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Configuration
    |--------------------------------------------------------------------------
    */

    'cache' => [
        // Cache TTL for various data (in seconds)
        'ttl' => [
            'tiers' => 3600,           // 1 hour
            'commission_structures' => 3600,
            'performance_metrics' => 300, // 5 minutes
            'supplier_stats' => 300,
            'dashboard_data' => 60,     // 1 minute
        ],

        // Cache key prefix
        'prefix' => 'supplier_',
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue Configuration
    |--------------------------------------------------------------------------
    */

    'queue' => [
        // Queue names for different jobs
        'queues' => [
            'settlements' => 'settlements',
            'metrics' => 'default',
            'notifications' => 'notifications',
            'reports' => 'reports',
        ],

        // Job retry settings
        'retry' => [
            'max_attempts' => 3,
            'backoff' => [60, 300, 900], // 1 min, 5 min, 15 min
        ],
    ],

];
