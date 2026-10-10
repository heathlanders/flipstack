<?php

namespace App\Http\Controllers\Fulfillment;

use App\Http\Controllers\Controller;
use App\Models\Fulfillment\CarrierAccount;
use App\Models\Fulfillment\PackagePreset;
use App\Models\Integration\IntegrationCredential;
use App\Models\Organization\Organization;
use App\Models\Organization\OrganizationSettings;
use App\Services\Fulfillment\EasyPostService;
use App\Services\Fulfillment\ShippingEasyService;
use App\Services\Fulfillment\ShipStationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Inertia\Inertia;

class SettingsController extends Controller
{
    protected function getOrganizationId(): int
    {
        return auth()->user()->currentTeam?->organization_id
            ?? auth()->user()->current_organization_id
            ?? auth()->user()->organization_id;
    }

    protected function getShippingDefaults(): array
    {
        return [
            'default_carrier' => 'USPS',
            'default_service' => 'Priority',
            'auto_insurance_enabled' => false,
            'auto_insurance_threshold' => 100.00,
            'signature_confirmation' => 'none',
            'signature_threshold' => 250.00,
            'saturday_delivery' => false,
            'always_use_cheapest' => false,
            'preferred_carriers' => ['USPS', 'UPS'],
            'max_shipping_cost' => null,
            'additional_item_cost_mode' => 'percentage',
            'additional_item_cost_percent' => 50,
            'additional_item_cost_fixed' => 3.00,
        ];
    }

    protected function getLabelsDefaults(): array
    {
        return [
            'label_format' => 'PDF',
            'label_size' => '4x6',
            'include_packing_slip' => true,
            'packing_slip_notes' => '',
            'include_return_label' => false,
            'include_logo_on_slip' => true,
            'custom_footer_text' => '',
        ];
    }

    protected function getWorkflowDefaults(): array
    {
        return [
            'auto_assign_enabled' => false,
            'auto_assign_by' => 'location',
            'auto_upload_tracking' => true,
            'order_sync_interval_minutes' => 5,
            'dropship_sla_hours' => 48,
            'priority_escalation_hours' => 24,
            'auto_mark_shipped_on_label' => false,
            'require_weight_on_pack' => true,
            'allow_partial_shipments' => true,
        ];
    }

    protected function getReturnsDefaults(): array
    {
        return [
            'return_window_days' => 30,
            'restocking_fee_percent' => 0,
            'auto_approve_enabled' => false,
            'auto_approve_under' => 25.00,
            'require_photos' => false,
            'return_reasons' => ['Defective', 'Wrong item', 'Not as described', 'Changed mind', 'Other'],
            'default_refund_method' => 'original_payment',
            'auto_restock_on_receive' => false,
        ];
    }

    protected function getNotificationsDefaults(): array
    {
        return [
            'notify_on_order_import' => true,
            'notify_on_ship' => true,
            'notify_on_delivery' => false,
            'notify_on_return_request' => true,
            'stale_shipment_alert_hours' => 72,
            'sla_warning_hours' => 4,
            'daily_summary_enabled' => false,
            'notification_channel' => 'in_app',
        ];
    }

    public function index()
    {
        $orgId = $this->getOrganizationId();

        return Inertia::render('Fulfillment/Settings/Index', [
            'carriers' => CarrierAccount::where('organization_id', $orgId)->get(),
            'packagePresets' => PackagePreset::where('organization_id', $orgId)
                ->active()->ordered()->get(),
            'settings' => [
                'shipping' => OrganizationSettings::get($orgId, 'fulfillment_shipping', $this->getShippingDefaults()),
                'labels' => OrganizationSettings::get($orgId, 'fulfillment_labels', $this->getLabelsDefaults()),
                'workflow' => OrganizationSettings::get($orgId, 'fulfillment_workflow', $this->getWorkflowDefaults()),
                'returns' => OrganizationSettings::get($orgId, 'fulfillment_returns', $this->getReturnsDefaults()),
                'notifications' => OrganizationSettings::get($orgId, 'fulfillment_notifications', $this->getNotificationsDefaults()),
            ],
            'webhookUrl' => route('webhooks.easypost'),
            'shipstationWebhookUrl' => route('webhooks.shipstation'),
            'shippingEasyWebhookUrl' => route('webhooks.shippingeasy'),
            'easypostConnected' => IntegrationCredential::where('organization_id', $orgId)
                ->where('platform', 'easypost')
                ->where('is_active', true)
                ->exists(),
            'shipstationConnected' => IntegrationCredential::where('organization_id', $orgId)
                ->where('platform', 'shipstation')
                ->where('is_active', true)
                ->exists(),
            'shippingEasyConnected' => IntegrationCredential::where('organization_id', $orgId)
                ->where('platform', 'shippingeasy')
                ->where('is_active', true)
                ->exists(),
            'shippingProvider' => OrganizationSettings::get($orgId, 'fulfillment_shipping_provider', 'easypost'),
        ]);
    }

    public function updateShipping(Request $request)
    {
        $validated = $request->validate([
            'default_carrier' => 'required|string|in:USPS,UPS,FedEx,DHL',
            'default_service' => 'required|string|max:50',
            'auto_insurance_enabled' => 'boolean',
            'auto_insurance_threshold' => 'nullable|numeric|min:0',
            'signature_confirmation' => 'required|string|in:none,threshold,always',
            'signature_threshold' => 'nullable|numeric|min:0',
            'saturday_delivery' => 'boolean',
            'always_use_cheapest' => 'boolean',
            'preferred_carriers' => 'array',
            'preferred_carriers.*' => 'string|in:USPS,UPS,FedEx,DHL',
            'max_shipping_cost' => 'nullable|numeric|min:0',
            'additional_item_cost_mode' => 'nullable|string|in:percentage,fixed',
            'additional_item_cost_percent' => 'nullable|numeric|min:0|max:100',
            'additional_item_cost_fixed' => 'nullable|numeric|min:0',
        ]);

        OrganizationSettings::set(
            $this->getOrganizationId(),
            'fulfillment_shipping',
            $validated,
            'fulfillment'
        );

        return back()->with('success', 'Shipping settings saved.');
    }

    public function updateLabels(Request $request)
    {
        $validated = $request->validate([
            'label_format' => 'required|string|in:PDF,PNG,ZPL',
            'label_size' => 'required|string|in:4x6,8.5x11',
            'include_packing_slip' => 'boolean',
            'packing_slip_notes' => 'nullable|string|max:500',
            'include_return_label' => 'boolean',
            'include_logo_on_slip' => 'boolean',
            'custom_footer_text' => 'nullable|string|max:255',
        ]);

        OrganizationSettings::set(
            $this->getOrganizationId(),
            'fulfillment_labels',
            $validated,
            'fulfillment'
        );

        return back()->with('success', 'Label settings saved.');
    }

    public function updateWorkflow(Request $request)
    {
        $validated = $request->validate([
            'auto_assign_enabled' => 'boolean',
            'auto_assign_by' => 'required|string|in:location,round_robin',
            'auto_upload_tracking' => 'boolean',
            'order_sync_interval_minutes' => 'required|integer|min:1|max:60',
            'dropship_sla_hours' => 'required|integer|min:1|max:168',
            'priority_escalation_hours' => 'required|integer|min:1|max:72',
            'auto_mark_shipped_on_label' => 'boolean',
            'require_weight_on_pack' => 'boolean',
            'allow_partial_shipments' => 'boolean',
        ]);

        OrganizationSettings::set(
            $this->getOrganizationId(),
            'fulfillment_workflow',
            $validated,
            'fulfillment'
        );

        return back()->with('success', 'Workflow settings saved.');
    }

    public function updateReturns(Request $request)
    {
        $validated = $request->validate([
            'return_window_days' => 'required|integer|min:0|max:365',
            'restocking_fee_percent' => 'required|numeric|min:0|max:100',
            'auto_approve_enabled' => 'boolean',
            'auto_approve_under' => 'nullable|numeric|min:0',
            'require_photos' => 'boolean',
            'return_reasons' => 'required|array|min:1',
            'return_reasons.*' => 'string|max:100',
            'default_refund_method' => 'required|string|in:original_payment,store_credit',
            'auto_restock_on_receive' => 'boolean',
        ]);

        OrganizationSettings::set(
            $this->getOrganizationId(),
            'fulfillment_returns',
            $validated,
            'fulfillment'
        );

        return back()->with('success', 'Return settings saved.');
    }

    public function updateNotifications(Request $request)
    {
        $validated = $request->validate([
            'notify_on_order_import' => 'boolean',
            'notify_on_ship' => 'boolean',
            'notify_on_delivery' => 'boolean',
            'notify_on_return_request' => 'boolean',
            'stale_shipment_alert_hours' => 'required|integer|min:1|max:168',
            'sla_warning_hours' => 'required|integer|min:1|max:48',
            'daily_summary_enabled' => 'boolean',
            'notification_channel' => 'required|string|in:in_app,email,both',
        ]);

        OrganizationSettings::set(
            $this->getOrganizationId(),
            'fulfillment_notifications',
            $validated,
            'fulfillment'
        );

        return back()->with('success', 'Notification settings saved.');
    }

    public function updateShippingProvider(Request $request)
    {
        $validated = $request->validate([
            'provider' => 'required|string|in:easypost,shipstation,shippingeasy',
        ]);

        OrganizationSettings::set(
            $this->getOrganizationId(),
            'fulfillment_shipping_provider',
            $validated['provider'],
            'fulfillment'
        );

        return back()->with('success', 'Shipping provider updated.');
    }

    public function storeShipStationCredentials(Request $request)
    {
        $validated = $request->validate([
            'api_key' => 'required|string|max:255',
            'api_secret' => 'required|string|max:255',
        ]);

        $orgId = $this->getOrganizationId();

        $credential = IntegrationCredential::updateOrCreate(
            [
                'organization_id' => $orgId,
                'platform' => 'shipstation',
            ],
            [
                'client_id' => $validated['api_key'],
                'client_secret' => Crypt::encryptString($validated['api_secret']),
                'is_active' => true,
            ]
        );

        // Test connection
        try {
            $organization = Organization::findOrFail($orgId);
            $service = new ShipStationService($organization);
            $connected = $service->testConnection();

            if (! $connected) {
                $credential->update(['is_active' => false]);

                return back()->withErrors(['shipstation' => 'Failed to connect to ShipStation. Please check your credentials.']);
            }
        } catch (\Exception $e) {
            $credential->update(['is_active' => false]);

            return back()->withErrors(['shipstation' => 'Failed to connect: '.$e->getMessage()]);
        }

        return back()->with('success', 'ShipStation credentials saved and verified.');
    }

    public function storeShippingEasyCredentials(Request $request)
    {
        $validated = $request->validate([
            'api_key' => 'required|string|max:255',
            'api_secret' => 'required|string|max:255',
            'store_api_key' => 'nullable|string|max:255',
        ]);

        $orgId = $this->getOrganizationId();

        $config = [];
        if (! empty($validated['store_api_key'])) {
            $config['store_api_key'] = $validated['store_api_key'];
        }

        $credential = IntegrationCredential::updateOrCreate(
            [
                'organization_id' => $orgId,
                'platform' => 'shippingeasy',
            ],
            [
                'client_id' => $validated['api_key'],
                'client_secret' => Crypt::encryptString($validated['api_secret']),
                'config' => array_merge(
                    IntegrationCredential::where('organization_id', $orgId)
                        ->where('platform', 'shippingeasy')
                        ->first()?->config ?? [],
                    $config
                ),
                'is_active' => true,
            ]
        );

        // Test connection
        try {
            $organization = Organization::findOrFail($orgId);
            $service = new ShippingEasyService($organization);
            $connected = $service->testConnection();

            if (! $connected) {
                $credential->update(['is_active' => false]);

                return back()->withErrors(['shippingeasy' => 'Failed to connect to ShippingEasy. Please check your credentials.']);
            }
        } catch (\Exception $e) {
            $credential->update(['is_active' => false]);

            return back()->withErrors(['shippingeasy' => 'Failed to connect: '.$e->getMessage()]);
        }

        return back()->with('success', 'ShippingEasy credentials saved and verified.');
    }

    public function syncCarriers(Request $request)
    {
        $orgId = $this->getOrganizationId();
        $provider = OrganizationSettings::get($orgId, 'fulfillment_shipping_provider', 'easypost');

        try {
            $organization = Organization::findOrFail($orgId);

            if ($provider === 'shippingeasy') {
                $service = new ShippingEasyService($organization);
                $carriersResponse = $service->getCarriers();
                $carriers = $carriersResponse['carriers'] ?? $carriersResponse;

                foreach ($carriers as $carrier) {
                    CarrierAccount::updateOrCreate(
                        [
                            'organization_id' => $orgId,
                            'carrier' => $carrier['key'] ?? $carrier['name'] ?? 'Unknown',
                        ],
                        [
                            'account_name' => $carrier['name'] ?? $carrier['key'] ?? 'Unknown',
                            'is_active' => true,
                        ]
                    );
                }

                return back()->with('success', 'Carrier accounts synced from ShippingEasy.');
            }

            if ($provider === 'shipstation') {
                $service = new ShipStationService($organization);
                $carriersResponse = $service->getCarriers ?? [];

                return back()->with('success', 'Carrier accounts synced from ShipStation.');
            }

            // Default: EasyPost
            $easyPostService = new EasyPostService($organization);
            $carrierAccounts = $easyPostService->getCarrierAccounts();

            foreach ($carrierAccounts as $carrier) {
                CarrierAccount::updateOrCreate(
                    [
                        'organization_id' => $orgId,
                        'easypost_carrier_id' => $carrier['id'],
                    ],
                    [
                        'carrier' => $carrier['carrier'] ?? $carrier['type'] ?? 'Unknown',
                        'account_name' => $carrier['description'] ?? $carrier['readable'] ?? $carrier['type'],
                        'is_active' => true,
                    ]
                );
            }

            return back()->with('success', 'Carrier accounts synced from EasyPost.');
        } catch (\Exception $e) {
            return back()->withErrors(['carrier_sync' => 'Failed to sync carriers: '.$e->getMessage()]);
        }
    }

    public function updateCarrier(Request $request, CarrierAccount $carrier)
    {
        $validated = $request->validate([
            'is_active' => 'boolean',
            'is_default' => 'boolean',
        ]);

        $carrier->update($validated);

        return back()->with('success', 'Carrier updated.');
    }

    public function storePreset(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'type' => 'required|string|in:box,envelope,soft_pack,custom',
            'length' => 'required|numeric|min:0.1|max:999',
            'width' => 'required|numeric|min:0.1|max:999',
            'height' => 'required|numeric|min:0.1|max:999',
            'weight_oz' => 'nullable|numeric|min:0|max:9999',
            'is_default' => 'boolean',
        ]);

        $validated['organization_id'] = $this->getOrganizationId();
        $validated['sort_order'] = PackagePreset::where('organization_id', $validated['organization_id'])->max('sort_order') + 1;

        PackagePreset::create($validated);

        return back()->with('success', 'Package preset created.');
    }

    public function updatePreset(Request $request, PackagePreset $preset)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'type' => 'required|string|in:box,envelope,soft_pack,custom',
            'length' => 'required|numeric|min:0.1|max:999',
            'width' => 'required|numeric|min:0.1|max:999',
            'height' => 'required|numeric|min:0.1|max:999',
            'weight_oz' => 'nullable|numeric|min:0|max:9999',
            'is_default' => 'boolean',
        ]);

        $preset->update($validated);

        return back()->with('success', 'Package preset updated.');
    }

    public function destroyPreset(PackagePreset $preset)
    {
        $preset->delete();

        return back()->with('success', 'Package preset deleted.');
    }
}
