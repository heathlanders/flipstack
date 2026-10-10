<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class PasswordChangeController extends Controller
{
    /**
     * Show the password change form.
     */
    public function show(): Response
    {
        return Inertia::render('Auth/ChangePassword', [
            'mustChange' => auth()->user()->password_change_required ?? false,
        ]);
    }

    /**
     * Handle the password change request.
     */
    public function update(Request $request)
    {
        $user = auth()->user();

        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user->update([
            'password' => Hash::make($validated['password']),
            'password_change_required' => false,
        ]);

        return redirect()
            ->intended(route('dashboard'))
            ->with('success', 'Password changed successfully.');
    }
}
