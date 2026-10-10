<?php

namespace App\Http\Controllers\Supplier;

use App\Http\Controllers\Controller;
use App\Models\Inventory\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class SupplierSettingsController extends Controller
{
    /**
     * Display the supplier settings page
     */
    public function index(Request $request)
    {
        $supplier = $this->getAuthenticatedSupplier();

        if (! $supplier) {
            return redirect()->route('dashboard')
                ->with('error', 'Supplier account not found.');
        }

        $supplier->load(['currentTier', 'commissionStructure']);

        $notificationPreferences = $supplier->meta_data['notification_preferences'] ?? [
            'categories' => [
                'settlement' => ['email' => true, 'sms' => false, 'push' => true],
                'tier_change' => ['email' => true, 'sms' => true, 'push' => true],
                'approval' => ['email' => true, 'sms' => false, 'push' => true],
                'payment' => ['email' => true, 'sms' => true, 'push' => true],
                'system' => ['email' => true, 'sms' => false, 'push' => false],
            ],
        ];

        $tierInfo = null;
        if ($supplier->currentTier) {
            $tierInfo = [
                'name' => $supplier->currentTier->tier_name,
                'code' => $supplier->currentTier->tier_code,
                'description' => $supplier->currentTier->description ?? null,
                'benefits' => $supplier->currentTier->benefits ?? [],
                'commission_adjustment' => $supplier->currentTier->commission_adjustment,
                'next_evaluation' => $supplier->tier_evaluation_due_at?->toDateString(),
            ];
        }

        $commissionInfo = null;
        if ($supplier->commissionStructure) {
            $commissionInfo = [
                'name' => $supplier->commissionStructure->name,
                'type' => $supplier->commissionStructure->type?->label() ?? $supplier->commissionStructure->type,
                'base_rate' => $supplier->commissionStructure->base_percentage,
                'effective_rate' => $supplier->getEffectiveCommissionRate(),
            ];
        }

        return Inertia::render('Supplier/Settings', [
            'supplier' => [
                'id' => $supplier->id,
                'name' => $supplier->name,
                'company_name' => $supplier->company_name,
                'email' => $supplier->email,
                'phone' => $supplier->phone,
                'address_line1' => $supplier->address_line1,
                'address_line2' => $supplier->address_line2,
                'city' => $supplier->city,
                'state' => $supplier->state,
                'postal_code' => $supplier->postal_code,
                'country' => $supplier->country,
                'w9_on_file' => $supplier->w9_on_file,
                'contract_signed' => $supplier->contract_signed,
                'contract_expires_at' => $supplier->contract_expires_at?->toDateString(),
                'payment_method' => $supplier->payment_method,
                'payment_info' => $supplier->payment_info,
                'bank_account_last4' => $supplier->bank_account_last4,
                'settlement_schedule' => $supplier->settlement_schedule,
                'settlement_day' => $supplier->settlement_day,
                'next_settlement_date' => $supplier->next_settlement_date?->toDateString(),
                'cached_performance_score' => $supplier->cached_performance_score,
                'cached_quality_score' => $supplier->cached_quality_score,
                'cached_fulfillment_score' => $supplier->cached_fulfillment_score,
                'cached_volume_score' => $supplier->cached_volume_score,
            ],
            'paymentMethods' => [
                ['value' => 'ach', 'label' => 'ACH Bank Transfer'],
                ['value' => 'check', 'label' => 'Check'],
                ['value' => 'paypal', 'label' => 'PayPal'],
                ['value' => 'wire', 'label' => 'Wire Transfer'],
            ],
            'notificationPreferences' => $notificationPreferences,
            'tierInfo' => $tierInfo,
            'commissionInfo' => $commissionInfo,
        ]);
    }

    /**
     * Update supplier settings
     */
    public function update(Request $request)
    {
        $supplier = $this->getAuthenticatedSupplier();

        if (! $supplier) {
            return redirect()->route('dashboard')
                ->with('error', 'Supplier account not found.');
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'company_name' => 'nullable|string|max:255',
            'email' => 'sometimes|email|max:255',
            'phone' => 'nullable|string|max:50',
            'address_line1' => 'nullable|string|max:255',
            'address_line2' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:255',
            'state' => 'nullable|string|max:255',
            'postal_code' => 'nullable|string|max:20',
            'country' => 'nullable|string|max:100',
            'payment_method' => 'nullable|string|in:ach,check,paypal,wire',
            'payment_info' => 'nullable|array',
            'bank_account_last4' => 'nullable|string|max:4',
            'notification_preferences' => 'nullable|array',
        ]);

        // Extract notification preferences separately
        $notificationPreferences = $validated['notification_preferences'] ?? null;
        unset($validated['notification_preferences']);

        // Update supplier profile fields
        $supplier->fill($validated);

        // Update notification preferences in meta_data
        if ($notificationPreferences !== null) {
            $metaData = $supplier->meta_data ?? [];
            $metaData['notification_preferences'] = $notificationPreferences;
            $supplier->meta_data = $metaData;
        }

        $supplier->save();

        return redirect()->back()->with('success', 'Settings updated successfully.');
    }

    /**
     * Get the authenticated supplier
     */
    protected function getAuthenticatedSupplier(): ?Supplier
    {
        $user = Auth::user();

        if ($user->supplier_id) {
            return Supplier::find($user->supplier_id);
        }

        return Supplier::where('email', $user->email)
            ->orWhere('user_id', $user->id)
            ->first();
    }
}
