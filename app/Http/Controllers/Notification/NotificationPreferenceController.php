<?php

namespace App\Http\Controllers\Notification;

use App\Http\Controllers\Controller;
use App\Services\NotificationPreferenceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class NotificationPreferenceController extends Controller
{
    public function __construct(
        private NotificationPreferenceService $preferenceService
    ) {}

    /**
     * Show notification preferences page.
     */
    public function edit()
    {
        $user = Auth::user();
        $preferences = $this->preferenceService->getPreferences($user);

        return Inertia::render('Notifications/Preferences', [
            'preferences' => $preferences,
        ]);
    }

    /**
     * Update notification preferences.
     */
    public function update(Request $request)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'email_enabled' => 'boolean',
            'sms_enabled' => 'boolean',
            'push_enabled' => 'boolean',
            'database_enabled' => 'boolean',
            'category_preferences' => 'array',
            'digest_frequency' => 'in:realtime,hourly,daily,weekly',
            'digest_time' => 'nullable|date_format:H:i',
            'quiet_hours_enabled' => 'boolean',
            'quiet_start' => 'nullable|date_format:H:i',
            'quiet_end' => 'nullable|date_format:H:i',
            'min_priority' => 'in:low,medium,high,critical',
        ]);

        $preferences = $this->preferenceService->updatePreferences($user, $validated);

        return redirect()->back()->with('success', 'Notification preferences updated successfully.');
    }
}
