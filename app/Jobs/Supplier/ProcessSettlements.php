<?php

namespace App\Jobs\Supplier;

use App\Services\Supplier\SettlementProcessorService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessSettlements implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 1800; // 30 minutes

    protected int $organizationId;

    protected bool $autoApprove;

    /**
     * Create a new job instance.
     */
    public function __construct(int $organizationId, bool $autoApprove = false)
    {
        $this->organizationId = $organizationId;
        $this->autoApprove = $autoApprove;
    }

    /**
     * Execute the job.
     */
    public function handle(SettlementProcessorService $processor): void
    {
        Log::info('Starting settlement processing', [
            'organization_id' => $this->organizationId,
            'auto_approve' => $this->autoApprove,
        ]);

        $results = $processor->processScheduledSettlements($this->organizationId);

        Log::info('Completed settlement processing', [
            'organization_id' => $this->organizationId,
            'results' => $results,
        ]);

        // If auto-approve is enabled, approve all pending settlements
        if ($this->autoApprove && $results['processed'] > 0) {
            foreach ($results['settlements'] as $settlementData) {
                try {
                    $settlement = \App\Models\Supplier\Settlement::find($settlementData['settlement_id']);
                    if ($settlement) {
                        $processor->submitForReview($settlement);
                        // Auto-approve would need a system user ID
                        // $processor->approveSettlement($settlement, $systemUserId);
                    }
                } catch (\Exception $e) {
                    Log::error('Error auto-approving settlement', [
                        'settlement_id' => $settlementData['settlement_id'],
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }
    }

    /**
     * Get the tags that should be assigned to the job.
     */
    public function tags(): array
    {
        return [
            'settlements',
            'organization:'.$this->organizationId,
        ];
    }
}
