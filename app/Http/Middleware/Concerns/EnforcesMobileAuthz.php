<?php

namespace App\Http\Middleware\Concerns;

use App\Models\User\User;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Shared deny path for the mobile API authz middlewares (api.permission / api.role).
 *
 * Runs in soft (audit-only) mode by default: every would-be denial writes a
 * `security.mobile.authz_denied` audit row but the request proceeds. Flip
 * MOBILE_AUTHZ_ENFORCE=true to return a hard 403 instead.
 */
trait EnforcesMobileAuthz
{
    /**
     * Record a denial. Returns a 403 response when enforcing, or null to let the
     * request continue (soft mode). The audit row is written in BOTH modes.
     */
    protected function denyMobileAuthz(Request $request, ?User $user, string $gateType, string $required): ?Response
    {
        $enforce = (bool) config('mobile.authz_enforce', false);

        audit_event(
            'security.mobile.authz_denied',
            'Mobile API authz '.($enforce ? 'denied' : 'would-deny').": user lacks {$gateType} [{$required}]",
            null,
            [
                'user_id' => $user?->id,
                'primary_role' => $user?->getPrimaryRole(),
                'route' => $request->route()?->getName() ?? $request->path(),
                'method' => $request->method(),
                'gate_type' => $gateType,
                'required' => $required,
                'mode' => $enforce ? 'enforce' : 'soft',
                'source' => 'mobile',
            ],
            'warning',
            'security',
            $user,
        );

        if ($enforce) {
            return response()->json([
                'success' => false,
                'message' => 'Insufficient permissions for this operation',
            ], 403);
        }

        return null;
    }
}
