<?php

namespace App\Support;

use App\Models\Organization\Organization;
use App\Models\User\User;
use Closure;

class AuditContext
{
    /**
     * Stack of contexts. Each entry: [actor, organization, metadata].
     * Last entry wins for resolve* calls.
     */
    protected static array $stack = [];

    /**
     * Push a new context, run the callback, then pop.
     * Use this to wrap job/webhook handlers so emitted audit rows inherit actor + source metadata.
     */
    public static function with(array $context, Closure $callback): mixed
    {
        self::$stack[] = [
            'actor' => $context['actor'] ?? null,
            'organization' => $context['organization'] ?? null,
            'metadata' => $context['metadata'] ?? [],
        ];

        try {
            return $callback();
        } finally {
            array_pop(self::$stack);
        }
    }

    public static function actor(): ?User
    {
        $top = self::top();

        return $top['actor'] ?? auth()->user();
    }

    public static function organization(): ?Organization
    {
        $top = self::top();
        if (! empty($top['organization'])) {
            return $top['organization'];
        }
        $actor = self::actor();

        return $actor?->organization;
    }

    public static function metadata(): array
    {
        $top = self::top();

        return $top['metadata'] ?? [];
    }

    /**
     * Test-only: clear the entire stack.
     */
    public static function reset(): void
    {
        self::$stack = [];
    }

    protected static function top(): array
    {
        return self::$stack[count(self::$stack) - 1] ?? [];
    }
}
