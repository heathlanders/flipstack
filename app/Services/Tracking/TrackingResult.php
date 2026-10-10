<?php

namespace App\Services\Tracking;

class TrackingResult
{
    /**
     * @param  array<int, array{
     *   status: string,
     *   description?: ?string,
     *   city?: ?string,
     *   state?: ?string,
     *   postal_code?: ?string,
     *   country?: ?string,
     *   source_event_id?: ?string,
     *   occurred_at: \DateTimeInterface,
     *   raw?: ?array
     * }>  $events
     */
    public function __construct(
        public readonly array $events = [],
        public readonly ?string $latestStatus = null,
        public readonly ?\DateTimeInterface $estimatedDeliveryAt = null,
        public readonly ?\DateTimeInterface $deliveredAt = null,
    ) {}

    public function isEmpty(): bool
    {
        return empty($this->events) && $this->latestStatus === null;
    }
}
