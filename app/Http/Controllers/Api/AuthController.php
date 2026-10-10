<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;

class AuthController extends Controller
{
    public function register(RegisterRequest $request)
    {
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Registration successful',
            'data' => [
                'user' => new UserResource($user),
                'access_token' => $token,
                'token_type' => 'Bearer',
            ],
        ], 201);
    }

    public function login(LoginRequest $request)
    {
        if (! Auth::attempt($request->only('email', 'password'))) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $user = Auth::user();
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Login successful',
            'data' => [
                'user' => new UserResource($user),
                'access_token' => $token,
                'token_type' => 'Bearer',
            ],
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logout successful',
        ]);
    }

    public function profile(Request $request)
    {
        return response()->json([
            'success' => true,
            'message' => 'Profile retrieved successfully',
            'data' => new UserResource($request->user()),
        ]);
    }

    public function refresh(Request $request)
    {
        $user = $request->user();
        $user->currentAccessToken()->delete();
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Token refreshed successfully',
            'data' => [
                'access_token' => $token,
                'token_type' => 'Bearer',
            ],
        ]);
    }

    /**
     * Send password reset link.
     */
    public function forgotPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $status = Password::sendResetLink(
            $request->only('email')
        );

        if ($status === Password::RESET_LINK_SENT) {
            return response()->json([
                'success' => true,
                'message' => 'Password reset link sent to your email.',
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Unable to send password reset link. Please check your email address.',
        ], 422);
    }

    /**
     * Reset password with token.
     */
    public function resetPassword(Request $request)
    {
        $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => 'required|min:8|confirmed',
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return response()->json([
                'success' => true,
                'message' => 'Password has been reset successfully.',
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Unable to reset password. The token may be invalid or expired.',
        ], 422);
    }

    /**
     * Verify 2FA code and complete login.
     */
    public function twoFactorChallenge(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'code' => 'required|string',
            'recovery_code' => 'nullable|string',
        ]);

        $user = User::where('email', $request->email)->first();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found.',
            ], 404);
        }

        if (! $user->two_factor_secret) {
            return response()->json([
                'success' => false,
                'message' => '2FA is not enabled for this account.',
            ], 400);
        }

        $valid = false;

        // Try recovery code first if provided
        if ($request->filled('recovery_code')) {
            $recoveryCodes = json_decode(decrypt($user->two_factor_recovery_codes), true);

            if (in_array($request->recovery_code, $recoveryCodes)) {
                $valid = true;
                // Remove used recovery code
                $recoveryCodes = array_diff($recoveryCodes, [$request->recovery_code]);
                $user->forceFill([
                    'two_factor_recovery_codes' => encrypt(json_encode($recoveryCodes)),
                ])->save();
            }
        } else {
            // Verify TOTP code
            try {
                $provider = app(TwoFactorAuthenticationProvider::class);
                $valid = $provider->verify(
                    decrypt($user->two_factor_secret),
                    $request->code
                );
            } catch (\Exception $e) {
                $valid = false;
            }
        }

        if (! $valid) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid verification code.',
            ], 401);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => '2FA verification successful',
            'data' => [
                'user' => new UserResource($user),
                'access_token' => $token,
                'token_type' => 'Bearer',
            ],
        ]);
    }

    /**
     * Update user profile.
     */
    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:users,email,'.$user->id,
            'phone' => 'nullable|string|max:20',
            'timezone' => 'nullable|string|max:50',
        ]);

        $user->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Profile updated successfully.',
            'data' => new UserResource($user->fresh()),
        ]);
    }

    /**
     * Update user password.
     */
    public function updatePassword(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'current_password' => 'required',
            'password' => 'required|min:8|confirmed',
        ]);

        if (! Hash::check($request->current_password, $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['The current password is incorrect.'],
            ]);
        }

        $user->update([
            'password' => Hash::make($request->password),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Password changed successfully.',
        ]);
    }

    /**
     * Get active sessions.
     */
    public function sessions(Request $request)
    {
        $user = $request->user();

        // Get sessions from the sessions table (if using database sessions)
        $sessions = DB::table('sessions')
            ->where('user_id', $user->id)
            ->orderByDesc('last_activity')
            ->get()
            ->map(function ($session) {
                $agent = new \Jenssegers\Agent\Agent;
                $agent->setUserAgent($session->user_agent);

                return [
                    'id' => $session->id,
                    'ip_address' => $session->ip_address,
                    'user_agent' => $session->user_agent,
                    'device' => $agent->device() ?: 'Unknown',
                    'platform' => $agent->platform() ?: 'Unknown',
                    'browser' => $agent->browser() ?: 'Unknown',
                    'is_current' => $session->id === session()->getId(),
                    'last_active' => \Carbon\Carbon::createFromTimestamp($session->last_activity)->toIso8601String(),
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $sessions,
        ]);
    }

    /**
     * Revoke a session.
     */
    public function revokeSession(Request $request, string $sessionId)
    {
        $user = $request->user();

        // Verify the session belongs to the user
        $session = DB::table('sessions')
            ->where('id', $sessionId)
            ->where('user_id', $user->id)
            ->first();

        if (! $session) {
            return response()->json([
                'success' => false,
                'message' => 'Session not found.',
            ], 404);
        }

        // Cannot revoke current session
        if ($sessionId === session()->getId()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot revoke current session. Use logout instead.',
            ], 400);
        }

        DB::table('sessions')->where('id', $sessionId)->delete();

        return response()->json([
            'success' => true,
            'message' => 'Session revoked successfully.',
        ]);
    }
}
