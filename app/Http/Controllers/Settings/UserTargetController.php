<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Analytics\UserTarget;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class UserTargetController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth']);
    }

    private function authorizeManager(Request $request): array
    {
        $user = $request->user();
        $orgId = $user->currentTeam?->organization_id ?? $user->current_organization_id ?? $user->organization_id;
        $isManager = $user->hasRole(['super_admin', 'organization_owner', 'admin', 'manager']);

        if (! $isManager) {
            abort(403, 'Only managers can manage performance targets.');
        }

        return [$user, $orgId];
    }

    public function index(Request $request): Response
    {
        [$authUser, $orgId] = $this->authorizeManager($request);

        $targets = UserTarget::where('organization_id', $orgId)
            ->orderBy('scope_type')
            ->orderBy('metric')
            ->orderBy('period')
            ->get();

        $usersById = DB::table('users')->where('organization_id', $orgId)
            ->pluck('name', 'id');
        $rolesById = DB::table('roles')->pluck('name', 'id');
        $teamsById = DB::table('teams')->where('organization_id', $orgId)->pluck('name', 'id');

        $payload = $targets->map(function ($t) use ($usersById, $rolesById, $teamsById) {
            $scopeLabel = match ($t->scope_type) {
                UserTarget::SCOPE_USER => $usersById[$t->scope_id] ?? "User #{$t->scope_id}",
                UserTarget::SCOPE_ROLE => $rolesById[$t->scope_id] ?? "Role #{$t->scope_id}",
                UserTarget::SCOPE_TEAM => $teamsById[$t->scope_id] ?? "Team #{$t->scope_id}",
                UserTarget::SCOPE_ORG => 'All users',
                default => $t->scope_id,
            };

            return [
                'id' => $t->id,
                'scope_type' => $t->scope_type,
                'scope_id' => $t->scope_id,
                'scope_label' => $scopeLabel,
                'metric' => $t->metric,
                'period' => $t->period,
                'target_value' => (float) $t->target_value,
                'effective_from' => $t->effective_from?->toDateString(),
                'effective_to' => $t->effective_to?->toDateString(),
                'notes' => $t->notes,
            ];
        });

        return Inertia::render('Settings/Targets/Index', [
            'targets' => $payload,
            'metrics' => UserTarget::METRICS,
            'periods' => [UserTarget::PERIOD_DAILY, UserTarget::PERIOD_WEEKLY, UserTarget::PERIOD_MONTHLY],
            'users' => $usersById->map(fn ($name, $id) => ['id' => $id, 'name' => $name])->values(),
            'roles' => $rolesById->map(fn ($name, $id) => ['id' => $id, 'name' => $name])->values(),
            'teams' => $teamsById->map(fn ($name, $id) => ['id' => $id, 'name' => $name])->values(),
        ]);
    }

    public function store(Request $request)
    {
        [$authUser, $orgId] = $this->authorizeManager($request);

        $validated = $request->validate([
            'scope_type' => 'required|in:user,role,team,organization',
            'scope_id' => 'nullable|string|max:255',
            'metric' => 'required|string|in:'.implode(',', UserTarget::METRICS),
            'period' => 'required|in:daily,weekly,monthly',
            'target_value' => 'required|numeric|min:0',
            'effective_from' => 'required|date',
            'effective_to' => 'nullable|date|after_or_equal:effective_from',
            'notes' => 'nullable|string|max:1000',
        ]);

        if ($validated['scope_type'] !== UserTarget::SCOPE_ORG && empty($validated['scope_id'])) {
            return back()->withErrors(['scope_id' => 'A scope target is required for this scope type.'])->withInput();
        }
        if ($validated['scope_type'] === UserTarget::SCOPE_ORG) {
            $validated['scope_id'] = null;
        }

        UserTarget::create(array_merge($validated, [
            'organization_id' => $orgId,
            'created_by' => $authUser->id,
        ]));

        return redirect()->route('settings.targets.index')->with('success', 'Target created.');
    }

    public function update(Request $request, UserTarget $target)
    {
        [$authUser, $orgId] = $this->authorizeManager($request);

        if ($target->organization_id !== $orgId) {
            abort(404);
        }

        $validated = $request->validate([
            'scope_type' => 'required|in:user,role,team,organization',
            'scope_id' => 'nullable|string|max:255',
            'metric' => 'required|string|in:'.implode(',', UserTarget::METRICS),
            'period' => 'required|in:daily,weekly,monthly',
            'target_value' => 'required|numeric|min:0',
            'effective_from' => 'required|date',
            'effective_to' => 'nullable|date|after_or_equal:effective_from',
            'notes' => 'nullable|string|max:1000',
        ]);

        if ($validated['scope_type'] === UserTarget::SCOPE_ORG) {
            $validated['scope_id'] = null;
        }

        $target->update($validated);

        return redirect()->route('settings.targets.index')->with('success', 'Target updated.');
    }

    public function destroy(Request $request, UserTarget $target)
    {
        [$authUser, $orgId] = $this->authorizeManager($request);

        if ($target->organization_id !== $orgId) {
            abort(404);
        }

        $target->delete();

        return redirect()->route('settings.targets.index')->with('success', 'Target deleted.');
    }
}
