<?php

namespace App\Actions\Fortify;

use App\Models\Organization\Organization;
use App\Models\Organization\Team;
use App\Models\User\Role;
use App\Models\User\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\CreatesNewUsers;
use Laravel\Jetstream\Jetstream;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    /**
     * Create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'organization_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => $this->passwordRules(),
            'terms' => Jetstream::hasTermsAndPrivacyPolicyFeature() ? ['accepted', 'required'] : '',
        ])->validate();

        return DB::transaction(function () use ($input) {
            $organization = $this->createOrganization($input['organization_name'], $input['email']);

            $user = User::create([
                'name' => $input['name'],
                'email' => $input['email'],
                'password' => Hash::make($input['password']),
                'organization_id' => $organization->id,
            ]);

            $organization->update(['owner_id' => $user->id]);

            $this->createTeam($user, $organization);
            $this->assignOwnerRole($user, $organization);

            return $user;
        });
    }

    /**
     * Create an organization for the registering user.
     */
    protected function createOrganization(string $organizationName, string $email): Organization
    {
        $baseSlug = Str::slug($organizationName);

        $slug = $baseSlug;
        $counter = 1;
        while (Organization::where('slug', $slug)->exists()) {
            $slug = $baseSlug.'-'.$counter++;
        }

        return Organization::create([
            'name' => $organizationName,
            'slug' => $slug,
            'email' => $email,
            'status' => 'active',
        ]);
    }

    /**
     * Create a personal team for the user.
     */
    protected function createTeam(User $user, Organization $organization): void
    {
        $team = Team::forceCreate([
            'user_id' => $user->id,
            'name' => explode(' ', $user->name, 2)[0]."'s Team",
            'personal_team' => true,
            'organization_id' => $organization->id,
        ]);

        $user->ownedTeams()->save($team);
        $user->switchTeam($team);
    }

    /**
     * Assign the organization_owner role to the user.
     */
    protected function assignOwnerRole(User $user, Organization $organization): void
    {
        $ownerRole = Role::where('slug', 'organization_owner')
            ->whereNull('organization_id')
            ->first();

        if ($ownerRole) {
            $user->assignRole($ownerRole, $organization->id);
        }
    }
}
