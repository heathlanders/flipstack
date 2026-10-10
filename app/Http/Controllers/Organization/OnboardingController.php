<?php

namespace App\Http\Controllers\Organization;

use App\Http\Controllers\Controller;
use App\Models\Organization\Team;
use App\Models\System\OnboardingProgress;
use App\Models\User\Role;
use App\Models\User\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class OnboardingController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth']);
    }

    /**
     * Show onboarding dashboard
     */
    public function index(Request $request)
    {
        $user = $request->user();

        // Check if user needs onboarding
        if ($this->isOnboardingComplete($user)) {
            return redirect()->route('dashboard');
        }

        $progress = $this->getOnboardingProgress($user);
        $currentStep = $this->getCurrentStep($progress);

        return Inertia::render('Onboarding/Index', [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'avatar' => $user->profile_photo_url ?? '/default-avatar.png',
                'primary_role' => $user->getPrimaryRole(),
            ],
            'progress' => $progress,
            'current_step' => $currentStep,
            'steps' => $this->getOnboardingSteps($user),
            'organization' => $user->organization ? [
                'name' => $user->organization->name,
                'id' => $user->organization->id,
            ] : null,
            'support_email' => config('mail.from.address'),
        ]);
    }

    /**
     * Complete an onboarding step
     */
    public function completeStep(Request $request)
    {
        $request->validate([
            'step' => ['required', 'string', 'in:welcome,profile,teams,sku_prefix_setup,role_understanding,first_action,resources,complete'],
            'data' => ['array'],
        ]);

        $user = $request->user();

        try {
            DB::transaction(function () use ($request, $user) {
                $progress = OnboardingProgress::firstOrCreate(
                    ['user_id' => $user->id],
                    [
                        'organization_id' => $user->organization_id,
                        'steps_completed' => [],
                        'current_step' => 'welcome',
                        'started_at' => now(),
                        'data' => [],
                    ]
                );

                $stepsCompleted = $progress->steps_completed ?? [];
                $stepData = $progress->data ?? [];

                // Mark step as completed
                if (! in_array($request->step, $stepsCompleted)) {
                    $stepsCompleted[] = $request->step;
                }

                // Store step-specific data
                if ($request->filled('data')) {
                    $stepData[$request->step] = $request->data;
                }

                // Determine next step
                $nextStep = $this->getNextStep($request->step);

                $progress->update([
                    'steps_completed' => $stepsCompleted,
                    'current_step' => $nextStep,
                    'data' => $stepData,
                    'completed_at' => $nextStep === 'completed' ? now() : null,
                ]);

                // Perform step-specific actions
                $this->handleStepCompletion($user, $request->step, $request->data ?? []);

                // Log activity
                $user->logActivity('onboarding_step_completed', "Completed onboarding step: {$request->step}", [
                    'step' => $request->step,
                    'progress_percentage' => $this->calculateProgressPercentage($stepsCompleted),
                ]);
            });

            return redirect()->route('onboarding.index');

        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Failed to complete step.']);
        }
    }

    /**
     * Skip onboarding
     */
    public function skip(Request $request)
    {
        $user = $request->user();

        try {
            OnboardingProgress::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'organization_id' => $user->organization_id,
                    'steps_completed' => ['skipped'],
                    'current_step' => 'completed',
                    'completed_at' => now(),
                    'skipped_at' => now(),
                ]
            );

            // Log activity
            $user->logActivity('onboarding_skipped', 'Skipped onboarding process');

            return redirect()->route('dashboard')
                ->with('success', 'Welcome! You can access the onboarding guide anytime from your profile.');

        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Failed to skip onboarding.']);
        }
    }

    /**
     * Complete entire onboarding
     */
    public function complete(Request $request)
    {
        $user = $request->user();

        try {
            $progress = OnboardingProgress::firstOrCreate(['user_id' => $user->id]);

            $progress->update([
                'current_step' => 'completed',
                'completed_at' => now(),
            ]);

            // Log activity
            $user->logActivity('onboarding_completed', 'Completed onboarding process', [
                'duration_minutes' => $progress->started_at ?
                    $progress->started_at->diffInMinutes(now()) : null,
            ]);

            return redirect()->route('dashboard')
                ->with('success', 'Welcome to the team! You\'re all set up and ready to go.');

        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Failed to complete onboarding.']);
        }
    }

    /**
     * Check if user has completed onboarding
     */
    private function isOnboardingComplete(User $user): bool
    {
        $progress = OnboardingProgress::where('user_id', $user->id)->first();

        return $progress && $progress->completed_at !== null;
    }

    /**
     * Get user's onboarding progress
     */
    private function getOnboardingProgress(User $user): ?OnboardingProgress
    {
        return OnboardingProgress::where('user_id', $user->id)->first();
    }

    /**
     * Get current onboarding step
     */
    private function getCurrentStep(?OnboardingProgress $progress): string
    {
        if (! $progress) {
            return 'welcome';
        }

        if ($progress->completed_at) {
            return 'completed';
        }

        return $progress->current_step ?? 'welcome';
    }

    /**
     * Get onboarding steps configuration
     */
    private function getOnboardingSteps(User $user): array
    {
        $role = $user->getPrimaryRole();

        return [
            'welcome' => [
                'title' => 'Welcome to the Team!',
                'description' => 'Let\'s get you set up and ready to be productive.',
                'icon' => 'fas fa-hand-wave',
                'estimated_time' => '2 minutes',
            ],
            'profile' => [
                'title' => 'Complete Your Profile',
                'description' => 'Add some personal details to help your teammates recognize you.',
                'icon' => 'fas fa-user',
                'estimated_time' => '3 minutes',
            ],
            'teams' => [
                'title' => 'Join Your Teams',
                'description' => 'Connect with the teams you\'ll be working with.',
                'icon' => 'fas fa-users',
                'estimated_time' => '2 minutes',
            ],
            'sku_prefix_setup' => [
                'title' => 'Set Up SKU Prefix',
                'description' => 'Choose a unique prefix for your organization\'s product SKUs.',
                'icon' => 'fas fa-tag',
                'estimated_time' => '2 minutes',
            ],
            'role_understanding' => [
                'title' => 'Understand Your Role',
                'description' => 'Learn about your responsibilities and permissions.',
                'icon' => 'fas fa-badge',
                'estimated_time' => '5 minutes',
            ],
            'first_action' => [
                'title' => 'Take Your First Action',
                'description' => $this->getFirstActionDescription($role),
                'icon' => 'fas fa-play',
                'estimated_time' => '5 minutes',
            ],
            'resources' => [
                'title' => 'Explore Resources',
                'description' => 'Discover helpful guides and documentation.',
                'icon' => 'fas fa-book',
                'estimated_time' => '3 minutes',
            ],
        ];
    }

    /**
     * Get next step in sequence
     */
    private function getNextStep(string $currentStep): string
    {
        $sequence = ['welcome', 'profile', 'teams', 'sku_prefix_setup', 'role_understanding', 'first_action', 'resources', 'completed'];

        $currentIndex = array_search($currentStep, $sequence);

        if ($currentIndex === false || $currentIndex >= count($sequence) - 1) {
            return 'completed';
        }

        return $sequence[$currentIndex + 1];
    }

    /**
     * Calculate progress percentage
     */
    private function calculateProgressPercentage(array $stepsCompleted): int
    {
        $totalSteps = 6; // Total onboarding steps
        $completed = count(array_diff($stepsCompleted, ['skipped']));

        return min(100, round(($completed / $totalSteps) * 100));
    }

    /**
     * Handle step-specific completion logic
     */
    private function handleStepCompletion(User $user, string $step, array $data)
    {
        switch ($step) {
            case 'profile':
                if (isset($data['bio'])) {
                    $user->update(['bio' => $data['bio']]);
                }
                if (isset($data['phone'])) {
                    $user->update(['phone' => $data['phone']]);
                }
                break;

            case 'teams':
                if (isset($data['team_requests'])) {
                    foreach ($data['team_requests'] as $teamId) {
                        $team = Team::find($teamId);
                        if ($team && ! $user->isMemberOfTeam($team)) {
                            // Create team join request or auto-add based on permissions
                            $this->handleTeamJoinRequest($user, $team);
                        }
                    }
                }
                break;
            case 'sku_prefix_setup':
                if (isset($data['sku_prefix'])) {
                    $skuService = app(\App\Services\OrganizationSkuService::class);
                    try {
                        $skuService->setPrefix($user->organization, $data['sku_prefix'], $user->id);
                    } catch (\Exception $e) {
                        \Log::warning('SKU prefix setup failed during onboarding', ['error' => $e->getMessage()]);
                    }
                }
                break;
            case 'first_action':
                // Role-specific first actions
                $this->handleFirstAction($user, $data);
                break;
        }
    }

    /**
     * Get first action description based on role
     */
    private function getFirstActionDescription(?string $role): string
    {
        return match ($role) {
            'buyer', 'sourcing_specialist' => 'Add your first product to the inventory.',
            'lister', 'listing_specialist' => 'Process an item from the intake queue.',
            'fulfillment_manager', 'shipper' => 'Review the current order fulfillment queue.',
            'organization_owner', 'admin' => 'Review your organization dashboard and team structure.',
            default => 'Explore the dashboard and familiarize yourself with the interface.'
        };
    }

    /**
     * Handle team join requests during onboarding
     */
    private function handleTeamJoinRequest(User $user, Team $team)
    {
        // For onboarding, we'll auto-approve with default role
        $defaultRole = Role::where('slug', 'team_member')
            ->orWhere('is_default', true)
            ->first();

        if ($defaultRole && ! $team->isAtCapacity()) {
            $team->addUser($user, $defaultRole);
        }
    }

    /**
     * Handle role-specific first actions
     */
    private function handleFirstAction(User $user, array $data)
    {
        $role = $user->getPrimaryRole();

        switch ($role) {
            case 'buyer':
            case 'sourcing_specialist':
                // Could guide them to product creation
                if (isset($data['visited_inventory'])) {
                    // Mark that they've explored inventory
                }
                break;

            case 'lister':
            case 'listing_specialist':
                // Could guide them to intake queue
                if (isset($data['visited_intake_queue'])) {
                    // Mark that they've explored intake queue
                }
                break;

            case 'organization_owner':
            case 'admin':
                // Could guide them to team management
                if (isset($data['reviewed_organization'])) {
                    // Mark that they've reviewed org structure
                }
                break;
        }
    }

    public function checkSkuPrefixAvailability(Request $request)
    {
        $request->validate([
            'prefix' => 'required|string|min:3|max:10|regex:/^[A-Z0-9]+$/',
        ]);

        $skuService = app(\App\Services\OrganizationSkuService::class);
        $available = $skuService->isPrefixAvailable($request->prefix);

        return response()->json([
            'available' => $available,
            'message' => $available
                ? 'This prefix is available! ✓'
                : 'This prefix is already taken.',
        ]);
    }
}
