<?php

namespace App\Services\Tracking\Contracts;

use App\Models\Fulfillment\Shipment;
use App\Services\Tracking\TrackingResult;

interface TrackingProvider
{
    /**
     * Stable identifier persisted on Shipment.tracking_provider (e.g. "usps", "easypost").
     */
    public function name(): string;

    /**
     * Whether this provider can fetch tracking for the given shipment right now
     * (carrier supported, credentials present, tracking number set).
     */
    public function supports(Shipment $shipment): bool;

    /**
     * Fetch normalized tracking events for a shipment. Implementations should
     * return an empty TrackingResult on no-data; throw only on hard errors
     * (auth, network) so the caller can decide whether to retry.
     */
    public function fetch(Shipment $shipment): TrackingResult;
}
