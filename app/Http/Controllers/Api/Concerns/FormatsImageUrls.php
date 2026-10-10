<?php

namespace App\Http\Controllers\Api\Concerns;

/**
 * Normalises a stored image reference to an absolute, loadable URL for the mobile app.
 *
 * Intake / background-removal images are stored as storage-relative paths
 * (e.g. "queue-photos-processed/x.png"); Shopify/eBay imports store absolute URLs.
 * The web app loads relative paths because the browser resolves them against the
 * origin, but Flutter's Image.network needs an absolute URL -- so prepend the public
 * storage host for relative paths and leave http(s) URLs untouched.
 *
 * Mirrors the established pattern in ProductIntakeQueue / UserController. asset() uses
 * APP_URL (prod: https://flipstack.app).
 */
trait FormatsImageUrls
{
    protected function imageUrl(?string $url): ?string
    {
        if (empty($url)) {
            return null;
        }

        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            return $url;
        }

        $path = ltrim($url, '/');

        return str_starts_with($path, 'storage/')
            ? asset($path)
            : asset('storage/'.$path);
    }
}
