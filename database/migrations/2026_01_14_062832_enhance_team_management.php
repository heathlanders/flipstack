<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up()
    {
        // Enhanced teams table
        Schema::table('teams', function (Blueprint $table) {
            // Add nullable columns first, then populate and make non-nullable where needed
            if (! Schema::hasColumn('teams', 'slug')) {
                $table->string('slug')->nullable()->after('name');
            }
            if (! Schema::hasColumn('teams', 'description')) {
                $table->text('description')->nullable()->after('slug');
            }
            if (! Schema::hasColumn('teams', 'department')) {
                $table->string('department')->nullable()->after('description');
            }
            if (! Schema::hasColumn('teams', 'organization_id')) {
                $table->foreignId('organization_id')->nullable()->after('department');
            }
            if (! Schema::hasColumn('teams', 'team_lead_id')) {
                $table->foreignId('team_lead_id')->nullable()->after('organization_id');
            }
            if (! Schema::hasColumn('teams', 'settings')) {
                $table->json('settings')->nullable()->after('team_lead_id');
            }
            if (! Schema::hasColumn('teams', 'permissions')) {
                $table->json('permissions')->nullable()->after('settings');
            }
            if (! Schema::hasColumn('teams', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('permissions');
            }
            if (! Schema::hasColumn('teams', 'max_members')) {
                $table->integer('max_members')->nullable()->after('is_active');
            }
            if (! Schema::hasColumn('teams', 'created_by')) {
                $table->foreignId('created_by')->nullable()->after('max_members');
            }
            if (! Schema::hasColumn('teams', 'performance_metrics')) {
                $table->json('performance_metrics')->nullable()->after('created_by');
            }
            if (! Schema::hasColumn('teams', 'goals')) {
                $table->json('goals')->nullable()->after('performance_metrics');
            }
            if (! Schema::hasColumn('teams', 'budget')) {
                $table->decimal('budget', 10, 2)->nullable()->after('goals');
            }
            if (! Schema::hasColumn('teams', 'contact_info')) {
                $table->json('contact_info')->nullable()->after('budget');
            }
            if (! Schema::hasColumn('teams', 'deleted_at')) {
                $table->softDeletes();
            }
        });

        // Populate slug for existing teams
        if (Schema::hasColumn('teams', 'slug')) {
            $teams = DB::table('teams')->whereNull('slug')->get();
            foreach ($teams as $team) {
                $baseSlug = Str::slug($team->name);
                $slug = $baseSlug;
                $counter = 1;

                // Ensure unique slug
                while (DB::table('teams')->where('slug', $slug)->where('id', '!=', $team->id)->exists()) {
                    $slug = $baseSlug.'-'.$counter;
                    $counter++;
                }

                DB::table('teams')
                    ->where('id', $team->id)
                    ->update(['slug' => $slug]);
            }
        }

        // Get first organization for existing teams (or create default)
        $defaultOrgId = 1;
        if (Schema::hasTable('organizations')) {
            $firstOrg = DB::table('organizations')->first();
            if ($firstOrg) {
                $defaultOrgId = $firstOrg->id;
            } else {
                // Create a default organization
                $defaultOrgId = DB::table('organizations')->insertGetId([
                    'name' => 'Default Organization',
                    'slug' => 'default',
                    'email' => 'admin@default.local',
                    'status' => 'active',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // Populate organization_id for existing teams
        if (Schema::hasColumn('teams', 'organization_id')) {
            DB::table('teams')
                ->whereNull('organization_id')
                ->update(['organization_id' => $defaultOrgId]);
        }

        // Get first user for created_by field
        $defaultUserId = null;
        if (Schema::hasTable('users')) {
            $firstUser = DB::table('users')->first();
            if ($firstUser) {
                $defaultUserId = $firstUser->id;

                // Populate created_by for existing teams
                if (Schema::hasColumn('teams', 'created_by')) {
                    DB::table('teams')
                        ->whereNull('created_by')
                        ->update(['created_by' => $defaultUserId]);
                }
            }
        }

        // Now make slug unique and organization_id/created_by required if we have data
        Schema::table('teams', function (Blueprint $table) {
            // Add unique constraint to slug
            if (Schema::hasColumn('teams', 'slug')) {
                $table->unique('slug')->change();
            }
        });

        // Enhanced user_teams pivot table
        if (! Schema::hasTable('user_teams')) {
            Schema::create('user_teams', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->onDelete('cascade');
                $table->foreignId('team_id')->constrained()->onDelete('cascade');
                $table->foreignId('role_id')->nullable()->constrained()->onDelete('set null');
                $table->timestamp('joined_at')->useCurrent();
                $table->boolean('is_active')->default(true);
                $table->json('permissions')->nullable();
                $table->timestamps();

                $table->unique(['user_id', 'team_id']);
                $table->index(['team_id', 'is_active']);
            });
        }

        // Team roles mapping
        if (! Schema::hasTable('team_roles')) {
            Schema::create('team_roles', function (Blueprint $table) {
                $table->id();
                $table->foreignId('team_id')->constrained()->onDelete('cascade');
                $table->foreignId('role_id')->constrained()->onDelete('cascade');
                $table->boolean('is_default')->default(false);
                $table->timestamps();

                $table->unique(['team_id', 'role_id']);
            });
        }

        // Organization settings
        if (! Schema::hasTable('organization_settings')) {
            Schema::create('organization_settings', function (Blueprint $table) {
                $table->id();
                $table->foreignId('organization_id')->constrained()->onDelete('cascade');
                $table->string('key');
                $table->json('value');
                $table->string('type')->default('general');
                $table->boolean('is_encrypted')->default(false);
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->unique(['organization_id', 'key']);
                $table->index(['organization_id', 'type']);
            });
        }

        // Onboarding progress
        if (! Schema::hasTable('onboarding_progress')) {
            Schema::create('onboarding_progress', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->onDelete('cascade');
                $table->foreignId('organization_id')->constrained()->onDelete('cascade');
                $table->json('steps_completed')->nullable();
                $table->string('current_step')->default('welcome');
                $table->json('data')->nullable();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamp('skipped_at')->nullable();
                $table->integer('completion_time_minutes')->nullable();
                $table->string('skip_reason')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->unique('user_id');
                $table->index(['organization_id', 'current_step']);
            });
        }

        // User activity logs
        if (! Schema::hasTable('user_activity_logs')) {
            Schema::create('user_activity_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->onDelete('cascade');
                $table->foreignId('organization_id')->constrained()->onDelete('cascade');
                $table->foreignId('team_id')->nullable()->constrained()->onDelete('set null');
                $table->string('action');
                $table->string('description');
                $table->json('metadata')->nullable();
                $table->string('ip_address')->nullable();
                $table->string('user_agent')->nullable();
                $table->string('session_id')->nullable();
                $table->timestamp('performed_at')->useCurrent();
                $table->timestamps();

                $table->index(['user_id', 'created_at']);
                $table->index(['organization_id', 'action']);
                $table->index('performed_at');
            });
        }

        // Enhance existing team_invitations table (if it exists from Jetstream)
        if (Schema::hasTable('team_invitations')) {
            Schema::table('team_invitations', function (Blueprint $table) {
                // Add enhanced invitation fields if they don't exist
                if (! Schema::hasColumn('team_invitations', 'organization_id')) {
                    $table->foreignId('organization_id')->nullable()->after('team_id');
                }
                if (! Schema::hasColumn('team_invitations', 'invited_by')) {
                    $table->foreignId('invited_by')->nullable()->after('email');
                }
                if (! Schema::hasColumn('team_invitations', 'token')) {
                    $table->string('token')->unique()->nullable()->after('role');
                }
                if (! Schema::hasColumn('team_invitations', 'permissions')) {
                    $table->json('permissions')->nullable()->after('token');
                }
                if (! Schema::hasColumn('team_invitations', 'expires_at')) {
                    $table->timestamp('expires_at')->nullable()->after('permissions');
                }
                if (! Schema::hasColumn('team_invitations', 'accepted_at')) {
                    $table->timestamp('accepted_at')->nullable()->after('expires_at');
                }
                if (! Schema::hasColumn('team_invitations', 'declined_at')) {
                    $table->timestamp('declined_at')->nullable()->after('accepted_at');
                }
                if (! Schema::hasColumn('team_invitations', 'cancelled_at')) {
                    $table->timestamp('cancelled_at')->nullable()->after('declined_at');
                }
                if (! Schema::hasColumn('team_invitations', 'message')) {
                    $table->text('message')->nullable()->after('cancelled_at');
                }
                if (! Schema::hasColumn('team_invitations', 'metadata')) {
                    $table->json('metadata')->nullable()->after('message');
                }
            });

            // Populate default values for existing invitations
            if (Schema::hasColumn('team_invitations', 'organization_id') && $defaultOrgId) {
                DB::table('team_invitations')
                    ->whereNull('organization_id')
                    ->update(['organization_id' => $defaultOrgId]);
            }

            if (Schema::hasColumn('team_invitations', 'expires_at')) {
                DB::table('team_invitations')
                    ->whereNull('expires_at')
                    ->update(['expires_at' => now()->addDays(7)]);
            }

            if (Schema::hasColumn('team_invitations', 'token')) {
                $invitations = DB::table('team_invitations')->whereNull('token')->get();
                foreach ($invitations as $invitation) {
                    DB::table('team_invitations')
                        ->where('id', $invitation->id)
                        ->update(['token' => Str::random(64)]);
                }
            }
        }
    }

    public function down()
    {
        // Drop new tables
        Schema::dropIfExists('user_activity_logs');
        Schema::dropIfExists('onboarding_progress');
        Schema::dropIfExists('organization_settings');
        Schema::dropIfExists('team_roles');
        Schema::dropIfExists('user_teams');

        // Remove enhanced team_invitations columns
        if (Schema::hasTable('team_invitations')) {
            Schema::table('team_invitations', function (Blueprint $table) {
                $enhancedColumns = [
                    'organization_id', 'invited_by', 'token', 'permissions',
                    'expires_at', 'accepted_at', 'declined_at', 'cancelled_at',
                    'message', 'metadata',
                ];

                foreach ($enhancedColumns as $column) {
                    if (Schema::hasColumn('team_invitations', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        // Remove added columns from teams table
        Schema::table('teams', function (Blueprint $table) {
            $columns = [
                'slug', 'description', 'department', 'organization_id',
                'team_lead_id', 'settings', 'permissions', 'is_active',
                'max_members', 'created_by', 'performance_metrics',
                'goals', 'budget', 'contact_info', 'deleted_at',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('teams', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
