<?php

namespace App\Mail;

use App\Models\Organization\OrganizationInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrganizationOwnerInvitationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public OrganizationInvitation $invitation;

    public function __construct(OrganizationInvitation $invitation)
    {
        $this->invitation = $invitation->load(['organization', 'invitedBy']);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your Organization Awaits - Set Up Your Account',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.invitation.organization-owner-invitation',
            with: [
                'organization' => $this->invitation->organization,
                'inviterName' => $this->invitation->invitedBy->name ?? 'A team member',
                'acceptUrl' => route('invitations.accept', ['token' => $this->invitation->token]),
                'expiresAt' => $this->invitation->expires_at->format('F j, Y \a\t g:i A'),
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
