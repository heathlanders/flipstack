<?php

namespace Tests\Support;

use App\Models\User\User;
use App\Models\User\UserActivityLog;
use PHPUnit\Framework\Assert;

trait AssertsAudits
{
    /**
     * Assert that an audit row matching the given action (and optionally actor + metadata subset) was emitted.
     */
    protected function assertAudited(string $action, ?User $user = null, array $metadataContains = []): UserActivityLog
    {
        $query = UserActivityLog::query()->where('action', $action);
        if ($user) {
            $query->where('user_id', $user->id);
        }

        $rows = $query->orderByDesc('id')->get();

        Assert::assertNotEmpty(
            $rows,
            "Expected audit row with action [{$action}]".($user ? " for user [{$user->id}]" : '').' but none found.',
        );

        foreach ($rows as $row) {
            $metadata = $row->metadata ?? [];
            if ($this->metadataMatches($metadata, $metadataContains)) {
                return $row;
            }
        }

        Assert::fail(
            "Expected audit row with action [{$action}] containing metadata "
            .json_encode($metadataContains, JSON_UNESCAPED_SLASHES)
            .'; saw '.$rows->count().' row(s) but metadata did not match.',
        );
    }

    /**
     * Assert NO audit row was emitted for the given action (optionally scoped to actor).
     */
    protected function assertNotAudited(string $action, ?User $user = null): void
    {
        $query = UserActivityLog::query()->where('action', $action);
        if ($user) {
            $query->where('user_id', $user->id);
        }
        Assert::assertSame(
            0,
            $query->count(),
            "Expected NO audit row with action [{$action}]".($user ? " for user [{$user->id}]" : '').' but found at least one.',
        );
    }

    /**
     * Assert the most recent audit row for a model matches the given action.
     */
    protected function assertLatestEntityAudit(string $modelClass, string|int $modelId, string $action): UserActivityLog
    {
        $row = UserActivityLog::query()
            ->where('model_type', $modelClass)
            ->where('model_id', (string) $modelId)
            ->orderByDesc('id')
            ->first();

        Assert::assertNotNull(
            $row,
            "Expected at least one audit row for {$modelClass} #{$modelId} but found none.",
        );
        Assert::assertSame(
            $action,
            $row->action,
            "Expected latest action for {$modelClass} #{$modelId} to be [{$action}], got [{$row->action}].",
        );

        return $row;
    }

    protected function metadataMatches(array $haystack, array $needle): bool
    {
        foreach ($needle as $key => $value) {
            if (! array_key_exists($key, $haystack)) {
                return false;
            }
            if (is_array($value)) {
                if (! is_array($haystack[$key]) || ! $this->metadataMatches($haystack[$key], $value)) {
                    return false;
                }
            } elseif ($haystack[$key] != $value) {
                return false;
            }
        }

        return true;
    }
}
