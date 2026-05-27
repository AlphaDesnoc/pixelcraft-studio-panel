<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\TwoFactorRecovery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use PragmaRX\Google2FA\Google2FA;

class ProfileTwoFactorController extends Controller
{
    private const CACHE_PREFIX = 'api_2fa_pending:';

    private const TTL_SECONDS = 300;

    public function setup(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->two_factor_confirmed_at) {
            return response()->json([
                'message' => 'La double authentification est déjà activée.',
            ], 422);
        }

        $google = new Google2FA();
        $secret = $google->generateSecretKey();

        Cache::put(
            self::CACHE_PREFIX.$user->id,
            Crypt::encryptString($secret),
            self::TTL_SECONDS,
        );

        $uri = $google->getQRCodeUrl(config('app.name', 'Pixelcraft'), $user->email ?? 'user', $secret);

        return response()->json([
            'otpauth_uri' => $uri,
        ]);
    }

    public function confirm(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'size:6'],
        ]);

        $user = $request->user();
        $pending = Cache::get(self::CACHE_PREFIX.$user->id);

        if (! $pending || ! is_string($pending)) {
            return response()->json([
                'message' => 'Aucune configuration en cours. Lancez une nouvelle installation.',
            ], 422);
        }

        $secret = Crypt::decryptString($pending);
        $google = new Google2FA();

        if (! $google->verifyKey($secret, $validated['code'], 4)) {
            return response()->json([
                'message' => 'Code incorrect ou expiré.',
            ], 422);
        }

        $plainCodes = TwoFactorRecovery::generatePlainCodes();
        $hashedCodes = TwoFactorRecovery::hashCodes($plainCodes);

        $user->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_recovery_codes' => $hashedCodes,
            'two_factor_confirmed_at' => now(),
        ]);
        $user->save();

        Cache::forget(self::CACHE_PREFIX.$user->id);

        return response()->json([
            'ok' => true,
            'recovery_codes' => $plainCodes,
        ]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $user = $request->user();

        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ]);
        $user->save();

        Cache::forget(self::CACHE_PREFIX.$user->id);

        return response()->json(['ok' => true]);
    }
}
