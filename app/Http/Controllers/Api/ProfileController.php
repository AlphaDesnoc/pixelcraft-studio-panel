<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\NotificationPreferences;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class ProfileController extends Controller
{
    public function notifications(Request $request): JsonResponse
    {
        return response()->json([
            'preferences' => NotificationPreferences::forUser($request->user()),
            'labels' => NotificationPreferences::labels(),
        ]);
    }

    public function updateNotifications(Request $request): JsonResponse
    {
        $user = $request->user();
        $labels = NotificationPreferences::labels();

        $rules = [];
        foreach (array_keys($labels) as $type) {
            $rules["preferences.{$type}"] = ['nullable', 'boolean'];
        }

        $validated = $request->validate($rules);

        $user->notification_preferences = NotificationPreferences::sanitize(
            $validated['preferences'] ?? [],
        );
        $user->save();

        return response()->json([
            'preferences' => NotificationPreferences::forUser($user),
        ]);
    }

    public function updatePassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', Password::defaults(), 'confirmed'],
        ]);

        $request->user()->update([
            'password' => Hash::make($validated['password']),
        ]);

        return response()->json(['ok' => true]);
    }
}
