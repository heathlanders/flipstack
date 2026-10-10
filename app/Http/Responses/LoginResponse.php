<?php

namespace App\Http\Responses;

use App\Models\System\OnboardingProgress;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;

class LoginResponse implements LoginResponseContract
{
    public function toResponse($request)
    {
        $user = $request->user();

        $onboarding = OnboardingProgress::where('user_id', $user->id)->first();

        if (! $onboarding || $onboarding->isInProgress()) {
            return $request->wantsJson()
                ? response()->json(['two_factor' => false])
                : redirect()->to('/onboarding');
        }

        return $request->wantsJson()
            ? response()->json(['two_factor' => false])
            : redirect()->intended('/dashboard');
    }
}
