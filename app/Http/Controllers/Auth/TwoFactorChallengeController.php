<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\TwoFactorRecovery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use PragmaRX\Google2FA\Google2FA;

class TwoFactorChallengeController extends Controller
{
    public function create(Request $request): Response|RedirectResponse
    {
        if (! $request->session()->has('login.id')) {
            return redirect()->route('login');
        }

        return Inertia::render('Auth/TwoFactorChallenge');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string'],
        ]);

        $userId = $request->session()->get('login.id');

        if (! $userId) {
            return redirect()->route('login');
        }

        $user = User::query()->find($userId);

        if (! $user || ! $user->is_active || ! $user->two_factor_confirmed_at || ! $user->two_factor_secret) {
            $request->session()->forget(['login.id', 'login.remember']);

            throw ValidationException::withMessages([
                'code' => 'Session expirée. Reconnectez-vous.',
            ]);
        }

        $code = trim($validated['code']);
        $verified = false;

        if (preg_match('/^\d{6}$/', $code)) {
            $google = new Google2FA();
            $verified = $google->verifyKey($user->two_factor_secret, $code, 4);
        }

        if (! $verified) {
            $codes = $user->two_factor_recovery_codes ?? [];
            if (is_array($codes) && TwoFactorRecovery::verifyAndConsume($codes, $code)) {
                $user->forceFill(['two_factor_recovery_codes' => $codes])->save();
                $verified = true;
            }
        }

        if (! $verified) {
            throw ValidationException::withMessages([
                'code' => 'Code incorrect ou expiré.',
            ]);
        }

        $remember = (bool) $request->session()->get('login.remember', false);
        $request->session()->forget(['login.id', 'login.remember']);

        Auth::login($user, $remember);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }
}
