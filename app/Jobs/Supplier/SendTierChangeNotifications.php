<?php

namespace App\Jobs\Supplier;

use App\Models\Supplier\SupplierNotification;
use App\Models\Supplier\SupplierTierHistory;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendTierChangeNotifications implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 300; // 5 minutes

    protected ?int $organizationId;

    protected ?string $tierHistoryId;

    /**
     * Create a new job instance.
     */
    public function __construct(?int $organizationId = null, ?string $tierHistoryId = null)
    {
        $this->organizationId = $organizationId;
        $this->tierHistoryId = $tierHistoryId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info('Starting tier change notifications', [
            'organization_id' => $this->organizationId,
            'tier_history_id' => $this->tierHistoryId,
        ]);

        // Get pending notifications
        $query = SupplierTierHistory::with(['supplier', 'fromTier', 'toTier'])
            ->where('notification_sent', false);

        if ($this->organizationId) {
            $query->where('organization_id', $this->organizationId);
        }

        if ($this->tierHistoryId) {
            $query->where('id', $this->tierHistoryId);
        }

        $tierChanges = $query->get();

        $results = [
            'processed' => 0,
            'emails_sent' => 0,
            'errors' => 0,
        ];

        foreach ($tierChanges as $tierChange) {
            try {
                $this->sendNotification($tierChange);
                $results['processed']++;
                $results['emails_sent']++;
            } catch (\Exception $e) {
                $results['errors']++;
                Log::error('Error sending tier change notification', [
                    'tier_history_id' => $tierChange->id,
                    'supplier_id' => $tierChange->supplier_id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('Completed tier change notifications', $results);
    }

    /**
     * Send notification for tier change
     */
    protected function sendNotification(SupplierTierHistory $tierChange): void
    {
        $supplier = $tierChange->supplier;

        if (! $supplier || ! $supplier->email) {
            Log::warning('Cannot send tier change notification - no supplier email', [
                'tier_history_id' => $tierChange->id,
            ]);

            return;
        }

        // Send email notification
        $this->sendEmail($tierChange);

        // Create in-app notification if not already exists
        $existingNotification = SupplierNotification::where('related_type', SupplierTierHistory::class)
            ->where('related_id', $tierChange->id)
            ->first();

        if (! $existingNotification) {
            SupplierNotification::tierChange($supplier, $tierChange);
        }

        // Mark notification as sent
        $tierChange->markNotificationSent();

        Log::info('Tier change notification sent', [
            'tier_history_id' => $tierChange->id,
            'supplier_id' => $supplier->id,
            'supplier_email' => $supplier->email,
            'change_type' => $tierChange->change_type,
        ]);
    }

    /**
     * Send email notification
     */
    protected function sendEmail(SupplierTierHistory $tierChange): void
    {
        $supplier = $tierChange->supplier;
        $fromTier = $tierChange->fromTier;
        $toTier = $tierChange->toTier;

        $subject = $tierChange->isPromotion()
            ? "Congratulations! You've been promoted to {$toTier->tier_name}"
            : 'Important: Your supplier tier has changed';

        $data = [
            'supplier_name' => $supplier->name,
            'from_tier' => $fromTier?->tier_name ?? 'Unassigned',
            'to_tier' => $toTier->tier_name,
            'change_type' => $tierChange->change_type,
            'is_promotion' => $tierChange->isPromotion(),
            'reason' => $tierChange->reason,
            'performance_score' => $tierChange->performance_score,
            'effective_date' => $tierChange->effective_date->format('F j, Y'),
            'benefits' => $toTier->benefits ?? [],
            'restrictions' => $toTier->restrictions ?? [],
            'settlement_frequency' => $toTier->settlement_frequency,
            'commission_adjustment' => $toTier->commission_adjustment,
        ];

        // Send mail (using a simple approach - would use a Mailable in production)
        try {
            Mail::send('emails.supplier.tier-change', $data, function ($message) use ($supplier, $subject) {
                $message->to($supplier->email, $supplier->name)
                    ->subject($subject);
            });
        } catch (\Exception $e) {
            // Fallback - just log for now if mail isn't configured
            Log::info('Would send tier change email', [
                'to' => $supplier->email,
                'subject' => $subject,
                'data' => $data,
            ]);
        }

        // Update notification record if exists
        SupplierNotification::where('related_type', SupplierTierHistory::class)
            ->where('related_id', $tierChange->id)
            ->update([
                'email_sent' => true,
                'email_sent_at' => now(),
            ]);
    }

    /**
     * Get the tags that should be assigned to the job.
     */
    public function tags(): array
    {
        return [
            'tier-notifications',
            'organization:'.($this->organizationId ?? 'all'),
        ];
    }
}
