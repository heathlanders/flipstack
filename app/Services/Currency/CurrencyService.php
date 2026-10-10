<?php

namespace App\Services\Currency;

use App\Models\System\ExchangeRate;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use RuntimeException;

class CurrencyService
{
    /**
     * Currency symbols and labels for supported currencies.
     */
    private const CURRENCIES = [
        'USD' => ['label' => 'US Dollar', 'symbol' => '$'],
        'EUR' => ['label' => 'Euro', 'symbol' => 'EUR'],
        'GBP' => ['label' => 'British Pound', 'symbol' => 'GBP'],
        'CAD' => ['label' => 'Canadian Dollar', 'symbol' => 'CA$'],
        'AUD' => ['label' => 'Australian Dollar', 'symbol' => 'A$'],
        'JPY' => ['label' => 'Japanese Yen', 'symbol' => 'JPY'],
        'CHF' => ['label' => 'Swiss Franc', 'symbol' => 'CHF'],
        'CNY' => ['label' => 'Chinese Yuan', 'symbol' => 'CNY'],
        'INR' => ['label' => 'Indian Rupee', 'symbol' => 'INR'],
        'MXN' => ['label' => 'Mexican Peso', 'symbol' => 'MX$'],
    ];

    /**
     * Convert an amount from one currency to another.
     */
    public function convert(float $amount, string $from, string $to, ?Carbon $date = null): float
    {
        if ($from === $to) {
            return $amount;
        }

        $rate = $this->getRate($from, $to, $date);

        return round($amount * $rate, 2);
    }

    /**
     * Get the exchange rate between two currencies.
     *
     * @throws RuntimeException If no rate is found.
     */
    public function getRate(string $from, string $to, ?Carbon $date = null): float
    {
        if ($from === $to) {
            return 1.0;
        }

        $rate = ExchangeRate::getRate($from, $to, $date);

        if ($rate === null) {
            throw new RuntimeException("Exchange rate not found for {$from} to {$to}.");
        }

        return $rate;
    }

    /**
     * Get the latest available rates for a given base currency.
     */
    public function getAvailableRates(string $baseCurrency = 'USD'): Collection
    {
        return ExchangeRate::where('base_currency', $baseCurrency)
            ->orderBy('fetched_at', 'desc')
            ->get()
            ->unique('target_currency')
            ->values();
    }

    /**
     * Format an amount with its currency symbol.
     */
    public function formatAmount(float $amount, string $currency): string
    {
        $symbol = self::CURRENCIES[$currency]['symbol'] ?? $currency;
        $decimals = $currency === 'JPY' ? 0 : 2;

        return $symbol.number_format($amount, $decimals);
    }

    /**
     * Return the list of supported currencies with their codes and labels.
     */
    public function getSupportedCurrencies(): array
    {
        $currencies = [];

        foreach (self::CURRENCIES as $code => $info) {
            $currencies[] = [
                'code' => $code,
                'label' => $info['label'],
                'symbol' => $info['symbol'],
            ];
        }

        return $currencies;
    }
}
