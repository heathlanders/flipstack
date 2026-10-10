<?php

namespace App\Mail;

use App\Models\User\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class NotificationDigestMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public Collection $notifications,
        public Collection $grouped,
        public string $frequency,
    ) {}

    public function envelope(): Envelope
    {
        $label = ucfirst($this->frequency);
        $count = $this->notifications->count();

        return new Envelope(
            subject: "{$label} notification digest ({$count} unread)",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.notifications.digest',
            with: [
                'user' => $this->user,
                'notifications' => $this->notifications,
                'grouped' => $this->grouped,
                'frequency' => $this->frequency,
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
