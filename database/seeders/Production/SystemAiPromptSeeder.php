<?php

namespace Database\Seeders\Production;

use Database\Seeders\AiCategoryPromptSeeder;
use Illuminate\Database\Seeder;

class SystemAiPromptSeeder extends Seeder
{
    /**
     * Seed system AI prompts with organization_id = NULL and is_system = true.
     *
     * This reuses the existing AiCategoryPromptSeeder which already creates
     * system-level prompts (organization_id = null, is_system = true).
     */
    public function run(): void
    {
        $this->command->info('Seeding AI category prompts...');

        // Use the existing AiCategoryPromptSeeder which already handles system prompts
        $this->call(AiCategoryPromptSeeder::class);

        $promptCount = \App\Models\AI\AiCategoryPrompt::whereNull('organization_id')
            ->where('is_system', true)
            ->count();

        $this->command->info("AI prompts seeded: {$promptCount} system prompts");
    }
}
