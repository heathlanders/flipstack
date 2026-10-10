<?php

namespace Tests\Feature\Notifications;

use App\Mail\OrganizationInvitationMail;
use App\Mail\OrganizationOwnerInvitationMail;
use App\Models\Organization\Organization;
use App\Models\Organization\OrganizationInvitation;
use App\Models\User\Role;
use App\Models\User\User;
use App\Notifications\UserInvitationNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserInvitationNotificationTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;

    private User $inviter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::factory()->create();
        $this->inviter = User::factory()->create([
            'organization_id' => $this->organization->id,
        ]);
    }

    public function test_regular_invitation_uses_organization_invitation_mail(): void
    {
        $role = Role::factory()->create(['slug' => 'buyer']);

        $invitation = OrganizationInvitation::create([
            'organization_id' => $this->organization->id,
            'invited_by_user_id' => $this->inviter->id,
            'email' => 'member@example.com',
            'roles' => [$role->id],
        ]);

        $notification = new UserInvitationNotification($invitation);
        $mailable = $notification->toMail($invitation);

        $this->assertInstanceOf(OrganizationInvitationMail::class, $mailable);
    }

    public function test_owner_invitation_uses_organization_owner_invitation_mail(): void
    {
        $role = Role::factory()->create(['slug' => 'organization_owner']);

        $invitation = OrganizationInvitation::create([
            'organization_id' => $this->organization->id,
            'invited_by_user_id' => $this->inviter->id,
            'email' => 'owner@example.com',
            'roles' => [$role->id],
        ]);

        $notification = new UserInvitationNotification($invitation);
        $mailable = $notification->toMail($invitation);

        $this->assertInstanceOf(OrganizationOwnerInvitationMail::class, $mailable);
    }

    public function test_owner_invitation_with_multiple_roles_uses_owner_mail(): void
    {
        $ownerRole = Role::factory()->create(['slug' => 'organization_owner']);
        $buyerRole = Role::factory()->create(['slug' => 'buyer']);

        $invitation = OrganizationInvitation::create([
            'organization_id' => $this->organization->id,
            'invited_by_user_id' => $this->inviter->id,
            'email' => 'owner@example.com',
            'roles' => [$ownerRole->id, $buyerRole->id],
        ]);

        $notification = new UserInvitationNotification($invitation);
        $mailable = $notification->toMail($invitation);

        $this->assertInstanceOf(OrganizationOwnerInvitationMail::class, $mailable);
    }

    public function test_invitation_with_empty_roles_uses_regular_mail(): void
    {
        $invitation = OrganizationInvitation::create([
            'organization_id' => $this->organization->id,
            'invited_by_user_id' => $this->inviter->id,
            'email' => 'noroles@example.com',
            'roles' => [],
        ]);

        $notification = new UserInvitationNotification($invitation);
        $mailable = $notification->toMail($invitation);

        $this->assertInstanceOf(OrganizationInvitationMail::class, $mailable);
    }

    public function test_notification_sends_to_invitation_email(): void
    {
        $role = Role::factory()->create(['slug' => 'buyer']);

        $invitation = OrganizationInvitation::create([
            'organization_id' => $this->organization->id,
            'invited_by_user_id' => $this->inviter->id,
            'email' => 'recipient@example.com',
            'roles' => [$role->id],
        ]);

        $notification = new UserInvitationNotification($invitation);
        $mailable = $notification->toMail($invitation);

        $toAddresses = collect($mailable->to)->pluck('address')->toArray();
        $this->assertEquals(['recipient@example.com'], $toAddresses);
    }

    public function test_notification_via_mail_channel(): void
    {
        $invitation = OrganizationInvitation::create([
            'organization_id' => $this->organization->id,
            'invited_by_user_id' => $this->inviter->id,
            'email' => 'test@example.com',
            'roles' => [],
        ]);

        $notification = new UserInvitationNotification($invitation);

        $this->assertEquals(['mail'], $notification->via($invitation));
    }

    public function test_notification_to_array_returns_expected_data(): void
    {
        $invitation = OrganizationInvitation::create([
            'organization_id' => $this->organization->id,
            'invited_by_user_id' => $this->inviter->id,
            'email' => 'test@example.com',
            'roles' => [],
        ]);

        $notification = new UserInvitationNotification($invitation);
        $data = $notification->toArray($invitation);

        $this->assertEquals($invitation->id, $data['invitation_id']);
        $this->assertEquals($this->organization->id, $data['organization_id']);
        $this->assertEquals($this->organization->name, $data['organization_name']);
        $this->assertEquals($this->inviter->name, $data['invited_by']);
        $this->assertArrayHasKey('expires_at', $data);
    }
}
