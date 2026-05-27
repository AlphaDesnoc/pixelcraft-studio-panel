<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

class PanelSessionController extends Controller
{
    public function enterUrl(Request $request): JsonResponse
    {
        $token = Str::random(64);

        Cache::put($this->cacheKey($token), (int) $request->user()->id, now()->addMinutes(2));

        return response()->json([
            'url' => URL::temporarySignedRoute(
                'mobile.enter',
                now()->addMinutes(2),
                ['token' => $token],
            ),
        ]);
    }

    public function enter(Request $request, string $token)
    {
        abort_unless($request->hasValidSignature(), 403);

        $userId = Cache::pull($this->cacheKey($token));

        abort_unless($userId, 403);

        $user = User::query()->find((int) $userId);

        abort_unless($user && $user->is_active, 403);

        Auth::login($user, remember: true);
        $request->session()->regenerate();

        return redirect()->route('dashboard');
    }

    private function cacheKey(string $token): string
    {
        return 'mobile_panel_enter:'.$token;
    }
}
