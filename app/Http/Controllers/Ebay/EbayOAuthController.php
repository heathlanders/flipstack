<?php

namespace App\Http\Controllers\Ebay;

use App\Http\Controllers\Controller;
use App\Models\Catalog\Product;
use App\Models\Integration\IntegrationCredential;
use App\Models\Integration\OAuthToken;
use App\Services\EbayOAuthService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class EbayOAuthController extends Controller
{
    public function callback(Request $request, string $environment)
    {
        try {
            // Validate the state parameter
            $state = decrypt($request->state);

            if (! isset($state['organization_id']) || ! isset($state['credential_id'])) {
                throw new \Exception('Invalid state parameter');
            }

            // Verify timestamp is recent (within 10 minutes)
            if (isset($state['timestamp']) && now()->timestamp - $state['timestamp'] > 600) {
                throw new \Exception('OAuth state expired');
            }

            // Get the credential
            $credential = IntegrationCredential::findOrFail($state['credential_id']);

            // Verify the user belongs to this organization
            if ($request->user()->organization_id !== $credential->organization_id && ! $request->user()->isSuperAdmin()) {
                abort(403, 'You do not have access to this organization');
            }

            // Check if this should be a user-level token
            $userId = $request->query('user_level') === 'true'
                ? $request->user()->id
                : null;

            // Handle error response from eBay
            if ($request->has('error')) {
                Log::warning('eBay OAuth error', [
                    'error' => $request->error,
                    'error_description' => $request->error_description,
                ]);

                return redirect()->route('organization.settings')
                    ->withErrors(['ebay' => 'eBay authorization failed: '.$request->error_description]);
            }

            // Validate authorization code
            if (! $request->has('code')) {
                throw new \Exception('No authorization code received');
            }

            // Exchange code for token
            $service = new EbayOAuthService($credential);
            $token = $service->exchangeCodeForToken($request->code, $userId);

            // Auto-register the per-credential ECN webhook so order notifications flow
            // without manual portal setup.
            $this->registerNotifications($credential);

            // Default new credentials to auto-sync polling so missed/legacy webhooks
            // still get covered by the 5-min poll.
            if (! $credential->auto_sync_enabled) {
                $credential->update(['auto_sync_enabled' => true]);
            }

            $message = $userId
                ? 'eBay connected successfully to your account'
                : 'eBay connected successfully to your organization';

            // Prompt inventory import if the organization has no products
            $organizationId = $credential->organization_id;
            $hasProducts = Product::where('organization_id', $organizationId)->exists();

            return redirect()->route('organization.settings')
                ->with('success', $message)
                ->with('show_ebay_import_prompt', ! $hasProducts);

        } catch (\Exception $e) {
            Log::error('eBay OAuth callback error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()->route('organization.settings')
                ->withErrors(['ebay' => 'Failed to connect to eBay: '.$e->getMessage()]);
        }
    }

    protected function registerNotifications(IntegrationCredential $credential): void
    {
        try {
            Artisan::call('ebay:register-notifications', [
                '--credential' => $credential->id,
            ]);

            Log::info('eBay notifications auto-registered for new credential', [
                'credential_id' => $credential->id,
                'organization_id' => $credential->organization_id,
            ]);
        } catch (\Throwable $e) {
            // Don't fail the OAuth flow if registration breaks; the 5-min poller
            // is a fallback, and admins can retry from the integrations UI.
            Log::warning('eBay notification auto-registration failed; falling back to poller', [
                'credential_id' => $credential->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function disconnect(Request $request, string $environment)
    {
        $organization = $request->user()->organization;

        // Determine if disconnecting user-level or org-level
        $userId = $request->query('user_level') === 'true'
            ? $request->user()->id
            : null;

        $token = OAuthToken::where('organization_id', $organization->id)
            ->where('platform', 'ebay')
            ->where('environment', $environment)
            ->where('user_id', $userId)
            ->active()
            ->first();

        if (! $token) {
            return back()->withErrors(['ebay' => 'No active eBay connection found']);
        }

        // Get the credential to revoke the token
        $credential = $token->integrationCredential;
        if ($credential) {
            $service = new EbayOAuthService($credential);
            $service->revokeToken($token);
        } else {
            // Just deactivate if credential is missing
            $token->deactivate();
        }

        $message = $userId
            ? 'eBay disconnected from your account'
            : 'eBay disconnected from your organization';

        return back()->with('success', $message);
    }

    public function refreshToken(Request $request, string $environment)
    {
        $organization = $request->user()->organization;

        $userId = $request->query('user_level') === 'true'
            ? $request->user()->id
            : null;

        $token = OAuthToken::where('organization_id', $organization->id)
            ->where('platform', 'ebay')
            ->where('environment', $environment)
            ->where('user_id', $userId)
            ->active()
            ->first();

        if (! $token) {
            return back()->withErrors(['ebay' => 'No active eBay token found']);
        }

        $credential = $token->integrationCredential;
        if (! $credential) {
            return back()->withErrors(['ebay' => 'Integration credentials not found']);
        }

        try {
            $service = new EbayOAuthService($credential);
            $service->refreshToken($token);

            return back()->with('success', 'eBay token refreshed successfully');
        } catch (\Exception $e) {
            Log::error('Failed to manually refresh eBay token', [
                'token_id' => $token->id,
                'error' => $e->getMessage(),
            ]);

            return back()->withErrors(['ebay' => 'Failed to refresh token: '.$e->getMessage()]);
        }
    }
}
