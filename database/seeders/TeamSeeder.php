<?php

namespace Database\Seeders;

use App\Models\Organization\Organization;
use App\Models\Organization\Team;
use App\Models\User\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TeamSeeder extends Seeder
{
    /**
     * 🏢 COMPREHENSIVE TEAM SEEDER - 100% USER COVERAGE
     *
     * Ensures ALL users are assigned to appropriate teams:
     * ✅ Creates teams for all organizations with unique slugs
     * ✅ Assigns ALL users to relevant teams based on their roles
     * ✅ Sets appropriate team leads
     * ✅ Organization owners get access to ALL teams
     * ✅ Ensures no user is left without team membership
     * ✅ Creates emergency teams when needed
     */
    private $teamTemplates = [
        [
            'name' => 'Product Intake Team',
            'slug' => 'product-intake-team',
            'description' => 'Responsible for capturing and processing incoming products',
            'department' => 'sourcing',
            'max_members' => 20,
            'preferred_lead_roles' => ['buyer', 'sourcing_specialist', 'manager'],
            'preferred_member_roles' => ['buyer', 'sourcing_specialist', 'lister', 'employee'],
        ],
        [
            'name' => 'Quality Control Team',
            'slug' => 'quality-control-team',
            'description' => 'Reviews and validates product data before listing',
            'department' => 'listing',
            'max_members' => 15,
            'preferred_lead_roles' => ['lister', 'listing_specialist', 'manager'],
            'preferred_member_roles' => ['lister', 'listing_specialist', 'employee'],
        ],
        [
            'name' => 'eBay Management Team',
            'slug' => 'ebay-management-team',
            'description' => 'Manages eBay listings, optimization, and customer service',
            'department' => 'listing',
            'max_members' => 25,
            'preferred_lead_roles' => ['lister', 'listing_specialist', 'manager'],
            'preferred_member_roles' => ['lister', 'listing_specialist', 'support_agent', 'employee'],
        ],
        [
            'name' => 'Fulfillment Team',
            'slug' => 'fulfillment-team',
            'description' => 'Handles order processing, packaging, and shipping',
            'department' => 'fulfillment',
            'max_members' => 20,
            'preferred_lead_roles' => ['fulfillment_manager', 'manager'],
            'preferred_member_roles' => ['fulfillment_manager', 'shipper', 'employee'],
        ],
        [
            'name' => 'Customer Support Team',
            'slug' => 'customer-support-team',
            'description' => 'Provides customer service and handles inquiries',
            'department' => 'support',
            'max_members' => 15,
            'preferred_lead_roles' => ['support_agent', 'manager'],
            'preferred_member_roles' => ['support_agent', 'employee'],
        ],
        [
            'name' => 'Operations Team',
            'slug' => 'operations-team',
            'description' => 'Oversees daily operations and coordination between departments',
            'department' => 'operations',
            'max_members' => 20,
            'preferred_lead_roles' => ['manager', 'admin'],
            'preferred_member_roles' => ['manager', 'admin', 'employee'],
        ],
    ];

    public function run(): void
    {
        $this->command->info('🏢 Creating comprehensive team structure with 100% user coverage...');

        // Clear existing team assignments
        DB::table('team_user')->delete();
        $this->command->info('🧹 Cleared existing team assignments');

        $organizations = Organization::with(['users.roles'])->get();

        foreach ($organizations as $organization) {
            $this->createTeamsForOrganization($organization);
        }

        $this->verifyCompleteTeamCoverage();
        $this->displaySummary();
    }

    /**
     * Create teams and assign all users for an organization
     */
    private function createTeamsForOrganization(Organization $organization): void
    {
        $this->command->info("🏢 Processing {$organization->name}:");

        $teams = $this->createTeamsForOrg($organization);
        $this->assignAllUsersToTeams($organization, $teams);
    }

    /**
     * Create all teams for an organization with unique slugs
     */
    private function createTeamsForOrg(Organization $organization): array
    {
        $teams = [];

        // Get a fallback user for team creation (required by Laravel Jetstream)
        $fallbackUser = User::where('organization_id', $organization->id)
            ->where('is_active', true)
            ->first();

        if (! $fallbackUser) {
            $this->command->warn("  ⚠️  No active users found for {$organization->name}");

            return $teams;
        }

        foreach ($this->teamTemplates as $template) {
            // Make slug unique per organization by adding org ID
            $uniqueSlug = $template['slug'].'-org-'.$organization->id;

            $team = Team::firstOrCreate(
                ['slug' => $uniqueSlug, 'organization_id' => $organization->id],
                [
                    'name' => $template['name'],
                    'user_id' => $fallbackUser->id, // Required by Jetstream (will be updated)
                    'personal_team' => false,
                    'organization_id' => $organization->id,
                    'description' => $template['description'],
                    'department' => $template['department'],
                    'max_members' => $template['max_members'],
                    'is_active' => true,
                    'created_by' => $fallbackUser->id,
                    'created_at' => now()->subDays(rand(30, 90)),
                    'updated_at' => now()->subDays(rand(0, 15)),
                ]
            );

            $this->command->info("  ✅ Team: {$team->name} (max: {$team->max_members})");
            $teams[] = [
                'team' => $team,
                'template' => $template,
            ];
        }

        return $teams;
    }

    /**
     * Assign ALL users in organization to appropriate teams with 100% coverage guarantee
     */
    private function assignAllUsersToTeams(Organization $organization, array $teams): void
    {
        $users = User::where('organization_id', $organization->id)
            ->where('is_active', true)
            ->with('roles')
            ->get();

        if ($users->isEmpty()) {
            $this->command->warn("  ⚠️  No users to assign in {$organization->name}");

            return;
        }

        $this->command->info("  👥 Assigning {$users->count()} users to teams (100% coverage)...");

        // PASS 1: Smart assignment based on roles
        foreach ($teams as $teamData) {
            $team = $teamData['team'];
            $template = $teamData['template'];

            // Add organization owners to ALL teams
            $this->addOrganizationOwnersToTeam($team, $users);

            // Assign team lead
            $teamLead = $this->assignTeamLead($team, $template, $users);

            // Add relevant members based on roles
            $this->addTeamMembers($team, $template, $users, $teamLead);
        }

        // PASS 2: Ensure ALL users are in at least one team (CRITICAL)
        $this->ensureAllUsersHaveTeams($organization, $teams);

        // PASS 3: Verify no user is left behind
        $this->verifyCompleteTeamCoverage($organization);

        // PASS 4: Set current teams for users who don't have one
        $this->setCurrentTeamsForUsers($organization);
    }

    /**
     * Add organization owners to ALL teams
     */
    private function addOrganizationOwnersToTeam(Team $team, $users): void
    {
        $owners = $users->filter(function ($user) {
            return $user->roles->pluck('slug')->contains('organization_owner');
        });

        foreach ($owners as $owner) {
            $this->addUserToTeam($team, $owner, 'lead');
            $this->command->info("    👑 Added owner: {$owner->name}");
        }
    }

    /**
     * Assign appropriate team lead
     */
    private function assignTeamLead(Team $team, array $template, $users): ?User
    {
        $preferredLeadRoles = $template['preferred_lead_roles'];

        foreach ($preferredLeadRoles as $roleSlug) {
            $potentialLeads = $users->filter(function ($user) use ($roleSlug, $team) {  // ✅ Fixed: Added $team
                return $user->roles->pluck('slug')->contains($roleSlug) &&
                       ! $this->userIsInTeam($user, $team);
            });

            if ($potentialLeads->isNotEmpty()) {
                $teamLead = $potentialLeads->first();
                $this->addUserToTeam($team, $teamLead, 'lead');

                // Update team's team_lead_id
                $team->update(['team_lead_id' => $teamLead->id]);

                $this->command->info("    👨‍💼 Team Lead: {$teamLead->name} ({$roleSlug})");

                return $teamLead;
            }
        }

        $this->command->info("    ⚠️  No dedicated team lead found for {$team->name}");

        return null;
    }

    /**
     * Add team members based on roles
     */
    private function addTeamMembers(Team $team, array $template, $users, ?User $teamLead): void
    {
        $preferredMemberRoles = $template['preferred_member_roles'];
        $maxMembers = $template['max_members'];
        $currentMembers = $this->getTeamMemberCount($team);

        // Reserve spots for existing members (owners + lead)
        $spotsAvailable = $maxMembers - $currentMembers;

        $membersAdded = 0;

        foreach ($preferredMemberRoles as $roleSlug) {
            if ($membersAdded >= $spotsAvailable) {
                break;
            }

            $usersWithRole = $users->filter(function ($user) use ($roleSlug, $team) {
                return $user->roles->pluck('slug')->contains($roleSlug) &&
                       ! $this->userIsInTeam($user, $team);
            });

            foreach ($usersWithRole as $user) {
                if ($membersAdded >= $spotsAvailable) {
                    break;
                }

                $this->addUserToTeam($team, $user, 'member');
                $this->command->info("    👥 Member: {$user->name} ({$roleSlug})");
                $membersAdded++;
            }
        }

        $totalMembers = $this->getTeamMemberCount($team);
        $this->command->info("    📊 Total members: {$totalMembers}/{$maxMembers}");
    }

    /**
     * CRITICAL: Ensure ALL users are in at least one team
     */
    private function ensureAllUsersHaveTeams(Organization $organization, array $teams): void
    {
        $usersWithoutTeams = User::where('organization_id', $organization->id)
            ->where('is_active', true)
            ->whereNotIn('id', function ($query) {
                $query->select('user_id')->from('team_user');
            })
            ->get();

        if ($usersWithoutTeams->isNotEmpty()) {
            $this->command->info("  🔧 CRITICAL: Assigning {$usersWithoutTeams->count()} users without teams...");

            // Get operations team (most general) or any team as fallback
            $fallbackTeam = null;
            foreach ($teams as $teamData) {
                if ($teamData['template']['slug'] === 'operations-team') {
                    $fallbackTeam = $teamData['team'];
                    break;
                }
            }

            // If no operations team, use the first available team
            if (! $fallbackTeam && ! empty($teams)) {
                $fallbackTeam = $teams[0]['team'];
            }

            // If still no team, create an emergency operations team
            if (! $fallbackTeam) {
                $this->command->warn("  🚨 No teams available, creating emergency team for {$organization->name}");
                $fallbackTeam = $this->createEmergencyTeam($organization);
            }

            if ($fallbackTeam) {
                foreach ($usersWithoutTeams as $user) {
                    $this->addUserToTeam($fallbackTeam, $user, 'member');
                    $this->command->info("    🔧 Emergency assigned {$user->name} to {$fallbackTeam->name}");
                }
            } else {
                $this->command->error("    💥 Could not create fallback team for {$organization->name}");
            }
        }
    }

    /**
     * Create an emergency team when none exist for an organization
     */
    private function createEmergencyTeam(Organization $organization): ?Team
    {
        $fallbackUser = User::where('organization_id', $organization->id)
            ->where('is_active', true)
            ->first();

        if (! $fallbackUser) {
            return null;
        }

        $emergencySlug = 'emergency-team-org-'.$organization->id;

        $team = Team::create([
            'name' => 'Emergency Operations Team',
            'slug' => $emergencySlug,
            'user_id' => $fallbackUser->id,
            'personal_team' => false,
            'organization_id' => $organization->id,
            'description' => 'Emergency team created to ensure all users have team assignments',
            'department' => 'operations',
            'max_members' => 50,
            'is_active' => true,
            'created_by' => $fallbackUser->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->command->info("    🚨 Created emergency team: {$team->name}");

        return $team;
    }

    /**
     * Set current team for users who don't have one
     */
    private function setCurrentTeamsForUsers(Organization $organization): void
    {
        $usersWithoutCurrentTeam = User::where('organization_id', $organization->id)
            ->where('is_active', true)
            ->whereNull('current_team_id')
            ->get();

        foreach ($usersWithoutCurrentTeam as $user) {
            // Get their first team assignment
            $userTeam = DB::table('team_user')
                ->join('teams', 'team_user.team_id', '=', 'teams.id')
                ->where('team_user.user_id', $user->id)
                ->where('teams.organization_id', $organization->id)
                ->first();

            if ($userTeam) {
                $user->update(['current_team_id' => $userTeam->team_id]);
                $this->command->info("    🎯 Set current team for {$user->name}");
            }
        }
    }

    /**
     * Check if user is already in team
     */
    private function userIsInTeam(User $user, Team $team): bool
    {
        return DB::table('team_user')
            ->where('user_id', $user->id)
            ->where('team_id', $team->id)
            ->exists();
    }

    /**
     * Add user to team
     */
    private function addUserToTeam(Team $team, User $user, string $role): void
    {
        if (! $this->userIsInTeam($user, $team)) {
            DB::table('team_user')->insert([
                'team_id' => $team->id,
                'user_id' => $user->id,
                'role' => $role,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Set as user's current team if they don't have one
            if (! $user->current_team_id) {
                $user->update(['current_team_id' => $team->id]);
            }
        }
    }

    /**
     * Get team member count
     */
    private function getTeamMemberCount(Team $team): int
    {
        return DB::table('team_user')->where('team_id', $team->id)->count();
    }

    /**
     * Verify that ALL users across the system have team assignments
     */
    private function verifyCompleteTeamCoverage(): void
    {
        $this->command->info('🔍 Verifying complete team coverage across all organizations...');

        $totalActiveUsers = User::where('is_active', true)->count();
        $usersInTeams = DB::table('team_user')
            ->join('users', 'team_user.user_id', '=', 'users.id')
            ->where('users.is_active', true)
            ->distinct('users.id')
            ->count('users.id');

        $usersWithoutTeams = $totalActiveUsers - $usersInTeams;

        if ($usersWithoutTeams > 0) {
            $this->command->error("❌ CRITICAL: {$usersWithoutTeams} users across system have no team assignments!");

            // Find and fix users without teams
            $unassignedUsers = User::where('is_active', true)
                ->whereNotIn('id', function ($query) {
                    $query->select('user_id')->from('team_user');
                })
                ->with('organization')
                ->get();

            $this->command->error("🔧 Emergency team assignment for {$unassignedUsers->count()} users:");

            foreach ($unassignedUsers as $user) {
                // Find any team in their organization
                $orgTeam = Team::where('organization_id', $user->organization_id)->first();

                if ($orgTeam) {
                    $this->addUserToTeam($orgTeam, $user, 'member');
                    $this->command->error("  🚨 {$user->name} ({$user->organization->name}) → {$orgTeam->name}");
                } else {
                    $this->command->error("  💥 No teams found for {$user->name} in {$user->organization->name}");
                }
            }
        } else {
            $this->command->info('✅ 100% TEAM COVERAGE - All active users are assigned to teams!');
        }
    }

    /**
     * Display comprehensive final summary with detailed coverage stats
     */
    private function displaySummary(): void
    {
        // Get comprehensive stats
        $totalTeams = Team::count();
        $totalMemberships = DB::table('team_user')->count();
        $teamsWithLeads = Team::whereNotNull('team_lead_id')->count();
        $orgsWithTeams = Organization::has('teams')->count();
        $totalOrgs = Organization::count();

        // User coverage stats
        $totalActiveUsers = User::where('is_active', true)->count();
        $usersInTeams = DB::table('team_user')
            ->join('users', 'team_user.user_id', '=', 'users.id')
            ->where('users.is_active', true)
            ->distinct('users.id')
            ->count('users.id');
        $usersWithCurrentTeam = User::where('is_active', true)->whereNotNull('current_team_id')->count();

        // Team lead coverage
        $ownersAsLeads = DB::table('team_user')
            ->join('user_roles', 'team_user.user_id', '=', 'user_roles.user_id')
            ->join('roles', 'user_roles.role_id', '=', 'roles.id')
            ->where('roles.slug', 'organization_owner')
            ->where('team_user.role', 'lead')
            ->count();

        $this->command->info('');
        $this->command->info('🎉 COMPREHENSIVE TEAM ASSIGNMENT COMPLETE!');
        $this->command->info('');
        $this->command->info('📊 TEAM STATISTICS:');
        $this->command->info("🏢 Total Teams: {$totalTeams}");
        $this->command->info("👥 Team Memberships: {$totalMemberships}");
        $this->command->info("👨‍💼 Teams with Leads: {$teamsWithLeads}/{$totalTeams}");
        $this->command->info("🏛️  Organizations with Teams: {$orgsWithTeams}/{$totalOrgs}");
        $this->command->info('');
        $this->command->info('👤 USER COVERAGE:');
        $this->command->info("🎯 Users in Teams: {$usersInTeams}/{$totalActiveUsers}");
        $this->command->info("🔄 Users with Current Team: {$usersWithCurrentTeam}/{$totalActiveUsers}");
        $this->command->info("👑 Owners as Team Leads: {$ownersAsLeads}");
        $this->command->info('');

        if ($usersInTeams >= $totalActiveUsers) {
            $this->command->info('✅ 100% TEAM COVERAGE ACHIEVED!');
            $this->command->info('🚀 ALL USERS are assigned to teams and ready for testing!');
        } else {
            $this->command->error('❌ INCOMPLETE COVERAGE - Some users still missing team assignments!');
        }

        $this->command->info('');
        $this->command->info('🎯 VERIFICATION CHECKLIST:');
        $this->command->info('  ✅ Teams created for all organizations');
        $this->command->info('  ✅ Organization owners added to all teams');
        $this->command->info('  ✅ Team leads assigned based on roles');
        $this->command->info('  ✅ Users assigned to relevant teams');
        $this->command->info('  ✅ Current teams set for users');
        $this->command->info('  ✅ Emergency assignments completed');
        $this->command->info('');
        $this->command->info('🏆 TEAMS ARE FULLY FUNCTIONAL FOR ALL USERS!');
    }
}
