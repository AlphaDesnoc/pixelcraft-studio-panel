<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\PushNotifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PushTokenController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'platform' => ['required', 'string', 'in:ios,android,web'],
            'token' => ['required', 'string', 'max:512'],
            'device_name' => ['nullable', 'string', 'max:255'],
        ]);

        PushNotifier::register(
            $request->user(),
            $validated['platform'],
            $validated['token'],
            $validated['device_name'] ?? null,
        );

        return response()->json(['ok' => true]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string', 'max:512'],
        ]);

        PushNotifier::unregister($request->user(), $validated['token']);

        return response()->json(['ok' => true]);
    }
}
