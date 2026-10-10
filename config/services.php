<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Vision Service Configuration
    |--------------------------------------------------------------------------
    |
    | Configure which vision provider to use for product image analysis:
    | - 'local': Free, uses Tesseract OCR on your server (no API costs)
    | - 'cloud': Paid, uses Anthropic Claude Vision API (better accuracy)
    |
    */
    'vision' => [
        'provider' => env('VISION_PROVIDER', 'local'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Anthropic AI Service (Cloud Vision)
    |--------------------------------------------------------------------------
    |
    | Only required if using 'cloud' provider
    |
    */
    'anthropic' => [
        'api_key' => env('ANTHROPIC_API_KEY'),
        'api_url' => env('ANTHROPIC_API_URL', 'https://api.anthropic.com/v1/messages'),
        'model' => env('ANTHROPIC_MODEL', 'claude-sonnet-4-20250514'),
        'max_tokens' => env('ANTHROPIC_MAX_TOKENS', 2000),
    ],

    /*
    |--------------------------------------------------------------------------
    | eBay API Configuration
    |--------------------------------------------------------------------------
    |
    | App-level eBay OAuth credentials for the integration.
    | Users don't need to provide these - they just click "Connect to eBay".
    | Get credentials from: https://developer.ebay.com/
    |
    | The app automatically uses sandbox or production based on APP_ENV,
    | or you can override with EBAY_USE_SANDBOX.
    |
    */
    'ebay' => [
        // Legacy fields for UPC lookup (kept for backwards compatibility)
        'app_id' => env('EBAY_APP_ID'),
        'cert_id' => env('EBAY_CERT_ID'),
        'dev_id' => env('EBAY_DEV_ID'),
        'marketplace' => env('EBAY_MARKETPLACE', 'EBAY_US'),

        // App-wide ECN verification tokens used by the legacy /api/webhooks/ebay
        // endpoint (Marketplace Account Deletion is registered once per app/env).
        // Per-credential tokens live in IntegrationCredential.config.
        'app_notification_token' => env('EBAY_APP_NOTIFICATION_TOKEN'),
        'sandbox_app_notification_token' => env('EBAY_SANDBOX_NOTIFICATION_TOKEN'),
        'production_app_notification_token' => env('EBAY_PRODUCTION_NOTIFICATION_TOKEN', env('EBAY_APP_NOTIFICATION_TOKEN')),

        // Sandbox credentials (for development/testing)
        'sandbox' => [
            'client_id' => env('EBAY_SANDBOX_CLIENT_ID'),
            'client_secret' => env('EBAY_SANDBOX_CLIENT_SECRET'),
            'ru_name' => env('EBAY_SANDBOX_RU_NAME'),
        ],

        // Production credentials (for live eBay accounts)
        'production' => [
            'client_id' => env('EBAY_PRODUCTION_CLIENT_ID'),
            'client_secret' => env('EBAY_PRODUCTION_CLIENT_SECRET'),
            'ru_name' => env('EBAY_PRODUCTION_RU_NAME'),
        ],

        // Automatically use sandbox unless in production environment
        // Override with EBAY_USE_SANDBOX=true or EBAY_USE_SANDBOX=false
        'use_sandbox' => env('EBAY_USE_SANDBOX', env('APP_ENV') !== 'production'),
    ],

    /*
    |--------------------------------------------------------------------------
    | UPC Database API Configuration
    |--------------------------------------------------------------------------
    |
    | Fallback for UPC lookups when eBay doesn't have product data
    | Get API key from: https://www.barcodelookup.com/
    | - Free tier: 100 lookups/day
    | - Paid tier: $10/month unlimited
    |
    */
    'upc_database' => [
        'api_key' => env('UPC_DATABASE_API_KEY'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Barcode Spider API Configuration
    |--------------------------------------------------------------------------
    */
    'barcode_spider' => [
        'api_key' => env('BARCODE_SPIDER_API_KEY'),
    ],

    /*
    |--------------------------------------------------------------------------
    | EasyPost Shipping API Configuration
    |--------------------------------------------------------------------------
    |
    | EasyPost credentials are stored per-organization in integration_credentials.
    | These are fallback/global settings only.
    |
    */
    'shipstation' => [
        'webhook_secret' => env('SHIPSTATION_WEBHOOK_SECRET'),
    ],

    'shippingeasy' => [
        'webhook_secret' => env('SHIPPINGEASY_WEBHOOK_SECRET'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Shopify API Configuration
    |--------------------------------------------------------------------------
    |
    | Shopify app credentials for the integration.
    | Get credentials from: https://partners.shopify.com/
    |
    */
    'shopify' => [
        // Legacy single-app fields (fallback when env-specific keys not set).
        'api_key' => env('SHOPIFY_API_KEY'),
        'api_secret' => env('SHOPIFY_API_SECRET'),
        'api_version' => env('SHOPIFY_API_VERSION', '2026-01'),
        'scopes' => env('SHOPIFY_SCOPES', 'read_products,write_products,read_inventory,write_inventory,read_orders,read_locations,write_fulfillments'),
        'redirect_uri' => env('SHOPIFY_REDIRECT_URI'),

        // Sandbox: separate Shopify Partner app for develop / dev stores.
        'sandbox' => [
            'api_key' => env('SHOPIFY_SANDBOX_API_KEY'),
            'api_secret' => env('SHOPIFY_SANDBOX_API_SECRET'),
        ],

        // Production: live Shopify Partner app.
        'production' => [
            'api_key' => env('SHOPIFY_PRODUCTION_API_KEY', env('SHOPIFY_API_KEY')),
            'api_secret' => env('SHOPIFY_PRODUCTION_API_SECRET', env('SHOPIFY_API_SECRET')),
        ],

        // Auto-select sandbox unless APP_ENV=production. Override with SHOPIFY_USE_SANDBOX.
        'use_sandbox' => env('SHOPIFY_USE_SANDBOX', env('APP_ENV') !== 'production'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Amazon SP-API Configuration
    |--------------------------------------------------------------------------
    |
    | LWA (Login With Amazon) credentials for the Selling Partner API.
    | Register an SP-API app at https://developer.amazon.com/.
    |
    | Auto-selects sandbox unless APP_ENV=production. Override with
    | AMAZON_USE_SANDBOX=true|false. AWS sigv4/STS is no longer required —
    | SP-API has been LWA-only since 2023-10-15.
    |
    */
    'amazon' => [
        'lwa_client_id' => env('AMAZON_LWA_CLIENT_ID'),
        'lwa_client_secret' => env('AMAZON_LWA_CLIENT_SECRET'),
        'redirect_uri' => env('AMAZON_REDIRECT_URI'),
        'region' => env('AMAZON_REGION', 'us-east-1'),
        'sns_arn' => env('AMAZON_SNS_ARN'),

        'sandbox' => [
            'sp_api_endpoint' => env('AMAZON_SANDBOX_ENDPOINT', 'https://sandbox.sellingpartnerapi-na.amazon.com'),
            'marketplace_id' => env('AMAZON_SANDBOX_MARKETPLACE_ID', 'ATVPDKIKX0DER'),
        ],

        'production' => [
            'sp_api_endpoint' => env('AMAZON_PRODUCTION_ENDPOINT', 'https://sellingpartnerapi-na.amazon.com'),
            'marketplace_id' => env('AMAZON_MARKETPLACE_ID', 'ATVPDKIKX0DER'),
        ],

        'use_sandbox' => env('AMAZON_USE_SANDBOX', env('APP_ENV') !== 'production'),
    ],

    'openexchangerates' => [
        'app_id' => env('OPENEXCHANGERATES_APP_ID'),
        'base_url' => 'https://openexchangerates.org/api',
    ],

    'background_removal' => [
        'url' => env('BACKGROUND_REMOVAL_URL'),
        'api_key' => env('BACKGROUND_REMOVAL_API_KEY'),
    ],

    'easypost' => [
        'mode' => env('EASYPOST_MODE', 'test'),
        'webhook_secret' => env('EASYPOST_WEBHOOK_SECRET'),
        'order_sync_interval_minutes' => env('ORDER_SYNC_INTERVAL_MINUTES', 5),
        'dropship_sla_hours' => env('DROPSHIP_SLA_HOURS', 48),
        'settlement_grace_period_days' => env('SETTLEMENT_GRACE_PERIOD_DAYS', 14),
    ],

    /*
    |--------------------------------------------------------------------------
    | USPS Web Tools (free) — used by tracking adapter
    |--------------------------------------------------------------------------
    | Register at https://www.usps.com/business/web-tools-apis/welcome.htm
    | for a USERID, then set USPS_USER_ID. No secret required for tracking.
    */
    'usps' => [
        'user_id' => env('USPS_USER_ID'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Twilio SMS Configuration
    |--------------------------------------------------------------------------
    |
    | Used by App\Notifications\Channels\SmsChannel. SDK already installed
    | (twilio/sdk in composer.json). Set the env vars below to enable.
    |
    */
    'twilio' => [
        'sid' => env('TWILIO_SID'),
        'token' => env('TWILIO_TOKEN'),
        'from' => env('TWILIO_FROM'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Firebase Cloud Messaging (mobile push)
    |--------------------------------------------------------------------------
    |
    | project_id: Firebase project ID (visible in Firebase console).
    | credentials_path: Absolute path to a service account JSON with
    |   firebase.messaging scope. Mount the file via volume in production
    |   and set FCM_CREDENTIALS_PATH to it.
    |
    */
    'fcm' => [
        'project_id' => env('FCM_PROJECT_ID'),
        'credentials_path' => env('FCM_CREDENTIALS_PATH'),
    ],
];
