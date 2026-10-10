<?php

namespace App\Services\Tracking;

use App\Models\Fulfillment\Shipment;
use App\Services\Tracking\Adapters\EasyPostTrackingAdapter;
use App\Services\Tracking\Adapters\EbayTrackingAdapter;
use App\Services\Tracking\Adapters\UspsTrackingAdapter;
use App\Services\Tracking\Contracts\TrackingProvider;

class TrackingProviderRegistry
{
    /**
     * Order matters — first supporting provider wins for a given shipment.
     * Paid universal (easypost) wins over free carrier-specific (usps); eBay
     * Trading API is registered last as a marketplace-side fallback for orgs
     * with no carrier-tracking integration yet.
     *
     * @return array<int, TrackingProvider>
     */
    public function all(): array
    {
        return [
            app(EasyPostTrackingAdapter::class),
            app(UspsTrackingAdapter::class),
            app(EbayTrackingAdapter::class),
        ];
    }

    public function resolve(Shipment $shipment): ?TrackingProvider
    {
        foreach ($this->all() as $provider) {
            if ($provider->supports($shipment)) {
                return $provider;
            }
        }

        return null;
    }
}
