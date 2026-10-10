<?php

namespace App\Notifications;

use App\Mail\OrganizationInvitationMail;
use App\Mail\OrganizationOwnerInvitationMail;
use App\Models\Organization\OrganizationInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class UserInvitationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected OrganizationInvitation $invitation;

    public function __construct(OrganizationInvitation $invitation)
    {
        $this->invitation = $invitation->load(['organization', 'invitedBy']);
    }

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): OrganizationInvitationMail|OrganizationOwnerInvitationMail
    {
        if ($this->invitation->isForOrganizationOwner()) {
            return (new OrganizationOwnerInvitationMail($this->invitation))
                ->to($this->invitation->email);
        }

        return (new OrganizationInvitationMail($this->invitation))
            ->to($this->invitation->email);
    }

    public function toArray($notifiable): array
    {
        return [
            'invitation_id' => $this->invitation->id,
            'organization_id' => $this->invitation->organization_id,
            'organization_name' => $this->invitation->organization->name ?? null,
            'invited_by' => $this->invitation->invitedBy->name ?? null,
            'expires_at' => $this->invitation->expires_at->toIso8601String(),
        ];
    }
}
