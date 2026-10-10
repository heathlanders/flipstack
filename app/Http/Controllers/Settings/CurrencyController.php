<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Jobs\Currency\SyncExchangeRates;
use App\Services\Currency\CurrencyService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CurrencyController extends Controller
{
    public function __construct(
        protected CurrencyService $currencyService,
    ) {
        $this->middleware(['auth']);
    }

    /**
     * Display the currency settings page with current rates and converter.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $organizationId = $user->currentTeam?->organization_id
            ?? $user->current_organization_id
            ?? $user->organization_id;

        $baseCurrency = 'USD';
        $rates = $this->currencyService->getAvailableRates($baseCurrency);
        $supportedCurrencies = $this->currencyService->getSupportedCurrencies();

        return Inertia::render('Settings/Currency/Index', [
            'baseCurrency' => $baseCurrency,
            'rates' => $rates,
            'supportedCurrencies' => $supportedCurrencies,
            'organizationId' => $organizationId,
        ]);
    }

    /**
     * Trigger a sync of exchange rates.
     */
    public function sync(Request $request)
    {
        SyncExchangeRates::dispatch();

        return redirect()->back()->with('success', 'Exchange rate sync has been queued.');
    }

    /**
     * Convert an amount between currencies (JSON endpoint).
     */
    public function convert(Request $request)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0',
            'from' => 'required|string|size:3',
            'to' => 'required|string|size:3',
        ]);

        try {
            $converted = $this->currencyService->convert(
                (float) $validated['amount'],
                $validated['from'],
                $validated['to'],
            );

            $formatted = $this->currencyService->formatAmount($converted, $validated['to']);

            return response()->json([
                'success' => true,
                'original_amount' => (float) $validated['amount'],
                'converted_amount' => $converted,
                'formatted' => $formatted,
                'from' => $validated['from'],
                'to' => $validated['to'],
            ]);
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 422);
        }
    }
}
