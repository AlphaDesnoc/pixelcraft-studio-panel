<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Api\ApiUserPayload;
use App\Support\Api\PendingTwoFactorLogin;
use App\Support\TwoFactorRecovery;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PragmaRX\Google2FA\Google2FA;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['required', 'string', 'max:255'],
        ]);

        $this->ensureIsNotRateLimited($request);

        $user = User::query()->where('email', $validated['email'])->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            RateLimiter::hit($this->throttleKey($request));

            throw ValidationException::withMessages([
                'email' => [trans('auth.failed')],
            ]);
        }

        if (! $user->is_active) {
            throw ValidationException::withMessages([
                'email' => ['Ce compte a été désactivé. Contactez un administrateur.'],
            ]);
        }

        RateLimiter::clear($this->throttleKey($request));

        if ($user->two_factor_confirmed_at && $user->two_factor_secret) {
            $loginToken = PendingTwoFactorLogin::create($user->id, false);

            return response()->json([
                'two_factor_required' => true,
                'login_token' => $loginToken,
            ]);
        }

        return response()->json($this->issueToken($user, $validated['device_name']));
    }

    public function twoFactorChallenge(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'login_token' => ['required', 'string'],
            'code' => ['required', 'string'],
            'device_name' => ['required', 'string', 'max:255'],
        ]);

        $pending = PendingTwoFactorLogin::consume($validated['login_token']);

        if (! $pending) {
            throw ValidationException::withMessages([
                'login_token' => ['Session expirée. Reconnectez-vous.'],
            ]);
        }

        $user = User::query()->find($pending['user_id']);

        if (! $user || ! $user->is_active || ! $user->two_factor_confirmed_at || ! $user->two_factor_secret) {
            throw ValidationException::withMessages([
                'login_token' => ['Session expirée. Reconnectez-vous.'],
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
                'code' => ['Code incorrect ou expiré.'],
            ]);
        }

        return response()->json($this->issueToken($user, $validated['device_name']));
    }

    public function user(Request $request): JsonResponse
    {
        return response()->json([
            'user' => ApiUserPayload::make($request->user()),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json(['ok' => true]);
    }

    /**
     * @return array{token: string, user: array<string, mixed>}
     */
    private function issueToken(User $user, string $deviceName): array
    {
        $user->tokens()->where('name', $deviceName)->delete();

        $token = $user->createToken($deviceName)->plainTextToken;

        return [
            'token' => $token,
            'user' => ApiUserPayload::make($user),
        ];
    }

    private function ensureIsNotRateLimited(Request $request): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey($request), 5)) {
            return;
        }

        event(new Lockout($request));

        $seconds = RateLimiter::availableIn($this->throttleKey($request));

        throw ValidationException::withMessages([
            'email' => [trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ])],
        ]);
    }

    private function throttleKey(Request $request): string
    {
        return Str::transliterate(Str::lower($request->string('email')).'|'.$request->ip());
    }
}
