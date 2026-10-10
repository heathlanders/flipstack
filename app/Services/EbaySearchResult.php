<?php

namespace App\Services;

class EbaySearchResult
{
    public function __construct(
        public array $soldListings,
        public array $activeListings
    ) {}

    public function hasListings(): bool
    {
        return ! empty($this->soldListings) || ! empty($this->activeListings);
    }

    public function getSoldCount(): int
    {
        return count($this->soldListings);
    }

    public function getActiveCount(): int
    {
        return count($this->activeListings);
    }

    public function toArray(): array
    {
        return [
            'sold_listings' => $this->soldListings,
            'active_listings' => $this->activeListings,
            'sold_count' => $this->getSoldCount(),
            'active_count' => $this->getActiveCount(),
        ];
    }
}
