<?php

namespace App\Services\Currency;

use App\Models\System\ExchangeRate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ExchangeRateProvider
{
    /**
     * Fetch exchange rates for a given base currency.
     *
     * Uses Open Exchange Rates API when configured, falls back to hardcoded rates.
     */
    public function fetchRates(string $baseCurrency = 'USD'): array
    {
        $appId = config('services.openexchangerates.app_id');

        if ($appId) {
            try {
                return $this->fetchFromApi($baseCurrency, $appId);
            } catch (\Throwable $e) {
                Log::warning('ExchangeRateProvider: API call failed, using fallback rates.', [
                    'error' => $e->getMessage(),
                    'base_currency' => $baseCurrency,
                ]);
            }
        }

        return $this->getFallbackRates($baseCurrency);
    }

    protected function fetchFromApi(string $baseCurrency, string $appId): array
    {
        $baseUrl = config('services.openexchangerates.base_url', 'https://openexchangerates.org/api');

        // Free tier only supports base=USD, so always fetch USD and convert
        $response = Http::timeout(10)
            ->get("{$baseUrl}/latest.json", [
                'app_id' => $appId,
            ]);

        if (! $response->successful()) {
            throw new \RuntimeException("API returned HTTP {$response->status()}");
        }

        $usdRates = $response->json('rates', []);

        if (empty($usdRates)) {
            throw new \RuntimeException('API returned empty rates');
        }

        // Filter to supported currencies
        $supported = ['EUR', 'GBP', 'CAD', 'AUD', 'JPY', 'CHF', 'CNY', 'INR', 'MXN'];
        $filteredRates = array_intersect_key($usdRates, array_flip($supported));

        if ($baseCurrency === 'USD') {
            return $filteredRates;
        }

        // Convert rates to requested base currency
        if (! isset($usdRates[$baseCurrency])) {
            Log::warning('ExchangeRateProvider: unsupported base currency requested.', [
                'base_currency' => $baseCurrency,
            ]);

            return [];
        }

        $baseToUsd = 1.0 / $usdRates[$baseCurrency];
        $rates = ['USD' => round($baseToUsd, 8)];

        foreach ($filteredRates as $currency => $usdRate) {
            if ($currency === $baseCurrency) {
                continue;
            }
            $rates[$currency] = round($usdRate * $baseToUsd, 8);
        }

        return $rates;
    }

    protected function getFallbackRates(string $baseCurrency): array
    {
        Log::info('ExchangeRateProvider: using fallback rates (no API key configured).', [
            'base_currency' => $baseCurrency,
        ]);

        $usdRates = [
            'EUR' => 0.92150000,
            'GBP' => 0.79230000,
            'CAD' => 1.36540000,
            'AUD' => 1.53280000,
            'JPY' => 149.85000000,
            'CHF' => 0.88120000,
            'CNY' => 7.24500000,
            'INR' => 83.12000000,
            'MXN' => 17.15000000,
        ];

        if ($baseCurrency === 'USD') {
            return $usdRates;
        }

        if (! isset($usdRates[$baseCurrency])) {
            Log::warning('ExchangeRateProvider: unsupported base currency requested.', [
                'base_currency' => $baseCurrency,
            ]);

            return [];
        }

        $baseToUsd = 1.0 / $usdRates[$baseCurrency];
        $rates = ['USD' => round($baseToUsd, 8)];

        foreach ($usdRates as $currency => $usdRate) {
            if ($currency === $baseCurrency) {
                continue;
            }
            $rates[$currency] = round($usdRate * $baseToUsd, 8);
        }

        return $rates;
    }

    /**
     * Fetch and store exchange rates in the database.
     */
    public function syncRates(string $baseCurrency = 'USD'): int
    {
        $rates = $this->fetchRates($baseCurrency);
        $now = now();
        $count = 0;

        foreach ($rates as $targetCurrency => $rate) {
            ExchangeRate::create([
                'base_currency' => $baseCurrency,
                'target_currency' => $targetCurrency,
                'rate' => $rate,
                'source' => 'api',
                'fetched_at' => $now,
            ]);
            $count++;
        }

        Log::info('ExchangeRateProvider: synced exchange rates.', [
            'base_currency' => $baseCurrency,
            'rates_saved' => $count,
        ]);

        return $count;
    }
}
