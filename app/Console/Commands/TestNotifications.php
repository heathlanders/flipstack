<?php

namespace App\Console\Commands;

use App\Models\User\User;
use App\Notifications\Inventory\LowStockNotification;
use App\Notifications\Orders\NewOrderNotification;
use App\Notifications\Orders\PaymentFailedNotification;
use Illuminate\Console\Command;

class TestNotifications extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'notifications:test {--user= : User ID to send notifications to}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send test notifications to verify the notification system';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $userId = $this->option('user');

        if ($userId) {
            $user = User::find($userId);
            if (! $user) {
                $this->error("User with ID {$userId} not found!");

                return 1;
            }
        } else {
            $user = User::first();
            if (! $user) {
                $this->error('No users found! Please create a user first.');

                return 1;
            }
        }

        $this->info("Sending test notifications to: {$user->name} ({$user->email})");
        $this->newLine();

        // TEST 1: New Order Notification
        $this->info('Sending New Order notification...');
        try {
            $orderData = (object) [
                'id' => 1001,
                'order_number' => 'ORD-2025-001',
                'customer_name' => 'John Smith',
                'total' => 249.99,
                'items' => collect([
                    (object) ['name' => 'Product A', 'quantity' => 2],
                    (object) ['name' => 'Product B', 'quantity' => 1],
                ]),
            ];

            $user->notify(new NewOrderNotification($orderData));
            $this->line('   New Order notification sent!');
        } catch (\Exception $e) {
            $this->error('   Error: '.$e->getMessage());
        }

        // TEST 2: Payment Failed Notification
        $this->info('Sending Payment Failed notification...');
        try {
            $paymentData = (object) [
                'order_id' => 1002,
                'order_number' => 'ORD-2025-002',
                'amount' => 159.50,
                'customer_email' => 'customer@example.com',
                'failure_reason' => 'Insufficient funds',
                'retry_url' => route('dashboard'), // Use a real route
            ];

            $user->notify(new PaymentFailedNotification($paymentData));
            $this->line('   Payment Failed notification sent!');
        } catch (\Exception $e) {
            $this->error('   Error: '.$e->getMessage());
        }

        // TEST 3: Low Stock Alert
        $this->info('Sending Low Stock notification...');
        try {
            $productData = (object) [
                'id' => 501,
                'name' => 'iPhone 15 Pro Max',
                'sku' => 'IPH-15-PM-256',
                'current_stock' => 3,
                'minimum_stock' => 10,
                'reorder_point' => 15,
                'suggested_order_quantity' => 25,
            ];

            $user->notify(new LowStockNotification($productData));
            $this->line('   Low Stock notification sent!');
        } catch (\Exception $e) {
            $this->error('   Error: '.$e->getMessage());
        }

        $this->newLine();

        // Show statistics
        $total = $user->notifications()->count();
        $unread = $user->unreadNotifications()->count();
        $read = $user->notifications()->whereNotNull('read_at')->count();

        $this->info('Notification Statistics:');
        $this->table(
            ['Metric', 'Count'],
            [
                ['Total Notifications', $total],
                ['Unread', $unread],
                ['Read', $read],
            ]
        );

        $this->newLine();
        $this->info('Test complete! Check your notification bell in the browser.');
        $this->info('URL: http://localhost:8090');

        return 0;
    }
}
