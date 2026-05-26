<?php

namespace App\Http\Controllers;

use App\Support\TwoFactorRecovery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use PragmaRX\Google2FA\Google2FA;

class ProfileTwoFactorController extends Controller
{
    public function setup(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->is_admin) {
            return response()->json([
                'message' => 'La double authentification est réservée aux administrateurs.',
            ], 403);
        }

        if ($user->two_factor_confirmed_at) {
            return response()->json([
                'message' => 'La double authentification est déjà activée.',
            ], 422);
        }

        $google = new Google2FA();
        $secret = $google->generateSecretKey();
        $request->session()->put('two_factor_pending', Crypt::encryptString($secret));

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

        $pending = $request->session()->get('two_factor_pending');

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

        $user = $request->user();
        $plainCodes = TwoFactorRecovery::generatePlainCodes();
        $hashedCodes = TwoFactorRecovery::hashCodes($plainCodes);

        $user->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_recovery_codes' => $hashedCodes,
            'two_factor_confirmed_at' => now(),
        ]);
        $user->save();

        $request->session()->forget('two_factor_pending');

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

        $request->session()->forget('two_factor_pending');

        return response()->json(['ok' => true]);
    }
}
