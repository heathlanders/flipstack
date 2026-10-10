<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Analytics\UserCompensation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class UserCompensationController extends Controller
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
            abort(403, 'Only managers can manage labor rates.');
        }

        return [$user, $orgId];
    }

    public function index(Request $request): Response
    {
        [$authUser, $orgId] = $this->authorizeManager($request);

        $rates = UserCompensation::where('organization_id', $orgId)
            ->orderBy('user_id')
            ->orderByDesc('effective_from')
            ->get();

        $usersById = DB::table('users')->where('organization_id', $orgId)->pluck('name', 'id');

        $payload = $rates->map(fn ($r) => [
            'id' => $r->id,
            'user_id' => $r->user_id,
            'user_name' => $usersById[$r->user_id] ?? "User #{$r->user_id}",
            'hourly_rate' => (float) $r->hourly_rate,
            'minutes_per_listing' => $r->minutes_per_listing,
            'minutes_per_ticket' => $r->minutes_per_ticket,
            'effective_from' => $r->effective_from?->toDateString(),
            'effective_to' => $r->effective_to?->toDateString(),
            'notes' => $r->notes,
        ]);

        return Inertia::render('Settings/LaborRates/Index', [
            'rates' => $payload,
            'users' => $usersById->map(fn ($name, $id) => ['id' => $id, 'name' => $name])->values(),
        ]);
    }

    public function store(Request $request)
    {
        [$authUser, $orgId] = $this->authorizeManager($request);

        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'hourly_rate' => 'required|numeric|min:0',
            'minutes_per_listing' => 'required|integer|min:0|max:1440',
            'minutes_per_ticket' => 'required|integer|min:0|max:1440',
            'effective_from' => 'required|date',
            'effective_to' => 'nullable|date|after_or_equal:effective_from',
            'notes' => 'nullable|string|max:1000',
        ]);

        UserCompensation::create(array_merge($validated, [
            'organization_id' => $orgId,
            'created_by' => $authUser->id,
        ]));

        return redirect()->route('settings.labor-rates.index')->with('success', 'Rate set.');
    }

    public function update(Request $request, UserCompensation $rate)
    {
        [$authUser, $orgId] = $this->authorizeManager($request);

        if ($rate->organization_id !== $orgId) {
            abort(404);
        }

        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'hourly_rate' => 'required|numeric|min:0',
            'minutes_per_listing' => 'required|integer|min:0|max:1440',
            'minutes_per_ticket' => 'required|integer|min:0|max:1440',
            'effective_from' => 'required|date',
            'effective_to' => 'nullable|date|after_or_equal:effective_from',
            'notes' => 'nullable|string|max:1000',
        ]);

        $rate->update($validated);

        return redirect()->route('settings.labor-rates.index')->with('success', 'Rate updated.');
    }

    public function destroy(Request $request, UserCompensation $rate)
    {
        [$authUser, $orgId] = $this->authorizeManager($request);

        if ($rate->organization_id !== $orgId) {
            abort(404);
        }

        $rate->delete();

        return redirect()->route('settings.labor-rates.index')->with('success', 'Rate removed.');
    }
}
