<?php

namespace App\Notifications;

use App\Models\Organization\Organization;
use App\Models\User\Role;
use App\Models\User\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class WelcomeToOrganizationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected User $user;

    protected Organization $organization;

    protected array $roleIds;

    public function __construct(User $user, Organization $organization, array $roleIds = [])
    {
        $this->user = $user;
        $this->organization = $organization;
        $this->roleIds = $roleIds;
    }

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable)
    {
        $roles = $this->resolveRoles();
        $quickStartLinks = $this->getQuickStartLinks($roles);

        return (new \Illuminate\Mail\Mailable)
            ->to($notifiable->email)
            ->subject("Welcome to {$this->organization->name}!")
            ->view('emails.welcome.welcome-to-organization', [
                'user' => $this->user,
                'organization' => $this->organization,
                'roles' => $roles,
                'quickStartLinks' => $quickStartLinks,
                'dashboardUrl' => route('dashboard'),
                'onboardingUrl' => route('onboarding.index'),
            ]);
    }

    protected function resolveRoles(): array
    {
        if (empty($this->roleIds)) {
            return [];
        }

        return Role::whereIn('id', $this->roleIds)
            ->select('id', 'name', 'slug', 'description')
            ->get()
            ->map(function ($role) {
                return [
                    'name' => $role->name,
                    'slug' => $role->slug,
                    'description' => $role->description,
                ];
            })
            ->toArray();
    }

    protected function getQuickStartLinks(array $roles): array
    {
        $links = [
            [
                'title' => 'Complete Onboarding',
                'description' => 'Get started with a quick tour of the platform',
                'url' => route('onboarding.index'),
                'icon' => 'rocket',
            ],
        ];

        // Add role-specific links
        $roleSlugs = array_column($roles, 'slug');

        if (array_intersect($roleSlugs, ['organization_owner', 'admin'])) {
            $links[] = [
                'title' => 'Organization Settings',
                'description' => 'Configure your organization preferences',
                'url' => route('organization.settings'),
                'icon' => 'cog',
            ];
            $links[] = [
                'title' => 'Invite Team Members',
                'description' => 'Build your team by sending invitations',
                'url' => route('organization.invitations.index'),
                'icon' => 'users',
            ];
        }

        if (array_intersect($roleSlugs, ['buyer', 'sourcing_specialist'])) {
            $links[] = [
                'title' => 'Product Intake',
                'description' => 'Start adding products to the inventory',
                'url' => route('intake-queue.index'),
                'icon' => 'shopping-bag',
            ];
        }

        if (array_intersect($roleSlugs, ['lister', 'listing_specialist'])) {
            $links[] = [
                'title' => 'Intake Queue',
                'description' => 'Review and list products',
                'url' => route('intake-queue.index'),
                'icon' => 'clipboard-list',
            ];
        }

        if (array_intersect($roleSlugs, ['fulfillment_manager', 'shipper'])) {
            $links[] = [
                'title' => 'Inventory Dashboard',
                'description' => 'View and manage inventory',
                'url' => route('inventory.dashboard'),
                'icon' => 'cube',
            ];
        }

        return $links;
    }

    public function toArray($notifiable): array
    {
        return [
            'user_id' => $this->user->id,
            'organization_id' => $this->organization->id,
            'organization_name' => $this->organization->name,
        ];
    }
}
