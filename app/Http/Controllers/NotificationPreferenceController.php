<?php

namespace App\Http\Controllers;

use App\Support\NotificationPreferences;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class NotificationPreferenceController extends Controller
{
    public function update(Request $request): RedirectResponse
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

        return back()->with('success', 'Préférences de notifications enregistrées.');
    }
}
