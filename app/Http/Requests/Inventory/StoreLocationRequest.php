<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Allow creation - authorization handled by controller/middleware
        return true;
    }

    public function rules(): array
    {
        $user = auth()->user();
        $organizationId = $user->currentTeam?->organization_id ?? $user->current_organization_id ?? $user->organization_id;

        return [
            'name' => 'required|string|max:255',
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('inventory_locations')->where('organization_id', $organizationId),
            ],
            'type' => 'required|in:warehouse,store,supplier,customer,virtual',
            'address_line1' => 'nullable|string|max:255',
            'address_line2' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'country' => 'nullable|string|max:100',
            'contact_person' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'is_active' => 'boolean',
            'is_default' => 'boolean',
            // Shipping configuration
            'is_ship_from' => 'boolean',
            'default_weight_oz' => 'nullable|numeric|min:0|max:9999',
            'default_length' => 'nullable|numeric|min:0|max:999',
            'default_width' => 'nullable|numeric|min:0|max:999',
            'default_height' => 'nullable|numeric|min:0|max:999',
            // Printer assignments
            'shipping_label_printer_id' => 'nullable|exists:organization_printers,id',
            'packing_slip_printer_id' => 'nullable|exists:organization_printers,id',
            'pick_list_printer_id' => 'nullable|exists:organization_printers,id',
            'auto_print_shipping_label' => 'boolean',
            'auto_print_packing_slip' => 'boolean',
            'auto_print_pick_list' => 'boolean',
        ];
    }
}
