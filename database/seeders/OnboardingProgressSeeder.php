<?php

namespace Database\Seeders;

use App\Models\System\OnboardingProgress;
use App\Models\User\User;
use Illuminate\Database\Seeder;

class OnboardingProgressSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Marking all active users as onboarding-complete...');

        $users = User::where('is_active', true)
            ->where(function ($q) {
                $q->where('is_supplier', false)->orWhereNull('is_supplier');
            })
            ->get();

        $count = 0;
        foreach ($users as $user) {
            OnboardingProgress::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'organization_id' => $user->organization_id,
                    'steps_completed' => ['welcome', 'profile', 'teams', 'role_understanding', 'first_action', 'resources'],
                    'current_step' => 'completed',
                    'data' => [],
                    'started_at' => $user->created_at,
                    'completed_at' => $user->created_at->addMinutes(5),
                    'completion_time_minutes' => 5,
                ]
            );
            $count++;
        }

        $this->command->info("Completed onboarding for {$count} users");
    }
}
