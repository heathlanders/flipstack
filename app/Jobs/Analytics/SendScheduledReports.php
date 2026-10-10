<?php

namespace App\Jobs\Analytics;

use App\Mail\ScheduledReportMail;
use App\Models\Analytics\ScheduledReport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendScheduledReports implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;

    public function __construct(protected ?string $scheduledReportId = null) {}

    public function handle(): void
    {
        $query = ScheduledReport::query()->where('is_active', true);

        if ($this->scheduledReportId) {
            $query->where('id', $this->scheduledReportId);
        } else {
            $query->where(function ($q) {
                $q->whereNull('next_due_at')->orWhere('next_due_at', '<=', now());
            });
        }

        $reports = $query->get();

        foreach ($reports as $report) {
            try {
                foreach ($report->recipients as $email) {
                    Mail::to($email)->queue(new ScheduledReportMail($report));
                }

                $report->last_sent_at = now();
                $report->next_due_at = $report->calculateNextDueAt(now());
                $report->save();
            } catch (\Throwable $e) {
                Log::error('Scheduled report send failed', [
                    'report_id' => $report->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
