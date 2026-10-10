<?php

namespace App\Http\Controllers\Notification;

use App\Http\Controllers\Controller;
use App\Models\Notification\NotificationRule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

/**
 * Owner-only management of per-org notification routing rules.
 */
class NotificationRuleController extends Controller
{
    private const ALLOWED_ROLES = ['super_admin', 'organization_owner', 'admin'];

    public function index()
    {
        $this->authorizeOwner();

        $orgId = Auth::user()->organization_id;

        $rules = NotificationRule::with('recipients')
            ->where('organization_id', $orgId)
            ->orderBy('event_key')
            ->get();

        $registry = collect(config('notifications-events.events', []))
            ->map(fn ($cfg, $key) => [
                'event_key' => $key,
                'description' => $cfg['description'] ?? $key,
                'default_priority' => $cfg['priority'] ?? 'medium',
                'default_category' => $cfg['category'] ?? 'system',
                'default_recipients' => $cfg['default_recipients'] ?? [],
                'default_dedup_minutes' => $cfg['dedup_minutes'] ?? null,
            ])
            ->values();

        return Inertia::render('Settings/Notifications/Rules', [
            'rules' => $rules,
            'registry' => $registry,
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizeOwner();

        $data = $this->validateRule($request);

        $orgId = Auth::user()->organization_id;

        return DB::transaction(function () use ($orgId, $data) {
            $rule = NotificationRule::updateOrCreate(
                ['organization_id' => $orgId, 'event_key' => $data['event_key']],
                [
                    'active' => $data['active'] ?? true,
                    'priority_override' => $data['priority_override'] ?? null,
                    'channel_overrides' => $data['channel_overrides'] ?? null,
                    'escalation_minutes' => $data['escalation_minutes'] ?? null,
                    'dedup_minutes' => $data['dedup_minutes'] ?? null,
                    'description' => $data['description'] ?? null,
                ],
            );

            $rule->recipients()->delete();
            foreach ($data['recipients'] ?? [] as $r) {
                $rule->recipients()->create([
                    'recipient_type' => $r['recipient_type'],
                    'recipient_value' => $r['recipient_value'],
                    'escalation_step' => $r['escalation_step'] ?? 0,
                ]);
            }

            return redirect()->back()->with('success', 'Notification rule saved.');
        });
    }

    public function destroy(NotificationRule $rule)
    {
        $this->authorizeOwner();

        if ($rule->organization_id !== Auth::user()->organization_id) {
            abort(403);
        }

        $rule->delete();

        return redirect()->back()->with('success', 'Notification rule removed.');
    }

    private function validateRule(Request $request): array
    {
        return $request->validate([
            'event_key' => 'required|string|max:191',
            'active' => 'boolean',
            'priority_override' => 'nullable|in:low,medium,high,critical',
            'channel_overrides' => 'nullable|array',
            'escalation_minutes' => 'nullable|integer|min:1|max:43200',
            'dedup_minutes' => 'nullable|integer|min:0|max:43200',
            'description' => 'nullable|string|max:500',
            'recipients' => 'array',
            'recipients.*.recipient_type' => 'required|in:user,role,external_email,supplier,customer',
            'recipients.*.recipient_value' => 'required|string|max:191',
            'recipients.*.escalation_step' => 'nullable|integer|min:0|max:10',
        ]);
    }

    private function authorizeOwner(): void
    {
        $user = Auth::user();
        if (! $user) {
            abort(403);
        }

        $hasRole = $user->roles()
            ->whereIn('slug', self::ALLOWED_ROLES)
            ->exists();

        if (! $hasRole) {
            abort(403);
        }
    }
}
