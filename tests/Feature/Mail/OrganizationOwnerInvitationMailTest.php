<?php

namespace Tests\Feature\Mail;

use App\Mail\OrganizationOwnerInvitationMail;
use App\Models\Organization\Organization;
use App\Models\Organization\OrganizationInvitation;
use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizationOwnerInvitationMailTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;

    private User $inviter;

    private OrganizationInvitation $invitation;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::factory()->create(['name' => 'Acme Corp']);
        $this->inviter = User::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Jane Admin',
        ]);
        $this->invitation = OrganizationInvitation::create([
            'organization_id' => $this->organization->id,
            'invited_by_user_id' => $this->inviter->id,
            'email' => 'owner@acme.com',
            'roles' => [],
        ]);
    }

    public function test_subject_line(): void
    {
        $mailable = new OrganizationOwnerInvitationMail($this->invitation);

        $mailable->assertHasSubject('Your Organization Awaits - Set Up Your Account');
    }

    public function test_renders_organization_name(): void
    {
        $mailable = new OrganizationOwnerInvitationMail($this->invitation);
        $rendered = $mailable->render();

        $this->assertStringContainsString('Acme Corp', $rendered);
    }

    public function test_renders_organization_setup_badge(): void
    {
        $mailable = new OrganizationOwnerInvitationMail($this->invitation);
        $rendered = $mailable->render();

        $this->assertStringContainsString('Organization Setup', $rendered);
        $this->assertStringNotContainsString('Team Invitation', $rendered);
    }

    public function test_renders_owner_heading(): void
    {
        $mailable = new OrganizationOwnerInvitationMail($this->invitation);
        $rendered = $mailable->render();

        $this->assertStringContainsString('Your Organization is Ready', $rendered);
        $this->assertStringNotContainsString("You're Invited!", $rendered);
    }

    public function test_renders_setup_cta_button(): void
    {
        $mailable = new OrganizationOwnerInvitationMail($this->invitation);
        $rendered = $mailable->render();

        $this->assertStringContainsString('Set Up Your Organization', $rendered);
        $this->assertStringNotContainsString('Accept Invitation', $rendered);
    }

    public function test_renders_accept_url(): void
    {
        $mailable = new OrganizationOwnerInvitationMail($this->invitation);
        $rendered = $mailable->render();

        $expectedUrl = route('invitations.accept', ['token' => $this->invitation->token]);
        $this->assertStringContainsString($expectedUrl, $rendered);
    }

    public function test_renders_expiration_date(): void
    {
        $mailable = new OrganizationOwnerInvitationMail($this->invitation);
        $rendered = $mailable->render();

        $expectedDate = $this->invitation->expires_at->format('F j, Y \a\t g:i A');
        $this->assertStringContainsString($expectedDate, $rendered);
    }

    public function test_renders_owner_specific_steps(): void
    {
        $mailable = new OrganizationOwnerInvitationMail($this->invitation);
        $rendered = $mailable->render();

        $this->assertStringContainsString('Create your account', $rendered);
        $this->assertStringContainsString('Configure your organization', $rendered);
        $this->assertStringContainsString('Invite your team', $rendered);
    }

    public function test_does_not_render_team_member_steps(): void
    {
        $mailable = new OrganizationOwnerInvitationMail($this->invitation);
        $rendered = $mailable->render();

        $this->assertStringNotContainsString('Complete onboarding', $rendered);
        $this->assertStringNotContainsString('Start collaborating', $rendered);
    }

    public function test_renders_owner_capabilities_section(): void
    {
        $mailable = new OrganizationOwnerInvitationMail($this->invitation);
        $rendered = $mailable->render();

        $this->assertStringContainsString('As Organization Owner', $rendered);
        $this->assertStringContainsString('Manage team members and their permissions', $rendered);
        $this->assertStringContainsString('Configure organization settings and integrations', $rendered);
        $this->assertStringContainsString('Access all inventory, products, and reporting', $rendered);
        $this->assertStringContainsString('Full control over billing and subscription management', $rendered);
    }

    public function test_does_not_render_role_names(): void
    {
        $mailable = new OrganizationOwnerInvitationMail($this->invitation);
        $rendered = $mailable->render();

        $this->assertStringNotContainsString('Your assigned role', $rendered);
    }

    public function test_uses_indigo_color_scheme(): void
    {
        $mailable = new OrganizationOwnerInvitationMail($this->invitation);
        $rendered = $mailable->render();

        // Indigo badge background
        $this->assertStringContainsString('#e0e7ff', $rendered);
        // Indigo badge text color
        $this->assertStringContainsString('#3730a3', $rendered);
        // Indigo CTA gradient
        $this->assertStringContainsString('#4f46e5', $rendered);
        // Dark slate/indigo header gradient
        $this->assertStringContainsString('#1e293b', $rendered);
        $this->assertStringContainsString('#312e81', $rendered);
    }

    public function test_does_not_use_blue_team_colors(): void
    {
        $mailable = new OrganizationOwnerInvitationMail($this->invitation);
        $rendered = $mailable->render();

        // The team template blue colors should not appear
        $this->assertStringNotContainsString('#dbeafe', $rendered);
        $this->assertStringNotContainsString('#1e40af', $rendered);
    }

    public function test_renders_footer(): void
    {
        $mailable = new OrganizationOwnerInvitationMail($this->invitation);
        $rendered = $mailable->render();

        $this->assertStringContainsString('If you did not expect this invitation', $rendered);
        $this->assertStringContainsString('Acme Corp', $rendered);
        $this->assertStringContainsString(date('Y'), $rendered);
    }

    public function test_renders_fallback_url_text(): void
    {
        $mailable = new OrganizationOwnerInvitationMail($this->invitation);
        $rendered = $mailable->render();

        $this->assertStringContainsString('Having trouble with the button?', $rendered);
    }

    public function test_renders_with_different_organization_name(): void
    {
        $org = Organization::factory()->create(['name' => 'Widget Inc']);
        $inviter = User::factory()->create(['organization_id' => $org->id]);

        $invitation = OrganizationInvitation::create([
            'organization_id' => $org->id,
            'invited_by_user_id' => $inviter->id,
            'email' => 'owner@widget.com',
            'roles' => [],
        ]);

        $mailable = new OrganizationOwnerInvitationMail($invitation);
        $rendered = $mailable->render();

        $this->assertStringContainsString('Widget Inc', $rendered);
        $this->assertStringContainsString('set up and manage', $rendered);
    }
}
