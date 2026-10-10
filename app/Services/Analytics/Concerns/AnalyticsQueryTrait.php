<?php

namespace App\Services\Analytics\Concerns;

use Illuminate\Support\Facades\Cache;

trait AnalyticsQueryTrait
{
    protected string $organizationId;

    protected ?string $startDate = null;

    protected ?string $endDate = null;

    protected ?string $compareStartDate = null;

    protected ?string $compareEndDate = null;

    public function forOrganization(string $organizationId): self
    {
        $this->organizationId = $organizationId;

        return $this;
    }

    public function dateRange(string $startDate, string $endDate): self
    {
        $this->startDate = $startDate;
        $this->endDate = $endDate;

        return $this;
    }

    public function compareTo(string $startDate, string $endDate): self
    {
        $this->compareStartDate = $startDate;
        $this->compareEndDate = $endDate;

        return $this;
    }

    protected function isComparing(): bool
    {
        return $this->compareStartDate !== null && $this->compareEndDate !== null;
    }

    protected function withComparison(callable $fetcher): array
    {
        $current = $fetcher($this->startDate, $this->endDate);

        if (! $this->isComparing()) {
            return ['current' => $current, 'previous' => null, 'changes' => null];
        }

        $previous = $fetcher($this->compareStartDate, $this->compareEndDate);
        $changes = $this->calculateChanges($current, $previous);

        return ['current' => $current, 'previous' => $previous, 'changes' => $changes];
    }

    protected function calculateChanges(array $current, array $previous): array
    {
        $changes = [];
        foreach ($current as $key => $value) {
            if (isset($previous[$key]) && is_numeric($value) && is_numeric($previous[$key])) {
                $prev = (float) $previous[$key];
                $changes[$key] = $prev != 0
                    ? round((((float) $value - $prev) / abs($prev)) * 100, 2)
                    : ($value > 0 ? 100 : 0);
            }
        }

        return $changes;
    }

    protected function cacheKey(string $suffix): string
    {
        return "analytics:{$this->organizationId}:{$suffix}:{$this->startDate}:{$this->endDate}";
    }

    protected function cached(string $key, int $ttlSeconds, callable $callback)
    {
        $store = Cache::store();

        // Tags require a store that supports them (redis/memcached/array).
        // database/file stores throw BadMethodCallException, so fall back to plain caching.
        if (method_exists($store->getStore(), 'tags')) {
            try {
                return $store->tags(['analytics', 'org_'.$this->organizationId])
                    ->remember($this->cacheKey($key), $ttlSeconds, $callback);
            } catch (\BadMethodCallException) {
                // Driver claims tags() exists but the underlying store doesn't support it.
            }
        }

        return $store->remember($this->cacheKey($key), $ttlSeconds, $callback);
    }
}
