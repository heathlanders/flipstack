<?php

namespace App\Services\NotificationDispatch;

use Illuminate\Support\Facades\Cache;

/**
 * Suppression window keyed by org + event_key + related identifier.
 *
 * `claim()` returns true the first time within the window and false on
 * subsequent calls. Uses Cache::add (atomic set-if-absent) so multiple
 * workers cannot all win simultaneously.
 */
class Dedup
{
    public function claim(int $organizationId, string $eventKey, ?string $relatedId, int $minutes): bool
    {
        if ($minutes <= 0) {
            return true;
        }

        $key = $this->key($organizationId, $eventKey, $relatedId);

        return Cache::add($key, 1, now()->addMinutes($minutes));
    }

    public function release(int $organizationId, string $eventKey, ?string $relatedId): void
    {
        Cache::forget($this->key($organizationId, $eventKey, $relatedId));
    }

    private function key(int $organizationId, string $eventKey, ?string $relatedId): string
    {
        return sprintf('notif:dedup:%d:%s:%s', $organizationId, $eventKey, $relatedId ?? 'global');
    }
}
