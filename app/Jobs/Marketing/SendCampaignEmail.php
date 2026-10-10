<?php

namespace App\Jobs\Marketing;

use App\Mail\CampaignEmail;
use App\Models\Marketing\EmailCampaignSend;
use App\Services\Marketing\EmailTemplateRenderer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendCampaignEmail implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public array $backoff = [60, 120, 300];

    public int $timeout = 30;

    public function __construct(
        protected string $sendId
    ) {}

    public function handle(EmailTemplateRenderer $renderer): void
    {
        $send = EmailCampaignSend::with(['campaign', 'customer', 'order'])->find($this->sendId);

        if (! $send || $send->status !== EmailCampaignSend::STATUS_PENDING) {
            return;
        }

        $campaign = $send->campaign;

        if (! $campaign || ! $campaign->is_active) {
            return;
        }

        $customer = $send->customer;

        if (! $customer || ! $customer->email) {
            $send->update(['status' => EmailCampaignSend::STATUS_BOUNCED]);

            return;
        }

        $variables = [];
        if ($send->order) {
            $variables = $renderer->buildVariablesForOrder($send->order);
        }

        $renderedSubject = $renderer->render($campaign->subject_template, $variables);
        $renderedBody = $renderer->render($campaign->body_template, $variables);

        Mail::to($customer->email)->send(
            new CampaignEmail($renderedSubject, $renderedBody, $campaign, $customer)
        );

        $send->update([
            'status' => EmailCampaignSend::STATUS_SENT,
            'sent_at' => now(),
        ]);

        $campaign->increment('sent_count');

        Log::info('Campaign email sent', [
            'send_id' => $send->id,
            'campaign_id' => $campaign->id,
            'customer_email' => $customer->email,
        ]);
    }

    public function tags(): array
    {
        return ['email-send', 'send:'.$this->sendId];
    }

    public function failed(\Throwable $exception): void
    {
        $send = EmailCampaignSend::find($this->sendId);

        if ($send) {
            $send->update(['status' => EmailCampaignSend::STATUS_BOUNCED]);
        }

        Log::error('Campaign email send failed', [
            'send_id' => $this->sendId,
            'error' => $exception->getMessage(),
        ]);
    }
}
