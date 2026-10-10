<?php

namespace App\Support;

use App\Models\Organization\Organization;
use App\Models\User\User;
use App\Models\User\UserActivityLog;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class AuditHelper
{
    /**
     * Emit a structured audit row. Resolves actor + organization from AuditContext (jobs/webhooks)
     * or falls back to auth()->user() (HTTP).
     */
    public static function event(
        string $action,
        ?string $description = null,
        ?Model $subject = null,
        array $metadata = [],
        string $severity = 'info',
        ?string $category = null,
        ?User $actor = null,
        ?Organization $organization = null,
    ): ?UserActivityLog {
        $actor ??= AuditContext::actor();
        $organization ??= AuditContext::organization();
        $organization ??= $actor && $actor->organization_id
            ? Organization::find($actor->organization_id)
            : null;
        $organization ??= $subject && isset($subject->organization_id)
            ? Organization::find($subject->organization_id)
            : null;

        if (! $organization) {
            // Cannot persist without an organization (FK NOT NULL). Drop silently to avoid breaking the
            // primary action. Surface via the audit-coverage agent's "null-actor + no-org" alert instead.
            return null;
        }

        $metadata = array_merge(AuditContext::metadata(), $metadata);

        if ($subject) {
            $metadata['entity_label'] ??= self::deriveEntityLabel($subject);
        }

        // Audit emission MUST NOT crash the host operation (login, ship, save, webhook).
        // If the insert fails (FK violation, schema drift, encoding issue), log + swallow.
        try {
            return UserActivityLog::create([
                'user_id' => $actor?->id,
                'organization_id' => $organization->id,
                'team_id' => $actor?->current_team_id,
                'action' => $action,
                'description' => $description ?? $action,
                'metadata' => $metadata,
                'ip_address' => request()?->ip(),
                'user_agent' => request()?->userAgent(),
                'session_id' => self::sessionId(),
                'model_type' => $subject ? get_class($subject) : null,
                'model_id' => $subject?->getKey() !== null ? (string) $subject->getKey() : null,
                'severity' => $severity,
                'category' => $category ?? 'general',
            ]);
        } catch (\Throwable $e) {
            Log::error('Audit emission failed', [
                'action' => $action,
                'error' => $e->getMessage(),
                'actor_id' => $actor?->id,
                'organization_id' => $organization->id,
            ]);

            return null;
        }
    }

    /**
     * Shortcut for security-category events (login, 2FA, credential changes).
     */
    public static function security(string $action, string $description, array $metadata = []): ?UserActivityLog
    {
        return self::event(
            action: $action,
            description: $description,
            metadata: $metadata,
            severity: 'warning',
            category: 'security',
        );
    }

    /**
     * Wrap a callable with actor + organization context. Use in jobs/webhooks where auth() is null.
     */
    public static function withActor(?User $actor, Closure $callback, array $metadata = [], ?Organization $organization = null): mixed
    {
        return AuditContext::with([
            'actor' => $actor,
            'organization' => $organization ?? $actor?->organization,
            'metadata' => $metadata,
        ], $callback);
    }

    protected static function deriveEntityLabel(Model $subject): string
    {
        foreach (['name', 'title', 'sku', 'po_number', 'order_number', 'email'] as $attr) {
            if (! empty($subject->{$attr})) {
                return class_basename($subject).': '.$subject->{$attr};
            }
        }

        return class_basename($subject).' #'.$subject->getKey();
    }

    protected static function sessionId(): ?string
    {
        try {
            return session()?->getId();
        } catch (\Throwable) {
            return null;
        }
    }
}
