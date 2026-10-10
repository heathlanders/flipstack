<?php

namespace App\Mail;

use App\Models\Customer\Customer;
use App\Models\Marketing\EmailCampaign;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;

class CampaignEmail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        protected string $renderedSubject,
        protected string $renderedBody,
        protected EmailCampaign $campaign,
        protected Customer $customer,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->renderedSubject,
        );
    }

    public function headers(): Headers
    {
        $headers = [];

        // List-Unsubscribe header for CAN-SPAM compliance
        if ($this->campaign->organization_id) {
            $headers['List-Unsubscribe'] = '<mailto:unsubscribe@'
                .config('app.url', 'localhost').'?subject=unsubscribe&body='.$this->customer->id.'>';
        }

        return new Headers(
            text: $headers,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.campaign',
            with: [
                'body' => $this->renderedBody,
                'campaignName' => $this->campaign->name,
                'customerName' => $this->customer->name ?? $this->customer->email,
            ],
        );
    }
}
