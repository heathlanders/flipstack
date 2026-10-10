<?php

namespace App\Services;

use App\Models\User\User;
use Illuminate\Support\Facades\Cache;

/**
 * CUSTOMIZED for your setup - Uses existing notification_settings column
 */
class NotificationPreferenceService
{
    /**
     * Get or create notification preferences for a user.
     * Uses the existing 'notification_settings' column on users table.
     */
    public function getPreferences(User $user): array
    {
        return Cache::remember(
            "notification_preferences:{$user->id}",
            now()->addHours(24),
            function () use ($user) {
                $settings = $user->notification_settings;

                // Handle JSON string, null, or already decoded array
                if (is_string($settings)) {
                    $settings = json_decode($settings, true) ?? [];
                } elseif (! is_array($settings)) {
                    $settings = [];
                }

                // Merge with defaults if not set
                return array_merge($this->getDefaultPreferences(), $settings);
            }
        );
    }

    /**
     * Update notification preferences for a user.
     */
    public function updatePreferences(User $user, array $data): array
    {
        // Get current settings and decode if JSON string
        $currentSettings = $user->notification_settings;
        if (is_string($currentSettings)) {
            $currentSettings = json_decode($currentSettings, true) ?? [];
        } elseif (! is_array($currentSettings)) {
            $currentSettings = [];
        }

        // Merge with existing settings
        $newSettings = array_merge($currentSettings, $data);

        // Update user (will be auto-encoded to JSON by cast)
        $user->update([
            'notification_settings' => $newSettings,
        ]);

        // Clear cache
        Cache::forget("notification_preferences:{$user->id}");

        return $this->getPreferences($user);
    }

    /**
     * Get enabled notification channels for a user and category.
     */
    public function getEnabledChannels(User $user, string $category): array
    {
        $preferences = $this->getPreferences($user);
        $channels = [];

        // Always include database channel if enabled
        if ($preferences['database_enabled'] ?? true) {
            $channels[] = 'database';
        }

        // Check if in quiet hours
        if ($this->isInQuietHours($preferences)) {
            // During quiet hours, only send critical notifications to database
            return $channels;
        }

        // Add broadcast for real-time in-app notifications
        if ($preferences['push_enabled'] ?? true) {
            if ($this->isChannelEnabledForCategory($preferences, 'push', $category)) {
                $channels[] = 'broadcast';
            }
        }

        // Check digest frequency
        if (($preferences['digest_frequency'] ?? 'realtime') !== 'realtime') {
            // Will be handled by digest job, so only database for now
            return $channels;
        }

        // Add email if enabled
        if ($preferences['email_enabled'] ?? true) {
            if ($this->isChannelEnabledForCategory($preferences, 'email', $category)) {
                $channels[] = 'mail';
            }
        }

        // Add SMS if enabled (check if user has phone number)
        if (($preferences['sms_enabled'] ?? false) && $user->phone) {
            if ($this->isChannelEnabledForCategory($preferences, 'sms', $category)) {
                $channels[] = 'sms';
            }
        }

        return $channels;
    }

    /**
     * Check if a channel is enabled for a specific category.
     */
    private function isChannelEnabledForCategory(array $preferences, string $channel, string $category): bool
    {
        $categoryPrefs = $preferences['category_preferences'] ?? [];

        if (isset($categoryPrefs[$category])) {
            return $categoryPrefs[$category][$channel] ?? true;
        }

        return true;
    }

    /**
     * Check if currently in quiet hours.
     */
    private function isInQuietHours(array $preferences): bool
    {
        if (! ($preferences['quiet_hours_enabled'] ?? false)) {
            return false;
        }

        $quietStart = $preferences['quiet_start'] ?? null;
        $quietEnd = $preferences['quiet_end'] ?? null;

        if (! $quietStart || ! $quietEnd) {
            return false;
        }

        $now = now()->format('H:i:s');

        // Handle overnight quiet hours (e.g., 22:00 to 08:00)
        if ($quietStart > $quietEnd) {
            return $now >= $quietStart || $now <= $quietEnd;
        }

        return $now >= $quietStart && $now <= $quietEnd;
    }

    /**
     * Check if notification should be sent based on priority filter.
     */
    public function shouldSendBasedOnPriority(User $user, string $priority): bool
    {
        $preferences = $this->getPreferences($user);

        $priorityLevels = [
            'low' => 1,
            'medium' => 2,
            'high' => 3,
            'critical' => 4,
        ];

        $minPriorityLevel = $priorityLevels[$preferences['min_priority'] ?? 'low'];
        $notificationPriorityLevel = $priorityLevels[$priority] ?? 1;

        return $notificationPriorityLevel >= $minPriorityLevel;
    }

    /**
     * Clear cached preferences for a user.
     */
    public function clearCache(User $user): void
    {
        Cache::forget("notification_preferences:{$user->id}");
    }

    /**
     * Get default preferences structure.
     */
    public function getDefaultPreferences(): array
    {
        return [
            // Global channel settings
            'email_enabled' => true,
            'sms_enabled' => false,
            'push_enabled' => false,   // TODO: Enable after configuring broadcast
            'database_enabled' => true, // Always enabled

            // Category-specific preferences
            'category_preferences' => [
                'orders' => ['email' => false, 'push' => false, 'sms' => false, 'database' => true],
                'inventory' => ['email' => false, 'push' => false, 'sms' => false, 'database' => true],
                'financial' => ['email' => false, 'push' => false, 'sms' => false, 'database' => true],
                'platform' => ['email' => false, 'push' => false, 'sms' => false, 'database' => true],
                'customer' => ['email' => false, 'push' => false, 'sms' => false, 'database' => true],
                'system' => ['email' => false, 'push' => false, 'sms' => false, 'database' => true],
            ],

            // Digest settings
            'digest_frequency' => 'realtime', // realtime, hourly, daily, weekly
            'digest_time' => '09:00',

            // Quiet hours
            'quiet_hours_enabled' => false,
            'quiet_start' => '22:00',
            'quiet_end' => '08:00',

            // Priority filtering
            'min_priority' => 'low', // Only show >= this priority
        ];
    }

    /**
     * Reset user preferences to defaults.
     */
    public function resetToDefaults(User $user): array
    {
        $user->update([
            'notification_settings' => $this->getDefaultPreferences(),
        ]);

        $this->clearCache($user);

        return $this->getPreferences($user);
    }
}
