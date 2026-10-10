<?php

namespace App\Http\Controllers\Organization;

use App\Http\Controllers\Controller;
use App\Models\Organization\OrganizationInvitation;
use App\Models\Organization\Team;
use App\Models\User\Role;
use App\Models\User\User;
use App\Notifications\UserInvitationNotification;
use App\Services\OrganizationService;
use App\Services\UserService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;

class InvitationController extends Controller
{
    protected UserService $userService;

    public function __construct(UserService $userService)
    {
        $this->userService = $userService;
    }

    /**
     * List pending invitations (admin)
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $organization = $user->organization;
        $showHistory = $request->boolean('history', false);

        // Query for pending invitations
        $pendingInvitations = OrganizationInvitation::where('organization_id', $organization->id)
            ->whereNull('accepted_at')
            ->with(['invitedBy:id,name,email'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($invitation) {
                return [
                    'id' => $invitation->id,
                    'email' => $invitation->email,
                    'roles' => $invitation->roles,
                    'role_names' => $this->resolveRoleDetails($invitation->roles ?? []),
                    'invited_by' => $invitation->invitedBy ? [
                        'name' => $invitation->invitedBy->name,
                        'email' => $invitation->invitedBy->email,
                    ] : null,
                    'expires_at' => $invitation->expires_at->format('M j, Y g:i A'),
                    'expires_in' => $invitation->expires_at->diffForHumans(),
                    'is_expired' => $invitation->isExpired(),
                    'created_at' => $invitation->created_at->format('M j, Y g:i A'),
                    'sent_ago' => $invitation->created_at->diffForHumans(),
                ];
            });

        // Query for accepted invitations (history)
        $acceptedInvitations = OrganizationInvitation::where('organization_id', $organization->id)
            ->whereNotNull('accepted_at')
            ->with(['invitedBy:id,name,email'])
            ->orderBy('accepted_at', 'desc')
            ->limit(50)
            ->get()
            ->map(function ($invitation) {
                // Find the user who accepted
                $acceptedUser = User::where('email', $invitation->email)
                    ->where('organization_id', $invitation->organization_id)
                    ->first();

                return [
                    'id' => $invitation->id,
                    'email' => $invitation->email,
                    'roles' => $invitation->roles,
                    'role_names' => $this->resolveRoleDetails($invitation->roles ?? []),
                    'invited_by' => $invitation->invitedBy ? [
                        'name' => $invitation->invitedBy->name,
                        'email' => $invitation->invitedBy->email,
                    ] : null,
                    'accepted_at' => $invitation->accepted_at->format('M j, Y g:i A'),
                    'accepted_ago' => $invitation->accepted_at->diffForHumans(),
                    'created_at' => $invitation->created_at->format('M j, Y g:i A'),
                    'accepted_user' => $acceptedUser ? [
                        'id' => $acceptedUser->id,
                        'name' => $acceptedUser->name,
                    ] : null,
                ];
            });

        $availableRoles = Role::forOrganization($organization->id)
            ->where('slug', '!=', 'super_admin')
            ->active()
            ->select('id', 'name', 'slug', 'description')
            ->get();

        return Inertia::render('Organization/Invitations/Index', [
            'invitations' => $pendingInvitations,
            'historyInvitations' => $acceptedInvitations,
            'availableRoles' => $availableRoles,
            'organization' => [
                'id' => $organization->id,
                'name' => $organization->name,
            ],
            'showHistory' => $showHistory,
        ]);
    }

    /**
     * Send new invitation (admin)
     */
    public function store(Request $request)
    {
        $user = $request->user();
        $organization = $user->organization;

        $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['integer', 'exists:roles,id'],
        ]);

        // Check if user already exists in this organization
        $existingUser = User::where('email', $request->email)
            ->where('organization_id', $organization->id)
            ->first();

        if ($existingUser) {
            return back()->withErrors(['email' => 'A user with this email already exists in your organization.']);
        }

        // Check if there's already a pending invitation
        $existingInvitation = OrganizationInvitation::where('email', $request->email)
            ->where('organization_id', $organization->id)
            ->whereNull('accepted_at')
            ->where('expires_at', '>', now())
            ->first();

        if ($existingInvitation) {
            return back()->withErrors(['email' => 'An invitation has already been sent to this email address.']);
        }

        if (! $organization) {
            return back()->withErrors(['error' => 'Organization not found.']);
        }

        try {
            $invitation = $this->userService->inviteUser([
                'email' => $request->email,
                'roles' => $request->roles ?? [],
            ], $user);

            return back()->with('success', 'Invitation sent successfully to '.$request->email);
        } catch (\Throwable $e) {
            \Log::error('Invitation failed', [
                'email' => $request->email,
                'organization_id' => $organization->id,
                'error' => $e->getMessage(),
            ]);

            return back()->withErrors(['error' => 'Failed to send invitation. Please try again.']);
        }
    }

    /**
     * Send bulk invitations (admin)
     */
    public function bulkStore(Request $request)
    {
        $user = $request->user();
        $organization = $user->organization;

        $request->validate([
            'emails' => ['required', 'array', 'min:1', 'max:50'],
            'emails.*' => ['required', 'email', 'max:255'],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['integer', 'exists:roles,id'],
        ]);

        $emails = array_unique(array_map('strtolower', array_map('trim', $request->emails)));
        $results = [
            'success' => [],
            'skipped' => [],
            'errors' => [],
        ];

        foreach ($emails as $email) {
            // Check if user already exists in this organization
            $existingUser = User::where('email', $email)
                ->where('organization_id', $organization->id)
                ->first();

            if ($existingUser) {
                $results['skipped'][] = [
                    'email' => $email,
                    'reason' => 'User already exists in organization',
                ];

                continue;
            }

            // Check if there's already a pending invitation
            $existingInvitation = OrganizationInvitation::where('email', $email)
                ->where('organization_id', $organization->id)
                ->whereNull('accepted_at')
                ->where('expires_at', '>', now())
                ->first();

            if ($existingInvitation) {
                $results['skipped'][] = [
                    'email' => $email,
                    'reason' => 'Pending invitation already exists',
                ];

                continue;
            }

            try {
                $this->userService->inviteUser([
                    'email' => $email,
                    'roles' => $request->roles ?? [],
                ], $user);

                $results['success'][] = $email;
            } catch (\Exception $e) {
                $results['errors'][] = [
                    'email' => $email,
                    'reason' => 'Failed to send invitation',
                ];
            }
        }

        $successCount = count($results['success']);
        $skippedCount = count($results['skipped']);
        $errorCount = count($results['errors']);

        $message = "{$successCount} invitation(s) sent successfully.";
        if ($skippedCount > 0) {
            $message .= " {$skippedCount} skipped.";
        }
        if ($errorCount > 0) {
            $message .= " {$errorCount} failed.";
        }

        return back()->with([
            'success' => $successCount > 0 ? $message : null,
            'bulkResults' => $results,
        ]);
    }

    /**
     * Cancel invitation (admin)
     */
    public function destroy(Request $request, OrganizationInvitation $invitation)
    {
        $user = $request->user();

        // Ensure the invitation belongs to the user's organization
        if ($invitation->organization_id !== $user->organization_id) {
            abort(403, 'Unauthorized action.');
        }

        $invitation->delete();

        return back()->with('success', 'Invitation cancelled successfully.');
    }

    /**
     * Resend invitation email (admin)
     */
    public function resend(Request $request, OrganizationInvitation $invitation)
    {
        $user = $request->user();

        // Ensure the invitation belongs to the user's organization
        if ($invitation->organization_id !== $user->organization_id) {
            abort(403, 'Unauthorized action.');
        }

        if ($invitation->isAccepted()) {
            return back()->withErrors(['error' => 'This invitation has already been accepted.']);
        }

        // Extend expiration if expired
        if ($invitation->isExpired()) {
            $invitation->update(['expires_at' => now()->addDays(7)]);
        }

        // Resend the notification
        Notification::route('mail', $invitation->email)
            ->notify(new UserInvitationNotification($invitation));

        return back()->with('success', 'Invitation resent successfully to '.$invitation->email);
    }

    /**
     * Display acceptance page (guest)
     */
    public function showAcceptForm(string $token)
    {
        $invitation = OrganizationInvitation::where('token', $token)
            ->with(['organization:id,name', 'invitedBy:id,name'])
            ->first();

        if (! $invitation) {
            return Inertia::render('Invitations/Expired', [
                'message' => 'This invitation link is invalid.',
                'reason' => 'invalid',
            ]);
        }

        if ($invitation->isAccepted()) {
            return Inertia::render('Invitations/Expired', [
                'message' => 'This invitation has already been accepted.',
                'reason' => 'accepted',
                'organization' => $invitation->organization ? [
                    'name' => $invitation->organization->name,
                ] : null,
            ]);
        }

        if ($invitation->isExpired()) {
            return Inertia::render('Invitations/Expired', [
                'message' => 'This invitation has expired.',
                'reason' => 'expired',
                'organization' => $invitation->organization ? [
                    'name' => $invitation->organization->name,
                ] : null,
            ]);
        }

        // Check if user already exists (include soft-deleted to avoid unique constraint issues)
        $existingUser = User::withTrashed()->where('email', $invitation->email)->first();

        // Resolve role names and descriptions
        $roles = $this->resolveRoleDetails($invitation->roles ?? []);

        // Calculate relative expiration
        $expiresIn = $invitation->expires_at->diffForHumans();

        return Inertia::render('Invitations/Accept', [
            'invitation' => [
                'token' => $invitation->token,
                'email' => $invitation->email,
                'organization' => $invitation->organization ? [
                    'name' => $invitation->organization->name,
                ] : null,
                'invited_by' => $invitation->invitedBy ? [
                    'name' => $invitation->invitedBy->name,
                ] : null,
                'expires_at' => $invitation->expires_at->format('M j, Y g:i A'),
                'expires_in' => $expiresIn,
                'roles' => $roles,
                'is_org_owner_invitation' => $invitation->isForOrganizationOwner(),
            ],
            'existingUser' => $existingUser ? true : false,
        ]);
    }

    /**
     * Resolve role IDs to names and descriptions
     */
    protected function resolveRoleDetails(array $roleIds): array
    {
        if (empty($roleIds)) {
            return [];
        }

        return Role::whereIn('id', $roleIds)
            ->select('id', 'name', 'slug', 'description')
            ->get()
            ->map(function ($role) {
                return [
                    'id' => $role->id,
                    'name' => $role->name,
                    'slug' => $role->slug,
                    'description' => $role->description,
                ];
            })
            ->toArray();
    }

    /**
     * Process acceptance (guest)
     */
    public function accept(Request $request, string $token)
    {
        $invitation = OrganizationInvitation::where('token', $token)
            ->with(['organization', 'invitedBy'])
            ->first();

        if (! $invitation) {
            return back()->withErrors(['error' => 'Invalid invitation link.']);
        }

        if ($invitation->isAccepted()) {
            return back()->withErrors(['error' => 'This invitation has already been accepted.']);
        }

        if ($invitation->isExpired()) {
            return back()->withErrors(['error' => 'This invitation has expired.']);
        }

        $isOrgOwnerInvitation = $invitation->isForOrganizationOwner();

        // Check if user already exists with this email (include soft-deleted to avoid unique constraint issues)
        $existingUser = User::withTrashed()->where('email', $invitation->email)->first();

        if ($existingUser) {
            // Restore if previously soft-deleted
            if ($existingUser->trashed()) {
                $existingUser->restore();
            }

            // Existing user - validate password
            $rules = [
                'password' => ['required', 'string'],
            ];

            if ($isOrgOwnerInvitation) {
                $rules['organization_name'] = ['required', 'string', 'max:255'];
                $rules['organization_subdomain'] = ['required', 'string', 'max:63', 'alpha_dash', 'unique:organizations,subdomain'];
                $rules['organization_email'] = ['required', 'email', 'max:255'];
            }

            $request->validate($rules);

            if (! Hash::check($request->password, $existingUser->password)) {
                return back()->withErrors(['password' => 'The provided password is incorrect.']);
            }

            if ($isOrgOwnerInvitation) {
                // Create a new organization for the user
                $orgService = app(OrganizationService::class);
                $orgService->createOrganization([
                    'name' => $request->organization_name,
                    'subdomain' => $request->organization_subdomain,
                    'email' => $request->organization_email,
                ], $existingUser);

                $invitation->accept();
                Auth::login($existingUser->fresh());

                return redirect()->route('onboarding.index')->with('success', 'Your organization has been created!');
            }

            // Non-org-owner: add user to inviter's organization
            $existingUser->update([
                'organization_id' => $invitation->organization_id,
            ]);

            // Create personal team for the user in their new organization
            $personalTeam = Team::create([
                'name' => explode(' ', $existingUser->name, 2)[0]."'s Team",
                'organization_id' => $invitation->organization_id,
                'user_id' => $existingUser->id,
                'personal_team' => true,
            ]);
            $existingUser->update(['current_team_id' => $personalTeam->id]);

            // Assign roles
            foreach ($invitation->roles as $roleId) {
                $role = Role::find($roleId);
                if ($role) {
                    $existingUser->assignRole($role, $invitation->organization_id);
                }
            }

            $invitation->accept();

            Auth::login($existingUser->fresh());

            return redirect()->route('onboarding.index')->with('success', 'You have joined '.$invitation->organization->name);
        }

        // New user - validate full registration data
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ];

        if ($isOrgOwnerInvitation) {
            $rules['organization_name'] = ['required', 'string', 'max:255'];
            $rules['organization_subdomain'] = ['required', 'string', 'max:63', 'alpha_dash', 'unique:organizations,subdomain'];
            $rules['organization_email'] = ['required', 'email', 'max:255'];
        }

        if (\Laravel\Jetstream\Jetstream::hasTermsAndPrivacyPolicyFeature()) {
            $rules['terms'] = ['accepted'];
        }

        $request->validate($rules);

        try {
            if ($isOrgOwnerInvitation) {
                $user = $this->userService->acceptInvitationAsOrgOwner($invitation, [
                    'name' => $request->name,
                    'email' => $invitation->email,
                    'password' => $request->password,
                ], [
                    'name' => $request->organization_name,
                    'subdomain' => $request->organization_subdomain,
                    'email' => $request->organization_email,
                ]);

                Auth::login($user);

                return redirect()->route('onboarding.index')->with('success', 'Your organization has been created!');
            }

            $user = $this->userService->acceptInvitation($invitation, [
                'name' => $request->name,
                'email' => $invitation->email,
                'password' => $request->password,
            ]);

            Auth::login($user);

            // Redirect new users to onboarding
            return redirect()->route('onboarding.index')->with('success', 'Welcome to '.$invitation->organization->name.'!');
        } catch (\Exception $e) {
            \Log::error('Invitation acceptance failed', [
                'token' => $token,
                'email' => $invitation->email,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return back()->withErrors(['error' => 'Failed to create account. Please try again.']);
        }
    }
}
