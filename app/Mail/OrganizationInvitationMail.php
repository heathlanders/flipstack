<?php

namespace App\Mail;

use App\Models\Organization\OrganizationInvitation;
use App\Models\User\Role;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrganizationInvitationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public OrganizationInvitation $invitation;

    public function __construct(OrganizationInvitation $invitation)
    {
        $this->invitation = $invitation->load(['organization', 'invitedBy']);
    }

    public function envelope(): Envelope
    {
        $organizationName = $this->invitation->organization->name ?? 'the organization';

        return new Envelope(
            subject: "You've been invited to join {$organizationName}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.invitation.organization-invitation',
            with: [
                'organization' => $this->invitation->organization,
                'inviterName' => $this->invitation->invitedBy->name ?? 'A team member',
                'acceptUrl' => route('invitations.accept', ['token' => $this->invitation->token]),
                'expiresAt' => $this->invitation->expires_at->format('F j, Y \a\t g:i A'),
                'roleNames' => $this->resolveRoleNames(),
            ],
        );
    }

    protected function resolveRoleNames(): array
    {
        $roleIds = $this->invitation->roles ?? [];

        if (empty($roleIds)) {
            return [];
        }

        return Role::whereIn('id', $roleIds)
            ->select('id', 'name', 'description')
            ->get()
            ->map(function ($role) {
                return [
                    'name' => $role->name,
                    'description' => $role->description,
                ];
            })
            ->toArray();
    }

    public function attachments(): array
    {
        return [];
    }
}
