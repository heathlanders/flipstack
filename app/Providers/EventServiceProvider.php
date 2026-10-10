<?php

namespace App\Providers;

use App\Events\Inventory\BulkOperationCompleted;
use App\Events\Inventory\LowStockAlert;
use App\Events\Inventory\MovementCompleted;
use App\Events\Inventory\StockUpdated;
use App\Listeners\Auth\LogAuthAudit;
use App\Listeners\Inventory\CheckLowStockThreshold;
use App\Listeners\Inventory\NotifyStockChange;
use App\Listeners\Inventory\SendBulkOperationNotification;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Event;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],

        // Auth audit (login/logout/failed/lockout/password-reset -> user_activity_logs)
        Login::class => [[LogAuthAudit::class, 'login']],
        Logout::class => [[LogAuthAudit::class, 'logout']],
        Failed::class => [[LogAuthAudit::class, 'failed']],
        Lockout::class => [[LogAuthAudit::class, 'lockout']],
        PasswordReset::class => [[LogAuthAudit::class, 'passwordReset']],

        // Inventory Events
        StockUpdated::class => [
            CheckLowStockThreshold::class,
            // Add more listeners as needed:
            // UpdateSearchIndex::class,
            // LogStockChange::class,
        ],

        LowStockAlert::class => [
            NotifyStockChange::class,
            // Add more listeners as needed:
            // CreateRestockSuggestion::class,
            // NotifySupplier::class,
        ],

        MovementCompleted::class => [
            // Add listeners as needed:
            // UpdateInventoryLevels::class,
            // LogMovement::class,
        ],

        BulkOperationCompleted::class => [
            SendBulkOperationNotification::class,
        ],
    ];

    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        //
    }

    /**
     * Determine if events and listeners should be automatically discovered.
     */
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
