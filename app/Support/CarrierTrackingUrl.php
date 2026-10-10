<?php

namespace App\Support;

/**
 * Build a public tracking deeplink for a given carrier + tracking number.
 * Used as a fallback when no shipping API has populated Shipment.tracking_url
 * (e.g. eBay-imported tracking, manual ship). Returns null if carrier is
 * unknown or the tracking number is missing.
 */
class CarrierTrackingUrl
{
    public static function for(?string $carrier, ?string $trackingNumber): ?string
    {
        $trackingNumber = self::normalizeTrackingNumber($trackingNumber);
        if (! $trackingNumber) {
            return null;
        }

        $key = self::normalizeCarrier($carrier) ?? self::detectCarrier($trackingNumber);
        if (! $key) {
            return null;
        }

        $tracking = rawurlencode($trackingNumber);

        return match ($key) {
            'usps' => "https://tools.usps.com/go/TrackConfirmAction?qtc_tLabels1={$tracking}",
            'ups' => "https://www.ups.com/track?tracknum={$tracking}",
            'fedex' => "https://www.fedex.com/fedextrack/?trknbr={$tracking}",
            'dhl' => "https://www.dhl.com/en/express/tracking.html?AWB={$tracking}",
            'ontrac' => "https://www.ontrac.com/tracking?number={$tracking}",
            'lasership' => "https://www.lasership.com/track/{$tracking}/0",
            'canada_post' => "https://www.canadapost-postescanada.ca/track-reperage/en#/details/{$tracking}",
            'royal_mail' => "https://www.royalmail.com/track-your-item#/tracking-results/{$tracking}",
            default => null,
        };
    }

    protected static function normalizeTrackingNumber(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $clean = preg_replace('/\s+/', '', $value) ?? '';
        if ($clean === '') {
            return null;
        }

        $len = strlen($clean);
        if ($len % 2 === 0) {
            $half = (int) ($len / 2);
            $left = substr($clean, 0, $half);
            $right = substr($clean, $half);
            if ($left === $right) {
                return $left;
            }
        }

        return $clean;
    }

    protected static function normalizeCarrier(?string $carrier): ?string
    {
        if (! $carrier) {
            return null;
        }

        $c = strtolower(trim($carrier));

        return match (true) {
            in_array($c, ['usps', 'us postal service', 'united states postal service'], true) => 'usps',
            in_array($c, ['ups', 'united parcel service'], true) => 'ups',
            $c === 'fedex' || str_starts_with($c, 'fedex') => 'fedex',
            $c === 'dhl' || str_starts_with($c, 'dhl') => 'dhl',
            $c === 'ontrac' => 'ontrac',
            $c === 'lasership' => 'lasership',
            $c === 'canada post' || $c === 'canada_post' => 'canada_post',
            $c === 'royal mail' || $c === 'royal_mail' => 'royal_mail',
            default => null,
        };
    }

    /**
     * Best-effort carrier inference from a tracking number's prefix.
     * Conservative — returns null when ambiguous.
     */
    protected static function detectCarrier(string $trackingNumber): ?string
    {
        $t = strtoupper(preg_replace('/\s+/', '', $trackingNumber) ?? '');

        if ($t === '') {
            return null;
        }

        if (str_starts_with($t, '1Z')) {
            return 'ups';
        }

        // USPS Priority/Express labels typically start with these IMpb prefixes.
        if (preg_match('/^(94|92|93|95|82|EA|EC|EM|RA|RB|CP|LK|UA)/', $t)) {
            return 'usps';
        }

        // FedEx tracking is usually 12 or 15 digits, all numeric.
        if (preg_match('/^\d{12}$|^\d{15}$/', $t)) {
            return 'fedex';
        }

        return null;
    }
}
